<?php
session_start();
require "../../config/database.php";
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['cliente_id'])) {
    echo json_encode(['success' => false, 'msg' => 'cliente_id no proporcionado']);
    exit;
}

$clienteId = (int)$_GET['cliente_id'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Si cliente_id es 0 (Consumidor Final), no mostrar pedidos
    if ($clienteId === 0) {
        echo json_encode(['success' => true, 'pedidos' => []]);
        exit;
    }

    $sql = "
        SELECT 
            pv.id_pedido_venta, 
            pv.pedido_fecha,
            c.cliente_nombre || ' ' || c.cliente_apellido AS cliente_nombre
        FROM pedido_venta pv
        JOIN clientes c ON c.id_cliente = pv.id_cliente
        WHERE pv.pedido_estado = 'PENDIENTE'
          AND pv.id_cliente = :cliente_id
        ORDER BY pv.id_pedido_venta DESC
        LIMIT 50
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cliente_id' => $clienteId]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'pedidos' => $pedidos]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
}
?>

