<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
    exit;
}

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');

if (!check_permission('PEDIDO_PRODUCCION', false)) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$pedidoId = isset($input['ped_id']) ? (int)$input['ped_id'] : 0;

if ($pedidoId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de pedido inválido']);
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
        SELECT pedido_prod_estado
        FROM pedido_produccion
        WHERE id_pedido_produccion = :id
        FOR UPDATE
    ");
    $st->execute([':id' => $pedidoId]);
    $estado = $st->fetchColumn();

    if ($estado === false) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
        exit;
    }

    $estado = strtoupper(trim((string)$estado));
    if ($estado !== 'PENDIENTE') {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => "Solo se pueden anular pedidos en estado PENDIENTE. Estado actual: {$estado}.",
        ]);
        exit;
    }

    $stOp = $pdo->prepare("
        SELECT orden_id, orden_prod_estado
        FROM orden_produccion
        WHERE id_pedido_produccion = :id
        LIMIT 1
    ");
    $stOp->execute([':id' => $pedidoId]);
    $orden = $stOp->fetch();

    if ($orden) {
        $pdo->rollBack();
        $vinculos = ["Orden de Producción #{$orden['orden_id']} (Estado: {$orden['orden_prod_estado']})"];
        echo json_encode([
            'success' => false,
            'message' => "El pedido no puede anularse porque está vinculado a una orden de producción.",
            'vinculos' => $vinculos,
        ]);
        exit;
    }

    $pdo->prepare("
        UPDATE pedido_produccion
        SET pedido_prod_estado = 'ANULADO',
            pedido_prod_ultima_modificacion = CURRENT_TIMESTAMP
        WHERE id_pedido_produccion = :id
    ")->execute([':id' => $pedidoId]);

    $pdo->prepare("
        INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
        VALUES (:id_usuario, :entidad, :id_registro, 'INACTIVACION', :descripcion)
    ")->execute([
        ':id_usuario' => $usuarioId,
        ':entidad' => 'pedido produccion',
        ':id_registro' => $pedidoId,
        ':descripcion' => "Se anula el Pedido de Producción #{$pedidoId}",
    ]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Pedido anulado correctamente']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
