<?php
require_once realpath(__DIR__ . '/../../config/database.php');

header('Content-Type: application/json; charset=utf-8');

$productoId = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;
$cantidad = isset($_GET['cantidad']) ? (int)$_GET['cantidad'] : 1;
if ($cantidad <= 0) {
    $cantidad = 1;
}

if ($productoId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Producto inválido']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $st = $pdo->prepare("
        SELECT
            r.id_materia_prima,
            mp.materia_prima_descripcion,
            r.cantidad_por_unidad,
            COALESCE((
                SELECT SUM(smp.cantidad_existente)
                FROM stock_materia_prima smp
                WHERE smp.id_materia_prima = r.id_materia_prima
            ), 0) AS stock_total
        FROM receta_produccion r
        JOIN materia_prima mp ON mp.id_materia_prima = r.id_materia_prima
        WHERE r.producto_id = :pid
          AND UPPER(TRIM(r.receta_estado)) = 'ACTIVO'
          AND UPPER(TRIM(mp.materia_prima_estado)) = 'ACTIVO'
        ORDER BY mp.materia_prima_descripcion
    ");
    $st->execute([':pid' => $productoId]);
    $rows = $st->fetchAll();

    if (empty($rows)) {
        echo json_encode([
            'success' => true,
            'tiene_receta' => false,
            'mensaje' => 'Este producto no tiene receta activa. Agregue los consumos manualmente.',
            'items' => [],
        ]);
        exit;
    }

    $items = [];
    foreach ($rows as $row) {
        $porUnidad = max(1, (int)round((float)$row['cantidad_por_unidad']));
        $sugerida = $porUnidad * $cantidad;
        $items[] = [
            'id_materia_prima' => (int)$row['id_materia_prima'],
            'materia_prima_descripcion' => $row['materia_prima_descripcion'],
            'cantidad_por_unidad' => $porUnidad,
            'cantidad_sugerida' => $sugerida,
            'stock_total' => (int)$row['stock_total'],
        ];
    }

    echo json_encode([
        'success' => true,
        'tiene_receta' => true,
        'cantidad_multiplicador' => $cantidad,
        'items' => $items,
    ]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'receta_produccion') !== false) {
        echo json_encode([
            'success' => false,
            'error' => 'La tabla receta_produccion no existe. Ejecute config/backup/migracion_receta_produccion.sql',
        ]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
