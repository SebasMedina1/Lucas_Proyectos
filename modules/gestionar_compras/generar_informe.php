<?php
require '../../config/database.php';
require_once '../../reporte/reporte_compras.php';

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

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$sql = "
        SELECT
            fc.id_factura_compra,
            fc.numero_factura,
            fc.timbrado,
            TO_CHAR(fc.fact_fecha_compra,'YYYY-MM-DD') AS fecha,
            TO_CHAR(fc.fact_fecha_compra,'HH24:MI:SS') AS hora,
            fc.fac_total,
            fc.fac_estado,
            fc.fac_plazo,
            COALESCE(fc.tipo_operacion, 'CONTADO') AS tipo_compra,
            0 AS fac_cuotas, -- Se obtiene desde fac_plazo si es necesario
            0 AS fac_interes_pct, -- Placeholder
            pv.razon_social     AS proveedor,
            u.username          AS usuario,
            mp.materia_prima_descripcion AS producto,
            fd.fac_cantidad,
            fd.fac_precio,
            CASE WHEN UPPER(fc.fac_estado) = 'EMITIDA' THEN cp.monto_total  ELSE NULL END AS cuenta_total,
            CASE WHEN UPPER(fc.fac_estado) = 'EMITIDA' THEN cp.estado ELSE NULL END AS cuenta_estado,
            CASE WHEN UPPER(fc.fac_estado) = 'EMITIDA' THEN cp.fecha_vencimiento  ELSE NULL END AS cuenta_plazo,
            CASE WHEN UPPER(fc.fac_estado) = 'EMITIDA' THEN 0     ELSE NULL END AS cuenta_cuotas,
            CASE WHEN UPPER(fc.fac_estado) = 'EMITIDA' THEN iv.iva_exento    ELSE NULL END AS iva_exento,
            CASE WHEN UPPER(fc.fac_estado) = 'EMITIDA' THEN iv.iva_5         ELSE NULL END AS iva_5,
            CASE WHEN UPPER(fc.fac_estado) = 'EMITIDA' THEN iv.iva_10        ELSE NULL END AS iva_10
        FROM factura_compra fc
        JOIN usuarios u            ON u.id_usuario       = fc.id_usuario
        JOIN orden_de_compra oc    ON oc.id_orden_compra = fc.id_orden_compra
        JOIN proveedor pv          ON pv.id_proveedor    = oc.id_proveedor
        LEFT JOIN cuentas_pagar cp ON cp.id_factura_compra = fc.id_factura_compra
        JOIN factura_detalle_compra fd ON fd.id_factura_compra = fc.id_factura_compra
        JOIN materia_prima mp      ON mp.id_materia_prima = fd.id_materia_prima
        LEFT JOIN (
            SELECT
                id_proveedor,
                fecha_emision,
                MAX(monto_total) AS monto_total,
                MAX(estado) AS estado,
                MAX(fecha_vencimiento) AS fecha_vencimiento
            FROM cuentas_pagar
            GROUP BY id_proveedor, fecha_emision
        ) cp ON cp.id_proveedor = oc.id_proveedor 
          AND cp.fecha_emision = fc.fact_fecha_compra
        LEFT JOIN (
            SELECT
                id_factura_compra,
                SUM(COALESCE(iva_exento,0)) AS iva_exento,
                SUM(COALESCE(iva_5,0))      AS iva_5,
                SUM(COALESCE(iva_10,0))      AS iva_10
            FROM iva_compra
            GROUP BY id_factura_compra
        ) iv ON iv.id_factura_compra = fc.id_factura_compra
        WHERE DATE(fc.fact_fecha_compra) BETWEEN :desde AND :hasta
    ";
    if (!$esTotal) {
        $sql .= " AND UPPER(fc.fac_estado) = :estado ";
    }
    $sql .= " ORDER BY fc.id_factura_compra DESC, mp.materia_prima_descripcion ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':desde', $desde, PDO::PARAM_STR);
    $stmt->bindParam(':hasta', $hasta, PDO::PARAM_STR);
    if (!$esTotal) {
        $stmt->bindParam(':estado', $estadoUpper, PDO::PARAM_STR);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        header("Location: ../reportes/view.php?alert=6");
        exit;
    }

    $facturas = [];
    foreach ($rows as $row) {
        $factId = (int)$row['id_factura_compra'];
        if (!isset($facturas[$factId])) {
            $facturas[$factId] = [
                'numero'    => $row['numero_factura'],
                'timbrado'  => $row['timbrado'],
                'fecha'     => $row['fecha'],
                'hora'      => $row['hora'],
                'proveedor' => $row['proveedor'],
                'usuario'   => $row['usuario'],
                'estado'    => $row['fac_estado'],
                'tipo'      => $row['tipo_compra'],
                'plazo'     => $row['fac_plazo'],
                'cuotas'    => $row['fac_cuotas'],
                'interes'   => $row['fac_interes_pct'],
                'total'     => (float)$row['fac_total'],
                'cuenta'    => [
                    'total'  => $row['cuenta_total'],
                    'estado' => $row['cuenta_estado'],
                    'plazo'  => $row['cuenta_plazo'],
                    'cuotas' => $row['cuenta_cuotas']
                ],
                'iva'       => [
                    'exento' => $row['iva_exento'],
                    'iva5'   => $row['iva_5'],
                    'iva10'  => $row['iva_10']
                ],
                'detalles'  => []
            ];
        }

        $facturas[$factId]['detalles'][] = [
            'producto' => $row['producto'],
            'cantidad' => (float)$row['fac_cantidad'],
            'precio'   => (float)$row['fac_precio']
        ];
    }

    $titulo = $esTotal
        ? 'Informe de Facturas de Compra - Todos los estados'
        : 'Informe de Facturas de Compra - Estado: ' . $estadoUpper;

    $pdf = new BasePDF();
    $pdf->AddPage();
    $pdf->SetFont('Times', 'B', 16);
    $pdf->Cell(0, 10, convertir($titulo), 0, 1, 'C');
    $pdf->Ln(4);

    $wProd = 80; $wCant = 25; $wPrecio = 35; $wSub = 40;

    $firstInvoice = true;
    foreach ($facturas as $id => $factura) {
        if ($firstInvoice) {
            $firstInvoice = false;
        } else {
            $pdf->AddPage();
            $pdf->SetFont('Times', 'B', 16);
            $pdf->Cell(0, 10, convertir($titulo), 0, 1, 'C');
            $pdf->Ln(4);
        }

        $pdf->SetFont('Times','B',12);
        $pdf->Cell(0, 8, convertir("ID Factura: {$id}"), 0, 1, 'L');
        $pdf->Cell(0, 8, convertir("Factura #{$factura['numero']} - {$factura['proveedor']}"), 0, 1, 'L');
        $pdf->SetFont('Times','',10);
        $pdf->Cell(95, 6, convertir("Fecha: {$factura['fecha']} {$factura['hora']}"), 0, 0, 'L');
        $pdf->Cell(95, 6, convertir("Estado: {$factura['estado']}"), 0, 1, 'L');
        $pdf->Cell(95, 6, convertir("Timbrado: {$factura['timbrado']}"), 0, 0, 'L');
        $pdf->Cell(95, 6, convertir("Usuario: {$factura['usuario']}"), 0, 1, 'L');
        $pdf->Cell(95, 6, convertir("Tipo de compra: {$factura['tipo']} / {$factura['plazo']}"), 0, 0, 'L');
        $pdf->Cell(95, 6, convertir("Cuotas: {$factura['cuotas']} | % Interés: {$factura['interes']}"), 0, 1, 'L');
        $pdf->Ln(2);

        $pdf->SetFont('Times','B',10);
        $pdf->SetFillColor(200,220,255);
        $pdf->Cell($wProd, 7, 'Producto', 1, 0, 'C', true);
        $pdf->Cell($wCant, 7, 'Cantidad', 1, 0, 'C', true);
        $pdf->Cell($wPrecio, 7, 'Precio', 1, 0, 'C', true);
        $pdf->Cell($wSub, 7, 'Subtotal', 1, 1, 'C', true);

        $pdf->SetFont('Times','',10);
        foreach ($factura['detalles'] as $detalle) {
            $cantidad = $detalle['cantidad'];
            $precio   = $detalle['precio'];
            $subtotal = $cantidad * $precio;

            $pdf->Cell($wProd,   7, convertir($detalle['producto']), 1, 0, 'L');
            $pdf->Cell($wCant,   7, number_format($cantidad, 0, ',', '.'), 1, 0, 'C');
            $pdf->Cell($wPrecio, 7, number_format($precio, 0, ',', '.') . ' Gs', 1, 0, 'R');
            $pdf->Cell($wSub,    7, number_format($subtotal, 0, ',', '.') . ' Gs', 1, 1, 'R');
        }

        $cuenta = $factura['cuenta'];
        $iva    = $factura['iva'];

        $pdf->SetFont('Times','B',10);
        $pdf->Cell($wProd + $wCant + $wPrecio, 7, convertir('Total factura'), 1, 0, 'R');
        $pdf->Cell($wSub, 7, number_format($factura['total'], 0, ',', '.') . ' Gs', 1, 1, 'R');

        $ivaTotal = null;
        if ($iva['exento'] !== null || $iva['iva5'] !== null || $iva['iva10'] !== null) {
            $ivaTotal = (float)($iva['exento'] ?? 0) + (float)($iva['iva5'] ?? 0) + (float)($iva['iva10'] ?? 0);
        }
        $pdf->SetFont('Times','B',10);
        $pdf->Cell($wProd + $wCant + $wPrecio, 7, convertir('Total IVA'), 1, 0, 'R');
        $pdf->Cell($wSub, 7, $ivaTotal === null ? '-' : number_format($ivaTotal, 0, ',', '.') . ' Gs', 1, 1, 'R');

        $pdf->Ln(2);
        $pdf->SetFont('Times','B',10);
        $pdf->Cell(0, 6, 'Cuenta a pagar', 0, 1, 'L');
        $pdf->SetFont('Times','',10);
        $pdf->Cell(0, 6, convertir(sprintf(
            'Total: %s | Estado: %s | Plazo: %s | Nro cuota: %s',
            $cuenta['total']  === null ? '-' : number_format($cuenta['total'], 0, ',', '.') . ' Gs',
            $cuenta['estado'] ?? '-',
            $cuenta['plazo']  ?? '-',
            $cuenta['cuotas'] ?? '-'
        )), 0, 1, 'L');

        $pdf->SetFont('Times','B',10);
        $pdf->Cell(0, 6, 'IVA compra', 0, 1, 'L');
        $pdf->SetFont('Times','',10);
        $pdf->Cell(0, 6, convertir(sprintf(
            'Exento: %s | IVA 5%%: %s | IVA 10%%: %s',
            $iva['exento'] === null ? '-' : number_format($iva['exento'], 0, ',', '.') . ' Gs',
            $iva['iva5']   === null ? '-' : number_format($iva['iva5'], 0, ',', '.') . ' Gs',
            $iva['iva10']  === null ? '-' : number_format($iva['iva10'], 0, ',', '.') . ' Gs'
        )), 0, 1, 'L');

        $pdf->Ln(8);
    }

    $nombreSalida = $esTotal
        ? 'Informe_Facturas_Compra_Todos.pdf'
        : 'Informe_Facturas_Compra_' . preg_replace('/\s+/', '_', $estadoUpper) . '.pdf';
    $pdf->Output('I', $nombreSalida);

} catch (PDOException $e) {
    echo "Error al consultar los datos: " . $e->getMessage();
    exit;
}
?>
