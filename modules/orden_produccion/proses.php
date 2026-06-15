<?php
session_start();

if (!isset($_SESSION['id_usuario']) && isset($_SESSION['usua_id'])) {
    $_SESSION['id_usuario'] = $_SESSION['usua_id'];
}

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');

if (empty($_SESSION['username'])) {
    echo "<script>alert('Sesión inválida'); window.location.href='../../login.html';</script>";
    exit;
}

if (!check_permission('ORDEN_PRODUCCION', false)) {
    header('Location: view.php?alert=4');
    exit;
}

function bitacoraOrden(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ");
        $stmt->execute([
            ':id_usuario' => $idUsuario,
            ':entidad' => 'orden produccion',
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

function pedidoDisponibleParaOrden(PDO $pdo, int $pedidoId): ?string
{
    $st = $pdo->prepare("
        SELECT pedido_prod_estado
        FROM pedido_produccion
        WHERE id_pedido_produccion = :id
    ");
    $st->execute([':id' => $pedidoId]);
    $estado = $st->fetchColumn();
    if ($estado === false) {
        return 'El pedido de producción no existe.';
    }
    if (strtoupper(trim((string)$estado)) !== 'PENDIENTE') {
        return 'El pedido debe estar en estado PENDIENTE para generar una orden.';
    }
    $stOp = $pdo->prepare("
        SELECT orden_id
        FROM orden_produccion
        WHERE id_pedido_produccion = :id
          AND orden_prod_estado <> 'ANULADA'
        LIMIT 1
    ");
    $stOp->execute([':id' => $pedidoId]);
    $ordenId = $stOp->fetchColumn();
    if ($ordenId) {
        return "El pedido ya tiene la orden de producción #{$ordenId}.";
    }
    return null;
}

function cargarDetallePedido(PDO $pdo, int $pedidoId): array
{
    $st = $pdo->prepare("
        SELECT d.producto_id, d.cantidad_pedido, p.producto_estado, p.producto_descri
        FROM pedido_detalle_produccion d
        JOIN productos p ON p.producto_id = d.producto_id
        WHERE d.id_pedido_produccion = :id
    ");
    $st->execute([':id' => $pedidoId]);
    $lineas = [];
    foreach ($st->fetchAll() as $row) {
        $pid = (int)$row['producto_id'];
        $cant = (int)$row['cantidad_pedido'];
        if ($pid <= 0 || $cant <= 0) {
            continue;
        }
        if (strtoupper(trim((string)$row['producto_estado'])) !== 'ACTIVO') {
            return ['error' => 'El producto "' . $row['producto_descri'] . '" no está activo.'];
        }
        $lineas[$pid] = $cant;
    }
    if (empty($lineas)) {
        return ['error' => 'El pedido no tiene productos válidos en el detalle.'];
    }
    return ['lineas' => $lineas];
}

function validarFechaEntrega(?string $fecha): ?string
{
    if ($fecha === null || trim($fecha) === '') {
        return 'Debe indicar la fecha de entrega prevista.';
    }
    $fecha = trim($fecha);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return 'Fecha de entrega inválida.';
    }
    date_default_timezone_set('America/Asuncion');
    $hoy = date('Y-m-d');
    // Comparar solo la parte fecha (Y-m-d) evita desfases por zona horaria con strtotime()
    if ($fecha < $hoy) {
        return 'La fecha de entrega no puede ser anterior a hoy.';
    }
    return null;
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
        $pedidoId = isset($_POST['id_pedido_produccion']) ? (int)$_POST['id_pedido_produccion'] : 0;
        $fechaEntrega = isset($_POST['orden_prod_fecha_entrega']) ? trim($_POST['orden_prod_fecha_entrega']) : '';

        $usuarioId = resolverUsuario($pdo);
        if ($usuarioId <= 0 || $pedidoId <= 0) {
            header('Location: view.php?alert=4&msg=' . urlencode('Datos incompletos para registrar la orden.'));
            exit;
        }

        $errFecha = validarFechaEntrega($fechaEntrega);
        if ($errFecha) {
            header('Location: view.php?alert=4&msg=' . urlencode($errFecha));
            exit;
        }

        $errPed = pedidoDisponibleParaOrden($pdo, $pedidoId);
        if ($errPed) {
            header('Location: view.php?alert=4&msg=' . urlencode($errPed));
            exit;
        }

        $det = cargarDetallePedido($pdo, $pedidoId);
        if (isset($det['error'])) {
            header('Location: view.php?alert=4&msg=' . urlencode($det['error']));
            exit;
        }
        $lineas = $det['lineas'];

        $pdo->beginTransaction();

        $ins = $pdo->prepare("
            INSERT INTO orden_produccion (
                orden_prod_fecha, orden_prod_fecha_entrega, orden_prod_estado,
                id_usuario, id_pedido_produccion
            ) VALUES (
                CURRENT_DATE, :fecha_entrega, 'PENDIENTE',
                :usuario, :pedido
            )
            RETURNING orden_id
        ");
        $ins->execute([
            ':fecha_entrega' => $fechaEntrega,
            ':usuario' => $usuarioId,
            ':pedido' => $pedidoId,
        ]);
        $ordenId = (int)$ins->fetchColumn();

        $insDet = $pdo->prepare("
            INSERT INTO orden_detalle_produccion (orden_id, producto_id, orden_prod_cantidad, cantidad_pendiente)
            VALUES (:orden, :producto, :cantidad, :pendiente)
        ");
        foreach ($lineas as $productoId => $cantidad) {
            $insDet->execute([
                ':orden' => $ordenId,
                ':producto' => $productoId,
                ':cantidad' => $cantidad,
                ':pendiente' => $cantidad,
            ]);
        }

        $pdo->prepare("
            UPDATE pedido_produccion
            SET pedido_prod_estado = 'ASIGNADO',
                pedido_prod_ultima_modificacion = CURRENT_TIMESTAMP
            WHERE id_pedido_produccion = :id
        ")->execute([':id' => $pedidoId]);

        bitacoraOrden($pdo, $usuarioId, 'ALTA',
            "Se genera Orden de Producción #{$ordenId} desde pedido #{$pedidoId}", $ordenId);

        $pdo->commit();
        header('Location: view.php?alert=1');
        exit;
    }

    if ($action === 'update' && isset($_POST['Guardar'])) {
        $ordenId = isset($_POST['orden_id']) ? (int)$_POST['orden_id'] : 0;
        $fechaEntrega = isset($_POST['orden_prod_fecha_entrega']) ? trim($_POST['orden_prod_fecha_entrega']) : '';

        $usuarioId = resolverUsuario($pdo);
        if ($ordenId <= 0 || $usuarioId <= 0) {
            header('Location: view.php?alert=4');
            exit;
        }

        $errFecha = validarFechaEntrega($fechaEntrega);
        if ($errFecha) {
            header('Location: view.php?alert=4&msg=' . urlencode($errFecha));
            exit;
        }

        $pdo->beginTransaction();

        $st = $pdo->prepare("
            SELECT orden_prod_estado, orden_prod_fecha_entrega::text AS fecha_entrega_ant
            FROM orden_produccion
            WHERE orden_id = :id
            FOR UPDATE
        ");
        $st->execute([':id' => $ordenId]);
        $cab = $st->fetch();

        if (!$cab) {
            $pdo->rollBack();
            header('Location: view.php?alert=4');
            exit;
        }

        if (strtoupper(trim((string)$cab['orden_prod_estado'])) !== 'PENDIENTE') {
            $pdo->rollBack();
            header('Location: view.php?alert=5&msg=' . urlencode(
                'Solo se puede modificar la fecha de entrega en órdenes PENDIENTES.'
            ));
            exit;
        }

        $stCtrl = $pdo->prepare('SELECT COUNT(*) FROM control_produccion WHERE orden_id = :id');
        $stCtrl->execute([':id' => $ordenId]);
        if ((int)$stCtrl->fetchColumn() > 0) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode(
                'No se puede modificar: la orden ya tiene control de producción registrado.'
            ));
            exit;
        }

        $fechaAnt = $cab['fecha_entrega_ant'] ?? '';
        if ($fechaAnt && strpos($fechaAnt, ' ') !== false) {
            $fechaAnt = substr($fechaAnt, 0, 10);
        }

        $pdo->prepare("
            UPDATE orden_produccion
            SET orden_prod_fecha_entrega = :fecha
            WHERE orden_id = :id
        ")->execute([
            ':fecha' => $fechaEntrega,
            ':id' => $ordenId,
        ]);

        bitacoraOrden($pdo, $usuarioId, 'MODIFICACION',
            "Se modifica fecha de entrega de la OP #{$ordenId}: {$fechaAnt} → {$fechaEntrega}",
            $ordenId);

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
