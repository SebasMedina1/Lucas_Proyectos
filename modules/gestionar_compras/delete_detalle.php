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
    $presupuestoId = $data['presupuestoId'];

    if (empty($detalleId) || empty($presupuestoId)) {
        throw new Exception("El ID del detalle o del presupuesto está vacío.");
    }

    // Eliminar el registro del detalle del presupuesto
    $deleteDetalle = $pdo->prepare("DELETE FROM presupuesto_detalle_compra WHERE pre_id = :presupuestoId AND mat_id = :detalleId");
    $deleteDetalle->bindParam(':presupuestoId', $presupuestoId, PDO::PARAM_INT);
    $deleteDetalle->bindParam(':detalleId', $detalleId, PDO::PARAM_INT);
    $deleteDetalle->execute();

    // Verificar si quedan registros en el detalle del presupuesto
    $query = $pdo->prepare("SELECT COUNT(*) AS count FROM presupuesto_detalle_compra WHERE pre_id = :presupuestoId");
    $query->bindParam(':presupuestoId', $presupuestoId, PDO::PARAM_INT);
    $query->execute();
    $count = $query->fetch(PDO::FETCH_ASSOC)['count'];

    if ($count == 0) {
        // Cambiar el estado del presupuesto a "ANULADO"
        $updatePresupuesto = $pdo->prepare("UPDATE presupuesto_compra SET pre_estado = 'ANULADO' WHERE pre_id = :presupuestoId");
        $updatePresupuesto->bindParam(':presupuestoId', $presupuestoId, PDO::PARAM_INT);
        $updatePresupuesto->execute();

        // Cambiar el estado del pedido relacionado a "PENDIENTE"
        $getPedidoId = $pdo->prepare("SELECT ped_id FROM presupuesto_compra WHERE pre_id = :presupuestoId");
        $getPedidoId->bindParam(':presupuestoId', $presupuestoId, PDO::PARAM_INT);
        $getPedidoId->execute();
        $pedidoId = $getPedidoId->fetch(PDO::FETCH_ASSOC)['ped_id'];

        if ($pedidoId) {
            $updatePedido = $pdo->prepare("UPDATE pedido_compras SET ped_estado = 'PENDIENTE' WHERE ped_id = :pedidoId");
            $updatePedido->bindParam(':pedidoId', $pedidoId, PDO::PARAM_INT);
            $updatePedido->execute();
        }

        echo json_encode([
            'success' => true,
            'message' => 'El detalle fue eliminado. El presupuesto fue anulado y el pedido fue puesto como "PENDIENTE".',
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
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
    ]);
}
