<?php
session_start();

if (!isset($_SESSION['id_usuario']) && isset($_SESSION['usua_id'])) {
    $_SESSION['id_usuario'] = $_SESSION['usua_id'];
}
if (!isset($_SESSION['id_sucursal']) && isset($_SESSION['sucursal_id'])) {
    $_SESSION['id_sucursal'] = $_SESSION['sucursal_id'];
}

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');

if (empty($_SESSION['username'])) {
    echo "<script>alert('Sesión inválida'); window.location.href='../../login.html';</script>";
    exit;
}

if (!check_permission('PEDIDO_MATERIA_PRIMA', false)) {
    header('Location: view.php?alert=4');
    exit;
}

function bitacoraPedMp(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void
{
    try {
        $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ")->execute([
            ':id_usuario' => $idUsuario,
            ':entidad' => 'pedido materia prima',
            ':id_registro' => $idRegistro,
            ':accion' => strtoupper($accion),
            ':descripcion' => $descripcion,
        ]);
    } catch (Throwable $e) {
        error_log('Bitácora falló: ' . $e->getMessage());
    }
}

function resolverUsuarioSucursal(PDO $pdo): array
{
    $usuarioId = (int)($_SESSION['usua_id'] ?? $_SESSION['id_usuario'] ?? 0);
    $sucursalId = (int)($_SESSION['sucursal_id'] ?? $_SESSION['id_sucursal'] ?? 0);

    if ($usuarioId > 0 && $sucursalId === 0) {
        $q = $pdo->prepare('SELECT id_sucursal FROM usuarios WHERE id_usuario = :id LIMIT 1');
        $q->execute([':id' => $usuarioId]);
        $sucursalId = (int)$q->fetchColumn();
    }

    if ($usuarioId === 0) {
        $q = $pdo->prepare('SELECT id_usuario, id_sucursal FROM usuarios WHERE username = :u LIMIT 1');
        $q->execute([':u' => $_SESSION['username'] ?? '']);
        $usr = $q->fetch(PDO::FETCH_ASSOC);
        if ($usr) {
            $usuarioId = (int)$usr['id_usuario'];
            $sucursalId = (int)$usr['id_sucursal'];
        }
    }

    return [$usuarioId, $sucursalId];
}

function normalizarDetalle(array $items): array
{
    $lineas = [];
    foreach ($items as $item) {
        $mpId = (int)($item['id_materia_prima'] ?? $item['codigo'] ?? 0);
        $cant = (int)($item['cantidad'] ?? $item['ped_mat_prod_cantidad'] ?? 0);
        if ($mpId <= 0 || $cant <= 0) {
            continue;
        }
        $lineas[$mpId] = ($lineas[$mpId] ?? 0) + $cant;
    }
    return $lineas;
}

