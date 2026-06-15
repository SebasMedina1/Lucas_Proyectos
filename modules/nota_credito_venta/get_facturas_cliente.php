<?php
session_start();
require "../../config/database.php";
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['cliente_id']) || !ctype_digit($_GET['cliente_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'msg' => 'cliente_id inválido']);
    exit;
}
$clienteId = (int)$_GET['cliente_id'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $sql = "
        SELECT 
            fv.id_factura_venta,
            COALESCE(fv.numero_factura, fv.factura_numero, 'N/A') AS numero_factura,
            fv.fecha_factura,
            COALESCE(fv.total_general, fv.factura_total, 0) AS total_general,
            fv.estado,
            fv.factura_estado
        FROM factura_ventas fv
        WHERE fv.id_cliente = :cliente_id
          AND fv.factura_estado = 'EMITIDA'
          AND (fv.estado IS NULL OR fv.estado != 'ANULADA')
        ORDER BY fv.fecha_factura DESC, fv.id_factura_venta DESC
        LIMIT 100
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cliente_id' => $clienteId]);
    $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'facturas' => $facturas]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
}
?>

