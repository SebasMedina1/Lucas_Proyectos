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
            fv.id_factura_venta,
            fv.numero_factura,
            fv.plazo,
            fv.tipo_factura,
            fv.fecha_emision::date AS factura_emision,
            fv.interes_pct AS interes,
            COALESCE(cc.saldo_pendiente, fv.total_general) AS saldo_pendiente,
            c.id_cliente,
            c.cliente_nombre || ' ' || c.cliente_apellido AS cliente,
            c.cliente_ruc AS ruc,
            fv.total_general
        FROM factura_ventas fv
        JOIN clientes c ON c.id_cliente = fv.id_cliente
        LEFT JOIN cuentas_cobrar cc ON cc.id_factura_venta = fv.id_factura_venta AND cc.estado = 'PENDIENTE'
        WHERE fv.id_factura_venta = :id
        LIMIT 1
    ");
    $st->execute([':id' => $fid]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'msg' => 'No existe la factura']);
        exit;
    }

    echo json_encode($row);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
?>

