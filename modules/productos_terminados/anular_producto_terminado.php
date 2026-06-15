<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
    exit;
}

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');

if (!check_permission('PRODUCTOS_TERMINADOS', false)) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$terminadoId = isset($input['terminado_id']) ? (int)$input['terminado_id'] : 0;

if ($terminadoId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

function revertirStock(PDO $pdo, int $productoId, int $depositoId, int $cantidad): void
{
    if ($cantidad <= 0 || $depositoId <= 0) {
        return;
    }
    $st = $pdo->prepare("
        UPDATE stock_producto
        SET stock_prod_existente = GREATEST(0, stock_prod_existente - :cant)
        WHERE producto_id = :p AND deposito_id = :d
    ");
    $st->execute([':cant' => $cantidad, ':p' => $productoId, ':d' => $depositoId]);
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

    $st = $pdo->prepare('SELECT orden_id FROM producto_terminado WHERE terminado_id = :id FOR UPDATE');
    $st->execute([':id' => $terminadoId]);
    $cab = $st->fetch();
    if (!$cab) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Registro no encontrado']);
        exit;
    }

    $stCal = $pdo->prepare('SELECT COUNT(*) FROM control_calidad_produccion WHERE terminado_id = :id');
    $stCal->execute([':id' => $terminadoId]);
    if ((int)$stCal->fetchColumn() > 0) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'No se puede anular: existe control de calidad asociado.',
        ]);
        exit;
    }

    $ordenId = (int)$cab['orden_id'];

    $lineas = $pdo->prepare("
        SELECT producto_id, deposito_id, terminado_cantidad
        FROM productos_terminados_detalle
        WHERE terminado_id = :id
    ");
    $lineas->execute([':id' => $terminadoId]);

    foreach ($lineas->fetchAll() as $row) {
        $cant = (int)$row['terminado_cantidad'];
        $dep = (int)($row['deposito_id'] ?? 0);
        $prod = (int)$row['producto_id'];
        revertirStock($pdo, $prod, $dep, $cant);

        $pdo->prepare("
            UPDATE orden_detalle_produccion
            SET cantidad_pendiente = LEAST(
                orden_prod_cantidad,
                COALESCE(cantidad_pendiente, 0) + :cant
            )
            WHERE orden_id = :o AND producto_id = :p
        ")->execute([':cant' => $cant, ':o' => $ordenId, ':p' => $prod]);
    }

    $pdo->prepare('DELETE FROM productos_terminados_detalle WHERE terminado_id = :id')->execute([':id' => $terminadoId]);
    $pdo->prepare('DELETE FROM producto_terminado WHERE terminado_id = :id')->execute([':id' => $terminadoId]);

    $pend = $pdo->prepare('SELECT COALESCE(SUM(cantidad_pendiente), 0) FROM orden_detalle_produccion WHERE orden_id = :id');
    $pend->execute([':id' => $ordenId]);
    if ((int)$pend->fetchColumn() > 0) {
        $pdo->prepare("
            UPDATE orden_produccion
            SET orden_prod_estado = 'EN_PROCESO'
            WHERE orden_id = :id AND orden_prod_estado = 'TERMINADA'
        ")->execute([':id' => $ordenId]);
    }

    $pdo->prepare("
        INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
        VALUES (:u, 'productos terminados', :id, 'INACTIVACION', :d)
    ")->execute([
        ':u' => $usuarioId,
        ':id' => $terminadoId,
        ':d' => "Se anula el registro de productos terminados #{$terminadoId} (OP #{$ordenId})",
    ]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Registro anulado correctamente']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
