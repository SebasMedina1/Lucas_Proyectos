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
        SELECT COALESCE(SUM(nota_total), 0) AS total_notas
        FROM nota_venta
        WHERE id_factura_venta = :fact_id
          AND nota_venta_estado != 'ANULADA'
          AND nota_venta_tipo = 'CREDITO'
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':fact_id' => $factId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'total_notas' => (float)($result['total_notas'] ?? 0)
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
}
?>

