<?php
require_once realpath(__DIR__ . '/../../config/database.php');

header('Content-Type: application/json; charset=utf-8');

$ordenId = isset($_GET['orden_id']) ? (int)$_GET['orden_id'] : 0;
if ($ordenId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de orden inválido']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $cab = $pdo->prepare("
        SELECT op.orden_id, op.orden_prod_estado, op.id_pedido_produccion,
               to_char(op.orden_prod_fecha, 'YYYY-MM-DD') AS fecha_emision
        FROM orden_produccion op
        WHERE op.orden_id = :id
        LIMIT 1
    ");
    $cab->execute([':id' => $ordenId]);
    $orden = $cab->fetch();

    if (!$orden) {
        echo json_encode(['success' => false, 'error' => 'Orden no encontrada']);
        exit;
    }

    $estado = strtoupper(trim((string)$orden['orden_prod_estado']));
    if ($estado === 'ANULADA') {
        echo json_encode(['success' => false, 'error' => 'La orden está anulada.']);
        exit;
    }
    if (!in_array($estado, ['PENDIENTE', 'EN_PROCESO', 'TERMINADA'], true)) {
        echo json_encode([
            'success' => false,
            'error' => "La orden no está disponible para registrar PT. Estado: {$estado}.",
        ]);
        exit;
    }

    $det = $pdo->prepare("
        SELECT
            d.producto_id,
            p.producto_descri,
            d.orden_prod_cantidad,
            COALESCE(ctrl.procesado, 0) AS total_procesado,
            COALESCE(pt.total_terminado, 0) AS total_terminado,
            GREATEST(0, COALESCE(ctrl.procesado, 0) - COALESCE(pt.total_terminado, 0)) AS cantidad_finalizable
        FROM orden_detalle_produccion d
        JOIN productos p ON p.producto_id = d.producto_id
        LEFT JOIN (
            /* Solo cantidad procesada en la última etapa (Empaque / lista para entrega) */
            SELECT c.producto_id, SUM(cd.control_cantidad)::int AS procesado
            FROM control_produccion c
            JOIN control_produccion_detalle cd ON cd.control_id = c.control_id
            JOIN etapa_detalle_produccion ed
                ON ed.etapa_id = c.etapa_id AND ed.producto_id = c.producto_id
            JOIN etapa_produccion ep ON ep.etapa_id = ed.etapa_id
            WHERE c.orden_id = :orden
              AND UPPER(TRIM(c.control_estado)) = 'REGISTRADO'
              AND UPPER(TRIM(ep.etapa_estado)) = 'ACTIVA'
              AND ed.etapa_secuencia = (
                  SELECT MAX(ed2.etapa_secuencia)
                  FROM etapa_detalle_produccion ed2
                  JOIN etapa_produccion ep2 ON ep2.etapa_id = ed2.etapa_id
                  WHERE ed2.producto_id = c.producto_id
                    AND UPPER(TRIM(ep2.etapa_estado)) = 'ACTIVA'
              )
            GROUP BY c.producto_id
        ) ctrl ON ctrl.producto_id = d.producto_id
        LEFT JOIN (
            SELECT ptd.producto_id, SUM(ptd.terminado_cantidad)::int AS total_terminado
            FROM productos_terminados_detalle ptd
            JOIN producto_terminado pt ON pt.terminado_id = ptd.terminado_id
            WHERE pt.orden_id = :orden2
            GROUP BY ptd.producto_id
        ) pt ON pt.producto_id = d.producto_id
        WHERE d.orden_id = :orden3
        ORDER BY p.producto_descri ASC
    ");
    $det->execute([':orden' => $ordenId, ':orden2' => $ordenId, ':orden3' => $ordenId]);
    $productos = [];
    foreach ($det->fetchAll() as $row) {
        $row['total_procesado'] = (int)$row['total_procesado'];
        $row['total_terminado'] = (int)$row['total_terminado'];
        $row['cantidad_finalizable'] = (int)$row['cantidad_finalizable'];
        if ($row['cantidad_finalizable'] > 0) {
            $productos[] = $row;
        }
    }

    if (empty($productos)) {
        echo json_encode([
            'success' => false,
            'error' => 'No hay productos listos para PT. Debe existir control en la etapa final (Empaque) y cantidad aún no registrada como terminada.',
        ]);
        exit;
    }

    $depositos = $pdo->query("
        SELECT deposito_id, deposito_descri
        FROM deposito
        ORDER BY deposito_descri ASC
    ")->fetchAll();

    echo json_encode([
        'success' => true,
        'orden' => $orden,
        'productos' => $productos,
        'depositos' => $depositos,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
