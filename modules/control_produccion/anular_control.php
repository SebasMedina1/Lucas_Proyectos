<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
    exit;
}

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');
require_once __DIR__ . '/etapas_helper.php';

if (!check_permission('CONTROL_PRODUCCION', false)) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$controlId = isset($input['control_id']) ? (int)$input['control_id'] : 0;

if ($controlId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de control inválido']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $usuarioId = (int)($_SESSION['id_usuario'] ?? $_SESSION['usua_id'] ?? 0);
    if ($usuarioId <= 0) {
        $stU = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1');
        $stU->execute([':u' => $_SESSION['username']]);
        $usuarioId = (int)$stU->fetchColumn();
    }

    $pdo->beginTransaction();

    $st = $pdo->prepare("
        SELECT control_estado, orden_id, producto_id, etapa_id
        FROM control_produccion
        WHERE control_id = :id
        FOR UPDATE
    ");
    $st->execute([':id' => $controlId]);
    $ctrl = $st->fetch();

    if (!$ctrl) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Control no encontrado']);
        exit;
    }

    if (strtoupper(trim((string)$ctrl['control_estado'])) !== 'REGISTRADO') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Solo se pueden anular controles en estado REGISTRADO.']);
        exit;
    }

    $stPt = $pdo->prepare('SELECT COUNT(*) FROM producto_terminado WHERE orden_id = :o');
    $stPt->execute([':o' => $ctrl['orden_id']]);
    if ((int)$stPt->fetchColumn() > 0) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'No se puede anular: la orden ya tiene productos terminados registrados.',
        ]);
        exit;
    }

    $stDet = $pdo->prepare("
        SELECT control_cantidad FROM control_produccion_detalle WHERE control_id = :id LIMIT 1
    ");
    $stDet->execute([':id' => $controlId]);
    $cantidad = (int)$stDet->fetchColumn();

    $consumos = $pdo->prepare("
        SELECT id_materia_prima, cantidad_consumida
        FROM control_produccion_consumo
        WHERE control_id = :id
    ");
    $consumos->execute([':id' => $controlId]);

    $reponer = $pdo->prepare("
        UPDATE stock_materia_prima
        SET cantidad_existente = cantidad_existente + :cant
        WHERE id_materia_prima = :mp
    ");
    foreach ($consumos->fetchAll() as $row) {
        $reponer->execute([
            ':cant' => (int)ceil((float)$row['cantidad_consumida']),
            ':mp' => (int)$row['id_materia_prima'],
        ]);
    }

    $ruta = obtenerRutaEtapas($pdo, (int)$ctrl['producto_id']);
    $eraUltima = esEtapaUltima($ruta, (int)$ctrl['etapa_id']);

    if ($eraUltima) {
        $pdo->prepare("
            UPDATE orden_detalle_produccion
            SET cantidad_pendiente = LEAST(
                orden_prod_cantidad,
                COALESCE(cantidad_pendiente, 0) + :cant
            )
            WHERE orden_id = :o AND producto_id = :p
        ")->execute([
            ':cant' => $cantidad,
            ':o' => (int)$ctrl['orden_id'],
            ':p' => (int)$ctrl['producto_id'],
        ]);
    }

    $pdo->prepare("
        UPDATE control_produccion SET control_estado = 'ANULADO' WHERE control_id = :id
    ")->execute([':id' => $controlId]);

    $pdo->prepare("
        UPDATE orden_produccion
        SET orden_prod_estado = 'EN_PROCESO'
        WHERE orden_id = :id AND orden_prod_estado = 'TERMINADA'
    ")->execute([':id' => $ctrl['orden_id']]);

    $pdo->prepare("
        INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
        VALUES (:u, 'control produccion', :id, 'INACTIVACION', :d)
    ")->execute([
        ':u' => $usuarioId,
        ':id' => $controlId,
        ':d' => "Se anula el control de producción #{$controlId}",
    ]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Control anulado correctamente']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
