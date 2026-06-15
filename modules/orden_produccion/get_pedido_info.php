<?php
require_once realpath(__DIR__ . '/../../config/database.php');

header('Content-Type: application/json; charset=utf-8');

$pedidoId = isset($_GET['ped_id']) ? (int)$_GET['ped_id'] : 0;
if ($pedidoId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de pedido inválido']);
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
            pp.id_pedido_produccion,
            to_char(pp.pedido_prod_fecha_emision, 'YYYY-MM-DD') AS fecha_emision,
            pp.pedido_prod_estado,
            tp.tipo_pedido_descri,
            u.username,
            s.descripcion_sucursal,
            pp.pedido_prod_observaciones
        FROM pedido_produccion pp
        JOIN tipo_pedido tp ON tp.id_tipo_pedido = pp.id_tipo_pedido
        JOIN usuarios u ON u.id_usuario = pp.id_usuario
        JOIN sucursales s ON s.id_sucursal = pp.id_sucursal
        WHERE pp.id_pedido_produccion = :id
        LIMIT 1
    ");
    $cab->execute([':id' => $pedidoId]);
    $pedido = $cab->fetch();

    if (!$pedido) {
        echo json_encode(['success' => false, 'error' => 'Pedido no encontrado']);
        exit;
    }

    if (strtoupper(trim((string)$pedido['pedido_prod_estado'])) !== 'PENDIENTE') {
        echo json_encode(['success' => false, 'error' => 'El pedido no está en estado PENDIENTE']);
        exit;
    }

    $stOp = $pdo->prepare("
        SELECT orden_id, orden_prod_estado
        FROM orden_produccion
        WHERE id_pedido_produccion = :id
          AND orden_prod_estado <> 'ANULADA'
        LIMIT 1
    ");
    $stOp->execute([':id' => $pedidoId]);
    $orden = $stOp->fetch();
    if ($orden) {
        echo json_encode([
            'success' => false,
            'error' => "El pedido ya tiene la orden de producción #{$orden['orden_id']} ({$orden['orden_prod_estado']}).",
        ]);
        exit;
    }

    $det = $pdo->prepare("
        SELECT
            d.producto_id AS codigo,
            p.producto_descri AS nombre_producto,
            d.cantidad_pedido AS cantidad
        FROM pedido_detalle_produccion d
        JOIN productos p ON p.producto_id = d.producto_id
        WHERE d.id_pedido_produccion = :id
        ORDER BY p.producto_descri ASC
    ");
    $det->execute([':id' => $pedidoId]);
    $detalle = $det->fetchAll();

    if (empty($detalle)) {
        echo json_encode(['success' => false, 'error' => 'El pedido no tiene productos en el detalle']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'pedido' => $pedido,
        'detalle' => $detalle,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
