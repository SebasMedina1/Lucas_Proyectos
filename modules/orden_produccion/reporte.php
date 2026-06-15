<?php
require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../reporte/pedido_ventas.php');

if (!isset($_GET['orden_id']) || !is_numeric($_GET['orden_id'])) {
    die('ID de orden inválido.');
}

$ordenId = (int)$_GET['orden_id'];
$pdf = new BasePDF('P', 'mm', 'A4', ['titulo' => 'Orden de Producción']);
$pdf->AddPage();

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $stmtGeneral = $pdo->prepare("
        SELECT
            op.orden_id,
            to_char(op.orden_prod_fecha, 'YYYY-MM-DD') AS fecha_emision,
            to_char(op.orden_prod_fecha_entrega, 'YYYY-MM-DD') AS fecha_entrega,
            op.orden_prod_estado,
            op.id_pedido_produccion,
            us.username AS usuario,
            tp.tipo_pedido_descri
        FROM orden_produccion op
        JOIN pedido_produccion pp ON pp.id_pedido_produccion = op.id_pedido_produccion
        JOIN tipo_pedido tp ON tp.id_tipo_pedido = pp.id_tipo_pedido
        JOIN usuarios us ON us.id_usuario = op.id_usuario
        WHERE op.orden_id = :id
        LIMIT 1
    ");
    $stmtGeneral->execute([':id' => $ordenId]);
    $general = $stmtGeneral->fetch(PDO::FETCH_ASSOC);

    if ($general) {
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(55, 8, convertir('Orden N°:'), 0, 0, 'L');
        $pdf->Cell(80, 8, $general['orden_id'], 0, 1, 'L');
        $pdf->Cell(55, 8, convertir('Fecha emisión:'), 0, 0, 'L');
        $pdf->Cell(80, 8, $general['fecha_emision'], 0, 1, 'L');
        $pdf->Cell(55, 8, convertir('Entrega prevista:'), 0, 0, 'L');
        $pdf->Cell(80, 8, $general['fecha_entrega'], 0, 1, 'L');
        $pdf->Cell(55, 8, convertir('Estado:'), 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['orden_prod_estado']), 0, 1, 'L');
        $pdf->Cell(55, 8, convertir('Pedido N°:'), 0, 0, 'L');
        $pdf->Cell(80, 8, $general['id_pedido_produccion'], 0, 1, 'L');
        $pdf->Cell(55, 8, convertir('Tipo pedido:'), 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['tipo_pedido_descri']), 0, 1, 'L');
        $pdf->Cell(55, 8, convertir('Usuario:'), 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['usuario']), 0, 1, 'L');
        $pdf->Ln(8);
    }

    $stmtDetails = $pdo->prepare("
        SELECT p.producto_descri AS producto, d.orden_prod_cantidad AS cantidad, d.cantidad_pendiente
        FROM orden_detalle_produccion d
        JOIN productos p ON p.producto_id = d.producto_id
        WHERE d.orden_id = :id
        ORDER BY p.producto_descri
    ");
    $stmtDetails->execute([':id' => $ordenId]);

    if ($stmtDetails->rowCount() > 0) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(200, 220, 255);
        $pdf->Cell(90, 8, convertir('Producto'), 1, 0, 'C', true);
        $pdf->Cell(45, 8, convertir('Cantidad'), 1, 0, 'C', true);
        $pdf->Cell(45, 8, convertir('Pendiente'), 1, 1, 'C', true);
        $pdf->SetFont('Arial', '', 10);
        while ($row = $stmtDetails->fetch(PDO::FETCH_ASSOC)) {
            $pdf->Cell(90, 8, convertir($row['producto']), 1, 0, 'L');
            $pdf->Cell(45, 8, $row['cantidad'], 1, 0, 'C');
            $pdf->Cell(45, 8, $row['cantidad_pendiente'], 1, 1, 'C');
        }
    } else {
        $pdf->Cell(0, 10, convertir('Sin detalle de productos.'), 0, 1, 'C');
    }

    $pdf->Output('I', "Orden_Produccion_{$ordenId}.pdf");
} catch (PDOException $e) {
    die('Error: ' . $e->getMessage());
}
