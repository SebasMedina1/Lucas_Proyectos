<?php
require "../../config/database.php";

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['fact_id'])) {
  echo json_encode([]);
  exit;
}

try {
  $factId = (int)$_GET['fact_id'];

  $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);

  $sql = "
    SELECT
      mp.materia_prima_descripcion AS producto,
      fdc.fac_cantidad       AS cantidad,
      fdc.fac_precio         AS precio,
      (fdc.fac_cantidad * fdc.fac_precio) AS subtotal,
      ti.iva_descri          AS iva
    FROM factura_detalle_compra fdc
    JOIN materia_prima mp  ON mp.id_materia_prima  = fdc.id_materia_prima
    LEFT JOIN tipo_iva  ti ON ti.iva_id      = mp.iva_id
    WHERE fdc.id_factura_compra = :fact_id
    ORDER BY mp.materia_prima_descripcion
  ";

  $q = $pdo->prepare($sql);
  $q->execute([':fact_id' => $factId]);

  echo json_encode($q->fetchAll(PDO::FETCH_ASSOC));

} catch (Throwable $e) {
  echo json_encode(['error' => $e->getMessage()]);
}
