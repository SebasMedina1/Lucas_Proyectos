<?php
session_start();
require "../../config/database.php";

// Verificar sesión
if (empty($_SESSION['username'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Sesión inválida']);
    exit;
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Obtener datos del POST
$id_materia_prima = isset($_POST['id_materia_prima']) ? (int)$_POST['id_materia_prima'] : 0;
$cantidad_pedido = isset($_POST['cantidad_pedido']) ? (int)$_POST['cantidad_pedido'] : 0;

if ($id_materia_prima <= 0 || $cantidad_pedido <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Parámetros inválidos']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consultar stock actual y máximo
    $query = $pdo->prepare("
        SELECT 
            mp.materia_prima_descripcion,
            COALESCE(smp.cantidad_existente, 0) as stock_actual,
            COALESCE(smp.stock_cantidad_maxima, 0) as stock_maximo
        FROM materia_prima mp
        LEFT JOIN stock_materia_prima smp ON smp.id_materia_prima = mp.id_materia_prima
        WHERE mp.id_materia_prima = :id_materia_prima
        LIMIT 1
    ");
    $query->execute([':id_materia_prima' => $id_materia_prima]);
    $stock_data = $query->fetch(PDO::FETCH_ASSOC);

    if (!$stock_data) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Materia prima no encontrada']);
        exit;
    }

    $stock_actual = (int)$stock_data['stock_actual'];
    $stock_maximo = (int)$stock_data['stock_maximo'];
    $stock_total_calculado = $stock_actual + $cantidad_pedido;
    $cupo_disponible = $stock_maximo > 0 ? ($stock_maximo - $stock_actual) : null;

    // Validar si supera el máximo (solo si hay límite configurado)
    $supera_maximo = false;
    if ($stock_maximo > 0 && $stock_total_calculado > $stock_maximo) {
        $supera_maximo = true;
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'materia_prima' => $stock_data['materia_prima_descripcion'],
        'stock_actual' => $stock_actual,
        'stock_maximo' => $stock_maximo,
        'cantidad_pedido' => $cantidad_pedido,
        'stock_total_calculado' => $stock_total_calculado,
        'cupo_disponible' => $cupo_disponible,
        'supera_maximo' => $supera_maximo,
        'tiene_limite' => $stock_maximo > 0
    ]);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error en la consulta: ' . $e->getMessage()]);
}

