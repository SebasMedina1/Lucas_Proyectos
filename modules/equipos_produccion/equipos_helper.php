<?php
/**
 * Helpers — equipos de trabajo por orden / etapa.
 */

function bitacoraEquipo(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void
{
    try {
        $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ")->execute([
            ':id_usuario' => $idUsuario,
            ':entidad' => 'equipos produccion',
            ':id_registro' => $idRegistro,
            ':accion' => strtoupper($accion),
            ':descripcion' => $descripcion,
        ]);
    } catch (Throwable $e) {
        error_log('Bitácora falló: ' . $e->getMessage());
    }
}

function resolverUsuarioEquipo(PDO $pdo): array
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

function parseMiembrosEquipo(array $items): array
{
    $lineas = [];
    foreach ($items as $item) {
        $tid = (int)($item['trabajadores_id'] ?? 0);
        $rol = trim((string)($item['tarea_rol'] ?? ''));
        if ($tid <= 0 || $rol === '') {
            continue;
        }
        if (isset($lineas[$tid])) {
            continue;
        }
        $lineas[$tid] = [
            'trabajadores_id' => $tid,
            'tarea_rol' => substr($rol, 0, 100),
        ];
    }
    return array_values($lineas);
}

function cargarDetalleEquipo(PDO $pdo, int $equipoId): array
{
    $st = $pdo->prepare("
        SELECT ed.trabajadores_id, ed.tarea_rol,
               TRIM(per.personal_nombre || ' ' || per.personal_apellido) AS nombre,
               t.trabajador_rol, t.trabajador_turno
        FROM equipo_detalle ed
        JOIN trabajadores t ON t.trabajadores_id = ed.trabajadores_id
        JOIN personal per ON per.id_personal = t.id_personal
        WHERE ed.equipo_id = :id
        ORDER BY ed.tarea_rol, per.personal_apellido
    ");
    $st->execute([':id' => $equipoId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function etapasDeOrden(PDO $pdo, int $ordenId): array
{
    $st = $pdo->prepare("
        SELECT DISTINCT ed.etapa_id, ed.etapa_nombre, ed.etapa_secuencia, ed.producto_id,
               pr.producto_descripcion
        FROM orden_detalle_produccion od
        JOIN etapa_detalle_produccion ed ON ed.producto_id = od.producto_id
        JOIN etapa_produccion ep ON ep.etapa_id = ed.etapa_id AND ep.producto_id = ed.producto_id
        JOIN productos pr ON pr.producto_id = od.producto_id
        WHERE od.orden_id = :oid
          AND UPPER(TRIM(ep.etapa_estado)) = 'ACTIVA'
        ORDER BY ed.etapa_secuencia, ed.etapa_nombre
    ");
    $st->execute([':oid' => $ordenId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
