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
      d.id_materia_prima,
      mp.materia_prima_descripcion AS producto,
      d.fac_cantidad         AS cantidad,
      d.fac_precio           AS precio,
      ti.iva_descri           iva_descri
    FROM factura_detalle_compra d
    JOIN materia_prima mp  ON mp.id_materia_prima = d.id_materia_prima
    LEFT JOIN tipo_iva  ti ON ti.iva_id    = mp.iva_id
    WHERE d.id_factura_compra = :id
    ORDER BY mp.materia_prima_descripcion
  ");
  $st->execute([':id'=>$fid]);
  echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
