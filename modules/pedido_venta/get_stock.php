<?php
require "../../config/database.php";

header('Content-Type: application/json; charset=UTF-8');

// Obtener los parámetros de la URL
$productoId = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : null;

if (!$productoId) {
    echo json_encode(['error' => 'Faltan parámetros']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener el stock total del producto (suma de todos los depósitos)
    // Nota: Si no existe stock_producto, retornamos 0 o N/A
    $query = $pdo->prepare("
        SELECT COALESCE(SUM(stock_prod_existente), 0) AS stock_existencia
        FROM stock_producto
        WHERE producto_id = :producto
    ");
    $query->bindParam(':producto', $productoId, PDO::PARAM_INT);
    $query->execute();

    $result = $query->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode(['stock_existencia' => (int)$result['stock_existencia']]);
    } else {
        echo json_encode(['stock_existencia' => 0]);
    }
} catch (PDOException $e) {
    // Si la tabla no existe o hay error, retornar N/A
    echo json_encode(['stock_existencia' => null, 'message' => 'Stock no disponible']);
}
?>

