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
$orden_id = isset($input['orden_id']) ? (int)$input['orden_id'] : 0;

if ($orden_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de orden inválido']);
    exit;
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener usuario actual
    $usuario_id = 0;
    if (!empty($_SESSION['id_usuario'])) {
        $usuario_id = (int)$_SESSION['id_usuario'];
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
        SELECT orden_estado, id_presupuesto_compra
        FROM orden_de_compra
        WHERE id_orden_compra = :id
        FOR UPDATE
    ");
    $st->execute([':id' => $orden_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Orden no encontrada']);
        exit;
    }

    $estado = strtoupper(trim((string)$row['orden_estado']));
    $idPresu = (int)$row['id_presupuesto_compra'];

    // 2) Validar estado según especificación: Solo permitir anular si está EMITIDA
    if ($estado !== 'EMITIDA') {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => "Solo se pueden anular órdenes en estado EMITIDA. Estado actual: {$estado}."
        ]);
        exit;
    }

    // 3) Verificar vínculos según especificación punto 20.3: 
    // "el sistema verifica que la OC no tenga facturas asociadas"
    $st_facturas = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM factura_compra 
        WHERE id_orden_compra = :id
    ");
    $st_facturas->execute([':id' => $orden_id]);
    $total_facturas = (int)$st_facturas->fetchColumn();
    
    if ($total_facturas > 0) {
        $pdo->rollBack();
        // Punto 20.5: "Si no cumple, el sistema rechaza la anulación e informa el motivo"
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => "La Orden #{$orden_id} no puede anularse porque tiene {$total_facturas} factura(s) asociada(s).",
            'vinculos' => ["Facturas de Compra ({$total_facturas} registro(s))"]
        ]);
        exit;
    }

    // 4) Anular la orden (según especificación punto 20.4)
    $upd = $pdo->prepare("
        UPDATE orden_de_compra
        SET orden_estado = 'ANULADO'
        WHERE id_orden_compra = :id
    ");
    $upd->execute([':id' => $orden_id]);

    // 5) Revertir el presupuesto a EMITIDO (según especificación punto 20.4)
    if ($idPresu > 0) {
        $updPresu = $pdo->prepare("
            UPDATE presupuesto_compra
            SET presu_estado = 'EMITIDO'
            WHERE id_presupuesto_compra = :id
        ");
        $updPresu->execute([':id' => $idPresu]);
    }

    // 6) Bitácora
    function bitacora(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
                VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
            ");
            $stmt->execute([
                ':id_usuario'  => $idUsuario,
                ':entidad'     => 'Orden compra',
                ':id_registro' => $idRegistro,
                ':accion'      => strtoupper($accion),
                ':descripcion' => $descripcion
            ]);
        } catch (Throwable $e) {
            error_log("Bitácora falló: ".$e->getMessage());
        }
    }
    bitacora($pdo, $usuario_id, 'INACTIVACION', "Se anula la Orden de Compra #{$orden_id}", $orden_id);

    // Commit
    $pdo->commit();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Orden anulada correctamente']);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
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

