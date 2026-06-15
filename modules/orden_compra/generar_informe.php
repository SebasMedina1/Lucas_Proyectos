<?php
// Incluir la conexión a la base de datos y la clase para generar PDF
require '../../config/database.php';
require_once '../../reporte/orden_compras.php'; // Clase BasePDF (FPDF/TCPDF)

function mapIvaPct(?string $ivaDescri): string {
    $k = strtolower(trim((string)$ivaDescri));
    if ($k === 'iva_10') return '10';
    if ($k === 'iva_5')  return '5';
    return '0';
}

// Validar parámetro de estado del modal
if (!isset($_GET['estado']) || $_GET['estado'] === '') {
    echo "<script>
            alert('No se recibió un estado válido para generar el informe.');
            window.location.href = '../reportes/view.php';
          </script>";
    exit;
}

$desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';
$reFecha = '/^\d{4}-\d{2}-\d{2}$/';
if ($desde === '' || $hasta === '' || !preg_match($reFecha, $desde) || !preg_match($reFecha, $hasta)) {
    echo "<script>
            alert('El rango de fechas es obligatorio y debe tener el formato YYYY-MM-DD.');
            window.location.href = '../reportes/view.php';
          </script>";
    exit;
}
if ($hasta < $desde) {
    echo "<script>
            alert('La fecha \"Hasta\" no puede ser anterior a \"Desde\".');
            window.location.href = '../reportes/view.php';
          </script>";
    exit;
}

$estadoParam    = trim($_GET['estado']);
$estadoUpper    = strtoupper($estadoParam);
$estadoTitulo   = $estadoUpper;
$esReporteTotal = in_array($estadoUpper, ['REPORTE TOTAL','TOTAL'], true);

