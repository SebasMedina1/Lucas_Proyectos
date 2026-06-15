<?php
session_start();
require "../../config/database.php";

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$cobroId = (int)($_POST['id_cobro'] ?? 0);

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

function bitacora(PDO $pdo, int $idUsuario, string $accion, string $desc, ?int $id = null): void {
    $pdo->exec("SAVEPOINT sp_bit");
    try {
        $sql = "
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:u, 'Cobro', :id, :acc, :d)
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':u' => $idUsuario, ':id' => $id, ':acc' => strtoupper($accion), ':d' => $desc]);
    } catch (Throwable $e) {
        $pdo->exec("ROLLBACK TO SAVEPOINT sp_bit");
        error_log("Bitácora falló: " . $e->getMessage());
    }
}

try {
    // Obtener usuario
    $q = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username=:u LIMIT 1");
    $q->execute([':u' => $_SESSION['username']]);
    $idUsuario = (int)$q->fetchColumn();
    if ($idUsuario <= 0) throw new Exception("No se pudo determinar el usuario.");

    $pdo->beginTransaction();

    // Verificar cobro existe y está en estado REGISTRADO
    $stCobro = $pdo->prepare("
        SELECT id_cobro, fecha_cobro, estado, id_apertura
        FROM cobros
        WHERE id_cobro = :id
        FOR UPDATE
    ");
    $stCobro->execute([':id' => $cobroId]);
    $cobro = $stCobro->fetch();

    if (!$cobro) {
        throw new Exception("Cobro no encontrado.");
    }

    if ($cobro['estado'] !== 'REGISTRADO') {
        throw new Exception("Solo se pueden anular cobros en estado REGISTRADO.");
    }

    // Verificar que sea del día actual
    $hoy = date('Y-m-d');
    if ($cobro['fecha_cobro'] !== $hoy) {
        throw new Exception("Solo se pueden anular cobros del día actual.");
    }

    // Verificar que no existan movimientos derivados (recaudaciones/depósitos)
    $stRecaudaciones = $pdo->prepare("
        SELECT COUNT(*) 
        FROM recaudaciones_depositar 
        WHERE id_apertura = :apertura_id
        LIMIT 1
    ");
    $stRecaudaciones->execute([':apertura_id' => (int)$cobro['id_apertura']]);
    $tieneRecaudaciones = (int)$stRecaudaciones->fetchColumn() > 0;
    
    if ($tieneRecaudaciones) {
        throw new Exception("No se puede anular el cobro porque ya existen recaudaciones asociadas a esta apertura de caja.");
    }

    // Verificar que la caja esté abierta si hubo efectivo
    $stDetalleEfectivo = $pdo->prepare("
        SELECT COUNT(*) 
        FROM cobros_detalle 
        WHERE id_cobro = :id AND tipo_pago = 'EFECTIVO'
    ");
    $stDetalleEfectivo->execute([':id' => $cobroId]);
    $tieneEfectivo = (int)$stDetalleEfectivo->fetchColumn() > 0;
    
    if ($tieneEfectivo) {
        $stCajaAbierta = $pdo->prepare("
            SELECT id_apertura 
            FROM apertura_cierre_caja 
            WHERE id_apertura = :apertura_id AND apertura_estado = 'ABIERTA'
            LIMIT 1
        ");
        $stCajaAbierta->execute([':apertura_id' => (int)$cobro['id_apertura']]);
        $cajaAbierta = $stCajaAbierta->fetch();
        
        if (!$cajaAbierta) {
            throw new Exception("No se puede anular el cobro porque la caja no está abierta y el cobro incluye efectivo.");
        }
    }

    // Obtener detalle del cobro
    $stDetalle = $pdo->prepare("
        SELECT id_factura_venta, tipo_pago, importe_aplicado
        FROM cobros_detalle
        WHERE id_cobro = :id
    ");
    $stDetalle->execute([':id' => $cobroId]);
    $detalle = $stDetalle->fetchAll();

    // Agrupar detalle por factura para procesar cada factura una sola vez
    $detallePorFactura = [];
    foreach ($detalle as $item) {
        $facturaId = (int)$item['id_factura_venta'];
        if (!isset($detallePorFactura[$facturaId])) {
            $detallePorFactura[$facturaId] = 0;
        }
        $detallePorFactura[$facturaId] += (float)$item['importe_aplicado'];
    }

    // Revertir Cuentas a Cobrar y actualizar estados de factura/pedido
    foreach ($detallePorFactura as $facturaId => $importeTotal) {
        // Obtener información de la factura
        $stFact = $pdo->prepare("
            SELECT fv.id_factura_venta, fv.tipo_factura, fv.total_general, fv.estado, fv.factura_estado, fv.id_pedido_venta
            FROM factura_ventas fv
            WHERE fv.id_factura_venta = :fact_id
            FOR UPDATE OF fv
        ");
        $stFact->execute([':fact_id' => $facturaId]);
        $factura = $stFact->fetch();

        if (!$factura) continue;

        // Si la factura es a crédito, restaurar saldo en CxC
        if ($factura['tipo_factura'] === 'CREDITO') {
            $stCxC = $pdo->prepare("
                SELECT id_cuenta_cobrar, saldo_pendiente, monto_total
                FROM cuentas_cobrar
                WHERE id_factura_venta = :fact_id
                FOR UPDATE
            ");
            $stCxC->execute([':fact_id' => $facturaId]);
            $cxc = $stCxC->fetch();

            if ($cxc) {
                $nuevoSaldo = min((float)$cxc['monto_total'], (float)$cxc['saldo_pendiente'] + $importeTotal);
                $nuevoEstado = ($nuevoSaldo >= (float)$cxc['monto_total'] - 0.01) ? 'PENDIENTE' : 'PARCIAL';

                $stUpdCxC = $pdo->prepare("
                    UPDATE cuentas_cobrar
                    SET saldo_pendiente = :saldo, estado = :estado
                    WHERE id_cuenta_cobrar = :cxc_id
                ");
                $stUpdCxC->execute([
                    ':saldo' => $nuevoSaldo,
                    ':estado' => $nuevoEstado,
                    ':cxc_id' => (int)$cxc['id_cuenta_cobrar']
                ]);
                bitacora($pdo, $idUsuario, 'MODIFICACION', "CxC revertida: id={$cxc['id_cuenta_cobrar']} | saldo={$nuevoSaldo} | estado={$nuevoEstado} | Cobro anulado={$cobroId}", (int)$cxc['id_cuenta_cobrar']);
            }
        }

        // Recalcular total pagado de la factura (sin el cobro anulado)
        $stTotalPagado = $pdo->prepare("
            SELECT COALESCE(SUM(cd.importe_aplicado), 0) AS total_pagado
            FROM cobros_detalle cd
            INNER JOIN cobros c ON c.id_cobro = cd.id_cobro
            WHERE cd.id_factura_venta = :fact_id
              AND c.estado = 'REGISTRADO'
              AND c.id_cobro != :cobro_id
        ");
        $stTotalPagado->execute([':fact_id' => $facturaId, ':cobro_id' => $cobroId]);
        $totalPagado = (float)$stTotalPagado->fetchColumn();

        $totalFactura = (float)$factura['total_general'];

        // Si el total pagado < total de la factura, revertir estado a EMITIDA
        if ($totalPagado < $totalFactura - 0.01) {
            // Actualizar estado de factura a EMITIDA
            $stUpdFact = $pdo->prepare("
                UPDATE factura_ventas
                SET estado = 'EMITIDA', factura_estado = 'EMITIDA'
                WHERE id_factura_venta = :fact_id
            ");
            $stUpdFact->execute([':fact_id' => $facturaId]);
            bitacora($pdo, $idUsuario, 'MODIFICACION', "Factura #{$facturaId} → EMITIDA (Cobro #{$cobroId} anulado. Total pagado: {$totalPagado})", $facturaId);
            
            // Si la factura tiene un pedido asociado, revertir su estado a PENDIENTE
            if (!empty($factura['id_pedido_venta'])) {
                $pedidoId = (int)$factura['id_pedido_venta'];
                $stUpdPedido = $pdo->prepare("
                    UPDATE pedido_venta
                    SET pedido_estado = 'PENDIENTE'
                    WHERE id_pedido_venta = :pedido_id
                      AND pedido_estado != 'ANULADO'
                ");
                $stUpdPedido->execute([':pedido_id' => $pedidoId]);
                bitacora($pdo, $idUsuario, 'MODIFICACION', "Pedido #{$pedidoId} → PENDIENTE (Factura #{$facturaId} revertida a EMITIDA por anulación de cobro #{$cobroId})", $pedidoId);
            }
        }
        // Si totalPagado >= totalFactura, mantener PAGADA (puede haber otros cobros)
    }

    // Revertir movimientos de caja (arqueo_caja)
    $stArqueo = $pdo->prepare("
        SELECT id_arqueo, monto_efectivo, monto_cheque, monto_tarjeta
        FROM arqueo_caja
        WHERE id_apertura = :apertura
        FOR UPDATE
    ");
    $stArqueo->execute([':apertura' => (int)$cobro['id_apertura']]);
    $arqueo = $stArqueo->fetch();

    if ($arqueo) {
        $montoEfectivo = (float)($arqueo['monto_efectivo'] ?? 0);
        $montoCheque = (float)($arqueo['monto_cheque'] ?? 0);
        $montoTarjeta = (float)($arqueo['monto_tarjeta'] ?? 0);

        // Restar importes por tipo de pago
        foreach ($detalle as $item) {
            $tipoPago = strtoupper(trim($item['tipo_pago'] ?? ''));
            $importe = (float)$item['importe_aplicado'];
            
            switch ($tipoPago) {
                case 'EFECTIVO':
                    $montoEfectivo = max(0, $montoEfectivo - $importe);
                    break;
                case 'CHEQUE':
                    $montoCheque = max(0, $montoCheque - $importe);
                    break;
                case 'TARJETA':
                case 'TRANSFERENCIA':
                case 'BILLETERA':
                    // TRANSFERENCIA y BILLETERA se suman en monto_tarjeta
                    $montoTarjeta = max(0, $montoTarjeta - $importe);
                    break;
            }
        }

        $stUpdArqueo = $pdo->prepare("
            UPDATE arqueo_caja
            SET monto_efectivo = :efectivo,
                monto_cheque = :cheque,
                monto_tarjeta = :tarjeta
            WHERE id_arqueo = :arqueo_id
        ");
        $stUpdArqueo->execute([
            ':efectivo' => $montoEfectivo,
            ':cheque' => $montoCheque,
            ':tarjeta' => $montoTarjeta,
            ':arqueo_id' => (int)$arqueo['id_arqueo']
        ]);

        bitacora($pdo, $idUsuario, 'MODIFICACION', "Arqueo revertido por anulación de cobro: id={$arqueo['id_arqueo']}", (int)$arqueo['id_arqueo']);
    }

    // Actualizar estado de cheques asociados
    $stCheques = $pdo->prepare("
        SELECT cc.id_cheque
        FROM cobro_cheques cc
        WHERE cc.id_cobro = :cobro_id
    ");
    $stCheques->execute([':cobro_id' => $cobroId]);
    $cheques = $stCheques->fetchAll();

    foreach ($cheques as $cheque) {
        $stUpdCheque = $pdo->prepare("
            UPDATE cheque
            SET cheque_estado = 'ANULADO'
            WHERE id_cheque = :cheque_id
        ");
        $stUpdCheque->execute([':cheque_id' => (int)$cheque['id_cheque']]);
        bitacora($pdo, $idUsuario, 'MODIFICACION', "Cheque anulado: id={$cheque['id_cheque']} | Cobro anulado={$cobroId}", (int)$cheque['id_cheque']);
    }

    // Actualizar estado del cobro a ANULADO
    $stUpd = $pdo->prepare("
        UPDATE cobros
        SET estado = 'ANULADO', cobro_estado = 'ANULADO',
            observaciones = COALESCE(observaciones || E'\n', '') || 'ANULADO: ' || CURRENT_TIMESTAMP::TEXT
        WHERE id_cobro = :id
    ");
    $stUpd->execute([':id' => $cobroId]);

    bitacora($pdo, $idUsuario, 'ANULACION', "Cobro anulado: id={$cobroId}", $cobroId);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Cobro anulado correctamente']);

} catch (Throwable $e) {
    $pdo->rollBack();
    error_log("Error en anular_cobro.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

