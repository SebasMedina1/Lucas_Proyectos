<?php
require "../../config/database.php";

header('Content-Type: application/json');

if (isset($_GET['pre_id'])) {
    try {
        $pre_id = (int)$_GET['pre_id'];

        if ($pre_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID de presupuesto inválido']);
            exit;
        }

        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $query = $pdo->prepare("
            SELECT 
                mp.materia_prima_descripcion AS producto,
                pdc.detalle_presu_cantidad AS cantidad,
                pdc.detalle_presu_precio_compra AS precio,
                COALESCE(pdc.descuento, 0) AS descuento,
                COALESCE(pdc.detalle_presu_iva, 0) AS iva,
                (pdc.detalle_presu_cantidad * pdc.detalle_presu_precio_compra - COALESCE(pdc.descuento, 0)) AS subtotal
            FROM presupuesto_detalle_compra pdc
            JOIN materia_prima mp ON pdc.id_materia_prima = mp.id_materia_prima
            WHERE pdc.id_presupuesto_compra = :pre_id
            ORDER BY mp.materia_prima_descripcion ASC
        ");
        $query->bindParam(':pre_id', $pre_id, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'detalle' => $result
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false, 
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'error' => 'ID de presupuesto no proporcionado'
    ]);
}
?>


