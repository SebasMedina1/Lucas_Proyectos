<?php
/**
 * Router de Informes
 * 
 * Recibe: movimiento, estado, desde, hasta (GET) y redirige al generar_informe.php correspondiente.
 * 
 * Soporta movimientos de:
 * - Compras: PEDIDO, PRESUPUESTO, ORDEN_COMPRA, FACTURA, NOTA_CREDITO, NOTA_DEBITO, NOTA_REMISION, AJUSTES, LIBRO_COMPRAS
 * - Ventas: PEDIDO_VENTA, PRESUPUESTO_VENTA, FACTURA_VENTA, NOTA_CREDITO_VENTA, LIBRO_VENTAS
 */

session_start();
require_once realpath(__DIR__ . '/../../config/app_modules.php');

function fail($msg, $back = 'view.php') {
    echo "<script>alert(".json_encode($msg)."); window.location.href=".json_encode($back).";</script>";
    exit;
}

// Validaciones básicas
$movimiento = isset($_GET['movimiento']) ? trim($_GET['movimiento']) : '';
$estado     = isset($_GET['estado'])     ? trim($_GET['estado'])     : '';
$desde      = isset($_GET['desde'])      ? trim($_GET['desde'])      : '';
$hasta      = isset($_GET['hasta'])      ? trim($_GET['hasta'])      : '';

if ($movimiento === '' || $desde === '' || $hasta === '') {
    fail('Faltan parámetros obligatorios: movimiento y rango de fechas.');
}

// Validar fechas YYYY-MM-DD
$reFecha = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($reFecha, $desde) || !preg_match($reFecha, $hasta)) {
    fail('Formato de fecha inválido. Use YYYY-MM-DD.');
}
if ($hasta < $desde) {
    fail('La fecha "Hasta" no puede ser anterior a "Desde".');
}

// Mapeo de movimientos a sus archivos generar_informe.php correspondientes
$movPermitidos = [
    // === COMPRAS ===
    'PEDIDO'        => '../pedido_compra/generar_informe.php',
    'PRESUPUESTO'   => '../presupuesto/generar_informe.php',
    'ORDEN_COMPRA'  => '../orden_compra/generar_informe.php',
    'FACTURA'       => '../gestionar_compras/generar_informe.php',
    'NOTA_CREDITO'  => '../nota_credito/generar_informe.php',
    'NOTA_DEBITO'   => '../nota_debito/generar_informe.php',
    'NOTA_REMISION' => '../nota_remision/generar_informe.php',
    'AJUSTES'       => '../ajustes/generar_informe.php',
    'LIBRO_COMPRAS' => 'libro_compras.php',
];

if (UI_MODULO_VENTAS) {
    $movPermitidos += [
        'PEDIDO_VENTA'       => '../pedido_venta/generar_informe.php',
        'PRESUPUESTO_VENTA'  => '../presupuesto_venta/generar_informe.php',
        'FACTURA_VENTA'      => '../gestionar_ventas/generar_informe.php',
        'NOTA_CREDITO_VENTA' => '../nota_credito_venta/generar_informe.php',
        'LIBRO_VENTAS'       => '../libro_ventas/exportar_pdf.php',
    ];
}

// Estados permitidos según los módulos
$estadoPermitidos = [
    // Compras
    'PENDIENTE', 'APROBADO', 'ANULADO', 'FINALIZADO', 'EMITIDA', 'FACTURADA',
    // Ventas
    'FACTURADO', 'ANULADA', 'PAGADA',
    // Generales
    'REPORTE TOTAL', 'TOTAL'
];

$movKey   = mb_strtoupper($movimiento);
$estadoUp = '';

if (!isset($movPermitidos[$movKey])) {
    fail('Movimiento no reconocido.');
}

// Los libros no requieren estado
if ($movKey === 'LIBRO_COMPRAS' || (UI_MODULO_VENTAS && $movKey === 'LIBRO_VENTAS')) {
    $estadoUp = '';
} else {
    if ($estado === '') {
        fail('Debe seleccionar un estado para el movimiento elegido.');
    }
    $estadoUp = mb_strtoupper($estado);
    if (!in_array($estadoUp, $estadoPermitidos, true)) {
        fail('Estado no reconocido.');
    }
}

// Armar destino con querystring
$destino = $movPermitidos[$movKey];

// Construir query segura
$params = [
    'desde'  => $desde,
    'hasta'  => $hasta,
];
if ($estadoUp !== '') {
    $params['estado'] = $estadoUp;
}

// Redirigir (el form ya viene con target="_blank")
$qs = http_build_query($params);
header("Location: {$destino}?{$qs}");
exit;
