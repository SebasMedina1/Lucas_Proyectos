<?php
require "../../config/database.php";

if (isset($_GET['remision_id'])) {
    try {
        $ped_id = $_GET['remision_id'];

        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $query = $pdo->prepare("SELECT mp.mat_descripcion AS materia_prima, odc.remision_cantidad AS cantidad, ti.iva_porcen as iva
                                FROM nota_remision_compra_detalle odc
                                JOIN materias_primas mp ON odc.mat_id = mp.mat_id
								JOIN tipo_iva ti ON ti.iva_id = mp.iva_id
                                WHERE odc.remision_id = :ped_id");
        $query->bindParam(':ped_id', $ped_id, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>


