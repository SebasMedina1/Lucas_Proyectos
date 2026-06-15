<?php
require_once '../../config/database.php';
require_once '../../reporte/pedido_ventas.php'; // Clase BasePDF (reutilizable)

// Verificar si se recibió el parámetro 'pre_id'
if (!isset($_GET['pre_id']) || empty($_GET['pre_id'])) {
    die("No se proporcionó un ID de presupuesto válido.");
}

$pre_id = intval($_GET['pre_id']);

$pdf = new BasePDF('P', 'mm', 'A4', ['titulo' => 'Presupuesto de Venta']);
$pdf->AddPage();

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para obtener la información general del presupuesto
    $queryGeneral = "
        SELECT 
            pv.id_presupuesto_venta,
            to_char(pv.fecha_presupuesto, 'YYYY-MM-DD') AS fecha_presupuesto,
            pv.estado, 
            pv.validez,
            pv.observacion,
            c.cliente_nombre || ' ' || c.cliente_apellido AS cliente_nombre,
            c.cliente_ruc,
            us.username AS usuario, 
            suc.descripcion_sucursal AS sucursal,
            COALESCE(pv.monto_total, 0) AS monto_total
        FROM 
            presupuesto_venta pv
        JOIN 
            clientes c ON pv.id_cliente = c.id_cliente
        JOIN 
            sucursales suc ON pv.id_sucursal = suc.id_sucursal
        JOIN 
            usuarios us ON pv.id_usuario = us.id_usuario
        WHERE 
            pv.id_presupuesto_venta = :pre_id
        LIMIT 1
    ";

    $stmtGeneral = $pdo->prepare($queryGeneral);
    $stmtGeneral->bindParam(':pre_id', $pre_id, PDO::PARAM_INT);
    $stmtGeneral->execute();
    $general = $stmtGeneral->fetch(PDO::FETCH_ASSOC);

    if ($general) {
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(40, 8, 'Presupuesto N°:', 0, 0, 'L');
        $pdf->Cell(80, 8, $general['id_presupuesto_venta'], 0, 1, 'L');
        $pdf->Cell(40, 8, 'Fecha:', 0, 0, 'L');
        $pdf->Cell(80, 8, $general['fecha_presupuesto'], 0, 1, 'L');
        $pdf->Cell(40, 8, 'Cliente:', 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['cliente_nombre']), 0, 1, 'L');
        $pdf->Cell(40, 8, 'RUC:', 0, 0, 'L');
        $pdf->Cell(80, 8, $general['cliente_ruc'], 0, 1, 'L');
        $pdf->Cell(40, 8, 'Estado:', 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['estado']), 0, 1, 'L');
        if ($general['validez']) {
            $pdf->Cell(40, 8, 'Validez:', 0, 0, 'L');
            $pdf->Cell(80, 8, $general['validez'] . ' días', 0, 1, 'L');
        }
        if ($general['observacion']) {
            $pdf->Cell(40, 8, 'Observación:', 0, 0, 'L');
            $pdf->Cell(80, 8, convertir($general['observacion']), 0, 1, 'L');
        }
        $pdf->Cell(40, 8, 'Usuario:', 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['usuario']), 0, 1, 'L');
        $pdf->Cell(40, 8, 'Sucursal:', 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['sucursal']), 0, 1, 'L');
        $pdf->Ln(10);
    } else {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'No se encontraron datos generales para este presupuesto.', 0, 1, 'C');
    }

    // Consulta para obtener los detalles del presupuesto
    $queryDetails = "
        SELECT 
            p.producto_descri AS producto, 
            d.cantidad,
            d.precio_unitario,
            d.iva,
            (d.cantidad * d.precio_unitario) AS subtotal_base,
            CASE 
                WHEN d.iva > 0 THEN (d.cantidad * d.precio_unitario * (1 + d.iva / 100))
                ELSE (d.cantidad * d.precio_unitario)
            END AS subtotal
        FROM 
            detalle_presupuesto_venta d
        JOIN 
            productos p ON d.producto_id = p.producto_id
        WHERE 
            d.id_presupuesto_venta = :pre_id
        ORDER BY 
            p.producto_descri
    ";

    $stmtDetails = $pdo->prepare($queryDetails);
    $stmtDetails->bindParam(':pre_id', $pre_id, PDO::PARAM_INT);
    $stmtDetails->execute();

    if ($stmtDetails->rowCount() > 0) {
        // Encabezado de la tabla
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(200, 220, 255);
        $pdf->Cell(60, 8, convertir('Producto'), 1, 0, 'C', true);
        $pdf->Cell(25, 8, convertir('Cantidad'), 1, 0, 'C', true);
        $pdf->Cell(30, 8, convertir('Precio Unit.'), 1, 0, 'C', true);
        $pdf->Cell(20, 8, convertir('IVA %'), 1, 0, 'C', true);
        $pdf->Cell(35, 8, convertir('Subtotal'), 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 10);
        $total = 0;
        $subtotalSinIva = 0;
        $totalIva = 0;
        while ($row = $stmtDetails->fetch(PDO::FETCH_ASSOC)) {
            $pdf->Cell(60, 8, convertir($row['producto']), 1, 0, 'L');
            $pdf->Cell(25, 8, $row['cantidad'], 1, 0, 'C');
            $pdf->Cell(30, 8, number_format($row['precio_unitario'], 0, ',', '.'), 1, 0, 'R');
            $pdf->Cell(20, 8, $row['iva'] > 0 ? $row['iva'] . '%' : 'Exento', 1, 0, 'C');
            $pdf->Cell(35, 8, number_format($row['subtotal'], 0, ',', '.'), 1, 1, 'R');
            $total += $row['subtotal'];
            $subtotalSinIva += $row['subtotal_base'];
            $totalIva += ($row['subtotal'] - $row['subtotal_base']);
        }

        // Totales
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(135, 8, convertir('Subtotal (sin IVA):'), 1, 0, 'R', true);
        $pdf->Cell(35, 8, number_format($subtotalSinIva, 0, ',', '.'), 1, 1, 'R', true);
        $pdf->Cell(135, 8, convertir('Total IVA:'), 1, 0, 'R', true);
        $pdf->Cell(35, 8, number_format($totalIva, 0, ',', '.'), 1, 1, 'R', true);
        $pdf->Cell(135, 8, convertir('TOTAL:'), 1, 0, 'R', true);
        $pdf->Cell(35, 8, number_format($total, 0, ',', '.'), 1, 1, 'R', true);
    } else {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, convertir('No se encontraron productos para este presupuesto.'), 0, 1, 'C');
    }

    $pdf->Output('I', "Presupuesto_Venta_$pre_id.pdf");
} catch (PDOException $e) {
    echo "Error en la conexión o consulta: " . $e->getMessage();
    exit;
}
?>

