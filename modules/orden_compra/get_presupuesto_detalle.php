<?php
require "../../config/database.php";

$pedidoId = $_GET['pre_id'] ?? null;

if (!$pedidoId) {
    echo json_encode([]);
    exit;
}

// AJAX para poder mostrar los detalles del presupuesto al momento de seleccionar un presupuesto en nueva orden

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $query = $pdo->prepare("
     SELECT 
        mp.id_materia_prima AS codigo,
        mp.materia_prima_descripcion AS descripcion,
        pdc.detalle_presu_cantidad AS cantidad,
        pdc.detalle_presu_precio_compra AS precio,
        COALESCE(pdc.descuento, 0) AS descuento,
        COALESCE(pdc.detalle_presu_iva, 0) AS iva_monto,
        ti.iva_descri AS iva
        FROM
        presupuesto_detalle_compra pdc
        JOIN materia_prima mp ON pdc.id_materia_prima = mp.id_materia_prima
        LEFT JOIN tipo_iva ti ON mp.iva_id = ti.iva_id
        WHERE pdc.id_presupuesto_compra = :pre_id
        ORDER BY mp.materia_prima_descripcion ASC
    ");
    $query->bindParam(':pre_id', $pedidoId, PDO::PARAM_INT);
    $query->execute();

    $detalles = $query->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($detalles);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>
