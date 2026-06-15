<?php
session_start();
require "../../config/database.php";

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$clienteId = (int)($_GET['cliente_id'] ?? 0);

if ($clienteId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Cliente inválido']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $pdo->exec("SET TIME ZONE 'America/Asuncion'");
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión']);
    exit;
}

try {
    // Obtener facturas del cliente con saldo pendiente
    // Verificar tanto estado como factura_estado para facturas EMITIDAS
    $st = $pdo->prepare("
        SELECT 
            fv.id_factura_venta,
            COALESCE(fv.numero_factura, fv.factura_numero, 'N/A') AS numero_factura,
            fv.fecha_factura,
            fv.tipo_factura,
            COALESCE(fv.total_general, fv.factura_total, 0) AS total_general,
            COALESCE(cc.saldo_pendiente, fv.total_general, fv.factura_total, 0) AS saldo_pendiente,
            fv.estado,
            fv.factura_estado
        FROM factura_ventas fv
        LEFT JOIN cuentas_cobrar cc ON cc.id_factura_venta = fv.id_factura_venta
        WHERE fv.id_cliente = :cliente_id
          AND fv.factura_estado = 'EMITIDA'
          AND (fv.estado IS NULL OR fv.estado != 'ANULADA')
          AND (
              (fv.tipo_factura = 'CONTADO' AND COALESCE(fv.total_general, fv.factura_total, 0) > 0)
              OR 
              (fv.tipo_factura = 'CREDITO' AND COALESCE(cc.saldo_pendiente, fv.total_general, fv.factura_total, 0) > 0)
          )
        ORDER BY fv.fecha_factura DESC, fv.numero_factura DESC
    ");
    $st->execute([':cliente_id' => $clienteId]);
    $facturas = $st->fetchAll();
    
    echo json_encode([
        'success' => true,
        'facturas' => $facturas
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al consultar facturas: ' . $e->getMessage()
    ]);
}

