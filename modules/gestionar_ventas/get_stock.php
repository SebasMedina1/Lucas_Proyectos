<?php
session_start();
require "../../config/database.php";
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['producto_id']) || !ctype_digit($_GET['producto_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'msg' => 'producto_id inválido']);
    exit;
}

$productoId = (int)$_GET['producto_id'];
$sucursalId = isset($_GET['sucursal_id']) ? (int)$_GET['sucursal_id'] : null;

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Obtener stock total del producto (suma de todos los depósitos)
    $sql = "
        SELECT COALESCE(SUM(stock_prod_existente), 0) AS stock_existencia
        FROM stock_producto
        WHERE producto_id = :producto_id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':producto_id' => $productoId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'stock_existencia' => (int)($result['stock_existencia'] ?? 0)
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
}
?>

