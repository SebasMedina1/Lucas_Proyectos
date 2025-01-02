<?php
require "../../config/database.php";
header('Content-Type: application/json');

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Leer los datos enviados desde el frontend
    $data = json_decode(file_get_contents('php://input'), true);
    $detalleId = $data['detalleId'];
    $pedidoId = $data['pedidoId'];

    // Eliminar el registro del detalle
    $deleteDetalle = $pdo->prepare("DELETE FROM detalle_pedidos WHERE pedido_id = :pedidoId AND cod_producto = :detalleId");
    $deleteDetalle->bindParam(':pedidoId', $pedidoId, PDO::PARAM_INT);
    $deleteDetalle->bindParam(':detalleId', $detalleId, PDO::PARAM_INT);
    $deleteDetalle->execute();

    // Verificar si quedan registros en el detalle
    $query = $pdo->prepare("SELECT COUNT(*) AS count FROM detalle_pedidos WHERE pedido_id = :pedidoId");
    $query->bindParam(':pedidoId', $pedidoId, PDO::PARAM_INT);
    $query->execute();
    $count = $query->fetch(PDO::FETCH_ASSOC)['count'];

    if ($count == 0) {
        // Cambiar el estado del pedido a ANULADO si no hay más detalles
        $updatePedido = $pdo->prepare("UPDATE pedidos_compras SET estado = 'ANULADO' WHERE pedido_id = :pedidoId");
        $updatePedido->bindParam(':pedidoId', $pedidoId, PDO::PARAM_INT);
        $updatePedido->execute();

        echo json_encode([
            'success' => true,
            'message' => 'El detalle fue eliminado y el pedido fue anulado porque no hay más registros.',
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'El detalle fue eliminado correctamente.',
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en la operación con la base de datos: ' . $e->getMessage(),
    ]);
}
