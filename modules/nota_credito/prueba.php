<?php
session_start();
require "../../config/database.php";

ini_set('display_errors','1'); error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');


/* ===========================  UTIL/LOG  =========================== */
function bitacora(PDO $pdo, int $idUsuario, string $accion, string $desc, ?int $id = null): void {
  try {
    $q = $pdo->prepare("
      INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
      VALUES (:u, 'Nota de Credito/Debito', :id, :acc, :d)
    ");
    $q->execute([':u'=>$idUsuario, ':id'=>$id, ':acc'=>strtoupper($accion), ':d'=>$desc]);
  } catch (Throwable $e) {
    error_log("Bitácora falló: ".$e->getMessage());
  }
}
function fail(string $msg){
  echo "<script>alert(".json_encode($msg).");window.history.back();</script>";
  exit;
}

/* ====== DEBUG SWITCH ====== */
const DEBUG_SQL = true; // poné false para modo normal

function dbg($m){
  if (DEBUG_SQL) { echo "<pre style='margin:0;color:#666'>".$m."</pre>"; @ob_flush(); @flush(); }
}
function step($s){ dbg(">> STEP: $s"); }

/**
 * Ejecuta una sentencia preparada y loguea SQL + parámetros.
 * Devuelve el PDOStatement ya ejecutado.
 */
function execSQL(PDO $pdo, string $label, string $sql, array $params = []): PDOStatement {
  if (DEBUG_SQL) {
    dbg("[$label] SQL:\n".$sql);
    dbg("[$label] PARAMS:\n".json_encode($params, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
  }
  try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    if (DEBUG_SQL) { dbg("[$label] rowCount=".$st->rowCount()); }
    return $st;
  } catch (PDOException $e) {
    // Mostramos TODO lo útil en una sola línea
    $code   = $e->getCode();                 // SQLSTATE (ej. 23503, 23502, 22P02...)
    $detail = $e->errorInfo[2] ?? $e->getMessage(); // DETAIL de Postgres
    dbg("[$label] ❌ ERROR $code: $detail");
    dbg("[$label] ❌ SQL: ".$sql);
    dbg("[$label] ❌ PARAMS: ".json_encode($params, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    throw $e; // re-lanza para que el catch externo haga rollback
  }
}


/* ===========================  GUARD RAILS  =========================== */
if (empty($_SESSION['username'])) fail('Sesión expirada. Inicie sesión nuevamente.');
if (($_GET['act'] ?? '') !== 'insert_nota') fail('Acción no soportada.');

/* ===========================  CONEXIÓN  =========================== */
try {
  $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
  $pdo->exec("SET TIME ZONE 'America/Asuncion'");
} catch (Throwable $e) {
  die("Error de conexión: ".$e->getMessage());
}

/* =====================  RESOLVER USUARIO / SUCURSAL  ===================== */
try {
  $idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
  if ($idUsuario <= 0) {
    $q = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username=:u LIMIT 1");
    $q->execute([':u'=>$_SESSION['username']]);
    $idUsuario = (int)$q->fetchColumn();
  }
  if ($idUsuario <= 0) throw new Exception("No se pudo determinar el usuario.");

  $q = $pdo->prepare("SELECT id_sucursal FROM usuarios WHERE id_usuario=:id LIMIT 1");
  $q->execute([':id'=>$idUsuario]);
  $idSucursal = (int)$q->fetchColumn();
  if ($idSucursal <= 0) throw new Exception("El usuario no tiene sucursal asociada.");
} catch (Throwable $e) {
  die("Error al resolver usuario/sucursal: ".$e->getMessage());
}

/* ===========================  INPUTS  =========================== */
$idProveedor   = (int)($_POST['id_proveedor'] ?? 0);
$factId        = (int)($_POST['fact_id'] ?? 0);
$notaTipo      = strtoupper(trim($_POST['nota_tipo'] ?? ''));     // CREDITO | DEBITO
$motivoId      = (int)($_POST['motivo_id'] ?? 0);                  // 1..4 (según front)
$descripcion   = trim($_POST['descripcion'] ?? '');
$notaNroStr    = trim($_POST['nota_nro'] ?? '');
$notaNro       = (int)preg_replace('/\D+/', '', $notaNroStr);      // solo dígitos
$timbrado      = trim($_POST['nota_timbrado'] ?? '');              // 8 dígitos
$notaEmision   = substr((string)($_POST['nota_emision'] ?? ''), 0, 10);
$notaVto       = substr((string)($_POST['nota_vto'] ?? ''), 0, 10);
$items         = json_decode($_POST['productos'] ?? '[]', true);
$totalFrontNum = (int)($_POST['nota_total_num'] ?? 0);
$costoAdic = isset($_POST['costo_adicional']) && $_POST['costo_adicional'] !== ''
             ? (int) $_POST['costo_adicional']
             : null;
             
if (!is_array($items)) $items = [];

/* ===========================  VALIDACIONES  =========================== */
if ($idProveedor <= 0)                      fail("Proveedor inválido.");
if ($factId <= 0)                           fail("Factura no seleccionada.");
if (!in_array($notaTipo, ['CREDITO','DEBITO'], true)) fail("Tipo de Nota inválido.");
if ($motivoId < 1)                          fail("Motivo inválido.");
if ($notaNro <= 0)                          fail("N° de Nota inválido.");
if (!preg_match('/^\d{8}$/', $timbrado))    fail("Timbrado inválido (8 dígitos).");
if (!$notaEmision)                          fail("Fecha de emisión obligatoria.");
if (empty($items))                           fail("Debe incluir al menos un ítem.");

/* ===== Confirmar que la factura existe, pertenece al proveedor y está habilitada ===== */
$stF = $pdo->prepare("
  SELECT f.id_factura_compra, f.factura_emision::date AS fac_emision,
         f.fac_estado, oc.id_proveedor
  FROM factura_compra f
  JOIN orden_de_compra oc ON oc.id_orden_compra = f.id_orden_compra
  WHERE f.id_factura_compra = :id
  LIMIT 1
");
$stF->execute([':id'=>$factId]);
$fac = $stF->fetch();
if (!$fac)                        fail("Factura inexistente.");
if ((int)$fac['id_proveedor'] !== $idProveedor) fail("La factura no corresponde al proveedor.");
if (!in_array($fac['fac_estado'], ['APROBADO','FINALIZADO'], true)) {
  fail("La factura no está en un estado válido para emitir notas.");
}

/* ===== Reglas de fecha (en server por si el front se saltea) ===== */
$hoy = (new DateTime('now', new DateTimeZone('America/Asuncion')))->format('Y-m-d');
if ($notaEmision < $fac['fac_emision'] || $notaEmision > $hoy) {
  fail("La emisión de la nota debe estar entre {$fac['fac_emision']} y {$hoy}.");
}
if ($notaVto && $notaVto < $notaEmision) fail("Vencimiento del timbrado no puede ser menor a la emisión.");

/* ===========================  TOTALES  =========================== */
/* Recalculamos el total a partir de items (cantidad*precio + costo_adicional). */
$stF = $pdo->prepare("
  SELECT tipo_compra, COALESCE(fac_interes_pct,0) AS fac_interes_pct
  FROM factura_compra
  WHERE id_factura_compra = :id
  LIMIT 1
");
$stF->execute([':id' => $factId]);
$fac = $stF->fetch();
if (!$fac) die("Factura no encontrada.");

$esCredito   = strtoupper((string)$fac['tipo_compra']) === 'CREDITO';
$interesPct  = (int)$fac['fac_interes_pct']; 

// ====== 1) Normalizador de IVA por descripción (sin fiarnos del front) ======
$descripcionToTasa = function (?string $descr): int {
  $t = strtolower(trim((string)$descr));
  if ($t === 'iva_10' || $t === '10' || $t === '10%') return 10;
  if ($t === 'iva_5'  || $t === '5'  || $t === '5%')  return 5;
  return 0; // exento u otro
};

// ====== 2) Calcular totales (base+costos) e IVA por línea ======
$totalBase   = 0;   // suma de (cantidad * precio + costo adicional)
$totalIVA5   = 0;
$totalIVA10  = 0;

foreach ($items as $it) {
  $cant = (int)($it['cantidad'] ?? 0);
  $prec = (int)($it['precio'] ?? 0);
  if ($cant < 0 || $prec < 0) die("Cantidad/Precio no pueden ser negativos.");

  // subtotal "original" de la línea (sin el costo adicional)
  $subOrig = $cant * $prec;

  // IVA por línea (los precios vienen IVA-incluido)
  $tasa = $descripcionToTasa($it['iva_descri'] ?? null);
  if ($tasa === 10) {
    $totalIVA10 += (int)floor($subOrig / 11);
  } elseif ($tasa === 5) {
    $totalIVA5 += (int)floor($subOrig / 21);
  }

  // subtotal final de la línea sumando el costo adicional (el costo adicional no altera la tasa, solo el total)
  $totalBase += $subOrig;
}

// Sumar costo adicional de CABECERA una sola vez ======
$totalBase += max(0, (int)($costoAdic ?? 0)); 

// Interés (solo si la factura es CREDITO y el interés > 1) ======
$interesMonto = ($esCredito && $interesPct > 1)
  ? (int)floor($totalBase * ($interesPct / 100.0))
  : 0;

// Total calculado en backend
$totalCalc = $totalBase + $interesMonto;


/* ===== VALIDAR TOPE: notas de CREDITO vs total de la factura ===== */
if ($notaTipo === 'CREDITO') {
  $stTop = $pdo->prepare("
    SELECT 
      f.fac_total                               AS factura_total,
      COALESCE(SUM(nc.nota_total), 0)           AS total_creditos
    FROM factura_compra f
    LEFT JOIN nota_compra nc
           ON nc.id_factura_compra = f.id_factura_compra
          AND nc.nota_compra_tipo  = 'CREDITO'
          AND nc.nota_compra_estado IN ('APROBADO')  -- ajusta estados válidos
    WHERE f.id_factura_compra = :fid
    GROUP BY f.fac_total
  ");
  $stTop->execute([':fid' => $factId]);
  $rowTop = $stTop->fetch(PDO::FETCH_ASSOC);

  if (!$rowTop) {
    fail("Factura no encontrada para validación de tope.");
  }

  $facTotal      = (int)$rowTop['factura_total'];
  $creditosYa    = (int)$rowTop['total_creditos'];
  $creditosConNueva = $creditosYa + (int)$totalCalc;   // incluye ESTA nota

  if ($creditosConNueva > $facTotal) {
    // redirige con mensaje (ajusta la URL a tu vista/form)
    $disp = max(0, $facTotal - $creditosYa);
    header("Location: form.php?form_nota=add&form=add&err=limite_credito"
         . "&fac=$factId&total_fac=$facTotal&ya=$creditosYa&disp=$disp");
    exit;
  }
}




// ====== 4) Guard-rail contra el total del front ======
$totalFrontNum = (int)($_POST['nota_total_num'] ?? 0);
if (abs($totalCalc - $totalFrontNum) > 1) {
  die("El total calculado ($totalCalc) no coincide con el total enviado ($totalFrontNum).");
}

// ====== 5) Usar $totalCalc como nota_total al insertar la cabecera ======

$notaTotalParaInsert = $totalCalc;

// Si NO es Débito / Costo adicional (motivo 3), guardar NULL en la cabecera
if (!($notaTipo === 'DEBITO' && $motivoId === 3)) {
  $costoAdic = null;
}



/* ===========================  TRANSACCIÓN  =========================== */
try {
  $pdo->beginTransaction();


  /* ---------- CABECERA NOTA ---------- */
  $stCab = $pdo->prepare("
    INSERT INTO nota_compra
      (nota_compra_tipo,  nota_compra_fecha, nota_nro,  nota_compra_timbrado,
       nota_compra_vencimiento, nota_compra_estado, nota_total,
       id_usuario, id_sucursal, id_proveedor, id_motivo, nota_compra_emision, descripcion, costo_adicional, id_factura_compra)
    VALUES
      (:tipo, CURRENT_DATE, :nro, :timbrado,
       :vto, 'APROBADO', :total,
       :idu, :ids, :idp, :idm, :emi, :desc, :costo_adicional, :fid)
    RETURNING id_nota_compra
  ");
  $stCab->execute([
    ':tipo'     => $notaTipo,
    ':nro'      => $notaNro,
    ':timbrado' => $timbrado,
    ':vto'      => $notaVto ?: null,
    ':total'    => $notaTotalParaInsert,
    ':idu'      => $idUsuario,
    ':ids'      => $idSucursal,
    ':idp'      => $idProveedor,
    ':idm'      => $motivoId,
    ':emi'      => $notaEmision,
    ':desc'     => ($descripcion !== '' ? $descripcion : null),
    ':costo_adicional' => $costoAdic,
    ':fid'      => $factId,  
  ]);
  $idNota = (int)$stCab->fetchColumn();
  if ($idNota <= 0) throw new Exception("No se obtuvo el ID de la nota.");

  // Bitácora: ALTA del registro de la nota de credito/debito
    bitacora(
        $pdo,
        (int)$idUsuario,
        'ALTA',
        sprintf(
            'Alta Nota Compra: nota %d | tipo=%s | motivo=%d | proveedor=%d | sucursal=%d | nro=%s | timbrado=%s | emision=%s | vto=%s | total=%d | Costo adicional=%d',
            $idNota,           // id de la nota
            $notaTipo,         // CREDITO / DEBITO
            $motivoId,         // 1..4
            $idProveedor,
            $idSucursal,
            $notaNroStr,       // "EEE-PPP-NNNNNNN"
            $timbrado,         // 8 dígitos
            $notaEmision,      // YYYY-MM-DD
            ($notaVto ?: 'NULL'),
            $notaTotalParaInsert,
            $costoAdic,
        ),
        $idNota               // id_registro (mantén el hilo por nota)
    );




  /* ---------- DETALLE NOTA ---------- */
  $stDet = $pdo->prepare("
    INSERT INTO nota_detalle_compra
      (id_producto, id_nota_compra, nota_compra_cantidad, tipo_iva, nota_precio)
    VALUES
      (:prod, :nota, :cant, :iva, :precio)
  ");

  foreach ($items as $it) {
    $prod = (int)($it['id_producto'] ?? $it['codigo'] ?? 0);
    $cant = (int)($it['cantidad'] ?? 0);
    $prec = (int)($it['precio'] ?? 0);
    $iva  = $descripcionToTasa($it['iva_descri'] ?? null);

    if ($prod <= 0) throw new Exception("Ítem sin id_producto.");

    $stDet->execute([
      ':prod'   => $prod,
      ':nota'   => $idNota,
      ':cant'   => $cant,
      ':iva'    => $iva,
      ':precio' => $prec
    ]);

      /* BITÁCORA: detalle (ALTA) */
    bitacora(
      $pdo,
      (int)$idUsuario,
      'ALTA',
      "Detalle Nota #{$idNota}: producto {$prod}, cantidad {$cant}, precio {$prec}, IVA {$iva}%.",
      $idNota
    );
  }


  // UPDATE DE CUENTAS A PAGAR
  // signo según tipo de nota, es solo un factor de signo para no repetir “si es crédito resta / si es débito suma” en cada actualización.
  $sign = ($notaTipo === 'DEBITO') ? +1 : -1;

  // 1) Leer el total actual de la factura (y bloquear fila para consistencia)
  $stSelCP = $pdo->prepare("
    SELECT cuenta_total
    FROM cuenta_pagar
    WHERE id_factura_compra = :fid
    FOR UPDATE
  ");
  $stSelCP->execute([':fid' => $factId]);
  $oldTot = $stSelCP->fetchColumn();
  if ($oldTot === false) {
    throw new Exception("Cuenta a pagar no encontrada para factura #{$factId}.");
  }
  $oldTot = (int)$oldTot;

  // 2) Delta a aplicar (total de la nota ya incluye interés + costo adicional)
  $deltaCuenta   = $sign * $totalCalc;
  $baseSinInteres = $totalBase;             // tu $totalBase ya trae costo adicional de cabecera

  // 3) Aplicar update
  $stUpdCP = $pdo->prepare("
    UPDATE cuenta_pagar
      SET cuenta_total = cuenta_total + :delta
    WHERE id_factura_compra = :fid
  ");
  $stUpdCP->execute([
    ':delta' => $deltaCuenta,
    ':fid'   => $factId
  ]);

  // 4) Nuevo total (puedes evitar otro SELECT y sumar en memoria)
  $newTot = $oldTot + $deltaCuenta;

  // Bitácora
  $fmt = fn($n)=>number_format((int)$n, 0, ',', '.').' Gs';
  $descCp = sprintf(
    "Cuentas a Pagar: factura #%d, Δ %s (antes %s → ahora %s). Origen nota #%d (%s/%d). Desglose: base %s + interés %s + costo %s.",
    $factId,
    ($deltaCuenta >= 0 ? '+' : '').$fmt($deltaCuenta),
    $fmt($oldTot),
    $fmt($newTot),
    $idNota,
    $notaTipo,
    (int)$motivoId,
    $fmt($baseSinInteres ?? ($totalCalc - $interesMonto - ($costoAdic ?? 0))),
    $fmt($interesMonto ?? 0),
    $fmt($costoAdic ?? 0)
  );

  bitacora($pdo, (int)$idUsuario, 'ACTUALIZACION', $descCp, $factId);



  /* ===================== IVA COMPRAS ===================== */
  /* 1) Bases e IVA por línea (precios IVA incluido) */
  $base5 = 0;      // suma de subtotales gravados al 5%
  $base10 = 0;     // suma de subtotales gravados al 10%
  $totalIVA5 = 0;  // IVA 5% de items (sin interés)
  $totalIVA10 = 0; // IVA 10% de items (sin interés)

  foreach ($items as $it) {
    $cant = (int)($it['cantidad'] ?? 0);
    $prec = (int)($it['precio'] ?? 0);
    $sub  = $cant * $prec;

    $tasa = $descripcionToTasa($it['iva_descri'] ?? null); // 0 | 5 | 10

    if ($tasa === 10) {
      $totalIVA10 += (int) floor($sub / 11);
      $base10     += $sub;
    } elseif ($tasa === 5) {
      $totalIVA5  += (int) floor($sub / 21);
      $base5      += $sub;
    } else {
      // exento → no suma IVA
    }
  }

  /* 2) Prorrateo del interés (solo si factura era CREDITO y hay interés > 1) */
  $iva5Extra  = 0;
  $iva10Extra = 0;

  $baseGravada = $base5 + $base10;
  if ($esCredito && $interesPct > 1 && $interesMonto > 0 && $baseGravada > 0) {
    $intTo5  = (int) floor($interesMonto * ($base5  / $baseGravada));
    $intTo10 = $interesMonto - $intTo5; // resto a 10

    $iva5Extra  = (int) floor($intTo5  / 21); // interés asignado a base 5%
    $iva10Extra = (int) floor($intTo10 / 11); // interés asignado a base 10%
  }

  /* 3) Totales de IVA por la nota */
  $iva5Nota      = $totalIVA5  + $iva5Extra;
  $iva10Nota     = $totalIVA10 + $iva10Extra;
  $ivaExentoNota = 0; // la nota no genera IVA exento adicional

  /* 4) Aplicar signo (DEBITO suma, CREDITO resta) */
  $deltaEx  = $sign * $ivaExentoNota; // usualmente 0
  $deltaI5  = $sign * $iva5Nota;
  $deltaI10 = $sign * $iva10Nota;

  /* 5) UPDATE con RETURNING para loguear old/new */
  $stOld = $pdo->prepare("
    SELECT iva_exento, iva_5, iva10
    FROM iva_compra
    WHERE id_factura_compra = :fid
    LIMIT 1
  ");
  $stOld->execute([':fid'=>$factId]);
  [$oldEx,$old5,$old10] = array_map('intval', $stOld->fetch(PDO::FETCH_NUM) ?: [0,0,0]);

  $stUpdIVA = $pdo->prepare("
    UPDATE iva_compra
      SET iva_exento = iva_exento + :dex,
          iva_5      = iva_5      + :di5,
          iva10      = iva10      + :di10
    WHERE id_factura_compra = :fid
    RETURNING iva_exento, iva_5, iva10
  ");
  $stUpdIVA->execute([
    ':dex' => $deltaEx,
    ':di5' => $deltaI5,
    ':di10'=> $deltaI10,
    ':fid' => $factId
  ]);
  [$newEx,$new5,$new10] = array_map('intval', $stUpdIVA->fetch(PDO::FETCH_NUM));

    step('BITACORA: escribir entradas finales');


  /* 6) Bitácora IVA compras */
  $fmt = fn($n)=>number_format((int)$n, 0, ',', '.').' Gs';
  $descIva = sprintf(
    "IVA Compras: factura #%d — Δ Exento %s, Δ 5%% %s, Δ 10%% %s. (Exento %s→%s, 5%% %s→%s, 10%% %s→%s). Origen nota #%d (%s/%d).",
    $factId,
    ($deltaEx  >= 0 ? '+' : '').$fmt($deltaEx),
    ($deltaI5  >= 0 ? '+' : '').$fmt($deltaI5),
    ($deltaI10 >= 0 ? '+' : '').$fmt($deltaI10),
    $fmt($oldEx), $fmt($newEx),
    $fmt($old5), $fmt($new5),
    $fmt($old10), $fmt($new10),
    $idNota,
    $notaTipo,
    (int)$motivoId
  );
  bitacora($pdo, (int)$idUsuario, 'ACTUALIZACION', $descIva, $factId);





  /* ---------- ACTUALIZACIÓN DE STOCK (solo cuando corresponde) ----------
     - CRÉDITO + (1=DEVOLUCIÓN TOTAL | 2=AJUSTE PARCIAL): el stock DISMINUYE por las unidades devueltas.
     - DÉBITO (3=Costo Adicional | 4=Diferencia): NO mueve stock.
  --------------------------------------------------------------------- */
  if ($notaTipo === 'CREDITO' && in_array($motivoId, [1,2], true)) {
    // Traemos un mapa de cantidades originales por producto en la factura para validar topes
    $stFD = $pdo->prepare("
      SELECT id_producto, fac_cantidad AS cant
      FROM factura_detalle_compra
      WHERE id_factura_compra = :f
    ");
    $stFD->execute([':f'=>$factId]);
    $orig = [];
    foreach ($stFD as $row) $orig[(int)$row['id_producto']] = (int)$row['cant'];

    $stUpd = $pdo->prepare("
      UPDATE stock_productos
      SET cantidad_existente = GREATEST(0, cantidad_existente - :delta)
      WHERE id_producto = :p AND deposito_id = :dep
    ");
    foreach ($items as $it) {
      $prod = (int)($it['id_producto'] ?? $it['codigo'] ?? 0);
      $cant = (int)($it['cantidad'] ?? 0);
      if ($prod <= 0 || $cant <= 0) continue;

      // tope suave: no permitir devolver más que lo facturado (si lo tenemos)
      if (isset($orig[$prod]) && $cant > $orig[$prod]) {
        throw new Exception("Cantidad devuelta de producto {$prod} excede lo facturado.");
      }

      // usamos la sucursal/deposito  del user (siempre 1 depósito por ahora)
      // AJUSTA 'deposito_id' según tu modelo (aquí uso el id_sucursal como proxy simple)
      $stUpd->execute([':delta'=>$cant, ':p'=>$prod, ':dep'=>$idSucursal]);
    }


  }


  $pdo->commit();
  header("Location: view.php?alert=1");
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  // Muestra el SQLSTATE *original* y el detalle de Postgres
  die("Fallo en transacción [{$e->getCode()}]: ".($e->errorInfo[2] ?? $e->getMessage()));
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  die("Fallo en transacción (PHP): ".$e->getMessage());

}
