<?php
require_once '../../config/database.php';
require_once '../../reporte/reporte_ajustes.php';

if (!function_exists('convertir')) {
    function convertir($texto) {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$texto);
    }
}

if (!function_exists('inferTipoDesdeDescripcion')) {
    function inferTipoDesdeDescripcion(string $descripcion): string {
        $texto = strtoupper(trim($descripcion));
        if (strpos($texto, 'SOBRANTE') !== false) {
            return 'ENTRADA';
        }
        if (strpos($texto, 'FALTANTE') !== false || strpos($texto, 'MERMA') !== false) {
            return 'SALIDA';
        }
        // Regularización puede ser entrada o salida, por defecto salida
        if (strpos($texto, 'REGULARIZACI') !== false) {
            return 'SALIDA'; // Se puede ajustar según el contexto
        }
        return 'SALIDA';
    }
}

$ajusteId = isset($_GET['ajuste_id']) ? (int)$_GET['ajuste_id'] : 0;
if ($ajusteId <= 0) {
    die("Identificador de ajuste inválido.");
}

$dsn = "pgsql:host=$host;port=$port;dbname=$database;";
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$sqlCabecera = "
    SELECT 
        a.id_ajuste,
        a.ajuste_fecha,
        a.ajuste_estado,
        u.username AS usuario,
        COALESCE(s.descripcion_sucursal, 'S/D') AS sucursal,
        d.deposito_descri AS deposito,
        m.motivo_descripcion AS motivo
    FROM ajustes a
    JOIN usuarios u        ON u.id_usuario   = a.id_usuario
    LEFT JOIN sucursales s ON s.id_sucursal  = u.id_sucursal
    JOIN deposito d        ON d.deposito_id  = a.deposito_id
    LEFT JOIN ajustes_detalle ad ON ad.id_ajuste = a.id_ajuste
    LEFT JOIN motivo m     ON m.id_motivo = ad.id_motivo
    WHERE a.id_ajuste = :id
    LIMIT 1
";

$stmtCab = $pdo->prepare($sqlCabecera);
$stmtCab->execute([':id' => $ajusteId]);
$cabecera = $stmtCab->fetch();

if (!$cabecera) {
    die("No se encontró el ajuste solicitado.");
}

$stmtDet = $pdo->prepare("
    SELECT 
        mp.materia_prima_descripcion AS producto,
        ad.ajuste_cantidad           AS cantidad,
        smp.cantidad_existente       AS stock_actual
    FROM ajustes_detalle ad
    JOIN materia_prima mp ON mp.id_materia_prima = ad.id_materia_prima
    LEFT JOIN stock_materia_prima smp ON smp.id_stock = ad.id_stock
    WHERE ad.id_ajuste = :id
    ORDER BY mp.materia_prima_descripcion ASC
");
$stmtDet->execute([':id' => $ajusteId]);
$detalle = $stmtDet->fetchAll();

$pdf = new BasePDF();
$pdf->AddPage();
$pdf->SetFont('Arial','',11);

$pdf->SetFillColor(245,245,245);
$pdf->Cell(40,8,convertir('Usuario:'),0,0,'L');
$pdf->Cell(60,8,convertir($cabecera['usuario']),0,0,'L');
$pdf->Cell(35,8,convertir('Fecha:'),0,0,'L');
$pdf->Cell(40,8,convertir($cabecera['ajuste_fecha']),0,1,'L');

$pdf->Cell(40,8,convertir('Sucursal:'),0,0,'L');
$pdf->Cell(60,8,convertir($cabecera['sucursal']),0,0,'L');
$pdf->Cell(35,8,convertir('Depósito:'),0,0,'L');
$pdf->Cell(40,8,convertir($cabecera['deposito']),0,1,'L');

$tipoReposo = inferTipoDesdeDescripcion($cabecera['motivo']);
$pdf->Cell(40,8,convertir('Motivo:'),0,0,'L');
$pdf->Cell(60,8,convertir($cabecera['motivo']),0,0,'L');
$pdf->Cell(35,8,convertir('Tipo:'),0,0,'L');
$pdf->Cell(40,8,convertir($tipoReposo),0,1,'L');
$pdf->Cell(40,8,convertir('Estado:'),0,0,'L');
$pdf->Cell(60,8,convertir($cabecera['ajuste_estado']),0,1,'L');

$pdf->Ln(8);
$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(39,42,87);
$pdf->SetTextColor(255,255,255);
$pdf->Cell(120,8,convertir('Detalle del Ajuste'),0,1,'L',true);
$pdf->Ln(2);

$pdf->SetTextColor(0,0,0);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(10,8,convertir('#'),1,0,'C');
$pdf->Cell(100,8,convertir('Materia Prima'),1,0,'L');
$pdf->Cell(30,8,convertir('Cantidad'),1,0,'C');
$pdf->Cell(30,8,convertir('Stock Actual'),1,1,'C');

$pdf->SetFont('Arial','',10);
if (empty($detalle)) {
    $pdf->Cell(170,8,convertir('No existen ítems cargados para este ajuste.'),1,1,'C');
} else {
    $contador = 1;
    foreach ($detalle as $row) {
        $pdf->Cell(10,8,$contador++,1,0,'C');
        $pdf->Cell(100,8,convertir($row['producto']),1,0,'L');
        $pdf->Cell(30,8,number_format((int)$row['cantidad'], 0, ',', '.'),1,0,'C');
        $pdf->Cell(30,8,number_format((int)($row['stock_actual'] ?? 0), 0, ',', '.'),1,1,'C');
    }
}

$pdf->Ln(6);
$totalItems = count($detalle);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(170,7,convertir("Total de ítems: {$totalItems}"),0,1,'R');

$pdf->Output('I', 'Ajuste_'.$ajusteId.'.pdf');
