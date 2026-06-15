<?php
require "../../config/database.php";

if (isset($_GET['id_nota_compra'])) {
    try {
        $nota_id = $_GET['id_nota_compra'];

        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $query = $pdo->prepare("SELECT
                                    mp.materia_prima_descripcion            AS producto,
                                    ndc.nota_compra_cantidad               AS cantidad,
                                    ndc.nota_precio                        AS precio,
                                    ndc.nota_compra_cantidad * ndc.nota_precio AS subtotal,
                                    fc.numero_factura                      AS factura
                                FROM nota_compra nc
                                JOIN factura_compra     fc  ON fc.id_factura_compra = nc.id_factura_compra
                                JOIN nota_detalle_compra ndc ON ndc.id_nota_compra   = nc.id_nota_compra
                                JOIN materia_prima      mp  ON mp.id_materia_prima   = ndc.id_materia_prima
                                LEFT JOIN tipo_iva      ti  ON ti.iva_id             = mp.iva_id
                                WHERE nc.id_nota_compra = :id_nota_compra");
        $query->bindParam(':id_nota_compra', $nota_id, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>


