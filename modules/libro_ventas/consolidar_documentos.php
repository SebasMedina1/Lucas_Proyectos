<?php
/**
 * Consolidar Documentos para Libro de Ventas
 * 
 * Esta función consolida Facturas de Venta, Notas de Crédito y Notas de Débito
 * del período seleccionado, calculando correctamente las bases imponibles e IVA
 * por cada tasa (Exentas, 5%, 10%).
 * 
 * Reglas de signo:
 * - Facturas: impacto positivo (suman)
 * - Notas de Crédito: impacto negativo (restan)
 * - Notas de Débito: impacto positivo (suman, como factura adicional)
 * 
 * Los documentos anulados se excluyen de los cálculos, pero pueden listarse
 * marcados como "ANULADO" si se requiere trazabilidad.
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param string $desde Fecha inicio del período (YYYY-MM-DD)
 * @param string $hasta Fecha fin del período (YYYY-MM-DD)
 * @param int|null $clienteId ID del cliente para filtrar (opcional)
 * @param string|null $tipoDoc Tipo de documento: 'FACTURA', 'NOTA_CREDITO', 'NOTA_DEBITO' (opcional)
 * @param string|null $busqueda Búsqueda por número de documento o timbrado (opcional)
 * @param int|null $timbradoId ID del timbrado para filtrar (opcional)
 * @return array Array de documentos consolidados con bases e IVA por tasa
 */
