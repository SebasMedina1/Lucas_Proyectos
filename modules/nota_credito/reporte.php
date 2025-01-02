<?php
require_once '../../config/database.php';
require_once '../../reporte/reporte_compras.php'; // Clase para generar PDF

// Verificar si se recibió el parámetro 'nota_id'
if (!isset($_GET['nota_id']) || empty($_GET['nota_id'])) {
    die("No se proporcionó un ID de nota válido.");
}

$nota_id = intval($_GET['nota_id']); // Asegurarse de que sea un entero

$pdf = new BasePDF();
$pdf->AddPage();

// Configurar el título del reporte
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Detalle de la Nota de Compra', 0, 1, 'C');
$pdf->Ln(5);

// Configurar los encabezados de la tabla
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(200, 220, 255); // Fondo azul claro para los encabezados
$pdf->Cell(25, 8, 'Fecha', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Nro Nota', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'Proveedor', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Estado', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'Usuario', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'Materia Prima', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Cantidad', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Precio', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'IVA', 1, 1, 'C', true);

try {
    // Configurar conexión PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para obtener los datos de la nota específica
    $query = "
        SELECT 
            nc.nota_fecha, 
            nc.nota_nro,
            nc.nota_estado,
            nc.nota_total,
            p.prov_descripcion AS proveedor,
            u.usua_email AS usuario,
            m.mat_descripcion AS materia_prima,
            ncd.nota_cantidad,
            ncd.nota_precio,
            ncd.nota_iva
        FROM 
            notas_compra nc
        JOIN 
            notas_compra_detalle ncd ON nc.nota_id = ncd.nota_id
        JOIN 
            proveedores p ON nc.prov_id = p.prov_id
        JOIN 
            usuarios u ON nc.usua_id = u.usua_id
        JOIN 
            materias_primas m ON ncd.mat_id = m.mat_id
        WHERE 
            nc.nota_id = :nota_id
        ORDER BY 
            ncd.mat_id;
    ";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':nota_id', $nota_id, PDO::PARAM_INT);
    $stmt->execute();

    // Añadir datos al PDF
    $pdf->SetFont('Arial', '', 10);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pdf->Cell(25, 8, $row['nota_fecha'], 1, 0, 'C');
        $pdf->Cell(20, 8, $row['nota_nro'], 1, 0, 'C');
        $pdf->Cell(40, 8, $row['proveedor'], 1, 0, 'C');
        $pdf->Cell(25, 8, $row['nota_estado'], 1, 0, 'C');
        $pdf->Cell(40, 8, $row['usuario'], 1, 0, 'C');
        $pdf->Cell(40, 8, $row['materia_prima'], 1, 0, 'C');
        $pdf->Cell(20, 8, $row['nota_cantidad'], 1, 0, 'C');
        $pdf->Cell(20, 8, number_format($row['nota_precio'], 2), 1, 0, 'C');
        $pdf->Cell(20, 8, $row['nota_iva'] . '%', 1, 1, 'C');
    }

    // Mostrar el PDF en el navegador
    $pdf->Output('I', "Detalle_Nota_Compra_$nota_id.pdf");
} catch (PDOException $e) {
    echo "Error en la conexión o consulta: " . $e->getMessage();
    exit;
}
?>
