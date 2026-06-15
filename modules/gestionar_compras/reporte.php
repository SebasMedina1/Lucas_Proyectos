<?php
require_once '../../config/database.php';
require_once '../../reporte/reporte_compras.php'; // BasePDF (FPDF/TCPDF)

// Validación de parámetro
if (!isset($_GET['fac_id']) || !ctype_digit($_GET['fac_id'])) {
  die("No se proporcionó un ID de factura válido.");
}
$fac_id = (int) $_GET['fac_id'];

$pdf = new BasePDF();
$pdf->AddPage();

$T = fn($s) => iconv('UTF-8','ISO-8859-1//TRANSLIT', (string)$s);


try {
  // Conexión PDO
  $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);

  // ======= CABECERA DE LA FACTURA DE COMPRA =======
  // Nota: proveedor lo obtenemos a través de la OC
  $sqlCab = "
    SELECT 
      f.id_factura_compra,
      f.numero_factura,
      f.timbrado,
      to_char(f.fact_fecha_compra,'YYYY-MM-DD') AS fecha,
      to_char(f.fact_fecha_compra,'HH24:MI:SS') AS hora,
      to_char(f.fact_fecha_compra,'YYYY-MM-DD') AS fecha_emision,
      f.fac_total,
      f.fac_estado,
      f.fac_plazo,
      f.fac_remision,
      COALESCE(f.tipo_operacion, 'CONTADO') AS tipo_compra,
      0 AS fac_cuotas, -- Se obtiene desde fac_plazo si es necesario
      0 AS fac_interes_pct, -- Placeholder, el interés se calcula
      u.username                         AS usuario,
      s.descripcion_sucursal             AS sucursal,
      pr.razon_social                    AS proveedor,
      pr.ruc_proveedor                      AS proveedor_ruc,
      f.id_orden_compra,
      COALESCE(nr.id_nota_remision, 0) AS id_nota_remision_compra
    FROM factura_compra f
    JOIN usuarios    u  ON u.id_usuario  = f.id_usuario
    JOIN sucursales  s  ON s.id_sucursal = f.id_sucursal
    JOIN orden_de_compra oc ON oc.id_orden_compra = f.id_orden_compra
    JOIN proveedor   pr ON pr.id_proveedor = oc.id_proveedor
    LEFT JOIN nota_remision_compra nr ON nr.id_factura_compra = f.id_factura_compra
    WHERE f.id_factura_compra = :id
    LIMIT 1;
  ";
  $stCab = $pdo->prepare($sqlCab);
  $stCab->execute([':id' => $fac_id]);
  $cab = $stCab->fetch();

  if (!$cab) die("No se encontró la factura de compra con el ID proporcionado.");

  // Encabezado
  $pdf->SetFont('Arial','',12);
  $pdf->Cell(40, 8, 'Usuario:', 0, 0, 'L');        $pdf->Cell(80, 8, $T($cab['usuario']), 0, 1, 'L');
  $pdf->Cell(40, 8, 'Fecha Registro:', 0, 0, 'L'); $pdf->Cell(80, 8, $T($cab['fecha']), 0, 1, 'L');
  $pdf->Cell(40, 8, 'Hora:', 0, 0, 'L');           $pdf->Cell(80, 8, $T($cab['hora']), 0, 1, 'L');
  if (!empty($cab['fecha_emision'])) {
    $pdf->Cell(40, 8, 'Fecha Emisión:', 0, 0, 'L'); $pdf->Cell(80, 8, $T($cab['fecha_emision']), 0, 1, 'L');
  }
  $pdf->Cell(40, 8, 'Sucursal:', 0, 0, 'L');       $pdf->Cell(80, 8, $T($cab['sucursal']), 0, 1, 'L');
  $pdf->Cell(40, 8, 'Proveedor:', 0, 0, 'L');      $pdf->Cell(80, 8, $T($cab['proveedor']), 0, 1, 'L');
  $pdf->Cell(40, 8, 'RUC:', 0, 0, 'L');            $pdf->Cell(80, 8, $T($cab['proveedor_ruc']), 0, 1, 'L');
  $pdf->Cell(40, 8, 'Factura Nro:', 0, 0, 'L');    $pdf->Cell(80, 8, $T($cab['numero_factura']), 0, 1, 'L');
  $pdf->Cell(40, 8, 'Timbrado:', 0, 0, 'L');       $pdf->Cell(80, 8, $T($cab['timbrado']), 0, 1, 'L');
  $pdf->Cell(40, 8, 'Factura ID:', 0, 0, 'L');     $pdf->Cell(80, 8, $T($cab['id_factura_compra']), 0, 1, 'L');
  $pdf->Cell(40, 8, 'Orden de Compra:', 0, 0, 'L'); $pdf->Cell(80, 8, $T($cab['id_orden_compra']), 0, 1, 'L');
  $pdf->Cell(40, 8, 'Estado:', 0, 0, 'L');         $pdf->Cell(80, 8, $T($cab['fac_estado']), 0, 1, 'L');
  $pdf->Cell(40, 8, $T('Condición:'), 0, 0, 'L'); $pdf->Cell(80, 8, $T($cab['tipo_compra']).' / '.$T($cab['fac_plazo']), 0, 1, 'L');

  if ((int)$cab['fac_remision'] === 1 && !empty($cab['id_nota_remision_compra'])) {
    $pdf->Cell(40, 8, 'NR vinculada:', 0, 0, 'L'); 
    $pdf->Cell(80, 8, $cab['id_nota_remision_compra'], 0, 1, 'L');
  }
  if ($cab['tipo_compra']==='CREDITO') {
    $pdf->Cell(40, 8, 'Cuotas:', 0, 0, 'L');       
    $pdf->Cell(80, 8, (string)$cab['fac_cuotas'], 0, 1, 'L');
    $pdf->Cell(40, 8, $T('% Interés:'), 0, 0, 'L'); 
    $pdf->Cell(80, 8, (string)$cab['fac_interes_pct'].' %', 0, 1, 'L');
  }
  $pdf->Ln(6);

  // ======= DETALLE =======
  $sqlDet = "
    SELECT 
      mp.materia_prima_descripcion AS producto,
      d.fac_cantidad         AS cantidad,
      d.fac_precio           AS precio,
      COALESCE(d.fac_iva, 0) AS iva_monto,
      ti.iva_descri          AS iva_desc   -- Para identificar tipo de IVA si es necesario
    FROM factura_detalle_compra d
    JOIN materia_prima mp  ON mp.id_materia_prima = d.id_materia_prima
    LEFT JOIN tipo_iva  ti ON ti.iva_id    = mp.iva_id
    WHERE d.id_factura_compra = :id
    ORDER BY mp.materia_prima_descripcion;
  ";
  $stDet = $pdo->prepare($sqlDet);
  $stDet->execute([':id' => $fac_id]);

  // Encabezados de tabla
  $pdf->SetFont('Arial','B',10);
  $pdf->SetFillColor(200, 220, 255);
  $wProd = 80; $wCant = 20; $wPrecio = 30; $wIva = 30; $wSubt = 35;

  $pdf->Cell($wProd,   8, 'Producto',       1, 0, 'C', true);
  $pdf->Cell($wCant,   8, 'Cant.',          1, 0, 'C', true);
  $pdf->Cell($wPrecio, 8, 'Precio Unit.',   1, 0, 'C', true);
  $pdf->Cell($wIva,    8, 'IVA',       1, 0, 'C', true);
  $pdf->Cell($wSubt,   8, 'Subtotal',       1, 1, 'C', true);

  $pdf->SetFont('Arial','',10);

  $totalImporteBase = 0; // suma de subtotales (base imponible)
  $totalIva5        = 0; // IVA 5%
  $totalIva10       = 0; // IVA 10%
  $totalIva         = 0; // Total IVA

  while ($d = $stDet->fetch()) {
    $producto = $T($d['producto']);
    $cant     = (int)$d['cantidad'];
    $precio   = (float)$d['precio'];
    $ivaMonto = (int)($d['iva_monto'] ?? 0); // IVA ya calculado y guardado
    $ivaDesc  = strtolower(trim((string)($d['iva_desc'] ?? '')));

    $subtotal = $cant * $precio;
    $ivaFila  = $ivaMonto; // Usar el IVA guardado directamente

    // Clasificar IVA para totales (5% o 10%)
    if ($ivaDesc === 'iva_10' || strpos($ivaDesc, '10') !== false) {
      $totalIva10 += $ivaFila;
    } elseif ($ivaDesc === 'iva_5' || strpos($ivaDesc, '5') !== false) {
      $totalIva5 += $ivaFila;
    }
    
    $totalImporteBase += $subtotal;
    $totalIva         += $ivaFila;

    $pdf->Cell($wProd,   8, $producto,                                1, 0, 'L');
    $pdf->Cell($wCant,   8, number_format($cant, 0, ',', '.'),        1, 0, 'C');
    $pdf->Cell($wPrecio, 8, number_format($precio, 0, ',', '.').' Gs',1, 0, 'R');
    $pdf->Cell($wIva,    8, number_format($ivaFila, 0, ',', '.').' Gs',1,0, 'R');
    $pdf->Cell($wSubt,   8, number_format($subtotal, 0, ',', '.').' Gs',1,1,'R');
  }

  // ======= TOTALES =======
  $pdf->Ln(5);
  $pdf->SetFont('Arial','B',11);

  // Base Imponible (antes de IVA)
  $baseImponible = $totalImporteBase - $totalIva;
  $pdf->Cell($wProd + $wCant + $wPrecio, 8, '', 0, 0);
  $pdf->Cell($wIva,  8, 'Base Imponible:', 1, 0, 'R');
  $pdf->Cell($wSubt, 8, number_format($baseImponible, 0, ',', '.').' Gs', 1, 1, 'R');

  // IVA 5%
  if ($totalIva5 > 0) {
    $pdf->Cell($wProd + $wCant + $wPrecio, 8, '', 0, 0);
    $pdf->Cell($wIva,  8, 'IVA 5%:', 1, 0, 'R');
    $pdf->Cell($wSubt, 8, number_format($totalIva5, 0, ',', '.').' Gs', 1, 1, 'R');
  }

  // IVA 10%
  if ($totalIva10 > 0) {
    $pdf->Cell($wProd + $wCant + $wPrecio, 8, '', 0, 0);
    $pdf->Cell($wIva,  8, 'IVA 10%:', 1, 0, 'R');
    $pdf->Cell($wSubt, 8, number_format($totalIva10, 0, ',', '.').' Gs', 1, 1, 'R');
  }

  // Total IVA
  $pdf->Cell($wProd + $wCant + $wPrecio, 8, '', 0, 0);
  $pdf->Cell($wIva,  8, 'Total IVA:', 1, 0, 'R');
  $pdf->Cell($wSubt, 8, number_format($totalIva, 0, ',', '.').' Gs', 1, 1, 'R');

  // Total Importe (con IVA incluido)
  $pdf->Cell($wProd + $wCant + $wPrecio, 8, '', 0, 0);
  $pdf->Cell($wIva,  8, 'Total Factura:', 1, 0, 'R');
  $pdf->Cell($wSubt, 8, number_format((float)$cab['fac_total'], 0, ',', '.').' Gs', 1, 1, 'R');

  // Si es crédito, mostrar cálculo de total con interés y lo registrado
  if ($cab['tipo_compra'] === 'CREDITO' && (int)$cab['fac_cuotas'] > 0) {
    $pdf->Ln(3);
    $pdf->SetFont('Arial','',10);

    $cuotas = (int)$cab['fac_cuotas'];
    $pct    = (float)$cab['fac_interes_pct'];

    // Mismo criterio que usás en backend/frontend
    $cuotaBase   = intdiv((int)$totalImporteBase, max(1,$cuotas));
    $interesCta  = (int) round($cuotaBase * ($pct/100));
    $cuotaFinal  = $cuotaBase + $interesCta;
    $totalConInt = $cuotaFinal * $cuotas;

    $pdf->Cell(0, 6, $T('Crédito: ').$cuotas.$T(' cuota(s) | Interés: ').$pct.' %', 0, 1, 'L');
  }

  // Salida
  $pdf->Output('I', "Compra_{$fac_id}.pdf");

} catch (PDOException $e) {
  echo "Error en la conexión o consulta: " . $e->getMessage();
  exit;
}
