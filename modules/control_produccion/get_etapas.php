<?php
require_once realpath(__DIR__ . '/../../config/database.php');

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

    $stmt = $pdo->prepare("
        SELECT
            ed.etapa_id,
            ed.etapa_nombre,
            ed.etapa_secuencia,
            ep.etapa_descri
        FROM etapa_detalle_produccion ed
        JOIN etapa_produccion ep ON ep.etapa_id = ed.etapa_id
        WHERE ed.producto_id = :pid
          AND UPPER(TRIM(ep.etapa_estado)) = 'ACTIVA'
        ORDER BY ed.etapa_secuencia ASC, ed.etapa_nombre ASC
    ");
    $stmt->execute([':pid' => $productoId]);
    $etapas = $stmt->fetchAll();

    if (empty($etapas)) {
        echo json_encode([
            'success' => false,
            'error' => 'No hay etapas activas definidas para este producto. Registre la ruta en Etapas de producción.',
        ]);
        exit;
    }

    echo json_encode(['success' => true, 'etapas' => $etapas]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
