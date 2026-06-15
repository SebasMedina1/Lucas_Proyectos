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
    $deleteDetalle = $pdo->prepare("DELETE FROM presupuesto_detalle_compra WHERE id_presupuesto_compra = :presupuestoId AND id_materia_prima = :detalleId");
    $deleteDetalle->bindParam(':presupuestoId', $pedidoId, PDO::PARAM_INT);
    $deleteDetalle->bindParam(':detalleId', $detalleId, PDO::PARAM_INT);
    $deleteDetalle->execute();

    // Verificar si quedan registros en el detalle
    $query = $pdo->prepare("SELECT COUNT(*) AS count FROM presupuesto_detalle_compra WHERE id_presupuesto_compra = :presupuestoId");
    $query->bindParam(':presupuestoId', $pedidoId, PDO::PARAM_INT);
    $query->execute();
    $count = $query->fetch(PDO::FETCH_ASSOC)['count'];

    if ($count == 0) {
        // Cambiar el estado del presupuesto a ANULADO si no hay más detalles
        $updatePresupuesto = $pdo->prepare("UPDATE presupuesto_compra SET presu_estado = 'ANULADO' WHERE id_presupuesto_compra = :presupuestoId");
        $updatePresupuesto->bindParam(':presupuestoId', $pedidoId, PDO::PARAM_INT);
        $updatePresupuesto->execute();

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
