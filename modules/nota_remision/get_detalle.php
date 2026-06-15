<?php
require "../../config/database.php";

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['id_nota_remision'])) {
  echo json_encode([]);
  exit;
}

try {
  $factId = (int)$_GET['id_nota_remision'];

  $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);

  $sql = "
    SELECT
      mp.materia_prima_descripcion AS producto,
      nrc.nota_cantidad       AS cantidad
    FROM nota_remision_detalle_compra nrc
    JOIN materia_prima mp  ON mp.id_materia_prima  = nrc.id_materia_prima
    WHERE nrc.id_nota_remision = :id_nota_remision
    ORDER BY mp.materia_prima_descripcion
  ";

  $q = $pdo->prepare($sql);
  $q->execute([':id_nota_remision' => $factId]);

  echo json_encode($q->fetchAll(PDO::FETCH_ASSOC));

} catch (Throwable $e) {
  echo json_encode(['error' => $e->getMessage()]);
}
