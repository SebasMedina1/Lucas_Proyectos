<?php
session_start();
require "../../config/database.php";
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['pedido_id']) || !ctype_digit($_GET['pedido_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'msg' => 'pedido_id inválido']);
    exit;
}

$pedidoId = (int)$_GET['pedido_id'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $sql = "
        SELECT 
            dpv.producto_id,
            p.producto_descri AS nombre_producto,
            dpv.cantidad_pedido,
            dpv.pedido_precio_total / NULLIF(dpv.cantidad_pedido, 0) AS precio_unitario,
            COALESCE(ti.iva_descri, 'N/A') AS iva_descri,
            COALESCE(p.iva_id, 0) AS iva_id,
            CASE 
                WHEN ti.iva_descri LIKE '%10%' THEN 10
                WHEN ti.iva_descri LIKE '%5%' THEN 5
                ELSE 0
            END AS iva_porcentaje
        FROM detalle_pedido_venta dpv
        JOIN productos p ON p.producto_id = dpv.producto_id
        LEFT JOIN tipo_iva ti ON ti.iva_id = p.iva_id
        WHERE dpv.id_pedido_venta = :pedido_id
        ORDER BY dpv.producto_id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':pedido_id' => $pedidoId]);
    $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($detalle)) {
        echo json_encode(['success' => false, 'msg' => 'No se encontró detalle para este pedido']);
        exit;
    }

    echo json_encode(['success' => true, 'detalle' => $detalle]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
}
?>

