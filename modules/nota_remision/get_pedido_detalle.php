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
            mp.mat_id AS codigo, 
            mp.mat_descripcion AS materia_prima, 
            fdc.fact_cantidad AS cantidad, 
            fdc.fact_precio AS precio, 
            ti.iva_porcen AS iva, 
            p.prov_descripcion AS proveedor
        FROM facturas_detalle_compra fdc
        JOIN materias_primas mp ON fdc.mat_id = mp.mat_id
        JOIN tipo_iva ti ON mp.iva_id = ti.iva_id
        JOIN facturas_compra fc ON fdc.fact_id = fc.fact_id
        JOIN proveedores p ON fc.prov_id = p.prov_id
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
