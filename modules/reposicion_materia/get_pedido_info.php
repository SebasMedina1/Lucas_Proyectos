<?php
require_once realpath(__DIR__ . '/../../config/database.php');

header('Content-Type: application/json; charset=utf-8');

$pedidoId = isset($_GET['id_pedido_mat_prod']) ? (int)$_GET['id_pedido_mat_prod'] : 0;
if ($pedidoId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de pedido inválido']);
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
            d.deposito_descri
        FROM pedido_materia_produccion p
        JOIN deposito d ON d.deposito_id = p.deposito_id
        WHERE p.id_pedido_mat_prod = :id
    ");
    $cab->execute([':id' => $pedidoId]);
    $pedido = $cab->fetch();
    if (!$pedido) {
        echo json_encode(['success' => false, 'error' => 'Pedido no encontrado']);
        exit;
    }

    $est = strtoupper(trim((string)$pedido['ped_mat_prod_estado']));
    if (!in_array($est, ['PENDIENTE', 'PARCIAL'], true)) {
        echo json_encode([
            'success' => false,
            'error' => "Pedido en estado {$est}. Solo PENDIENTE o PARCIAL admiten reposición.",
        ]);
        exit;
    }

    $det = $pdo->prepare("
        SELECT
            pd.id_materia_prima,
            mp.materia_prima_descripcion,
            pd.ped_mat_prod_cantidad,
            pd.cantidad_repuesta,
            (pd.ped_mat_prod_cantidad - pd.cantidad_repuesta)::int AS cantidad_pendiente
        FROM pedido_materia_detalle_produccion pd
        JOIN materia_prima mp ON mp.id_materia_prima = pd.id_materia_prima
        WHERE pd.id_pedido_mat_prod = :id
          AND pd.cantidad_repuesta < pd.ped_mat_prod_cantidad
        ORDER BY mp.materia_prima_descripcion
    ");
    $det->execute([':id' => $pedidoId]);
    $lineas = $det->fetchAll();

    if (empty($lineas)) {
        echo json_encode(['success' => false, 'error' => 'El pedido no tiene cantidades pendientes de reponer.']);
        exit;
    }

    echo json_encode(['success' => true, 'pedido' => $pedido, 'lineas' => $lineas]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
