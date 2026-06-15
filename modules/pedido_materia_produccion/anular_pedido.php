<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');

if (empty($_SESSION['username']) || !check_permission('PEDIDO_MATERIA_PRIMA', false)) {
    echo json_encode(['success' => false, 'message' => 'Sin permiso']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$pedidoId = (int)($input['id_pedido_mat_prod'] ?? 0);
if ($pedidoId <= 0) {
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
        $q = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1');
        $q->execute([':u' => $_SESSION['username']]);
        $usuarioId = (int)$q->fetchColumn();
    }

    $pdo->beginTransaction();

    $st = $pdo->prepare("
        SELECT ped_mat_prod_estado FROM pedido_materia_produccion
        WHERE id_pedido_mat_prod = :id FOR UPDATE
    ");
    $st->execute([':id' => $pedidoId]);
    $estado = strtoupper(trim((string)$st->fetchColumn()));
    if ($estado === '' || $estado === 'ANULADO') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado o ya anulado.']);
        exit;
    }
    if (in_array($estado, ['PARCIAL', 'COMPLETADO'], true)) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'No se puede anular: el pedido ya fue atendido.']);
        exit;
    }

    $stRep = $pdo->prepare("
        SELECT COUNT(*) FROM reposicion_materia
        WHERE id_pedido_mat_prod = :id AND UPPER(TRIM(reposicion_estado)) = 'REGISTRADO'
    ");
    $stRep->execute([':id' => $pedidoId]);
    if ((int)$stRep->fetchColumn() > 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Tiene reposiciones activas vinculadas.']);
        exit;
    }

    $pdo->prepare("
        UPDATE pedido_materia_produccion SET ped_mat_prod_estado = 'ANULADO'
        WHERE id_pedido_mat_prod = :id
    ")->execute([':id' => $pedidoId]);

    try {
        $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:u, 'pedido materia prima', :id, 'ANULACION', :d)
        ")->execute([
            ':u' => $usuarioId,
            ':id' => $pedidoId,
            ':d' => "Anulación pedido MP #{$pedidoId}",
        ]);
    } catch (Throwable $e) {
        error_log($e->getMessage());
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
