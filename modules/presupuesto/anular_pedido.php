<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require "../../config/database.php";

    $data = json_decode(file_get_contents('php://input'), true);
    $pedidoId = $data['pedidoId'];

    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Marcar el presupuesto como "ANULADO"
        $query = $pdo->prepare("UPDATE presupuesto_compra SET presu_estado = 'ANULADO' WHERE id_presupuesto_compra = :presupuestoId");
        $query->bindParam(':presupuestoId', $pedidoId, PDO::PARAM_INT);
        $query->execute();

        echo json_encode(['success' => true, 'message' => 'Presupuesto anulado exitosamente.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al anular el pedido: ' . $e->getMessage()]);
    }
}
?>
