<?php
require_once realpath(__DIR__ . '/../../config/database.php');

header('Content-Type: application/json; charset=utf-8');

$calidadId = isset($_GET['calidad_id']) ? (int)$_GET['calidad_id'] : 0;
if ($calidadId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de control de calidad inválido']);
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
            cc.calidad_id,
            cc.calidad_estado,
            cc.terminado_id,
            pt.orden_id,
            to_char(cc.calidad_fecha, 'YYYY-MM-DD') AS calidad_fecha
        FROM control_calidad_produccion cc
        JOIN producto_terminado pt ON pt.terminado_id = cc.terminado_id
        WHERE cc.calidad_id = :id
        LIMIT 1
    ");
    $cab->execute([':id' => $calidadId]);
    $calidad = $cab->fetch();

    if (!$calidad) {
        echo json_encode(['success' => false, 'error' => 'Control de calidad no encontrado']);
        exit;
    }

    $estado = strtoupper(trim((string)$calidad['calidad_estado']));
    if ($estado === 'ANULADO') {
        echo json_encode(['success' => false, 'error' => 'El control de calidad está anulado.']);
        exit;
    }
    if ($estado !== 'NO CONFORME') {
        echo json_encode([
            'success' => false,
            'error' => 'Solo se registran pérdidas sobre inspecciones NO CONFORME.',
        ]);
        exit;
    }

    $stPer = $pdo->prepare("
        SELECT perdidas_id, perdida_estado
        FROM perdidas
        WHERE calidad_id = :id AND UPPER(TRIM(perdida_estado)) <> 'ANULADO'
        LIMIT 1
    ");
    $stPer->execute([':id' => $calidadId]);
    $perExistente = $stPer->fetch();
    if ($perExistente) {
        echo json_encode([
            'success' => false,
            'error' => "Ya existe pérdida #{$perExistente['perdidas_id']} para este control de calidad.",
        ]);
        exit;
    }

    $det = $pdo->prepare("
        SELECT
            cd.producto_id,
            p.producto_descri,
            MAX(cd.calidad_cantidad)::int AS cantidad_inspeccionada,
            ptd.deposito_id,
            d.deposito_descri
        FROM control_calidad_detalle cd
        JOIN productos p ON p.producto_id = cd.producto_id
        JOIN control_calidad_produccion cc ON cc.calidad_id = cd.calidad_id
        JOIN productos_terminados_detalle ptd
            ON ptd.terminado_id = cc.terminado_id AND ptd.producto_id = cd.producto_id
        LEFT JOIN deposito d ON d.deposito_id = ptd.deposito_id
        WHERE cd.calidad_id = :id
        GROUP BY cd.producto_id, p.producto_descri, ptd.deposito_id, d.deposito_descri
        ORDER BY p.producto_descri
    ");
    $det->execute([':id' => $calidadId]);
    $productos = [];
    foreach ($det->fetchAll() as $row) {
        $cant = (int)$row['cantidad_inspeccionada'];
        if ($cant > 0) {
            $row['cantidad_disponible'] = $cant;
            $productos[] = $row;
        }
    }

    if (empty($productos)) {
        echo json_encode(['success' => false, 'error' => 'No hay productos en el control de calidad.']);
        exit;
    }

    $tipos = $pdo->query("
        SELECT tipo_perdida_id, tipo_perdida_descri
        FROM tipo_perdida
        ORDER BY tipo_perdida_id
    ")->fetchAll();

    echo json_encode([
        'success' => true,
        'calidad' => $calidad,
        'productos' => $productos,
        'tipos_perdida' => $tipos,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
