<?php
require "../../config/database.php";

if (isset($_GET['ajuste_id'])) {
    try {
        $ped_id = $_GET['ajuste_id'];

        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $query = $pdo->prepare("SELECT 
                                    p.p_descrip AS producto, 
                                    aj.ajuste_cantidad AS cantidad
                                FROM 
                                    ajuste_detalle aj
                                JOIN 
                                    producto p ON aj.cod_producto = p.cod_producto
                                WHERE aj.ajuste_id = :ped_id");
        $query->bindParam(':ped_id', $ped_id, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>


