<?php
require_once '../../config/database.php';
require_once '../../reporte/reporte_compras.php'; // BasePDF

if (!isset($_GET['fact_id']) || !ctype_digit($_GET['fact_id'])) {
    die("No se proporcionó un ID de factura válido.");
}
$fact_id = (int)$_GET['fact_id'];

$pdf = new BasePDF();
$pdf->AddPage();

$T = fn($s) => iconv('UTF-8','ISO-8859-1//TRANSLIT', (string)$s);

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Cabecera de la factura de venta
    $sqlCab = "
        SELECT 
            fv.id_factura_venta,
            fv.numero_factura,
            fv.timbrado,
            TO_CHAR(fv.fecha_factura,'YYYY-MM-DD') AS fecha,
            TO_CHAR(fv.hora_factura,'HH24:MI:SS') AS hora,
            TO_CHAR(fv.fecha_emision,'YYYY-MM-DD') AS fecha_emision,
            fv.total_general,
            fv.estado,
            fv.tipo_factura,
            fv.plazo,
            fv.cuotas,
            fv.interes_pct,
            fv.subtotal,
            fv.iva_5,
            fv.iva_10,
            fv.iva_exento,
            u.username AS usuario,
            s.descripcion_sucursal AS sucursal,
            c.cliente_nombre || ' ' || c.cliente_apellido AS cliente_nombre,
            c.cliente_ruc,
            fv.id_pedido_venta,
            fv.observaciones
        FROM factura_ventas fv
        JOIN usuarios u ON u.id_usuario = fv.id_usuario
        JOIN sucursales s ON s.id_sucursal = fv.id_sucursal
        JOIN clientes c ON c.id_cliente = fv.id_cliente
        WHERE fv.id_factura_venta = :id
        LIMIT 1
    ";
    $stCab = $pdo->prepare($sqlCab);
    $stCab->execute([':id' => $fact_id]);
    $cab = $stCab->fetch();

    if (!$cab) die("No se encontró la factura de venta con el ID proporcionado.");

    // Encabezado
    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(0, 10, $T('FACTURA DE VENTA'), 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('Arial','',12);
    $pdf->Cell(40, 8, 'Usuario:', 0, 0, 'L');
    $pdf->Cell(80, 8, $T($cab['usuario']), 0, 1, 'L');
    $pdf->Cell(40, 8, 'Fecha:', 0, 0, 'L');
    $pdf->Cell(80, 8, $T($cab['fecha']), 0, 1, 'L');
    $pdf->Cell(40, 8, 'Hora:', 0, 0, 'L');
    $pdf->Cell(80, 8, $T($cab['hora']), 0, 1, 'L');
    $pdf->Cell(40, 8, 'Sucursal:', 0, 0, 'L');
    $pdf->Cell(80, 8, $T($cab['sucursal']), 0, 1, 'L');
    $pdf->Cell(40, 8, 'Cliente:', 0, 0, 'L');
    $pdf->Cell(80, 8, $T($cab['cliente_nombre']), 0, 1, 'L');
    $pdf->Cell(40, 8, 'RUC:', 0, 0, 'L');
    $pdf->Cell(80, 8, $T($cab['cliente_ruc']), 0, 1, 'L');
    $pdf->Cell(40, 8, 'Factura Nro:', 0, 0, 'L');
    $pdf->Cell(80, 8, $T($cab['numero_factura']), 0, 1, 'L');
    $pdf->Cell(40, 8, 'Timbrado:', 0, 0, 'L');
    $pdf->Cell(80, 8, $T($cab['timbrado']), 0, 1, 'L');
    $pdf->Cell(40, 8, 'Fecha Emisión:', 0, 0, 'L');
    $pdf->Cell(80, 8, $T($cab['fecha_emision'] ?? $cab['fecha']), 0, 1, 'L');
    $pdf->Cell(40, 8, 'Estado:', 0, 0, 'L');
    $pdf->Cell(80, 8, $T($cab['estado']), 0, 1, 'L');
    $pdf->Cell(40, 8, 'Tipo:', 0, 0, 'L');
    $pdf->Cell(80, 8, $T($cab['tipo_factura']), 0, 1, 'L');

    if ($cab['tipo_factura'] === 'CREDITO') {
        $pdf->Cell(40, 8, 'Cuotas:', 0, 0, 'L');
        $pdf->Cell(80, 8, (string)$cab['cuotas'], 0, 1, 'L');
        $pdf->Cell(40, 8, '% Interés:', 0, 0, 'L');
        $pdf->Cell(80, 8, (string)$cab['interes_pct'] . ' %', 0, 1, 'L');
        if (!empty($cab['plazo'])) {
            $pdf->Cell(40, 8, 'Plazo:', 0, 0, 'L');
            $pdf->Cell(80, 8, $T($cab['plazo']), 0, 1, 'L');
        }
    }

    if ($cab['id_pedido_venta']) {
        $pdf->Cell(40, 8, 'Pedido N°:', 0, 0, 'L');
        $pdf->Cell(80, 8, (string)$cab['id_pedido_venta'], 0, 1, 'L');
    }

    $pdf->Ln(6);

    // Detalle
    $sqlDet = "
        SELECT 
            p.producto_descri AS producto,
            fd.cantidad,
            fd.precio_unitario,
            fd.subtotal,
            fd.iva_porcentaje,
            fd.iva_monto,
            fd.total_linea
        FROM factura_detalle_venta fd
        JOIN productos p ON p.producto_id = fd.producto_id
        WHERE fd.id_factura_venta = :id
        ORDER BY p.producto_descri
    ";
    $stDet = $pdo->prepare($sqlDet);
    $stDet->execute([':id' => $fact_id]);
    $detalles = $stDet->fetchAll();

    if (!empty($detalles)) {
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell(80, 8, $T('Producto'), 1, 0, 'L');
        $pdf->Cell(20, 8, $T('Cant.'), 1, 0, 'C');
        $pdf->Cell(30, 8, $T('Precio Unit.'), 1, 0, 'R');
        $pdf->Cell(20, 8, $T('IVA %'), 1, 0, 'C');
        $pdf->Cell(30, 8, $T('Total'), 1, 1, 'R');

        $pdf->SetFont('Arial','',9);
        foreach ($detalles as $det) {
            $pdf->Cell(80, 6, $T($det['producto']), 1, 0, 'L');
            $pdf->Cell(20, 6, (string)$det['cantidad'], 1, 0, 'C');
            $pdf->Cell(30, 6, number_format($det['precio_unitario'], 0, ',', '.'), 1, 0, 'R');
            $pdf->Cell(20, 6, (string)$det['iva_porcentaje'] . '%', 1, 0, 'C');
            $pdf->Cell(30, 6, number_format($det['total_linea'], 0, ',', '.'), 1, 1, 'R');
        }

        $pdf->Ln(5);
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell(130, 8, $T('Subtotal (sin IVA):'), 0, 0, 'R');
        $pdf->Cell(30, 8, number_format($cab['subtotal'], 0, ',', '.'), 0, 1, 'R');
        $pdf->Cell(130, 8, $T('IVA 5%:'), 0, 0, 'R');
        $pdf->Cell(30, 8, number_format($cab['iva_5'], 0, ',', '.'), 0, 1, 'R');
        $pdf->Cell(130, 8, $T('IVA 10%:'), 0, 0, 'R');
        $pdf->Cell(30, 8, number_format($cab['iva_10'], 0, ',', '.'), 0, 1, 'R');
        $pdf->Cell(130, 8, $T('TOTAL GENERAL:'), 0, 0, 'R');
        $pdf->Cell(30, 8, number_format($cab['total_general'], 0, ',', '.'), 0, 1, 'R');
    }

    if (!empty($cab['observaciones'])) {
        $pdf->Ln(5);
        $pdf->SetFont('Arial','I',9);
        $pdf->Cell(0, 6, $T('Observaciones: ' . $cab['observaciones']), 0, 1, 'L');
    }

    $pdf->Output('I', 'factura_venta_' . $fact_id . '.pdf');
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

