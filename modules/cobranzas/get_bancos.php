<?php
session_start();
require "../../config/database.php";

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $pdo->exec("SET TIME ZONE 'America/Asuncion'");
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit;
}

try {
    // Obtener bancos activos
    $st = $pdo->query("
        SELECT id_banco, banco_descri
        FROM banco
        ORDER BY banco_descri
    ");
    $bancos = $st->fetchAll();
    
    echo json_encode([
        'success' => true,
        'bancos' => $bancos
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener bancos: ' . $e->getMessage()
    ]);
}
?>

