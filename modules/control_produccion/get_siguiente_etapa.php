<?php
require_once realpath(__DIR__ . '/../../config/database.php');
require_once __DIR__ . '/etapas_helper.php';

header('Content-Type: application/json; charset=utf-8');

$ordenId = isset($_GET['orden_id']) ? (int)$_GET['orden_id'] : 0;
$productoId = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;

if ($ordenId <= 0 || $productoId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $info = resolverSiguienteEtapa($pdo, $ordenId, $productoId);
    if (!$info['success']) {
        echo json_encode(['success' => false, 'error' => $info['error']]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'orden_cantidad' => $info['orden_cantidad'],
        'completado' => $info['completado'],
        'siguiente_etapa' => $info['siguiente_etapa'],
        'cantidad_maxima' => $info['cantidad_maxima'],
        'es_ultima_etapa' => $info['es_ultima_etapa'],
        'resumen_etapas' => $info['resumen'],
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
