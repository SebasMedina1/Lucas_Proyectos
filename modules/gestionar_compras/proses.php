<?php
session_start();
if (empty($_SESSION['username'])) { die("Sesión expirada."); }

$accion = $_GET['act'] ?? '';
$accionesPermitidas = ['insert','aprobar'];
if (!in_array($accion, $accionesPermitidas, true)) {
  http_response_code(400);
  die("Acción inválida.");
}

require "../../config/database.php";
$dsn = "pgsql:host=$host;port=$port;dbname=$database;";

/** ========= BITÁCORA ========= */
function bitacora(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void {
  $useSavepoint = false;
  try {
    if ($pdo->inTransaction()) {
      $useSavepoint = true;
      $pdo->exec('SAVEPOINT bitacora_sp');
    }
    $stmt = $pdo->prepare("
      INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
      VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
    ");
    $stmt->execute([
      ':id_usuario'  => $idUsuario,
      ':entidad'     => 'Gestionar compra / Factura',
      ':id_registro' => $idRegistro,
      ':accion'      => strtoupper($accion),
      ':descripcion' => $descripcion
    ]);
    if ($useSavepoint) {
      $pdo->exec('RELEASE SAVEPOINT bitacora_sp');
    }
  } catch (Throwable $e) {
    if ($useSavepoint) {
      try { $pdo->exec('ROLLBACK TO SAVEPOINT bitacora_sp'); } catch (Throwable $inner) {}
      try { $pdo->exec('RELEASE SAVEPOINT bitacora_sp'); } catch (Throwable $inner) {}
    }
    error_log("Bitácora falló: ".$e->getMessage());
  }
}

try {
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false
  ]);

  function redirect_or_dump(string $url, array $payload = []) {
  if (!empty($_GET['debug'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['redirect_to'=>$url, 'payload'=>$payload], JSON_UNESCAPED_UNICODE);
    exit;
  } else {
    header("Location: $url");
    exit;
  }
}

function log_dbg($msg, $ctx = []) {
  @file_put_contents(__DIR__.'/aprobar_factura.log',
    '['.date('Y-m-d H:i:s')."] $msg ".json_encode($ctx, JSON_UNESCAPED_UNICODE).PHP_EOL,
    FILE_APPEND
  );
}

function execOrFail(PDO $pdo, PDOStatement $stmt, array $params, string $label) {
  if (!$stmt->execute($params)) {
    $err = $stmt->errorInfo();
    throw new Exception("Fallo SQL en $label: ".$err[2]);
  }
  return $stmt->rowCount();
}

function obtenerCuotasDesdeFactura(array $factura): int {
  // fac_cuotas no existe en factura_compra, se obtiene desde fac_plazo
  $plazoTxt = strtoupper((string)($factura['fac_plazo'] ?? ''));
  if (preg_match('/(\d+)/', $plazoTxt, $matches)) {
    return (int)$matches[1];
  }
  return 0;
}


if ($accion === 'aprobar') {



  try {

    $factId = isset($_GET['fact_id']) ? (int)$_GET['fact_id'] : 0;
    if ($factId <= 0) throw new Exception("Factura inválida.");

    // Confirma que esta conexión está en la BD esperada
    $pdo->query('SELECT 1'); // fuerza conexión

    $stmtUser = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
    execOrFail($pdo, $stmtUser, [':u' => $_SESSION['username']], 'SELECT usuario');
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if (!$userRow) throw new Exception("Usuario no encontrado.");
    $idUsuario = (int)$userRow['id_usuario'];

    $pdo->beginTransaction();

    $stmtFac = $pdo->prepare("
      SELECT f.*
      FROM factura_compra f
      WHERE f.id_factura_compra = :id
      FOR UPDATE
    ");
    execOrFail($pdo, $stmtFac, [':id' => $factId], 'SELECT factura');
    $factura = $stmtFac->fetch(PDO::FETCH_ASSOC);
    if (!$factura) throw new Exception("Factura inexistente.");

    $estadoFactura = strtoupper((string)$factura['fac_estado']);
    if ($estadoFactura === 'EMITIDA' || $estadoFactura === 'APROBADO') {
      $pdo->rollBack();
      header("Location: view.php?alert=6");
      exit;
    }
    if ($estadoFactura === 'ANULADO') {
      $pdo->rollBack();
      header("Location: view.php?alert=7");
      exit;
    }

    $id_oc = (int)$factura['id_orden_compra'];

    $stmtRel = $pdo->prepare("
      SELECT 
        oc.id_presupuesto_compra AS pre_id,
        pc.id_pedido_compra      AS ped_id
      FROM orden_de_compra oc
      LEFT JOIN presupuesto_compra pc ON pc.id_presupuesto_compra = oc.id_presupuesto_compra
      WHERE oc.id_orden_compra = :oc
      LIMIT 1
    ");
    execOrFail($pdo, $stmtRel, [':oc' => $id_oc], 'SELECT relación OC');
    $rel = $stmtRel->fetch(PDO::FETCH_ASSOC) ?: [];

    $pre_id          = (int)($rel['pre_id'] ?? 0);
    $ped_id          = (int)($rel['ped_id'] ?? 0);
    // Obtener tipo_compra desde tipo_operacion de la factura
    $tipo_compra = strtoupper(trim($factura['tipo_operacion'] ?? 'CONTADO'));
    $fac_plazo_text  = (string)($factura['fac_plazo'] ?? 'CONTADO');
    // Obtener cuotas e interés desde cuentas_pagar (no están en factura_compra)
    // Nota: cuentas_pagar no tiene id_factura_compra, se relaciona por proveedor
    // Las cuotas se obtienen desde fac_plazo (texto) o se calculan desde fecha_vencimiento
    $fac_cuotas = 0;
    if (!empty($fac_plazo_text) && is_numeric($fac_plazo_text)) {
        $fac_cuotas = (int)$fac_plazo_text;
    } else {
        // Intentar obtener desde cuentas_pagar
        $stCtaInfo = $pdo->prepare("
          SELECT cp.monto_total, cp.estado, cp.fecha_vencimiento, cp.fecha_emision
          FROM cuentas_pagar cp
          JOIN factura_compra fc ON fc.id_factura_compra = :f
          JOIN orden_de_compra oc ON oc.id_orden_compra = fc.id_orden_compra
          WHERE cp.id_proveedor = oc.id_proveedor 
            AND cp.fecha_emision = fc.fact_fecha_compra
          LIMIT 1
        ");
        $stCtaInfo->execute([':f' => $factId]);
        $ctaInfo = $stCtaInfo->fetch(PDO::FETCH_ASSOC);
        if ($ctaInfo && !empty($ctaInfo['fecha_emision']) && !empty($ctaInfo['fecha_vencimiento'])) {
            // Calcular cuotas desde diferencia de fechas (asumiendo 30 días por cuota)
            $fecEmision = new DateTime($ctaInfo['fecha_emision']);
            $fecVenc = new DateTime($ctaInfo['fecha_vencimiento']);
            $diff = $fecEmision->diff($fecVenc);
            $dias = $diff->days;
            $fac_cuotas = max(1, intdiv($dias, 30));
        }
    }
    // El interés se calcula desde el total y las cuotas, no se guarda directamente
    $fac_interes_pct = 0; // Se calculará si es necesario
    $fac_total_guard = (int)($factura['fac_total'] ?? 0);
    $id_sucursal_fac = (int)($factura['id_sucursal'] ?? 0);
    $recep_prev      = (int)($factura['fac_remision'] ?? 0);
    // Asegura formato compatible con la columna de destino
    $fecha_hora_fact = ($factura['fact_fecha_compra'] ?? date('Y-m-d H:i:s'));
    // Si iva_fecha es DATE, usa Y-m-d
    $fecha_iva = substr($fecha_hora_fact, 0, 10);

    // Detalle
    $sqlDet = $pdo->prepare("
      SELECT fd.id_materia_prima, fd.fac_cantidad, fd.fac_precio, mp.iva_id
      FROM factura_detalle_compra fd
      JOIN materia_prima mp ON mp.id_materia_prima = fd.id_materia_prima
      WHERE fd.id_factura_compra = :fact
    ");
    execOrFail($pdo, $sqlDet, [':fact' => $factId], 'SELECT detalle');
    $detalle = $sqlDet->fetchAll(PDO::FETCH_ASSOC);
    if (empty($detalle)) throw new Exception("La factura no posee detalle para aprobar.");

    // Mapas IVA
    $rowsIva = $pdo->query("SELECT iva_id, iva_descri FROM tipo_iva")->fetchAll(PDO::FETCH_ASSOC);
    $descri_por_id  = []; 
    $divisor_por_id = [];
    $ivaExentoId    = null;
    foreach ($rowsIva as $r) {
      $idIva = (int)$r['iva_id'];
      // Normaliza posibles variantes: "IVA 10%", "iva10", etc.
      $descr = strtolower(preg_replace('/\s|%/','', (string)$r['iva_descri']));
      $descri_por_id[$idIva] = $descr;
      if (in_array($descr, ['iva10','iva_10','10','10porc','iva10%']))      $divisor_por_id[$idIva] = 11;
      elseif (in_array($descr, ['iva5','iva_5','5','5porc','iva5%']))       $divisor_por_id[$idIva] = 21;
      else {
        $divisor_por_id[$idIva] = 0;
        if ($ivaExentoId === null) $ivaExentoId = $idIva;
      }
    }

    // Recalculo total/IVA
    $total_calc = 0;
    $iva_por_id = [];
    $sumCant    = [];
    $items_ok   = [];

    foreach ($detalle as $row) {
      $id_prod = (int)$row['id_materia_prima'];
      $cant    = (int)$row['fac_cantidad'];
      $precio  = (int)$row['fac_precio'];
      $iva_id  = (int)$row['iva_id'];
      if ($id_prod <= 0 || $cant <= 0 || $precio <= 0) continue;

      $sub = $cant * $precio;
      $total_calc += $sub;

      $div = $divisor_por_id[$iva_id] ?? 0;
      $iva_monto = ($div > 0) ? intdiv($precio * $cant, $div) : 0;
      $iva_por_id[$iva_id] = ($iva_por_id[$iva_id] ?? 0) + $iva_monto;

      $items_ok[] = ['id_materia_prima'=>$id_prod,'cant'=>$cant,'precio'=>$precio];
      $sumCant[$id_prod] = ($sumCant[$id_prod] ?? 0) + $cant;
    }

    if ($total_calc <= 0) throw new Exception("Total calculado inválido para la factura.");

    $fac_total_base = $total_calc;
    $fac_total_con_interes = $fac_total_base;
    $interesMonto = 0;

    // Calcular total con intereses para la cuenta a pagar (si aplica)
    if ($tipo_compra === 'CREDITO' && $fac_cuotas > 0 && $fac_interes_pct > 0) {
      $cuotaBase   = intdiv($fac_total_base, max(1, $fac_cuotas));
      $interesCta  = (int) round($cuotaBase * ($fac_interes_pct / 100));
      $fac_total_con_interes = ($cuotaBase + $interesCta) * $fac_cuotas;
      $interesMonto = max(0, $fac_total_con_interes - $fac_total_base);
    }
    
    // IMPORTANTE: La factura NO incluye intereses (solo el valor real de la mercadería)
    // Los intereses van solo en la cuenta a pagar (financiamiento)
    $fac_total_calculado = $fac_total_base; // Factura sin intereses
    
    // Siempre usar el total recalculado en la aprobación, ya que es el correcto basado en el detalle actual
    // Si hay diferencia con el total guardado, registrar en log pero usar el recalculado
    if ($fac_total_guard > 0 && abs($fac_total_guard - $fac_total_calculado) > 1) {
      // Registrar la diferencia pero continuar con el total recalculado
      error_log("Aprobación factura #$factId: Total guardado ($fac_total_guard) difiere del recalculado ($fac_total_calculado). Usando recalculado.");
    }
    
    // Usar siempre el total recalculado que es el correcto (SIN intereses para la factura)
    $fac_total_confirmado = $fac_total_calculado;

    // IVA COMPRA
    $stDelIva = $pdo->prepare("DELETE FROM iva_compra WHERE id_factura_compra = :f");
    execOrFail($pdo, $stDelIva, [':f' => $factId], 'DELETE IVA_COMPRA');

    // Consolidar todos los tipos de IVA en un solo registro
    $iva_exento_total = 0;
    $iva_5_total = 0;
    $iva_10_total = 0;

    foreach ($iva_por_id as $iva_id => $monto) {
      if ($monto <= 0) continue;
      $descr = $descri_por_id[$iva_id] ?? '';
      $div   = $divisor_por_id[$iva_id] ?? 0;

      // Acumular según el tipo de IVA
      if ($div === 0) {
        $iva_exento_total += $monto;
      } elseif (strpos($descr, '5') !== false) {
        $iva_5_total += $monto;
      } elseif (strpos($descr, '10') !== false) {
        $iva_10_total += $monto;
      } else {
        $iva_exento_total += $monto; // Por defecto, exento
      }
    }

    // Insertar un solo registro con los totales consolidados
    // NOTA: iva_id es autoincremental (clave primaria), no se debe incluir en el INSERT
    $sqlIva = $pdo->prepare("
      INSERT INTO iva_compra (id_factura_compra, iva_fecha, iva_exento, iva_5, iva_10)
      VALUES (:f, :fecha, :ex, :iva5, :iva_10)
    ");

    execOrFail($pdo, $sqlIva, [
      ':f'      => $factId,
      ':fecha'  => $fecha_iva,
      ':ex'     => $iva_exento_total,
      ':iva5'   => $iva_5_total,
      ':iva_10' => $iva_10_total
    ], "INSERT IVA_COMPRA");

    // Proteger el flujo si bitacora falla
    try {
      bitacora($pdo, $idUsuario, 'ALTA', "IVA COMPRA Factura #$factId | Exento:$iva_exento_total | IVA5:$iva_5_total | IVA10:$iva_10_total", $factId);
    } catch (Throwable $e) {
    }

    // FINALIZAR OC / PRESUPUESTO / PEDIDO
    $u1 = $pdo->prepare("UPDATE orden_de_compra SET orden_estado='FINALIZADO' WHERE id_orden_compra=:oc");
    execOrFail($pdo, $u1, [':oc' => $id_oc], 'UPDATE orden_de_compra');
    try { bitacora($pdo,$idUsuario,'MODIFICACION',"OC #$id_oc → FINALIZADO (Factura #$factId)",$id_oc); } catch(Throwable $e){}

    if ($pre_id > 0) {
      $u2 = $pdo->prepare("UPDATE presupuesto_compra SET presu_estado='FINALIZADO' WHERE id_presupuesto_compra=:pre");
      execOrFail($pdo, $u2, [':pre'=>$pre_id], 'UPDATE presupuesto_compra');
      try { bitacora($pdo,$idUsuario,'MODIFICACION',"Presupuesto #$pre_id → FINALIZADO (Factura #$factId)",$pre_id); } catch(Throwable $e){}
    }

    if ($ped_id > 0) {
      $u3 = $pdo->prepare("UPDATE pedidos_compra SET pedido_estado='FINALIZADO' WHERE id_pedido_compra=:ped");
      execOrFail($pdo, $u3, [':ped'=>$ped_id], 'UPDATE pedidos_compra');
      try { bitacora($pdo,$idUsuario,'MODIFICACION',"Pedido #$ped_id → FINALIZADO (Factura #$factId)",$ped_id); } catch(Throwable $e){}
    }

    if ($pre_id <= 0 && $ped_id <= 0) {
      try { bitacora($pdo,$idUsuario,'ALTA', "No se halló relación OC→Presupuesto→Pedido para OC #$id_oc (Factura #$factId)", $id_oc); } catch(Throwable $e){}
    }

    // IMPACTO DE STOCK (solo si no hubo recepción previa)
    /* 
    Si fac_remision es 0, se usan los items_ok de la factura. Si fac_remision es 1 y 
    la factura tiene id_nota_remision_compra, se reemplazan las cantidades por las de nota_remision_detalle_compra
    */
    // Nota: id_nota_remision_compra no existe en factura_compra, se obtiene desde nota_remision_compra
    $stNrId = $pdo->prepare("SELECT id_nota_remision FROM nota_remision_compra WHERE id_factura_compra = :f LIMIT 1");
    $stNrId->execute([':f' => $factId]);
    $nrIdRow = $stNrId->fetch(PDO::FETCH_ASSOC);
    $id_nota_remision_compra = $nrIdRow ? (int)$nrIdRow['id_nota_remision'] : 0;
    
    if (!empty($items_ok) || ($recep_prev === 1 && $id_nota_remision_compra > 0)) {
      // Para compras usamos stock_materia_prima, no stock_productos
      // Obtener depósito desde stock_materia_prima o usar el de la factura/orden
      $stmtDep = $pdo->prepare("SELECT deposito_id FROM stock_materia_prima WHERE id_materia_prima = :id LIMIT 1");
      $sqlUpdStock = $pdo->prepare("
        UPDATE stock_materia_prima
        SET cantidad_existente = COALESCE(cantidad_existente, 0) + :c
        WHERE id_materia_prima = :mp AND deposito_id = :d
      ");
      $sqlInsStock = $pdo->prepare("
        INSERT INTO stock_materia_prima (id_materia_prima, deposito_id, cantidad_existente, stock_cantidad_minima, stock_cantidad_maxima, id_usuario)
        VALUES (:mp, :d, :c, 0, 0, :u)
        ON CONFLICT DO NOTHING
      ");
      $itemsParaStock = $items_ok;
      if ($recep_prev === 1 && $id_nota_remision_compra > 0) {
        $stmtNr = $pdo->prepare("
          SELECT id_materia_prima, nota_cantidad AS cant
          FROM nota_remision_detalle_compra
          WHERE id_nota_remision = :id
        ");
        execOrFail($pdo, $stmtNr, [':id' => $id_nota_remision_compra], 'SELECT detalle NR');
        $itemsNR = $stmtNr->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($itemsNR)) $itemsParaStock = $itemsNR;
      }
      // Obtener depósito desde la orden de compra o usar el de la sucursal
      $depositoId = 1; // Por defecto
      $stmtOcDep = $pdo->prepare("SELECT d.deposito_id FROM orden_de_compra oc JOIN deposito d ON d.deposito_id = oc.id_sucursal WHERE oc.id_orden_compra = :oc LIMIT 1");
      try {
        $stmtOcDep->execute([':oc' => $factura['id_orden_compra']]);
        $ocDep = $stmtOcDep->fetch(PDO::FETCH_ASSOC);
        if ($ocDep) $depositoId = (int)$ocDep['deposito_id'];
      } catch (Throwable $e) {}
      
      foreach ($itemsParaStock as $it) {
        $pid  = (int)($it['id_materia_prima'] ?? $it['id_producto'] ?? 0);
        $cant = (int)($it['cant'] ?? 0);
        if ($pid <= 0 || $cant <= 0) continue;
        
        // Intentar obtener depósito desde stock existente
        $stmtDep->execute([':id'=>$pid]);
        $depRow = $stmtDep->fetch(PDO::FETCH_ASSOC);
        $depId = (int)($depRow['deposito_id'] ?? $depositoId);
        if ($depId <= 0) $depId = 1;

        // Intentar actualizar stock existente
        $stmtUpd = $pdo->prepare("
          UPDATE stock_materia_prima
          SET cantidad_existente = COALESCE(cantidad_existente, 0) + :c
          WHERE id_materia_prima = :mp AND deposito_id = :d
        ");
        $stmtUpd->execute([':c' => $cant, ':mp' => $pid, ':d' => $depId]);
        
        // Si no se actualizó ninguna fila, insertar nuevo registro
        if ($stmtUpd->rowCount() === 0) {
          $stmtIns = $pdo->prepare("
            INSERT INTO stock_materia_prima (id_materia_prima, deposito_id, cantidad_existente, stock_cantidad_minima, stock_cantidad_maxima, id_usuario)
            VALUES (:mp, :d, :c, 0, 0, :u)
          ");
          $stmtIns->execute([':mp' => $pid, ':d' => $depId, ':c' => $cant, ':u' => $idUsuario]);
        }
        
        try {
          bitacora($pdo,$idUsuario,'ALTA',"UPDATE stock sin NR: +$cant unid. | Materia Prima:$pid | Depósito:$depId | Factura #$factId",$factId);
        } catch (Throwable $e) {}
      }
      if ($recep_prev === 1) {
        try {
          bitacora($pdo,$idUsuario,'ALTA',"Stock actualizado tomando cantidades de Nota de Remisión para Factura #$factId",$factId);
        } catch (Throwable $e) {}
      }
    }

    if ($recep_prev === 1 && $id_nota_remision_compra > 0) {
      $stmtNrState = $pdo->prepare("UPDATE nota_remision_compra SET nota_estado = 'FINALIZADO' WHERE id_nota_remision = :id");
      execOrFail($pdo, $stmtNrState, [':id' => $id_nota_remision_compra], 'UPDATE nota_remision_compra');
      try {
        bitacora($pdo,$idUsuario,'MODIFICACION',"Nota de Remisión #$id_nota_remision_compra → FINALIZADO (Factura #$factId)",$id_nota_remision_compra);
      } catch (Throwable $e) {}
    }

    // Obtener tipo_operacion de la factura (mismo campo que se inserta en el INSERT)
    $stTipo = $pdo->prepare("SELECT tipo_operacion FROM factura_compra WHERE id_factura_compra = :id");
    $stTipo->execute([':id' => $factId]);
    $tipoOperacionRaw = $stTipo->fetchColumn();
    // Normalizar igual que en el INSERT: 'CONTADO' o 'CREDITO'
    $tipoOperacion = ($tipoOperacionRaw && strtoupper(trim((string)$tipoOperacionRaw)) === 'CREDITO') ? 'CREDITO' : 'CONTADO';
    
    // Aprobar factura - Actualizar estado a EMITIDA
    $stmtUpd = $pdo->prepare("
      UPDATE factura_compra
      SET fac_estado = 'EMITIDA', fac_total = :tot
      WHERE id_factura_compra = :id
    ");
    execOrFail($pdo, $stmtUpd, [':tot' => $fac_total_confirmado, ':id' => $factId], 'UPDATE factura_compra');

    // Actualizar cuenta a pagar según tipo_operacion de la factura
    // IMPORTANTE: La cuenta a pagar SÍ incluye intereses (financiamiento), a diferencia de la factura
    // Si es CONTADO: estado FINALIZADO y monto_pendiente = 0
    // Si es CREDITO: estado PENDIENTE y monto_pendiente = monto_total (con intereses)
    $estadoCta = ($tipoOperacion === 'CONTADO') ? 'FINALIZADO' : 'PENDIENTE';
    $montoPendiente = ($tipoOperacion === 'CONTADO') ? 0 : $fac_total_con_interes; // Cuenta a pagar CON intereses
    
    $stUpdCta = $pdo->prepare("
      UPDATE cuentas_pagar 
      SET monto_total = :tot, 
          monto_pendiente = :pend, 
          estado = :estado 
      WHERE id_factura_compra = :fact
    ");
    execOrFail($pdo, $stUpdCta, [
      ':tot' => $fac_total_con_interes, // Cuenta a pagar CON intereses
      ':pend' => $montoPendiente,
      ':estado' => $estadoCta,
      ':fact' => $factId
    ], 'UPDATE cuentas_pagar');
    
    try {
      bitacora($pdo, $idUsuario, 'MODIFICACION', "Factura #$factId aprobada | Estado: EMITIDA | CtaPagar: $estadoCta | Tipo: $tipoOperacion", $factId);
    } catch (Throwable $e) {}

    $pdo->commit();

    header("Location: view.php?alert=9");
    exit;

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); log_dbg('ROLLBACK', ['err'=>$e->getMessage()]); }
    // redirigí con el error codificado si te sirve
    header("Location: view.php?alert=4&msg=".urlencode($e->getMessage()));
    exit;
  }
}

  
  
  else if ($accion === 'insert') {

  // ===== Helpers =====
  $toInt = function($v){ return (int)preg_replace('/\D+/', '', (string)$v); };

  // ===== Entradas =====
  date_default_timezone_set('America/Asuncion');

  $username         = $_SESSION['username'];
  $fecha            = $_POST['fecha']            ?? date('Y-m-d');
  $hora             = $_POST['hora']             ?? date('H:i:s');
  $fecha_hora       = trim("$fecha $hora"); // timestamp
  $fecha_emision    = isset($_POST['fecha_emision_factura']) ? trim($_POST['fecha_emision_factura']) : null;

  $id_oc            = (int)($_POST['orden_compra'] ?? 0);
  $numero_factura   = trim($_POST['numero_factura'] ?? '');
  $timbrado         = trim($_POST['timbrado'] ?? '');

  $orden_condicion  = strtoupper(trim($_POST['orden_condicion'] ?? '')); // CONTADO | CREDITO
  $cuotas           = (int)($_POST['cuotas'] ?? 0);
  $interes_pct_in   = trim($_POST['interes_pct'] ?? '0');
  // Guardamos el porcentaje en ENTERO (ej: "12" => 12)
  $fac_interes_pct  = (int)preg_replace('/\D+/', '', $interes_pct_in);
  $fac_cuotas       = max(0, $cuotas);

  // Totales del front → enteros
  $total_importe         = $toInt($_POST['total_importe']       ?? '0');
  $total_con_interes_raw = $toInt($_POST['total_con_interes']   ?? '0');

  $recep_prev       = isset($_POST['recep_prev']) ? 1 : 0;

  $productos_json   = $_POST['productos'] ?? '[]';
  $items            = json_decode($productos_json, true);
  if (!is_array($items)) $items = [];

  // ===== Validaciones básicas =====
  if ($id_oc <= 0)                        throw new Exception("Debe seleccionar una Orden de Compra.");
  if (!$numero_factura)                   throw new Exception("Número de factura requerido.");
  if (!preg_match('/^\d{3}-\d{3}-\d{7}$/', $numero_factura)) throw new Exception("Formato de factura inválido.");
  if (!preg_match('/^\d{8}$/', $timbrado))                    throw new Exception("Timbrado inválido (8 dígitos).");
  if (count($items) === 0)                throw new Exception("No hay detalle para registrar.");
  if (!$fecha_emision || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_emision)) {
    throw new Exception("Fecha de emisión inválida.");
  }
  $tz = new DateTimeZone(date_default_timezone_get());
  $hoy = new DateTimeImmutable('today', $tz);
  $emisionDt = DateTimeImmutable::createFromFormat('Y-m-d', $fecha_emision, $tz);
  if (!$emisionDt) {
    throw new Exception("Formato de fecha de emisión inválido.");
  }

  // tipo_compra desde la condición de la OC (no usamos tipo_operacion)
  $tipo_compra = ($orden_condicion === 'CREDITO') ? 'CREDITO' : 'CONTADO';

  // ===== Usuario / sucursal =====
  $q = $pdo->prepare("SELECT u.id_usuario, u.id_sucursal FROM usuarios u WHERE u.username = :u LIMIT 1");
  $q->execute([':u'=>$username]);
  $usr = $q->fetch(PDO::FETCH_ASSOC);
  if (!$usr) throw new Exception("No se encontró el usuario.");
  $id_usuario  = (int)$usr['id_usuario'];
  $id_sucursal = (int)$usr['id_sucursal'];

    // Validar timbrado: solo números y 8 dígitos
  if (!preg_match('/^(?!0{8})\d{8}$/', $timbrado)) {
    throw new Exception("Timbrado inválido: debe tener exactamente 8 dígitos numéricos (no todos ceros).");
  }

  // Validar formato de número de factura: NNN-NNN-NNNNNNN
  if (!preg_match('/^(?!000)\d{3}-(?!000)\d{3}-(?!0{7})\d{7}$/', $numero_factura)) {
    throw new Exception("N° de factura inválido: formato debe ser NNN-NNN-NNNNNNN (donde NNN≠000 y NNNNNNN≠0000000).");
  }

  // VALIDAR FACTURA POR PROVEEDOR
   $qProv = $pdo->prepare("
    SELECT oc.id_proveedor, p.ruc_proveedor
    FROM orden_de_compra oc
    JOIN proveedor p ON p.id_proveedor = oc.id_proveedor
    WHERE oc.id_orden_compra = :oc
    LIMIT 1
  ");
  $qProv->execute([':oc' => $id_oc]);
  $rowProv = $qProv->fetch(PDO::FETCH_ASSOC);
  if (!$rowProv) {
    // OC inválida o inexistente
    header("Location: view.php?alert=oc_invalida");
    exit;
  }
  $id_proveedor = (int)$rowProv['id_proveedor'];
  $ruc_proveedor = trim((string)($rowProv['ruc_proveedor'] ?? ''));
  
  // Validar formato RUC del proveedor (normativa paraguaya)
  if (!empty($ruc_proveedor)) {
    $rucLimpio = preg_replace('/\s/', '', $ruc_proveedor);
    // Formato: NNNNNNN-N o NNNNNN-N (6-7 dígitos + guión + 1 dígito) o NNNNNNNN (8-9 dígitos sin guión)
    if (!preg_match('/^(\d{6,7}-\d{1}|\d{8,9})$/', $rucLimpio)) {
      throw new Exception("El RUC del proveedor tiene formato inválido. Formato esperado: NNNNNNN-N o NNNNNN-N");
    }
  }

  // Validar que no se repita timbrado+nro_factura en la BD (evitar duplicados)
  $qDup = $pdo->prepare("
    SELECT 1
    FROM factura_compra f
    WHERE f.timbrado = :tim
      AND f.numero_factura = :nro
      AND f.fac_estado != 'ANULADO'
    LIMIT 1
  ");
  $qDup->execute([
    ':tim'  => $timbrado,
    ':nro'  => $numero_factura
  ]);

  if ($qDup->fetchColumn()) {
    // Ya existe ese timbrado+número de factura (combinación única)
    header("Location: view.php?alert=5");
    exit;
  }
  
  // Validación adicional: número de factura único por proveedor (según especificación punto 10)
  $qDupProv = $pdo->prepare("
    SELECT 1
    FROM factura_compra f
    JOIN orden_de_compra oc ON oc.id_orden_compra = f.id_orden_compra
    WHERE oc.id_proveedor = :prov
      AND f.numero_factura = :nro
      AND f.fac_estado != 'ANULADO'
    LIMIT 1
  ");
  $qDupProv->execute([
    ':prov' => $id_proveedor,
    ':nro'  => $numero_factura
  ]);

  if ($qDupProv->fetchColumn()) {
    // Ya existe ese número de factura para el mismo proveedor
    header("Location: view.php?alert=5");
    exit;
  }

  

  // ===== Transacción =====
  $pdo->beginTransaction();

  // ===== Mapas IVA por DESCRIPCIÓN =====
  $rowsIva = $pdo->query("SELECT iva_id, iva_descri FROM tipo_iva")->fetchAll(PDO::FETCH_ASSOC);
  $descri_por_id  = [];
  $divisor_por_id = [];
  foreach ($rowsIva as $r) {
    $id    = (int)$r['iva_id'];
    $descr = strtolower(preg_replace('/\s|%/','', (string)$r['iva_descri'])); // Normaliza: quita espacios y %
    $descri_por_id[$id] = $descr;
    // Comparación más flexible, igual que en la acción aprobar
    if (in_array($descr, ['iva10','iva_10','10','10porc','iva10%']))      $divisor_por_id[$id] = 11;
    elseif (in_array($descr, ['iva5','iva_5','5','5porc','iva5%']))       $divisor_por_id[$id] = 21;
    else                          $divisor_por_id[$id] = 0; // exento/otros
  }

  // ===== Materia Prima → iva_id =====
  $sqlMpIva = $pdo->prepare("
    SELECT mp.id_materia_prima, mp.iva_id
    FROM materia_prima mp
    WHERE mp.id_materia_prima = :id
    LIMIT 1
  ");

  // Acumuladores enteros
  $total_calc = 0;
  $items_ok   = [];
  $iva_por_id = []; // [iva_id => monto_iva_acumulado INT]

  // ===== Procesar detalle con aritmética entera =====
  foreach ($items as $it) {
    $id_mp = (int)($it['codigo'] ?? 0);
    $cant    = (int)($it['cantidad'] ?? 0);
    $precio  = (int)($it['precio'] ?? 0);
    if ($id_mp<=0 || $cant<=0 || $precio<=0) continue;

    $sqlMpIva->execute([':id'=>$id_mp]);
    $row = $sqlMpIva->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new Exception("Materia prima $id_mp no existe.");
    $iva_id = (int)$row['iva_id'];

    $sub = $cant * $precio;               // entero
    $total_calc += $sub;

    $div = $divisor_por_id[$iva_id] ?? 0; // 11, 21 o 0
    $iva_monto = ($div > 0) ? intdiv($precio * $cant, $div) : 0; // ENTERO, truncado
    
    // Debug: Si el IVA es 0, verificar por qué
    if ($iva_monto === 0 && $div === 0) {
      error_log("DEBUG IVA: materia_prima=$id_mp, iva_id=$iva_id, descr=" . ($descri_por_id[$iva_id] ?? 'NO_ENCONTRADO') . ", precio=$precio, cant=$cant, div=$div");
    }

    if (!isset($iva_por_id[$iva_id])) $iva_por_id[$iva_id] = 0;
    $iva_por_id[$iva_id] += $iva_monto;

    $items_ok[] = [
      'id_materia_prima' => $id_mp,
      'cant'        => $cant,
      'precio'      => $precio,
      'iva_monto'   => $iva_monto  // IVA calculado para este ítem
    ];
  }

  if ($total_calc <= 0) throw new Exception("Total calculado inválido.");

  // NOTA: El descuento ya está aplicado en el precio que viene del frontend
  // El precio mostrado en el formulario ya es el precio neto (precio - descuento/cantidad)
  // Por lo tanto, el total_calc ya incluye el descuento aplicado

  // Validar cantidades facturadas vs OC según especificación punto 11
  $stOcDet = $pdo->prepare("
    SELECT id_materia_prima, oc_cantidad_compra
    FROM orden_detalle_compra
    WHERE id_orden_compra = :oc
  ");
  $stOcDet->execute([':oc' => $id_oc]);
  $ocDetalle = $stOcDet->fetchAll(PDO::FETCH_ASSOC);
  
  $cantidadesOc = [];
  foreach ($ocDetalle as $row) {
    $mpId = (int)$row['id_materia_prima'];
    $cantOc = (int)$row['oc_cantidad_compra'];
    $cantidadesOc[$mpId] = $cantOc;
  }
  
  $erroresCantidad = [];
  foreach ($items_ok as $it) {
    $mpId = (int)$it['id_materia_prima'];
    $cantFact = (int)$it['cant'];
    $cantOc = $cantidadesOc[$mpId] ?? 0;
    
    if ($cantFact > $cantOc) {
      $sqlMpNom = $pdo->prepare("SELECT materia_prima_descripcion FROM materia_prima WHERE id_materia_prima = :id LIMIT 1");
      $sqlMpNom->execute([':id' => $mpId]);
      $mpNom = $sqlMpNom->fetchColumn() ?: "ID $mpId";
      $erroresCantidad[] = "$mpNom: facturado $cantFact, OC tiene $cantOc";
    }
  }
  
  if (!empty($erroresCantidad)) {
    throw new Exception("Las cantidades facturadas exceden las de la OC:\n" . implode("\n", $erroresCantidad));
  }

  // Base sin interés (suma de subtotales)
  $fac_total_base = $total_calc;

  // Total con interés (si corresponde)
  $fac_total_con_interes = $fac_total_base;

  if ($tipo_compra === 'CREDITO' && $fac_cuotas > 0 && $fac_interes_pct > 0) {
    // Misma fórmula que el front:
    // cuotaBase = floor(total / cuotas)
    // interesCta = round(cuotaBase * (pct/100))
    // cuotaFinal = cuotaBase + interesCta
    // total = cuotaFinal * cuotas
    $cuotaBase   = intdiv($fac_total_base, $fac_cuotas);                 // floor entero
    $interesCta  = (int) round($cuotaBase * ($fac_interes_pct / 100));   // round
    $cuotaFinal  = $cuotaBase + $interesCta;
    $fac_total_con_interes = $cuotaFinal * $fac_cuotas;
  }

  // IMPORTANTE: La factura NO incluye intereses (solo el valor real de la mercadería)
  // Los intereses van solo en la cuenta a pagar (financiamiento)
  $fac_total = $fac_total_base; // Factura sin intereses


 // $recep_prev viene del checkbox; si está tildado, debe venir un ID válido
  $nrId = $recep_prev ? (int)($_POST['nota_remision_id'] ?? 0) : null;
  if ($recep_prev && !$nrId) {
    throw new Exception('La “Recepción previa” está tildada pero no seleccionaste Nota de Remisión.');
  }

  // ===== Factura (INSERT) =====
  // Nota: fac_cuotas, fac_interes_pct e id_nota_remision_compra no existen en la tabla
  // La información de cuotas/interés se guarda en cuenta_pagar
  // tipo_operacion almacena directamente 'CONTADO' o 'CREDITO' desde orden_compra.orden_condicion
  $sqlFac = $pdo->prepare("
    INSERT INTO factura_compra
      (numero_factura, timbrado, fact_fecha_compra, fac_fecha_vencimiento, fac_total, fac_estado, fac_plazo, fac_remision,
      tipo_operacion, id_usuario, id_sucursal, id_orden_compra)
    VALUES
      (:nro, :tim, :fec, :fec_venc, :tot, :est, :plazo, :rem,
      :tipo_op, :idu, :ids, :oc)
    RETURNING id_factura_compra
  ");

  $fac_estado = 'PENDIENTE';
  $fac_plazo_text = ($tipo_compra === 'CREDITO' && $fac_cuotas > 0) ? ($fac_cuotas . ' CUOTAS') : 'CONTADO';
  $fac_plazo  = $fac_plazo_text;
  $fac_rem    = $recep_prev ? 1 : 0;
  
  // Calcular fecha de vencimiento
  // Para CONTADO: misma fecha de emisión
  // Para CREDITO: fecha de emisión + plazo (30 días por cuota o según especificación)
  $fecha_vencimiento = $fecha_emision; // Default: misma fecha
  if ($tipo_compra === 'CREDITO' && $fac_cuotas > 0) {
    // Calcular días: típicamente 30 días por cuota
    $dias_vencimiento = $fac_cuotas * 30;
    $fecha_vencimiento = date('Y-m-d', strtotime("$fecha_emision +$dias_vencimiento days"));
  }

  // Bind de todos los parámetros "normales"
  $sqlFac->bindValue(':nro',         $numero_factura);
  $sqlFac->bindValue(':tim',         $timbrado);
  $sqlFac->bindValue(':fec',         $fecha_emision);          // DATE (fact_fecha_compra usa la fecha de emisión)
  $sqlFac->bindValue(':fec_venc',    $fecha_vencimiento);      // DATE (fac_fecha_vencimiento)
  $sqlFac->bindValue(':tot',         $fac_total, PDO::PARAM_INT);
  $sqlFac->bindValue(':est',         $fac_estado);
  $sqlFac->bindValue(':plazo',       $fac_plazo);
  $sqlFac->bindValue(':rem',         $fac_rem, PDO::PARAM_INT);
  $sqlFac->bindValue(':tipo_op',     $tipo_compra); // 'CONTADO' o 'CREDITO'
  $sqlFac->bindValue(':idu',         $id_usuario, PDO::PARAM_INT);
  $sqlFac->bindValue(':ids',         $id_sucursal, PDO::PARAM_INT);
  $sqlFac->bindValue(':oc',          $id_oc, PDO::PARAM_INT);

  $sqlFac->execute();

  $id_fact = (int)$sqlFac->fetchColumn();
  if ($id_fact <= 0) {
    throw new Exception("No se pudo generar la factura.");
  }

  bitacora(
    $pdo,
    $id_usuario,
    'ALTA',
    "Factura #$id_fact | OC:$id_oc | Nro:$numero_factura | Timbrado:$timbrado | Emisión:$fecha_emision | Total:$fac_total | Tipo:$tipo_compra | Cuotas:$fac_cuotas | %Int:$fac_interes_pct",
    $id_fact
  );

  // Marcamos la OC como FACTURADA según especificación punto 15
  $stmtOcFact = $pdo->prepare("UPDATE orden_de_compra SET orden_estado = 'FACTURADA' WHERE id_orden_compra = :oc");
  execOrFail($pdo, $stmtOcFact, [':oc' => $id_oc], 'UPDATE orden_de_compra estado FACTURADA');
  try { bitacora($pdo, $id_usuario, 'MODIFICACION', "OC #$id_oc → FACTURADA (Factura #$id_fact)", $id_oc); } catch (Throwable $e) {}

  // ===== Detalle =====
  $sqlDet = $pdo->prepare("
    INSERT INTO factura_detalle_compra
      (id_materia_prima, id_factura_compra, fac_cantidad, fac_precio, fac_iva)
    VALUES
      (:mp, :f, :c, :pre, :iva)
  ");
  foreach ($items_ok as $it) {
    $sqlDet->execute([
      ':mp'   => $it['id_materia_prima'],
      ':f'   => $id_fact,
      ':c'   => $it['cant'],
      ':pre' => $it['precio'],
      ':iva' => (int)($it['iva_monto'] ?? 0)  // IVA calculado para este ítem
    ]);
    bitacora($pdo,$id_usuario,'ALTA',"Detalle Factura #$id_fact | Materia Prima:{$it['id_materia_prima']} | Cant:{$it['cant']} | Precio:{$it['precio']} | IVA:{$it['iva_monto']}",$id_fact);
  }

  // ===== IVA COMPRA =====
  // Insertar IVA según especificación punto 13
  // La tabla iva_compra tiene un solo registro por factura con los montos consolidados
  $fecha_iva = substr($fecha_emision, 0, 10); // Solo la fecha (YYYY-MM-DD)
  
  // Consolidar todos los tipos de IVA en un solo registro
  $iva_exento_total = 0;
  $iva_5_total = 0;
  $iva_10_total = 0;

  foreach ($iva_por_id as $iva_id => $monto) {
    if ($monto <= 0) continue;
    
    $descr = $descri_por_id[$iva_id] ?? '';
    $div   = $divisor_por_id[$iva_id] ?? 0;

    // Acumular según el tipo de IVA
    if ($div === 0) {
      $iva_exento_total += $monto;
    } elseif ($div === 21 || strpos($descr, '5') !== false) {
      $iva_5_total += $monto;
    } elseif ($div === 11 || strpos($descr, '10') !== false) {
      $iva_10_total += $monto;
    } else {
      $iva_exento_total += $monto; // Por defecto, exento
    }
  }

  // Insertar un solo registro con los totales consolidados
  // NOTA: iva_id es autoincremental (clave primaria), no se debe incluir en el INSERT
  $sqlIva = $pdo->prepare("
    INSERT INTO iva_compra (id_factura_compra, iva_fecha, iva_exento, iva_5, iva_10)
    VALUES (:f, :fecha, :ex, :iva5, :iva_10)
  ");

  $sqlIva->execute([
    ':f'      => $id_fact,
    ':fecha'  => $fecha_iva,
    ':ex'     => $iva_exento_total,
    ':iva5'   => $iva_5_total,
    ':iva_10' => $iva_10_total
  ]);

  try {
    bitacora($pdo, $id_usuario, 'ALTA', "IVA COMPRA Factura #$id_fact | Exento:$iva_exento_total | IVA5:$iva_5_total | IVA10:$iva_10_total", $id_fact);
  } catch (Throwable $e) {}

  // ===== ACTUALIZAR STOCK (si no hay nota de remisión) =====
  // Según especificación punto 16: "Si la factura no tiene nota de remisión, actualiza stock"
  if (!$recep_prev || $nrId === null) {
    // Actualizar stock para cada ítem
    foreach ($items_ok as $it) {
      $mpId = (int)$it['id_materia_prima'];
      $cantidad = (int)$it['cant'];
      
      if ($mpId <= 0 || $cantidad <= 0) continue;

      // Obtener depósito desde stock_materia_prima
      $stCheck = $pdo->prepare("
        SELECT deposito_id, cantidad_existente 
        FROM stock_materia_prima 
        WHERE id_materia_prima = :mp
        LIMIT 1
      ");
      $stCheck->execute([':mp' => $mpId]);
      $stockExist = $stCheck->fetch(PDO::FETCH_ASSOC);

      if ($stockExist !== false) {
        // Actualizar cantidad_existente en el depósito existente
        $depositoId = (int)$stockExist['deposito_id'];
        $stStock = $pdo->prepare("
          UPDATE stock_materia_prima
          SET cantidad_existente = COALESCE(cantidad_existente, 0) + :c
          WHERE id_materia_prima = :mp AND deposito_id = :dep
        ");
        $stStock->execute([
          ':c' => $cantidad,
          ':mp' => $mpId,
          ':dep' => $depositoId
        ]);

        try {
          bitacora($pdo, $id_usuario, 'MODIFICACION', "Stock actualizado: +$cantidad unid. | Materia Prima:$mpId | Depósito:$depositoId | Factura #$id_fact", $id_fact);
        } catch (Throwable $e) {}
      }
      // Si no existe registro en stock_materia_prima, no se crea ni actualiza nada
    }
  }

  // Generar Cuentas a Pagar según especificación punto 14
  // Estructura de cuentas_pagar: monto_total, monto_pendiente, estado, fecha_emision, fecha_vencimiento, id_sucursal, id_usuario, id_proveedor
  // IMPORTANTE: El estado siempre debe ser PENDIENTE para permitir validaciones de anulación
  // IMPORTANTE: La cuenta a pagar SÍ incluye intereses (financiamiento), a diferencia de la factura
  $esCredito      = ($tipo_compra === 'CREDITO' && $fac_cuotas > 0);
  $total_cta      = $fac_total_con_interes; // Cuenta a pagar CON intereses
  $estadoCta      = 'PENDIENTE'; // Siempre PENDIENTE para permitir anulación
  
  // Calcular fecha de vencimiento (misma lógica que fac_fecha_vencimiento)
  $fecha_venc_cta = $fecha_emision;
  if ($esCredito && $fac_cuotas > 0) {
    $dias_vencimiento = $fac_cuotas * 30;
    $fecha_venc_cta = date('Y-m-d', strtotime("$fecha_emision +$dias_vencimiento days"));
  }

  // Calcular plazo_cuenta y nro_cuota
  // Si es CONTADO: ambos son 0
  // Si es CREDITO: plazo_cuenta = cantidad de cuotas, nro_cuota = 0 (inicialmente)
  $plazo_cuenta = ($tipo_compra === 'CREDITO' && $fac_cuotas > 0) ? $fac_cuotas : 0;
  $nro_cuota = 0; // Inicialmente 0, se actualizará cuando se paguen cuotas

  $sqlCta = $pdo->prepare("
    INSERT INTO cuentas_pagar
      (monto_total, monto_pendiente, estado, fecha_emision, fecha_vencimiento, id_sucursal, id_usuario, id_proveedor, id_factura_compra, plazo_cuenta, nro_cuota)
    VALUES
      (:tot, :pend, :estado, :fec_emision, :fec_venc, :ids, :idu, :prov, :fact, :plazo, :nro_cuota)
    RETURNING id_cuenta_pagar
  ");
  if (!$sqlCta->execute([
    ':tot'        => $total_cta,
    ':pend'       => $total_cta, // Inicialmente igual al total
    ':estado'     => $estadoCta,
    ':fec_emision' => $fecha_emision,
    ':fec_venc'   => $fecha_venc_cta,
    ':ids'        => $id_sucursal,
    ':idu'        => $id_usuario,
    ':prov'       => $id_proveedor,
    ':fact'       => $id_fact,
    ':plazo'      => $plazo_cuenta,
    ':nro_cuota'  => $nro_cuota
  ])) {
    $err = $sqlCta->errorInfo();
    throw new Exception("Fallo SQL en INSERT cuentas_pagar: ".$err[2]);
  }
  $id_cta = (int)$sqlCta->fetchColumn();
  if ($id_cta <= 0) {
    throw new Exception("No se pudo generar la cuenta por pagar.");
  }
  try {
    bitacora($pdo, $id_usuario, 'ALTA', "CtaPagar #$id_cta | Factura #$id_fact | Total:$total_cta | Estado:$estadoCta", $id_cta);
  } catch (Throwable $e) {}

  // ===== FINALIZAR PRESUPUESTO Y PEDIDO =====
  // Obtener presupuesto y pedido desde la OC para actualizar sus estados a FINALIZADO
  $stmtRel = $pdo->prepare("
    SELECT 
      oc.id_presupuesto_compra AS pre_id,
      pc.id_pedido_compra AS ped_id
    FROM orden_de_compra oc
    LEFT JOIN presupuesto_compra pc ON pc.id_presupuesto_compra = oc.id_presupuesto_compra
    WHERE oc.id_orden_compra = :oc
    LIMIT 1
  ");
  $stmtRel->execute([':oc' => $id_oc]);
  $rel = $stmtRel->fetch(PDO::FETCH_ASSOC) ?: [];
  
  $pre_id = (int)($rel['pre_id'] ?? 0);
  $ped_id = (int)($rel['ped_id'] ?? 0);
  
  // Actualizar presupuesto a FINALIZADO si existe
  if ($pre_id > 0) {
    $u2 = $pdo->prepare("UPDATE presupuesto_compra SET presu_estado='FINALIZADO' WHERE id_presupuesto_compra=:pre");
    execOrFail($pdo, $u2, [':pre'=>$pre_id], 'UPDATE presupuesto_compra FINALIZADO');
    try { bitacora($pdo, $id_usuario, 'MODIFICACION', "Presupuesto #$pre_id → FINALIZADO (Factura #$id_fact)", $pre_id); } catch (Throwable $e) {}
  }
  
  // Actualizar pedido a FINALIZADO si existe
  if ($ped_id > 0) {
    $u3 = $pdo->prepare("UPDATE pedidos_compra SET pedido_estado='FINALIZADO' WHERE id_pedido_compra=:ped");
    execOrFail($pdo, $u3, [':ped'=>$ped_id], 'UPDATE pedidos_compra FINALIZADO');
    try { bitacora($pdo, $id_usuario, 'MODIFICACION', "Pedido #$ped_id → FINALIZADO (Factura #$id_fact)", $ped_id); } catch (Throwable $e) {}
  }

  $pdo->commit();
  header("Location: view.php?alert=1");
  exit;
  }

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo "Error al registrar la compra: ".$e->getMessage();
}
