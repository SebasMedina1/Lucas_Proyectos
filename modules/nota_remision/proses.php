<?php
session_start();
require "../../config/database.php";

// Verificar si la sesión es válida
if (empty($_SESSION['username'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Token de sesión inválido']);
    exit;
}

ini_set('display_errors','1'); error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('America/Asuncion');

/* ===========================  UTIL/LOG  =========================== */
function bitacora(PDO $pdo, int $idUsuario, string $accion, string $desc, ?int $id = null): void {
  $savepointName = null;
  try {
    // Si estamos en una transacción, crear un savepoint para aislar errores de bitacora
    if ($pdo->inTransaction()) {
      $savepointName = 'bitacora_' . str_replace(['.', '-'], '_', uniqid('', true));
      $pdo->exec("SAVEPOINT {$savepointName}");
    }
    
    $sql = "
      INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
      VALUES (:u, 'Nota de Remisión (Compra)', :id, :acc, :d)
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':u'=>$idUsuario, ':id'=>$id, ':acc'=>strtoupper($accion), ':d'=>$desc]);
    
    // Si usamos savepoint, liberarlo
    if ($savepointName !== null) {
      $pdo->exec("RELEASE SAVEPOINT {$savepointName}");
    }
  } catch (PDOException $e) {
    // Si hay un error SQL y usamos savepoint, hacer rollback al savepoint
    if ($savepointName !== null) {
      try {
        $pdo->exec("ROLLBACK TO SAVEPOINT {$savepointName}");
      } catch (PDOException $rollbackError) {
        error_log("Error al hacer rollback del savepoint de bitacora: ".$rollbackError->getMessage());
      }
    }
    error_log("Bitácora NR falló: ".$e->getMessage());
  } catch (Throwable $e) {
    if ($savepointName !== null) {
      try {
        $pdo->exec("ROLLBACK TO SAVEPOINT {$savepointName}");
      } catch (PDOException $rollbackError) {
        error_log("Error al hacer rollback del savepoint de bitacora: ".$rollbackError->getMessage());
      }
    }
    error_log("Bitácora NR falló: ".$e->getMessage());
  }
}
function fail(string $msg){ 
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'message' => $msg]);
  exit; 
}

