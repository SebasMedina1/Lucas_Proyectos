<?php
require_once realpath(__DIR__ . '/../../config/database.php');
require_once __DIR__ . '/etapas_helper.php';

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
    if (!in_array($estado, ['PENDIENTE', 'EN_PROCESO'], true)) {
        echo json_encode([
            'success' => false,
            'error' => "La orden debe estar PENDIENTE o EN_PROCESO. Estado actual: {$estado}.",
        ]);
        exit;
    }

    $det = $pdo->prepare("
        SELECT d.producto_id, p.producto_descri, d.orden_prod_cantidad, d.cantidad_pendiente
        FROM orden_detalle_produccion d
        JOIN productos p ON p.producto_id = d.producto_id
        WHERE d.orden_id = :id
        ORDER BY p.producto_descri
    ");
    $det->execute([':id' => $ordenId]);

    $productos = [];
    foreach ($det->fetchAll() as $row) {
        $info = resolverSiguienteEtapa($pdo, $ordenId, (int)$row['producto_id']);
        if (!$info['success'] || $info['completado']) {
            continue;
        }
        $sig = $info['siguiente_etapa'];
        $productos[] = [
            'producto_id' => (int)$row['producto_id'],
            'producto_descri' => $row['producto_descri'],
            'orden_prod_cantidad' => (int)$row['orden_prod_cantidad'],
            'cantidad_pendiente' => (int)$row['cantidad_pendiente'],
            'siguiente_etapa_nombre' => $sig['etapa_nombre'] ?? '',
            'cantidad_max_etapa' => (int)$info['cantidad_maxima'],
        ];
    }

    if (empty($productos)) {
        echo json_encode([
            'success' => false,
            'error' => 'No hay productos con etapas pendientes en esta orden. Use Productos terminados si ya empaquetó.',
        ]);
        exit;
    }

    echo json_encode(['success' => true, 'orden' => $orden, 'productos' => $productos]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
