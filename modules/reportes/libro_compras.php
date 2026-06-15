<?php
session_start();
require '../../config/database.php';

$desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';

$reFecha = '/^\d{4}-\d{2}-\d{2}$/';
if ($desde === '' || $hasta === '' || !preg_match($reFecha, $desde) || !preg_match($reFecha, $hasta)) {
    echo "<script>
            alert('El rango de fechas es obligatorio y debe tener el formato YYYY-MM-DD.');
            window.location.href = 'view.php';
          </script>";
    exit;
}
if ($hasta < $desde) {
    echo "<script>
            alert('La fecha \"Hasta\" no puede ser anterior a \"Desde\".');
            window.location.href = 'view.php';
          </script>";
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $sqlFacturas = "
        SELECT
            base.id_factura_compra,
            base.fecha,
            base.numero_factura,
            base.timbrado,
            base.ruc_proveedor,
            base.razon_social,
            base.condicion,
            SUM(CASE WHEN base.iva_label = 0  THEN base.subtotal ELSE 0 END) AS exento,
            SUM(CASE WHEN base.iva_label = 5  THEN base.subtotal ELSE 0 END) AS gravado5,
            SUM(CASE WHEN base.iva_label = 10 THEN base.subtotal ELSE 0 END) AS gravado10,
            SUM(CASE WHEN base.iva_label = 5  THEN FLOOR(base.subtotal/21.0)::bigint ELSE 0 END) AS iva5,
            SUM(CASE WHEN base.iva_label = 10 THEN FLOOR(base.subtotal/11.0)::bigint ELSE 0 END) AS iva10
        FROM (
            SELECT
                fc.id_factura_compra,
                COALESCE(fc.factura_emision::date, fc.fact_fecha_compra::date) AS fecha,
                fc.numero_factura,
                COALESCE(fc.timbrado, '') AS timbrado,
                pr.ruc_proveedor,
                pr.razon_social,
                COALESCE(fc.tipo_compra, '') AS condicion,
                (fdc.fac_cantidad * fdc.fac_precio) AS subtotal,
                CASE
                    WHEN POSITION('10' IN LOWER(ti.iva_descri)) > 0 THEN 10
                    WHEN POSITION('5'  IN LOWER(ti.iva_descri)) > 0 THEN 5
                    ELSE 0
                END AS iva_label
            FROM factura_compra fc
            JOIN orden_de_compra oc         ON oc.id_orden_compra = fc.id_orden_compra
            JOIN proveedor pr              ON pr.id_proveedor    = oc.id_proveedor
            JOIN factura_detalle_compra fdc ON fdc.id_factura_compra = fc.id_factura_compra
            JOIN producto p                ON p.id_producto      = fdc.id_producto
            JOIN tipo_iva ti               ON ti.iva_id          = p.iva_id
            WHERE fc.fac_estado = 'APROBADO'
              AND COALESCE(fc.factura_emision::date, fc.fact_fecha_compra::date) BETWEEN :desde AND :hasta
        ) AS base
        GROUP BY base.id_factura_compra, base.fecha, base.numero_factura, base.timbrado,
                 base.ruc_proveedor, base.razon_social, base.condicion
        ORDER BY base.fecha ASC, base.numero_factura ASC
    ";

    $stmtFact = $pdo->prepare($sqlFacturas);
    $stmtFact->execute([':desde' => $desde, ':hasta' => $hasta]);
    $facturas = $stmtFact->fetchAll();

    $sqlNotas = "
        SELECT
            base.id_nota_compra,
            base.fecha,
            base.nota_compra_tipo,
            base.numero,
            base.timbrado,
            base.ruc_proveedor,
            base.razon_social,
            base.condicion,
            SUM(CASE WHEN base.iva_label = 0  THEN base.signo * base.subtotal ELSE 0 END) AS exento,
            SUM(CASE WHEN base.iva_label = 5  THEN base.signo * base.subtotal ELSE 0 END) AS gravado5,
            SUM(CASE WHEN base.iva_label = 10 THEN base.signo * base.subtotal ELSE 0 END) AS gravado10,
            SUM(CASE WHEN base.iva_label = 5  THEN base.signo * FLOOR(base.subtotal/21.0)::bigint ELSE 0 END) AS iva5,
            SUM(CASE WHEN base.iva_label = 10 THEN base.signo * FLOOR(base.subtotal/11.0)::bigint ELSE 0 END) AS iva10
        FROM (
            SELECT
                nc.id_nota_compra,
                nc.nota_compra_emision::date AS fecha,
                nc.nota_compra_tipo,
                nc.nota_nro AS numero,
                COALESCE(nc.nota_compra_timbrado, '') AS timbrado,
                pr.ruc_proveedor,
                pr.razon_social,
                COALESCE(fc.tipo_compra, '') AS condicion,
                (nd.nota_compra_cantidad * nd.nota_precio) AS subtotal,
                COALESCE(NULLIF(nd.tipo_iva, 0), 0) AS iva_label,
                CASE WHEN UPPER(nc.nota_compra_tipo) = 'CREDITO' THEN -1 ELSE 1 END AS signo
            FROM nota_compra nc
            JOIN proveedor pr         ON pr.id_proveedor = nc.id_proveedor
            JOIN nota_detalle_compra nd ON nd.id_nota_compra = nc.id_nota_compra
            LEFT JOIN factura_compra fc ON fc.id_factura_compra = nc.id_factura_compra
            WHERE nc.nota_compra_estado = 'APROBADO'
              AND nc.nota_compra_emision::date BETWEEN :desde AND :hasta
        ) AS base
        GROUP BY base.id_nota_compra, base.fecha, base.nota_compra_tipo, base.numero,
                 base.timbrado, base.ruc_proveedor, base.razon_social, base.condicion
        ORDER BY base.fecha ASC, base.numero ASC
    ";

    $stmtNotas = $pdo->prepare($sqlNotas);
    $stmtNotas->execute([':desde' => $desde, ':hasta' => $hasta]);
    $notas = $stmtNotas->fetchAll();

} catch (Throwable $e) {
    echo "<script>alert('Error al generar el libro de compras: ".addslashes($e->getMessage())."');window.location.href='view.php';</script>";
    exit;
}

