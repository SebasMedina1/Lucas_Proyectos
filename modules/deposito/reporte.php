<?php
require_once '../../config/database.php';
require_once '../../reporte/reporte_deposito.php';

$pdf = new BasePDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// Consulta los datos
$query = "SELECT * FROM deposito";

$statement = $pdo->query($query);

while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
    $pdf->SetX(60); // Ajusta este valor según el ancho de tu página y tabla
    $pdf->Cell(50, 10, $row['cod_deposito'], 1, 0, 'C');
    $pdf->Cell(50, 10, convertir($row['descrip']), 1, 1, 'C');

}

// Mostrar el PDF en el navegador
$pdf->Output('I', 'Reporte_Deposito.pdf');
