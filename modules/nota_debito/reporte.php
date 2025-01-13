<?php
require_once '../../config/database.php';
require_once '../../reporte/reporte_notaDebito.php'; // Clase para generar PDF

// Verificar si se recibió el parámetro 'nota_id'
if (!isset($_GET['nota_debito_id']) || empty($_GET['nota_debito_id'])) {
    die("No se proporcionó un ID de nota válido.");
}

$nota_id = intval($_GET['nota_debito_id']); // Asegurarse de que sea un entero

$pdf = new BasePDF();
$pdf->AddPage();

// Configurar el título del reporte
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, mb_convert_encoding('Detalle de la Nota Débito', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
$pdf->Ln(5);


try {
    // Configurar conexión PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para obtener los datos principales de la nota específica
    $queryNota = "
        SELECT 
            nd.nota_debito_id, 
            nd.nota_fecha, 
            nd.nota_hora, 
            nd.nota_estado,
            nd.nota_cargo,
            m.motivo_descripcion AS motivo,
            pro.razon_social AS proveedor,
            u.username AS usuario
        FROM 
            nota_debito nd
        JOIN 
            motivo_debito m ON nd.motivo_id = m.motivo_id
        JOIN 
            proveedor pro ON nd.cod_proveedor = pro.cod_proveedor
        JOIN 
            usuarios u ON nd.id_usuario = u.id_usuario
        WHERE 
            nd.nota_debito_id = :nota_debito_id;
    ";

    $stmtNota = $pdo->prepare($queryNota);
    $stmtNota->bindParam(':nota_debito_id', $nota_id, PDO::PARAM_INT);
    $stmtNota->execute();

    // Obtener los datos de la nota y mostrarlos en la parte superior izquierda
    if ($rowNota = $stmtNota->fetch(PDO::FETCH_ASSOC)) {
        $pdf->SetFont('Arial', '', 10);
    
        $pdf->SetX(35);
        $pdf->Cell(30, 8, 'Nota ID:', 0, 0);
        $pdf->Cell(50, 8, $rowNota['nota_debito_id'], 0, 1);
    
        $pdf->SetX(35);
        $pdf->Cell(30, 8, 'Fecha:', 0, 0);
        $pdf->Cell(50, 8, $rowNota['nota_fecha'], 0, 1);
    
        $pdf->SetX(35);
        $pdf->Cell(30, 8, 'Hora:', 0, 0);
        $pdf->Cell(50, 8, $rowNota['nota_hora'], 0, 1);
    
        $pdf->SetX(35);
        $pdf->Cell(30, 8, 'Usuario:', 0, 0);
        $pdf->Cell(50, 8, $rowNota['usuario'], 0, 1);
    
        $pdf->SetX(35);
        $pdf->Cell(30, 8, 'Estado:', 0, 0);
        $pdf->Cell(50, 8, $rowNota['nota_estado'], 0, 1);      
    
        $pdf->SetX(35);
        $pdf->Cell(30, 8, 'Motivo:', 0, 0);
        $pdf->Cell(50, 8, $rowNota['motivo'], 0, 1);

        $pdf->SetX(35);
        $pdf->Cell(30, 8, 'Cargo adicional:', 0, 0);
        $pdf->Cell(50, 8, number_format($rowNota['nota_cargo'], 0, ',', '.') . ' Gs', 0, 1);  
    
        $pdf->SetX(35);
        $pdf->Cell(30, 8, 'Proveedor:', 0, 0);
        $pdf->Cell(50, 8, $rowNota['proveedor'], 0, 1);
    }
    

    $pdf->Ln(10);

    // Configurar los encabezados de la tabla de detalles
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(200, 220, 255); // Fondo azul claro para los encabezados
    $pdf->SetX(55);
    $pdf->Cell(40, 8, 'Producto', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Cantidad', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Precio', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'IVA', 1, 1, 'C', true);

    // Consulta para obtener los detalles de la nota específica
    $queryDetalle = "
        SELECT 
            p.p_descrip AS producto,
            ncd.nota_cantidad,
            ncd.nota_precio,
            ncd.nota_iva
        FROM 
            nota_debito_detalle ncd
        JOIN 
            producto p ON ncd.cod_producto = p.cod_producto
        WHERE 
            ncd.nota_debito_id = :nota_debito_id
        ORDER BY 
            p.p_descrip;
    ";

    $stmtDetalle = $pdo->prepare($queryDetalle);
    $stmtDetalle->bindParam(':nota_debito_id', $nota_id, PDO::PARAM_INT);
    $stmtDetalle->execute();

    // Añadir los detalles al PDF
    $pdf->SetFont('Arial', '', 10);
    while ($rowDetalle = $stmtDetalle->fetch(PDO::FETCH_ASSOC)) {
        $pdf->SetX(55);
        $pdf->Cell(40, 8, $rowDetalle['producto'], 1, 0, 'C');
        $pdf->Cell(20, 8, $rowDetalle['nota_cantidad'], 1, 0, 'C');
        $pdf->Cell(30, 8, number_format($rowDetalle['nota_precio'], 2), 1, 0, 'C');
        $pdf->Cell(30, 8, $rowDetalle['nota_iva'] . '%', 1, 1, 'C');
    }

    // Mostrar el PDF en el navegador
    $pdf->Output('I', "Detalle_Nota_Compra_$nota_id.pdf");
} catch (PDOException $e) {
    echo "Error en la conexión o consulta: " . $e->getMessage();
    exit;
}
?>