/* ====== DEBUG SWITCH ====== */
const DEBUG_SQL = false;
function dbg($m){ if (DEBUG_SQL) { echo "<pre style='margin:0;color:#666'>".$m."</pre>"; @ob_flush(); @flush(); } }
function execSQL(PDO $pdo, string $label, string $sql, array $params = []): PDOStatement {
  if (DEBUG_SQL) { dbg("[$label] SQL:\n".$sql); dbg("[$label] PARAMS:\n".json_encode($params, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); }
  try { $st = $pdo->prepare($sql); $st->execute($params); if (DEBUG_SQL) { dbg("[$label] rowCount=".$st->rowCount()); } return $st; }
  catch (PDOException $e) { $detail=$e->errorInfo[2]??$e->getMessage(); dbg("[$label] ❌ ".$detail); throw $e; }
}

/* ===========================  GUARD RAILS  =========================== */
if (empty($_SESSION['username'])) fail('Sesión expirada. Inicie sesión nuevamente.');
$act = $_GET['act'] ?? '';
if (!in_array($act, ['insert_nr','update_nr','anular_nr'], true)) fail('Acción no soportada.');

/* ===========================  CONEXIÓN  =========================== */
try {
  $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
  $pdo->exec("SET TIME ZONE 'America/Asuncion'");
} catch (Throwable $e) { die("Error de conexión: ".$e->getMessage()); }

/* =====================  RESOLVER USUARIO / SUCURSAL  ===================== */
try {
  $idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
  if ($idUsuario <= 0) {
    $q = $pdo->prepare("SELECT id_usuario, id_sucursal FROM usuarios WHERE username=:u LIMIT 1");
    $q->execute([':u'=>$_SESSION['username']]);
    $rowU = $q->fetch();
    $idUsuario = (int)($rowU['id_usuario'] ?? 0);
    $idSucursal = (int)($rowU['id_sucursal'] ?? 0);
  } else {
    $q = $pdo->prepare("SELECT id_sucursal FROM usuarios WHERE id_usuario=:id LIMIT 1");
    $q->execute([':id'=>$idUsuario]);
    $idSucursal = (int)$q->fetchColumn();
  }
  if ($idUsuario <= 0) throw new Exception("No se pudo determinar el usuario.");
  if ($idSucursal <= 0) throw new Exception("El usuario no tiene sucursal asociada.");
} catch (Throwable $e) { die("Error al resolver usuario/sucursal: ".$e->getMessage()); }

/* ===========================  HELPERS NR  =========================== */
function getOcProveedor(PDO $pdo, int $idOc): int {
  $st = $pdo->prepare("SELECT id_proveedor FROM orden_de_compra WHERE id_orden_compra=:id LIMIT 1");
  $st->execute([':id'=>$idOc]);
  return (int)$st->fetchColumn();
}
function getOcFecha(PDO $pdo, int $idOc): ?string {
  $st = $pdo->prepare("SELECT orden_fecha::date FROM orden_de_compra WHERE id_orden_compra=:id LIMIT 1");
  $st->execute([':id'=>$idOc]);
  $fecha = $st->fetchColumn();
  return $fecha ? substr($fecha,0,10) : null;
}
function getIvaProducto(PDO $pdo, int $idProd): int {
  $st = $pdo->prepare("
    SELECT ti.iva_descri
      FROM materia_prima mp
      LEFT JOIN tipo_iva ti ON mp.iva_id = ti.iva_id
     WHERE mp.id_materia_prima=:mp
     LIMIT 1
  ");
  $st->execute([':mp'=>$idProd]);
  $d = strtolower((string)$st->fetchColumn());
  if (strpos($d,'10') !== false) return 10;
  if (strpos($d,'5')  !== false) return 5;
  return 0;
}
function precioOc(PDO $pdo, int $idOc, int $idProd): int {
  // Usa tus columnas reales de orden_detalle_compra
  $st = $pdo->prepare("SELECT oc_precio_compra FROM orden_detalle_compra WHERE id_orden_compra=:oc AND id_materia_prima=:mp LIMIT 1");
  $st->execute([':oc'=>$idOc, ':mp'=>$idProd]);
  return (int)($st->fetchColumn() ?: 0);
}
function saldoOc(PDO $pdo, int $idOc, int $idProd): int {
  $ord = (int)execSQL($pdo,'OC-CANT',
    "SELECT oc_cantidad_compra FROM orden_detalle_compra WHERE id_orden_compra=:oc AND id_materia_prima=:mp",
    [':oc'=>$idOc, ':mp'=>$idProd]
  )->fetchColumn();
  
  // Obtener NRs directamente por id_orden_compra (más eficiente)
  $ya = (int)(execSQL($pdo,'NR-ACUM',
    "SELECT COALESCE(SUM(d.nota_cantidad),0)
       FROM nota_remision_detalle_compra d
       JOIN nota_remision_compra r ON r.id_nota_remision = d.id_nota_remision
      WHERE r.id_orden_compra=:oc 
        AND d.id_materia_prima=:mp 
        AND r.nota_estado <> 'ANULADO'",
    [':oc'=>$idOc, ':mp'=>$idProd]
  )->fetchColumn());
  
  return max(0, $ord - $ya);
}

// Función para actualizar stock en depósito
function actualizarStock(PDO $pdo, int $idMateriaPrima, int $depositoId, int $cantidad, int $idUsuario, int $idNota, string $operacion = '+'): void {
  if ($cantidad <= 0) return;
  
  // Intentar actualizar stock existente
  $sqlUpd = $pdo->prepare("
    UPDATE stock_materia_prima
    SET cantidad_existente = GREATEST(0, COALESCE(cantidad_existente, 0) " . ($operacion === '+' ? '+' : '-') . " :c)
    WHERE id_materia_prima = :mp AND deposito_id = :d
  ");
  $sqlUpd->execute([':c' => $cantidad, ':mp' => $idMateriaPrima, ':d' => $depositoId]);
  
  // Si no se actualizó ninguna fila, insertar nuevo registro (solo para operación +)
  if ($sqlUpd->rowCount() === 0 && $operacion === '+') {
    $sqlIns = $pdo->prepare("
      INSERT INTO stock_materia_prima (id_materia_prima, deposito_id, cantidad_existente, stock_cantidad_minima, stock_cantidad_maxima, id_usuario)
      VALUES (:mp, :d, :c, 0, 0, :u)
    ");
    $sqlIns->execute([':mp' => $idMateriaPrima, ':d' => $depositoId, ':c' => $cantidad, ':u' => $idUsuario]);
  }
  
  bitacora($pdo, $idUsuario, 'MODIFICACION', 
    "Stock " . ($operacion === '+' ? 'ingresado' : 'revertido') . ": " . ($operacion === '+' ? '+' : '-') . "{$cantidad} unid. | Materia Prima:{$idMateriaPrima} | Depósito:{$depositoId} | NR #{$idNota}", 
    $idNota);
}

/* ============ NORMALIZACIÓN DEL NÚMERO LEGAL DE REMISIÓN ============ */
/**
 * Acepta "EEE-PPP-NNNNNNN" o 13 dígitos y devuelve:
 *  - $digits13: 13 dígitos sin guiones
 *  - $formateado: EEE-PPP-NNNNNNN
 * Retorna true si es válido.
 */
function normalizarRemision(string $input, string &$digits13, string &$formateado): bool {
  $digits13 = preg_replace('/\D/', '', $input);
  if (strlen($digits13) !== 13) return false;
  $formateado = substr($digits13,0,3).'-'.substr($digits13,3,3).'-'.substr($digits13,6,7);
  return true;
}

/* ===========================  INSERT =========================== */
if ($act === 'insert_nr') {
  $idOc        = (int)($_POST['id_orden_compra'] ?? 0);
  $idProveedor = (int)($_POST['id_proveedor'] ?? 0);
  $conductorId = (int)($_POST['conductor_id'] ?? 0);
  $vehiculoId  = (int)($_POST['vehiculo_id'] ?? 0);

  // Acepta 'nota_remision_nro', 'notra_remision_nro' o 'remision_nro' (back-compat)
  $nrInput     = trim($_POST['nota_remision_nro'] ?? ($_POST['notra_remision_nro'] ?? ($_POST['remision_nro'] ?? '')));

  $timbrado    = trim($_POST['timbrado'] ?? '');              // opcional
  $timbradoVto = substr((string)($_POST['timbrado_vto'] ?? ''),0,10); // opcional
  $fechaNR     = substr((string)($_POST['fecha_remision'] ?? date('Y-m-d')), 0, 10);
  $items       = json_decode($_POST['productos'] ?? '[]', true);

  if (!is_array($items)) $items = [];
  
  // Validaciones según especificación punto 13
  if ($idOc <= 0) fail('Debe seleccionar una Orden de Compra.');
  
  // Validar que la OC esté en estado EMITIDA (punto 7)
  $stOc = $pdo->prepare("SELECT orden_estado, id_proveedor FROM orden_de_compra WHERE id_orden_compra=:id LIMIT 1");
  $stOc->execute([':id'=>$idOc]);
  $ocData = $stOc->fetch(PDO::FETCH_ASSOC);
  if (!$ocData) fail('Orden de Compra no encontrada.');
  
  $estadoOc = strtoupper(trim($ocData['orden_estado']));
  if ($estadoOc !== 'EMITIDA') fail('La Orden de Compra debe estar en estado EMITIDA.');
  
  $provDeOc = (int)$ocData['id_proveedor'];
  if ($provDeOc <= 0) fail('Orden de Compra inválida.');
  if ($idProveedor > 0 && $idProveedor !== $provDeOc) fail('El proveedor no coincide con la OC.');
  $idProveedor = $provDeOc;

  $fechaOc = getOcFecha($pdo, $idOc);
  $hoy = (new DateTime('now', new DateTimeZone('America/Asuncion')))->format('Y-m-d');
  if ($fechaOc && $fechaNR < $fechaOc) fail("La fecha de la NR no puede ser anterior a la Orden de Compra.");
  if ($fechaNR > $hoy) fail('La fecha de la NR no puede ser mayor al día actual.');

  if ($conductorId <= 0) fail('Seleccione un conductor.');
  if ($vehiculoId  <= 0) fail('Seleccione un vehículo.');
  if ($nrInput === '') fail('Número legal de remisión inválido.');
  if ($timbrado !== '' && !preg_match('/^(?!0{8})\d{8}$/', $timbrado)) fail('Timbrado inválido (8 dígitos y ≠ 00000000).');
  if ($timbradoVto && $timbradoVto < $fechaNR) fail('Vencimiento de timbrado no puede ser menor a la fecha de la NR.');
  if (empty($items)) fail('La nota debe contener al menos un ítem.');

  // Normalizar y validar N° remisión
  $nrDigits = $nrFmt = '';
  if (!normalizarRemision($nrInput, $nrDigits, $nrFmt)) {
    fail('Número legal de remisión inválido.');
  }

  // Validar unicidad del número legal
  // El campo puede ser INTEGER o VARCHAR según la versión de la BD
  // Intentamos ambos formatos para compatibilidad
  $stUni = $pdo->prepare("
    SELECT 1 FROM nota_remision_compra 
    WHERE CAST(nota_remision_nro AS TEXT) = :nro 
       OR CAST(nota_remision_nro AS TEXT) = :nro_digits
       OR nota_remision_nro = :nro
       OR nota_remision_nro = :nro_digits
    LIMIT 1
  ");
  $stUni->execute([':nro'=>$nrFmt, ':nro_digits'=>$nrDigits]);
  if ($stUni->fetch()) fail('El número legal de remisión ya existe.');

  // Validar timbrado vigente si se proporciona
  if ($timbrado !== '' && $timbradoVto) {
    if ($timbradoVto < $fechaNR) fail('El timbrado debe estar vigente en la fecha de la remisión.');
  }

  // Validar items y calcular total (punto 10, 13)
  // El depósito se obtiene desde stock_materia_prima para cada ítem
  $total = 0;
  $depositosUsados = [];
  foreach ($items as $it) {
    $prod = (int)($it['id_materia_prima'] ?? $it['id_producto'] ?? $it['codigo'] ?? 0);
    $cant = (int)($it['cantidad'] ?? 0);
    $depositoItem = (int)($it['deposito_id'] ?? 0);
    
    if ($prod <= 0) fail('Ítem sin producto.');
    if ($cant <= 0) fail('Cantidades deben ser numéricas y > 0.');
    
    // Si no viene depósito en el ítem, obtenerlo desde stock_materia_prima
    if ($depositoItem <= 0) {
      $stDep = $pdo->prepare("SELECT deposito_id FROM stock_materia_prima WHERE id_materia_prima=:mp LIMIT 1");
      $stDep->execute([':mp'=>$prod]);
      $depositoItem = (int)($stDep->fetchColumn() ?? 0);
    }
    
    if ($depositoItem <= 0) {
      fail("El producto $prod no tiene depósito asignado en stock. Verifique el stock de materia prima.");
    }
    
    $depositosUsados[$prod] = $depositoItem;
    $saldo = saldoOc($pdo, $idOc, $prod);
    if ($cant > $saldo) fail("Cantidad de materia prima $prod excede el saldo de la OC (saldo disponible: $saldo).");
    $precio = precioOc($pdo, $idOc, $prod);
    $total += ($cant * $precio);
  }
  
  // Usar el primer depósito encontrado como depósito principal de la NR
  // (o el depósito predeterminado del producto si hay uno)
  $depositoId = !empty($depositosUsados) ? reset($depositosUsados) : 0;

  try {
    $pdo->beginTransaction();

    // Obtener número interno correlativo (punto 4)
    $nextNumero = (int)$pdo->query("SELECT COALESCE(MAX(CAST(nota_remision_nro AS INTEGER)), 0) + 1 FROM nota_remision_compra WHERE nota_remision_nro ~ '^[0-9]+$'")->fetchColumn();
    if ($nextNumero <= 0) $nextNumero = 1;

    // CABECERA - Estado EMITIDA según especificación punto 14
    // id_factura_compra es NULL inicialmente (se actualizará cuando se concilie con la factura)
    // id_orden_compra se guarda directamente para acceso rápido
    // nota_remision_nro: intentar guardar como VARCHAR, si falla usar solo dígitos (compatibilidad con INTEGER)
    
    // Verificar si las columnas timbrado y vencimiento_timbrado existen
    $tieneTimbrado = false;
    try {
      $checkCol = $pdo->query("
        SELECT COUNT(*) 
        FROM information_schema.columns 
        WHERE table_schema = 'public' 
          AND table_name = 'nota_remision_compra' 
          AND column_name IN ('timbrado', 'vencimiento_timbrado')
      ");
      $tieneTimbrado = ($checkCol->fetchColumn() == 2);
    } catch (Exception $e) {
      $tieneTimbrado = false;
    }
    
    try {
      if ($tieneTimbrado) {
        $idNota = (int)execSQL(
          $pdo,'NR-CAB-INS',
          "INSERT INTO nota_remision_compra
             (nota_fecha, nota_remision_total, nota_remision_nro, nota_estado,
              id_usuario, id_proveedor, deposito_id, conductor_id, vehiculo_id, 
              id_factura_compra, id_orden_compra, timbrado, vencimiento_timbrado)
           VALUES (:f, :tot, :nro, 'EMITIDA',
                   :u, :prov, :dep, :cond, :veh, NULL, :oc, :timb, :timb_vto)
           RETURNING id_nota_remision",
          [
            ':f'=>$fechaNR,
            ':tot'=>$total,
            ':nro'=>$nrFmt,  // Intentar guardar formateado: EEE-PPP-NNNNNNN
            ':u'=>$idUsuario,
            ':prov'=>$idProveedor,
            ':dep'=>$depositoId,
            ':cond'=>$conductorId,
            ':veh'=>$vehiculoId,
            ':oc'=>$idOc,
            ':timb'=>$timbrado !== '' ? $timbrado : null,
            ':timb_vto'=>$timbradoVto ?: null
          ]
        )->fetchColumn();
      } else {
        $idNota = (int)execSQL(
          $pdo,'NR-CAB-INS',
          "INSERT INTO nota_remision_compra
             (nota_fecha, nota_remision_total, nota_remision_nro, nota_estado,
              id_usuario, id_proveedor, deposito_id, conductor_id, vehiculo_id, 
              id_factura_compra, id_orden_compra)
           VALUES (:f, :tot, :nro, 'EMITIDA',
                   :u, :prov, :dep, :cond, :veh, NULL, :oc)
           RETURNING id_nota_remision",
          [
            ':f'=>$fechaNR,
            ':tot'=>$total,
            ':nro'=>$nrFmt,  // Intentar guardar formateado: EEE-PPP-NNNNNNN
            ':u'=>$idUsuario,
            ':prov'=>$idProveedor,
            ':dep'=>$depositoId,
            ':cond'=>$conductorId,
            ':veh'=>$vehiculoId,
            ':oc'=>$idOc
          ]
        )->fetchColumn();
      }
    } catch (PDOException $e) {
      // Si falla porque el campo es INTEGER y el valor excede el rango
      if (strpos($e->getMessage(), 'out of range') !== false || 
          strpos($e->getMessage(), '22003') !== false ||
          strpos($e->getMessage(), 'integer') !== false) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        fail("Error: El campo nota_remision_nro es INTEGER y no puede almacenar números de 13 dígitos. Por favor, ejecute el script ALTER_NOTa_REMISION_NRO.sql para cambiar el campo a VARCHAR(20).");
      }
      throw $e;
    }

    if ($idNota <= 0) throw new Exception('No se obtuvo ID de Nota de Remisión.');

    bitacora($pdo, $idUsuario, 'ALTA',
      sprintf('Alta NR id=%d | OC=%d | proveedor=%d | depósito=%d | total=%d | nroLegal=%s',
        $idNota, $idOc, $idProveedor, $depositoId, $total, $nrFmt), $idNota);

    // DETALLE + STOCK ingreso (punto 14)
    // Cada ítem puede tener su propio depósito (desde stock_materia_prima)
    foreach ($items as $it) {
      $prod = (int)($it['id_materia_prima'] ?? $it['id_producto'] ?? $it['codigo'] ?? 0);
      $cant = (int)($it['cantidad'] ?? 0);
      $depositoItem = (int)($it['deposito_id'] ?? $depositosUsados[$prod] ?? $depositoId);
      
      if ($prod <= 0 || $cant <= 0) continue;
      $iva  = getIvaProducto($pdo, $prod);

      execSQL($pdo,'NR-DET-INS',
        "INSERT INTO nota_remision_detalle_compra (id_nota_remision, id_materia_prima, nota_cantidad, nota_remi_iva)
         VALUES (:nr,:mp,:c,:iva)",
        [':nr'=>$idNota, ':mp'=>$prod, ':c'=>$cant, ':iva'=>$iva]
      );

      // Ingresar stock al depósito del ítem (punto 14)
      actualizarStock($pdo, $prod, $depositoItem, $cant, $idUsuario, $idNota, '+');
    }

    $pdo->commit();
    header("Location: view.php?alert=1");
    exit;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Fallo NR [{$e->getCode()}]: ".($e->errorInfo[2] ?? $e->getMessage()));
  }
}

/* ===========================  UPDATE =========================== */
if ($act === 'update_nr') {
  $idNota     = (int)($_POST['id_nota_remision'] ?? 0);
  $idOc       = (int)($_POST['id_orden_compra'] ?? 0);
  $items      = json_decode($_POST['productos'] ?? '[]', true);
  if (!is_array($items)) $items = [];
  if ($idNota <= 0) fail('NR inválida.');

  // Bloquear la fila para evitar concurrencia (punto 19)
  $nrData = execSQL($pdo,'NR-LOCK',
    "SELECT nota_estado, deposito_id, id_factura_compra 
     FROM nota_remision_compra 
     WHERE id_nota_remision=:id 
     FOR UPDATE",
    [':id'=>$idNota]
  )->fetch(PDO::FETCH_ASSOC);
  
  if (!$nrData) fail('Nota de Remisión no encontrada.');
  
  $estado = strtoupper(trim($nrData['nota_estado']));
  $idFacturaCompra = (int)($nrData['id_factura_compra'] ?? 0);
  
  // Validar según especificación punto 19.a
  if ($estado !== 'EMITIDA') fail('Solo NR en estado EMITIDA pueden editarse.');
  
  // Validar que no esté conciliada con factura (punto 19.a)
  if ($idFacturaCompra > 0) {
    $stFac = $pdo->prepare("SELECT id_factura_compra, fac_estado FROM factura_compra WHERE id_factura_compra=:id LIMIT 1");
    $stFac->execute([':id'=>$idFacturaCompra]);
    $facData = $stFac->fetch(PDO::FETCH_ASSOC);
    if ($facData) {
      fail('La NR ya está conciliada con la Factura de Compra #' . $idFacturaCompra . '; no se puede editar.');
    }
  }
  
  if (empty($items)) fail('La nota debe contener al menos un ítem.');

  // Obtener OC directamente desde la NR o desde factura_compra si no está disponible
  if ($idOc <= 0) {
    $stOc = $pdo->prepare("SELECT id_orden_compra FROM nota_remision_compra WHERE id_nota_remision=:id LIMIT 1");
    $stOc->execute([':id'=>$idNota]);
    $idOc = (int)($stOc->fetchColumn() ?? 0);
    
    // Si aún no hay OC, intentar obtener desde factura_compra
    if ($idOc <= 0 && $idFacturaCompra > 0) {
      $stOc = $pdo->prepare("SELECT id_orden_compra FROM factura_compra WHERE id_factura_compra=:id LIMIT 1");
      $stOc->execute([':id'=>$idFacturaCompra]);
      $idOc = (int)($stOc->fetchColumn() ?? 0);
    }
  }
  if ($idOc <= 0) fail('No se pudo determinar la Orden de Compra.');

  // Obtener fecha de remisión para validaciones
  $fechaNR = substr((string)($_POST['fecha_remision'] ?? ''), 0, 10);
  if (!$fechaNR) {
    // Si no viene en POST, obtenerla de la BD
    $stFecha = execSQL($pdo,'NR-FECHA', 
      "SELECT nota_fecha FROM nota_remision_compra WHERE id_nota_remision=:id LIMIT 1",
      [':id'=>$idNota]
    );
    $fechaNR = $stFecha->fetchColumn() ?: date('Y-m-d');
  }
  
  // Obtener timbrado del POST si viene
  $timbrado = trim($_POST['timbrado'] ?? '');
  $timbradoVto = substr((string)($_POST['timbrado_vto'] ?? ''),0,10);
  $timbradoVto = $timbradoVto ?: null;
  
  // Validar fecha de vencimiento del timbrado (debe ser >= fecha de remisión)
  if ($timbradoVto && $timbradoVto < $fechaNR) {
    fail('La fecha de vencimiento del timbrado no puede ser anterior a la fecha de emisión de la remisión.');
  }

  // Detalle actual para calcular diferencias
  $detActual = execSQL($pdo,'NR-DET-ACT',
    "SELECT id_materia_prima, nota_cantidad FROM nota_remision_detalle_compra WHERE id_nota_remision=:id",
    [':id'=>$idNota]
  )->fetchAll();
  $mapAct = [];
  foreach ($detActual as $r) { 
    $mapAct[(int)$r['id_materia_prima']] = (int)$r['nota_cantidad']; 
  }

  // Validaciones y total (punto 19)
  // El depósito se obtiene desde stock_materia_prima para cada ítem
  $total = 0;
  $depositosUsados = [];
  foreach ($items as $it) {
    $prod = (int)($it['id_materia_prima'] ?? $it['id_producto'] ?? $it['codigo'] ?? 0);
    $cant = (int)($it['cantidad'] ?? 0);
    $depositoItem = (int)($it['deposito_id'] ?? 0);
    
    if ($prod <= 0 || $cant <= 0) fail('Cantidades deben ser > 0.');
    
    // Si no viene depósito en el ítem, obtenerlo desde stock_materia_prima
    if ($depositoItem <= 0) {
      $stDep = $pdo->prepare("SELECT deposito_id FROM stock_materia_prima WHERE id_materia_prima=:mp LIMIT 1");
      $stDep->execute([':mp'=>$prod]);
      $depositoItem = (int)($stDep->fetchColumn() ?? 0);
    }
    
    if ($depositoItem <= 0) {
      fail("El producto $prod no tiene depósito asignado en stock. Verifique el stock de materia prima.");
    }
    
    $depositosUsados[$prod] = $depositoItem;
    
    // Validar saldo de OC considerando lo ya remitido en esta NR (punto 19)
    $saldoGlobal = saldoOc($pdo, $idOc, $prod);
    $prev = (int)($mapAct[$prod] ?? 0);
    $saldoEditable = $saldoGlobal + $prev; // se puede reutilizar lo ya remitido en esta misma NR
    if ($cant > $saldoEditable) fail("Cantidad de materia prima $prod excede saldo de OC (disponible: $saldoEditable).");
    
    $precio = precioOc($pdo, $idOc, $prod);
    $total += ($cant * $precio);
  }
  
  // Usar el primer depósito encontrado como depósito principal de la NR
  $depositoId = !empty($depositosUsados) ? reset($depositosUsados) : 0;

  try {
    $pdo->beginTransaction();

    // Obtener timbrado del POST si viene
    $timbrado = trim($_POST['timbrado'] ?? '');
    $timbradoVto = substr((string)($_POST['timbrado_vto'] ?? ''),0,10);
    $timbradoVto = $timbradoVto ?: null;
    
    // Validar fecha de vencimiento del timbrado (debe ser >= fecha de remisión)
    if ($timbradoVto && $timbradoVto < $fechaNR) {
      fail('La fecha de vencimiento del timbrado no puede ser anterior a la fecha de emisión de la remisión.');
    }
    
    // Verificar si las columnas timbrado y vencimiento_timbrado existen
    $tieneTimbrado = false;
    try {
      $checkCol = $pdo->query("
        SELECT COUNT(*) 
        FROM information_schema.columns 
        WHERE table_schema = 'public' 
          AND table_name = 'nota_remision_compra' 
          AND column_name IN ('timbrado', 'vencimiento_timbrado')
      ");
      $tieneTimbrado = ($checkCol->fetchColumn() == 2);
    } catch (Exception $e) {
      $tieneTimbrado = false;
    }
    
    // Actualizar cabecera (incluyendo id_orden_compra, depósito principal y timbrado si existe)
    if ($tieneTimbrado) {
      execSQL($pdo,'NR-CAB-UPD',
        "UPDATE nota_remision_compra 
         SET nota_remision_total=:t, deposito_id=:dep, id_orden_compra=:oc, 
             timbrado=:timb, vencimiento_timbrado=:timb_vto
         WHERE id_nota_remision=:id",
        [
          ':t'=>$total, 
          ':dep'=>$depositoId, 
          ':oc'=>$idOc, 
          ':id'=>$idNota,
          ':timb'=>$timbrado !== '' ? $timbrado : null,
          ':timb_vto'=>$timbradoVto
        ]
      );
    } else {
      execSQL($pdo,'NR-CAB-UPD',
        "UPDATE nota_remision_compra 
         SET nota_remision_total=:t, deposito_id=:dep, id_orden_compra=:oc
         WHERE id_nota_remision=:id",
        [
          ':t'=>$total, 
          ':dep'=>$depositoId, 
          ':oc'=>$idOc, 
          ':id'=>$idNota
        ]
      );
    }

    // Calcular diferencias y ajustar stock (punto 21)
    // Cada ítem puede tener su propio depósito (desde stock_materia_prima)
    $mapNuevo = [];
    $mapDepositos = [];
    foreach ($items as $it) {
      $prod = (int)($it['id_materia_prima'] ?? $it['id_producto'] ?? $it['codigo'] ?? 0);
      $cant = (int)($it['cantidad'] ?? 0);
      $depositoItem = (int)($it['deposito_id'] ?? $depositosUsados[$prod] ?? 0);
      $mapNuevo[$prod] = $cant;
      $mapDepositos[$prod] = $depositoItem;
    }
    
    // Obtener depósitos del detalle anterior desde stock_materia_prima
    $mapDepositosAnt = [];
    foreach ($mapAct as $prod => $cantAnt) {
      $stDep = $pdo->prepare("SELECT deposito_id FROM stock_materia_prima WHERE id_materia_prima=:mp LIMIT 1");
      $stDep->execute([':mp'=>$prod]);
      $mapDepositosAnt[$prod] = (int)($stDep->fetchColumn() ?? 0);
    }
    
    // Revertir stock del detalle anterior
    foreach ($mapAct as $prod => $cantAnt) {
      $depositoAnt = $mapDepositosAnt[$prod] ?? 0;
      if ($depositoAnt <= 0) continue;
      
      if (!isset($mapNuevo[$prod])) {
        // Se eliminó el ítem, revertir stock
        actualizarStock($pdo, $prod, $depositoAnt, $cantAnt, $idUsuario, $idNota, '-');
      } else {
        $cantNue = $mapNuevo[$prod];
        $depositoNue = $mapDepositos[$prod] ?? $depositoAnt;
        if ($cantNue !== $cantAnt || $depositoNue !== $depositoAnt) {
          // Ajustar diferencia
          if ($depositoNue === $depositoAnt) {
            // Mismo depósito, solo ajustar cantidad
            $diff = $cantNue - $cantAnt;
            if ($diff > 0) {
              actualizarStock($pdo, $prod, $depositoNue, $diff, $idUsuario, $idNota, '+');
            } else {
              actualizarStock($pdo, $prod, $depositoAnt, abs($diff), $idUsuario, $idNota, '-');
            }
          } else {
            // Diferente depósito: revertir del anterior y agregar al nuevo
            actualizarStock($pdo, $prod, $depositoAnt, $cantAnt, $idUsuario, $idNota, '-');
            actualizarStock($pdo, $prod, $depositoNue, $cantNue, $idUsuario, $idNota, '+');
          }
        }
      }
    }
    
    // Agregar nuevos ítems
    foreach ($mapNuevo as $prod => $cantNue) {
      if (!isset($mapAct[$prod])) {
        // Nuevo ítem, ingresar stock al depósito del ítem
        $depositoNue = $mapDepositos[$prod] ?? 0;
        if ($depositoNue > 0) {
          actualizarStock($pdo, $prod, $depositoNue, $cantNue, $idUsuario, $idNota, '+');
        }
      }
    }

    // Eliminar y recrear detalle
    execSQL($pdo,'NR-DET-DEL',
      "DELETE FROM nota_remision_detalle_compra WHERE id_nota_remision=:id",
      [':id'=>$idNota]
    );

    foreach ($items as $it) {
      $prod = (int)($it['id_materia_prima'] ?? $it['id_producto'] ?? $it['codigo'] ?? 0);
      $cant = (int)($it['cantidad'] ?? 0);
      $iva  = getIvaProducto($pdo, $prod);

      execSQL($pdo,'NR-DET-INS2',
        "INSERT INTO nota_remision_detalle_compra (id_nota_remision, id_materia_prima, nota_cantidad, nota_remi_iva)
         VALUES (:nr,:mp,:c,:iva)",
        [':nr'=>$idNota, ':mp'=>$prod, ':c'=>$cant, ':iva'=>$iva]
      );
    }

    bitacora($pdo, $idUsuario, 'MODIFICACION',
      "NR #$idNota editada. Total actualizado. Stock ajustado en diferencia neta.", $idNota);

    $pdo->commit();
    header("Location: view.php?alert=2");
    exit;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Fallo UPDATE NR [{$e->getCode()}]: ".($e->errorInfo[2] ?? $e->getMessage()));
  }
}

/* ===========================  ANULAR =========================== */
if ($act === 'anular_nr') {
  // Aceptar tanto GET como POST para compatibilidad
  $idNota = (int)($_GET['id_nota_remision'] ?? $_POST['id_nota_remision'] ?? 0);
  if ($idNota <= 0) fail('NR inválida.');

  try {
    $pdo->beginTransaction();
    
    // Bloquear la fila y obtener datos (punto 24.3)
    $nrData = execSQL($pdo,'NR-ANU-LOCK',
      "SELECT nota_estado, deposito_id, id_factura_compra 
       FROM nota_remision_compra 
       WHERE id_nota_remision=:id 
       FOR UPDATE",
      [':id'=>$idNota]
    )->fetch(PDO::FETCH_ASSOC);
    
    if (!$nrData) {
      $pdo->rollBack();
      fail('Nota de Remisión no encontrada.');
    }
    
    $estado = strtoupper(trim($nrData['nota_estado']));
    $idFacturaCompra = (int)($nrData['id_factura_compra'] ?? 0);
    $depositoId = (int)($nrData['deposito_id'] ?? 0);
    
    // Validar según especificación punto 24.3
    if ($estado === 'ANULADO') {
      $pdo->rollBack();
      fail('La NR ya está anulada.');
    }
    
  // Verificar que no esté conciliada con factura (punto 24.3)
  // La relación es: nota_remision_compra.id_factura_compra → factura_compra.id_factura_compra
  // Si id_factura_compra no es NULL, significa que está conciliada
  if ($idFacturaCompra > 0) {
    // Verificar que la factura existe y está activa
    $stFac = $pdo->prepare("SELECT id_factura_compra, fac_estado FROM factura_compra WHERE id_factura_compra=:id LIMIT 1");
    $stFac->execute([':id'=>$idFacturaCompra]);
    $facData = $stFac->fetch(PDO::FETCH_ASSOC);
    if ($facData) {
      $pdo->rollBack();
      fail('La NR ya está conciliada con la Factura de Compra #' . $idFacturaCompra . '; no se puede anular.');
    }
  }
  // Si id_factura_compra es NULL, no hay factura asociada, por lo que se puede anular

    // Obtener detalle para revertir stock (punto 24.4)
    $detalle = execSQL($pdo,'NR-DET-ANU',
      "SELECT id_materia_prima, nota_cantidad 
       FROM nota_remision_detalle_compra 
       WHERE id_nota_remision=:id",
      [':id'=>$idNota]
    )->fetchAll();

    // Revertir stock ingresado (punto 24.4)
    foreach ($detalle as $det) {
      $prod = (int)$det['id_materia_prima'];
      $cant = (int)$det['nota_cantidad'];
      if ($prod > 0 && $cant > 0 && $depositoId > 0) {
        actualizarStock($pdo, $prod, $depositoId, $cant, $idUsuario, $idNota, '-');
      }
    }

    // Anular la NR (punto 24.4)
    execSQL($pdo,'NR-ANU',
      "UPDATE nota_remision_compra SET nota_estado='ANULADO' WHERE id_nota_remision=:id",
      [':id'=>$idNota]
    );

    bitacora($pdo, $idUsuario, 'ANULACION', 
      "NR #$idNota ANULADA. Stock revertido.", $idNota);

    $pdo->commit();
    header("Location: view.php?alert=3");
    exit;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Fallo ANULAR NR [{$e->getCode()}]: ".($e->errorInfo[2] ?? $e->getMessage()));
  }
}
