<?php
require_once realpath(__DIR__ . '/../../config/database.php');

header('Content-Type: application/json; charset=utf-8');

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->query("
        SELECT
            mp.id_materia_prima,
            mp.materia_prima_descripcion,
            COALESCE(SUM(smp.cantidad_existente), 0) AS stock_total
        FROM materia_prima mp
        LEFT JOIN stock_materia_prima smp ON smp.id_materia_prima = mp.id_materia_prima
        WHERE UPPER(TRIM(mp.materia_prima_estado)) = 'ACTIVO'
        GROUP BY mp.id_materia_prima, mp.materia_prima_descripcion
        HAVING COALESCE(SUM(smp.cantidad_existente), 0) > 0
        ORDER BY mp.materia_prima_descripcion
    ");

    echo json_encode(['success' => true, 'materias' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
