<?php
require "../../config/database.php";

header('Content-Type: application/json; charset=UTF-8');

if (isset($_GET['ped_id'])) {
    try {
        $ped_id = (int)$_GET['ped_id'];

        if ($ped_id <= 0) {
            echo json_encode(['error' => 'ID de pedido inválido']);
            exit;
        }

        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $query = $pdo->prepare("
            SELECT 
                d.producto_id,
                p.producto_descri AS nombre_producto,
                d.cantidad_pedido AS cantidad,
                p.producto_precio AS precio_unitario,
                d.pedido_precio_total AS subtotal,
                COALESCE(ti.iva_descri, 'N/A') AS iva_descri,
                COALESCE(ti.iva_id, 0) AS iva_id
            FROM detalle_pedido_venta d
            JOIN productos p ON p.producto_id = d.producto_id
            LEFT JOIN tipo_iva ti ON p.iva_id = ti.iva_id
            WHERE d.id_pedido_venta = :pedido_id
            ORDER BY d.producto_id
        ");
        $query->bindParam(':pedido_id', $ped_id, PDO::PARAM_INT);
        $query->execute();

        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatear los datos para el frontend
        $detalle = [];
        foreach ($result as $row) {
            // Extraer porcentaje de IVA de iva_descri (ej: "IVA_10" -> 10)
            $ivaAplicado = 'N/A';
            if ($row['iva_id'] > 0 && $row['iva_descri'] !== 'N/A') {
                if (preg_match('/(\d+)/', $row['iva_descri'], $matches)) {
                    $ivaAplicado = $matches[1] . '%';
                }
            }
            
            $detalle[] = [
                'nombre_producto' => $row['nombre_producto'],
                'cantidad' => number_format($row['cantidad'], 0, ',', '.'),
                'precio_unitario' => number_format($row['precio_unitario'], 0, ',', '.'),
                'subtotal' => number_format($row['subtotal'], 0, ',', '.'),
                'iva_aplicado' => $ivaAplicado
            ];
        }
        
        echo json_encode(['success' => true, 'detalle' => $detalle]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Error al consultar el detalle: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'No se proporcionó el ID del pedido']);
}
?>

