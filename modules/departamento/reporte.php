<?php
require_once '../../config/database.php';
require_once '../../reporte/reporte_departamento.php';

$pdf = new BasePDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// Consulta los datos
$query = "SELECT * FROM departamento";

$statement = $pdo->query($query);

while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
    $pdf->SetX(60); // Ajusta este valor según el ancho de tu página y tabla
    $pdf->Cell(50, 10, $row['id_departamento'], 1, 0, 'C');
    $pdf->Cell(50, 10, convertir($row['dep_descripcion']), 1, 1, 'C');

}

// Mostrar el PDF en el navegador
$pdf->Output('I', 'Reporte_Departamento.pdf');
