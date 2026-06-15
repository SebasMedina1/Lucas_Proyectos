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
        SELECT p.cod_producto AS codigo, p.p_descrip AS producto, pdc.orden_cantidad as cantidad, ti.porcentaje_tipo_iva AS iva, pdc.orden_precio as precio, pr.razon_social AS proveedor
        FROM orden_detalle_compras pdc
        JOIN producto p ON pdc.cod_producto = p.cod_producto
        JOIN tipo_iva ti ON p.iva_id = ti.iva_id
        JOIN orden_compras oc ON pdc.orden_id = oc.orden_id
        JOIN proveedor pr ON oc.cod_proveedor = pr.cod_proveedor
        WHERE pdc.orden_id = :pedido_id
    ");
    $query->bindParam(':pedido_id', $pedidoId, PDO::PARAM_INT);
    $query->execute();

    $detalles = $query->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($detalles);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>
