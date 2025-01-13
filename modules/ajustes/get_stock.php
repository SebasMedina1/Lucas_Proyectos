<?php
require "../../config/database.php";

// Obtener los parámetros de la URL
$productoId = isset($_GET['cod_producto']) ? $_GET['cod_producto'] : null;
$depositoId = isset($_GET['cod_deposito']) ? $_GET['cod_deposito'] : null;

if (!$productoId || !$depositoId) {
    echo json_encode(['error' => 'Faltan parámetros']);
    exit;
}

try {
    // Conexión a la base de datos
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para obtener el stock existencia
    $query = $pdo->prepare(
        "SELECT COALESCE(stock_existencia, 0) AS stock_existencia
        FROM stock
        WHERE cod_producto = :cod_producto AND cod_deposito = :cod_deposito"
    );
    $query->bindParam(':cod_producto', $productoId, PDO::PARAM_INT);
    $query->bindParam(':cod_deposito', $depositoId, PDO::PARAM_INT);
    $query->execute();

    $result = $query->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode(['stock_existencia' => $result['stock_existencia']]);
    } else {
        echo json_encode(['stock_existencia' => 0]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Error en la consulta: ' . $e->getMessage()]);
}
?>
