<?php
require "../../config/database.php";

if (isset($_GET['nota_id'])) {
    try {
        $nota_id = $_GET['nota_id'];

        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $query = $pdo->prepare("SELECT p.p_descrip AS producto,
                                    ndd.nota_cantidad AS cantidad,
                                    ndd.nota_precio AS precio,
                                    ndd.nota_cantidad  * ndd.nota_precio as subtotal,
                                    nd.nota_cargo as cargo_adicional,
                                    fc.fact_nro as factura, 
                                    m.motivo_descripcion as motivo
                                FROM nota_debito nd
                                JOIN facturas_compra fc ON nd.fact_id = fc.fact_id
                                JOIN motivo_debito m ON nd.motivo_id = m.motivo_id
                                JOIN nota_debito_detalle ndd ON ndd.nota_debito_id = nd.nota_debito_id
                                JOIN producto p ON ndd.cod_producto = p.cod_producto
                                JOIN tipo_iva ti ON ti.iva_id = p.iva_id
                                WHERE nd.nota_debito_id = :nota_id");
        $query->bindParam(':nota_id', $nota_id, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>


