<?php
session_start();

if (!isset($_SESSION['id_usuario']) && isset($_SESSION['usua_id'])) {
    $_SESSION['id_usuario'] = $_SESSION['usua_id'];
}

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');
require_once __DIR__ . '/etapas_helper.php';

if (empty($_SESSION['username'])) {
    echo "<script>alert('Sesión inválida'); window.location.href='../../login.html';</script>";
    exit;
}

if (!check_permission('CONTROL_PRODUCCION', false)) {
    header('Location: view.php?alert=4');
    exit;
}

function bitacoraControl(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void
{
    try {
        $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ")->execute([
            ':id_usuario' => $idUsuario,
            ':entidad' => 'control produccion',
            ':id_registro' => $idRegistro,
            ':accion' => strtoupper($accion),
            ':descripcion' => $descripcion,
        ]);
    } catch (Throwable $e) {
        error_log('Bitácora falló: ' . $e->getMessage());
    }
}

function resolverUsuario(PDO $pdo): int
{
    $usuarioId = (int)($_SESSION['usua_id'] ?? $_SESSION['id_usuario'] ?? 0);
    if ($usuarioId === 0) {
        $q = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1');
        $q->execute([':u' => $_SESSION['username'] ?? '']);
        $usuarioId = (int)$q->fetchColumn();
    }
    return $usuarioId;
}

function normalizarConsumos(array $consumos): array
{
    $lineas = [];
    foreach ($consumos as $item) {
        $mpId = (int)($item['id_materia_prima'] ?? $item['materia_id'] ?? 0);
        $cant = (int)round((float)($item['cantidad'] ?? $item['cantidad_consumida'] ?? 0));
        if ($mpId <= 0 || $cant <= 0) {
            continue;
        }
        if (!isset($lineas[$mpId])) {
            $lineas[$mpId] = 0;
        }
        $lineas[$mpId] += $cant;
    }
    return $lineas;
}

