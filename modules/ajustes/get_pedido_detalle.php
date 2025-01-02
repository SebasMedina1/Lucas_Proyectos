<?php
require "../../config/database.php";

$facturaId = $_GET['mat_id'] ?? null;

if (!$facturaId) {
    echo json_encode([]);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $query = $pdo->prepare("
        SELECT m.mat_id as codigo, m.mat_descripcion as materia,dp.deposito_descripcion as deposito FROM materias_primas m 
        join depositos dp on m.deposito_id = dp.deposito_id
        where mat_id= :mat_id

    ");
    $query->bindParam(':mat_id', $facturaId, PDO::PARAM_INT);
    $query->execute();

    $detalles = $query->fetchAll(PDO::FETCH_ASSOC);



    echo json_encode($detalles);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>
