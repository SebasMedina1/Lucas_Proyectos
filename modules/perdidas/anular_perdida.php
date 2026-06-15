<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
    exit;
}

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');

if (!check_permission('PERDIDAS', false)) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$perdidasId = isset($input['perdidas_id']) ? (int)$input['perdidas_id'] : 0;

if ($perdidasId <= 0) {
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
        SELECT pe.perdida_estado, pe.calidad_id, cc.terminado_id
        FROM perdidas pe
        LEFT JOIN control_calidad_produccion cc ON cc.calidad_id = pe.calidad_id
        WHERE pe.perdidas_id = :id FOR UPDATE
    ");
    $st->execute([':id' => $perdidasId]);
    $cab = $st->fetch();

    if (!$cab) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Registro no encontrado']);
        exit;
    }

    if (strtoupper(trim((string)$cab['perdida_estado'])) !== 'REGISTRADO') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Solo se pueden anular pérdidas en estado REGISTRADO.']);
        exit;
    }

    $terminadoId = (int)($cab['terminado_id'] ?? 0);

    $lineas = $pdo->prepare("
        SELECT pd.producto_id, pd.perdida_cantidad, ptd.deposito_id
        FROM perdidas_detalle pd
        LEFT JOIN productos_terminados_detalle ptd
            ON ptd.terminado_id = :t AND ptd.producto_id = pd.producto_id
        WHERE pd.perdidas_id = :id
    ");
    $lineas->execute([':id' => $perdidasId, ':t' => $terminadoId]);

    foreach ($lineas->fetchAll() as $row) {
        $dep = (int)($row['deposito_id'] ?? 0);
        $cant = (int)$row['perdida_cantidad'];
        $prod = (int)$row['producto_id'];
        if ($dep > 0 && $cant > 0) {
            $stStock = $pdo->prepare("
                SELECT id_stock_productos FROM stock_producto
                WHERE producto_id = :p AND deposito_id = :d
            ");
            $stStock->execute([':p' => $prod, ':d' => $dep]);
            if ($stStock->fetchColumn()) {
                $pdo->prepare("
                    UPDATE stock_producto
                    SET stock_prod_existente = stock_prod_existente + :cant
                    WHERE producto_id = :p AND deposito_id = :d
                ")->execute([':cant' => $cant, ':p' => $prod, ':d' => $dep]);
            }
        }
    }

    $pdo->prepare("
        UPDATE perdidas SET perdida_estado = 'ANULADO' WHERE perdidas_id = :id
    ")->execute([':id' => $perdidasId]);

    $pdo->prepare("
        INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
        VALUES (:u, 'perdidas', :id, 'INACTIVACION', :d)
    ")->execute([
        ':u' => $usuarioId,
        ':id' => $perdidasId,
        ':d' => "Se anula pérdida #{$perdidasId} y se repone stock",
    ]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Pérdida anulada y stock repuesto']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
