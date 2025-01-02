<?php
require_once '../../config/database.php';
require_once '../../reporte/reporte_proveedor.php'; // Clase para generar PDF

$pdf = new BasePDF();
$pdf->AddPage();

try {
    // Configurar conexión PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para obtener todos los proveedores
    $query = "SELECT * FROM proveedor ORDER BY cod_proveedor ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();

    // Establecer fuente para el contenido
    $pdf->SetFont('Arial', '', 10);

    // Iterar sobre los resultados y añadirlos al PDF
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pdf->Cell(20, 10, convertir($row['cod_proveedor']), 1, 0, 'C');
        $pdf->Cell(50, 10, convertir($row['razon_social']), 1, 0, 'L');
        $pdf->Cell(25, 10, convertir($row['ruc']), 1, 0, 'C');
        $pdf->Cell(70, 10, convertir($row['direccion']), 1, 0, 'L');
        $pdf->Cell(30, 10, convertir($row['telefono']), 1, 1, 'C');
    }

    // Mostrar el PDF en el navegador
    $pdf->Output('I', "Reporte_Proveedores.pdf");
} catch (PDOException $e) {
    echo "Error en la conexión o consulta: " . $e->getMessage();
    exit;
}
?>
