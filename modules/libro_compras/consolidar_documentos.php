<?php
/**
 * Consolida documentos fiscales (Facturas, NC, ND) del período para el Libro de Compras
 * Retorna array de documentos con sus cálculos de IVA
 */
function consolidarDocumentos(PDO $pdo, string $desde, string $hasta, ?int $proveedorId = null, ?string $tipoDoc = null, ?int $sucursalId = null): array {
  $documentos = [];
  
  // ===== FACTURAS DE COMPRA =====
  $sqlFacturas = "
    SELECT
      fc.id_factura_compra,
      fc.fact_fecha_compra AS fecha,
      fc.numero_factura,
      COALESCE(fc.timbrado, '') AS timbrado,
      pr.ruc_proveedor,
      pr.razon_social,
      COALESCE(fc.tipo_operacion, 'CONTADO') AS condicion,
      COALESCE(iv.iva_exento, 0) AS exento,
      COALESCE(iv.iva_5, 0) AS iva5,
      COALESCE(iv.iva_10, 0) AS iva10,
      COALESCE(fc.fac_total, 0) AS total,
      s.descripcion_sucursal AS sucursal
    FROM factura_compra fc
    JOIN orden_de_compra oc ON oc.id_orden_compra = fc.id_orden_compra
    JOIN proveedor pr ON pr.id_proveedor = oc.id_proveedor
    LEFT JOIN iva_compra iv ON iv.id_factura_compra = fc.id_factura_compra
    LEFT JOIN sucursales s ON s.id_sucursal = fc.id_sucursal
    WHERE UPPER(TRIM(fc.fac_estado)) = 'EMITIDA'
      AND fc.fact_fecha_compra BETWEEN :desde AND :hasta
  ";
  
  $params = [':desde' => $desde, ':hasta' => $hasta];
  
  if ($proveedorId !== null && $proveedorId > 0) {
    $sqlFacturas .= " AND pr.id_proveedor = :proveedor";
    $params[':proveedor'] = $proveedorId;
  }
  
  if ($sucursalId !== null && $sucursalId > 0) {
    $sqlFacturas .= " AND fc.id_sucursal = :sucursal";
    $params[':sucursal'] = $sucursalId;
  }
  
  $sqlFacturas .= " ORDER BY fc.fact_fecha_compra ASC, fc.numero_factura ASC";
  
  $stmtFact = $pdo->prepare($sqlFacturas);
  $stmtFact->execute($params);
  $facturas = $stmtFact->fetchAll();
  
  foreach ($facturas as $fac) {
    $iva5 = (int)$fac['iva5'];
    $iva10 = (int)$fac['iva10'];
    $exento = (int)$fac['exento'];
    $gravado5 = $iva5 * 21;  // Base gravada al 5%
    $gravado10 = $iva10 * 11; // Base gravada al 10%
    $total = $exento + $gravado5 + $gravado10;
    
    $documentos[] = [
      'id' => (int)$fac['id_factura_compra'],
      'fecha' => $fac['fecha'],
      'tipo' => 'FACTURA',
      'numero' => $fac['numero_factura'],
      'timbrado' => $fac['timbrado'],
      'ruc' => $fac['ruc_proveedor'],
      'razon' => $fac['razon_social'],
      'condicion' => $fac['condicion'],
      'exento' => $exento,
      'base_5' => $gravado5,
      'iva_5' => $iva5,
      'base_10' => $gravado10,
      'iva_10' => $iva10,
      'total' => $total,
      'sucursal' => $fac['sucursal'] ?? 'N/D',
      'signo' => 1, // Facturas siempre positivas
      'id_documento' => (int)$fac['id_factura_compra'],
      'tipo_documento' => 'FACTURA'
    ];
  }
  
  // ===== NOTAS DE CRÉDITO Y DÉBITO =====
  $sqlNotas = "
    SELECT
      nc.id_nota_compra,
      nc.nota_compra_fecha AS fecha,
      nc.nota_compra_tipo,
      nc.nota_nro AS numero,
      COALESCE(nc.nota_compra_timbrado, '') AS timbrado,
      pr.ruc_proveedor,
      pr.razon_social,
      COALESCE(fc.tipo_operacion, 'CONTADO') AS condicion,
      nc.nota_total,
      s.descripcion_sucursal AS sucursal,
      nc.id_factura_compra
    FROM nota_compra nc
    JOIN proveedor pr ON pr.id_proveedor = nc.id_proveedor
    LEFT JOIN factura_compra fc ON fc.id_factura_compra = nc.id_factura_compra
    LEFT JOIN sucursales s ON s.id_sucursal = nc.id_sucursal
    WHERE UPPER(TRIM(nc.nota_compra_estado)) = 'EMITIDA'
      AND nc.nota_compra_fecha BETWEEN :desde AND :hasta
  ";
  
  $paramsNotas = [':desde' => $desde, ':hasta' => $hasta];
  
  if ($proveedorId !== null && $proveedorId > 0) {
    $sqlNotas .= " AND pr.id_proveedor = :proveedor";
    $paramsNotas[':proveedor'] = $proveedorId;
  }
  
  if ($sucursalId !== null && $sucursalId > 0) {
    $sqlNotas .= " AND nc.id_sucursal = :sucursal";
    $paramsNotas[':sucursal'] = $sucursalId;
  }
  
  if ($tipoDoc !== null && $tipoDoc !== '') {
    $sqlNotas .= " AND UPPER(nc.nota_compra_tipo) = UPPER(:tipo)";
    $paramsNotas[':tipo'] = $tipoDoc;
  }
  
  $sqlNotas .= " ORDER BY nc.nota_compra_fecha ASC, nc.nota_nro ASC";
  
  $stmtNotas = $pdo->prepare($sqlNotas);
  $stmtNotas->execute($paramsNotas);
  $notas = $stmtNotas->fetchAll();
  
  foreach ($notas as $nota) {
    $notaTipo = strtoupper(trim($nota['nota_compra_tipo']));
    $signo = ($notaTipo === 'CREDITO') ? -1 : 1;
    
    // Calcular IVA desde el detalle de la nota
    $sqlDetNota = "
      SELECT 
        ndc.nota_compra_cantidad,
        ndc.nota_precio,
        COALESCE(ndc.tipo_iva, 0) AS tipo_iva
      FROM nota_detalle_compra ndc
      WHERE ndc.id_nota_compra = :id
    ";
    $stmtDet = $pdo->prepare($sqlDetNota);
    $stmtDet->execute([':id' => $nota['id_nota_compra']]);
    $detalles = $stmtDet->fetchAll();
    
    $exento = 0;
    $gravado5 = 0;
    $gravado10 = 0;
    $iva5 = 0;
    $iva10 = 0;
    
    foreach ($detalles as $det) {
      $subtotal = (int)$det['nota_compra_cantidad'] * (int)$det['nota_precio'];
      $tipoIva = (int)$det['tipo_iva'];
      
      if ($tipoIva === 5) {
        $gravado5 += $subtotal * $signo;
        $iva5 += (int)floor($subtotal / 21) * $signo;
      } elseif ($tipoIva === 10) {
        $gravado10 += $subtotal * $signo;
        $iva10 += (int)floor($subtotal / 11) * $signo;
      } else {
        $exento += $subtotal * $signo;
      }
    }
    
    $total = $exento + $gravado5 + $gravado10;
    $etiqueta = ($notaTipo === 'CREDITO') ? 'NOTA_CREDITO' : 'NOTA_DEBITO';
    
    $documentos[] = [
      'id' => (int)$nota['id_nota_compra'],
      'fecha' => $nota['fecha'],
      'tipo' => $etiqueta,
      'numero' => (string)$nota['numero'],
      'timbrado' => $nota['timbrado'],
      'ruc' => $nota['ruc_proveedor'],
      'razon' => $nota['razon_social'],
      'condicion' => $nota['condicion'],
      'exento' => $exento,
      'base_5' => $gravado5,
      'iva_5' => $iva5,
      'base_10' => $gravado10,
      'iva_10' => $iva10,
      'total' => $total,
      'sucursal' => $nota['sucursal'] ?? 'N/D',
      'signo' => $signo,
      'id_documento' => (int)$nota['id_nota_compra'],
      'tipo_documento' => $notaTipo
    ];
  }
  
  // Ordenar por fecha, tipo, número
  usort($documentos, function($a, $b) {
    $cmp = strcmp($a['fecha'], $b['fecha']);
    if ($cmp !== 0) return $cmp;
    $cmp = strcmp($a['tipo'], $b['tipo']);
    if ($cmp !== 0) return $cmp;
    return strcmp((string)$a['numero'], (string)$b['numero']);
  });
  
  return $documentos;
}
?>

