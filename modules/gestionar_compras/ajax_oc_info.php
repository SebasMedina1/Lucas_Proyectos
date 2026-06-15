<?php
header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_GET['oc_id'], $_GET['prov_id'])) {
        echo json_encode(['ok' => false, 'msg' => 'Parámetros incompletos']); exit;
    }
    $ocId   = (int) $_GET['oc_id'];
    $provId = (int) $_GET['prov_id'];

    require "../../config/database.php";
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $sql = "
      SELECT orden_condicion
      FROM orden_de_compra
      WHERE id_orden_compra = :oc
        AND id_proveedor    = :prov
      LIMIT 1
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':oc' => $ocId, ':prov' => $provId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) { echo json_encode(['ok'=>false,'msg'=>'OC no encontrada o no aprobada']); exit; }

    echo json_encode(['ok'=>true, 'condicion'=>$row['orden_condicion']]); // CONTADO | CREDITO
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'msg'=>'Error interno','err'=>$e->getMessage()]);
}
