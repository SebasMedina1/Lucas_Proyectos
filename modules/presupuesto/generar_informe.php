<?php
// Incluir DB y PDF
require '../../config/database.php';
require_once '../../reporte/reporte_presupuesto.php'; // Clase BasePDF (FPDF/TCPDF)

// Validar parámetro
if (!isset($_GET['estado']) || $_GET['estado'] === '') {
    echo "<script>
            alert('No se recibió un estado válido para generar el informe.');
            window.location.href = '../reportes/view.php';
          </script>";
    exit();
}

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

$estadoParam = trim($_GET['estado']);
$estadoUpper = mb_strtoupper($estadoParam);
$esTotal     = in_array($estadoUpper, ['REPORTE TOTAL','TOTAL'], true);

// Helper para convertir a ISO si tu BasePDF trabaja en ISO-8859-1 (opcional)
if (!function_exists('conv')) {
    function conv($s) {
        if ($s === null) return '';
        // Ajustá la conversión si tu BasePDF ya maneja UTF-8
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$s);
    }
}

try {
    // Conexión PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // ================= SQL base =================
    // Traemos cabecera + detalle + producto + iva + usuario (y proveedor si querés mostrarlo)
    $sql = "
        SELECT
            pc.id_presupuesto_compra                 AS pre_id,
            to_char(pc.presu_fecha,'YYYY-MM-DD')     AS fecha,
            to_char(pc.presu_fecha,'HH24:MI:SS')     AS hora,
            pc.presu_estado                          AS estado,
            u.username                               AS usuario,
            pr.razon_social                          AS proveedor,

            mp.materia_prima_descripcion             AS producto,
            d.detalle_presu_cantidad                 AS cantidad,
            d.detalle_presu_precio_compra            AS precio,
            ti.iva_descri                            AS iva_descri
        FROM presupuesto_compra pc
        JOIN usuarios u   ON u.id_usuario      = pc.id_usuario
        JOIN proveedor pr ON pr.id_proveedor   = pc.id_proveedor
        JOIN presupuesto_detalle_compra d ON d.id_presupuesto_compra = pc.id_presupuesto_compra
        JOIN materia_prima mp ON mp.id_materia_prima = d.id_materia_prima
        LEFT JOIN tipo_iva ti  ON ti.iva_id         = mp.iva_id
        WHERE DATE(pc.presu_fecha) BETWEEN :desde AND :hasta
    ";

    if (!$esTotal) {
        $sql .= " AND pc.presu_estado = :estado ";
    }

    $sql .= " ORDER BY pc.presu_fecha DESC, pc.id_presupuesto_compra DESC, mp.materia_prima_descripcion ASC;";

    $st = $pdo->prepare($sql);
    $st->bindParam(':desde', $desde, PDO::PARAM_STR);
    $st->bindParam(':hasta', $hasta, PDO::PARAM_STR);
    if (!$esTotal) {
        $st->bindParam(':estado', $estadoUpper, PDO::PARAM_STR);
    }
    $st->execute();

    $rows = $st->fetchAll();
    if (count($rows) === 0) {
        header("Location: ../reportes/view.php?alert=6");
        exit();
    }

    // ================= PDF =================
    $titulo = $esTotal
        ? 'INFORME DE PRESUPUESTOS - TODOS LOS ESTADOS'
        : 'INFORME DE PRESUPUESTOS - ESTADO: ' . $estadoUpper;

    // Si BasePDF acepta opciones, pásalas; si no, podés imprimir el título manualmente
    $pdf = new BasePDF('P','mm','A4', ['titulo' => $titulo]);
    $pdf->AddPage();

    // Cabecera de columnas (A4)
    $pdf->SetFont('Times','B',11);
    $pdf->SetFillColor(200,220,255);

    // Anchos (pensados para caber en A4 apaisado simple)
    $wPre  = 16; // Presu
    $wProv = 30; // Proveedor
    $wProd = 52; // Producto
    $wCant = 12; // Cant.
    $wPrec = 18; // Precio
    $wSubt = 22; // Subtotal
    $wFec  = 20; // Fecha
    $wEst  = 20; // Estado

    // Puedes comentar proveedor si no querés mostrarlo:
    $pdf->Cell($wPre,  9, 'Presu',     1, 0, 'C', true);
    $pdf->Cell($wProv, 9, 'Proveedor', 1, 0, 'C', true);
    $pdf->Cell($wProd, 9, 'Producto',  1, 0, 'C', true);
    $pdf->Cell($wCant, 9, 'Cant.',     1, 0, 'C', true);
    $pdf->Cell($wPrec, 9, 'Precio',    1, 0, 'C', true);
    $pdf->Cell($wSubt, 9, 'Subtotal',  1, 0, 'C', true);
    $pdf->Cell($wFec,  9, 'Fecha',     1, 0, 'C', true);
    $pdf->Cell($wEst,  9, 'Estado',    1, 1, 'C', true);

    $pdf->SetFont('Times','',10);

    $totalImporte = 0;
    $totalIva     = 0;
    $docCount     = 0;
    $ultimoPreId  = null;

    foreach ($rows as $r) {
        $preId   = (int)$r['pre_id'];
        $fecha   = $r['fecha'];
        $estadoR = $r['estado'];
        $prov    = $r['proveedor'];

        $producto = $r['producto'];
        $cantidad = (int)$r['cantidad'];
        $precio   = (float)$r['precio'];
        $ivaDesc  = strtolower(trim((string)$r['iva_descri'])); // 'iva_10' | 'iva_5' | otro

        // Subtotal
        $subtotal = $cantidad * $precio;

        // IVA por fila (10% => /11, 5% => /21)
        $ivaUnit = 0;
        if ($ivaDesc === 'iva_10')      $ivaUnit = floor($precio / 11);
        elseif ($ivaDesc === 'iva_5')   $ivaUnit = floor($precio / 21);
        $ivaFila = $cantidad * $ivaUnit;

        $totalImporte += $subtotal;
        $totalIva     += $ivaFila;

        // Contar documentos únicos
        if ($ultimoPreId === null || $ultimoPreId !== $preId) {
            $docCount++;
            $ultimoPreId = $preId;
        }

        // Fila
        $pdf->Cell($wPre,  8, number_format($preId, 0, ',', '.'),          1, 0, 'C');
        $pdf->Cell($wProv, 8, conv($prov),                                  1, 0, 'L');
        $pdf->Cell($wProd, 8, conv($producto),                              1, 0, 'L');
        $pdf->Cell($wCant, 8, number_format($cantidad, 0, ',', '.'),        1, 0, 'C');
        $pdf->Cell($wPrec, 8, number_format($precio, 0, ',', '.') . ' Gs',  1, 0, 'R');
        $pdf->Cell($wSubt, 8, number_format($subtotal, 0, ',', '.') . ' Gs',1, 0, 'R');
        $pdf->Cell($wFec,  8, conv($fecha),                                 1, 0, 'C');
        $pdf->Cell($wEst,  8, conv($estadoR),                               1, 1, 'C');
    }

    // ===== Resumen final =====
    $pdf->Ln(5);
    $pdf->SetFont('Times','B',11);
    $pdf->Cell(0, 7, 'Resumen', 0, 1, 'R');

    $pdf->SetFont('Times','',10);
    $pdf->Cell(140, 7, 'Cantidad de presupuestos (documentos):', 0, 0, 'R');
    $pdf->Cell(50,  7, number_format($docCount, 0, ',', '.'), 0, 1, 'R');

    $pdf->Cell(140, 7, 'Total IVA (suma de IVA por fila):', 0, 0, 'R');
    $pdf->Cell(50,  7, number_format($totalIva, 0, ',', '.') . ' Gs', 0, 1, 'R');

    $pdf->Cell(140, 7, 'Total Importe (suma de subtotales):', 0, 0, 'R');
    $pdf->Cell(50,  7, number_format($totalImporte, 0, ',', '.') . ' Gs', 0, 1, 'R');

    // Salida
    $nombreSalida = $esTotal
        ? 'Informe_Presupuestos_Todos.pdf'
        : 'Informe_Presupuestos_' . preg_replace('/\s+/', '_', $estadoUpper) . '.pdf';

    $pdf->Output('I', $nombreSalida);

} catch (PDOException $e) {
    echo "Error al consultar los datos: " . $e->getMessage();
    exit;
}
