<?php
require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../reporte/pedido_ventas.php');

if (!isset($_GET['ped_id']) || !is_numeric($_GET['ped_id'])) {
    die('ID de pedido inválido.');
}

$pedId = (int)$_GET['ped_id'];
$pdf = new BasePDF('P', 'mm', 'A4', ['titulo' => 'Pedido de Producción']);
$pdf->AddPage();

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $stmtGeneral = $pdo->prepare("
        SELECT
            pp.id_pedido_produccion,
            to_char(pp.pedido_prod_fecha_emision, 'YYYY-MM-DD') AS pedido_fecha,
            pp.pedido_prod_estado,
            tp.tipo_pedido_descri,
            us.username AS usuario,
            suc.descripcion_sucursal AS sucursal,
            pp.pedido_prod_observaciones
        FROM pedido_produccion pp
        JOIN sucursales suc ON pp.id_sucursal = suc.id_sucursal
        JOIN usuarios us ON pp.id_usuario = us.id_usuario
        JOIN tipo_pedido tp ON tp.id_tipo_pedido = pp.id_tipo_pedido
        WHERE pp.id_pedido_produccion = :ped_id
        LIMIT 1
    ");
    $stmtGeneral->execute([':ped_id' => $pedId]);
    $general = $stmtGeneral->fetch(PDO::FETCH_ASSOC);

    if ($general) {
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(45, 8, convertir('Pedido N°:'), 0, 0, 'L');
        $pdf->Cell(80, 8, $general['id_pedido_produccion'], 0, 1, 'L');
        $pdf->Cell(45, 8, convertir('Fecha:'), 0, 0, 'L');
        $pdf->Cell(80, 8, $general['pedido_fecha'], 0, 1, 'L');
        $pdf->Cell(45, 8, convertir('Estado:'), 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['pedido_prod_estado']), 0, 1, 'L');
        $pdf->Cell(45, 8, convertir('Tipo:'), 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['tipo_pedido_descri']), 0, 1, 'L');
        $pdf->Cell(45, 8, convertir('Usuario:'), 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['usuario']), 0, 1, 'L');
        $pdf->Cell(45, 8, convertir('Sucursal:'), 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['sucursal']), 0, 1, 'L');
        if (!empty($general['pedido_prod_observaciones'])) {
            $pdf->Cell(45, 8, convertir('Obs.:'), 0, 0, 'L');
            $pdf->MultiCell(130, 8, convertir($general['pedido_prod_observaciones']), 0, 'L');
        }
        $pdf->Ln(8);
    }

    $stmtDetails = $pdo->prepare("
        SELECT p.producto_descri AS producto, d.cantidad_pedido AS cantidad
        FROM pedido_detalle_produccion d
        JOIN productos p ON p.producto_id = d.producto_id
        WHERE d.id_pedido_produccion = :ped_id
        ORDER BY p.producto_descri
    ");
    $stmtDetails->execute([':ped_id' => $pedId]);

    if ($stmtDetails->rowCount() > 0) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(200, 220, 255);
        $pdf->Cell(120, 8, convertir('Producto'), 1, 0, 'C', true);
        $pdf->Cell(60, 8, convertir('Cantidad'), 1, 1, 'C', true);
        $pdf->SetFont('Arial', '', 10);
        while ($row = $stmtDetails->fetch(PDO::FETCH_ASSOC)) {
            $pdf->Cell(120, 8, convertir($row['producto']), 1, 0, 'L');
            $pdf->Cell(60, 8, $row['cantidad'], 1, 1, 'C');
        }
    } else {
        $pdf->Cell(0, 10, convertir('Sin detalle de productos.'), 0, 1, 'C');
    }

    $pdf->Output('I', "Pedido_Produccion_{$pedId}.pdf");
} catch (PDOException $e) {
    die('Error: ' . $e->getMessage());
}
