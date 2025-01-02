<?php
require "../../config/database.php";

if (isset($_GET['pre_id'])) {
    try {
        $pre_id = $_GET['pre_id'];

        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $query = $pdo->prepare("SELECT 
                                p.p_descrip AS producto,
                                pdc.pre_cantidad AS cantidad,
                                pdc.pre_precio AS precio
                                FROM
                                presupuesto_detalle_compra pdc
                                JOIN producto p ON pdc.cod_producto = p.cod_producto
                                WHERE pdc.presupuesto_id = :pre_id");
        $query->bindParam(':pre_id', $pre_id, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>


