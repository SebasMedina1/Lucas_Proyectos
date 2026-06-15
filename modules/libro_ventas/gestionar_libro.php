<?php
session_start();
require '../../config/database.php';

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$accion = trim($_POST['accion'] ?? '');
$idLibro = isset($_POST['id_libro']) ? (int)$_POST['id_libro'] : 0;

if ($accion !== 'cerrar' || $idLibro <= 0) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Verificar que el libro existe y está abierto
    $qLibro = $pdo->prepare("SELECT id_libro, estado FROM libro_ventas_historico WHERE id_libro = :id LIMIT 1");
    $qLibro->execute([':id' => $idLibro]);
    $libro = $qLibro->fetch();
    
    if (!$libro) {
        echo json_encode(['success' => false, 'message' => 'Libro no encontrado']);
        exit;
    }
    
    if ($libro['estado'] === 'CERRADO') {
        echo json_encode(['success' => false, 'message' => 'El libro ya está cerrado']);
        exit;
    }
    
    // Cerrar el libro
    $qCerrar = $pdo->prepare("UPDATE libro_ventas_historico SET estado = 'CERRADO' WHERE id_libro = :id");
    $qCerrar->execute([':id' => $idLibro]);
    
    echo json_encode(['success' => true, 'message' => 'Libro cerrado correctamente']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

