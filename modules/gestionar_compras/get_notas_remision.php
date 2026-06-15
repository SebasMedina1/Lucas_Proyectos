<?php
require "../../config/database.php";
header('Content-Type: application/json; charset=utf-8');

$prov = isset($_GET['prov_id']) ? (int)$_GET['prov_id'] : 0;
$oc   = isset($_GET['oc_id'])   ? (int)$_GET['oc_id']   : 0;

if ($prov <= 0 || $oc <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'prov_id/oc_id inválidos','prov_id'=>$prov,'oc_id'=>$oc]);
  exit;
}

try {
  $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
  $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

  $sqlA = "
    SELECT
      nrc.id_nota_remision                AS id,
      nrc.notra_remision_nro               AS nro,
      to_char(nrc.nota_fecha,'YYYY-MM-DD') AS fecha
    FROM nota_remision_compra nrc
    WHERE nrc.id_proveedor    = :prov
      AND nrc.id_orden_compra = :oc
      AND nrc.nota_estado     = 'PENDIENTE'
    ORDER BY nrc.id_nota_remision DESC
  ";

  try {
    $st = $pdo->prepare($sqlA);
    $st->execute([':prov'=>$prov, ':oc'=>$oc]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok'=>true,'items'=>$rows], JSON_UNESCAPED_UNICODE);
    exit;
  } catch (PDOException $e) {
    // Si es "undefined column" (42703), probamos con el esquema que tiene el typo
    if ($e->getCode() !== '42703') { throw $e; }
  }

  
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok'  => false,
    'msg' => 'excepción',
    'err' => $e->getMessage()
  ]);
}
