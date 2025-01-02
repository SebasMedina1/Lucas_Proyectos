<?php
require "../../config/database.php";

if (isset($_GET['ped_id'])) {
    try {
        $ped_id = $_GET['ped_id'];

        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $query = $pdo->prepare("SELECT dp.cantidad, p.p_descrip AS nombre_producto
                                FROM detalle_pedidos dp
                                JOIN producto p ON dp.cod_producto = p.cod_producto
                                WHERE dp.pedido_id = :pedido_id");
        $query->bindParam(':pedido_id', $ped_id, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
