<?php
session_start();
require "../../config/database.php";

header('Content-Type: application/json; charset=UTF-8');

// Verificar autenticación
if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
    exit;
}

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
            ':entidad'     => 'pedido venta',
            ':id_registro' => $idRegistro,
            ':accion'      => strtoupper($accion),
            ':descripcion' => $descripcion
        ]);
    } catch (Throwable $e) {
        error_log("Bitácora falló: ".$e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ped_id'])) {
    try {
        $pedido_id = (int)$_POST['ped_id'];

        if ($pedido_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de pedido inválido']);
            exit;
        }

        // Obtener usuario
        if (!empty($_SESSION['usua_id'])) {
            $usuario_id = (int) $_SESSION['usua_id'];
        } else {
            $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmtUid = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
            $stmtUid->execute([':u' => $_SESSION['username'] ?? '']);
            $usuario_id = (int) $stmtUid->fetchColumn();
        }

        if ($usuario_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Usuario no válido']);
            exit;
        }

        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->beginTransaction();

        // Verificar estado y BLOQUEAR
        $st = $pdo->prepare("
            SELECT pedido_estado
            FROM pedido_venta
            WHERE id_pedido_venta = :id
            FOR UPDATE
        ");
        $st->execute([':id' => $pedido_id]);
        $estado = $st->fetchColumn();

        if ($estado === false) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
            exit;
        }

        $estado = strtoupper(trim((string)$estado));

        // Solo se puede anular si está PENDIENTE
        if ($estado !== 'PENDIENTE') {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => "El pedido no puede anularse (estado: {$estado}). Solo se pueden anular pedidos PENDIENTES."]);
            exit;
        }

        // Verificar que no tenga documentos posteriores (presupuesto o factura)
        // Verificar presupuestos
        $qPresupuesto = $pdo->prepare("
            SELECT COUNT(*) 
            FROM presupuesto_venta 
            WHERE id_pedido_venta = :id
        ");
        $qPresupuesto->execute([':id' => $pedido_id]);
        $tienePresupuesto = $qPresupuesto->fetchColumn() > 0;

        // Verificar facturas
        $qFactura = $pdo->prepare("
            SELECT COUNT(*) 
            FROM factura_ventas 
            WHERE id_pedido_venta = :id
        ");
        $qFactura->execute([':id' => $pedido_id]);
        $tieneFactura = $qFactura->fetchColumn() > 0;

        if ($tienePresupuesto || $tieneFactura) {
            $pdo->rollBack();
            $docs = [];
            if ($tienePresupuesto) $docs[] = 'presupuesto';
            if ($tieneFactura) $docs[] = 'factura';
            echo json_encode([
                'success' => false, 
                'message' => 'No se puede anular un pedido que ya tiene documentos asociados (' . implode(', ', $docs) . ').'
            ]);
            exit;
        }

        // Anular el pedido
        $upd = $pdo->prepare("
            UPDATE pedido_venta
            SET pedido_estado = 'ANULADO'
            WHERE id_pedido_venta = :id
        ");
        $upd->execute([':id' => $pedido_id]);

        // Bitácora
        bitacora($pdo, $usuario_id, 'INACTIVACION', 
            "Se anula el Pedido de Venta #{$pedido_id}", 
            $pedido_id);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Pedido anulado correctamente']);

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>

