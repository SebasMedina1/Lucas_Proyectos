<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');
require_once __DIR__ . '/reposicion_helper.php';

if (empty($_SESSION['username']) || !check_permission('REPOSICION_MATERIA', false)) {
    echo json_encode(['success' => false, 'message' => 'Sin permiso']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$reposicionId = (int)($input['reposicion_id'] ?? 0);
if ($reposicionId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $usuarioId = resolverUsuarioRep($pdo);

    $pdo->beginTransaction();

    $st = $pdo->prepare("
        SELECT reposicion_estado, deposito_id, id_pedido_mat_prod
        FROM reposicion_materia WHERE reposicion_id = :id FOR UPDATE
    ");
    $st->execute([':id' => $reposicionId]);
    $cab = $st->fetch();
    if (!$cab) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Reposición no encontrada']);
        exit;
    }
    if (strtoupper(trim((string)$cab['reposicion_estado'])) !== 'REGISTRADO') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'La reposición ya está anulada.']);
        exit;
    }

    $pedidoId = (int)$cab['id_pedido_mat_prod'];
    $depositoId = (int)$cab['deposito_id'];

    $lineas = $pdo->prepare("
        SELECT id_materia_prima, reposicion_cantidad
        FROM reposicion_materia_detalle WHERE reposicion_id = :id
    ");
    $lineas->execute([':id' => $reposicionId]);

    foreach ($lineas->fetchAll() as $row) {
        $mpId = (int)$row['id_materia_prima'];
        $cant = (int)$row['reposicion_cantidad'];

        decrementarStockMp($pdo, $mpId, $depositoId, $cant);

        $pdo->prepare("
            UPDATE pedido_materia_detalle_produccion
            SET cantidad_repuesta = GREATEST(0, cantidad_repuesta - :cant)
            WHERE id_pedido_mat_prod = :p AND id_materia_prima = :mp
        ")->execute([':cant' => $cant, ':p' => $pedidoId, ':mp' => $mpId]);
    }

    $pdo->prepare("
        UPDATE reposicion_materia SET reposicion_estado = 'ANULADO' WHERE reposicion_id = :id
    ")->execute([':id' => $reposicionId]);

    if ($pedidoId > 0) {
        actualizarEstadoPedido($pdo, $pedidoId);
    }

    bitacoraRep($pdo, $usuarioId, 'ANULACION', "Anulación reposición #{$reposicionId}", $reposicionId);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
