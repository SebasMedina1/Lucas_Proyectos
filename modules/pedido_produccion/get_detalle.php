<?php
require_once realpath(__DIR__ . '/../../config/database.php');

header('Content-Type: application/json; charset=utf-8');

$pedId = isset($_GET['ped_id']) ? (int)$_GET['ped_id'] : 0;
if ($pedId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de pedido inválido']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->prepare("
        SELECT
            d.cantidad_pedido AS cantidad,
            p.producto_descri AS nombre_producto,
            p.producto_id AS codigo
        FROM pedido_detalle_produccion d
        JOIN productos p ON p.producto_id = d.producto_id
        WHERE d.id_pedido_produccion = :id
        ORDER BY p.producto_descri ASC
    ");
    $stmt->execute([':id' => $pedId]);

    echo json_encode([
        'success' => true,
        'detalle' => $stmt->fetchAll(),
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
