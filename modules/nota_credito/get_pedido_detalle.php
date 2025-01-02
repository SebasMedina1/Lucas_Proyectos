<?php
require "../../config/database.php";

$facturaId = $_GET['fact_id'] ?? null;

if (!$facturaId) {
    echo json_encode([]);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $query = $pdo->prepare("
	    SELECT 
            pro.cod_producto AS codigo, 
            pro.p_descrip AS producto, 
            fdc.fact_cantidad AS cantidad, 
            fdc.fact_precio AS precio, 
            ti.porcentaje_tipo_iva AS iva, 
            p.razon_social AS proveedor
        FROM facturas_detalle_compra fdc
        JOIN producto pro ON fdc.cod_producto = pro.cod_producto
        JOIN tipo_iva ti ON pro.iva_id = ti.iva_id
        JOIN facturas_compra fc ON fdc.fact_id = fc.fact_id
        JOIN proveedor p ON fc.cod_proveedor = p.cod_proveedor
        WHERE fdc.fact_id = :factura_id
    ");
    $query->bindParam(':factura_id', $facturaId, PDO::PARAM_INT);
    $query->execute();

    $detalles = $query->fetchAll(PDO::FETCH_ASSOC);

    // Agregar cálculos de subtotal y mantener los datos editables
    foreach ($detalles as &$detalle) {
        $detalle['subtotal'] = $detalle['cantidad'] * $detalle['precio'];
        $detalle['cantidad_editable'] = "<input type='number' class='form-control cantidad-editable' value='{$detalle['cantidad']}' data-codigo='{$detalle['codigo']}' />";
        $detalle['precio_editable'] = "<input type='number' class='form-control precio-editable' value='{$detalle['precio']}' data-codigo='{$detalle['codigo']}' />";
    }

    echo json_encode($detalles);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>