function obtenerNombreEtapa(PDO $pdo, int $etapaId, int $productoId): string
{
    $st = $pdo->prepare("
        SELECT etapa_nombre FROM etapa_detalle_produccion
        WHERE etapa_id = :e AND producto_id = :p LIMIT 1
    ");
    $st->execute([':e' => $etapaId, ':p' => $productoId]);
    $nombre = $st->fetchColumn();
    return $nombre ? substr((string)$nombre, 0, 30) : 'Etapa';
}

function actualizarEstadoOrden(PDO $pdo, int $ordenId): void
{
    $st = $pdo->prepare('SELECT orden_prod_estado FROM orden_produccion WHERE orden_id = :id');
    $st->execute([':id' => $ordenId]);
    $estado = strtoupper(trim((string)$st->fetchColumn()));

    $pend = $pdo->prepare('SELECT COALESCE(SUM(cantidad_pendiente), 0) FROM orden_detalle_produccion WHERE orden_id = :id');
    $pend->execute([':id' => $ordenId]);
    $totalPend = (int)$pend->fetchColumn();

    if ($totalPend <= 0) {
        $pdo->prepare("
            UPDATE orden_produccion SET orden_prod_estado = 'TERMINADA' WHERE orden_id = :id
        ")->execute([':id' => $ordenId]);
    } elseif ($estado === 'PENDIENTE') {
        $pdo->prepare("
            UPDATE orden_produccion SET orden_prod_estado = 'EN_PROCESO' WHERE orden_id = :id
        ")->execute([':id' => $ordenId]);
    }
}

if (!isset($_GET['act'])) {
    header('Location: view.php?alert=4');
    exit;
}

$action = $_GET['act'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    if ($action === 'insert' && isset($_POST['Guardar'])) {
        $ordenId = (int)($_POST['orden_id'] ?? 0);
        $productoId = (int)($_POST['producto_id'] ?? 0);
        $etapaId = (int)($_POST['etapa_id'] ?? 0);
        $cantidad = (int)($_POST['cantidad_procesada'] ?? 0);
        $inspectorId = (int)($_POST['id_inspectores'] ?? 0);
        $observacion = isset($_POST['control_observacion']) ? trim($_POST['control_observacion']) : null;

        $consumos = [];
        if (!empty($_POST['consumos'])) {
            $tmp = json_decode($_POST['consumos'], true);
            if (is_array($tmp)) {
                $consumos = $tmp;
            }
        }
        $lineasConsumo = normalizarConsumos($consumos);

        $usuarioId = resolverUsuario($pdo);
        if ($usuarioId <= 0 || $ordenId <= 0 || $productoId <= 0 || $etapaId <= 0) {
            header('Location: view.php?alert=4&msg=' . urlencode('Complete todos los campos obligatorios.'));
            exit;
        }
        if ($cantidad <= 0) {
            header('Location: view.php?alert=4&msg=' . urlencode('La cantidad procesada debe ser mayor a cero.'));
            exit;
        }
        if ($inspectorId <= 0) {
            header('Location: view.php?alert=4&msg=' . urlencode('Debe seleccionar un inspector.'));
            exit;
        }
        if (empty($lineasConsumo)) {
            header('Location: view.php?alert=4&msg=' . urlencode('Debe registrar al menos un consumo de materia prima.'));
            exit;
        }

        $pdo->beginTransaction();

        $stOrden = $pdo->prepare("
            SELECT orden_prod_estado FROM orden_produccion WHERE orden_id = :id FOR UPDATE
        ");
        $stOrden->execute([':id' => $ordenId]);
        $estOrden = strtoupper(trim((string)$stOrden->fetchColumn()));
        if (!in_array($estOrden, ['PENDIENTE', 'EN_PROCESO'], true)) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('La orden no está disponible para control.'));
            exit;
        }

        $pdo->prepare("
            SELECT orden_prod_cantidad FROM orden_detalle_produccion
            WHERE orden_id = :o AND producto_id = :p FOR UPDATE
        ")->execute([':o' => $ordenId, ':p' => $productoId]);

        $valEtapa = validarEtapaSecuencial($pdo, $ordenId, $productoId, $etapaId, $cantidad);
        if (!$valEtapa['ok']) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode($valEtapa['error']));
            exit;
        }
        $esUltimaEtapa = $valEtapa['es_ultima'];

        $stInsp = $pdo->prepare("
            SELECT 1 FROM inspectores WHERE id_inspectores = :id AND UPPER(TRIM(inspector_estado)) = 'ACTIVO'
        ");
        $stInsp->execute([':id' => $inspectorId]);
        if (!$stInsp->fetchColumn()) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('El inspector seleccionado no está activo.'));
            exit;
        }

        foreach ($lineasConsumo as $mpId => $cantCons) {
            $stStock = $pdo->prepare("
                SELECT COALESCE(SUM(cantidad_existente), 0) AS stock
                FROM stock_materia_prima
                WHERE id_materia_prima = :mp
            ");
            $stStock->execute([':mp' => $mpId]);
            $stock = (int)$stStock->fetchColumn();
            if ($cantCons > $stock) {
                $pdo->rollBack();
                header('Location: view.php?alert=4&msg=' . urlencode(
                    "Stock insuficiente para materia prima #{$mpId}. Disponible: {$stock}."
                ));
                exit;
            }
        }

        $nombreEtapa = obtenerNombreEtapa($pdo, $etapaId, $productoId);

        $ins = $pdo->prepare("
            INSERT INTO control_produccion (
                control_fecha, control_estado, id_inspectores, orden_id,
                id_usuario, producto_id, etapa_id, control_observacion
            ) VALUES (
                CURRENT_DATE, 'REGISTRADO', :inspector, :orden,
                :usuario, :producto, :etapa, :obs
            )
            RETURNING control_id
        ");
        $ins->execute([
            ':inspector' => $inspectorId,
            ':orden' => $ordenId,
            ':usuario' => $usuarioId,
            ':producto' => $productoId,
            ':etapa' => $etapaId,
            ':obs' => $observacion !== '' ? $observacion : null,
        ]);
        $controlId = (int)$ins->fetchColumn();

        $pdo->prepare("
            INSERT INTO control_produccion_detalle (control_id, producto_id, control_cantidad, control_descri)
            VALUES (:c, :p, :cant, :descri)
        ")->execute([
            ':c' => $controlId,
            ':p' => $productoId,
            ':cant' => $cantidad,
            ':descri' => $nombreEtapa,
        ]);

        $insCons = $pdo->prepare("
            INSERT INTO control_produccion_consumo (control_id, id_materia_prima, cantidad_consumida)
            VALUES (:c, :mp, :cant)
        ");
        foreach ($lineasConsumo as $mpId => $cantCons) {
            $insCons->execute([':c' => $controlId, ':mp' => $mpId, ':cant' => $cantCons]);
        }

        $descontar = $pdo->prepare("
            UPDATE stock_materia_prima
            SET cantidad_existente = cantidad_existente - :cant
            WHERE id_materia_prima = :mp AND cantidad_existente >= :cant
        ");
        foreach ($lineasConsumo as $mpId => $cantCons) {
            $cantEntera = (int)$cantCons;
            $descontar->execute([':cant' => $cantEntera, ':mp' => $mpId]);
            if ($descontar->rowCount() === 0) {
                $pdo->rollBack();
                header('Location: view.php?alert=4&msg=' . urlencode('No se pudo descontar stock de materia prima.'));
                exit;
            }
        }

        if ($esUltimaEtapa) {
            $pdo->prepare("
                UPDATE orden_detalle_produccion
                SET cantidad_pendiente = GREATEST(0, cantidad_pendiente - :cant)
                WHERE orden_id = :o AND producto_id = :p
            ")->execute([':cant' => $cantidad, ':o' => $ordenId, ':p' => $productoId]);
            actualizarEstadoOrden($pdo, $ordenId);
        } else {
            $pdo->prepare("
                UPDATE orden_produccion SET orden_prod_estado = 'EN_PROCESO'
                WHERE orden_id = :id AND orden_prod_estado = 'PENDIENTE'
            ")->execute([':id' => $ordenId]);
        }

        bitacoraControl($pdo, $usuarioId, 'ALTA',
            "Control de producción #{$controlId} — OP #{$ordenId}, producto {$productoId}, cant. {$cantidad}",
            $controlId);

        $pdo->commit();
        header('Location: view.php?alert=1');
        exit;
    }

    if ($action === 'update' && isset($_POST['Guardar'])) {
        $controlId = (int)($_POST['control_id'] ?? 0);
        $inspectorId = (int)($_POST['id_inspectores'] ?? 0);
        $observacion = isset($_POST['control_observacion']) ? trim($_POST['control_observacion']) : null;

        $usuarioId = resolverUsuario($pdo);
        if ($controlId <= 0 || $inspectorId <= 0 || $usuarioId <= 0) {
            header('Location: view.php?alert=4');
            exit;
        }

        $pdo->beginTransaction();

        $st = $pdo->prepare("
            SELECT control_estado FROM control_produccion WHERE control_id = :id FOR UPDATE
        ");
        $st->execute([':id' => $controlId]);
        $estado = strtoupper(trim((string)$st->fetchColumn()));
        if ($estado !== 'REGISTRADO') {
            $pdo->rollBack();
            header('Location: view.php?alert=5');
            exit;
        }

        $stInsp = $pdo->prepare("
            SELECT 1 FROM inspectores WHERE id_inspectores = :id AND UPPER(TRIM(inspector_estado)) = 'ACTIVO'
        ");
        $stInsp->execute([':id' => $inspectorId]);
        if (!$stInsp->fetchColumn()) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('Inspector no válido.'));
            exit;
        }

        $pdo->prepare("
            UPDATE control_produccion
            SET id_inspectores = :insp, control_observacion = :obs
            WHERE control_id = :id
        ")->execute([
            ':insp' => $inspectorId,
            ':obs' => $observacion !== '' ? $observacion : null,
            ':id' => $controlId,
        ]);

        bitacoraControl($pdo, $usuarioId, 'MODIFICACION',
            "Se actualizan observaciones/inspector del control #{$controlId}", $controlId);

        $pdo->commit();
        header('Location: view.php?alert=2');
        exit;
    }

    header('Location: view.php?alert=4');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: view.php?alert=4&msg=' . urlencode($e->getMessage()));
}
