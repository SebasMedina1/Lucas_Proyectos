<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require "../../config/database.php";

    $data = json_decode(file_get_contents('php://input'), true);
    $pedidoId = $data['pedidoId'];

    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Marcar el pedido como "ANULADO"
        $query = $pdo->prepare("UPDATE pedido_compras SET ped_estado = 'ANULADO' WHERE ped_id = :pedidoId");
        $query->bindParam(':pedidoId', $pedidoId, PDO::PARAM_INT);
        $query->execute();

        echo json_encode(['success' => true, 'message' => 'Pedido anulado exitosamente.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al anular el pedido: ' . $e->getMessage()]);
    }
}
?>
