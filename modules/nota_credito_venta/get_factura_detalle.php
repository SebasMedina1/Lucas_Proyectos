<?php
session_start();
require "../../config/database.php";
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['fact_id']) || !ctype_digit($_GET['fact_id'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'fact_id inválido']);
    exit;
}
$fid = (int)$_GET['fact_id'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $st = $pdo->prepare("
        SELECT 
            d.producto_id,
            p.producto_descri AS producto,
            d.cantidad,
            d.precio_unitario AS precio,
            d.iva_porcentaje,
            COALESCE(ti.iva_descri, 'N/A') AS iva_descri
        FROM factura_detalle_venta d
        JOIN productos p ON p.producto_id = d.producto_id
        LEFT JOIN tipo_iva ti ON ti.iva_id = p.iva_id
        WHERE d.id_factura_venta = :id
        ORDER BY p.producto_descri
    ");
    $st->execute([':id' => $fid]);
    echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
?>

