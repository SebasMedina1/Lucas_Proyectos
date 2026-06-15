<?php
/**
 * Funciones compartidas — reposición de materia prima.
 */

function bitacoraRep(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void
{
    try {
        $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ")->execute([
            ':id_usuario' => $idUsuario,
            ':entidad' => 'reposicion materia prima',
            ':id_registro' => $idRegistro,
            ':accion' => strtoupper($accion),
            ':descripcion' => $descripcion,
        ]);
    } catch (Throwable $e) {
        error_log('Bitácora falló: ' . $e->getMessage());
    }
}

function resolverUsuarioRep(PDO $pdo): int
{
    $usuarioId = (int)($_SESSION['usua_id'] ?? $_SESSION['id_usuario'] ?? 0);
    if ($usuarioId === 0) {
        $q = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1');
        $q->execute([':u' => $_SESSION['username'] ?? '']);
        $usuarioId = (int)$q->fetchColumn();
    }
    return $usuarioId;
}

function normalizarLineasRep(array $items): array
{
    $lineas = [];
    foreach ($items as $item) {
        $mpId = (int)($item['id_materia_prima'] ?? $item['codigo'] ?? 0);
        $cant = (int)($item['cantidad'] ?? $item['reposicion_cantidad'] ?? 0);
        if ($mpId <= 0 || $cant <= 0) {
            continue;
        }
        $lineas[$mpId] = ($lineas[$mpId] ?? 0) + $cant;
    }
    return $lineas;
}

function incrementarStockMp(PDO $pdo, int $mpId, int $depositoId, int $cantidad, int $usuarioId): void
{
    $st = $pdo->prepare("
        SELECT id_stock FROM stock_materia_prima
        WHERE id_materia_prima = :mp AND deposito_id = :d
        FOR UPDATE
    ");
    $st->execute([':mp' => $mpId, ':d' => $depositoId]);
    $idStock = $st->fetchColumn();

    if ($idStock) {
        $pdo->prepare("
            UPDATE stock_materia_prima
            SET cantidad_existente = cantidad_existente + :cant
            WHERE id_materia_prima = :mp AND deposito_id = :d
        ")->execute([':cant' => $cantidad, ':mp' => $mpId, ':d' => $depositoId]);
    } else {
        $pdo->prepare("
            INSERT INTO stock_materia_prima (
                id_materia_prima, deposito_id, cantidad_existente,
                stock_cantidad_minima, stock_cantidad_maxima, id_usuario
            ) VALUES (:mp, :d, :cant, 0, 0, :u)
        ")->execute([':mp' => $mpId, ':d' => $depositoId, ':cant' => $cantidad, ':u' => $usuarioId]);
    }
}

function decrementarStockMp(PDO $pdo, int $mpId, int $depositoId, int $cantidad): void
{
    if ($cantidad <= 0) {
        return;
    }
    $pdo->prepare("
        UPDATE stock_materia_prima
        SET cantidad_existente = GREATEST(0, cantidad_existente - :cant)
        WHERE id_materia_prima = :mp AND deposito_id = :d
    ")->execute([':cant' => $cantidad, ':mp' => $mpId, ':d' => $depositoId]);
}

function actualizarEstadoPedido(PDO $pdo, int $pedidoId): void
{
    $st = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            COUNT(*) FILTER (WHERE cantidad_repuesta >= ped_mat_prod_cantidad) AS completas
        FROM pedido_materia_detalle_produccion
        WHERE id_pedido_mat_prod = :id
    ");
    $st->execute([':id' => $pedidoId]);
    $row = $st->fetch();
    $total = (int)($row['total'] ?? 0);
    $completas = (int)($row['completas'] ?? 0);

    $nuevoEstado = 'PENDIENTE';
    if ($total > 0 && $completas >= $total) {
        $nuevoEstado = 'COMPLETADO';
    } else {
        $stRep = $pdo->prepare("
            SELECT COALESCE(SUM(cantidad_repuesta), 0)::int
            FROM pedido_materia_detalle_produccion WHERE id_pedido_mat_prod = :id
        ");
        $stRep->execute([':id' => $pedidoId]);
        if ((int)$stRep->fetchColumn() > 0) {
            $nuevoEstado = 'PARCIAL';
        }
    }

    $pdo->prepare("
        UPDATE pedido_materia_produccion SET ped_mat_prod_estado = :e
        WHERE id_pedido_mat_prod = :id AND UPPER(TRIM(ped_mat_prod_estado)) <> 'ANULADO'
    ")->execute([':e' => $nuevoEstado, ':id' => $pedidoId]);
}
