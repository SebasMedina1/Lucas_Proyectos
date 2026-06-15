<?php
require_once realpath(__DIR__ . '/../../config/database.php');
require_once __DIR__ . '/costos_helper.php';

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
               STRING_AGG(DISTINCT p.producto_descri, ', ' ORDER BY p.producto_descri) AS productos
        FROM orden_produccion op
        JOIN orden_detalle_produccion d ON d.orden_id = op.orden_id
        JOIN productos p ON p.producto_id = d.producto_id
        WHERE op.orden_id = :id
        GROUP BY op.orden_id, op.orden_prod_estado, op.id_pedido_produccion
    ");
    $cab->execute([':id' => $ordenId]);
    $orden = $cab->fetch();
    if (!$orden) {
        echo json_encode(['success' => false, 'error' => 'Orden no encontrada']);
        exit;
    }

    $stCtrl = $pdo->prepare("
        SELECT COUNT(*) FROM control_produccion
        WHERE orden_id = :o AND UPPER(TRIM(control_estado)) = 'REGISTRADO'
    ");
    $stCtrl->execute([':o' => $ordenId]);
    if ((int)$stCtrl->fetchColumn() === 0) {
        echo json_encode(['success' => false, 'error' => 'La OP no tiene controles de producción registrados.']);
        exit;
    }

    if (ordenTieneCostoActivo($pdo, $ordenId)) {
        echo json_encode(['success' => false, 'error' => 'Esta OP ya tiene un costeo activo (PENDIENTE o CERRADO).']);
        exit;
    }

    $mp = consumosMpPorOrden($pdo, $ordenId);
    if (empty($mp)) {
        echo json_encode(['success' => false, 'error' => 'No hay consumos de MP registrados en la OP.']);
        exit;
    }

    $trab = $pdo->query("
        SELECT t.trabajadores_id,
               TRIM(per.personal_nombre || ' ' || per.personal_apellido) AS nombre,
               COALESCE(t.trabajador_costo_hora, 0)::int AS costo_hora,
               t.trabajador_rol
        FROM trabajadores t
        JOIN personal per ON per.id_personal = t.id_personal
        WHERE UPPER(TRIM(t.trabajador_estado)) = 'ACTIVO'
        ORDER BY per.personal_nombre, per.personal_apellido
    ")->fetchAll();

    echo json_encode([
        'success' => true,
        'orden' => $orden,
        'lineas' => ['mp' => $mp, 'mo' => [], 'cif' => []],
        'trabajadores' => $trab,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
