<?php
session_start();
require "../../config/database.php";
header('Content-Type: application/json; charset=utf-8');
// Evitar caché para asegurar que siempre se lea el valor actualizado
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_GET['fact_id']) || !ctype_digit($_GET['fact_id'])) {
  http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'fact_id inválido']); exit;
}
$fid = (int)$_GET['fact_id'];

try{
  $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false
  ]);

  // Primero verificar directamente el fac_total de la factura
  $stCheck = $pdo->prepare("SELECT fac_total FROM factura_compra WHERE id_factura_compra = :id");
  $stCheck->execute([':id' => $fid]);
  $facTotalDirecto = $stCheck->fetchColumn();
  error_log("get_factura_header.php: Factura #{$fid} - fac_total directo de BD: " . ($facTotalDirecto ?? 'NULL'));

  // Forzar lectura fresca del fac_total actualizado (sin caché)
  $st = $pdo->prepare("
    SELECT 
      f.id_factura_compra,
      f.numero_factura,
      f.fac_plazo,
      COALESCE(f.tipo_operacion, 'CONTADO') AS tipo_compra,
      f.fact_fecha_compra::date AS factura_emision,
      f.fac_total,
      COALESCE(cp.monto_pendiente, 0)::int AS saldo_pendiente,
      pr.id_proveedor,
      pr.razon_social AS proveedor,
      pr.ruc_proveedor   AS ruc
    FROM factura_compra f
    JOIN orden_de_compra oc ON oc.id_orden_compra = f.id_orden_compra
    JOIN proveedor pr       ON pr.id_proveedor    = oc.id_proveedor
    LEFT JOIN cuentas_pagar cp ON cp.id_factura_compra = f.id_factura_compra
    WHERE f.id_factura_compra = :id
    LIMIT 1
  ");
  $st->execute([':id'=>$fid]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'msg'=>'No existe la factura']); exit; }

  // Asegurar que SIEMPRE usamos el valor directo de la BD (más confiable)
  if ($facTotalDirecto !== false && $facTotalDirecto !== null) {
    $row['fac_total'] = (int)$facTotalDirecto;
    error_log("get_factura_header.php: Factura #{$fid} - fac_total corregido a: " . $row['fac_total']);
  } else {
    // Si no se pudo obtener directamente, usar el de la consulta JOIN
    $row['fac_total'] = (int)($row['fac_total'] ?? 0);
    error_log("get_factura_header.php: Factura #{$fid} - fac_total desde JOIN: " . $row['fac_total']);
  }

  // Log temporal para depuración
  error_log("get_factura_header.php: Factura #{$fid} - fac_total final devuelto en JSON: " . $row['fac_total']);

  // Asegurar que el valor sea numérico en el JSON
  $row['fac_total'] = (int)$row['fac_total'];

  echo json_encode($row, JSON_NUMERIC_CHECK);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
