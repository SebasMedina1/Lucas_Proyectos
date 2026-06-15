<?php
require "../../config/database.php";

header('Content-Type: application/json; charset=UTF-8');

if (isset($_GET['apertura_id'])) {
    try {
        $apertura_id = (int)$_GET['apertura_id'];

        if ($apertura_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID de apertura inválido']);
            exit;
        }

        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Obtener información de la apertura
        $queryApertura = $pdo->prepare("
            SELECT monto_apertura AS monto_inicial, fecha_apertura, hora_apertura
            FROM apertura_cierre_caja
            WHERE id_apertura = :apertura_id
            LIMIT 1
        ");
        $queryApertura->execute([':apertura_id' => $apertura_id]);
        $apertura = $queryApertura->fetch(PDO::FETCH_ASSOC);

        if (!$apertura) {
            echo json_encode(['success' => false, 'error' => 'Apertura no encontrada']);
            exit;
        }

        // Obtener cobros desde factura_ventas (facturas de contado)
        // Usar id_apertura_cierre (FK a apertura_cierre_caja.id_apertura)
        $queryFacturas = $pdo->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN tipo_pago = 'EFECTIVO' THEN total_general ELSE 0 END), 0) AS total_efectivo_facturas,
                COALESCE(SUM(CASE WHEN tipo_pago = 'TARJETA' THEN total_general ELSE 0 END), 0) AS total_tarjeta_facturas,
                COALESCE(SUM(CASE WHEN tipo_pago = 'TRANSFERENCIA' THEN total_general ELSE 0 END), 0) AS total_transferencia_facturas,
                COALESCE(SUM(CASE WHEN tipo_pago = 'CHEQUE' THEN total_general ELSE 0 END), 0) AS total_cheque_facturas,
                COALESCE(SUM(CASE WHEN tipo_pago = 'BILLETERA' THEN total_general ELSE 0 END), 0) AS total_billetera_facturas
            FROM factura_ventas
            WHERE id_apertura_cierre = :apertura_id
              AND estado = 'EMITIDA'
        ");
        $queryFacturas->execute([':apertura_id' => $apertura_id]);
        $totalesFacturas = $queryFacturas->fetch(PDO::FETCH_ASSOC);
        
        // Obtener cobros desde tabla cobros (cobranzas)
        // Nota: cobros.id_apertura referencia a apertura_cierre_caja.id_apertura
        $queryCobros = $pdo->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN cd.tipo_pago = 'EFECTIVO' THEN cd.importe_aplicado ELSE 0 END), 0) AS total_efectivo_cobros,
                COALESCE(SUM(CASE WHEN cd.tipo_pago = 'TARJETA' THEN cd.importe_aplicado ELSE 0 END), 0) AS total_tarjeta_cobros,
                COALESCE(SUM(CASE WHEN cd.tipo_pago = 'TRANSFERENCIA' THEN cd.importe_aplicado ELSE 0 END), 0) AS total_transferencia_cobros,
                COALESCE(SUM(CASE WHEN cd.tipo_pago = 'CHEQUE' THEN cd.importe_aplicado ELSE 0 END), 0) AS total_cheque_cobros,
                COALESCE(SUM(CASE WHEN cd.tipo_pago = 'BILLETERA' THEN cd.importe_aplicado ELSE 0 END), 0) AS total_billetera_cobros
            FROM cobros c
            JOIN cobros_detalle cd ON cd.id_cobro = c.id_cobro
            WHERE c.id_apertura = :apertura_id
              AND c.estado = 'REGISTRADO'
        ");
        $queryCobros->execute([':apertura_id' => $apertura_id]);
        $totalesCobros = $queryCobros->fetch(PDO::FETCH_ASSOC);
        
        // Combinar totales de facturas y cobros
        $totales = [
            'total_efectivo' => (float)($totalesFacturas['total_efectivo_facturas'] ?? 0) + (float)($totalesCobros['total_efectivo_cobros'] ?? 0),
            'total_tarjeta' => (float)($totalesFacturas['total_tarjeta_facturas'] ?? 0) + (float)($totalesCobros['total_tarjeta_cobros'] ?? 0),
            'total_transferencia' => (float)($totalesFacturas['total_transferencia_facturas'] ?? 0) + (float)($totalesCobros['total_transferencia_cobros'] ?? 0),
            'total_cheque' => (float)($totalesFacturas['total_cheque_facturas'] ?? 0) + (float)($totalesCobros['total_cheque_cobros'] ?? 0),
            'total_billetera' => (float)($totalesFacturas['total_billetera_facturas'] ?? 0) + (float)($totalesCobros['total_billetera_cobros'] ?? 0)
        ];
        
        $totales['total_general'] = $totales['total_efectivo'] + $totales['total_tarjeta'] + 
                                   $totales['total_transferencia'] + $totales['total_cheque'] + 
                                   $totales['total_billetera'];

        // Si no hay resultados, inicializar en 0
        if (!$totalesFacturas) {
            $totalesFacturas = [
                'total_efectivo_facturas' => 0,
                'total_tarjeta_facturas' => 0,
                'total_transferencia_facturas' => 0,
                'total_cheque_facturas' => 0,
                'total_billetera_facturas' => 0
            ];
        }
        
        if (!$totalesCobros) {
            $totalesCobros = [
                'total_efectivo_cobros' => 0,
                'total_tarjeta_cobros' => 0,
                'total_transferencia_cobros' => 0,
                'total_cheque_cobros' => 0,
                'total_billetera_cobros' => 0
            ];
        }
        
        // Combinar totales de facturas y cobros
        $totales = [
            'total_efectivo' => (float)($totalesFacturas['total_efectivo_facturas'] ?? 0) + (float)($totalesCobros['total_efectivo_cobros'] ?? 0),
            'total_tarjeta' => (float)($totalesFacturas['total_tarjeta_facturas'] ?? 0) + (float)($totalesCobros['total_tarjeta_cobros'] ?? 0),
            'total_transferencia' => (float)($totalesFacturas['total_transferencia_facturas'] ?? 0) + (float)($totalesCobros['total_transferencia_cobros'] ?? 0),
            'total_cheque' => (float)($totalesFacturas['total_cheque_facturas'] ?? 0) + (float)($totalesCobros['total_cheque_cobros'] ?? 0),
            'total_billetera' => (float)($totalesFacturas['total_billetera_facturas'] ?? 0) + (float)($totalesCobros['total_billetera_cobros'] ?? 0)
        ];
        
        $totales['total_general'] = $totales['total_efectivo'] + $totales['total_tarjeta'] + 
                                   $totales['total_transferencia'] + $totales['total_cheque'] + 
                                   $totales['total_billetera'];

        echo json_encode([
            'success' => true,
            'apertura' => $apertura,
            'totales' => [
                'efectivo' => (float)$totales['total_efectivo'],
                'tarjeta' => (float)$totales['total_tarjeta'],
                'transferencia' => (float)$totales['total_transferencia'],
                'cheque' => (float)$totales['total_cheque'],
                'billetera' => (float)$totales['total_billetera'],
                'general' => (float)$totales['total_general']
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error al consultar los cobros: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'No se proporcionó el ID de la apertura']);
}
?>

