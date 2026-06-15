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

if (!check_permission('PEDIDO_PRODUCCION', false)) {
    header('Location: view.php?alert=4');
    exit;
}

function bitacora(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ");
        $stmt->execute([
            ':id_usuario' => $idUsuario,
            ':entidad' => 'pedido produccion',
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

function normalizarDetalle(array $detalle): array
{
    $lineas = [];
    foreach ($detalle as $item) {
        $productoId = (int)($item['codigo'] ?? $item['producto_id'] ?? 0);
        $cantidad = (int)($item['cantidad'] ?? 0);
        if ($productoId <= 0 || $cantidad <= 0) {
            continue;
        }
        if (isset($lineas[$productoId])) {
            $lineas[$productoId] += $cantidad;
        } else {
            $lineas[$productoId] = $cantidad;
        }
    }
    return $lineas;
}

function validarProductosActivos(PDO $pdo, array $lineas): ?string
{
    if (empty($lineas)) {
        return 'El pedido debe incluir al menos un producto con cantidad mayor a cero.';
    }

    $ids = array_keys($lineas);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("
        SELECT producto_id, producto_descri, producto_estado
        FROM productos
        WHERE producto_id IN ($placeholders)
    ");
    $st->execute($ids);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    if (count($rows) !== count($ids)) {
        return 'Uno o más productos no existen en el sistema.';
    }

    foreach ($rows as $row) {
        if (strtoupper(trim((string)$row['producto_estado'])) !== 'ACTIVO') {
            return 'El producto "' . $row['producto_descri'] . '" no está activo.';
        }
    }

    return null;
}

function validarTipoPedido(PDO $pdo, int $idTipo): ?string
{
    if ($idTipo <= 0) {
        return 'Debe seleccionar un tipo de pedido.';
    }
    $st = $pdo->prepare("
        SELECT tipo_pedido_estado
        FROM tipo_pedido
        WHERE id_tipo_pedido = :id
        LIMIT 1
    ");
    $st->execute([':id' => $idTipo]);
    $estado = $st->fetchColumn();
    if ($estado === false) {
        return 'El tipo de pedido seleccionado no existe.';
    }
    if (strtoupper(trim((string)$estado)) !== 'ACTIVO') {
        return 'El tipo de pedido seleccionado no está activo.';
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
        $pedidoId = isset($_POST['codigo']) ? (int)$_POST['codigo'] : 0;
        $idTipoPedido = isset($_POST['id_tipo_pedido']) ? (int)$_POST['id_tipo_pedido'] : 0;
        $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : null;

        $detalle = [];
        if (!empty($_POST['productos'])) {
            $tmp = json_decode($_POST['productos'], true);
            if (is_array($tmp)) {
                $detalle = $tmp;
            }
        }

        [$usuarioId, $sucursalId] = resolverUsuarioSucursal($pdo);

        if ($usuarioId <= 0 || $sucursalId <= 0) {
            header('Location: view.php?alert=4&msg=' . urlencode('No se pudo obtener usuario o sucursal.'));
            exit;
        }

        if ($pedidoId <= 0) {
            header('Location: view.php?alert=4&msg=' . urlencode('Código de pedido inválido.'));
            exit;
        }

        $errTipo = validarTipoPedido($pdo, $idTipoPedido);
        if ($errTipo) {
            header('Location: view.php?alert=4&msg=' . urlencode($errTipo));
            exit;
        }

        $lineas = normalizarDetalle($detalle);
        $errProd = validarProductosActivos($pdo, $lineas);
        if ($errProd) {
            header('Location: view.php?alert=4&msg=' . urlencode($errProd));
            exit;
        }

        $pdo->beginTransaction();

        $existe = $pdo->prepare('SELECT 1 FROM pedido_produccion WHERE id_pedido_produccion = :id');
        $existe->execute([':id' => $pedidoId]);
        if ($existe->fetchColumn()) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('El número de pedido ya existe. Recargue el formulario.'));
            exit;
        }

        $ins = $pdo->prepare("
            INSERT INTO pedido_produccion (
                id_pedido_produccion, pedido_prod_fecha_emision, pedido_prod_estado,
                id_tipo_pedido, id_usuario, id_sucursal, pedido_prod_observaciones,
                pedido_prod_ultima_modificacion
            ) VALUES (
                :id, CURRENT_DATE, 'PENDIENTE',
                :tipo, :usuario, :sucursal, :obs,
                CURRENT_TIMESTAMP
            )
        ");
        $ins->execute([
            ':id' => $pedidoId,
            ':tipo' => $idTipoPedido,
            ':usuario' => $usuarioId,
            ':sucursal' => $sucursalId,
            ':obs' => $observaciones !== '' ? $observaciones : null,
        ]);

        bitacora($pdo, $usuarioId, 'ALTA', "Se registra Pedido de Producción #{$pedidoId}", $pedidoId);

        $insDet = $pdo->prepare("
            INSERT INTO pedido_detalle_produccion (id_pedido_produccion, producto_id, cantidad_pedido)
            VALUES (:pedido, :producto, :cantidad)
        ");
        foreach ($lineas as $productoId => $cantidad) {
            $insDet->execute([
                ':pedido' => $pedidoId,
                ':producto' => $productoId,
                ':cantidad' => $cantidad,
            ]);
            bitacora($pdo, $usuarioId, 'ALTA',
                "Detalle pedido prod. {$pedidoId}: producto {$productoId}, cant. {$cantidad}",
                $pedidoId);
        }

        $pdo->commit();
        header('Location: view.php?alert=1');
        exit;
    }

    if ($action === 'update' && isset($_POST['Guardar'])) {
        $pedidoId = isset($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : 0;
        $idTipoPedido = isset($_POST['id_tipo_pedido']) ? (int)$_POST['id_tipo_pedido'] : 0;
        $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : null;
        $cantidades = isset($_POST['cantidad']) && is_array($_POST['cantidad']) ? $_POST['cantidad'] : [];
        $productosEliminados = !empty($_POST['productos_eliminados'])
            ? json_decode($_POST['productos_eliminados'], true) : [];
        $productosNuevos = !empty($_POST['productos_nuevos'])
            ? json_decode($_POST['productos_nuevos'], true) : [];
        $ultimaModForm = $_POST['pedido_prod_ultima_modificacion'] ?? null;

        [$usuarioId] = resolverUsuarioSucursal($pdo);

        if ($pedidoId <= 0 || $usuarioId <= 0) {
            header('Location: view.php?alert=4');
            exit;
        }

        $errTipo = validarTipoPedido($pdo, $idTipoPedido);
        if ($errTipo) {
            header('Location: view.php?alert=4&msg=' . urlencode($errTipo));
            exit;
        }

        date_default_timezone_set('America/Asuncion');
        $stamp = date('Y-m-d H:i:s');

        $pdo->beginTransaction();

        $st = $pdo->prepare("
            SELECT pedido_prod_estado, pedido_prod_ultima_modificacion::text AS ultima_mod
            FROM pedido_produccion
            WHERE id_pedido_produccion = :id
            FOR UPDATE
        ");
        $st->execute([':id' => $pedidoId]);
        $cab = $st->fetch();

        if (!$cab) {
            $pdo->rollBack();
            header('Location: view.php?alert=4');
            exit;
        }

        if ($ultimaModForm && $cab['ultima_mod'] && trim($ultimaModForm) !== trim($cab['ultima_mod'])) {
            $pdo->rollBack();
            header('Location: view.php?alert=6&msg=' . urlencode(
                'El pedido fue modificado por otro usuario. Recargue la página.'
            ));
            exit;
        }

        $estado = strtoupper(trim((string)$cab['pedido_prod_estado']));
        if ($estado !== 'PENDIENTE') {
            $pdo->rollBack();
            header('Location: view.php?alert=5&msg=' . urlencode(
                "Solo se pueden editar pedidos PENDIENTES. Estado actual: {$estado}."
            ));
            exit;
        }

        $pdo->prepare("
            UPDATE pedido_produccion
            SET id_tipo_pedido = :tipo,
                pedido_prod_observaciones = :obs,
                pedido_prod_ultima_modificacion = CURRENT_TIMESTAMP
            WHERE id_pedido_produccion = :id
        ")->execute([
            ':tipo' => $idTipoPedido,
            ':obs' => $observaciones !== '' ? $observaciones : null,
            ':id' => $pedidoId,
        ]);

        if (is_array($productosEliminados)) {
            $del = $pdo->prepare("
                DELETE FROM pedido_detalle_produccion
                WHERE id_pedido_produccion = :pedido AND producto_id = :producto
            ");
            foreach ($productosEliminados as $idElim) {
                $idElim = (int)$idElim;
                if ($idElim > 0) {
                    $del->execute([':pedido' => $pedidoId, ':producto' => $idElim]);
                    bitacora($pdo, $usuarioId, 'MODIFICACION',
                        "[{$stamp}] Elimina producto {$idElim} del pedido {$pedidoId}", $pedidoId);
                }
            }
        }

        if (is_array($productosNuevos) && !empty($productosNuevos)) {
            $lineasNuevas = normalizarDetalle($productosNuevos);
            if (!empty($lineasNuevas)) {
                $errProd = validarProductosActivos($pdo, $lineasNuevas);
                if ($errProd) {
                    $pdo->rollBack();
                    header('Location: view.php?alert=4&msg=' . urlencode($errProd));
                    exit;
                }
                $insDet = $pdo->prepare("
                    INSERT INTO pedido_detalle_produccion (id_pedido_produccion, producto_id, cantidad_pedido)
                    VALUES (:pedido, :producto, :cantidad)
                    ON CONFLICT (id_pedido_produccion, producto_id)
                    DO UPDATE SET cantidad_pedido = EXCLUDED.cantidad_pedido
                ");
                foreach ($lineasNuevas as $productoId => $cantidad) {
                    $insDet->execute([
                        ':pedido' => $pedidoId,
                        ':producto' => $productoId,
                        ':cantidad' => $cantidad,
                    ]);
                    bitacora($pdo, $usuarioId, 'MODIFICACION',
                        "[{$stamp}] Agrega/actualiza producto {$productoId} en pedido {$pedidoId}", $pedidoId);
                }
            }
        }

        $upd = $pdo->prepare("
            UPDATE pedido_detalle_produccion
            SET cantidad_pedido = :cantidad
            WHERE id_pedido_produccion = :pedido AND producto_id = :producto
        ");
        foreach ($cantidades as $productoId => $cantidad) {
            $productoId = (int)$productoId;
            $cantidad = (int)$cantidad;
            if ($productoId <= 0 || $cantidad <= 0) {
                $pdo->rollBack();
                header('Location: view.php?alert=4&msg=' . urlencode('Cantidad inválida en el detalle.'));
                exit;
            }
            $upd->execute([
                ':cantidad' => $cantidad,
                ':pedido' => $pedidoId,
                ':producto' => $productoId,
            ]);
        }

        $cnt = $pdo->prepare('SELECT COUNT(*) FROM pedido_detalle_produccion WHERE id_pedido_produccion = :id');
        $cnt->execute([':id' => $pedidoId]);
        if ((int)$cnt->fetchColumn() === 0) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('El pedido debe tener al menos un producto.'));
            exit;
        }

        $stDet = $pdo->prepare("
            SELECT d.producto_id, p.producto_estado, p.producto_descri
            FROM pedido_detalle_produccion d
            JOIN productos p ON p.producto_id = d.producto_id
            WHERE d.id_pedido_produccion = :id
        ");
        $stDet->execute([':id' => $pedidoId]);
        foreach ($stDet->fetchAll() as $row) {
            if (strtoupper(trim((string)$row['producto_estado'])) !== 'ACTIVO') {
                $pdo->rollBack();
                header('Location: view.php?alert=4&msg=' . urlencode(
                    'El producto "' . $row['producto_descri'] . '" no está activo.'
                ));
                exit;
            }
        }

        bitacora($pdo, $usuarioId, 'MODIFICACION', "Se modifica Pedido de Producción #{$pedidoId}", $pedidoId);

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
