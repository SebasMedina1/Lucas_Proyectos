<?php
require_once realpath(__DIR__ . '/../../config/database.php');
require_once __DIR__ . '/etapas_helper.php';

header('Content-Type: application/json; charset=utf-8');

$productoId = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;
if ($productoId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Producto inválido']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $st = $pdo->prepare("
        SELECT producto_id, producto_descripcion
        FROM productos WHERE producto_id = :id
    ");
    $st->execute([':id' => $productoId]);
    $prod = $st->fetch();
    if (!$prod) {
        echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
        exit;
    }

    $etapas = cargarEtapasProducto($pdo, $productoId, true);
    foreach ($etapas as &$e) {
        $e['en_uso'] = etapaEnUso($pdo, (int)$e['etapa_id']);
    }
    unset($e);

    echo json_encode([
        'success' => true,
        'producto' => $prod,
        'etapas' => $etapas,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
