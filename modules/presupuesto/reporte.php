<?php
require_once '../../config/database.php';
require_once '../../reporte/reporte_presupuesto.php'; // Clase BasePDF (FPDF/TCPDF)

// Validación de parámetro
if (!isset($_GET['pre_id']) || !ctype_digit($_GET['pre_id'])) {
    die("No se proporcionó un ID de presupuesto válido.");
}
$pre_id = (int) $_GET['pre_id'];

$pdf = new BasePDF();
$pdf->AddPage();

try {
    // Conexión PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // ======= CABECERA DEL PRESUPUESTO =======
    $sqlCab = "
        SELECT 
            pc.id_presupuesto_compra,
            to_char(pc.presu_fecha, 'YYYY-MM-DD') AS fecha,
            to_char(pc.presu_fecha, 'HH24:MI:SS') AS hora,
            pc.presu_estado,
            pc.presu_total,
            u.username        AS usuario,
            s.descripcion_sucursal AS sucursal,
            pr.razon_social   AS proveedor
        FROM presupuesto_compra pc
        JOIN usuarios    u  ON u.id_usuario  = pc.id_usuario
        JOIN sucursales  s  ON s.id_sucursal = pc.id_sucursal
        JOIN proveedor   pr ON pr.id_proveedor = pc.id_proveedor
        WHERE pc.id_presupuesto_compra = :id
        LIMIT 1;
    ";
    $stCab = $pdo->prepare($sqlCab);
    $stCab->execute([':id' => $pre_id]);
    $cab = $stCab->fetch();

    if (!$cab) {
        die("No se encontró el presupuesto con el ID proporcionado.");
    }

    // Encabezado
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(40, 8, 'Usuario:', 0, 0, 'L');    $pdf->Cell(80, 8, $cab['usuario'], 0, 1, 'L');
    $pdf->Cell(40, 8, 'Fecha:', 0, 0, 'L');      $pdf->Cell(80, 8, $cab['fecha'], 0, 1, 'L');
    $pdf->Cell(40, 8, 'Hora:', 0, 0, 'L');       $pdf->Cell(80, 8, $cab['hora'], 0, 1, 'L');
    $pdf->Cell(40, 8, 'Sucursal:', 0, 0, 'L');   $pdf->Cell(80, 8, $cab['sucursal'], 0, 1, 'L');
    $pdf->Cell(40, 8, 'Proveedor:', 0, 0, 'L');  $pdf->Cell(80, 8, $cab['proveedor'], 0, 1, 'L');
    $pdf->Cell(40, 8, 'Presupuesto Nro:', 0, 0, 'L');
    $pdf->Cell(80, 8, $cab['id_presupuesto_compra'], 0, 1, 'L');
    $pdf->Cell(40, 8, 'Estado:', 0, 0, 'L');    $pdf->Cell(80, 8, $cab['presu_estado'], 0, 1, 'L');
    $pdf->Ln(8);

    // ======= DETALLE =======
    $sqlDet = "
        SELECT 
            mp.materia_prima_descripcion AS producto,
            d.detalle_presu_cantidad      AS cantidad,
            d.detalle_presu_precio_compra AS precio,
            COALESCE(d.descuento, 0)      AS descuento,
            COALESCE(d.detalle_presu_iva, 0) AS iva_monto,
            ti.iva_descri                 AS iva_descri   -- valores esperados: 'iva_10', 'iva_5', (u otro)
        FROM presupuesto_detalle_compra d
        JOIN materia_prima mp ON mp.id_materia_prima = d.id_materia_prima
        LEFT JOIN tipo_iva  ti ON ti.iva_id    = mp.iva_id
        WHERE d.id_presupuesto_compra = :id
        ORDER BY mp.materia_prima_descripcion;
    ";
    $stDet = $pdo->prepare($sqlDet);
    $stDet->execute([':id' => $pre_id]);

    // Encabezados de tabla
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(200, 220, 255);
    // Anchos recomendados
    $wProd = 80; $wCant = 20; $wPrecio = 30; $wIva = 25; $wSubt = 35;

    $pdf->Cell($wProd, 8, 'Producto',        1, 0, 'C', true);
    $pdf->Cell($wCant, 8, 'Cant.',           1, 0, 'C', true);
    $pdf->Cell($wPrecio, 8, 'Precio Unit.',  1, 0, 'C', true);
    $pdf->Cell($wIva, 8, 'IVA fila',         1, 0, 'C', true);
    $pdf->Cell($wSubt, 8, 'Subtotal',        1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 10);

    $totalImporte = 0;
    $totalIva     = 0;

    while ($d = $stDet->fetch()) {
        $producto = $d['producto'];
        $cant     = (int) $d['cantidad'];
        $precio   = (float) $d['precio'];
        $descuento = (float) ($d['descuento'] ?? 0);
        $ivaMonto = (float) ($d['iva_monto'] ?? 0);
        $ivaDesc  = strtolower(trim((string)($d['iva_descri'] ?? ''))); // 'iva_10' | 'iva_5' | otro

        // Subtotal = (cantidad * precio) - descuento
        $subtotal = ($cant * $precio) - $descuento;

        // IVA: usar el valor almacenado en la BD si existe, sino calcularlo
        if ($ivaMonto > 0) {
            $ivaFila = $ivaMonto;
        } else {
            // Calcular IVA si no está almacenado (norma local: 10% -> /11, 5% -> /21)
            $precioConDescuento = $precio - ($descuento / $cant);
            $precioBaseImponible = $precioConDescuento > 0 ? $precioConDescuento : $precio;
            $ivaUnit = 0;
            if ($ivaDesc === 'iva_10')      $ivaUnit = floor($precioBaseImponible / 11);
            elseif ($ivaDesc === 'iva_5')   $ivaUnit = floor($precioBaseImponible / 21);
            $ivaFila = $cant * $ivaUnit;
        }

        // Acumular
        $totalImporte += $subtotal;
        $totalIva     += $ivaFila;

        // Render fila
        $pdf->Cell($wProd, 8, $producto, 1, 0, 'L');
        $pdf->Cell($wCant, 8, number_format($cant, 0, ',', '.'), 1, 0, 'C');
        $pdf->Cell($wPrecio, 8, number_format($precio, 0, ',', '.') . ' Gs', 1, 0, 'R');
        $pdf->Cell($wIva, 8, number_format($ivaFila, 0, ',', '.') . ' Gs', 1, 0, 'R');
        $pdf->Cell($wSubt, 8, number_format($subtotal, 0, ',', '.') . ' Gs', 1, 1, 'R');
    }

    // ======= TOTALES =======
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 11);

    // Total IVA
    $pdf->Cell($wProd + $wCant + $wPrecio, 8, '', 0, 0); // espacio
    $pdf->Cell($wIva, 8, 'Total IVA:', 1, 0, 'R');
    $pdf->Cell($wSubt, 8, number_format($totalIva, 0, ',', '.') . ' Gs', 1, 1, 'R');

    // Total Importe (suma de subtotales) — debería coincidir con presu_total
    $pdf->Cell($wProd + $wCant + $wPrecio, 8, '', 0, 0);
    $pdf->Cell($wIva, 8, 'Total Importe:', 1, 0, 'R');
    $pdf->Cell($wSubt, 8, number_format($totalImporte, 0, ',', '.') . ' Gs', 1, 1, 'R');

    // Total segun cabecera (por si querés mostrar lo registrado exactamente en DB)
    $pdf->SetFont('Arial', '', 10);
    $pdf->Ln(3);
    $pdf->Cell(0, 6,
        'Total registrado en cabecera: ' . number_format((float)$cab['presu_total'], 0, ',', '.') . ' Gs',
        0, 1, 'R'
    );

    // Salida
    $pdf->Output('I', "Presupuesto_{$pre_id}.pdf");
} catch (PDOException $e) {
    echo "Error en la conexión o consulta: " . $e->getMessage();
    exit;
}