try {
    // Conexión PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Query base (detalle por producto)
    $sql = "
        SELECT
            oc.id_orden_compra                          AS orden_id,
            TO_CHAR(oc.orden_fecha, 'YYYY-MM-DD')       AS fecha,
            TO_CHAR(oc.orden_fecha, 'HH24:MI:SS')       AS hora,
            oc.orden_estado                             AS estado,
            oc.orden_total                              AS total,
            oc.orden_condicion                          AS condicion,
            u.username                                  AS usuario,
            s.descripcion_sucursal                      AS sucursal,
            pv.razon_social                             AS proveedor,
            d.id_materia_prima                                AS cod_producto,
            mp.materia_prima_descripcion                      AS producto,
            d.oc_cantidad_compra                        AS cantidad,
            d.oc_precio_compra                          AS precio,
            ti.iva_descri                               AS iva_descri
        FROM orden_de_compra oc
        JOIN orden_detalle_compra d  ON d.id_orden_compra = oc.id_orden_compra
        JOIN materia_prima mp        ON mp.id_materia_prima = d.id_materia_prima
        LEFT JOIN tipo_iva ti        ON ti.iva_id         = mp.iva_id
        JOIN usuarios u              ON u.id_usuario      = oc.id_usuario
        JOIN sucursales s            ON s.id_sucursal     = oc.id_sucursal
        JOIN proveedor pv            ON pv.id_proveedor   = oc.id_proveedor
        WHERE DATE(oc.orden_fecha) BETWEEN :desde AND :hasta
    ";

    if (!$esReporteTotal) {
        $sql .= " AND oc.orden_estado = :estado ";
    }

    $sql .= " ORDER BY oc.id_orden_compra ASC, d.id_materia_prima ASC;";

    $st = $pdo->prepare($sql);
    $st->bindParam(':desde', $desde, PDO::PARAM_STR);
    $st->bindParam(':hasta', $hasta, PDO::PARAM_STR);
    if (!$esReporteTotal) {
        $st->bindParam(':estado', $estadoUpper, PDO::PARAM_STR);
    }
    $st->execute();
    $rows = $st->fetchAll();

    if (!$rows || count($rows) === 0) {
        header("Location: ../reportes/view.php?alert=6");
        exit;
    }

    // Crear PDF
    $pdf = new BasePDF();
    function u($s){ return mb_convert_encoding((string)$s, 'ISO-8859-1', 'UTF-8'); }

    $pdf->AddPage();
    $pdf->SetFont('Times', 'B', 12);
    $titulo = $esReporteTotal ? "Informe de Órdenes de Compra" : "Informe de Órdenes de Compra \n Estado: {$estadoTitulo}";
    $pdf->Cell(0, 10, u($titulo), 0, 1, 'C');
    $pdf->Ln(3);

    // Encabezado (alineado a tu ejemplo)
    $pdf->SetFont('Times', 'B', 8);
    $pdf->SetFillColor(200, 220, 255);
    // Ajuste de anchos pensando en A4 horizontal o vertical (ajusta si usas landscape)
    $wId = 8; $wUsuario = 22; $wFecha = 18; $wHora = 16; $wSucursal = 28; $wProveedor = 34;
    $wCond = 16; $wTotal = 15; $wProd = 40; $wCant = 14; $wPrecio = 18; $wIva = 12; $wSubt = 20;

    $pdf->Cell($wId, 6, 'ID', 1, 0, 'C', true);
    //$pdf->Cell($wUsuario, 6, 'Usuario', 1, 0, 'C', true);
    $pdf->Cell($wFecha, 6, 'Fecha', 1, 0, 'C', true);
    //$pdf->Cell($wHora, 6, 'Hora', 1, 0, 'C', true);
    //$pdf->Cell($wSucursal, 6, 'Sucursal', 1, 0, 'C', true);
    $pdf->Cell($wProveedor, 6, 'Proveedor', 1, 0, 'C', true);
    $pdf->Cell($wCond, 6, 'Condicion', 1, 0, 'C', true);
    $pdf->Cell($wTotal, 6, 'Total', 1, 0, 'C', true);
    $pdf->Cell($wProd, 6, 'Producto', 1, 0, 'C', true);
    $pdf->Cell($wCant, 6, 'Cant.', 1, 0, 'C', true);
    $pdf->Cell($wPrecio, 6, 'Precio', 1, 0, 'C', true);
    //$pdf->Cell($wIva, 6, 'IVA', 1, 0, 'C', true);
    $pdf->Cell($wSubt, 6, 'Subtot.', 1, 1, 'C', true);

    $pdf->SetFont('Times', '', 8);

    // Render filas
    foreach ($rows as $r) {
        $subtotal = (int)$r['cantidad'] * (int)$r['precio'];
        $ivaPct   = mapIvaPct($r['iva_descri']);

        $pdf->Cell($wId, 6, $r['orden_id'], 1, 0, 'C');
        //$pdf->Cell($wUsuario, 6, $r['usuario'], 1, 0, 'C');
        $pdf->Cell($wFecha, 6, $r['fecha'], 1, 0, 'C');
        //$pdf->Cell($wHora, 6, $r['hora'], 1, 0, 'C');
        //$pdf->Cell($wSucursal, 6, $r['sucursal'], 1, 0, 'C');
        $pdf->Cell($wProveedor, 6, $r['proveedor'], 1, 0, 'C');
        $pdf->Cell($wCond, 6, $r['condicion'], 1, 0, 'C');
        $pdf->Cell($wTotal, 6, number_format((float)$r['total'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell($wProd, 6, $r['producto'], 1, 0, 'L');
        $pdf->Cell($wCant, 6, number_format((int)$r['cantidad'], 0, ',', '.'), 1, 0, 'C');
        $pdf->Cell($wPrecio, 6, number_format((int)$r['precio'], 0, ',', '.'), 1, 0, 'R');
        //$pdf->Cell($wIva, 6, $ivaPct.'%', 1, 0, 'C');
        $pdf->Cell($wSubt, 6, number_format($subtotal, 0, ',', '.'), 1, 1, 'R');
    }

    // Salida del PDF
    $nombre = 'Informe_OC_' . ($esReporteTotal ? 'TODOS' : preg_replace('/\s+/', '_', $estadoTitulo)) . '.pdf';
    $pdf->Output('I', $nombre);

} catch (PDOException $e) {
    echo "Error al consultar los datos: " . htmlspecialchars($e->getMessage());
    exit;
}
