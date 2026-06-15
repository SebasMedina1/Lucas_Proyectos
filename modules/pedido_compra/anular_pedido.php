<?php
session_start();

// Verificar si la sesión es válida
if (empty($_SESSION['username'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Token de sesión inválido']);
    exit;
}

require "../../config/database.php";

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);
$pedido_id = isset($input['ped_id']) ? (int)$input['ped_id'] : 0;

if ($pedido_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de pedido inválido']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener usuario actual
    $usuario_id = 0;
    if (!empty($_SESSION['usua_id'])) {
        $usuario_id = (int)$_SESSION['usua_id'];
    } else {
        $stmtUid = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
        $stmtUid->execute([':u' => $_SESSION['username'] ?? '']);
        $usuario_id = (int)$stmtUid->fetchColumn();
    }

    if ($usuario_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Usuario no válido']);
        exit;
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    // 1) Consultar estado actual y BLOQUEAR la fila
    $st = $pdo->prepare("
        SELECT pedido_estado
        FROM pedidos_compra
        WHERE id_pedido_compra = :id
        FOR UPDATE
    ");
    $st->execute([':id' => $pedido_id]);
    $estado = $st->fetchColumn();

    if ($estado === false) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
        exit;
    }

    $estado = strtoupper(trim((string)$estado));

    // 2) Validar estado según especificación punto 22.3: Solo permitir anular si está en PENDIENTE
    if ($estado !== 'PENDIENTE') {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => "Solo se pueden anular pedidos en estado PENDIENTE. Estado actual: {$estado}."
        ]);
        exit;
    }

    // 3) Verificar vínculos con documentos posteriores según especificación punto 22.3
    // (presupuesto/orden/compra)
    $vinculos = [];

    // Verificar presupuestos asociados directamente
    $st_presu = $pdo->prepare("
        SELECT id_presupuesto_compra, presu_estado 
        FROM presupuesto_compra 
        WHERE id_pedido_compra = :id 
        LIMIT 1
    ");
    $st_presu->execute([':id' => $pedido_id]);
    $presu = $st_presu->fetch(PDO::FETCH_ASSOC);
    if ($presu) {
        $vinculos[] = "Presupuesto #{$presu['id_presupuesto_compra']} (Estado: {$presu['presu_estado']})";
    }

    // Verificar órdenes de compra asociadas directamente (a través de presupuesto)
    $st_orden = $pdo->prepare("
        SELECT oc.id_orden_compra, oc.orden_estado 
        FROM orden_de_compra oc
        JOIN presupuesto_compra pc ON pc.id_presupuesto_compra = oc.id_presupuesto_compra
        WHERE pc.id_pedido_compra = :id 
        LIMIT 1
    ");
    $st_orden->execute([':id' => $pedido_id]);
    $orden = $st_orden->fetch(PDO::FETCH_ASSOC);
    if ($orden) {
        $vinculos[] = "Orden de Compra #{$orden['id_orden_compra']} (Estado: {$orden['orden_estado']})";
    }

    // Verificar facturas/compra asociadas directamente (a través de orden)
    $st_factura = $pdo->prepare("
        SELECT fc.id_factura_compra, fc.fac_estado 
        FROM factura_compra fc
        JOIN orden_de_compra oc ON oc.id_orden_compra = fc.id_orden_compra
        JOIN presupuesto_compra pc ON pc.id_presupuesto_compra = oc.id_presupuesto_compra
        WHERE pc.id_pedido_compra = :id 
        LIMIT 1
    ");
    $st_factura->execute([':id' => $pedido_id]);
    $factura = $st_factura->fetch(PDO::FETCH_ASSOC);
    if ($factura) {
        $vinculos[] = "Factura de Compra #{$factura['id_factura_compra']} (Estado: {$factura['fac_estado']})";
    }

    // Si hay vínculos, rechazar anulación (punto 22.5: "Si no cumple, el sistema rechaza la anulación e informa el motivo")
    if (!empty($vinculos)) {
        $pdo->rollBack();
        $mensaje = "El Pedido #{$pedido_id} no puede anularse porque está vinculado a:\n" . implode("\n", $vinculos);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => $mensaje,
            'vinculos' => $vinculos
        ]);
        exit;
    }

    // 4) Anular el pedido (punto 22.4: "Si cumple condiciones, el sistema cambia el estado a 'Anulado'")
    $upd = $pdo->prepare("
        UPDATE pedidos_compra
        SET pedido_estado = 'ANULADO',
            pedido_ultima_modificacion = CURRENT_TIMESTAMP
        WHERE id_pedido_compra = :id
    ");
    $upd->execute([':id' => $pedido_id]);

    // 5) Bitácora
    $stmt_bitacora = $pdo->prepare("
        INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
        VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
    ");
    $stmt_bitacora->execute([
        ':id_usuario'  => $usuario_id,
        ':entidad'     => 'pedido compra',
        ':id_registro' => $pedido_id,
        ':accion'      => 'INACTIVACION',
        ':descripcion' => "Se anula el Pedido de Compra #{$pedido_id}"
    ]);

    // Commit
    $pdo->commit();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Pedido anulado correctamente']);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

