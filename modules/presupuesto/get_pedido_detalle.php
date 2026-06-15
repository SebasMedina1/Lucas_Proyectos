<?php
require "../../config/database.php";

$pedidoId = $_GET['pedido_id'] ?? null;

if (!$pedidoId) {
    echo json_encode([]);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $query = $pdo->prepare("SELECT
                                mp.id_materia_prima AS codigo,
                                mp.materia_prima_descripcion AS descripcion,
                                pdc.cantidad_pedido AS cantidad,
                                0 AS precio,
                                ti.iva_descri AS iva
                                FROM pedido_detalle_compra pdc
                                JOIN materia_prima mp ON mp.id_materia_prima = pdc.id_materia_prima
                                LEFT JOIN tipo_iva ti ON mp.iva_id = ti.iva_id
                                WHERE pdc.id_pedido_compra = :pedido_id;
    ");
    $query->bindParam(':pedido_id', $pedidoId, PDO::PARAM_INT);
    $query->execute();

    $detalles = $query->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($detalles);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>
