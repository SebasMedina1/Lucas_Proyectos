<?php
require_once '../../config/database.php';
require_once '../../reporte/pedido_ventas.php'; // Clase para generar PDF

// Verificar si se recibió el parámetro 'ped_id'
if (!isset($_GET['ped_id']) || empty($_GET['ped_id'])) {
    die("No se proporcionó un ID de pedido válido.");
}

$ped_id = intval($_GET['ped_id']);

$pdf = new BasePDF('P', 'mm', 'A4', ['titulo' => 'Pedido de Venta']);
$pdf->AddPage();

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para obtener la información general del pedido
    $queryGeneral = "
        SELECT 
            pv.id_pedido_venta,
            to_char(pv.pedido_fecha, 'YYYY-MM-DD') AS pedido_fecha,
            pv.pedido_estado, 
            c.cliente_nombre || ' ' || c.cliente_apellido AS cliente_nombre,
            c.cliente_ruc,
            us.username AS usuario, 
            suc.descripcion_sucursal AS sucursal
        FROM 
            pedido_venta pv
        JOIN 
            clientes c ON pv.id_cliente = c.id_cliente
        JOIN 
            sucursales suc ON pv.id_sucursal = suc.id_sucursal
        JOIN 
            usuarios us ON pv.id_usuario = us.id_usuario
        WHERE 
            pv.id_pedido_venta = :ped_id
        LIMIT 1
    ";

    $stmtGeneral = $pdo->prepare($queryGeneral);
    $stmtGeneral->bindParam(':ped_id', $ped_id, PDO::PARAM_INT);
    $stmtGeneral->execute();
    $general = $stmtGeneral->fetch(PDO::FETCH_ASSOC);

    if ($general) {
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(40, 8, 'Pedido N°:', 0, 0, 'L');
        $pdf->Cell(80, 8, $general['id_pedido_venta'], 0, 1, 'L');
        $pdf->Cell(40, 8, 'Fecha:', 0, 0, 'L');
        $pdf->Cell(80, 8, $general['pedido_fecha'], 0, 1, 'L');
        $pdf->Cell(40, 8, 'Cliente:', 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['cliente_nombre']), 0, 1, 'L');
        $pdf->Cell(40, 8, 'RUC:', 0, 0, 'L');
        $pdf->Cell(80, 8, $general['cliente_ruc'], 0, 1, 'L');
        $pdf->Cell(40, 8, 'Estado:', 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['pedido_estado']), 0, 1, 'L');
        $pdf->Cell(40, 8, 'Usuario:', 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['usuario']), 0, 1, 'L');
        $pdf->Cell(40, 8, 'Sucursal:', 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['sucursal']), 0, 1, 'L');
        $pdf->Ln(10);
    } else {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'No se encontraron datos generales para este pedido.', 0, 1, 'C');
    }

    // Consulta para obtener los detalles del pedido
    $queryDetails = "
        SELECT 
            p.producto_descri AS producto, 
            d.cantidad_pedido AS cantidad,
            p.producto_precio AS precio_unitario,
            d.pedido_precio_total AS subtotal
        FROM 
            detalle_pedido_venta d
        JOIN 
            productos p ON d.producto_id = p.producto_id
        WHERE 
            d.id_pedido_venta = :ped_id
        ORDER BY 
            p.producto_descri
    ";

    $stmtDetails = $pdo->prepare($queryDetails);
    $stmtDetails->bindParam(':ped_id', $ped_id, PDO::PARAM_INT);
    $stmtDetails->execute();

    if ($stmtDetails->rowCount() > 0) {
        // Encabezado de la tabla
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(200, 220, 255);
        $pdf->Cell(80, 8, convertir('Producto'), 1, 0, 'C', true);
        $pdf->Cell(30, 8, convertir('Cantidad'), 1, 0, 'C', true);
        $pdf->Cell(30, 8, convertir('Precio Unit.'), 1, 0, 'C', true);
        $pdf->Cell(40, 8, convertir('Subtotal'), 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 10);
        $total = 0;
        while ($row = $stmtDetails->fetch(PDO::FETCH_ASSOC)) {
            $pdf->Cell(80, 8, convertir($row['producto']), 1, 0, 'L');
            $pdf->Cell(30, 8, $row['cantidad'], 1, 0, 'C');
            $pdf->Cell(30, 8, number_format($row['precio_unitario'], 0, ',', '.'), 1, 0, 'R');
            $pdf->Cell(40, 8, number_format($row['subtotal'], 0, ',', '.'), 1, 1, 'R');
            $total += $row['subtotal'];
        }

        // Total
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(140, 8, convertir('TOTAL:'), 1, 0, 'R', true);
        $pdf->Cell(40, 8, number_format($total, 0, ',', '.'), 1, 1, 'R', true);
    } else {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, convertir('No se encontraron productos para este pedido.'), 0, 1, 'C');
    }

    $pdf->Output('I', "Pedido_Venta_$ped_id.pdf");
} catch (PDOException $e) {
    echo "Error en la conexión o consulta: " . $e->getMessage();
    exit;
}
?>

