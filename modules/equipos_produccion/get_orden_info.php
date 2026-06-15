<?php
require_once realpath(__DIR__ . '/../../config/database.php');
require_once __DIR__ . '/equipos_helper.php';

header('Content-Type: application/json; charset=utf-8');

$ordenId = isset($_GET['orden_id']) ? (int)$_GET['orden_id'] : 0;
if ($ordenId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Orden inválida']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $cab = $pdo->prepare("
        SELECT op.orden_id, op.orden_prod_estado, op.id_pedido_produccion
        FROM orden_produccion op WHERE op.orden_id = :id
    ");
    $cab->execute([':id' => $ordenId]);
    $orden = $cab->fetch();
    if (!$orden) {
        echo json_encode(['success' => false, 'error' => 'OP no encontrada']);
        exit;
    }

    $est = strtoupper(trim((string)$orden['orden_prod_estado']));
    if (!in_array($est, ['PENDIENTE', 'EN_PROCESO'], true)) {
        echo json_encode(['success' => false, 'error' => 'La OP debe estar PENDIENTE o EN_PROCESO.']);
        exit;
    }

    $productos = $pdo->prepare("
        SELECT od.producto_id, pr.producto_descripcion, od.cantidad_pendiente
        FROM orden_detalle_produccion od
        JOIN productos pr ON pr.producto_id = od.producto_id
        WHERE od.orden_id = :id
        ORDER BY pr.producto_descripcion
    ");
    $productos->execute([':id' => $ordenId]);

    $etapas = etapasDeOrden($pdo, $ordenId);

    $trab = $pdo->query("
        SELECT t.trabajadores_id,
               TRIM(per.personal_nombre || ' ' || per.personal_apellido) AS nombre,
               t.trabajador_rol, t.trabajador_turno, t.id_etapa,
               ed.etapa_nombre AS etapa_asignada
        FROM trabajadores t
        JOIN personal per ON per.id_personal = t.id_personal
        LEFT JOIN etapa_detalle_produccion ed ON ed.etapa_id = t.id_etapa
        WHERE UPPER(TRIM(t.trabajador_estado)) = 'ACTIVO'
        ORDER BY per.personal_apellido, per.personal_nombre
    ")->fetchAll();

    echo json_encode([
        'success' => true,
        'orden' => $orden,
        'productos' => $productos->fetchAll(),
        'etapas' => $etapas,
        'trabajadores' => $trab,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
