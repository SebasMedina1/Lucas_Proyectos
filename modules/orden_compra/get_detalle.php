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
                p.p_descrip AS producto,
                odc.orden_cantidad AS cantidad,
                odc.orden_precio AS precio,
                (odc.orden_cantidad * odc.orden_precio) AS subtotal,
                ti.porcentaje_tipo_iva AS iva
            FROM 
                orden_detalle_compras odc
            JOIN 
                producto p ON odc.cod_producto = p.cod_producto
            JOIN 
                tipo_iva ti ON p.iva_id = ti.iva_id
            WHERE 
                odc.orden_id = :ped_id");
        $query->bindParam(':ped_id', $ped_id, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>


