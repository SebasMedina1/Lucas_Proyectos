<?php
require_once realpath(__DIR__ . '/../../config/database.php');

header('Content-Type: application/json; charset=utf-8');

$reposicionId = isset($_GET['reposicion_id']) ? (int)$_GET['reposicion_id'] : 0;
if ($reposicionId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $cab = $pdo->prepare("
        SELECT
            r.reposicion_id,
            r.reposicion_fecha,
            r.reposicion_estado,
            r.id_pedido_mat_prod,
            r.deposito_id,
            d.deposito_descri,
            u.username
        FROM reposicion_materia r
        JOIN deposito d ON d.deposito_id = r.deposito_id
        JOIN usuarios u ON u.id_usuario = r.id_usuario
        WHERE r.reposicion_id = :id
    ");
    $cab->execute([':id' => $reposicionId]);
    $cabecera = $cab->fetch();
    if (!$cabecera) {
        echo json_encode(['success' => false, 'error' => 'Reposición no encontrada']);
        exit;
    }

    $det = $pdo->prepare("
        SELECT mp.materia_prima_descripcion, rd.reposicion_cantidad
        FROM reposicion_materia_detalle rd
        JOIN materia_prima mp ON mp.id_materia_prima = rd.id_materia_prima
        WHERE rd.reposicion_id = :id
        ORDER BY mp.materia_prima_descripcion
    ");
    $det->execute([':id' => $reposicionId]);

    echo json_encode([
        'success' => true,
        'cabecera' => $cabecera,
        'detalle' => $det->fetchAll(),
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
