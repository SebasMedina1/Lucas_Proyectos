<?php
require_once realpath(__DIR__ . '/../../config/database.php');

header('Content-Type: application/json; charset=utf-8');

$ordenId = isset($_GET['orden_id']) ? (int)$_GET['orden_id'] : 0;
$productoId = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;

if ($ordenId <= 0 || $productoId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $st = $pdo->prepare("
        SELECT
            c.control_id,
            c.control_fecha,
            c.control_estado,
            cd.control_cantidad,
            COALESCE(ed.etapa_nombre, '-') AS etapa_nombre,
            ed.etapa_secuencia,
            TRIM(per.personal_nombre || ' ' || per.personal_apellido) AS inspector
        FROM control_produccion c
        JOIN control_produccion_detalle cd ON cd.control_id = c.control_id
        LEFT JOIN etapa_detalle_produccion ed
            ON ed.etapa_id = c.etapa_id AND ed.producto_id = c.producto_id
        JOIN inspectores i ON i.id_inspectores = c.id_inspectores
        JOIN personal per ON per.id_personal = i.id_personal
        WHERE c.orden_id = :o AND c.producto_id = :p
        ORDER BY c.control_fecha DESC, c.control_id DESC
    ");
    $st->execute([':o' => $ordenId, ':p' => $productoId]);
    $movimientos = [];
    foreach ($st->fetchAll() as $row) {
        $movimientos[] = [
            'control_id' => (int)$row['control_id'],
            'control_fecha' => substr((string)$row['control_fecha'], 0, 10),
            'control_estado' => $row['control_estado'],
            'control_cantidad' => (int)$row['control_cantidad'],
            'etapa_nombre' => $row['etapa_nombre'],
            'etapa_secuencia' => (int)($row['etapa_secuencia'] ?? 0),
            'inspector' => $row['inspector'],
        ];
    }

    echo json_encode(['success' => true, 'movimientos' => $movimientos]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
