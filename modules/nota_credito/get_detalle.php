<?php
require "../../config/database.php";

if (isset($_GET['nota_id'])) {
    try {
        $nota_id = $_GET['nota_id'];

        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $query = $pdo->prepare("SELECT p.p_descrip AS producto,
                                    ndc.nota_cantidad AS cantidad,
                                    ndc.nota_precio AS precio,
                                    ndc.nota_cantidad  * ndc.nota_precio as subtotal,
                                    fc.fact_nro as factura, 
                                    m.motivo_descripcion as motivo
                                FROM notas_compra nc
                                JOIN facturas_compra fc ON nc.fact_id = fc.fact_id
                                JOIN motivo m ON nc.motivo_id = m.motivo_id
                                JOIN notas_compra_detalle ndc ON ndc.nota_id = nc.nota_id
                                JOIN producto p ON ndc.cod_producto = p.cod_producto
                                JOIN tipo_iva ti ON ti.iva_id = p.iva_id
                                WHERE nc.nota_id = :nota_id");
        $query->bindParam(':nota_id', $nota_id, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>


