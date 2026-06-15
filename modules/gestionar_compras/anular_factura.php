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
$factura_id = isset($input['factura_id']) ? (int)$input['factura_id'] : 0;

if ($factura_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de factura inválido']);
    exit;
}

// Función bitácora
function bitacora(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void {
    $savepointName = null;
    try {
        // Si estamos en una transacción, crear un savepoint para aislar errores de bitacora
        if ($pdo->inTransaction()) {
            $savepointName = 'bitacora_' . str_replace(['.', '-'], '_', uniqid('', true));
            $pdo->exec("SAVEPOINT {$savepointName}");
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ");
        $stmt->execute([
            ':id_usuario'  => $idUsuario,
            ':entidad'     => 'Gestionar compra / Factura',
            ':id_registro' => $idRegistro,
            ':accion'      => strtoupper($accion),
            ':descripcion' => $descripcion
        ]);
        
        // Si usamos savepoint, liberarlo
        if ($savepointName !== null) {
            $pdo->exec("RELEASE SAVEPOINT {$savepointName}");
        }
    } catch (PDOException $e) {
        // Si hay un error SQL y usamos savepoint, hacer rollback al savepoint
        if ($savepointName !== null) {
            try {
                $pdo->exec("ROLLBACK TO SAVEPOINT {$savepointName}");
            } catch (PDOException $rollbackError) {
                error_log("Error al hacer rollback del savepoint de bitacora: ".$rollbackError->getMessage());
            }
        }
        error_log("Bitácora falló: ".$e->getMessage());
        // No re-lanzar la excepción para no afectar la transacción principal
    } catch (Throwable $e) {
        // Si hay un error y usamos savepoint, hacer rollback al savepoint
        if ($savepointName !== null) {
            try {
                $pdo->exec("ROLLBACK TO SAVEPOINT {$savepointName}");
            } catch (PDOException $rollbackError) {
                error_log("Error al hacer rollback del savepoint de bitacora: ".$rollbackError->getMessage());
            }
        }
        error_log("Bitácora falló: ".$e->getMessage());
    }
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

    // 1) Consultar factura y BLOQUEAR la fila (según especificación punto 19.3)
    $st = $pdo->prepare("
        SELECT 
            fc.fac_estado,
            fc.id_orden_compra,
            fc.tipo_operacion,
            fc.fac_remision
        FROM factura_compra fc
        WHERE fc.id_factura_compra = :id
        FOR UPDATE
    ");
    $st->execute([':id' => $factura_id]);
    $factura = $st->fetch(PDO::FETCH_ASSOC);

    if ($factura === false) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Factura no encontrada']);
        exit;
    }

    $estado = strtoupper(trim((string)$factura['fac_estado']));
    $idOc = (int)$factura['id_orden_compra'];
    $facRemision = (int)($factura['fac_remision'] ?? 0);
    $tipoOperacion = strtoupper(trim((string)($factura['tipo_operacion'] ?? 'CONTADO')));

    // 2) Validar estado según especificación punto 19.3
    // No permitir anular si ya está EMITIDA o ANULADA
    if (in_array($estado, ['EMITIDA', 'ANULADO'], true)) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => "La factura no puede anularse porque ya se encuentra {$estado}."
        ]);
        exit;
    }

    // 3) Verificar cuenta a pagar según especificación punto 19.3
    // "que la cuenta a pagar no se haya finalizado en caso de ser contado o no se haya aplicado el primer pago en caso de ser crédito"
    // La tabla cuentas_pagar tiene id_factura_compra según bd_final.sql línea 426
    $cuenta = false;
    $stCta = $pdo->prepare("
        SELECT 
            cp.estado,
            cp.monto_total,
            cp.monto_pendiente
        FROM cuentas_pagar cp
        WHERE cp.id_factura_compra = :fact
        LIMIT 1
    ");
    $stCta->execute([':fact' => $factura_id]);
    $cuenta = $stCta->fetch(PDO::FETCH_ASSOC);

    if ($cuenta !== false) {
        $estadoCta = strtoupper(trim((string)$cuenta['estado']));
        
        // Si es CONTADO: verificar que no esté FINALIZADO
        if ($tipoOperacion === 'CONTADO' && $estadoCta === 'FINALIZADO') {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => "La factura no puede anularse porque la cuenta a pagar ya está finalizada."
            ]);
            exit;
        }
        
        // Si es CREDITO: verificar que no se haya aplicado el primer pago
        // Si el monto_pendiente es menor que monto_total, significa que hay pagos aplicados
        $montoTotal = (int)($cuenta['monto_total'] ?? 0);
        $montoPendiente = (int)($cuenta['monto_pendiente'] ?? 0);
        if ($tipoOperacion === 'CREDITO' && $montoPendiente < $montoTotal) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => "La factura no puede anularse porque ya se aplicaron pagos a la cuenta a pagar."
            ]);
            exit;
        }
    }

    // 4) Anular la factura (según especificación punto 19.4)
    $upd = $pdo->prepare("
        UPDATE factura_compra
        SET fac_estado = 'ANULADO'
        WHERE id_factura_compra = :id
    ");
    $upd->execute([':id' => $factura_id]);
    bitacora($pdo, $usuario_id, 'ANULACION', "Se anula la Factura de Compra #{$factura_id}", $factura_id);

    // 5) Revertir cuentas a pagar (según especificación punto 19.4)
    if ($cuenta !== false) {
        $updCta = $pdo->prepare("
            UPDATE cuentas_pagar
            SET estado = 'ANULADO'
            WHERE id_factura_compra = :fact
        ");
        $updCta->execute([':fact' => $factura_id]);
        bitacora($pdo, $usuario_id, 'ANULACION', "Se anulan las cuentas a pagar de la Factura #{$factura_id}", $factura_id);
    }

    // 6) Revertir stock si corresponde (según especificación punto 19.4)
    // "Si la factura impactó stock (sin nota de remisión), revierte el movimiento."
    if ($facRemision === 0) {
        // Obtener detalle de la factura para revertir stock
        $stDet = $pdo->prepare("
            SELECT 
                fd.id_materia_prima,
                fd.fac_cantidad
            FROM factura_detalle_compra fd
            WHERE fd.id_factura_compra = :id
        ");
        $stDet->execute([':id' => $factura_id]);
        $detalles = $stDet->fetchAll(PDO::FETCH_ASSOC);

        // Revertir stock (restar las cantidades que se sumaron)
        // El stock se actualiza por materia_prima + deposito_id
        foreach ($detalles as $det) {
            $mpId = (int)$det['id_materia_prima'];
            $cantidad = (int)$det['fac_cantidad'];
            
            if ($mpId <= 0 || $cantidad <= 0) continue;

            // Obtener el depósito desde el stock existente de esta materia prima
            $stStockInfo = $pdo->prepare("
                SELECT deposito_id, cantidad_existente
                FROM stock_materia_prima
                WHERE id_materia_prima = :mp
                LIMIT 1
            ");
            $stStockInfo->execute([':mp' => $mpId]);
            $stockInfo = $stStockInfo->fetch(PDO::FETCH_ASSOC);

            if ($stockInfo !== false) {
                $depositoId = (int)$stockInfo['deposito_id'];
                
                // Restar del stock
                $stStock = $pdo->prepare("
                    UPDATE stock_materia_prima
                    SET cantidad_existente = GREATEST(0, COALESCE(cantidad_existente, 0) - :c)
                    WHERE id_materia_prima = :mp AND deposito_id = :d
                ");
                $stStock->execute([
                    ':c' => $cantidad,
                    ':mp' => $mpId,
                    ':d' => $depositoId
                ]);
                
                bitacora($pdo, $usuario_id, 'MODIFICACION', "Stock revertido: -{$cantidad} unid. | Materia Prima:{$mpId} | Depósito:{$depositoId} | Factura #{$factura_id}", $factura_id);
            }
        }
    }

    // 7) Revertir OC a EMITIDA (según especificación punto 19.5)
    if ($idOc > 0) {
        $updOc = $pdo->prepare("
            UPDATE orden_de_compra
            SET orden_estado = 'EMITIDA'
            WHERE id_orden_compra = :oc
        ");
        $updOc->execute([':oc' => $idOc]);
        bitacora($pdo, $usuario_id, 'MODIFICACION', "OC #{$idOc} → EMITIDA (Anulación Factura #{$factura_id})", $idOc);
    }

    // Commit
    $pdo->commit();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Factura anulada correctamente']);

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