function consolidarDocumentos(PDO $pdo, string $desde, string $hasta, ?int $clienteId = null, ?string $tipoDoc = null, ?string $busqueda = null, ?int $timbradoId = null): array {
    
    // ============================================
    // FACTURAS DE VENTA
    // ============================================
    $condicionesFacturas = [];
    $paramsFacturas = [':desde' => $desde, ':hasta' => $hasta];
    
    if ($clienteId !== null && $clienteId > 0) {
        $condicionesFacturas[] = "fv.id_cliente = :cliente_id";
        $paramsFacturas[':cliente_id'] = $clienteId;
    }
    
    if ($busqueda !== null && $busqueda !== '') {
        $condicionesFacturas[] = "(fv.numero_factura ILIKE :busqueda OR fv.timbrado ILIKE :busqueda)";
        $paramsFacturas[':busqueda'] = '%' . $busqueda . '%';
    }
    
    if ($timbradoId !== null && $timbradoId > 0) {
        $condicionesFacturas[] = "fv.id_timbrado = :timbrado_id";
        $paramsFacturas[':timbrado_id'] = $timbradoId;
    }
    
    $whereFacturas = !empty($condicionesFacturas) ? ' AND ' . implode(' AND ', $condicionesFacturas) : '';
    
    // Obtener facturas del período
    // Excluir facturas anuladas (estado = 'ANULADA' o factura_estado = 'ANULADA')
    $sqlFacturas = "
        SELECT 
            fv.id_factura_venta,
            fv.numero_factura,
            COALESCE(fv.timbrado, '') AS timbrado,
            fv.id_timbrado,
            COALESCE(fv.fecha_emision, fv.fecha_factura) AS fecha,
            fv.estado,
            fv.factura_estado,
            COALESCE(fv.subtotal, 0) AS subtotal,
            COALESCE(fv.iva_5, 0) AS iva_5,
            COALESCE(fv.iva_10, 0) AS iva_10,
            COALESCE(fv.iva_exento, 0) AS iva_exento,
            COALESCE(fv.total_general, fv.factura_total, 0) AS total_general,
            c.cliente_nombre || ' ' || c.cliente_apellido AS cliente_nombre,
            COALESCE(c.cliente_ruc, '') AS cliente_ruc,
            ct.punto_expedicion,
            'FACTURA' AS tipo_documento
        FROM factura_ventas fv
        JOIN clientes c ON c.id_cliente = fv.id_cliente
        LEFT JOIN caja_timbrado ct ON ct.id_timbrado = fv.id_timbrado AND ct.id_caja = (
            SELECT acc.id_caja 
            FROM apertura_cierre_caja acc 
            WHERE acc.id_apertura = fv.id_apertura_cierre 
            LIMIT 1
        )
        WHERE (fv.estado IS NULL OR fv.estado != 'ANULADA')
          AND fv.factura_estado = 'EMITIDA'
          AND COALESCE(fv.fecha_emision, fv.fecha_factura) BETWEEN :desde AND :hasta
          {$whereFacturas}
        ORDER BY COALESCE(fv.fecha_emision, fv.fecha_factura) ASC, fv.numero_factura ASC
    ";
    
    $stFacturas = $pdo->prepare($sqlFacturas);
    $stFacturas->execute($paramsFacturas);
    $facturas = $stFacturas->fetchAll();
    
    // Filtrar por tipo si es necesario
    if ($tipoDoc === 'FACTURA') {
        // Ya está filtrado, solo facturas
    } elseif ($tipoDoc !== null && $tipoDoc !== '') {
        $facturas = []; // Si el filtro es NC o ND, no incluir facturas
    }
    
    // ============================================
    // NOTAS DE CRÉDITO
    // ============================================
    $condicionesNC = [];
    $paramsNC = [':desde' => $desde, ':hasta' => $hasta];
    
    if ($clienteId !== null && $clienteId > 0) {
        $condicionesNC[] = "nv.id_cliente = :cliente_id";
        $paramsNC[':cliente_id'] = $clienteId;
    }
    
    if ($busqueda !== null && $busqueda !== '') {
        $condicionesNC[] = "(nv.nota_nro::TEXT ILIKE :busqueda OR nv.nota_venta_timbrado ILIKE :busqueda)";
        $paramsNC[':busqueda'] = '%' . $busqueda . '%';
    }
    
    if ($timbradoId !== null && $timbradoId > 0) {
        // Para notas, necesitamos obtener el timbrado desde la factura relacionada
        $condicionesNC[] = "fv.id_timbrado = :timbrado_id";
        $paramsNC[':timbrado_id'] = $timbradoId;
    }
    
    $whereNC = !empty($condicionesNC) ? ' AND ' . implode(' AND ', $condicionesNC) : '';
    
    // Obtener notas de crédito del período
    // Excluir notas anuladas (nota_venta_estado != 'ANULADA')
    $sqlNotasCredito = "
        SELECT 
            nv.id_nota_venta,
            nv.nota_nro::TEXT AS numero_documento,
            COALESCE(nv.nota_venta_timbrado, '') AS timbrado,
            fv.id_timbrado,
            COALESCE(nv.nota_venta_emision, nv.nota_venta_fecha, nv.fecha_emision) AS fecha,
            nv.nota_venta_estado AS estado,
            COALESCE(nv.subtotal, 0) AS subtotal,
            COALESCE(nv.iva_5, 0) AS iva_5,
            COALESCE(nv.iva_10, 0) AS iva_10,
            COALESCE(nv.iva_exento, 0) AS iva_exento,
            COALESCE(nv.nota_total, nv.monto_total, 0) AS total_general,
            c.cliente_nombre || ' ' || c.cliente_apellido AS cliente_nombre,
            COALESCE(c.cliente_ruc, '') AS cliente_ruc,
            'NOTA_CREDITO' AS tipo_documento,
            fv.numero_factura AS factura_referencia,
            ct.punto_expedicion
        FROM nota_venta nv
        JOIN clientes c ON c.id_cliente = nv.id_cliente
        LEFT JOIN factura_ventas fv ON fv.id_factura_venta = nv.id_factura_venta
        LEFT JOIN caja_timbrado ct ON ct.id_timbrado = fv.id_timbrado AND ct.id_caja = (
            SELECT acc.id_caja 
            FROM apertura_cierre_caja acc 
            WHERE acc.id_apertura = fv.id_apertura_cierre 
            LIMIT 1
        )
        WHERE nv.nota_venta_tipo = 'CREDITO'
          AND nv.nota_venta_estado = 'EMITIDA'
          AND COALESCE(nv.nota_venta_emision, nv.nota_venta_fecha, nv.fecha_emision) BETWEEN :desde AND :hasta
          {$whereNC}
        ORDER BY COALESCE(nv.nota_venta_emision, nv.nota_venta_fecha, nv.fecha_emision) ASC, nv.nota_nro ASC
    ";
    
    $stNotasCredito = $pdo->prepare($sqlNotasCredito);
    $stNotasCredito->execute($paramsNC);
    $notasCredito = $stNotasCredito->fetchAll();
    
    // Filtrar por tipo si es necesario
    if ($tipoDoc === 'NOTA_CREDITO') {
        // Ya está filtrado, solo NC
    } elseif ($tipoDoc !== null && $tipoDoc !== '' && $tipoDoc !== 'NOTA_DEBITO') {
        $notasCredito = []; // Si el filtro es Factura o ND, no incluir NC
    }
    
    // ============================================
    // NOTAS DE DÉBITO
    // ============================================
    $condicionesND = [];
    $paramsND = [':desde' => $desde, ':hasta' => $hasta];
    
    if ($clienteId !== null && $clienteId > 0) {
        $condicionesND[] = "nv.id_cliente = :cliente_id";
        $paramsND[':cliente_id'] = $clienteId;
    }
    
    if ($busqueda !== null && $busqueda !== '') {
        $condicionesND[] = "(nv.nota_nro::TEXT ILIKE :busqueda OR nv.nota_venta_timbrado ILIKE :busqueda)";
        $paramsND[':busqueda'] = '%' . $busqueda . '%';
    }
    
    if ($timbradoId !== null && $timbradoId > 0) {
        $condicionesND[] = "fv.id_timbrado = :timbrado_id";
        $paramsND[':timbrado_id'] = $timbradoId;
    }
    
    $whereND = !empty($condicionesND) ? ' AND ' . implode(' AND ', $condicionesND) : '';
    
    // Obtener notas de débito del período
    // Excluir notas anuladas (nota_venta_estado != 'ANULADA')
    $sqlNotasDebito = "
        SELECT 
            nv.id_nota_venta,
            nv.nota_nro::TEXT AS numero_documento,
            COALESCE(nv.nota_venta_timbrado, '') AS timbrado,
            fv.id_timbrado,
            COALESCE(nv.nota_venta_emision, nv.nota_venta_fecha, nv.fecha_emision) AS fecha,
            nv.nota_venta_estado AS estado,
            COALESCE(nv.subtotal, 0) AS subtotal,
            COALESCE(nv.iva_5, 0) AS iva_5,
            COALESCE(nv.iva_10, 0) AS iva_10,
            COALESCE(nv.iva_exento, 0) AS iva_exento,
            COALESCE(nv.nota_total, nv.monto_total, 0) AS total_general,
            c.cliente_nombre || ' ' || c.cliente_apellido AS cliente_nombre,
            COALESCE(c.cliente_ruc, '') AS cliente_ruc,
            'NOTA_DEBITO' AS tipo_documento,
            fv.numero_factura AS factura_referencia,
            ct.punto_expedicion
        FROM nota_venta nv
        JOIN clientes c ON c.id_cliente = nv.id_cliente
        LEFT JOIN factura_ventas fv ON fv.id_factura_venta = nv.id_factura_venta
        LEFT JOIN caja_timbrado ct ON ct.id_timbrado = fv.id_timbrado AND ct.id_caja = (
            SELECT acc.id_caja 
            FROM apertura_cierre_caja acc 
            WHERE acc.id_apertura = fv.id_apertura_cierre 
            LIMIT 1
        )
        WHERE nv.nota_venta_tipo = 'DEBITO'
          AND nv.nota_venta_estado = 'EMITIDA'
          AND COALESCE(nv.nota_venta_emision, nv.nota_venta_fecha, nv.fecha_emision) BETWEEN :desde AND :hasta
          {$whereND}
        ORDER BY COALESCE(nv.nota_venta_emision, nv.nota_venta_fecha, nv.fecha_emision) ASC, nv.nota_nro ASC
    ";
    
    $stNotasDebito = $pdo->prepare($sqlNotasDebito);
    $stNotasDebito->execute($paramsND);
    $notasDebito = $stNotasDebito->fetchAll();
    
    // Filtrar por tipo si es necesario
    if ($tipoDoc === 'NOTA_DEBITO') {
        // Ya está filtrado, solo ND
    } elseif ($tipoDoc !== null && $tipoDoc !== '' && $tipoDoc !== 'NOTA_CREDITO') {
        $notasDebito = []; // Si el filtro es Factura o NC, no incluir ND
    }
    
    // ============================================
    // CONSOLIDAR DOCUMENTOS Y CALCULAR BASES
    // ============================================
    $documentos = [];
    
    /**
     * Calcular bases imponibles desde el detalle del documento
     * 
     * Para cada documento, calculamos las bases imponibles desde el detalle
     * para mayor precisión. Si no hay detalle disponible, calculamos desde
     * los montos de IVA (base = IVA / tasa).
     * 
     * Bases por tasa:
     * - Exentas: monto exento (ya es la base)
     * - 5%: base = IVA_5 / 0.05
     * - 10%: base = IVA_10 / 0.10
     */
    
    // Agregar facturas (impacto positivo: signo = 1)
    foreach ($facturas as $fact) {
        $iva5 = (float)$fact['iva_5'];
        $iva10 = (float)$fact['iva_10'];
        $ivaExento = (float)$fact['iva_exento'];
        
        // Calcular bases imponibles desde IVA
        // Base 5% = IVA_5 / 0.05 (si IVA es 5%, la base es IVA / tasa)
        $base5 = $iva5 > 0 ? ($iva5 / 0.05) : 0;
        
        // Base 10% = IVA_10 / 0.10
        $base10 = $iva10 > 0 ? ($iva10 / 0.10) : 0;
        
        // Base exenta = monto exento (ya es la base)
        $baseExento = $ivaExento;
        
        // Número completo con punto de expedición (formato: EEE-PPP-NNNNNNN)
        $numeroCompleto = $fact['numero_factura'];
        if (!empty($fact['punto_expedicion']) && strpos($numeroCompleto, '-') === false) {
            // Si el número no tiene formato completo, agregar punto de expedición
            $numeroCompleto = $fact['punto_expedicion'] . '-' . $numeroCompleto;
        }
        
        $documentos[] = [
            'id' => $fact['id_factura_venta'],
            'tipo' => 'FACTURA',
            'numero' => $numeroCompleto,
            'timbrado' => $fact['timbrado'],
            'id_timbrado' => $fact['id_timbrado'],
            'fecha' => $fact['fecha'],
            'cliente' => $fact['cliente_nombre'],
            'ruc' => $fact['cliente_ruc'],
            'exento' => $baseExento,
            'base_5' => $base5,
            'iva_5' => $iva5,
            'base_10' => $base10,
            'iva_10' => $iva10,
            'total' => (float)$fact['total_general'],
            'signo' => 1, // Factura: impacto positivo
            'estado' => $fact['estado'] ?? 'EMITIDA'
        ];
    }
    
    // Agregar notas de crédito (impacto negativo: signo = -1)
    foreach ($notasCredito as $nc) {
        $iva5 = (float)$nc['iva_5'];
        $iva10 = (float)$nc['iva_10'];
        $ivaExento = (float)$nc['iva_exento'];
        
        // Calcular bases imponibles desde IVA
        $base5 = $iva5 > 0 ? ($iva5 / 0.05) : 0;
        $base10 = $iva10 > 0 ? ($iva10 / 0.10) : 0;
        $baseExento = $ivaExento;
        
        // Número completo con punto de expedición
        $numeroCompleto = $nc['numero_documento'];
        if (!empty($nc['punto_expedicion']) && strpos($numeroCompleto, '-') === false) {
            $numeroCompleto = $nc['punto_expedicion'] . '-' . $numeroCompleto;
        }
        
        $documentos[] = [
            'id' => $nc['id_nota_venta'],
            'tipo' => 'NOTA_CREDITO',
            'numero' => $numeroCompleto,
            'timbrado' => $nc['timbrado'],
            'id_timbrado' => $nc['id_timbrado'],
            'fecha' => $nc['fecha'],
            'cliente' => $nc['cliente_nombre'],
            'ruc' => $nc['cliente_ruc'],
            'exento' => $baseExento,
            'base_5' => $base5,
            'iva_5' => $iva5,
            'base_10' => $base10,
            'iva_10' => $iva10,
            'total' => (float)$nc['total_general'],
            'signo' => -1, // Nota de Crédito: impacto negativo (resta)
            'referencia' => $nc['factura_referencia'] ?? '',
            'estado' => $nc['estado'] ?? 'EMITIDA'
        ];
    }
    
    // Agregar notas de débito (impacto positivo: signo = 1, como factura adicional)
    foreach ($notasDebito as $nd) {
        $iva5 = (float)$nd['iva_5'];
        $iva10 = (float)$nd['iva_10'];
        $ivaExento = (float)$nd['iva_exento'];
        
        // Calcular bases imponibles desde IVA
        $base5 = $iva5 > 0 ? ($iva5 / 0.05) : 0;
        $base10 = $iva10 > 0 ? ($iva10 / 0.10) : 0;
        $baseExento = $ivaExento;
        
        // Número completo con punto de expedición
        $numeroCompleto = $nd['numero_documento'];
        if (!empty($nd['punto_expedicion']) && strpos($numeroCompleto, '-') === false) {
            $numeroCompleto = $nd['punto_expedicion'] . '-' . $numeroCompleto;
        }
        
        $documentos[] = [
            'id' => $nd['id_nota_venta'],
            'tipo' => 'NOTA_DEBITO',
            'numero' => $numeroCompleto,
            'timbrado' => $nd['timbrado'],
            'id_timbrado' => $nd['id_timbrado'],
            'fecha' => $nd['fecha'],
            'cliente' => $nd['cliente_nombre'],
            'ruc' => $nd['cliente_ruc'],
            'exento' => $baseExento,
            'base_5' => $base5,
            'iva_5' => $iva5,
            'base_10' => $base10,
            'iva_10' => $iva10,
            'total' => (float)$nd['total_general'],
            'signo' => 1, // Nota de Débito: impacto positivo (suma, como factura)
            'referencia' => $nd['factura_referencia'] ?? '',
            'estado' => $nd['estado'] ?? 'EMITIDA'
        ];
    }
    
    // Ordenar por fecha, luego por tipo, luego por número
    usort($documentos, function($a, $b) {
        $cmpFecha = strcmp($a['fecha'], $b['fecha']);
        if ($cmpFecha !== 0) return $cmpFecha;
        
        $cmpTipo = strcmp($a['tipo'], $b['tipo']);
        if ($cmpTipo !== 0) return $cmpTipo;
        
        return strcmp($a['numero'], $b['numero']);
    });
    
    return $documentos;
}
