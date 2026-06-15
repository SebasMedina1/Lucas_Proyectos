<?php
// Incluir la conexión a la base de datos y la clase para generar PDF
require '../../config/database.php';
require_once '../../reporte/pedido_ventas.php'; // Clase BasePDF

// Validación del parámetro
if (!isset($_GET['estado']) || $_GET['estado'] === '') {
    echo "<script>
            alert('No se recibió un estado válido para generar el informe.');
            window.location.href = 'view.php';
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
            window.location.href = 'view.php';
          </script>";
    exit();
}
if ($hasta < $desde) {
    echo "<script>
            alert('La fecha \"Hasta\" no puede ser anterior a \"Desde\".');
            window.location.href = 'view.php';
          </script>";
    exit();
}

$estado   = trim($_GET['estado']);
$estadoUpper = mb_strtoupper($estado);
$esTotal  = in_array($estadoUpper, ['REPORTE TOTAL','TOTAL'], true);

function convertir($texto) {
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

try {
    // Conexión PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]);

    // SQL base para presupuestos de venta
    $sql = "
        SELECT 
            pv.id_presupuesto_venta                         AS presupuesto_id,
            c.cliente_nombre || ' ' || c.cliente_apellido AS cliente,
            u.username                                  AS usuario,
            p.producto_descri                          AS producto,
            d.cantidad,
            d.precio_unitario,
            d.iva,
            CASE 
                WHEN d.iva > 0 THEN (d.cantidad * d.precio_unitario * (1 + d.iva / 100))
                ELSE (d.cantidad * d.precio_unitario)
            END AS subtotal,
            pv.estado,
            to_char(pv.fecha_presupuesto,'YYYY-MM-DD')      AS fecha_presupuesto
        FROM presupuesto_venta pv
        JOIN detalle_presupuesto_venta d ON pv.id_presupuesto_venta = d.id_presupuesto_venta
        JOIN productos p              ON d.producto_id      = p.producto_id
        JOIN clientes c                ON pv.id_cliente     = c.id_cliente
        JOIN usuarios u                ON pv.id_usuario     = u.id_usuario
        WHERE DATE(pv.fecha_presupuesto) BETWEEN :desde AND :hasta
    ";

    // Condición solo si NO es "REPORTE TOTAL"
    if (!$esTotal) {
        $sql .= " AND pv.estado = :estado ";
    }

    $sql .= " ORDER BY pv.fecha_presupuesto DESC, pv.id_presupuesto_venta DESC, p.producto_descri ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':desde', $desde, PDO::PARAM_STR);
    $stmt->bindParam(':hasta', $hasta, PDO::PARAM_STR);
    if (!$esTotal) {
        $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
    }
    $stmt->execute();

    $presupuestos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($presupuestos) === 0) {
        echo "<script>
                alert('No se encontraron presupuestos para el rango de fechas y estado seleccionado.');
                window.location.href = 'view.php';
              </script>";
        exit;
    }

    // PDF
    $titulo = $esTotal ? 'INFORME DE PRESUPUESTOS DE VENTA - TODOS LOS ESTADOS'
                       : 'INFORME DE PRESUPUESTOS DE VENTA - ESTADO: ' . $estado;

    $pdf = new BasePDF('P','mm','A4', ['titulo' => $titulo]);
    $pdf->AddPage();

    // Información del rango de fechas
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(0, 8, convertir('Período: ' . $desde . ' al ' . $hasta), 0, 1, 'L');
    $pdf->Ln(5);

    // Encabezado de tabla
    $pdf->SetFont('Arial','B',9);
    $pdf->SetFillColor(200,220,255);

    $pdf->Cell(18,  9, convertir('Presup.'),   1, 0, 'C', true);
    $pdf->Cell(45,  9, convertir('Cliente'),  1, 0, 'C', true);
    $pdf->Cell(40,  9, convertir('Producto'), 1, 0, 'C', true);
    $pdf->Cell(15,  9, convertir('Cant.'),    1, 0, 'C', true);
    $pdf->Cell(22,  9, convertir('Subtotal'),  1, 0, 'C', true);
    $pdf->Cell(20,  9, convertir('Fecha'),    1, 0, 'C', true);
    $pdf->Cell(20,  9, convertir('Estado'),   1, 1, 'C', true);

    // Filas
    $pdf->SetFont('Arial','',8);
    $totalGeneral = 0;
    foreach ($presupuestos as $row) {
        $pdf->Cell(18, 8, $row['presupuesto_id'],     1, 0, 'C');
        $pdf->Cell(45, 8, convertir(substr($row['cliente'], 0, 25)),        1, 0, 'L');
        $pdf->Cell(40, 8, convertir(substr($row['producto'], 0, 20)),       1, 0, 'L');
        $pdf->Cell(15, 8, $row['cantidad'],       1, 0, 'C');
        $pdf->Cell(22, 8, number_format($row['subtotal'], 0, ',', '.'),   1, 0, 'R');
        $pdf->Cell(20, 8, $row['fecha_presupuesto'],   1, 0, 'C');
        $pdf->Cell(20, 8, convertir($row['estado']),         1, 1, 'C');
        $totalGeneral += $row['subtotal'];
    }

    // Total general
    $pdf->SetFont('Arial','B',10);
    $pdf->SetFillColor(220,220,220);
    $pdf->Cell(158, 9, convertir('TOTAL GENERAL:'), 1, 0, 'R', true);
    $pdf->Cell(22, 9, number_format($totalGeneral, 0, ',', '.'), 1, 1, 'R', true);

    // Nombre de salida
    $nombreSalida = $esTotal ? 'Informe_Presupuestos_Venta_Todos.pdf'
                             : 'Informe_Presupuestos_Venta_' . preg_replace('/\s+/', '_', $estado) . '.pdf';
    $pdf->Output('I', $nombreSalida);

} catch (PDOException $e) {
    echo "Error al consultar los datos: " . $e->getMessage();
    exit;
}
?>

