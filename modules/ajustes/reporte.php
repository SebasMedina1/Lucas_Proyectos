<?php
require_once '../../config/database.php';
require_once '../../reporte/reporte_ajustes.php';

if (!function_exists('convertir')) {
    function convertir($texto) {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto);
    }
}



// Crear instancia de PDF
$pdf = new BasePDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// Capturar el ID del ajuste desde la URL
$ajuste_id = $_GET['ajuste_id'] ?? 1;

// Configurar conexión PDO
$dsn = "pgsql:host=$host;port=$port;dbname=$database;";
$pdo = new PDO($dsn, $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // Consulta los datos del ajuste
    $queryAjuste = "
        SELECT 
            a.ajuste_id,
            a.ajuste_fecha,
            a.ajuste_hora,
            u.username AS usuario,
            a.ajuste_estado,
            a.tipo_ajuste,
            m.motivo_descripcion AS motivo
        FROM ajustes a
        JOIN usuarios u ON a.id_usuario = u.id_usuario
        JOIN motivo_ajuste m ON a.motivo_id = m.motivo_id
        WHERE a.ajuste_id = :ajuste_id
    ";
    $stmtAjuste = $pdo->prepare($queryAjuste);
    $stmtAjuste->execute([':ajuste_id' => $ajuste_id]);
    $rowAjuste = $stmtAjuste->fetch(PDO::FETCH_ASSOC);

    if ($rowAjuste) {
        // Mostrar los datos del ajuste en la parte izquierda
        $pdf->Cell(40, 10, 'ID Ajuste:', 0, 0, 'L');
        $pdf->Cell(50, 10, convertir($rowAjuste['ajuste_id']), 0, 1, 'L');

        $pdf->Cell(40, 10, 'Fecha:', 0, 0, 'L');
        $pdf->Cell(50, 10, convertir($rowAjuste['ajuste_fecha']), 0, 1, 'L');

        $pdf->Cell(40, 10, 'Hora:', 0, 0, 'L');
        $pdf->Cell(50, 10, convertir($rowAjuste['ajuste_hora']), 0, 1, 'L');

        $pdf->Cell(40, 10, 'Usuario:', 0, 0, 'L');
        $pdf->Cell(50, 10, convertir($rowAjuste['usuario']), 0, 1, 'L');

        $pdf->Cell(40, 10, 'Estado:', 0, 0, 'L');
        $pdf->Cell(50, 10, convertir($rowAjuste['ajuste_estado']), 0, 1, 'L');

        $pdf->Cell(40, 10, 'Tipo Ajuste:', 0, 0, 'L');
        $pdf->Cell(50, 10, convertir($rowAjuste['tipo_ajuste']), 0, 1, 'L');

        $pdf->Cell(40, 10, 'Motivo:', 0, 0, 'L');
        $pdf->Cell(50, 10, convertir($rowAjuste['motivo']), 0, 1, 'L');
    }

    // Espacio antes de la tabla de detalles
    $pdf->Ln(10);

    // Posicionar la tabla centrada
    $pdf->SetX(($pdf->GetPageWidth() - 120) / 2); // Ajusta el valor 120 según el ancho de la tabla
    $pdf->SetFont('Arial', 'B', 10);

    // Encabezado de la tabla de detalles
    $pdf->Cell(40, 10, 'Producto', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Cantidad', 1, 0, 'C');
    $pdf->Cell(40, 10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Depósito'), 1, 1, 'C');


    // Consulta los detalles del ajuste
    $queryDetalles = "
        SELECT 
            p.p_descrip AS producto,
            d.ajuste_cantidad AS cantidad,
            dep.descrip AS deposito
        FROM ajuste_detalle d
        JOIN producto p ON d.cod_producto = p.cod_producto
        JOIN deposito dep ON d.cod_deposito = dep.cod_deposito
        WHERE d.ajuste_id = :ajuste_id
    ";
    $stmtDetalles = $pdo->prepare($queryDetalles);
    $stmtDetalles->execute([':ajuste_id' => $ajuste_id]);

    $pdf->SetFont('Arial', '', 10);
    $pdf->SetX(($pdf->GetPageWidth() - 120) / 2);
    while ($rowDetalle = $stmtDetalles->fetch(PDO::FETCH_ASSOC)) {
        $pdf->Cell(40, 10, convertir($rowDetalle['producto']), 1, 0, 'C');
        $pdf->Cell(40, 10, convertir($rowDetalle['cantidad']), 1, 0, 'C');
        $pdf->Cell(40, 10, convertir($rowDetalle['deposito']), 1, 1, 'C');
    }

    // Mostrar el PDF en el navegador
    $pdf->Output('I', 'Reporte_Ajuste_' . $ajuste_id . '.pdf');

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
