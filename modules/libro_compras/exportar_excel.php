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
    
    $filename = sprintf('libro_compras_%s_%s.xls', str_replace('-','',$desde), str_replace('-','',$hasta));
    
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo "\xEF\xBB\xBF";
    
    $headers = ['Fecha','Tipo','Número','Timbrado','RUC','Razón Social','Condición','Exento','Gravado 5%','Gravado 10%','IVA 5%','IVA 10%','Total'];
    
    $esc = static function ($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    };
    
    echo "<table border=\"1\">\n";
    echo "<tr><td colspan=\"13\" align=\"center\"><strong>LIBRO DE COMPRAS (IVA COMPRAS)</strong></td></tr>\n";
    echo "<tr><td colspan=\"13\" align=\"center\">Período: " . date('d/m/Y', strtotime($desde)) . " al " . date('d/m/Y', strtotime($hasta)) . "</td></tr>\n";
    echo "<tr></tr>\n";
    echo "<thead><tr>";
    foreach ($headers as $title) {
        echo '<th style="background-color: #C8DCFF;">'.$esc($title).'</th>';
    }
    echo "</tr></thead>\n<tbody>\n";
    
    foreach ($documentos as $doc) {
        $tipoLabel = $doc['tipo'] === 'FACTURA' ? 'Factura' : 
                     (($doc['tipo'] === 'NOTA_CREDITO') ? 'Nota de Crédito' : 'Nota de Débito');
        $signo = $doc['signo'] > 0 ? '' : '-';
        
        echo "<tr>";
        echo '<td>'.$esc($doc['fecha']).'</td>';
        echo '<td>'.$esc($tipoLabel).'</td>';
        echo '<td>'.$esc($doc['numero']).'</td>';
        echo '<td>'.$esc($doc['timbrado']).'</td>';
        echo '<td>'.$esc($doc['ruc']).'</td>';
        echo '<td>'.$esc($doc['razon']).'</td>';
        echo '<td>'.$esc($doc['condicion']).'</td>';
        echo '<td align="right">'.$signo.$esc(number_format($doc['exento'], 0, ',', '.')).'</td>';
        echo '<td align="right">'.$signo.$esc(number_format($doc['base_5'], 0, ',', '.')).'</td>';
        echo '<td align="right">'.$signo.$esc(number_format($doc['base_10'], 0, ',', '.')).'</td>';
        echo '<td align="right">'.$signo.$esc(number_format($doc['iva_5'], 0, ',', '.')).'</td>';
        echo '<td align="right">'.$signo.$esc(number_format($doc['iva_10'], 0, ',', '.')).'</td>';
        echo '<td align="right">'.$signo.$esc(number_format($doc['total'], 0, ',', '.')).'</td>';
        echo "</tr>\n";
    }
    
    echo "<tr style=\"background-color: #E0E0E0; font-weight: bold;\">";
    echo '<td colspan="7" align="right">TOTALES</td>';
    echo '<td align="right">'.number_format($totalExento, 0, ',', '.').'</td>';
    echo '<td align="right">'.number_format($totalBase5, 0, ',', '.').'</td>';
    echo '<td align="right">'.number_format($totalBase10, 0, ',', '.').'</td>';
    echo '<td align="right">'.number_format($totalIva5, 0, ',', '.').'</td>';
    echo '<td align="right">'.number_format($totalIva10, 0, ',', '.').'</td>';
    echo '<td align="right">'.number_format($totalGeneral, 0, ',', '.').'</td>';
    echo "</tr>\n";
    
    echo "</tbody></table>";
    exit;
    
} catch (PDOException $e) {
    die("Error al generar el Excel: " . $e->getMessage());
}

