<?php
require_once '../../config/database.php';
require_once '../../reporte/reporte_presupuesto.php'; // Clase para generar el PDF

// Verificar si se recibió el parámetro 'pre_id'
if (!isset($_GET['pre_id']) || empty($_GET['pre_id'])) {
    die("No se proporcionó un ID de presupuesto válido.");
}

$pre_id = intval($_GET['pre_id']); // Convertir a entero

$pdf = new BasePDF();
$pdf->AddPage();

try {
    // Configurar conexión PDO con UTF-8
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    // Consultar datos del presupuesto de compra
    $queryPresupuesto = "
        SELECT 
            pc.pre_fecha,
            pc.pre_hora,
            pc.pre_estado,
            pc.total_importe,
            p.razon_social AS proveedor,
            u.username AS usuario
        FROM 
            presupuesto_compra pc
        JOIN 
            proveedor p ON pc.cod_proveedor = p.cod_proveedor
        JOIN 
            usuarios u ON pc.id_usuario = u.id_usuario
        WHERE 
            pc.presupuesto_id = :pre_id
    ";

    $stmtPresupuesto = $pdo->prepare($queryPresupuesto);
    $stmtPresupuesto->bindParam(':pre_id', $pre_id, PDO::PARAM_INT);
    $stmtPresupuesto->execute();

    $presupuesto = $stmtPresupuesto->fetch();

    if (!$presupuesto) {
        die("No se encontró el presupuesto con el ID proporcionado.");
    }

    // Encabezado de factura
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, "Fecha: " . $presupuesto['pre_fecha'], 0, 1, 'L');
    $pdf->Cell(0, 6, "Hora: " . $presupuesto['pre_hora'], 0, 1, 'L');
    $pdf->Cell(0, 6, "Usuario: " . $presupuesto['usuario'], 0, 1, 'L');
    $pdf->Cell(0, 6, "Presupuesto Nro: " . $pre_id, 0, 1, 'L');
    $pdf->Cell(0, 6, "Estado: " . $presupuesto['pre_estado'], 0, 1, 'L');
    $pdf->Ln(5); // Espaciado

    // Agregar encabezados de la tabla del detalle
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(200, 220, 255);
    $pdf->Cell(45, 8, 'Producto', 1, 0, 'C', true);
    $pdf->Cell(45, 8, 'Cantidad', 1, 0, 'C', true);
    $pdf->Cell(45, 8, 'Precio Unitario', 1, 0, 'C', true);
    $pdf->Cell(45, 8, 'Subtotal', 1, 1, 'C', true);

    // Consultar datos del detalle del presupuesto
    $queryDetalle = "
        SELECT 
            p.p_descrip AS producto,
            pdc.pre_cantidad AS cantidad,
            pdc.pre_precio AS precio,
            (pdc.pre_cantidad * pdc.pre_precio) AS subtotal
        FROM
            presupuesto_detalle_compra pdc
        JOIN producto p ON pdc.cod_producto = p.cod_producto
        WHERE
            pdc.presupuesto_id = :pre_id
    ";

    $stmtDetalle = $pdo->prepare($queryDetalle);
    $stmtDetalle->bindParam(':pre_id', $pre_id, PDO::PARAM_INT);
    $stmtDetalle->execute();

    // Agregar datos del detalle al PDF
    $pdf->SetFont('Arial', '', 10);
    while ($detalle = $stmtDetalle->fetch()) {
        $pdf->Cell(45, 8, $detalle['producto'], 1, 0, 'C');
        $pdf->Cell(45, 8, $detalle['cantidad'], 1, 0, 'C');
        $pdf->Cell(45, 8,  number_format($detalle['precio'], 0, ',', '.'). " Gs", 1, 0, 'C');
        $pdf->Cell(45, 8,  number_format($detalle['subtotal'], 0, ',', '.'). " Gs", 1, 1, 'C');

        // Sumar el subtotal al total general
    }

    // Agregar total al final
    $pdf->Ln(5); // Espaciado
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, "Total: " . $presupuesto['total_importe'] . " Gs", 0, 1, 'R');

    // Mostrar el PDF en el navegador
    $pdf->Output('I', "Detalle_Presupuesto_$pre_id.pdf");
} catch (PDOException $e) {
    echo "Error en la conexión o consulta: " . $e->getMessage();
    exit;
}
?>
