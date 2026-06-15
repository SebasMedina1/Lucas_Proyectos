<?php
require_once realpath(__DIR__ . '/../../config/database.php');

header('Content-Type: application/json; charset=utf-8');

$terminadoId = isset($_GET['terminado_id']) ? (int)$_GET['terminado_id'] : 0;
if ($terminadoId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
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
            u.username,
            op.orden_prod_estado,
            (SELECT COUNT(*) FROM control_calidad_produccion cc WHERE cc.terminado_id = pt.terminado_id) AS tiene_calidad
        FROM producto_terminado pt
        JOIN usuarios u ON u.id_usuario = pt.id_usuario
        JOIN orden_produccion op ON op.orden_id = pt.orden_id
        WHERE pt.terminado_id = :id
        LIMIT 1
    ");
    $cab->execute([':id' => $terminadoId]);
    $cabecera = $cab->fetch();

    if (!$cabecera) {
        echo json_encode(['success' => false, 'error' => 'Registro no encontrado']);
        exit;
    }

    $det = $pdo->prepare("
        SELECT
            ptd.producto_id,
            p.producto_descri,
            ptd.terminado_cantidad,
            d.deposito_descri,
            to_char(ptd.terminado_fecha_elab, 'YYYY-MM-DD') AS fecha_elab,
            to_char(ptd.terminado_fecha_venc, 'YYYY-MM-DD') AS fecha_venc
        FROM productos_terminados_detalle ptd
        JOIN productos p ON p.producto_id = ptd.producto_id
        LEFT JOIN deposito d ON d.deposito_id = ptd.deposito_id
        WHERE ptd.terminado_id = :id
        ORDER BY p.producto_descri
    ");
    $det->execute([':id' => $terminadoId]);
    $lineas = [];
    foreach ($det->fetchAll() as $row) {
        $lineas[] = [
            'producto_id' => (int)$row['producto_id'],
            'producto_descri' => $row['producto_descri'],
            'terminado_cantidad' => (int)$row['terminado_cantidad'],
            'deposito_descri' => $row['deposito_descri'] ?? '-',
            'fecha_elab' => $row['fecha_elab'] ?? '-',
            'fecha_venc' => $row['fecha_venc'] ?? '-',
        ];
    }

    echo json_encode([
        'success' => true,
        'cabecera' => $cabecera,
        'detalle' => $lineas,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
