<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
    exit;
}

require "../../config/database.php";

// Función de bitácora
function bitacora(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void {
    try {
        $check = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'bitacora' LIMIT 1");
        if ($check->rowCount() === 0) {
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ");
        $stmt->execute([
            ':id_usuario'  => $idUsuario,
            ':entidad'     => 'presupuesto venta',
            ':id_registro' => $idRegistro,
            ':accion'      => strtoupper($accion),
            ':descripcion' => $descripcion
        ]);
    } catch (Throwable $e) {
        error_log("Bitácora falló: ".$e->getMessage());
    }
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['pre_id'])) {
        echo json_encode(['success' => false, 'message' => 'Faltan parámetros requeridos']);
        exit;
    }
    
    $pre_id = (int)$data['pre_id'];
    
    if ($pre_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de presupuesto inválido']);
        exit;
    }
    
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener usuario
    $stmtUid = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
    $stmtUid->execute([':u' => $_SESSION['username'] ?? '']);
    $usuario_id = (int)$stmtUid->fetchColumn();
    
    if ($usuario_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Verificar existencia y estado, y BLOQUEAR
    $st = $pdo->prepare("
        SELECT estado, id_presupuesto_venta
        FROM presupuesto_venta
        WHERE id_presupuesto_venta = :id
        FOR UPDATE
    ");
    $st->execute([':id' => $pre_id]);
    $presupuesto = $st->fetch(PDO::FETCH_ASSOC);
    
    if (!$presupuesto) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Presupuesto no encontrado']);
        exit;
    }
    
    $estado = strtoupper(trim((string)$presupuesto['estado']));
    
    // Validar que no esté ya anulado
    if ($estado === 'ANULADO') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'El presupuesto ya está anulado']);
        exit;
    }
    
    // Validar que no esté APROBADO o PRESUPUESTADO (ya convertido)
    if (in_array($estado, ['APROBADO', 'PRESUPUESTADO'], true)) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'No se puede anular un presupuesto que ya fue aprobado o convertido']);
        exit;
    }
    
    // Verificar si fue convertido en Factura de Venta (conversión directa)
    $stFactura = $pdo->prepare("
        SELECT 1 
        FROM factura_ventas 
        WHERE id_presupuesto_venta = :id 
        LIMIT 1
    ");
    $stFactura->execute([':id' => $pre_id]);
    if ($stFactura->fetchColumn()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'No se puede anular un presupuesto que ya fue convertido en Factura de Venta']);
        exit;
    }
    
    // Actualizar estado a ANULADO
    $upd = $pdo->prepare("
        UPDATE presupuesto_venta
        SET estado = 'ANULADO'
        WHERE id_presupuesto_venta = :id
    ");
    $upd->execute([
        ':id' => $pre_id
    ]);
    
    // Verificar que se actualizó al menos una fila
    $filasActualizadas = $upd->rowCount();
    if ($filasActualizadas === 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'No se pudo actualizar el presupuesto. Posiblemente ya fue modificado. Estado actual: ' . $estado]);
        exit;
    }
    
    // Registrar en bitácora
    bitacora($pdo, $usuario_id, 'ANULACION', 
        "Presupuesto #{$pre_id} anulado", 
        $pre_id);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Presupuesto anulado correctamente', 'filas_actualizadas' => $filasActualizadas]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error al anular el presupuesto: ' . $e->getMessage()]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
