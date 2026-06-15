<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');
require_once __DIR__ . '/costos_helper.php';

if (empty($_SESSION['username']) || !check_permission('COSTOS_PRODUCCION', false)) {
    echo json_encode(['success' => false, 'message' => 'Sin permiso']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$costoId = (int)($input['costo_id'] ?? 0);
if ($costoId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $usuarioId = resolverUsuarioCosto($pdo);
    $pdo->beginTransaction();

    $st = $pdo->prepare('SELECT costo_estado FROM costo_produccion WHERE costo_id = :id FOR UPDATE');
    $st->execute([':id' => $costoId]);
    $est = strtoupper(trim((string)$st->fetchColumn()));
    if ($est === '' || $est === 'ANULADO') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Costeo no encontrado o ya anulado.']);
        exit;
    }

    $pdo->prepare("UPDATE costo_produccion SET costo_estado = 'ANULADO' WHERE costo_id = :id")
        ->execute([':id' => $costoId]);

    bitacoraCosto($pdo, $usuarioId, 'ANULACION', "Anulación costeo #{$costoId}", $costoId);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
