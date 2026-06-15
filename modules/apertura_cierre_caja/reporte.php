<?php
require_once '../../config/database.php';
require_once '../../reporte/pedido_ventas.php'; // Clase BasePDF (reutilizable)

// Verificar si se recibió el parámetro 'apertura_id'
if (!isset($_GET['apertura_id']) || empty($_GET['apertura_id'])) {
    die("No se proporcionó un ID de apertura válido.");
}

$apertura_id = intval($_GET['apertura_id']);

$pdf = new BasePDF('P', 'mm', 'A4', ['titulo' => 'Apertura y Cierre de Caja']);
$pdf->AddPage();

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para obtener la información general de la apertura
    $queryGeneral = "
        SELECT 
            acc.id_apertura,
            acc.id_apertura AS numero_apertura,
            to_char(acc.fecha_apertura, 'YYYY-MM-DD') AS fecha_apertura,
            to_char(acc.hora_apertura, 'HH24:MI:SS') AS hora_apertura,
            to_char(acc.fecha_cierre, 'YYYY-MM-DD') AS fecha_cierre,
            to_char(acc.hora_cierre, 'HH24:MI:SS') AS hora_cierre,
            acc.monto_apertura AS monto_inicial,
            acc.apertura_estado AS estado,
            acc.apertura_efectivo AS total_efectivo,
            acc.apertura_tarjeta AS total_tarjeta,
            0 AS total_transferencia,
            acc.apertura_cheque AS total_cheque,
            0 AS total_billetera,
            (acc.apertura_efectivo + acc.apertura_tarjeta + acc.apertura_cheque) AS total_general,
            0 AS sobrante,
            0 AS faltante,
            '' AS observaciones,
            c.descripcion_caja,
            p.personal_nombre || ' ' || p.personal_apellido AS cajero_nombre,
            s.descripcion_sucursal
        FROM 
            apertura_cierre_caja acc
        JOIN 
            caja c ON acc.id_caja = c.id_caja
        JOIN 
            cajero cj ON acc.cajero_id = cj.cajero_id
        JOIN 
            personal p ON cj.id_personal = p.id_personal
        JOIN 
            sucursales s ON acc.id_sucursal = s.id_sucursal
        WHERE 
            acc.id_apertura = :apertura_id
        LIMIT 1
    ";

    $stmtGeneral = $pdo->prepare($queryGeneral);
    $stmtGeneral->bindParam(':apertura_id', $apertura_id, PDO::PARAM_INT);
    $stmtGeneral->execute();
    $general = $stmtGeneral->fetch(PDO::FETCH_ASSOC);

    if ($general) {
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(50, 8, 'N° Apertura:', 0, 0, 'L');
        $pdf->Cell(80, 8, $general['numero_apertura'], 0, 1, 'L');
        $pdf->Cell(50, 8, 'Fecha Apertura:', 0, 0, 'L');
        $pdf->Cell(80, 8, $general['fecha_apertura'] . ' ' . $general['hora_apertura'], 0, 1, 'L');
        if ($general['fecha_cierre']) {
            $pdf->Cell(50, 8, 'Fecha Cierre:', 0, 0, 'L');
            $pdf->Cell(80, 8, $general['fecha_cierre'] . ' ' . $general['hora_cierre'], 0, 1, 'L');
        }
        $pdf->Cell(50, 8, 'Caja:', 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['descripcion_caja']), 0, 1, 'L');
        $pdf->Cell(50, 8, 'Cajero:', 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['cajero_nombre']), 0, 1, 'L');
        $pdf->Cell(50, 8, 'Sucursal:', 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['descripcion_sucursal']), 0, 1, 'L');
        $pdf->Cell(50, 8, 'Estado:', 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['estado']), 0, 1, 'L');
        $pdf->Ln(5);

        // Totales por tipo de pago
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, convertir('Resumen de Cobros'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(50, 8, 'Monto Inicial:', 0, 0, 'L');
        $pdf->Cell(80, 8, number_format($general['monto_inicial'], 0, ',', '.'), 0, 1, 'R');
        $pdf->Cell(50, 8, 'Efectivo:', 0, 0, 'L');
        $pdf->Cell(80, 8, number_format($general['total_efectivo'], 0, ',', '.'), 0, 1, 'R');
        $pdf->Cell(50, 8, 'Tarjeta:', 0, 0, 'L');
        $pdf->Cell(80, 8, number_format($general['total_tarjeta'], 0, ',', '.'), 0, 1, 'R');
        $pdf->Cell(50, 8, 'Transferencia:', 0, 0, 'L');
        $pdf->Cell(80, 8, number_format($general['total_transferencia'], 0, ',', '.'), 0, 1, 'R');
        $pdf->Cell(50, 8, 'Cheque:', 0, 0, 'L');
        $pdf->Cell(80, 8, number_format($general['total_cheque'], 0, ',', '.'), 0, 1, 'R');
        $pdf->Cell(50, 8, 'Billetera:', 0, 0, 'L');
        $pdf->Cell(80, 8, number_format($general['total_billetera'], 0, ',', '.'), 0, 1, 'R');
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(50, 8, 'Total General:', 0, 0, 'L');
        $pdf->Cell(80, 8, number_format($general['total_general'], 0, ',', '.'), 0, 1, 'R');
        
        if ($general['sobrante'] > 0 || $general['faltante'] > 0) {
            $pdf->Ln(3);
            if ($general['sobrante'] > 0) {
                $pdf->Cell(50, 8, 'Sobrante:', 0, 0, 'L');
                $pdf->Cell(80, 8, number_format($general['sobrante'], 0, ',', '.'), 0, 1, 'R');
            }
            if ($general['faltante'] > 0) {
                $pdf->Cell(50, 8, 'Faltante:', 0, 0, 'L');
                $pdf->Cell(80, 8, number_format($general['faltante'], 0, ',', '.'), 0, 1, 'R');
            }
        }

        if ($general['observaciones']) {
            $pdf->Ln(5);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 8, convertir('Observaciones:'), 0, 1, 'L');
            $pdf->MultiCell(0, 6, convertir($general['observaciones']), 0, 'L');
        }

        // Arqueos realizados
        // Nota: arqueo_caja.id_apertura referencia a apertura_cierre_caja.id_apertura
        $queryArqueos = $pdo->prepare("
            SELECT 
                to_char(fecha_arqueo, 'YYYY-MM-DD') AS fecha,
                to_char(hora_arqueo, 'HH24:MI:SS') AS hora,
                efectivo_contado,
                diferencia_efectivo,
                observacion
            FROM arqueo_caja
            WHERE id_apertura = :apertura_id
            ORDER BY fecha_arqueo DESC, hora_arqueo DESC
        ");
        $queryArqueos->execute([':apertura_id' => $apertura_id]);
        $arqueos = $queryArqueos->fetchAll(PDO::FETCH_ASSOC);

        if (count($arqueos) > 0) {
            $pdf->Ln(10);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 8, convertir('Arqueos Realizados'), 0, 1, 'L');
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(200, 220, 255);
            $pdf->Cell(40, 8, convertir('Fecha/Hora'), 1, 0, 'C', true);
            $pdf->Cell(40, 8, convertir('Efectivo Contado'), 1, 0, 'C', true);
            $pdf->Cell(40, 8, convertir('Diferencia'), 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 9);
            foreach ($arqueos as $arqueo) {
                $pdf->Cell(40, 8, $arqueo['fecha'] . ' ' . $arqueo['hora'], 1, 0, 'L');
                $pdf->Cell(40, 8, number_format($arqueo['efectivo_contado'], 0, ',', '.'), 1, 0, 'R');
                $pdf->Cell(40, 8, number_format($arqueo['diferencia_efectivo'], 0, ',', '.'), 1, 1, 'R');
            }
        }
    } else {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'No se encontraron datos para esta apertura.', 0, 1, 'C');
    }

    $pdf->Output('I', "Apertura_Caja_{$apertura_id}.pdf");
} catch (PDOException $e) {
    echo "Error en la conexión o consulta: " . $e->getMessage();
    exit;
}
?>