function validarMateriasActivas(PDO $pdo, array $lineas): ?string
{
    if (empty($lineas)) {
        return 'Agregue al menos una materia prima con cantidad mayor a cero.';
    }
    $ids = array_keys($lineas);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("
        SELECT id_materia_prima, materia_prima_descripcion, materia_prima_estado
        FROM materia_prima WHERE id_materia_prima IN ($ph)
    ");
    $st->execute($ids);
    $rows = $st->fetchAll();
    if (count($rows) !== count($ids)) {
        return 'Una o más materias primas no existen.';
    }
    foreach ($rows as $row) {
        if (strtoupper(trim((string)$row['materia_prima_estado'])) !== 'ACTIVO') {
            return 'La materia prima "' . $row['materia_prima_descripcion'] . '" no está activa.';
        }
    }
    return null;
}

function pedidoEditable(PDO $pdo, int $pedidoId): array
{
    $st = $pdo->prepare("
        SELECT ped_mat_prod_estado FROM pedido_materia_produccion
        WHERE id_pedido_mat_prod = :id FOR UPDATE
    ");
    $st->execute([':id' => $pedidoId]);
    $estado = strtoupper(trim((string)$st->fetchColumn()));
    if ($estado === '' || $estado === 'ANULADO') {
        return ['ok' => false, 'error' => 'Pedido no encontrado o anulado.'];
    }
    if ($estado !== 'PENDIENTE') {
        return ['ok' => false, 'error' => 'Solo se puede modificar un pedido en estado PENDIENTE.'];
    }
    $stRep = $pdo->prepare("
        SELECT COUNT(*) FROM reposicion_materia
        WHERE id_pedido_mat_prod = :id AND UPPER(TRIM(reposicion_estado)) = 'REGISTRADO'
    ");
    $stRep->execute([':id' => $pedidoId]);
    if ((int)$stRep->fetchColumn() > 0) {
        return ['ok' => false, 'error' => 'El pedido ya tiene reposiciones registradas.'];
    }
    $stDet = $pdo->prepare("
        SELECT COALESCE(SUM(cantidad_repuesta), 0)::int FROM pedido_materia_detalle_produccion
        WHERE id_pedido_mat_prod = :id
    ");
    $stDet->execute([':id' => $pedidoId]);
    if ((int)$stDet->fetchColumn() > 0) {
        return ['ok' => false, 'error' => 'El pedido ya tiene cantidades repuestas.'];
    }
    return ['ok' => true];
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
        $fecha = trim((string)($_POST['ped_mat_prod_fecha'] ?? date('Y-m-d')));
        $depositoId = (int)($_POST['deposito_id'] ?? 0);

        $items = [];
        if (!empty($_POST['items'])) {
            $tmp = json_decode($_POST['items'], true);
            if (is_array($tmp)) {
                $items = $tmp;
            }
        }
        $lineas = normalizarDetalle($items);

        [$usuarioId, $sucursalId] = resolverUsuarioSucursal($pdo);
        if ($usuarioId <= 0 || $sucursalId <= 0 || $depositoId <= 0) {
            header('Location: view.php?alert=4&msg=' . urlencode('Complete usuario, sucursal y depósito destino.'));
            exit;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            header('Location: view.php?alert=4&msg=' . urlencode('Fecha inválida.'));
            exit;
        }

        $errMp = validarMateriasActivas($pdo, $lineas);
        if ($errMp) {
            header('Location: view.php?alert=4&msg=' . urlencode($errMp));
            exit;
        }

        $stDep = $pdo->prepare('SELECT 1 FROM deposito WHERE deposito_id = :id');
        $stDep->execute([':id' => $depositoId]);
        if (!$stDep->fetchColumn()) {
            header('Location: view.php?alert=4&msg=' . urlencode('Depósito inválido.'));
            exit;
        }

        $pdo->beginTransaction();

        $ins = $pdo->prepare("
            INSERT INTO pedido_materia_produccion (
                ped_mat_prod_fecha, ped_mat_prod_estado, id_usuario, id_sucursal, deposito_id
            ) VALUES (:fecha, 'PENDIENTE', :usuario, :sucursal, :dep)
            RETURNING id_pedido_mat_prod
        ");
        $ins->execute([
            ':fecha' => $fecha,
            ':usuario' => $usuarioId,
            ':sucursal' => $sucursalId,
            ':dep' => $depositoId,
        ]);
        $pedidoId = (int)$ins->fetchColumn();

        $insDet = $pdo->prepare("
            INSERT INTO pedido_materia_detalle_produccion (
                id_pedido_mat_prod, id_materia_prima, ped_mat_prod_cantidad, cantidad_repuesta
            ) VALUES (:ped, :mp, :cant, 0)
        ");
        foreach ($lineas as $mpId => $cant) {
            $insDet->execute([':ped' => $pedidoId, ':mp' => $mpId, ':cant' => $cant]);
        }

        bitacoraPedMp($pdo, $usuarioId, 'ALTA',
            "Pedido MP #{$pedidoId} — depósito #{$depositoId}, " . count($lineas) . ' ítem(s)',
            $pedidoId);

        $pdo->commit();
        header('Location: view.php?alert=1');
        exit;
    }

    if ($action === 'update' && isset($_POST['Guardar'])) {
        $pedidoId = (int)($_POST['id_pedido_mat_prod'] ?? 0);
        $fecha = trim((string)($_POST['ped_mat_prod_fecha'] ?? ''));
        $depositoId = (int)($_POST['deposito_id'] ?? 0);

        $items = [];
        if (!empty($_POST['items'])) {
            $tmp = json_decode($_POST['items'], true);
            if (is_array($tmp)) {
                $items = $tmp;
            }
        }
        $lineas = normalizarDetalle($items);

        [$usuarioId] = resolverUsuarioSucursal($pdo);
        if ($pedidoId <= 0 || $usuarioId <= 0 || $depositoId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            header('Location: view.php?alert=4');
            exit;
        }

        $errMp = validarMateriasActivas($pdo, $lineas);
        if ($errMp) {
            header('Location: view.php?alert=4&msg=' . urlencode($errMp));
            exit;
        }

        $pdo->beginTransaction();

        $chk = pedidoEditable($pdo, $pedidoId);
        if (!$chk['ok']) {
            $pdo->rollBack();
            header('Location: view.php?alert=5&msg=' . urlencode($chk['error']));
            exit;
        }

        $pdo->prepare("
            UPDATE pedido_materia_produccion
            SET ped_mat_prod_fecha = :f, deposito_id = :d
            WHERE id_pedido_mat_prod = :id
        ")->execute([':f' => $fecha, ':d' => $depositoId, ':id' => $pedidoId]);

        $pdo->prepare('DELETE FROM pedido_materia_detalle_produccion WHERE id_pedido_mat_prod = :id')
            ->execute([':id' => $pedidoId]);

        $insDet = $pdo->prepare("
            INSERT INTO pedido_materia_detalle_produccion (
                id_pedido_mat_prod, id_materia_prima, ped_mat_prod_cantidad, cantidad_repuesta
            ) VALUES (:ped, :mp, :cant, 0)
        ");
        foreach ($lineas as $mpId => $cant) {
            $insDet->execute([':ped' => $pedidoId, ':mp' => $mpId, ':cant' => $cant]);
        }

        bitacoraPedMp($pdo, $usuarioId, 'MODIFICACION',
            "Pedido MP #{$pedidoId} actualizado", $pedidoId);

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
