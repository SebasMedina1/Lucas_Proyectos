<?php
require_once realpath(__DIR__ . '/../../config/database.php');
require_once __DIR__ . '/costos_helper.php';

header('Content-Type: application/json; charset=utf-8');

$costoId = isset($_GET['costo_id']) ? (int)$_GET['costo_id'] : 0;
if ($costoId <= 0) {
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
        SELECT c.costo_id, c.costo_fecha, c.costo_estado, c.costo_total, c.orden_id,
               u.username, op.orden_prod_estado
        FROM costo_produccion c
        JOIN usuarios u ON u.id_usuario = c.id_usuario
        LEFT JOIN orden_produccion op ON op.orden_id = c.orden_id
        WHERE c.costo_id = :id
    ");
    $cab->execute([':id' => $costoId]);
    $cabecera = $cab->fetch();
    if (!$cabecera) {
        echo json_encode(['success' => false, 'error' => 'Costeo no encontrado']);
        exit;
    }

    $st = $pdo->prepare("
        SELECT
            cd.costo_tipo,
            cd.costo_cantidad,
            cd.costo_precio,
            cd.costo_concepto,
            mp.materia_prima_descripcion,
            TRIM(per.personal_nombre || ' ' || per.personal_apellido) AS trabajador_nombre
        FROM costo_detalle_produccion cd
        LEFT JOIN materia_prima mp ON mp.id_materia_prima = cd.id_materia_prima
        LEFT JOIN trabajadores t ON t.trabajadores_id = cd.trabajadores_id
        LEFT JOIN personal per ON per.id_personal = t.id_personal
        WHERE cd.costo_id = :id
        ORDER BY cd.costo_tipo, cd.costo_detalle_id
    ");
    $st->execute([':id' => $costoId]);

    $detalle = [];
    $subMp = 0;
    $subMo = 0;
    $subCif = 0;
    foreach ($st->fetchAll() as $row) {
        $sub = (int)$row['costo_cantidad'] * (int)$row['costo_precio'];
        $tipo = strtoupper(trim($row['costo_tipo']));
        if ($tipo === 'MP') {
            $subMp += $sub;
        } elseif ($tipo === 'MO') {
            $subMo += $sub;
        } else {
            $subCif += $sub;
        }
        $desc = $row['materia_prima_descripcion'] ?? $row['trabajador_nombre'] ?? $row['costo_concepto'] ?? '-';
        $detalle[] = [
            'tipo' => $tipo,
            'descripcion' => $desc,
            'cantidad' => (int)$row['costo_cantidad'],
            'precio' => (int)$row['costo_precio'],
            'subtotal' => $sub,
        ];
    }

    echo json_encode([
        'success' => true,
        'cabecera' => $cabecera,
        'detalle' => $detalle,
        'resumen' => ['mp' => $subMp, 'mo' => $subMo, 'cif' => $subCif],
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
