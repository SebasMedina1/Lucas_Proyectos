<?php
require "../../config/database.php";

header('Content-Type: application/json');

if (!isset($_GET['deposito_id']) || empty($_GET['deposito_id'])) {
  echo json_encode(['error' => 'Depósito no especificado']);
  exit;
}

$depositoId = (int)$_GET['deposito_id'];

try {
  $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  $stmt = $pdo->prepare("
    SELECT 
      smp.id_stock,
      smp.id_materia_prima,
      mp.materia_prima_descripcion,
      COALESCE(smp.cantidad_existente, 0) AS stock_actual,
      d.deposito_descri
    FROM stock_materia_prima smp
    INNER JOIN materia_prima mp ON mp.id_materia_prima = smp.id_materia_prima
    INNER JOIN deposito d ON d.deposito_id = smp.deposito_id
    WHERE smp.deposito_id = :deposito_id
      AND mp.materia_prima_estado = 'ACTIVO'
    ORDER BY mp.materia_prima_descripcion ASC
  ");
  
  $stmt->execute([':deposito_id' => $depositoId]);
  $productos = $stmt->fetchAll();
  
  echo json_encode(['success' => true, 'productos' => $productos]);
} catch (PDOException $e) {
  echo json_encode(['error' => 'Error al obtener productos: ' . $e->getMessage()]);
}
?>

