<?php
session_start();
require "../../config/database.php";

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$cobroId = (int)($_GET['id'] ?? 0);

if ($cobroId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de cobro inválido']);
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
    // Obtener cabecera del cobro
    $stCobro = $pdo->prepare("
        SELECT 
            c.id_cobro,
            c.numero_recibo,
            c.fecha_cobro,
            c.hora_cobro,
            c.total_cobrado,
            c.vuelto,
            c.efectivo_recibido,
            c.estado,
            c.observaciones,
            cl.cliente_nombre || ' ' || cl.cliente_apellido AS cliente_nombre,
            u.username
        FROM cobros c
        JOIN clientes cl ON cl.id_cliente = c.id_cliente
        JOIN usuarios u ON u.id_usuario = c.id_usuario
        WHERE c.id_cobro = :id
        LIMIT 1
    ");
    $stCobro->execute([':id' => $cobroId]);
    $cobro = $stCobro->fetch();
    
    if (!$cobro) {
        echo json_encode(['success' => false, 'message' => 'Cobro no encontrado']);
        exit;
    }
    
    // Obtener detalle del cobro
    $stDetalle = $pdo->prepare("
        SELECT 
            cd.id_factura_venta,
            cd.tipo_pago,
            cd.importe_aplicado,
            fv.numero_factura
        FROM cobros_detalle cd
        JOIN factura_ventas fv ON fv.id_factura_venta = cd.id_factura_venta
        WHERE cd.id_cobro = :id
        ORDER BY cd.id_factura_venta, cd.tipo_pago
    ");
    $stDetalle->execute([':id' => $cobroId]);
    $detalle = $stDetalle->fetchAll();
    
    echo json_encode([
        'success' => true,
        'cobro' => $cobro,
        'detalle' => $detalle
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al consultar el detalle: ' . $e->getMessage()
    ]);
}

