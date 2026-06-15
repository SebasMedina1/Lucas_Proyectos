<?php
require_once realpath(__DIR__ . '/../../config/database.php');

header('Content-Type: application/json; charset=utf-8');

$calidadId = isset($_GET['calidad_id']) ? (int)$_GET['calidad_id'] : 0;
if ($calidadId <= 0) {
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
            cc.calidad_id,
            cc.calidad_fecha,
            cc.calidad_estado,
            cc.terminado_id,
            pt.orden_id,
            u.username,
            TRIM(per.personal_nombre || ' ' || per.personal_apellido) AS inspector,
            (SELECT COUNT(*) FROM perdidas pe WHERE pe.calidad_id = cc.calidad_id) AS tiene_perdidas
        FROM control_calidad_produccion cc
        JOIN producto_terminado pt ON pt.terminado_id = cc.terminado_id
        JOIN usuarios u ON u.id_usuario = cc.id_usuario
        JOIN inspectores i ON i.id_inspectores = cc.id_inspectores
        JOIN personal per ON per.id_personal = i.id_personal
        WHERE cc.calidad_id = :id
        LIMIT 1
    ");
    $cab->execute([':id' => $calidadId]);
    $cabecera = $cab->fetch();

    if (!$cabecera) {
        echo json_encode(['success' => false, 'error' => 'Control no encontrado']);
        exit;
    }

    $det = $pdo->prepare("
        SELECT
            cd.producto_id,
            p.producto_descri,
            cd.calidad_cantidad,
            cd.calidad_estado,
            pc.parametro_descri,
            cd.valor_medido,
            cd.cumple_parametro
        FROM control_calidad_detalle cd
        JOIN productos p ON p.producto_id = cd.producto_id
        JOIN parametros_control pc ON pc.parametro_id = cd.parametro_id
        WHERE cd.calidad_id = :id
        ORDER BY p.producto_descri, pc.parametro_id
    ");
    $det->execute([':id' => $calidadId]);
    $lineas = [];
    foreach ($det->fetchAll() as $row) {
        $lineas[] = [
            'producto_id' => (int)$row['producto_id'],
            'producto_descri' => $row['producto_descri'],
            'calidad_cantidad' => (int)$row['calidad_cantidad'],
            'calidad_estado' => $row['calidad_estado'],
            'parametro_descri' => $row['parametro_descri'],
            'valor_medido' => $row['valor_medido'],
            'cumple_parametro' => (bool)$row['cumple_parametro'],
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
