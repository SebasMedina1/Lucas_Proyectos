<?php
require_once '../../config/database.php';
require_once '../../reporte/reporte_ajustes.php';

if (!function_exists('convertir')) {
    function convertir($texto) {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto);
    }
}

// Capturar el estado desde la URL
$estado = $_GET['estado'] ?? 'PROCESADO';

// Crear instancia de PDF
$pdf = new BasePDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, "Reporte de Ajustes - Estado: " . convertir($estado), 0, 1, 'C');
$pdf->Ln(5);

// Configurar conexión PDO
$dsn = "pgsql:host=$host;port=$port;dbname=$database;";
$pdo = new PDO($dsn, $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // Consulta los ajustes filtrados por estado
    $query = "
        SELECT 
            a.ajuste_id,
            u.username AS usuario,
            m.motivo_descripcion AS motivo
        FROM ajustes a
        JOIN usuarios u ON a.id_usuario = u.id_usuario
        JOIN motivo_ajuste m ON a.motivo_id = m.motivo_id
        WHERE a.ajuste_estado = :estado
        ORDER BY a.ajuste_id ASC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':estado' => $estado]);

    // Posicionar la tabla centrada
    $pdf->SetX(($pdf->GetPageWidth() - 150) / 2);

    // Encabezado de la tabla
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(30, 10, 'ID Ajuste', 1, 0, 'C');
    $pdf->Cell(60, 10, 'Motivo', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Producto', 1, 0, 'C');
    $pdf->Cell(20, 10, 'Cantidad', 1, 1, 'C');

    // Contenido de la tabla
    $pdf->SetFont('Arial', '', 10);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pdf->SetX(($pdf->GetPageWidth() - 150) / 2);
        $pdf->Cell(30, 10, $row['ajuste_id'], 1, 0, 'C');
        $pdf->Cell(60, 10, convertir($row['motivo']), 1, 0, 'C');

        // Consulta los detalles del ajuste
        $queryDetalles = "
            SELECT 
                p.p_descrip AS producto,
                d.ajuste_cantidad AS cantidad
            FROM ajuste_detalle d
            JOIN producto p ON d.cod_producto = p.cod_producto
            WHERE d.ajuste_id = :ajuste_id
        ";
        $stmtDetalles = $pdo->prepare($queryDetalles);
        $stmtDetalles->execute([':ajuste_id' => $row['ajuste_id']]);

        // Mostrar los detalles en nuevas filas
        $firstDetail = true;
        while ($detalle = $stmtDetalles->fetch(PDO::FETCH_ASSOC)) {
            if (!$firstDetail) {
                $pdf->SetX(($pdf->GetPageWidth() - 150) / 2);
                $pdf->Cell(30, 10, '', 1, 0, 'C');  // Celda vacía para alinear
                $pdf->Cell(60, 10, '', 1, 0, 'C');  // Celda vacía para alinear
            }
            $pdf->Cell(40, 10, convertir($detalle['producto']), 1, 0, 'C');
            $pdf->Cell(20, 10, $detalle['cantidad'], 1, 1, 'C');
            $firstDetail = false;
        }
    }

    // Mostrar el PDF en el navegador
    $pdf->Output('I', 'Reporte_Ajustes_' . $estado . '.pdf');

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
