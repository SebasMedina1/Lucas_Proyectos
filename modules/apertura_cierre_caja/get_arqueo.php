<?php
require "../../config/database.php";

header('Content-Type: application/json; charset=UTF-8');

if (isset($_GET['apertura_id'])) {
    try {
        $apertura_id = (int)$_GET['apertura_id'];

        if ($apertura_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID de apertura inválido']);
            exit;
        }

        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Obtener el último arqueo de la apertura (mismo día)
        // Nota: arqueo_caja.id_apertura referencia a apertura_cierre_caja.id_apertura
        $queryArqueo = $pdo->prepare("
            SELECT 
                id_arqueo,
                efectivo_contado,
                cheques_contados,
                otros_contados,
                diferencia_efectivo,
                observacion,
                fecha_arqueo
            FROM arqueo_caja
            WHERE id_apertura = :apertura_id
              AND fecha_arqueo = CURRENT_DATE
            ORDER BY fecha_arqueo DESC, hora_arqueo DESC
            LIMIT 1
        ");
        $queryArqueo->execute([':apertura_id' => $apertura_id]);
        $arqueo = $queryArqueo->fetch(PDO::FETCH_ASSOC);

        if ($arqueo) {
            echo json_encode([
                'success' => true,
                'existe' => true,
                'arqueo' => [
                    'id_arqueo' => (int)$arqueo['id_arqueo'],
                    'efectivo_contado' => (float)$arqueo['efectivo_contado'],
                    'cheques_contados' => (float)$arqueo['cheques_contados'],
                    'otros_contados' => (float)$arqueo['otros_contados'],
                    'diferencia_efectivo' => (float)$arqueo['diferencia_efectivo'],
                    'observacion' => $arqueo['observacion'] ?? ''
                ]
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'existe' => false
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error al consultar el arqueo: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'No se proporcionó el ID de la apertura']);
}
?>

