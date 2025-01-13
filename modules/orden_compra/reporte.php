<?php
require_once '../../config/database.php';
require_once '../../reporte/orden_compras.php'; // Clase para generar el PDF

// Verificar si se recibió el parámetro 'orden_id'
if (!isset($_GET['orden_id']) || empty($_GET['orden_id'])) {
    die("No se proporcionó un ID de orden válido.");
}

$orden_id = intval($_GET['orden_id']); // Convertir a entero

$pdf = new BasePDF();
$pdf->AddPage();


try {
    // Configurar conexión PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consultar datos de la orden de compra
    $queryOrden = "
        SELECT 
            oc.orden_fecha,
            oc.orden_hora,
            oc.orden_estado,
            oc.orden_total,

            p.razon_social AS proveedor,
            u.username AS usuario,
            oc.orden_id
        FROM 
            orden_compras oc
        JOIN 
            proveedor p ON oc.cod_proveedor = p.cod_proveedor
        JOIN 
            usuarios u ON oc.id_usuario = u.id_usuario
        WHERE 
            oc.orden_id = :orden_id
    ";

    $stmtOrden = $pdo->prepare($queryOrden);
    $stmtOrden->bindParam(':orden_id', $orden_id, PDO::PARAM_INT);
    $stmtOrden->execute();

    $orden = $stmtOrden->fetch(PDO::FETCH_ASSOC);

    if (!$orden) {
        die("No se encontró la orden con el ID proporcionado.");
    }

    // Agregar datos generales a la izquierda
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(30, 8, 'Fecha:', 0, 0, 'L');
    $pdf->Cell(50, 8, $orden['orden_fecha'], 0, 1, 'L');
    $pdf->Cell(30, 8, 'Hora:', 0, 0, 'L');
    $pdf->Cell(50, 8, $orden['orden_hora'], 0, 1, 'L');
    $pdf->Cell(30, 8, 'Usuario:', 0, 0, 'L');
    $pdf->Cell(50, 8, $orden['usuario'], 0, 1, 'L');
    $pdf->Cell(30, 8, 'Nro Orden:', 0, 0, 'L');
    $pdf->Cell(50, 8, $orden['orden_id'], 0, 1, 'L');
    $pdf->Cell(30, 8, 'Estado:', 0, 0, 'L');
    $pdf->Cell(50, 8, $orden['orden_estado'], 0, 1, 'L');
    $pdf->Ln(10);

    // Encabezados para el detalle
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(200, 220, 255);
    $pdf->Cell(70, 8, 'Producto', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'IVA', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Cantidad', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'Precio Unitario', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'Subtotal', 1, 1, 'C', true);

    // Consultar datos del detalle de la orden
    $queryDetalle = "
        SELECT 
            p.p_descrip AS producto,
            ti.porcentaje_tipo_iva AS iva,
            od.orden_cantidad,
            od.orden_precio,
            (od.orden_cantidad * od.orden_precio) AS subtotal
        FROM 
            orden_detalle_compras od
        JOIN 
            producto p ON od.cod_producto = p.cod_producto
        JOIN
            tipo_iva ti ON p.iva_id = ti.iva_id
        WHERE 
            od.orden_id = :orden_id
    ";

    $stmtDetalle = $pdo->prepare($queryDetalle);
    $stmtDetalle->bindParam(':orden_id', $orden_id, PDO::PARAM_INT);
    $stmtDetalle->execute();

    // Agregar datos del detalle al PDF
    $pdf->SetFont('Arial', '', 10);
    $totalGeneral = 0;
    while ($detalle = $stmtDetalle->fetch(PDO::FETCH_ASSOC)) {
        $pdf->Cell(70, 8, $detalle['producto'], 1, 0, 'C');
        $pdf->Cell(20, 8, $detalle['iva'].'%', 1, 0, 'C');
        $pdf->Cell(20, 8, $detalle['orden_cantidad'], 1, 0, 'C');
        $pdf->Cell(40, 8, number_format($detalle['orden_precio'], 0, ',', '.'), 1, 0, 'C');
        $pdf->Cell(40, 8, number_format($detalle['subtotal'], 0, ',', '.'), 1, 1, 'C');
        
    }

    // Total general
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(140, 8, 'Total:', 0, 0, 'R');
    $pdf->Cell(40, 8, $orden['orden_total'] .' Gs', 1, 1, 'C');

    // Mostrar el PDF en el navegador
    $pdf->Output('I', "Detalle_Orden_$orden_id.pdf");
} catch (PDOException $e) {
    echo "Error en la conexión o consulta: " . $e->getMessage();
    exit;
}
?>
