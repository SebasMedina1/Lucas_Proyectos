<?php
require "../../config/database.php";

if (isset($_GET['ped_id'])) {
    try {
        $ped_id = $_GET['ped_id'];

        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $query = $pdo->prepare("
            SELECT 
                mp.materia_prima_descripcion AS producto,
                odc.oc_cantidad_compra AS cantidad,
                odc.oc_precio_compra AS precio,
                (odc.oc_cantidad_compra * odc.oc_precio_compra) AS subtotal,
                ti.iva_descri AS iva
            FROM 
                orden_detalle_compra odc
            JOIN 
                materia_prima mp ON odc.id_materia_prima = mp.id_materia_prima
            LEFT JOIN 
                tipo_iva ti ON mp.iva_id = ti.iva_id
            WHERE 
                odc.id_orden_compra = :ped_id");
        $query->bindParam(':ped_id', $ped_id, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>


