<?php
require "../../config/database.php";
header('Content-Type: application/json; charset=utf-8');

$nrId = isset($_GET['nr_id']) ? (int)$_GET['nr_id'] : 0;
$ocId = isset($_GET['oc_id']) ? (int)$_GET['oc_id'] : 0;

if ($nrId <= 0 || $ocId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'msg' => 'nr_id y oc_id son obligatorios']);
  exit;
}

try {
  $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
  $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

  // Verificamos que la nota pertenezca a la OC indicada y que siga pendiente
  $sqlNota = "
    SELECT id_nota_remision
    FROM nota_remision_compra
    WHERE id_nota_remision = :nr
      AND id_orden_compra = :oc
      AND nota_estado = 'PENDIENTE'
    LIMIT 1
  ";
  $stNota = $pdo->prepare($sqlNota);
  $stNota->execute([':nr' => $nrId, ':oc' => $ocId]);

  if (!$stNota->fetch()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'msg' => 'La nota de remisión no pertenece a la OC o ya no está pendiente.']);
    exit;
  }

  $sqlDetalle = "
    SELECT
      d.id_materia_prima,
      d.nota_cantidad AS cantidad,
      mp.materia_prima_descripcion AS producto
    FROM nota_remision_detalle_compra d
    JOIN materia_prima mp ON mp.id_materia_prima = d.id_materia_prima
    WHERE d.id_nota_remision = :nr
    ORDER BY mp.materia_prima_descripcion
  ";
  $stDet = $pdo->prepare($sqlDetalle);
  $stDet->execute([':nr' => $nrId]);
  $items = $stDet->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'msg' => 'Error al obtener el detalle de la nota de remisión',
    'err' => $e->getMessage()
  ]);
}
