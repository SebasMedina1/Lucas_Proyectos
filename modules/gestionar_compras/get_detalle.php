<?php
require "../../config/database.php";

if (isset($_GET['fact_id'])) {
    try {
        $ped_id = $_GET['fact_id'];

        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $query = $pdo->prepare("
                    SELECT p.p_descrip AS producto, fdc.fact_cantidad AS cantidad, fdc.fact_precio AS precio, fdc.fact_cantidad * fdc.fact_precio as subtotal, ti.porcentaje_tipo_iva as iva
                                FROM facturas_detalle_compra fdc
                                JOIN producto p ON fdc.cod_producto = p.cod_producto
								JOIN tipo_iva ti ON ti.iva_id = p.iva_id
                                WHERE fdc.fact_id = :fact_id
        ");
        $query->bindParam(':fact_id', $ped_id, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>


