<?php
require_once realpath(__DIR__ . '/../../config/database.php');

header('Content-Type: application/json; charset=utf-8');

$pedidoId = isset($_GET['id_pedido_mat_prod']) ? (int)$_GET['id_pedido_mat_prod'] : 0;
if ($pedidoId <= 0) {
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
            p.id_pedido_mat_prod,
            p.ped_mat_prod_fecha,
            p.ped_mat_prod_estado,
            p.deposito_id,
            d.deposito_descri,
            u.username,
            s.descripcion_sucursal
        FROM pedido_materia_produccion p
        JOIN deposito d ON d.deposito_id = p.deposito_id
        JOIN usuarios u ON u.id_usuario = p.id_usuario
        JOIN sucursales s ON s.id_sucursal = p.id_sucursal
        WHERE p.id_pedido_mat_prod = :id
    ");
    $cab->execute([':id' => $pedidoId]);
    $cabecera = $cab->fetch();
    if (!$cabecera) {
        echo json_encode(['success' => false, 'error' => 'Pedido no encontrado']);
        exit;
    }

    $det = $pdo->prepare("
        SELECT
            mp.id_materia_prima,
            mp.materia_prima_descripcion,
            pd.ped_mat_prod_cantidad,
            pd.cantidad_repuesta
        FROM pedido_materia_detalle_produccion pd
        JOIN materia_prima mp ON mp.id_materia_prima = pd.id_materia_prima
        WHERE pd.id_pedido_mat_prod = :id
        ORDER BY mp.materia_prima_descripcion
    ");
    $det->execute([':id' => $pedidoId]);

    echo json_encode([
        'success' => true,
        'cabecera' => $cabecera,
        'detalle' => $det->fetchAll(),
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
