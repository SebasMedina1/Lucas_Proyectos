<?php
require "../../config/database.php";

header('Content-Type: application/json; charset=UTF-8');

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
                d.producto_id,
                p.producto_descri AS nombre_producto,
                d.cantidad,
                d.precio_unitario,
                d.iva,
                (d.cantidad * d.precio_unitario) AS subtotal_base,
                CASE 
                    WHEN d.iva > 0 THEN (d.cantidad * d.precio_unitario * (1 + d.iva / 100))
                    ELSE (d.cantidad * d.precio_unitario)
                END AS subtotal,
                COALESCE(ti.iva_descri, 'N/A') AS iva_descri
            FROM detalle_presupuesto_venta d
            JOIN productos p ON p.producto_id = d.producto_id
            LEFT JOIN tipo_iva ti ON p.iva_id = ti.iva_id
            WHERE d.id_presupuesto_venta = :presupuesto_id
            ORDER BY d.producto_id
        ");
        $query->bindParam(':presupuesto_id', $pre_id, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatear los datos para la respuesta
        $detalle = [];
        foreach ($result as $row) {
            $detalle[] = [
                'nombre_producto' => $row['nombre_producto'],
                'cantidad' => (int)$row['cantidad'],
                'precio_unitario' => number_format($row['precio_unitario'], 0, ',', '.'),
                'iva_aplicado' => $row['iva'] > 0 ? $row['iva'] . '%' : 'Exento',
                'subtotal' => number_format($row['subtotal'], 0, ',', '.')
            ];
        }
        
        echo json_encode(['success' => true, 'detalle' => $detalle]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error al consultar el detalle: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'No se proporcionó el ID del presupuesto']);
}
?>

