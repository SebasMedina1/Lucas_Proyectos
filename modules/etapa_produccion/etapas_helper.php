<?php
/**
 * Helpers — rutas de etapas de producción por producto.
 */

function bitacoraEtapa(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void
{
    try {
        $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ")->execute([
            ':id_usuario' => $idUsuario,
            ':entidad' => 'etapas produccion',
            ':id_registro' => $idRegistro,
            ':accion' => strtoupper($accion),
            ':descripcion' => $descripcion,
        ]);
    } catch (Throwable $e) {
        error_log('Bitácora falló: ' . $e->getMessage());
    }
}

function resolverUsuarioEtapa(PDO $pdo): int
{
    $usuarioId = (int)($_SESSION['usua_id'] ?? $_SESSION['id_usuario'] ?? 0);
    if ($usuarioId === 0) {
        $q = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1');
        $q->execute([':u' => $_SESSION['username'] ?? '']);
        $usuarioId = (int)$q->fetchColumn();
    }
    return $usuarioId;
}

function etapaEnUso(PDO $pdo, int $etapaId): bool
{
    $st = $pdo->prepare("
        SELECT COUNT(*) FROM control_produccion
        WHERE etapa_id = :id AND UPPER(TRIM(control_estado)) = 'REGISTRADO'
    ");
    $st->execute([':id' => $etapaId]);
    return (int)$st->fetchColumn() > 0;
}

function productoTieneRutaActiva(PDO $pdo, int $productoId): bool
{
    $st = $pdo->prepare("
        SELECT COUNT(*) FROM etapa_produccion
        WHERE producto_id = :p AND UPPER(TRIM(etapa_estado)) = 'ACTIVA'
    ");
    $st->execute([':p' => $productoId]);
    return (int)$st->fetchColumn() > 0;
}

function cargarEtapasProducto(PDO $pdo, int $productoId, bool $soloActivas = true): array
{
    $sql = "
        SELECT
            ep.etapa_id,
            ep.etapa_descri,
            ep.etapa_fecha,
            ep.etapa_estado,
            ep.producto_id,
            ed.etapa_nombre,
            ed.etapa_procedimiento,
            ed.etapa_secuencia,
            ed.etapa_tiempo_estimado,
            ed.etapa_observaciones
        FROM etapa_produccion ep
        JOIN etapa_detalle_produccion ed
          ON ed.etapa_id = ep.etapa_id AND ed.producto_id = ep.producto_id
        WHERE ep.producto_id = :p
    ";
    if ($soloActivas) {
        $sql .= " AND UPPER(TRIM(ep.etapa_estado)) = 'ACTIVA'";
    }
    $sql .= ' ORDER BY ed.etapa_secuencia ASC, ed.etapa_nombre ASC';

    $st = $pdo->prepare($sql);
    $st->execute([':p' => $productoId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function parseEtapasPayload(array $items): array
{
    $lineas = [];
    foreach ($items as $item) {
        $nombre = trim((string)($item['etapa_nombre'] ?? ''));
        $proc = trim((string)($item['etapa_procedimiento'] ?? ''));
        $seq = (int)($item['etapa_secuencia'] ?? 0);
        if ($nombre === '' || $proc === '' || $seq <= 0) {
            continue;
        }
        $lineas[] = [
            'etapa_id' => (int)($item['etapa_id'] ?? 0),
            'etapa_nombre' => $nombre,
            'etapa_procedimiento' => $proc,
            'etapa_secuencia' => $seq,
            'etapa_tiempo_estimado' => isset($item['etapa_tiempo_estimado']) && $item['etapa_tiempo_estimado'] !== ''
                ? (int)$item['etapa_tiempo_estimado'] : null,
            'etapa_observaciones' => trim((string)($item['etapa_observaciones'] ?? '')) ?: null,
            'etapa_descri' => trim((string)($item['etapa_descri'] ?? '')),
        ];
    }
    usort($lineas, static fn($a, $b) => $a['etapa_secuencia'] <=> $b['etapa_secuencia']);
    return $lineas;
}

function validarLineasEtapas(array $lineas): ?string
{
    if (empty($lineas)) {
        return 'Defina al menos una etapa con nombre, procedimiento y secuencia.';
    }
    $secuencias = array_column($lineas, 'etapa_secuencia');
    if (count($secuencias) !== count(array_unique($secuencias))) {
        return 'Las secuencias de etapa deben ser únicas.';
    }
    return null;
}

function generarCodigoEtapa(int $productoId, int $secuencia, string $nombre): string
{
    $pref = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $nombre) ?: 'ET', 0, 3));
    return sprintf('P%d-%02d %s', $productoId, $secuencia, $pref);
}
