<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');
require_once __DIR__ . '/equipos_helper.php';

if (empty($_SESSION['username']) || !check_permission('EQUIPOS_PRODUCCION', false)) {
    echo json_encode(['success' => false, 'error' => 'Sin permiso']);
    exit;
}

$equipoId = isset($_POST['equipo_id']) ? (int)$_POST['equipo_id'] : 0;
if ($equipoId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    [$usuarioId] = resolverUsuarioEquipo($pdo);
    $pdo->beginTransaction();

    $st = $pdo->prepare('SELECT equipo_estado FROM equipos_produccion WHERE equipo_id = :id FOR UPDATE');
    $st->execute([':id' => $equipoId]);
    $est = strtoupper(trim((string)$st->fetchColumn()));
    if ($est === '' || $est === 'ANULADO') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Equipo no encontrado o ya anulado.']);
        exit;
    }

    $pdo->prepare("UPDATE equipos_produccion SET equipo_estado = 'ANULADO' WHERE equipo_id = :id")
        ->execute([':id' => $equipoId]);

    bitacoraEquipo($pdo, $usuarioId, 'ANULACION', "Equipo #{$equipoId} anulado", $equipoId);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
