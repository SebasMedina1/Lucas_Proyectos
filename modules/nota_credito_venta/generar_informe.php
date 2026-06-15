<?php
/**
 * Generar Informe de Notas de Crédito Venta
 * 
 * Genera un informe PDF de Notas de Crédito de Venta filtrado por estado y período.
 * 
 * Parámetros GET:
 * - estado: Estado de la nota (EMITIDA, ANULADA, TOTAL)
 * - desde: Fecha inicio (YYYY-MM-DD)
 * - hasta: Fecha fin (YYYY-MM-DD)
 */

require_once '../../config/database.php';
require_once '../../reporte/pedido_ventas.php'; // BasePDF

// Validación del parámetro estado
if (!isset($_GET['estado']) || $_GET['estado'] === '') {
    echo "<script>
            alert('No se recibió un estado válido para generar el informe.');
            window.location.href = '../reportes/view.php';
          </script>";
    exit();
}

// Fechas obligatorias
$desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';

$reFecha = '/^\d{4}-\d{2}-\d{2}$/';
if ($desde === '' || $hasta === '' || !preg_match($reFecha, $desde) || !preg_match($reFecha, $hasta)) {
    echo "<script>
            alert('El rango de fechas es obligatorio y debe tener el formato YYYY-MM-DD.');
            window.location.href = '../reportes/view.php';
          </script>";
    exit();
}
if ($hasta < $desde) {
    echo "<script>
            alert('La fecha \"Hasta\" no puede ser anterior a \"Desde\".');
            window.location.href = '../reportes/view.php';
          </script>";
    exit();
}

$estado = trim($_GET['estado']);
$estadoUpper = mb_strtoupper($estado);
$esTotal = in_array($estadoUpper, ['REPORTE TOTAL', 'TOTAL'], true);

function convertir($texto) {
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

try {
    // Conexión PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // SQL base para notas de crédito venta
    $sql = "
        SELECT 
            nv.id_nota_venta,
            nv.nota_nro::TEXT AS numero_nota,
            COALESCE(nv.nota_venta_timbrado, '') AS timbrado,
            COALESCE(nv.nota_venta_emision, nv.nota_venta_fecha, nv.fecha_emision) AS fecha_emision,
            nv.nota_venta_estado AS estado,
            COALESCE(nv.nota_total, nv.monto_total, 0) AS total_nota,
            c.cliente_nombre || ' ' || c.cliente_apellido AS cliente_nombre,
            COALESCE(c.cliente_ruc, '') AS cliente_ruc,
            fv.numero_factura AS factura_referencia,
            u.username AS usuario,
            m.motivo_descripcion AS motivo
        FROM nota_venta nv
        JOIN clientes c ON c.id_cliente = nv.id_cliente
        LEFT JOIN factura_ventas fv ON fv.id_factura_venta = nv.id_factura_venta
        LEFT JOIN motivo m ON m.id_motivo = nv.id_motivo
        JOIN usuarios u ON u.id_usuario = nv.id_usuario
        WHERE nv.nota_venta_tipo = 'CREDITO'
          AND COALESCE(nv.nota_venta_emision, nv.nota_venta_fecha, nv.fecha_emision) BETWEEN :desde AND :hasta
    ";

    // Condición solo si NO es "REPORTE TOTAL"
    if (!$esTotal) {
        $sql .= " AND nv.nota_venta_estado = :estado";
    }

    $sql .= " ORDER BY COALESCE(nv.nota_venta_emision, nv.nota_venta_fecha, nv.fecha_emision) DESC, nv.id_nota_venta DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':desde', $desde, PDO::PARAM_STR);
    $stmt->bindParam(':hasta', $hasta, PDO::PARAM_STR);
    if (!$esTotal) {
        $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
    }
    $stmt->execute();

    $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($notas) === 0) {
        echo "<script>
                alert('No se encontraron notas de crédito para el rango de fechas y estado seleccionado.');
                window.location.href = '../reportes/view.php';
              </script>";
        exit();
    }

    // PDF
    $titulo = $esTotal ? 'INFORME DE NOTAS DE CRÉDITO VENTA - TODOS LOS ESTADOS'
                       : 'INFORME DE NOTAS DE CRÉDITO VENTA - ESTADO: ' . $estado;

    $pdf = new BasePDF('P', 'mm', 'A4', ['titulo' => $titulo]);
    $pdf->AddPage();

    // Información del rango de fechas
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, convertir('Período: ' . $desde . ' al ' . $hasta), 0, 1, 'L');
    $pdf->Ln(5);

    // Encabezado de tabla
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(200, 220, 255);

    $pdf->Cell(25, 9, convertir('N° Nota'), 1, 0, 'C', true);
    $pdf->Cell(20, 9, convertir('Timbrado'), 1, 0, 'C', true);
    $pdf->Cell(25, 9, convertir('Fecha'), 1, 0, 'C', true);
    $pdf->Cell(50, 9, convertir('Cliente'), 1, 0, 'C', true);
    $pdf->Cell(30, 9, convertir('Factura Ref.'), 1, 0, 'C', true);
    $pdf->Cell(30, 9, convertir('Motivo'), 1, 0, 'C', true);
    $pdf->Cell(30, 9, convertir('Total'), 1, 0, 'C', true);
    $pdf->Cell(20, 9, convertir('Estado'), 1, 1, 'C', true);

    // Filas
    $pdf->SetFont('Arial', '', 8);
    $totalGeneral = 0;
    foreach ($notas as $row) {
        $pdf->Cell(25, 7, convertir($row['numero_nota']), 1, 0, 'C');
        $pdf->Cell(20, 7, $row['timbrado'], 1, 0, 'C');
        $pdf->Cell(25, 7, date('d/m/Y', strtotime($row['fecha_emision'])), 1, 0, 'C');
        $pdf->Cell(50, 7, convertir(substr($row['cliente_nombre'], 0, 25)), 1, 0, 'L');
        $pdf->Cell(30, 7, convertir($row['factura_referencia'] ?? 'N/A'), 1, 0, 'C');
        $pdf->Cell(30, 7, convertir(substr($row['motivo'] ?? 'N/A', 0, 20)), 1, 0, 'L');
        $pdf->Cell(30, 7, number_format($row['total_nota'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(20, 7, convertir($row['estado']), 1, 1, 'C');
        $totalGeneral += $row['total_nota'];
    }

    // Total general
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell(180, 9, convertir('TOTAL GENERAL:'), 1, 0, 'R', true);
    $pdf->Cell(30, 9, number_format($totalGeneral, 0, ',', '.'), 1, 1, 'R', true);

    // Nombre de salida
    $nombreSalida = $esTotal ? 'Informe_Notas_Credito_Venta_Todos.pdf'
                             : 'Informe_Notas_Credito_Venta_' . preg_replace('/\s+/', '_', $estado) . '.pdf';
    $pdf->Output('I', $nombreSalida);

} catch (PDOException $e) {
    echo "Error al consultar los datos: " . $e->getMessage();
    exit;
}
?>

