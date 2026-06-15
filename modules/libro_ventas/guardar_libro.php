<?php
session_start();
require '../../config/database.php';

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$desde = trim($_POST['fecha_desde'] ?? '');
$hasta = trim($_POST['fecha_hasta'] ?? '');
$observaciones = trim($_POST['observaciones'] ?? '');

if (empty($desde) || empty($hasta)) {
    echo json_encode(['success' => false, 'message' => 'Fechas requeridas']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Obtener usuario
    $qUsuario = $pdo->prepare("SELECT id_usuario, id_sucursal FROM usuarios WHERE username = :u LIMIT 1");
    $qUsuario->execute([':u' => $_SESSION['username']]);
    $usuario = $qUsuario->fetch();
    
    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit;
    }
    
    // Consolidar documentos (sin filtros adicionales para guardar libro completo)
    require 'consolidar_documentos.php';
    $documentos = consolidarDocumentos($pdo, $desde, $hasta, null, null, null, null);
    
    // Calcular totales
    $totalExento = 0;
    $totalBase5 = 0;
    $totalIva5 = 0;
    $totalBase10 = 0;
    $totalIva10 = 0;
    $totalGeneral = 0;
    $cantFacturas = 0;
    $cantNC = 0;
    $cantND = 0;
    
    foreach ($documentos as $doc) {
        $totalExento += $doc['exento'] * $doc['signo'];
        $totalBase5 += $doc['base_5'] * $doc['signo'];
        $totalIva5 += $doc['iva_5'] * $doc['signo'];
        $totalBase10 += $doc['base_10'] * $doc['signo'];
        $totalIva10 += $doc['iva_10'] * $doc['signo'];
        $totalGeneral += $doc['total'] * $doc['signo'];
        
        if ($doc['tipo'] === 'FACTURA') $cantFacturas++;
        elseif ($doc['tipo'] === 'NOTA_CREDITO') $cantNC++;
        elseif ($doc['tipo'] === 'NOTA_DEBITO') $cantND++;
    }
    
    // Validar que no exista un libro cerrado para este período
    $qExiste = $pdo->prepare("
        SELECT id_libro FROM libro_ventas_historico 
        WHERE fecha_desde = :desde AND fecha_hasta = :hasta AND estado = 'CERRADO'
        LIMIT 1
    ");
    $qExiste->execute([':desde' => $desde, ':hasta' => $hasta]);
    if ($qExiste->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ya existe un libro cerrado para este período']);
        exit;
    }
    
    // Insertar o actualizar libro
    $qInsert = $pdo->prepare("
        INSERT INTO libro_ventas_historico (
            fecha_desde, fecha_hasta, estado, total_exento, total_base_5, total_iva_5,
            total_base_10, total_iva_10, total_general, cantidad_facturas,
            cantidad_notas_credito, cantidad_notas_debito, observaciones,
            id_usuario, id_sucursal
        ) VALUES (
            :desde, :hasta, 'ABIERTO', :exento, :base5, :iva5, :base10, :iva10,
            :total, :fact, :nc, :nd, :obs, :user, :suc
        )
        ON CONFLICT DO NOTHING
    ");
    
    // Si no hay conflicto, intentar UPDATE
    $qUpdate = $pdo->prepare("
        UPDATE libro_ventas_historico SET
            total_exento = :exento,
            total_base_5 = :base5,
            total_iva_5 = :iva5,
            total_base_10 = :base10,
            total_iva_10 = :iva10,
            total_general = :total,
            cantidad_facturas = :fact,
            cantidad_notas_credito = :nc,
            cantidad_notas_debito = :nd,
            observaciones = :obs,
            fecha_generacion = CURRENT_TIMESTAMP
        WHERE fecha_desde = :desde AND fecha_hasta = :hasta AND estado = 'ABIERTO'
    ");
    
    $params = [
        ':desde' => $desde,
        ':hasta' => $hasta,
        ':exento' => $totalExento,
        ':base5' => $totalBase5,
        ':iva5' => $totalIva5,
        ':base10' => $totalBase10,
        ':iva10' => $totalIva10,
        ':total' => $totalGeneral,
        ':fact' => $cantFacturas,
        ':nc' => $cantNC,
        ':nd' => $cantND,
        ':obs' => $observaciones,
        ':user' => (int)$usuario['id_usuario'],
        ':suc' => $usuario['id_sucursal'] ? (int)$usuario['id_sucursal'] : null
    ];
    
    $qUpdate->execute($params);
    
    if ($qUpdate->rowCount() === 0) {
        // No había registro abierto, insertar nuevo
        $qInsert->execute($params);
    }
    
    echo json_encode(['success' => true, 'message' => 'Libro guardado correctamente']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

