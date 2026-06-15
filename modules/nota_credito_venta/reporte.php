<?php
require_once '../../config/database.php';
require_once '../../reporte/reporte_NCND.php';

function fmt_gs($valor) {
    return number_format((int)$valor, 0, ',', '.') . ' Gs';
}

$notaId = isset($_GET['nota_id']) ? (int)$_GET['nota_id'] : (int)($_GET['id'] ?? 0);
if ($notaId <= 0) {
    die('Parámetro de nota inválido.');
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Throwable $e) {
    die("No se pudo conectar a la base de datos: " . $e->getMessage());
}

$sqlNota = "
    SELECT
        nv.id_nota_venta,
        nv.nota_venta_tipo,
        nv.nota_venta_fecha,
        nv.nota_venta_emision,
        nv.nota_nro,
        nv.nota_venta_timbrado,
        nv.nota_venta_estado,
        nv.nota_total,
        nv.subtotal,
        nv.iva_5,
        nv.iva_10,
        nv.iva_exento,
        nv.descripcion,
        nv.medio_devolucion,
        nv.id_factura_venta,
        m.motivo_descripcion,
        c.cliente_nombre || ' ' || c.cliente_apellido AS cliente_nombre,
        c.cliente_ruc,
        u.username,
        fv.numero_factura
    FROM nota_venta nv
    JOIN clientes c ON c.id_cliente = nv.id_cliente
    JOIN usuarios u ON u.id_usuario = nv.id_usuario
    LEFT JOIN motivo m ON m.id_motivo = nv.id_motivo
    LEFT JOIN factura_ventas fv ON fv.id_factura_venta = nv.id_factura_venta
    WHERE nv.id_nota_venta = :id
    LIMIT 1
";

$stNota = $pdo->prepare($sqlNota);
$stNota->execute([':id' => $notaId]);
$nota = $stNota->fetch();
if (!$nota) {
    die('Nota no encontrada.');
}

$sqlDet = "
    SELECT
        p.producto_descri,
        d.cantidad_nota AS cantidad,
        d.nota_precio AS precio,
        d.iva_porcentaje,
        d.total_linea
    FROM nota_detalle_venta d
    JOIN productos p ON p.producto_id = d.producto_id
    WHERE d.id_nota_venta = :id
    ORDER BY p.producto_descri
";
$stDet = $pdo->prepare($sqlDet);
$stDet->execute([':id' => $notaId]);
$detalles = $stDet->fetchAll();

$pdf = new BasePDF();
$pdf->AddPage();

$T = fn($s) => iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$s);

// Encabezado
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, $T('NOTA DE CRÉDITO'), 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('Arial', '', 12);
$pdf->Cell(40, 8, 'N° Nota:', 0, 0, 'L');
$pdf->Cell(80, 8, $T($nota['nota_nro'] ?? 'N/A'), 0, 1, 'L');
$pdf->Cell(40, 8, 'Timbrado:', 0, 0, 'L');
$pdf->Cell(80, 8, $T($nota['nota_venta_timbrado'] ?? 'N/A'), 0, 1, 'L');
$pdf->Cell(40, 8, 'Fecha:', 0, 0, 'L');
$pdf->Cell(80, 8, $T($nota['nota_venta_fecha']), 0, 1, 'L');
$pdf->Cell(40, 8, 'Fecha Emisión:', 0, 0, 'L');
$pdf->Cell(80, 8, $T($nota['nota_venta_emision'] ?? $nota['nota_venta_fecha']), 0, 1, 'L');
$pdf->Cell(40, 8, 'Cliente:', 0, 0, 'L');
$pdf->Cell(80, 8, $T($nota['cliente_nombre']), 0, 1, 'L');
$pdf->Cell(40, 8, 'RUC:', 0, 0, 'L');
$pdf->Cell(80, 8, $T($nota['cliente_ruc']), 0, 1, 'L');
$pdf->Cell(40, 8, 'Factura:', 0, 0, 'L');
$pdf->Cell(80, 8, $T($nota['numero_factura']), 0, 1, 'L');
$pdf->Cell(40, 8, 'Motivo:', 0, 0, 'L');
$pdf->Cell(80, 8, $T($nota['motivo_descripcion'] ?? 'N/A'), 0, 1, 'L');
$pdf->Cell(40, 8, 'Estado:', 0, 0, 'L');
$pdf->Cell(80, 8, $T($nota['nota_venta_estado']), 0, 1, 'L');
$pdf->Cell(40, 8, 'Medio Devolución:', 0, 0, 'L');
$pdf->Cell(80, 8, $T($nota['medio_devolucion'] ?? 'N/A'), 0, 1, 'L');
$pdf->Cell(40, 8, 'Usuario:', 0, 0, 'L');
$pdf->Cell(80, 8, $T($nota['username']), 0, 1, 'L');
$pdf->Ln(5);

// Detalle
if (!empty($detalles)) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(80, 8, $T('Producto'), 1, 0, 'L');
    $pdf->Cell(20, 8, $T('Cant.'), 1, 0, 'C');
    $pdf->Cell(30, 8, $T('Precio'), 1, 0, 'R');
    $pdf->Cell(20, 8, $T('IVA %'), 1, 0, 'C');
    $pdf->Cell(30, 8, $T('Total'), 1, 1, 'R');

    $pdf->SetFont('Arial', '', 9);
    foreach ($detalles as $det) {
        $pdf->Cell(80, 6, $T($det['producto_descri']), 1, 0, 'L');
        $pdf->Cell(20, 6, (string)$det['cantidad'], 1, 0, 'C');
        $pdf->Cell(30, 6, number_format($det['precio'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(20, 6, (string)$det['iva_porcentaje'] . '%', 1, 0, 'C');
        $pdf->Cell(30, 6, number_format($det['total_linea'], 0, ',', '.'), 1, 1, 'R');
    }

    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(130, 8, $T('Subtotal (sin IVA):'), 0, 0, 'R');
    $pdf->Cell(30, 8, number_format($nota['subtotal'], 0, ',', '.'), 0, 1, 'R');
    $pdf->Cell(130, 8, $T('IVA 5%:'), 0, 0, 'R');
    $pdf->Cell(30, 8, number_format($nota['iva_5'], 0, ',', '.'), 0, 1, 'R');
    $pdf->Cell(130, 8, $T('IVA 10%:'), 0, 0, 'R');
    $pdf->Cell(30, 8, number_format($nota['iva_10'], 0, ',', '.'), 0, 1, 'R');
    $pdf->Cell(130, 8, $T('TOTAL:'), 0, 0, 'R');
    $pdf->Cell(30, 8, number_format($nota['nota_total'], 0, ',', '.'), 0, 1, 'R');
}

if (!empty($nota['descripcion'])) {
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(0, 6, $T('Descripción: ' . $nota['descripcion']), 0, 1, 'L');
}

$pdf->Output('I', 'nota_credito_venta_' . $notaId . '.pdf');
?>

