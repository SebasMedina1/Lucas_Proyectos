<?php
require "../../config/database.php";

header('Content-Type: application/json');

if (isset($_GET['ped_id'])) {
    try {
        $ped_id = (int)$_GET['ped_id'];

        if ($ped_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID de pedido inválido']);
            exit;
        }

        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $query = $pdo->prepare("
            SELECT 
                dp.cantidad_pedido AS cantidad, 
                mp.materia_prima_descripcion AS nombre_producto,
                mp.id_materia_prima AS codigo
            FROM pedido_detalle_compra dp
            JOIN materia_prima mp ON dp.id_materia_prima = mp.id_materia_prima
            WHERE dp.id_pedido_compra = :pedido_id
            ORDER BY mp.materia_prima_descripcion ASC
        ");
        $query->bindParam(':pedido_id', $ped_id, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'detalle' => $result
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false, 
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'error' => 'ID de pedido no proporcionado'
    ]);
}
?>
