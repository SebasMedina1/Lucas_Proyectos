<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
    exit;
}

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');

if (!check_permission('CONTROL_CALIDAD', false)) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$calidadId = isset($input['calidad_id']) ? (int)$input['calidad_id'] : 0;

if ($calidadId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
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
        SELECT calidad_estado, terminado_id FROM control_calidad_produccion
        WHERE calidad_id = :id FOR UPDATE
    ");
    $st->execute([':id' => $calidadId]);
    $row = $st->fetch();

    if (!$row) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Control no encontrado']);
        exit;
    }

    if (strtoupper(trim((string)$row['calidad_estado'])) === 'ANULADO') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'El control ya está anulado.']);
        exit;
    }

    $stPer = $pdo->prepare('SELECT COUNT(*) FROM perdidas WHERE calidad_id = :id');
    $stPer->execute([':id' => $calidadId]);
    if ((int)$stPer->fetchColumn() > 0) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'No se puede anular: existen pérdidas vinculadas. Regístrelas o reviértalas primero.',
        ]);
        exit;
    }

    $pdo->prepare("
        UPDATE control_calidad_produccion SET calidad_estado = 'ANULADO' WHERE calidad_id = :id
    ")->execute([':id' => $calidadId]);

    $pdo->prepare("
        INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
        VALUES (:u, 'control calidad', :id, 'INACTIVACION', :d)
    ")->execute([
        ':u' => $usuarioId,
        ':id' => $calidadId,
        ':d' => "Se anula control de calidad #{$calidadId} (lote PT #{$row['terminado_id']})",
    ]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Control de calidad anulado']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
