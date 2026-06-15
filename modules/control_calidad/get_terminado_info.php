<?php
require_once realpath(__DIR__ . '/../../config/database.php');

header('Content-Type: application/json; charset=utf-8');

$terminadoId = isset($_GET['terminado_id']) ? (int)$_GET['terminado_id'] : 0;
if ($terminadoId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de lote inválido']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $cab = $pdo->prepare("
        SELECT
            pt.terminado_id,
            pt.orden_id,
            to_char(pt.terminado_fecha, 'YYYY-MM-DD') AS terminado_fecha,
            op.orden_prod_estado
        FROM producto_terminado pt
        JOIN orden_produccion op ON op.orden_id = pt.orden_id
        WHERE pt.terminado_id = :id
        LIMIT 1
    ");
    $cab->execute([':id' => $terminadoId]);
    $lote = $cab->fetch();

    if (!$lote) {
        echo json_encode(['success' => false, 'error' => 'Lote de productos terminados no encontrado']);
        exit;
    }

    $stDup = $pdo->prepare("
        SELECT calidad_id, calidad_estado
        FROM control_calidad_produccion
        WHERE terminado_id = :id
          AND UPPER(TRIM(calidad_estado)) NOT IN ('ANULADO')
        LIMIT 1
    ");
    $stDup->execute([':id' => $terminadoId]);
    $existente = $stDup->fetch();
    if ($existente) {
        echo json_encode([
            'success' => false,
            'error' => "El lote ya tiene control de calidad #{$existente['calidad_id']} ({$existente['calidad_estado']}).",
        ]);
        exit;
    }

    $det = $pdo->prepare("
        SELECT
            ptd.producto_id,
            p.producto_descri,
            ptd.terminado_cantidad,
            d.deposito_descri
        FROM productos_terminados_detalle ptd
        JOIN productos p ON p.producto_id = ptd.producto_id
        LEFT JOIN deposito d ON d.deposito_id = ptd.deposito_id
        WHERE ptd.terminado_id = :id
        ORDER BY p.producto_descri
    ");
    $det->execute([':id' => $terminadoId]);
    $productos = $det->fetchAll();

    if (empty($productos)) {
        echo json_encode(['success' => false, 'error' => 'El lote no tiene detalle de productos']);
        exit;
    }

    $parametrosPorProducto = [];
    $stPar = $pdo->prepare("
        SELECT parametro_id, parametro_descri
        FROM parametros_control
        WHERE producto_id = :p
          AND UPPER(TRIM(parametro_estado)) = 'ACTIVO'
        ORDER BY parametro_id
    ");

    foreach ($productos as &$prod) {
        $prod['terminado_cantidad'] = (int)$prod['terminado_cantidad'];
        $pid = (int)$prod['producto_id'];
        $stPar->execute([':p' => $pid]);
        $params = $stPar->fetchAll();
        if (empty($params)) {
            echo json_encode([
                'success' => false,
                'error' => "No hay parámetros de calidad activos para el producto: {$prod['producto_descri']}. Ejecute inserts_basicos_calidad.sql",
            ]);
            exit;
        }
        $parametrosPorProducto[$pid] = $params;
    }
    unset($prod);

    echo json_encode([
        'success' => true,
        'lote' => $lote,
        'productos' => $productos,
        'parametros' => $parametrosPorProducto,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
