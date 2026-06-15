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

if (!check_permission('PRODUCTOS_TERMINADOS', false)) {
    header('Location: view.php?alert=4');
    exit;
}

function bitacoraPt(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void
{
    try {
        $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ")->execute([
            ':id_usuario' => $idUsuario,
            ':entidad' => 'productos terminados',
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

function cantidadFinalizable(PDO $pdo, int $ordenId, int $productoId, int $excluirTerminadoId = 0): int
{
    $stCtrl = $pdo->prepare("
        SELECT COALESCE(SUM(cd.control_cantidad), 0)::int
        FROM control_produccion c
        JOIN control_produccion_detalle cd ON cd.control_id = c.control_id
        JOIN etapa_detalle_produccion ed
            ON ed.etapa_id = c.etapa_id AND ed.producto_id = c.producto_id
        JOIN etapa_produccion ep ON ep.etapa_id = ed.etapa_id
        WHERE c.orden_id = :o AND c.producto_id = :p
          AND UPPER(TRIM(c.control_estado)) = 'REGISTRADO'
          AND UPPER(TRIM(ep.etapa_estado)) = 'ACTIVA'
          AND ed.etapa_secuencia = (
              SELECT MAX(ed2.etapa_secuencia)
              FROM etapa_detalle_produccion ed2
              JOIN etapa_produccion ep2 ON ep2.etapa_id = ed2.etapa_id
              WHERE ed2.producto_id = c.producto_id
                AND UPPER(TRIM(ep2.etapa_estado)) = 'ACTIVA'
          )
    ");
    $stCtrl->execute([':o' => $ordenId, ':p' => $productoId]);
    $procesado = (int)$stCtrl->fetchColumn();

    $sqlPt = "
        SELECT COALESCE(SUM(ptd.terminado_cantidad), 0)::int
        FROM productos_terminados_detalle ptd
        JOIN producto_terminado pt ON pt.terminado_id = ptd.terminado_id
        WHERE pt.orden_id = :o AND ptd.producto_id = :p
    ";
    $params = [':o' => $ordenId, ':p' => $productoId];
    if ($excluirTerminadoId > 0) {
        $sqlPt .= ' AND pt.terminado_id <> :ex';
        $params[':ex'] = $excluirTerminadoId;
    }
    $stPt = $pdo->prepare($sqlPt);
    $stPt->execute($params);
    $terminado = (int)$stPt->fetchColumn();

    return max(0, $procesado - $terminado);
}

function incrementarStock(PDO $pdo, int $productoId, int $depositoId, int $cantidad, int $usuarioId): void
{
    $st = $pdo->prepare("
        SELECT id_stock_productos FROM stock_producto
        WHERE producto_id = :p AND deposito_id = :d
        FOR UPDATE
    ");
    $st->execute([':p' => $productoId, ':d' => $depositoId]);
    $idStock = $st->fetchColumn();

    if ($idStock) {
        $pdo->prepare("
            UPDATE stock_producto
            SET stock_prod_existente = stock_prod_existente + :cant
            WHERE producto_id = :p AND deposito_id = :d
        ")->execute([':cant' => $cantidad, ':p' => $productoId, ':d' => $depositoId]);
    } else {
        $pdo->prepare("
            INSERT INTO stock_producto (producto_id, deposito_id, stock_prod_existente, id_usuario)
            VALUES (:p, :d, :cant, :u)
        ")->execute([':p' => $productoId, ':d' => $depositoId, ':cant' => $cantidad, ':u' => $usuarioId]);
    }
}

function actualizarEstadoOrden(PDO $pdo, int $ordenId): void
{
    $st = $pdo->prepare('SELECT orden_prod_estado FROM orden_produccion WHERE orden_id = :id');
    $st->execute([':id' => $ordenId]);
    $estado = strtoupper(trim((string)$st->fetchColumn()));
    if ($estado === 'ANULADA') {
        return;
    }

    $stPend = $pdo->prepare("
        SELECT COUNT(*) FROM orden_detalle_produccion
        WHERE orden_id = :id AND COALESCE(cantidad_pendiente, 0) > 0
    ");
    $stPend->execute([':id' => $ordenId]);
    $hayPendiente = (int)$stPend->fetchColumn() > 0;

    $stPt = $pdo->prepare("
        SELECT d.producto_id, d.orden_prod_cantidad,
               COALESCE(SUM(ptd.terminado_cantidad), 0)::int AS terminado
        FROM orden_detalle_produccion d
        LEFT JOIN productos_terminados_detalle ptd ON ptd.producto_id = d.producto_id
        LEFT JOIN producto_terminado pt ON pt.terminado_id = ptd.terminado_id AND pt.orden_id = d.orden_id
        WHERE d.orden_id = :id
        GROUP BY d.producto_id, d.orden_prod_cantidad
    ");
    $stPt->execute([':id' => $ordenId]);
    $todoPt = true;
    foreach ($stPt->fetchAll() as $row) {
        if ((int)$row['terminado'] < (int)$row['orden_prod_cantidad']) {
            $todoPt = false;
            break;
        }
    }

    if (!$hayPendiente && $todoPt) {
        $pdo->prepare("
            UPDATE orden_produccion SET orden_prod_estado = 'TERMINADA' WHERE orden_id = :id
        ")->execute([':id' => $ordenId]);
    } elseif ($estado === 'PENDIENTE') {
        $pdo->prepare("
            UPDATE orden_produccion SET orden_prod_estado = 'EN_PROCESO' WHERE orden_id = :id
        ")->execute([':id' => $ordenId]);
    }
}

function normalizarLineas(array $items): array
{
    $lineas = [];
    foreach ($items as $item) {
        $productoId = (int)($item['producto_id'] ?? 0);
        $depositoId = (int)($item['deposito_id'] ?? 0);
        $cantidad = (int)($item['cantidad'] ?? $item['terminado_cantidad'] ?? 0);
        $fechaElab = trim((string)($item['fecha_elab'] ?? $item['terminado_fecha_elab'] ?? ''));
        $fechaVenc = trim((string)($item['fecha_venc'] ?? $item['terminado_fecha_venc'] ?? ''));

        if ($productoId <= 0 || $depositoId <= 0 || $cantidad <= 0) {
            continue;
        }

        // PK detalle: (terminado_id, producto_id) — un depósito por producto por registro
        if (!isset($lineas[$productoId])) {
            $lineas[$productoId] = [
                'producto_id' => $productoId,
                'deposito_id' => $depositoId,
                'cantidad' => 0,
                'fecha_elab' => $fechaElab !== '' ? $fechaElab : null,
                'fecha_venc' => $fechaVenc !== '' ? $fechaVenc : null,
            ];
        }
        $lineas[$productoId]['cantidad'] += $cantidad;
        $lineas[$productoId]['deposito_id'] = $depositoId;
        if ($fechaElab !== '') {
            $lineas[$productoId]['fecha_elab'] = $fechaElab;
        }
        if ($fechaVenc !== '') {
            $lineas[$productoId]['fecha_venc'] = $fechaVenc;
        }
    }
    return array_values($lineas);
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
        $fecha = trim((string)($_POST['terminado_fecha'] ?? date('Y-m-d')));

        $items = [];
        if (!empty($_POST['items'])) {
            $tmp = json_decode($_POST['items'], true);
            if (is_array($tmp)) {
                $items = $tmp;
            }
        }
        $lineas = normalizarLineas($items);

        $usuarioId = resolverUsuario($pdo);
        if ($usuarioId <= 0 || $ordenId <= 0 || empty($lineas)) {
            header('Location: view.php?alert=4&msg=' . urlencode('Complete la orden y al menos un ítem válido.'));
            exit;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            header('Location: view.php?alert=4&msg=' . urlencode('Fecha inválida.'));
            exit;
        }

        $pdo->beginTransaction();

        $stOrden = $pdo->prepare("
            SELECT orden_prod_estado FROM orden_produccion WHERE orden_id = :id FOR UPDATE
        ");
        $stOrden->execute([':id' => $ordenId]);
        $estOrden = strtoupper(trim((string)$stOrden->fetchColumn()));
        if ($estOrden === 'ANULADA' || $estOrden === '') {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('La orden no está disponible.'));
            exit;
        }

        $acumPorProducto = [];
        foreach ($lineas as $ln) {
            $pid = $ln['producto_id'];
            if (!isset($acumPorProducto[$pid])) {
                $acumPorProducto[$pid] = 0;
            }
            $acumPorProducto[$pid] += $ln['cantidad'];
        }

        foreach ($acumPorProducto as $pid => $totalCant) {
            $max = cantidadFinalizable($pdo, $ordenId, (int)$pid);
            if ($totalCant > $max) {
                $pdo->rollBack();
                header('Location: view.php?alert=4&msg=' . urlencode(
                    "Producto #{$pid}: cantidad ({$totalCant}) supera lo finalizable ({$max})."
                ));
                exit;
            }
            if ($max <= 0) {
                $pdo->rollBack();
                header('Location: view.php?alert=4&msg=' . urlencode(
                    "Producto #{$pid}: sin cantidad procesada disponible para finalizar."
                ));
                exit;
            }
        }

        foreach ($lineas as $ln) {
            if ($ln['fecha_elab'] && $ln['fecha_venc'] && $ln['fecha_venc'] < $ln['fecha_elab']) {
                $pdo->rollBack();
                header('Location: view.php?alert=4&msg=' . urlencode('La fecha de vencimiento no puede ser anterior a elaboración.'));
                exit;
            }
            $stDep = $pdo->prepare('SELECT 1 FROM deposito WHERE deposito_id = :id');
            $stDep->execute([':id' => $ln['deposito_id']]);
            if (!$stDep->fetchColumn()) {
                $pdo->rollBack();
                header('Location: view.php?alert=4&msg=' . urlencode('Depósito inválido.'));
                exit;
            }
        }

        $ins = $pdo->prepare("
            INSERT INTO producto_terminado (orden_id, terminado_fecha, id_usuario)
            VALUES (:orden, :fecha, :usuario)
            RETURNING terminado_id
        ");
        $ins->execute([':orden' => $ordenId, ':fecha' => $fecha, ':usuario' => $usuarioId]);
        $terminadoId = (int)$ins->fetchColumn();

        $insDet = $pdo->prepare("
            INSERT INTO productos_terminados_detalle (
                terminado_id, producto_id, terminado_cantidad, deposito_id,
                terminado_fecha_elab, terminado_fecha_venc
            ) VALUES (
                :tid, :prod, :cant, :dep, :elab, :venc
            )
        ");

        foreach ($lineas as $ln) {
            $insDet->execute([
                ':tid' => $terminadoId,
                ':prod' => $ln['producto_id'],
                ':cant' => $ln['cantidad'],
                ':dep' => $ln['deposito_id'],
                ':elab' => $ln['fecha_elab'],
                ':venc' => $ln['fecha_venc'],
            ]);

            incrementarStock($pdo, $ln['producto_id'], $ln['deposito_id'], $ln['cantidad'], $usuarioId);

            $pdo->prepare("
                UPDATE orden_detalle_produccion
                SET cantidad_pendiente = GREATEST(0, COALESCE(cantidad_pendiente, 0) - :cant)
                WHERE orden_id = :o AND producto_id = :p
            ")->execute([
                ':cant' => $ln['cantidad'],
                ':o' => $ordenId,
                ':p' => $ln['producto_id'],
            ]);
        }

        actualizarEstadoOrden($pdo, $ordenId);

        bitacoraPt($pdo, $usuarioId, 'ALTA',
            "Productos terminados #{$terminadoId} — OP #{$ordenId}, " . count($lineas) . ' línea(s)',
            $terminadoId);

        $pdo->commit();
        header('Location: view.php?alert=1');
        exit;
    }

    if ($action === 'update' && isset($_POST['Guardar'])) {
        $terminadoId = (int)($_POST['terminado_id'] ?? 0);
        $fecha = trim((string)($_POST['terminado_fecha'] ?? ''));

        $usuarioId = resolverUsuario($pdo);
        if ($terminadoId <= 0 || $usuarioId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            header('Location: view.php?alert=4');
            exit;
        }

        $pdo->beginTransaction();

        $st = $pdo->prepare('SELECT orden_id FROM producto_terminado WHERE terminado_id = :id FOR UPDATE');
        $st->execute([':id' => $terminadoId]);
        if (!$st->fetchColumn()) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('Registro no encontrado.'));
            exit;
        }

        $pdo->prepare('UPDATE producto_terminado SET terminado_fecha = :f WHERE terminado_id = :id')
            ->execute([':f' => $fecha, ':id' => $terminadoId]);

        bitacoraPt($pdo, $usuarioId, 'MODIFICACION',
            "Se actualiza fecha del registro PT #{$terminadoId}", $terminadoId);

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
