<?php
require_once '../../config/database.php';
require_once '../../reporte/reporte_NCND.php';

function fmt_gs($valor){
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
    die("No se pudo conectar a la base de datos: ".$e->getMessage());
}

$sqlNota = "
    SELECT
      nc.id_nota_compra,
      nc.nota_compra_tipo,
      nc.nota_compra_fecha,
      nc.nota_compra_inicio AS nota_compra_emision,
      nc.nota_compra_vencimiento,
      nc.nota_nro,
      nc.nota_compra_timbrado,
      nc.nota_compra_estado,
      nc.nota_total,
      nc.id_factura_compra,
      m.motivo_descripcion,
      pr.razon_social,
      pr.ruc_proveedor,
      u.username,
      fc.numero_factura
    FROM nota_compra nc
    JOIN proveedor pr ON pr.id_proveedor = nc.id_proveedor
    JOIN usuarios  u  ON u.id_usuario   = nc.id_usuario
    LEFT JOIN motivo m ON m.id_motivo   = nc.id_motivo
    LEFT JOIN factura_compra fc ON fc.id_factura_compra = nc.id_factura_compra
    WHERE nc.id_nota_compra = :id
    LIMIT 1
";

$stNota = $pdo->prepare($sqlNota);
$stNota->execute([':id'=>$notaId]);
$nota = $stNota->fetch();
if (!$nota) {
    die('Nota no encontrada.');
}

$sqlDet = "
  SELECT
    mp.materia_prima_descripcion,
    d.nota_compra_cantidad AS cantidad,
    d.nota_precio          AS precio,
    d.tipo_iva
  FROM nota_detalle_compra d
  JOIN materia_prima mp ON mp.id_materia_prima = d.id_materia_prima
  WHERE d.id_nota_compra = :id
  ORDER BY mp.materia_prima_descripcion
";
$stDet = $pdo->prepare($sqlDet);
$stDet->execute([':id'=>$notaId]);
$detalles = $stDet->fetchAll();

$totIva = ['5'=>0,'10'=>0];
$totalSub = 0;
foreach ($detalles as &$det) {
    $sub = (int)$det['cantidad'] * (int)$det['precio'];
    $det['subtotal'] = $sub;
    $totalSub += $sub;
    $ivaKey = (int)$det['tipo_iva'];
    if ($ivaKey === 10) {
        $totIva['10'] += (int)floor($sub / 11);
    } elseif ($ivaKey === 5) {
        $totIva['5'] += (int)floor($sub / 21);
    }
}
unset($det);

$pdf = new BasePDF('P', 'mm', 'A4', ['titulo'=>'Detalle Nota Crédito / Débito']);
$pdf->AddPage();
$pdf->SetFont('Arial','',10);

$pdf->Cell(40,6,convertir('Nota N°:'),0,0);
$pdf->Cell(60,6,convertir($nota['nota_nro']),0,0);
$pdf->Cell(40,6,convertir('Tipo:'),0,0);
$pdf->Cell(50,6,convertir($nota['nota_compra_tipo']),0,1);

$pdf->Cell(40,6,convertir('Fecha:'),0,0);
$pdf->Cell(60,6,convertir($nota['nota_compra_fecha']),0,0);
$pdf->Cell(40,6,convertir('Estado:'),0,0);
$pdf->Cell(50,6,convertir($nota['nota_compra_estado']),0,1);

$pdf->Cell(40,6,convertir('Emisión:'),0,0);
$pdf->Cell(60,6,convertir($nota['nota_compra_emision']),0,0);
$pdf->Cell(40,6,convertir('Vencimiento:'),0,0);
$pdf->Cell(50,6,convertir($nota['nota_compra_vencimiento']),0,1);

$pdf->Cell(40,6,convertir('Proveedor:'),0,0);
$pdf->Cell(60,6,convertir($nota['razon_social']),0,0);
$pdf->Cell(40,6,convertir('RUC:'),0,0);
$pdf->Cell(50,6,convertir($nota['ruc_proveedor'] ?? ''),0,1);

$pdf->Cell(40,6,convertir('Motivo:'),0,0);
$pdf->Cell(60,6,convertir($nota['motivo_descripcion'] ?? '—'),0,0);
$pdf->Cell(40,6,convertir('Usuario:'),0,0);
$pdf->Cell(50,6,convertir($nota['username']),0,1);

$pdf->Cell(40,6,convertir('Factura origen:'),0,0);
$pdf->Cell(60,6,convertir($nota['numero_factura'] ?? '—'),0,1);

$pdf->Ln(4);

$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(200,220,255);
$pdf->Cell(70,8,convertir('Producto'),1,0,'C',true);
$pdf->Cell(20,8,convertir('Cantidad'),1,0,'C',true);
$pdf->Cell(30,8,convertir('Precio'),1,0,'C',true);
$pdf->Cell(30,8,convertir('Subtotal'),1,0,'C',true);
$pdf->Cell(30,8,convertir('IVA'),1,1,'C',true);

$pdf->SetFont('Arial','',9);
foreach ($detalles as $det) {
    $pdf->Cell(70,8,convertir($det['materia_prima_descripcion']),1,0,'L');
    $pdf->Cell(20,8,$det['cantidad'],1,0,'C');
    $pdf->Cell(30,8,fmt_gs($det['precio']),1,0,'R');
    $pdf->Cell(30,8,fmt_gs($det['subtotal']),1,0,'R');
    $pdf->Cell(30,8,convertir($det['tipo_iva'].'%'),1,1,'C');
}

$pdf->Ln(4);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(40,6,convertir('Subtotal productos:'),0,0,'L');
$pdf->SetFont('Arial','',10);
$pdf->Cell(50,6,fmt_gs($totalSub),0,1,'L');
$pdf->SetFont('Arial','B',10);
$pdf->Cell(40,6,convertir('Total Nota:'),0,0,'L');
$pdf->SetFont('Arial','',10);
$pdf->Cell(50,6,fmt_gs($nota['nota_total']),0,1,'L');

$pdf->Ln(2);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(40,6,convertir('IVA 5%:'),0,0);
$pdf->SetFont('Arial','',10);
$pdf->Cell(50,6,fmt_gs($totIva['5']),0,1);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(40,6,convertir('IVA 10%:'),0,0);
$pdf->SetFont('Arial','',10);
$pdf->Cell(50,6,fmt_gs($totIva['10']),0,1);

// Nota: El campo 'descripcion' no existe en la tabla nota_compra según el esquema actual
// Si se necesita mostrar descripción, debería agregarse a la tabla o obtenerse de otra fuente

$pdf->Output('I', "NotaCompra_{$notaId}.pdf");
