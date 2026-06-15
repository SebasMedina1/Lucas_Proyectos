<?php
session_start();
require "../../config/database.php";
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['nota_id']) || !ctype_digit($_GET['nota_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'msg' => 'nota_id inválido']);
    exit;
}
$notaId = (int)$_GET['nota_id'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $sql = "
        SELECT 
            ndv.producto_id,
            p.producto_descri AS nombre_producto,
            ndv.cantidad_nota AS nota_cantidad,
            ndv.nota_precio,
            ndv.iva_porcentaje,
            ndv.total_linea
        FROM nota_detalle_venta ndv
        JOIN productos p ON p.producto_id = ndv.producto_id
        WHERE ndv.id_nota_venta = :nota_id
        ORDER BY p.producto_descri
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':nota_id' => $notaId]);
    $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($detalle)) {
        echo json_encode(['success' => false, 'msg' => 'No se encontró detalle para esta nota']);
        exit;
    }

    // Formatear números
    foreach ($detalle as &$item) {
        $item['nota_precio'] = number_format($item['nota_precio'], 0, ',', '.');
        $item['total_linea'] = number_format($item['total_linea'], 0, ',', '.');
    }

    echo json_encode(['success' => true, 'detalle' => $detalle]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
}
?>

