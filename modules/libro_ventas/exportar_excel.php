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
    
    $clienteId = isset($_GET['cliente_id']) && $_GET['cliente_id'] !== '' ? (int)$_GET['cliente_id'] : null;
    $tipoDoc = isset($_GET['tipo_documento']) && $_GET['tipo_documento'] !== '' ? $_GET['tipo_documento'] : null;
    $busqueda = isset($_GET['busqueda']) && $_GET['busqueda'] !== '' ? trim($_GET['busqueda']) : null;
    $timbradoId = isset($_GET['timbrado_id']) && $_GET['timbrado_id'] !== '' ? (int)$_GET['timbrado_id'] : null;
    
    require 'consolidar_documentos.php';
    $documentos = consolidarDocumentos($pdo, $desde, $hasta, $clienteId, $tipoDoc, $busqueda, $timbradoId);
    
    // Calcular totales
    $totalExento = 0;
    $totalBase5 = 0;
    $totalIva5 = 0;
    $totalBase10 = 0;
    $totalIva10 = 0;
    $totalGeneral = 0;
    
    foreach ($documentos as $doc) {
        $totalExento += $doc['exento'] * $doc['signo'];
        $totalBase5 += $doc['base_5'] * $doc['signo'];
        $totalIva5 += $doc['iva_5'] * $doc['signo'];
        $totalBase10 += $doc['base_10'] * $doc['signo'];
        $totalIva10 += $doc['iva_10'] * $doc['signo'];
        $totalGeneral += $doc['total'] * $doc['signo'];
    }
    
    $filename = sprintf('libro_ventas_%s_%s.xls', str_replace('-','',$desde), str_replace('-','',$hasta));
    
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo "\xEF\xBB\xBF";
    
    // Encabezado del documento
    echo "<html><head><meta charset='UTF-8'></head><body>";
    echo "<h2>LIBRO DE VENTAS</h2>";
    echo "<p><strong>Período:</strong> " . date('d/m/Y', strtotime($desde)) . " al " . date('d/m/Y', strtotime($hasta)) . "</p>";
    
    // Mostrar filtros si existen
    if ($clienteId !== null || $tipoDoc !== null || $busqueda !== null) {
        echo "<p><strong>Filtros aplicados:</strong> ";
        $filtros = [];
        if ($clienteId !== null) {
            $qCliente = $pdo->prepare("SELECT cliente_nombre || ' ' || cliente_apellido AS nombre FROM clientes WHERE id_cliente = :id");
            $qCliente->execute([':id' => $clienteId]);
            $cliente = $qCliente->fetch();
            if ($cliente) $filtros[] = 'Cliente: ' . htmlspecialchars($cliente['nombre']);
        }
        if ($tipoDoc !== null) {
            $tipos = ['FACTURA' => 'Factura', 'NOTA_CREDITO' => 'Nota de Crédito', 'NOTA_DEBITO' => 'Nota de Débito'];
            $filtros[] = 'Tipo: ' . ($tipos[$tipoDoc] ?? $tipoDoc);
        }
        if ($busqueda !== null) {
            $filtros[] = 'Búsqueda: ' . htmlspecialchars($busqueda);
        }
        echo implode(' | ', $filtros) . "</p>";
    }
    
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    
    $headers = ['Fecha','Tipo','N° Documento','Timbrado','Cliente','RUC','Exento','Base 5%','IVA 5%','Base 10%','IVA 10%','Total','Estado'];
    
    $esc = static function ($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    };
    
    echo "<table border=\"1\">\n<thead><tr>";
    foreach ($headers as $title) {
        echo '<th>'.$esc($title).'</th>';
    }
    echo "</tr></thead>\n<tbody>\n";
    
    foreach ($documentos as $doc) {
        $fechaFormateada = date('d/m/Y', strtotime($doc['fecha']));
        $tipoLabel = $doc['tipo'] === 'FACTURA' ? 'Factura' : 
                    ($doc['tipo'] === 'NOTA_CREDITO' ? 'NC' : 'ND');
        $signo = $doc['signo'] > 0 ? '' : '-';
        $estadoDoc = strtoupper(trim($doc['estado'] ?? 'EMITIDA'));
        $estadoLabel = ($estadoDoc === 'ANULADA' || $estadoDoc === 'ANULADO') ? 'ANULADO' : 'VIGENTE';
        
        echo "<tr>";
        echo '<td>'.$esc($fechaFormateada).'</td>';
        echo '<td>'.$esc($tipoLabel).'</td>';
        echo '<td>'.$esc($doc['numero']).'</td>';
        echo '<td>'.$esc($doc['timbrado']).'</td>';
        echo '<td>'.$esc($doc['cliente']).'</td>';
        echo '<td>'.$esc($doc['ruc']).'</td>';
        echo '<td align="right">'.$signo.$esc(number_format($doc['exento'] * $doc['signo'], 0, ',', '.')).'</td>';
        echo '<td align="right">'.$signo.$esc(number_format($doc['base_5'] * $doc['signo'], 0, ',', '.')).'</td>';
        echo '<td align="right">'.$signo.$esc(number_format($doc['iva_5'] * $doc['signo'], 0, ',', '.')).'</td>';
        echo '<td align="right">'.$signo.$esc(number_format($doc['base_10'] * $doc['signo'], 0, ',', '.')).'</td>';
        echo '<td align="right">'.$signo.$esc(number_format($doc['iva_10'] * $doc['signo'], 0, ',', '.')).'</td>';
        echo '<td align="right">'.$signo.$esc(number_format($doc['total'] * $doc['signo'], 0, ',', '.')).'</td>';
        echo '<td>'.$esc($estadoLabel).'</td>';
        echo "</tr>\n";
    }
    
    echo "<tr>";
    echo '<td colspan="6"><strong>TOTALES</strong></td>';
    echo '<td align="right"><strong>'.$esc(number_format($totalExento, 0, ',', '.')).'</strong></td>';
    echo '<td align="right"><strong>'.$esc(number_format($totalBase5, 0, ',', '.')).'</strong></td>';
    echo '<td align="right"><strong>'.$esc(number_format($totalIva5, 0, ',', '.')).'</strong></td>';
    echo '<td align="right"><strong>'.$esc(number_format($totalBase10, 0, ',', '.')).'</strong></td>';
    echo '<td align="right"><strong>'.$esc(number_format($totalIva10, 0, ',', '.')).'</strong></td>';
    echo '<td align="right"><strong>'.$esc(number_format($totalGeneral, 0, ',', '.')).'</strong></td>';
    echo '<td></td>';
    echo "</tr>\n";
    
    echo "</tbody></table>";
    echo "</body></html>";
    exit;
    
} catch (PDOException $e) {
    die("Error al generar el Excel: " . $e->getMessage());
}

