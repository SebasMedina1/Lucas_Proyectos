<?php
/**
 * Helpers — costos de producción (MP, MO, CIF).
 */

function bitacoraCosto(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void
{
    try {
        $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ")->execute([
            ':id_usuario' => $idUsuario,
            ':entidad' => 'costos produccion',
            ':id_registro' => $idRegistro,
            ':accion' => strtoupper($accion),
            ':descripcion' => $descripcion,
        ]);
    } catch (Throwable $e) {
        error_log('Bitácora falló: ' . $e->getMessage());
    }
}

function resolverUsuarioCosto(PDO $pdo): int
{
    $usuarioId = (int)($_SESSION['usua_id'] ?? $_SESSION['id_usuario'] ?? 0);
    if ($usuarioId === 0) {
        $q = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1');
        $q->execute([':u' => $_SESSION['username'] ?? '']);
        $usuarioId = (int)$q->fetchColumn();
    }
    return $usuarioId;
}

function ultimoPrecioMp(PDO $pdo, int $mpId): int
{
    $st = $pdo->prepare("
        SELECT odc.oc_precio_compra
        FROM orden_detalle_compra odc
        JOIN orden_de_compra oc ON oc.id_orden_compra = odc.id_orden_compra
        WHERE odc.id_materia_prima = :mp
          AND UPPER(TRIM(oc.orden_estado)) NOT IN ('ANULADA', 'ANULADO')
        ORDER BY oc.orden_fecha DESC, oc.id_orden_compra DESC
        LIMIT 1
    ");
    $st->execute([':mp' => $mpId]);
    $precio = (int)$st->fetchColumn();
    if ($precio > 0) {
        return $precio;
    }
    $st2 = $pdo->prepare("
        SELECT fd.fac_precio
        FROM factura_detalle_compra fd
        JOIN factura_compra fc ON fc.id_factura_compra = fd.id_factura_compra
        WHERE fd.id_materia_prima = :mp
          AND UPPER(TRIM(fc.factura_estado)) NOT IN ('ANULADA', 'ANULADO')
        ORDER BY fc.factura_fecha DESC, fc.id_factura_compra DESC
        LIMIT 1
    ");
    $st2->execute([':mp' => $mpId]);
    return max(0, (int)$st2->fetchColumn());
}

function consumosMpPorOrden(PDO $pdo, int $ordenId): array
{
    $st = $pdo->prepare("
        SELECT
            c.id_materia_prima,
            mp.materia_prima_descripcion,
            SUM(c.cantidad_consumida)::int AS cantidad
        FROM control_produccion cp
        JOIN control_produccion_consumo c ON c.control_id = cp.control_id
        JOIN materia_prima mp ON mp.id_materia_prima = c.id_materia_prima
        WHERE cp.orden_id = :o AND UPPER(TRIM(cp.control_estado)) = 'REGISTRADO'
        GROUP BY c.id_materia_prima, mp.materia_prima_descripcion
        ORDER BY mp.materia_prima_descripcion
    ");
    $st->execute([':o' => $ordenId]);
    $lineas = [];
    foreach ($st->fetchAll() as $row) {
        $mpId = (int)$row['id_materia_prima'];
        $lineas[] = [
            'tipo' => 'MP',
            'id_materia_prima' => $mpId,
            'nombre' => $row['materia_prima_descripcion'],
            'cantidad' => (int)$row['cantidad'],
            'precio' => ultimoPrecioMp($pdo, $mpId),
        ];
    }
    return $lineas;
}

function parseLineasCosto(array $payload): array
{
    $lineas = [];
    foreach (['mp', 'mo', 'cif'] as $grupo) {
        if (empty($payload[$grupo]) || !is_array($payload[$grupo])) {
            continue;
        }
        foreach ($payload[$grupo] as $item) {
            $tipo = strtoupper(trim((string)($item['tipo'] ?? $grupo)));
            if ($tipo === 'MP') {
                $mpId = (int)($item['id_materia_prima'] ?? 0);
                $cant = (int)($item['cantidad'] ?? 0);
                $precio = (int)($item['precio'] ?? 0);
                if ($mpId <= 0 || $cant <= 0 || $precio < 0) {
                    continue;
                }
                $lineas[] = [
                    'tipo' => 'MP',
                    'id_materia_prima' => $mpId,
                    'trabajadores_id' => null,
                    'concepto' => null,
                    'cantidad' => $cant,
                    'precio' => $precio,
                ];
            } elseif ($tipo === 'MO') {
                $tid = (int)($item['trabajadores_id'] ?? 0);
                $cant = (int)($item['cantidad'] ?? 0);
                $precio = (int)($item['precio'] ?? 0);
                if ($tid <= 0 || $cant <= 0 || $precio < 0) {
                    continue;
                }
                $lineas[] = [
                    'tipo' => 'MO',
                    'id_materia_prima' => null,
                    'trabajadores_id' => $tid,
                    'concepto' => trim((string)($item['concepto'] ?? '')) ?: null,
                    'cantidad' => $cant,
                    'precio' => $precio,
                ];
            } elseif ($tipo === 'CIF') {
                $concepto = trim((string)($item['concepto'] ?? ''));
                $cant = (int)($item['cantidad'] ?? 1);
                $precio = (int)($item['precio'] ?? 0);
                if ($concepto === '' || $cant <= 0 || $precio < 0) {
                    continue;
                }
                $lineas[] = [
                    'tipo' => 'CIF',
                    'id_materia_prima' => null,
                    'trabajadores_id' => null,
                    'concepto' => $concepto,
                    'cantidad' => $cant,
                    'precio' => $precio,
                ];
            }
        }
    }
    return $lineas;
}

function calcularTotalLineas(array $lineas): int
{
    $total = 0;
    foreach ($lineas as $ln) {
        $total += (int)$ln['cantidad'] * (int)$ln['precio'];
    }
    return $total;
}

function insertarDetalleCosto(PDO $pdo, int $costoId, array $lineas): void
{
    $ins = $pdo->prepare("
        INSERT INTO costo_detalle_produccion (
            costo_id, id_materia_prima, costo_cantidad, costo_precio,
            costo_tipo, trabajadores_id, costo_concepto
        ) VALUES (
            :cid, :mp, :cant, :precio, :tipo, :tid, :concepto
        )
    ");
    foreach ($lineas as $ln) {
        $ins->execute([
            ':cid' => $costoId,
            ':mp' => $ln['id_materia_prima'],
            ':cant' => $ln['cantidad'],
            ':precio' => $ln['precio'],
            ':tipo' => $ln['tipo'],
            ':tid' => $ln['trabajadores_id'],
            ':concepto' => $ln['concepto'],
        ]);
    }
}

function ordenTieneCostoActivo(PDO $pdo, int $ordenId, int $excluirCostoId = 0): bool
{
    $sql = "
        SELECT COUNT(*) FROM costo_produccion
        WHERE orden_id = :o
          AND UPPER(TRIM(costo_estado)) IN ('PENDIENTE', 'CERRADO')
    ";
    $params = [':o' => $ordenId];
    if ($excluirCostoId > 0) {
        $sql .= ' AND costo_id <> :ex';
        $params[':ex'] = $excluirCostoId;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return (int)$st->fetchColumn() > 0;
}

function detalleCostoParaJson(PDO $pdo, int $costoId): array
{
    $st = $pdo->prepare("
        SELECT
            cd.costo_tipo,
            cd.id_materia_prima,
            cd.trabajadores_id,
            cd.costo_concepto,
            cd.costo_cantidad,
            cd.costo_precio,
            mp.materia_prima_descripcion,
            TRIM(per.personal_nombre || ' ' || per.personal_apellido) AS trabajador_nombre
        FROM costo_detalle_produccion cd
        LEFT JOIN materia_prima mp ON mp.id_materia_prima = cd.id_materia_prima
        LEFT JOIN trabajadores t ON t.trabajadores_id = cd.trabajadores_id
        LEFT JOIN personal per ON per.id_personal = t.id_personal
        WHERE cd.costo_id = :id
        ORDER BY cd.costo_tipo, cd.costo_detalle_id
    ");
    $st->execute([':id' => $costoId]);
    $mp = [];
    $mo = [];
    $cif = [];
    foreach ($st->fetchAll() as $row) {
        $tipo = strtoupper(trim($row['costo_tipo']));
        $item = [
            'tipo' => $tipo,
            'cantidad' => (int)$row['costo_cantidad'],
            'precio' => (int)$row['costo_precio'],
        ];
        if ($tipo === 'MP') {
            $item['id_materia_prima'] = (int)$row['id_materia_prima'];
            $item['nombre'] = $row['materia_prima_descripcion'];
            $mp[] = $item;
        } elseif ($tipo === 'MO') {
            $item['trabajadores_id'] = (int)$row['trabajadores_id'];
            $item['nombre'] = $row['trabajador_nombre'];
            $item['concepto'] = $row['costo_concepto'];
            $mo[] = $item;
        } else {
            $item['concepto'] = $row['costo_concepto'];
            $cif[] = $item;
        }
    }
    return ['mp' => $mp, 'mo' => $mo, 'cif' => $cif];
}