$registros = [];

foreach ($facturas as $fac) {
    $exento    = (int)$fac['exento'];
    $grav5     = (int)$fac['gravado5'];
    $grav10    = (int)$fac['gravado10'];
    $iva5      = (int)$fac['iva5'];
    $iva10     = (int)$fac['iva10'];
    $total     = $exento + $grav5 + $grav10;

    $registros[] = [
        'fecha'      => $fac['fecha'],
        'tipo'       => 'Factura',
        'numero'     => $fac['numero_factura'],
        'timbrado'   => $fac['timbrado'],
        'ruc'        => $fac['ruc_proveedor'],
        'razon'      => $fac['razon_social'],
        'condicion'  => $fac['condicion'] ?: 'CONTADO',
        'exento'     => $exento,
        'gravado5'   => $grav5,
        'gravado10'  => $grav10,
        'iva5'       => $iva5,
        'iva10'      => $iva10,
        'total'      => $total,
        'tipo_compra'=> 'Bienes'
    ];
}

foreach ($notas as $nota) {
    $etiqueta = (mb_strtoupper($nota['nota_compra_tipo']) === 'CREDITO') ? 'Nota de Crédito' : 'Nota de Débito';
    $exento   = (int)$nota['exento'];
    $grav5    = (int)$nota['gravado5'];
    $grav10   = (int)$nota['gravado10'];
    $iva5     = (int)$nota['iva5'];
    $iva10    = (int)$nota['iva10'];
    $total    = $exento + $grav5 + $grav10;

    $registros[] = [
        'fecha'      => $nota['fecha'],
        'tipo'       => $etiqueta,
        'numero'     => $nota['numero'],
        'timbrado'   => $nota['timbrado'],
        'ruc'        => $nota['ruc_proveedor'],
        'razon'      => $nota['razon_social'],
        'condicion'  => $nota['condicion'] ?: 'CONTADO',
        'exento'     => $exento,
        'gravado5'   => $grav5,
        'gravado10'  => $grav10,
        'iva5'       => $iva5,
        'iva10'      => $iva10,
        'total'      => $total,
        'tipo_compra'=> 'Bienes'
    ];
}

