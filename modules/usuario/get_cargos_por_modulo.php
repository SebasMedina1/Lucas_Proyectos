<?php
/**
 * Endpoint AJAX para obtener cargos permitidos según el módulo seleccionado
 */

session_start();

if (empty($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$file = realpath("../../config/database.php");
if (!$file || !file_exists($file)) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de configuración']);
    exit();
}

require_once $file;
require_once realpath("../../config/modulo_cargo_map.php");

header('Content-Type: application/json');

$moduloId = isset($_GET['modulo_id']) ? (int)$_GET['modulo_id'] : 0;

if ($moduloId <= 0) {
    echo json_encode(['cargos' => []]);
    exit();
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Obtener IDs de cargos permitidos para el módulo
    $cargosIds = obtenerCargosIdsPorModulo($pdo, $moduloId);
    
    if (empty($cargosIds)) {
        echo json_encode(['cargos' => []]);
        exit();
    }
    
    // Obtener información completa de los cargos permitidos
    $placeholders = [];
    $params = [];
    foreach ($cargosIds as $index => $cargoId) {
        $key = ':cargo' . $index;
        $placeholders[] = $key;
        $params[$key] = $cargoId;
    }
    
    $sql = "
        SELECT id_cargo, cargo_descripcion
        FROM cargos
        WHERE id_cargo IN (" . implode(',', $placeholders) . ")
        AND estado_cargo = 'ACTIVO'
        ORDER BY cargo_descripcion ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cargos = $stmt->fetchAll();
    
    echo json_encode(['cargos' => $cargos]);
    
} catch (PDOException $e) {
    error_log("Error en get_cargos_por_modulo: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener cargos']);
}

