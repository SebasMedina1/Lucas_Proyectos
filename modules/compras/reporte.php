<?php
require_once '../../config/database.php';
require_once '../../reporte/reporte_compras.php';

// Función para convertir caracteres especiales a ISO-8859-1
//function convertir($texto) {
  //  return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
//}

$pdf = new BasePDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// Consulta los datos
$query = "SELECT 
    c.cod_compra,
    p.razon_social,
    d.descrip AS deposito, -- Usamos 'descrip' para obtener el nombre del depósito
    c.nro_factura,
    c.fecha,
    c.estado,
    c.hora,
    c.total_compra,
    u.name_user
FROM 
    compra c
JOIN 
    proveedor p ON c.cod_proveedor = p.cod_proveedor
JOIN 
    deposito d ON c.cod_deposito = d.cod_deposito
JOIN 
    usuarios u ON c.id_user = u.id_user";

$result = $mysqli->query($query);

while ($row = $result->fetch_assoc()) {
    $pdf->SetX(2);
    $pdf->Cell(10, 10, convertir($row['cod_compra']), 1, 0, 'C');
    $pdf->Cell(35, 10, convertir($row['razon_social']), 1, 0, 'C');
    $pdf->Cell(25, 10, convertir($row['nro_factura']), 1, 0, 'C');
    $pdf->Cell(25, 10, convertir($row['fecha']), 1, 0, 'C');
    $pdf->Cell(20, 10, convertir($row['estado']), 1, 0, 'C');
    $pdf->Cell(27, 10, convertir($row['deposito']), 1, 0, 'C'); // Ahora muestra el nombre del depósito
    $pdf->Cell(17, 10, convertir($row['hora']), 1, 0, 'C');
    $pdf->Cell(17, 10, convertir($row['total_compra']), 1, 0, 'C');
    $pdf->Cell(30, 10, convertir($row['name_user']), 1, 1, 'C');
}

// Mostrar el PDF en el navegador
$pdf->Output('I', 'Reporte_Compras.pdf');
?>
