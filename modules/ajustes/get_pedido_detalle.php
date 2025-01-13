<?php
require "../../config/database.php";

$producto = $_GET['cod_producto'] ?? null;

if (!$producto) {
    echo json_encode([]);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para obtener el stock existencia
    $query = $pdo->prepare("
        SELECT p.cod_producto, p.p_descrip AS producto, d.descrip AS deposito, 
               COALESCE(s.stock_existencia, 0) AS stock_existencia
        FROM producto p
        JOIN deposito d ON p.cod_deposito = d.cod_deposito
        LEFT JOIN stock s ON p.cod_producto = s.cod_producto AND d.cod_deposito = s.cod_deposito
        WHERE p.cod_producto = :cod_producto
    ");
    $query->bindParam(':cod_producto', $producto, PDO::PARAM_INT);
    $query->execute();

    $detalles = $query->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($detalles);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>
