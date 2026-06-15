<?php
require_once realpath(__DIR__ . '/../../config/database.php');

header('Content-Type: application/json; charset=utf-8');

$controlId = isset($_GET['control_id']) ? (int)$_GET['control_id'] : 0;
if ($controlId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de control inválido']);
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
            c.control_id,
            c.control_fecha,
            c.control_estado,
            c.orden_id,
            c.control_observacion,
            p.producto_descri,
            ed.etapa_nombre,
            per.personal_nombre || ' ' || per.personal_apellido AS inspector,
            cd.control_cantidad AS cantidad_procesada
        FROM control_produccion c
        JOIN control_produccion_detalle cd ON cd.control_id = c.control_id
        JOIN productos p ON p.producto_id = c.producto_id
        LEFT JOIN etapa_detalle_produccion ed
            ON ed.etapa_id = c.etapa_id AND ed.producto_id = c.producto_id
        JOIN inspectores i ON i.id_inspectores = c.id_inspectores
        JOIN personal per ON per.id_personal = i.id_personal
        WHERE c.control_id = :id
        LIMIT 1
    ");
    $cab->execute([':id' => $controlId]);
    $cabecera = $cab->fetch();

    if (!$cabecera) {
        echo json_encode(['success' => false, 'error' => 'Control no encontrado']);
        exit;
    }

    $cons = $pdo->prepare("
        SELECT mp.materia_prima_descripcion, cc.cantidad_consumida
        FROM control_produccion_consumo cc
        JOIN materia_prima mp ON mp.id_materia_prima = cc.id_materia_prima
        WHERE cc.control_id = :id
        ORDER BY mp.materia_prima_descripcion
    ");
    $cons->execute([':id' => $controlId]);

    if ($cabecera) {
        $cabecera['cantidad_procesada'] = (int)round((float)$cabecera['cantidad_procesada']);
    }

    $consumos = [];
    foreach ($cons->fetchAll() as $row) {
        $consumos[] = [
            'materia_prima_descripcion' => $row['materia_prima_descripcion'],
            'cantidad_consumida' => (int)round((float)$row['cantidad_consumida']),
        ];
    }

    echo json_encode([
        'success' => true,
        'cabecera' => $cabecera,
        'consumos' => $consumos,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
