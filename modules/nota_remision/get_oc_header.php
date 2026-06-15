<?php
header('Content-Type: application/json; charset=utf-8');

try {
  require "../../config/database.php";
  $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);

  $oc_id = isset($_GET['oc_id']) ? (int)$_GET['oc_id'] : 0;
  if ($oc_id <= 0) { echo json_encode(['error'=>'oc_id inválido']); exit; }

  $sql = "
    SELECT
      oc.id_orden_compra,
      oc.orden_estado,
      oc.id_proveedor,
      pr.razon_social AS proveedor,
      pr.ruc_proveedor   AS ruc
    FROM orden_de_compra oc
    JOIN proveedor pr ON pr.id_proveedor = oc.id_proveedor
    WHERE oc.id_orden_compra = :id
    LIMIT 1
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':id'=>$oc_id]);
  $row = $st->fetch();

  if (!$row) { echo json_encode(['error'=>'OC no encontrada']); exit; }

  echo json_encode([
    'id_orden_compra' => (int)$row['id_orden_compra'],
    'orden_estado'    => $row['orden_estado'],
    'id_proveedor'    => (int)$row['id_proveedor'],
    'proveedor'       => $row['proveedor'],
    'ruc'             => $row['ruc']
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  echo json_encode(['error'=>$e->getMessage()]);
}
