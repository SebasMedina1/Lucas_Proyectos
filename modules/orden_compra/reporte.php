<?php
require_once '../../config/database.php';
require_once '../../reporte/orden_compras.php'; // Clase BasePDF (FPDF/TCPDF)

// Validación del parámetro
if (!isset($_GET['orden_id']) || !ctype_digit($_GET['orden_id'])) {
    die("No se proporcionó un ID de orden válido.");
}
$orden_id = (int) $_GET['orden_id'];

// Helper: formatear Gs
function gs($n) { return number_format((float)$n, 0, ',', '.') . ' Gs'; }

// Mapea 'iva_10'/'iva_5' → porcentaje y cálculo por unidad (precio IVA incluido)
function ivaUnitFromDescri(string $descri, float $precioUnit): int {
    $k = strtolower(trim($descri));
    if ($k === 'iva_10') return (int) floor($precioUnit / 11);
    if ($k === 'iva_5')  return (int) floor($precioUnit / 21);
    return 0;
}

$pdf = new BasePDF();
$pdf->AddPage();

try {
    // Conexión PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // ======= CABECERA =======
    $sqlCab = "
        SELECT 
            oc.id_orden_compra,
            TO_CHAR(oc.orden_fecha, 'YYYY-MM-DD') AS fecha,
            TO_CHAR(oc.orden_fecha, 'HH24:MI:SS') AS hora,
            oc.orden_estado,
            oc.orden_total,
            oc.orden_condicion,
            u.username            AS usuario,
            s.descripcion_sucursal AS sucursal,
            pr.razon_social       AS proveedor,
            oc.id_presupuesto_compra
        FROM orden_de_compra oc
        JOIN usuarios   u  ON u.id_usuario  = oc.id_usuario
        JOIN sucursales s  ON s.id_sucursal = oc.id_sucursal
        JOIN proveedor  pr ON pr.id_proveedor = oc.id_proveedor
        WHERE oc.id_orden_compra = :id
        LIMIT 1;
    ";
    $stCab = $pdo->prepare($sqlCab);
    $stCab->execute([':id' => $orden_id]);
    $cab = $stCab->fetch();

    if (!$cab) {
        die("No se encontró la orden de compra con el ID proporcionado.");
    }

    // Encabezado
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(40, 8, 'Usuario:', 0, 0, 'L');       $pdf->Cell(80, 8, $cab['usuario'], 0, 1, 'L');
    $pdf->Cell(40, 8, 'Fecha:', 0, 0, 'L');         $pdf->Cell(80, 8, $cab['fecha'], 0, 1, 'L');
    $pdf->Cell(40, 8, 'Hora:', 0, 0, 'L');          $pdf->Cell(80, 8, $cab['hora'], 0, 1, 'L');
    $pdf->Cell(40, 8, 'Sucursal:', 0, 0, 'L');      $pdf->Cell(80, 8, $cab['sucursal'], 0, 1, 'L');
    $pdf->Cell(40, 8, 'Proveedor:', 0, 0, 'L');     $pdf->Cell(80, 8, $cab['proveedor'], 0, 1, 'L');
    $pdf->Cell(40, 8, 'Orden Nro:', 0, 0, 'L');     $pdf->Cell(80, 8, $cab['id_orden_compra'], 0, 1, 'L');
    $pdf->Cell(40, 8, 'Estado:', 0, 0, 'L');        $pdf->Cell(80, 8, $cab['orden_estado'], 0, 1, 'L');
    $pdf->Cell(40, 8, 'Condicion:', 0, 0, 'L');     $pdf->Cell(80, 8, $cab['orden_condicion'], 0, 1, 'L');
    $pdf->Cell(40, 8, 'Presupuesto Nro:', 0, 0, 'L');
    $pdf->Cell(80, 8, $cab['id_presupuesto_compra'], 0, 1, 'L');

    $pdf->Ln(8);

    // ======= DETALLE =======
    $sqlDet = "
        SELECT 
            d.id_materia_prima            AS codigo,
            mp.materia_prima_descripcion   AS producto,
            d.oc_cantidad_compra     AS cantidad,
            d.oc_precio_compra       AS precio,
            ti.iva_descri            AS iva    -- 'iva_10' | 'iva_5' | ...
        FROM orden_detalle_compra d
        JOIN materia_prima mp  ON mp.id_materia_prima = d.id_materia_prima
        LEFT JOIN tipo_iva  ti ON ti.iva_id     = mp.iva_id
        WHERE d.id_orden_compra = :id
        ORDER BY mp.materia_prima_descripcion;
    ";
    $stDet = $pdo->prepare($sqlDet);
    $stDet->execute([':id' => $orden_id]);

    // Encabezados tabla
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(200, 220, 255);
    $wCod = 25; $wProd = 75; $wCant = 20; $wPrecio = 30; $wIva = 25; $wSubt = 35;

    $pdf->Cell($wCod, 8, 'Codigo',       1, 0, 'C', true);
    $pdf->Cell($wProd, 8, 'Producto',    1, 0, 'C', true);
    $pdf->Cell($wCant, 8, 'Cant.',       1, 0, 'C', true);
    $pdf->Cell($wPrecio, 8, 'Precio',    1, 0, 'C', true);
    $pdf->Cell($wSubt, 8, 'Subtotal',    1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 10);

    $totalImporte = 0;
    $totalIva     = 0;

    while ($d = $stDet->fetch()) {
        $codigo  = (int) $d['codigo'];
        $prod    = $d['producto'];
        $cant    = (int) $d['cantidad'];
        $precio  = (float) $d['precio'];
        $ivaDesc = (string) $d['iva'];

        $subtotal = $cant * $precio;

        // IVA por fila (precio IVA incluido)
        $ivaUnit = ivaUnitFromDescri($ivaDesc, $precio);
        $ivaFila = $cant * $ivaUnit;

        $totalImporte += $subtotal;
        $totalIva     += $ivaFila;

        $pdf->Cell($wCod, 8, (string)$codigo, 1, 0, 'C');
        $pdf->Cell($wProd, 8, $prod,          1, 0, 'L');
        $pdf->Cell($wCant, 8, number_format($cant, 0, ',', '.'), 1, 0, 'C');
        $pdf->Cell($wPrecio, 8, gs($precio),  1, 0, 'R');
        $pdf->Cell($wSubt, 8, gs($subtotal),  1, 1, 'R');
    }

    // ======= TOTALES =======
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 11);

    $relleno = $wProd + $wCant;

    // Total IVA
    $pdf->Cell($relleno, 8, '', 0, 0);
    $pdf->Cell($wIva, 8, 'Total IVA:', 1, 0, 'R');
    $pdf->Cell($wSubt, 8, gs($totalIva), 1, 1, 'R');

    // Total Importe (suma de subtotales)
    $pdf->Cell($relleno, 8, '', 0, 0);
    $pdf->Cell($wIva, 8, 'Total Importe:', 1, 0, 'R');
    $pdf->Cell($wSubt, 8, gs($totalImporte), 1, 1, 'R');

    // Total de cabecera
    $pdf->SetFont('Arial', '', 10);
    $pdf->Ln(3);
    $pdf->Cell(0, 6,
        'Total registrado en cabecera: ' . gs($cab['orden_total']),
        0, 1, 'R'
    );

    // Salida
    $pdf->Output('I', "OrdenCompra_{$orden_id}.pdf");

} catch (PDOException $e) {
    echo "Error en la conexión o consulta: " . htmlspecialchars($e->getMessage());
    exit;
}
