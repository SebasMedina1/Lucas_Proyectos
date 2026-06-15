<?php
/**
 * Ruta secuencial de etapas y cantidades procesadas por OP + producto.
 */

function obtenerRutaEtapas(PDO $pdo, int $productoId): array
{
    $st = $pdo->prepare("
        SELECT ed.etapa_id, ed.etapa_nombre, ed.etapa_secuencia
        FROM etapa_detalle_produccion ed
        JOIN etapa_produccion ep ON ep.etapa_id = ed.etapa_id
        WHERE ed.producto_id = :p AND UPPER(TRIM(ep.etapa_estado)) = 'ACTIVA'
        ORDER BY ed.etapa_secuencia ASC, ed.etapa_id ASC
    ");
    $st->execute([':p' => $productoId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function cantidadProcesadaEnEtapa(PDO $pdo, int $ordenId, int $productoId, int $etapaId): int
{
    $st = $pdo->prepare("
        SELECT COALESCE(SUM(cd.control_cantidad), 0)::int
        FROM control_produccion c
        JOIN control_produccion_detalle cd ON cd.control_id = c.control_id
        WHERE c.orden_id = :o AND c.producto_id = :p AND c.etapa_id = :e
          AND UPPER(TRIM(c.control_estado)) = 'REGISTRADO'
    ");
    $st->execute([':o' => $ordenId, ':p' => $productoId, ':e' => $etapaId]);
    return (int)$st->fetchColumn();
}

function esEtapaUltima(array $ruta, int $etapaId): bool
{
    if (empty($ruta)) {
        return false;
    }
    $ultima = end($ruta);
    return (int)$ultima['etapa_id'] === $etapaId;
}

function construirResumenRuta(PDO $pdo, int $ordenId, int $productoId, array $ruta, int $ordenCantidad): array
{
    $resumen = [];
    $prevProcesado = 0;
    foreach ($ruta as $idx => $etapa) {
        $eid = (int)$etapa['etapa_id'];
        $procesado = cantidadProcesadaEnEtapa($pdo, $ordenId, $productoId, $eid);
        if ($idx === 0) {
            $disponible = max(0, $ordenCantidad - $procesado);
        } else {
            $disponible = max(0, $prevProcesado - $procesado);
        }
        $resumen[] = [
            'etapa_id' => $eid,
            'etapa_nombre' => $etapa['etapa_nombre'],
            'etapa_secuencia' => (int)$etapa['etapa_secuencia'],
            'cantidad_procesada' => $procesado,
            'cantidad_disponible' => $disponible,
            'es_ultima' => ($idx === count($ruta) - 1),
        ];
        $prevProcesado = $procesado;
    }
    return $resumen;
}

/**
 * @return array{
 *   success: bool,
 *   error?: string,
 *   ruta?: array,
 *   resumen?: array,
 *   siguiente_etapa?: ?array,
 *   cantidad_maxima?: int,
 *   es_ultima_etapa?: bool,
 *   orden_cantidad?: int,
 *   completado?: bool
 * }
 */
function resolverSiguienteEtapa(PDO $pdo, int $ordenId, int $productoId): array
{
    $stDet = $pdo->prepare("
        SELECT orden_prod_cantidad FROM orden_detalle_produccion
        WHERE orden_id = :o AND producto_id = :p
    ");
    $stDet->execute([':o' => $ordenId, ':p' => $productoId]);
    $ordenCantidad = (int)$stDet->fetchColumn();
    if ($ordenCantidad <= 0) {
        return ['success' => false, 'error' => 'Producto no encontrado en la orden.'];
    }

    $ruta = obtenerRutaEtapas($pdo, $productoId);
    if (empty($ruta)) {
        return ['success' => false, 'error' => 'No hay etapas activas para este producto.'];
    }

    $resumen = construirResumenRuta($pdo, $ordenId, $productoId, $ruta, $ordenCantidad);
    $siguiente = null;
    foreach ($resumen as $row) {
        if ($row['cantidad_disponible'] > 0) {
            $siguiente = $row;
            break;
        }
    }

    $completado = $siguiente === null;
    $cantidadMax = $siguiente ? (int)$siguiente['cantidad_disponible'] : 0;

    return [
        'success' => true,
        'ruta' => $ruta,
        'resumen' => $resumen,
        'siguiente_etapa' => $siguiente,
        'cantidad_maxima' => $cantidadMax,
        'es_ultima_etapa' => $siguiente ? (bool)$siguiente['es_ultima'] : false,
        'orden_cantidad' => $ordenCantidad,
        'completado' => $completado,
    ];
}

function validarEtapaSecuencial(PDO $pdo, int $ordenId, int $productoId, int $etapaId, int $cantidad): array
{
    $info = resolverSiguienteEtapa($pdo, $ordenId, $productoId);
    if (!$info['success']) {
        return ['ok' => false, 'error' => $info['error'] ?? 'Error de ruta.'];
    }
    if ($info['completado']) {
        return ['ok' => false, 'error' => 'Todas las etapas están completas para este producto. Registre productos terminados.'];
    }
    $sig = $info['siguiente_etapa'];
    if ((int)$sig['etapa_id'] !== $etapaId) {
        return [
            'ok' => false,
            'error' => 'Debe registrar primero la etapa: ' . $sig['etapa_nombre'] . '.',
        ];
    }
    if ($cantidad > (int)$info['cantidad_maxima']) {
        return [
            'ok' => false,
            'error' => "Cantidad máxima en esta etapa: {$info['cantidad_maxima']}.",
        ];
    }
    return [
        'ok' => true,
        'es_ultima' => (bool)$info['es_ultima_etapa'],
        'cantidad_maxima' => (int)$info['cantidad_maxima'],
    ];
}

/** Etiqueta de estado consolidado para listado OP + producto */
function etiquetaEstadoLinea(array $info): array
{
    if (!$info['success']) {
        return ['texto' => 'Sin ruta', 'class' => 'badge-secondary'];
    }
    if ($info['completado']) {
        return ['texto' => 'Empaque completo — ir a PT', 'class' => 'badge-success'];
    }
    $sig = $info['siguiente_etapa'];
    return [
        'texto' => 'Pendiente: ' . $sig['etapa_nombre'],
        'class' => 'badge-primary',
    ];
}

function htmlProgresoEtapas(array $resumen): string
{
    $html = '';
    foreach ($resumen as $e) {
        $done = $e['cantidad_disponible'] === 0 && $e['cantidad_procesada'] > 0;
        $partial = $e['cantidad_procesada'] > 0 && $e['cantidad_disponible'] > 0;
        $cls = $done ? 'badge-success' : ($partial ? 'badge-warning' : 'badge-light border text-muted');
        $html .= '<span class="badge ' . $cls . ' mr-1 mb-1" title="' . htmlspecialchars($e['etapa_nombre']) . '">'
            . (int)$e['etapa_secuencia'] . '. ' . htmlspecialchars($e['etapa_nombre'])
            . ' (' . (int)$e['cantidad_procesada'] . ')</span>';
    }
    return $html;
}
