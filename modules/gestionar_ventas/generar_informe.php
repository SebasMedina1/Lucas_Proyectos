<?php
/**
 * Generar Informe de Facturas de Venta
 * 
 * Genera un informe PDF de Facturas de Venta filtrado por estado y período.
 * 
 * Parámetros GET:
 * - estado: Estado de la factura (EMITIDA, ANULADA, PAGADA, TOTAL)
 * - desde: Fecha inicio (YYYY-MM-DD)
 * - hasta: Fecha fin (YYYY-MM-DD)
 */

require_once '../../config/database.php';
require_once '../../reporte/reporte_compras.php'; // BasePDF

// Validación de parámetros
$desde = isset($_GET['desde']) ? trim($_GET['desde']) : date('Y-m-01');
$hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : date('Y-m-d');
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';

// Validar formato de fechas
$reFecha = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($reFecha, $desde) || !preg_match($reFecha, $hasta)) {
    die('Formato de fecha inválido. Use YYYY-MM-DD.');
}
if ($hasta < $desde) {
    die('La fecha "Hasta" no puede ser anterior a "Desde".');
}

// Determinar si es reporte total (debe hacerse ANTES de usar $esTotal)
$estadoUpper = mb_strtoupper(trim($estado));
$esTotal = in_array($estadoUpper, ['REPORTE TOTAL', 'TOTAL'], true);

$T = fn($s) => iconv('UTF-8','ISO-8859-1//TRANSLIT', (string)$s);

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Título del informe
    $titulo = $esTotal ? 'INFORME DE FACTURAS DE VENTA - TODOS LOS ESTADOS'
                       : 'INFORME DE FACTURAS DE VENTA - ESTADO: ' . $estado;

    $pdf = new BasePDF('P', 'mm', 'A4', ['titulo' => $titulo]);
    $pdf->AddPage();
    
    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(0, 10, $T($titulo), 0, 1, 'C');
    $pdf->SetFont('Arial','',10);
    $pdf->Cell(0, 6, $T('Período: ' . $desde . ' al ' . $hasta), 0, 1, 'C');
    $pdf->Ln(5);

    // Consulta SQL base
    $sql = "
        SELECT 
            fv.id_factura_venta,
            COALESCE(fv.numero_factura, fv.factura_numero, 'N/A') AS numero_factura,
            COALESCE(fv.fecha_emision, fv.fecha_factura) AS fecha_factura,
            COALESCE(fv.tipo_factura, 'CONTADO') AS tipo_factura,
            COALESCE(fv.estado, fv.factura_estado, 'PENDIENTE') AS estado,
            COALESCE(fv.total_general, fv.factura_total, 0) AS total_general,
            c.cliente_nombre || ' ' || c.cliente_apellido AS cliente_nombre,
            u.username AS usuario
        FROM factura_ventas fv
        JOIN clientes c ON c.id_cliente = fv.id_cliente
        JOIN usuarios u ON u.id_usuario = fv.id_usuario
        WHERE COALESCE(fv.fecha_emision, fv.fecha_factura) BETWEEN :desde AND :hasta
    ";

    $params = [':desde' => $desde, ':hasta' => $hasta];
    
    // Filtrar por estado si no es TOTAL
    if (!empty($estado) && !$esTotal) {
        // Verificar si es PAGADA (estado especial que puede estar en estado o factura_estado)
        if ($estadoUpper === 'PAGADA') {
            // PAGADA puede estar en estado o puede ser una factura EMITIDA completamente pagada
            $sql .= " AND (
                UPPER(fv.estado) = 'PAGADA' 
                OR UPPER(fv.factura_estado) = 'PAGADA'
            )";
        } else {
            // Para otros estados, verificar en ambas columnas
            $sql .= " AND (
                UPPER(fv.estado) = :estado 
                OR UPPER(fv.factura_estado) = :estado
            )";
            $params[':estado'] = $estadoUpper;
        }
    }
    
    $sql .= " ORDER BY COALESCE(fv.fecha_emision, fv.fecha_factura) DESC, fv.id_factura_venta DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $facturas = $stmt->fetchAll();

    if (empty($facturas)) {
        $pdf->SetFont('Arial','',12);
        $pdf->Cell(0, 10, $T('No se encontraron facturas en el período seleccionado.'), 0, 1, 'C');
        $pdf->Output('I', 'informe_facturas_venta.pdf');
        exit;
    }

    // Encabezados de tabla
    $pdf->SetFont('Arial','B',9);
    $pdf->SetFillColor(200, 220, 255);
    $pdf->Cell(20, 8, $T('N° Fact.'), 1, 0, 'C', true);
    $pdf->Cell(25, 8, $T('Fecha'), 1, 0, 'C', true);
    $pdf->Cell(50, 8, $T('Cliente'), 1, 0, 'L', true);
    $pdf->Cell(25, 8, $T('Tipo'), 1, 0, 'C', true);
    $pdf->Cell(25, 8, $T('Estado'), 1, 0, 'C', true);
    $pdf->Cell(35, 8, $T('Total'), 1, 1, 'R', true);

    // Filas de datos
    $pdf->SetFont('Arial','',8);
    $totalGeneral = 0;
    foreach ($facturas as $fac) {
        $pdf->Cell(20, 6, $T($fac['numero_factura']), 1, 0, 'C');
        $pdf->Cell(25, 6, date('d/m/Y', strtotime($fac['fecha_factura'])), 1, 0, 'C');
        $pdf->Cell(50, 6, $T(substr($fac['cliente_nombre'], 0, 30)), 1, 0, 'L');
        $pdf->Cell(25, 6, $T($fac['tipo_factura']), 1, 0, 'C');
        $pdf->Cell(25, 6, $T($fac['estado']), 1, 0, 'C');
        $pdf->Cell(35, 6, number_format($fac['total_general'], 0, ',', '.'), 1, 1, 'R');
        $totalGeneral += $fac['total_general'];
    }

    // Total general
    $pdf->Ln(3);
    $pdf->SetFont('Arial','B',10);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell(145, 8, $T('TOTAL GENERAL:'), 1, 0, 'R', true);
    $pdf->Cell(35, 8, number_format($totalGeneral, 0, ',', '.'), 1, 1, 'R', true);

    $pdf->Output('I', 'informe_facturas_venta.pdf');
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
