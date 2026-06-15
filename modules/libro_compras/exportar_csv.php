<?php
session_start();
require '../../config/database.php';

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
    
    $filename = sprintf('libro_compras_%s_%s.csv', str_replace('-','',$desde), str_replace('-','',$hasta));
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    
    $output = fopen('php://output', 'w');
    
    // BOM para Excel
    fprintf($output, "\xEF\xBB\xBF");
    
    // Encabezado
    fputcsv($output, ['LIBRO DE COMPRAS (IVA COMPRAS)'], ';');
    fputcsv($output, ['Período: ' . date('d/m/Y', strtotime($desde)) . ' al ' . date('d/m/Y', strtotime($hasta))], ';');
    fputcsv($output, []); // Línea vacía
    
    // Encabezados de columna
    fputcsv($output, [
        'Fecha',
        'Tipo',
        'Número',
        'Timbrado',
        'RUC',
        'Razón Social',
        'Condición',
        'Exento',
        'Gravado 5%',
        'Gravado 10%',
        'IVA 5%',
        'IVA 10%',
        'Total'
    ], ';');
    
    // Datos
    foreach ($documentos as $doc) {
        $tipoLabel = $doc['tipo'] === 'FACTURA' ? 'Factura' : 
                     (($doc['tipo'] === 'NOTA_CREDITO') ? 'Nota de Crédito' : 'Nota de Débito');
        $signo = $doc['signo'] > 0 ? '' : '-';
        
        fputcsv($output, [
            $doc['fecha'],
            $tipoLabel,
            $doc['numero'],
            $doc['timbrado'],
            $doc['ruc'],
            $doc['razon'],
            $doc['condicion'],
            $signo . number_format($doc['exento'], 0, ',', '.'),
            $signo . number_format($doc['base_5'], 0, ',', '.'),
            $signo . number_format($doc['base_10'], 0, ',', '.'),
            $signo . number_format($doc['iva_5'], 0, ',', '.'),
            $signo . number_format($doc['iva_10'], 0, ',', '.'),
            $signo . number_format($doc['total'], 0, ',', '.')
        ], ';');
    }
    
    // Totales
    fputcsv($output, []); // Línea vacía
    fputcsv($output, [
        'TOTALES',
        '',
        '',
        '',
        '',
        '',
        '',
        number_format($totalExento, 0, ',', '.'),
        number_format($totalBase5, 0, ',', '.'),
        number_format($totalBase10, 0, ',', '.'),
        number_format($totalIva5, 0, ',', '.'),
        number_format($totalIva10, 0, ',', '.'),
        number_format($totalGeneral, 0, ',', '.')
    ], ';');
    
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    die("Error al generar el CSV: " . $e->getMessage());
}

