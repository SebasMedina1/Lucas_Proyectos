<?php
session_start();
require "../../config/database.php";

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$producto_id = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;

if ($producto_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de producto inválido']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Verificar si existe la tabla historial_productos
    $checkTable = $pdo->query("
        SELECT 1 FROM information_schema.tables 
        WHERE table_name = 'historial_productos' 
        LIMIT 1
    ");
    
    if ($checkTable->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'La tabla de historial no está disponible']);
        exit;
    }

    $query = $pdo->prepare("
        SELECT 
            h.fecha_modificacion,
            h.campo_modificado,
            h.valor_anterior,
            h.valor_nuevo,
            h.accion,
            u.username
        FROM historial_productos h
        JOIN usuarios u ON u.id_usuario = h.id_usuario
        WHERE h.producto_id = :producto_id
        ORDER BY h.fecha_modificacion DESC
        LIMIT 50
    ");
    $query->execute([':producto_id' => $producto_id]);
    $historial = $query->fetchAll();

    // Formatear fechas
    foreach ($historial as &$item) {
        $item['fecha_modificacion'] = date('d/m/Y H:i:s', strtotime($item['fecha_modificacion']));
    }

    echo json_encode(['success' => true, 'historial' => $historial]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

