<?php
require "../../config/database.php";

if (isset($_GET['ajuste_id'])) {
    try {
        $ped_id = $_GET['ajuste_id'];

        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $query = $pdo->prepare("SELECT 
                                    mp.materia_prima_descripcion AS producto, 
                                    ad.ajuste_cantidad AS cantidad
                                FROM 
                                    ajustes_detalle ad
                                JOIN 
                                    materia_prima mp ON ad.id_materia_prima = mp.id_materia_prima
                                WHERE ad.id_ajuste = :ped_id");
        $query->bindParam(':ped_id', $ped_id, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>


