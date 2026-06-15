<?php
session_start();
require '../../config/database.php';
require_once '../../reporte/pedido_ventas.php'; // Clase BasePDF

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
    
    $pdf = new BasePDF('P', 'mm', 'A4', ['titulo' => 'Libro de Ventas']);
    $pdf->AddPage();
    
    // Encabezado
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, convertir('LIBRO DE VENTAS'), 0, 1, 'C');
    $pdf->Ln(3);
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'Período: ' . date('d/m/Y', strtotime($desde)) . ' al ' . date('d/m/Y', strtotime($hasta)), 0, 1, 'C');
    
    // Mostrar filtros aplicados si existen
    if ($clienteId !== null || $tipoDoc !== null || $busqueda !== null) {
        $pdf->SetFont('Arial', 'I', 8);
        $filtros = [];
        if ($clienteId !== null) {
            $qCliente = $pdo->prepare("SELECT cliente_nombre || ' ' || cliente_apellido AS nombre FROM clientes WHERE id_cliente = :id");
            $qCliente->execute([':id' => $clienteId]);
            $cliente = $qCliente->fetch();
            if ($cliente) $filtros[] = 'Cliente: ' . $cliente['nombre'];
        }
        if ($tipoDoc !== null) {
            $tipos = ['FACTURA' => 'Factura', 'NOTA_CREDITO' => 'Nota de Crédito', 'NOTA_DEBITO' => 'Nota de Débito'];
            $filtros[] = 'Tipo: ' . ($tipos[$tipoDoc] ?? $tipoDoc);
        }
        if ($busqueda !== null) {
            $filtros[] = 'Búsqueda: ' . $busqueda;
        }
        if (!empty($filtros)) {
            $pdf->Cell(0, 5, 'Filtros: ' . implode(' | ', $filtros), 0, 1, 'C');
        }
    }
    
    $pdf->Ln(3);
    
    // Tabla
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(200, 220, 255);
    
    // Encabezados de columna (ajustar anchos para incluir Estado)
    $pdf->Cell(18, 8, convertir('Fecha'), 1, 0, 'C', true);
    $pdf->Cell(15, 8, convertir('Tipo'), 1, 0, 'C', true);
    $pdf->Cell(28, 8, convertir('N° Doc.'), 1, 0, 'C', true);
    $pdf->Cell(22, 8, convertir('Timbrado'), 1, 0, 'C', true);
    $pdf->Cell(45, 8, convertir('Cliente'), 1, 0, 'C', true);
    $pdf->Cell(22, 8, convertir('RUC'), 1, 0, 'C', true);
    $pdf->Cell(22, 8, convertir('Exento'), 1, 0, 'C', true);
    $pdf->Cell(22, 8, convertir('Base 5%'), 1, 0, 'C', true);
    $pdf->Cell(18, 8, convertir('IVA 5%'), 1, 0, 'C', true);
    $pdf->Cell(22, 8, convertir('Base 10%'), 1, 0, 'C', true);
    $pdf->Cell(18, 8, convertir('IVA 10%'), 1, 0, 'C', true);
    $pdf->Cell(25, 8, convertir('Total'), 1, 0, 'C', true);
    $pdf->Cell(18, 8, convertir('Estado'), 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', '', 7);
    $altura = 6;
    $yInicial = $pdf->GetY();
    $maxY = 270; // Altura máxima antes de nueva página
    
    foreach ($documentos as $doc) {
        // Verificar si necesitamos nueva página
        if ($pdf->GetY() > $maxY) {
            $pdf->AddPage();
            // Reimprimir encabezados de columna
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetFillColor(200, 220, 255);
            $pdf->Cell(18, 8, convertir('Fecha'), 1, 0, 'C', true);
            $pdf->Cell(15, 8, convertir('Tipo'), 1, 0, 'C', true);
            $pdf->Cell(28, 8, convertir('N° Doc.'), 1, 0, 'C', true);
            $pdf->Cell(22, 8, convertir('Timbrado'), 1, 0, 'C', true);
            $pdf->Cell(45, 8, convertir('Cliente'), 1, 0, 'C', true);
            $pdf->Cell(22, 8, convertir('RUC'), 1, 0, 'C', true);
            $pdf->Cell(22, 8, convertir('Exento'), 1, 0, 'C', true);
            $pdf->Cell(22, 8, convertir('Base 5%'), 1, 0, 'C', true);
            $pdf->Cell(18, 8, convertir('IVA 5%'), 1, 0, 'C', true);
            $pdf->Cell(22, 8, convertir('Base 10%'), 1, 0, 'C', true);
            $pdf->Cell(18, 8, convertir('IVA 10%'), 1, 0, 'C', true);
            $pdf->Cell(25, 8, convertir('Total'), 1, 0, 'C', true);
            $pdf->Cell(18, 8, convertir('Estado'), 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 7);
        }
        $fechaFormateada = date('d/m/Y', strtotime($doc['fecha']));
        $tipoLabel = $doc['tipo'] === 'FACTURA' ? 'F' : ($doc['tipo'] === 'NOTA_CREDITO' ? 'NC' : 'ND');
        $signo = $doc['signo'] > 0 ? '' : '-';
        $estadoDoc = strtoupper(trim($doc['estado'] ?? 'EMITIDA'));
        $estadoLabel = ($estadoDoc === 'ANULADA' || $estadoDoc === 'ANULADO') ? 'ANUL' : 'VIG';
        
        $pdf->Cell(18, $altura, $fechaFormateada, 1, 0, 'C');
        $pdf->Cell(15, $altura, $tipoLabel, 1, 0, 'C');
        $pdf->Cell(28, $altura, convertir($doc['numero']), 1, 0, 'C');
        $pdf->Cell(22, $altura, $doc['timbrado'], 1, 0, 'C');
        $pdf->Cell(45, $altura, convertir(substr($doc['cliente'], 0, 25)), 1, 0, 'L');
        $pdf->Cell(22, $altura, $doc['ruc'], 1, 0, 'C');
        $pdf->Cell(22, $altura, $signo . number_format($doc['exento'] * $doc['signo'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(22, $altura, $signo . number_format($doc['base_5'] * $doc['signo'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(18, $altura, $signo . number_format($doc['iva_5'] * $doc['signo'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(22, $altura, $signo . number_format($doc['base_10'] * $doc['signo'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(18, $altura, $signo . number_format($doc['iva_10'] * $doc['signo'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(25, $altura, $signo . number_format($doc['total'] * $doc['signo'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(18, $altura, convertir($estadoLabel), 1, 1, 'C');
    }
    
    // Totales
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(150, $altura, 'TOTALES:', 1, 0, 'R', true);
    $pdf->Cell(22, $altura, number_format($totalExento, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell(22, $altura, number_format($totalBase5, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell(18, $altura, number_format($totalIva5, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell(22, $altura, number_format($totalBase10, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell(18, $altura, number_format($totalIva10, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell(25, $altura, number_format($totalGeneral, 0, ',', '.'), 1, 0, 'R', true);
    $pdf->Cell(18, $altura, '', 1, 1, 'C', true);
    
    $pdf->Output('libro_ventas_' . str_replace('-', '', $desde) . '_' . str_replace('-', '', $hasta) . '.pdf', 'D');
    
} catch (PDOException $e) {
    die("Error al generar el PDF: " . $e->getMessage());
}

