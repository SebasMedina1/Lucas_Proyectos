<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');
require_once __DIR__ . '/etapas_helper.php';

if (empty($_SESSION['username']) || !check_permission('ETAPAS_PRODUCCION', false)) {
    echo json_encode(['success' => false, 'error' => 'Sin permiso']);
    exit;
}

$productoId = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : 0;
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

    $usuarioId = resolverUsuarioEtapa($pdo);
    $pdo->beginTransaction();

    $etapas = cargarEtapasProducto($pdo, $productoId, true);
    if (empty($etapas)) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'No hay ruta activa.']);
        exit;
    }

    foreach ($etapas as $e) {
        if (etapaEnUso($pdo, (int)$e['etapa_id'])) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'error' => 'No se puede anular: la etapa "' . $e['etapa_nombre'] . '" ya fue usada en control de producción.',
            ]);
            exit;
        }
    }

    foreach ($etapas as $e) {
        $pdo->prepare("UPDATE etapa_produccion SET etapa_estado = 'ANULADA' WHERE etapa_id = :id")
            ->execute([':id' => (int)$e['etapa_id']]);
    }

    bitacoraEtapa($pdo, $usuarioId, 'ANULACION', "Ruta anulada producto #{$productoId}", $productoId);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
