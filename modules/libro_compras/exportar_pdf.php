<?php
session_start();
require '../../config/database.php';
require_once '../../reporte/reporte_compras.php'; // Clase BasePDF

if (!function_exists('convertir')) {
    function convertir($texto) {
        return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
    }
}

$desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';

$reFecha = '/^\d{4}-\d{2}-\d{2}$/';
if ($desde === '' || $hasta === '' || !preg_match($reFecha, $desde) || !preg_match($reFecha, $hasta)) {
    die('El rango de fechas es obligatorio y debe tener el formato YYYY-MM-DD.');
}
if ($hasta < $desde) {
    die('La fecha "Hasta" no puede ser anterior a "Desde".');
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $proveedorId = isset($_GET['proveedor']) && $_GET['proveedor'] !== '' && $_GET['proveedor'] !== '0' ? (int)$_GET['proveedor'] : null;
    $tipoDoc = isset($_GET['tipo']) && $_GET['tipo'] !== '' ? trim($_GET['tipo']) : null;
    $sucursalId = isset($_GET['sucursal']) && $_GET['sucursal'] !== '' && $_GET['sucursal'] !== '0' ? (int)$_GET['sucursal'] : null;
    
    require 'consolidar_documentos.php';
    $documentos = consolidarDocumentos($pdo, $desde, $hasta, $proveedorId, $tipoDoc, $sucursalId);
    
    // Calcular totales
    $totalExento = 0;
    $totalBase5 = 0;
    $totalIva5 = 0;
    $totalBase10 = 0;
    $totalIva10 = 0;
    $totalGeneral = 0;
    
    foreach ($documentos as $doc) {
        $totalExento += $doc['exento'];
        $totalBase5 += $doc['base_5'];
        $totalIva5 += $doc['iva_5'];
        $totalBase10 += $doc['base_10'];
        $totalIva10 += $doc['iva_10'];
        $totalGeneral += $doc['total'];
    }
    
    $pdf = new BasePDF('P', 'mm', 'A4', ['titulo' => 'Libro de Compras (IVA Compras)']);
    $pdf->AddPage();
    
    // Encabezado
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, convertir('LIBRO DE COMPRAS (IVA COMPRAS)'), 0, 1, 'C');
    $pdf->Ln(3);
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'Período: ' . date('d/m/Y', strtotime($desde)) . ' al ' . date('d/m/Y', strtotime($hasta)), 0, 1, 'C');
    
    // Mostrar filtros aplicados si existen
    if ($proveedorId !== null || $tipoDoc !== null || $sucursalId !== null) {
        $pdf->SetFont('Arial', 'I', 8);
        $filtros = [];
        if ($proveedorId !== null) {
            $qProv = $pdo->prepare("SELECT razon_social FROM proveedor WHERE id_proveedor = :id");
            $qProv->execute([':id' => $proveedorId]);
            $prov = $qProv->fetch();
            if ($prov) $filtros[] = 'Proveedor: ' . $prov['razon_social'];
        }
        if ($tipoDoc !== null) {
            $tipos = ['FACTURA' => 'Factura', 'CREDITO' => 'Nota de Crédito', 'DEBITO' => 'Nota de Débito'];
            $filtros[] = 'Tipo: ' . ($tipos[$tipoDoc] ?? $tipoDoc);
        }
        if ($sucursalId !== null) {
            $qSuc = $pdo->prepare("SELECT descripcion_sucursal FROM sucursales WHERE id_sucursal = :id");
            $qSuc->execute([':id' => $sucursalId]);
            $suc = $qSuc->fetch();
            if ($suc) $filtros[] = 'Sucursal: ' . $suc['descripcion_sucursal'];
        }
        if (!empty($filtros)) {
            $pdf->Cell(0, 5, 'Filtros: ' . implode(' | ', $filtros), 0, 1, 'C');
        }
    }
    
    $pdf->Ln(3);
    
    // Tabla
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(200, 220, 255);
    
    // Encabezados de columna (ajustados para compras)
    $pdf->Cell(18, 8, convertir('Fecha'), 1, 0, 'C', true);
    $pdf->Cell(18, 8, convertir('Tipo'), 1, 0, 'C', true);
    $pdf->Cell(25, 8, convertir('N° Doc.'), 1, 0, 'C', true);
    $pdf->Cell(20, 8, convertir('Timbrado'), 1, 0, 'C', true);
    $pdf->Cell(15, 8, convertir('RUC'), 1, 0, 'C', true);
    $pdf->Cell(45, 8, convertir('Proveedor'), 1, 0, 'C', true);
    $pdf->Cell(15, 8, convertir('Cond.'), 1, 0, 'C', true);
    $pdf->Cell(20, 8, convertir('Exento'), 1, 0, 'C', true);
    $pdf->Cell(20, 8, convertir('Base 5%'), 1, 0, 'C', true);
    $pdf->Cell(15, 8, convertir('IVA 5%'), 1, 0, 'C', true);
    $pdf->Cell(20, 8, convertir('Base 10%'), 1, 0, 'C', true);
    $pdf->Cell(15, 8, convertir('IVA 10%'), 1, 0, 'C', true);
    $pdf->Cell(25, 8, convertir('Total'), 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', '', 6);
    $altura = 5;
    $maxY = 270;
    
    foreach ($documentos as $doc) {
        if ($pdf->GetY() > $maxY) {
            $pdf->AddPage();
            // Reimprimir encabezados
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->SetFillColor(200, 220, 255);
            $pdf->Cell(18, 8, convertir('Fecha'), 1, 0, 'C', true);
            $pdf->Cell(18, 8, convertir('Tipo'), 1, 0, 'C', true);
            $pdf->Cell(25, 8, convertir('N° Doc.'), 1, 0, 'C', true);
            $pdf->Cell(20, 8, convertir('Timbrado'), 1, 0, 'C', true);
            $pdf->Cell(15, 8, convertir('RUC'), 1, 0, 'C', true);
            $pdf->Cell(45, 8, convertir('Proveedor'), 1, 0, 'C', true);
            $pdf->Cell(15, 8, convertir('Cond.'), 1, 0, 'C', true);
            $pdf->Cell(20, 8, convertir('Exento'), 1, 0, 'C', true);
            $pdf->Cell(20, 8, convertir('Base 5%'), 1, 0, 'C', true);
            $pdf->Cell(15, 8, convertir('IVA 5%'), 1, 0, 'C', true);
            $pdf->Cell(20, 8, convertir('Base 10%'), 1, 0, 'C', true);
            $pdf->Cell(15, 8, convertir('IVA 10%'), 1, 0, 'C', true);
            $pdf->Cell(25, 8, convertir('Total'), 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 6);
        }
        
        $fechaFormateada = date('d/m/Y', strtotime($doc['fecha']));
        $tipoLabel = $doc['tipo'] === 'FACTURA' ? 'F' : ($doc['tipo'] === 'NOTA_CREDITO' ? 'NC' : 'ND');
        $signo = $doc['signo'] > 0 ? '' : '-';
        
        $pdf->Cell(18, $altura, $fechaFormateada, 1, 0, 'C');
        $pdf->Cell(18, $altura, $tipoLabel, 1, 0, 'C');
        $pdf->Cell(25, $altura, convertir($doc['numero']), 1, 0, 'C');
        $pdf->Cell(20, $altura, $doc['timbrado'], 1, 0, 'C');
        $pdf->Cell(15, $altura, $doc['ruc'], 1, 0, 'C');
        $pdf->Cell(45, $altura, convertir(substr($doc['razon'], 0, 25)), 1, 0, 'L');
        $pdf->Cell(15, $altura, convertir($doc['condicion']), 1, 0, 'C');
        $pdf->Cell(20, $altura, $signo . number_format($doc['exento'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(20, $altura, $signo . number_format($doc['base_5'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(15, $altura, $signo . number_format($doc['iva_5'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(20, $altura, $signo . number_format($doc['base_10'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(15, $altura, $signo . number_format($doc['iva_10'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(25, $altura, $signo . number_format($doc['total'], 0, ',', '.'), 1, 1, 'R');
    }
    
    // Totales
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->Cell(152, $altura, 'TOTALES:', 1, 0, 'R', true);
    $pdf->Cell(20, $altura, number_format($totalExento, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell(20, $altura, number_format($totalBase5, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell(15, $altura, number_format($totalIva5, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell(20, $altura, number_format($totalBase10, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell(15, $altura, number_format($totalIva10, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell(25, $altura, number_format($totalGeneral, 0, ',', '.'), 1, 1, 'R', true);
    
    $pdf->Output('libro_compras_' . str_replace('-', '', $desde) . '_' . str_replace('-', '', $hasta) . '.pdf', 'D');
    
} catch (PDOException $e) {
    die("Error al generar el PDF: " . $e->getMessage());
}