if (empty($registros)) {
    header("Location: view.php?alert=6");
    exit;
}

usort($registros, static function ($a, $b) {
    return strcmp($a['fecha'], $b['fecha']) ?: strcmp($a['tipo'], $b['tipo']) ?: strcmp($a['numero'], $b['numero']);
});

$totales = [
    'exento'   => 0,
    'gravado5' => 0,
    'gravado10'=> 0,
    'iva5'     => 0,
    'iva10'    => 0,
    'total'    => 0,
];

foreach ($registros as $row) {
    $totales['exento']    += $row['exento'];
    $totales['gravado5']  += $row['gravado5'];
    $totales['gravado10'] += $row['gravado10'];
    $totales['iva5']      += $row['iva5'];
    $totales['iva10']     += $row['iva10'];
    $totales['total']     += $row['total'];
}

$filename = sprintf('libro_compras_%s_%s.xls', str_replace('-','',$desde), str_replace('-','',$hasta));

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
echo "\xEF\xBB\xBF";

$headers = ['Fecha','Tipo','Número','Timbrado','RUC','Razón Social','Condición','Exento','Gravado 5','Gravado 10','IVA 5%','IVA 10%','Total','Tipo de compra'];

$esc = static function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

echo "<table border=\"1\">\n<thead><tr>";
foreach ($headers as $title) {
    echo '<th>'.$esc($title).'</th>';
}
echo "</tr></thead>\n<tbody>\n";

foreach ($registros as $row) {
    echo "<tr>";
    echo '<td>'.$esc($row['fecha']).'</td>';
    echo '<td>'.$esc($row['tipo']).'</td>';
    echo '<td>'.$esc($row['numero']).'</td>';
    echo '<td>'.$esc($row['timbrado']).'</td>';
    echo '<td>'.$esc($row['ruc']).'</td>';
    echo '<td>'.$esc($row['razon']).'</td>';
    echo '<td>'.$esc($row['condicion']).'</td>';
    echo '<td align="right">'.$esc(number_format($row['exento'], 0, ',', '.')).'</td>';
    echo '<td align="right">'.$esc(number_format($row['gravado5'], 0, ',', '.')).'</td>';
    echo '<td align="right">'.$esc(number_format($row['gravado10'], 0, ',', '.')).'</td>';
    echo '<td align="right">'.$esc(number_format($row['iva5'], 0, ',', '.')).'</td>';
    echo '<td align="right">'.$esc(number_format($row['iva10'], 0, ',', '.')).'</td>';
    echo '<td align="right">'.$esc(number_format($row['total'], 0, ',', '.')).'</td>';
    echo '<td>'.$esc($row['tipo_compra']).'</td>';
    echo "</tr>\n";
}

echo "<tr>";
echo '<td colspan="7"><strong>Totales</strong></td>';
echo '<td align="right"><strong>'.$esc(number_format($totales['exento'], 0, ',', '.')).'</strong></td>';
echo '<td align="right"><strong>'.$esc(number_format($totales['gravado5'], 0, ',', '.')).'</strong></td>';
echo '<td align="right"><strong>'.$esc(number_format($totales['gravado10'], 0, ',', '.')).'</strong></td>';
echo '<td align="right"><strong>'.$esc(number_format($totales['iva5'], 0, ',', '.')).'</strong></td>';
echo '<td align="right"><strong>'.$esc(number_format($totales['iva10'], 0, ',', '.')).'</strong></td>';
echo '<td align="right"><strong>'.$esc(number_format($totales['total'], 0, ',', '.')).'</strong></td>';
echo '<td></td>';
echo "</tr>\n";

echo "</tbody></table>";
exit;
