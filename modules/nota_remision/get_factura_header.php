<?php
session_start();
require "../../config/database.php";
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['fact_id']) || !ctype_digit($_GET['fact_id'])) {
  http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'fact_id inválido']); exit;
}
$fid = (int)$_GET['fact_id'];

try{
  $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
  $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

  $st = $pdo->prepare("
    SELECT 
      f.id_factura_compra,
      f.numero_factura,
      f.fac_plazo,
      f.tipo_compra,
      f.factura_emision::date AS factura_emision,
      f.fac_interes_pct AS interes,
      NULL::int AS saldo_pendiente,
      pr.id_proveedor,
      pr.razon_social AS proveedor,
      pr.ruc_proveedor   AS ruc
    FROM factura_compra f
    JOIN orden_de_compra oc ON oc.id_orden_compra = f.id_orden_compra
    JOIN proveedor pr       ON pr.id_proveedor    = oc.id_proveedor
    WHERE f.id_factura_compra = :id
    LIMIT 1
  ");
  $st->execute([':id'=>$fid]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'msg'=>'No existe la factura']); exit; }

  echo json_encode($row);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
