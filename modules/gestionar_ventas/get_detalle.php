<?php
session_start();
require "../../config/database.php";
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['fact_id']) || !ctype_digit($_GET['fact_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'msg' => 'fact_id inválido']);
    exit;
}

$factId = (int)$_GET['fact_id'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $sql = "
        SELECT 
            fd.producto_id,
            p.producto_descri AS nombre_producto,
            fd.cantidad,
            fd.precio_unitario,
            fd.iva_porcentaje,
            fd.total_linea
        FROM factura_detalle_venta fd
        JOIN productos p ON p.producto_id = fd.producto_id
        WHERE fd.id_factura_venta = :fact_id
        ORDER BY fd.producto_id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':fact_id' => $factId]);
    $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($detalle)) {
        echo json_encode(['success' => false, 'msg' => 'No se encontró detalle para esta factura']);
        exit;
    }

    // Formatear números
    foreach ($detalle as &$item) {
        $item['precio_unitario'] = number_format($item['precio_unitario'], 0, ',', '.');
        $item['total_linea'] = number_format($item['total_linea'], 0, ',', '.');
    }

    echo json_encode(['success' => true, 'detalle' => $detalle]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
}
?>

