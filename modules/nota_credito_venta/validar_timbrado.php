<?php
session_start();
require "../../config/database.php";

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$timbrado = trim($_GET['timbrado'] ?? '');

if (empty($timbrado) || !preg_match('/^\d{8}$/', $timbrado)) {
    echo json_encode(['success' => false, 'message' => 'Timbrado inválido']);
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
    $st = $pdo->prepare("
        SELECT ct.id_timbrado, ct.id_caja, t.timbrado_numero AS timbrado, 
               ct.fecha_vencimiento, ct.estado,
               ct.punto_expedicion, ct.numero_inicial, ct.numero_final, ct.numero_actual
        FROM caja_timbrado ct
        JOIN timbrado t ON t.id_timbrado = ct.id_timbrado
        WHERE t.timbrado_numero = :timbrado
          AND ct.estado = 'ACTIVO'
          AND ct.fecha_vencimiento >= CURRENT_DATE
        LIMIT 1
    ");
    $st->execute([':timbrado' => $timbrado]);
    $timbradoData = $st->fetch();
    
    if (!$timbradoData) {
        echo json_encode([
            'success' => false, 
            'message' => 'El timbrado no está vigente o ha vencido. Verifique el timbrado y su fecha de vencimiento.'
        ]);
        exit;
    }
    
    // Verificar si hay números disponibles
    $numeroActual = $timbradoData['numero_actual'] ?? ($timbradoData['numero_inicial'] - 1);
    $proximoNumero = $numeroActual + 1;
    $hayNumeros = $proximoNumero <= $timbradoData['numero_final'];
    
    echo json_encode([
        'success' => true,
        'timbrado' => $timbradoData['timbrado'],
        'fecha_vencimiento' => $timbradoData['fecha_vencimiento'],
        'punto_expedicion' => $timbradoData['punto_expedicion'],
        'hay_numeros' => $hayNumeros,
        'numero_actual' => $numeroActual,
        'numero_final' => $timbradoData['numero_final']
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error al validar timbrado: ' . $e->getMessage()
    ]);
}

