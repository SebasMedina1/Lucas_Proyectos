<?php
require "../../config/database.php";

$pedidoId = $_GET['pedido_id'] ?? null;

if (!$pedidoId) {
    echo json_encode([]);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $query = $pdo->prepare("
        SELECT 
        p.cod_producto AS codigo,
        p.p_descrip AS descripcion,
        pdc.pre_cantidad AS cantidad,
        ti.porcentaje_tipo_iva AS iva,
        pdc.pre_precio AS precio
        FROM
        presupuesto_detalle_compra pdc
        JOIN producto p ON pdc.cod_producto = p.cod_producto
        JOIN tipo_iva ti ON p.iva_id = ti.iva_id
        WHERE pdc.presupuesto_id = :pedido_id
    ");
    $query->bindParam(':pedido_id', $pedidoId, PDO::PARAM_INT);
    $query->execute();

    $detalles = $query->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($detalles);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>
