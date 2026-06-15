<?php
require_once realpath(__DIR__ . '/../../config/database.php');

header('Content-Type: application/json; charset=utf-8');

$ordenId = isset($_GET['orden_id']) ? (int)$_GET['orden_id'] : 0;
if ($ordenId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de orden inválido']);
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
            d.producto_id AS codigo,
            p.producto_descri AS nombre_producto,
            d.orden_prod_cantidad AS cantidad,
            d.cantidad_pendiente
        FROM orden_detalle_produccion d
        JOIN productos p ON p.producto_id = d.producto_id
        WHERE d.orden_id = :id
        ORDER BY p.producto_descri ASC
    ");
    $stmt->execute([':id' => $ordenId]);

    echo json_encode([
        'success' => true,
        'detalle' => $stmt->fetchAll(),
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
