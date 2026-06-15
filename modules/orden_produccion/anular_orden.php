<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
    exit;
}

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');

if (!check_permission('ORDEN_PRODUCCION', false)) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$ordenId = isset($input['orden_id']) ? (int)$input['orden_id'] : 0;

if ($ordenId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de orden inválido']);
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

    if ($usuarioId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Usuario no válido']);
        exit;
    }

    $pdo->beginTransaction();

    $st = $pdo->prepare("
        SELECT orden_prod_estado, id_pedido_produccion
        FROM orden_produccion
        WHERE orden_id = :id
        FOR UPDATE
    ");
    $st->execute([':id' => $ordenId]);
    $orden = $st->fetch();

    if (!$orden) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Orden no encontrada']);
        exit;
    }

    $estado = strtoupper(trim((string)$orden['orden_prod_estado']));
    if ($estado !== 'PENDIENTE') {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => "Solo se pueden anular órdenes en estado PENDIENTE. Estado actual: {$estado}.",
        ]);
        exit;
    }

    $stCtrl = $pdo->prepare('SELECT COUNT(*) FROM control_produccion WHERE orden_id = :id');
    $stCtrl->execute([':id' => $ordenId]);
    if ((int)$stCtrl->fetchColumn() > 0) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'No se puede anular: la orden tiene registros de control de producción.',
            'vinculos' => ['Control de producción vinculado'],
        ]);
        exit;
    }

    $pdo->prepare("
        UPDATE orden_produccion
        SET orden_prod_estado = 'ANULADA'
        WHERE orden_id = :id
    ")->execute([':id' => $ordenId]);

    $pedidoId = (int)$orden['id_pedido_produccion'];
    $pdo->prepare("
        UPDATE pedido_produccion
        SET pedido_prod_estado = 'PENDIENTE',
            pedido_prod_ultima_modificacion = CURRENT_TIMESTAMP
        WHERE id_pedido_produccion = :id
          AND pedido_prod_estado = 'ASIGNADO'
    ")->execute([':id' => $pedidoId]);

    $pdo->prepare("
        INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
        VALUES (:id_usuario, :entidad, :id_registro, 'INACTIVACION', :descripcion)
    ")->execute([
        ':id_usuario' => $usuarioId,
        ':entidad' => 'orden produccion',
        ':id_registro' => $ordenId,
        ':descripcion' => "Se anula la Orden de Producción #{$ordenId}; pedido #{$pedidoId} vuelve a PENDIENTE",
    ]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Orden anulada correctamente']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
