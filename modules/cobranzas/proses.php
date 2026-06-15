<?php
session_start();
require "../../config/database.php";

ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('America/Asuncion');

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

function fail(string $msg) {
    echo "<script>alert(" . json_encode($msg) . ");window.history.back();</script>";
    exit;
}

function execSQL(PDO $pdo, string $label, string $sql, array $params = []): PDOStatement {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st;
    } catch (PDOException $e) {
        $detail = $e->errorInfo[2] ?? $e->getMessage();
        throw new Exception("[$label] ERROR: $detail");
    }
}

if (empty($_SESSION['username'])) {
    fail('Sesión expirada. Inicie sesión nuevamente.');
}

$act = $_GET['act'] ?? '';
if ($act !== 'insert_cobro') {
    fail('Acción no soportada.');
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $pdo->exec("SET TIME ZONE 'America/Asuncion'");
} catch (Throwable $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Resolver usuario / sucursal
try {
    $idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
    if ($idUsuario <= 0) {
        $q = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username=:u LIMIT 1");
        $q->execute([':u' => $_SESSION['username']]);
        $idUsuario = (int)$q->fetchColumn();
    }
    if ($idUsuario <= 0) throw new Exception("No se pudo determinar el usuario.");

    $q = $pdo->prepare("SELECT id_sucursal FROM usuarios WHERE id_usuario=:id LIMIT 1");
    $q->execute([':id' => $idUsuario]);
    $idSucursal = (int)$q->fetchColumn();
    if ($idSucursal <= 0) throw new Exception("El usuario no tiene sucursal asociada.");
} catch (Throwable $e) {
    die("Error al resolver usuario/sucursal: " . $e->getMessage());
}

// Inputs
$idCliente = (int)($_POST['cliente_id'] ?? 0);
$numeroRecibo = trim($_POST['numero_recibo'] ?? '');
$fechaCobro = substr((string)($_POST['fecha'] ?? ''), 0, 10);
$horaCobro = substr((string)($_POST['hora'] ?? ''), 0, 8);
$idAperturaCierre = (int)($_POST['apertura_cierre_id'] ?? 0);
$observaciones = trim($_POST['observaciones'] ?? '');
$facturasJson = $_POST['facturas_json'] ?? '[]';
$pagosJson = $_POST['pagos_json'] ?? '[]';
$totalCobrado = (float)($_POST['total_cobrado'] ?? 0);

// Validaciones
if ($idCliente <= 0) fail("Cliente inválido.");
if (empty($numeroRecibo)) fail("Número de recibo inválido.");
if (empty($fechaCobro)) fail("Fecha inválida.");
if ($idAperturaCierre <= 0) fail("Apertura de caja inválida.");
if ($totalCobrado <= 0) fail("El total cobrado debe ser mayor a cero.");

$facturas = json_decode($facturasJson, true);
$pagos = json_decode($pagosJson, true);

if (!is_array($facturas) || empty($facturas)) fail("Debe incluir al menos una factura.");
if (!is_array($pagos) || empty($pagos)) fail("Debe incluir al menos un medio de pago.");

// Validar caja abierta y obtener cajero_id
$stCaja = $pdo->prepare("
    SELECT acc.id_apertura, acc.apertura_estado AS estado, acc.cajero_id
    FROM apertura_cierre_caja acc
    WHERE acc.id_apertura = :id AND acc.apertura_estado = 'ABIERTA'
    LIMIT 1
");
$stCaja->execute([':id' => $idAperturaCierre]);
$caja = $stCaja->fetch();

if (!$caja) {
    fail("La caja no está abierta o no existe.");
}

$cajeroId = (int)$caja['cajero_id'];
if ($cajeroId <= 0) {
    fail("No se pudo determinar el cajero asociado a la apertura de caja.");
}

// Validar que la suma de totales de facturas = total cobrado
// El importe_aplicado ahora representa el total de la factura (siempre se cobra el total)
$sumaImportesFacturas = 0;
foreach ($facturas as $factData) {
    $sumaImportesFacturas += (float)($factData['importe_aplicado'] ?? 0);
}

// Validar que la suma de importes de pagos >= total cobrado (puede ser mayor si hay efectivo con vuelto)
$sumaImportesPagos = 0;
foreach ($pagos as $pago) {
    $sumaImportesPagos += (float)($pago['importe_aplicado'] ?? $pago['importe'] ?? 0);
}

// Validar coherencia: suma de facturas = total cobrado
if (abs($sumaImportesFacturas - $totalCobrado) > 0.01) {
    fail("La suma de importes aplicados a facturas ({$sumaImportesFacturas}) no coincide con el total cobrado ({$totalCobrado}).");
}

// Validar que cada factura tenga pagos que sumen exactamente su total
// Agrupar pagos por factura y validar
$pagosPorFactura = [];
foreach ($pagos as $pago) {
    $facturaId = (int)($pago['id_factura_venta'] ?? 0);
    $importe = (float)($pago['importe_aplicado'] ?? $pago['importe'] ?? 0);
    if ($facturaId > 0 && $importe > 0) {
        if (!isset($pagosPorFactura[$facturaId])) {
            $pagosPorFactura[$facturaId] = 0;
        }
        $pagosPorFactura[$facturaId] += $importe;
    }
}

// Validar que cada factura tenga pagos que sumen exactamente su total
// El importe_aplicado ahora representa el total de la factura
foreach ($facturas as $factData) {
    $facturaId = (int)($factData['id_factura_venta'] ?? 0);
    $totalFactura = (float)($factData['importe_aplicado'] ?? 0); // Este es el total de la factura
    
    if ($facturaId <= 0 || $totalFactura <= 0) continue;
    
    $totalPagosFactura = $pagosPorFactura[$facturaId] ?? 0;
    
    // Saldo Pendiente = TotalFactura - SumaDeMediosDePagoAsociadosALaFactura
    // Debe ser 0 para poder guardar (todos los pagos deben sumar exactamente el total)
    $saldoPendiente = $totalFactura - $totalPagosFactura;
    
    if ($saldoPendiente > 0.01) {
        fail("La factura ID {$facturaId} tiene saldo pendiente: " . number_format($saldoPendiente, 2, '.', ',') . ". Debe completar todos los pagos antes de guardar. Total factura: " . number_format($totalFactura, 2, '.', ',') . ", Pagos actuales: " . number_format($totalPagosFactura, 2, '.', ','));
    } else if ($saldoPendiente < -0.01) {
        fail("La factura ID {$facturaId} tiene pagos en exceso. Debe sumar exactamente: " . number_format($totalFactura, 2, '.', ',') . ". Pagos actuales: " . number_format($totalPagosFactura, 2, '.', ','));
    }
}

// El total de pagos debe ser igual al total cobrado (ya validamos que cada factura sume exactamente)
if (abs($sumaImportesPagos - $totalCobrado) > 0.01) {
    fail("La suma de importes de pagos ({$sumaImportesPagos}) debe ser igual al total cobrado ({$totalCobrado}).");
}

// Calcular efectivo recibido (suma de todos los pagos en EFECTIVO)
$efectivoRecibido = 0;
foreach ($pagos as $pago) {
    if (strtoupper($pago['tipo_pago'] ?? '') === 'EFECTIVO') {
        $efectivoRecibido += (float)($pago['importe_aplicado'] ?? $pago['importe'] ?? 0);
    }
}

// Calcular vuelto: efectivo recibido - total cobrado (si es positivo, sino 0)
$vuelto = max(0, $efectivoRecibido - $totalCobrado);

// TRANSACCIÓN
try {
    $pdo->beginTransaction();

    // Insertar cabecera de cobro
    // Usar id_apertura (FK a apertura_cierre_caja.id_apertura)
    $stCab = execSQL(
        $pdo,
        'CABECERA',
        "INSERT INTO cobros
         (numero_recibo, cobro_fecha, fecha_cobro, hora_cobro, id_cliente, id_apertura, cajero_id,
          total_cobrado, vuelto, efectivo_recibido, cobro_estado, estado, observaciones,
          id_usuario, id_sucursal)
         VALUES
         (:nro, :fecha, :fecha, :hora, :cliente, :apertura, :cajero,
          :total, :vuelto, :efectivo, 'REGISTRADO', 'REGISTRADO', :obs,
          :usu, :suc)
         RETURNING id_cobro",
        [
            ':nro' => $numeroRecibo,
            ':fecha' => $fechaCobro,
            ':hora' => $horaCobro,
            ':cliente' => $idCliente,
            ':apertura' => $idAperturaCierre,
            ':cajero' => $cajeroId,
            ':total' => $totalCobrado,
            ':vuelto' => $vuelto,
            ':efectivo' => $efectivoRecibido,
            ':obs' => ($observaciones !== '' ? $observaciones : null),
            ':usu' => $idUsuario,
            ':suc' => $idSucursal
        ]
    );
    $idCobro = (int)$stCab->fetchColumn();
    if ($idCobro <= 0) throw new Exception("No se obtuvo el ID del cobro.");

    bitacora(
        $pdo,
        $idUsuario,
        'ALTA',
        "Alta Cobro: id={$idCobro} | recibo={$numeroRecibo} | cliente={$idCliente} | total={$totalCobrado}",
        $idCobro
    );

    // Validar todas las facturas con FOR UPDATE antes de procesar (concurrencia)
    $facturasValidadas = [];
    foreach ($facturas as $factData) {
        $facturaId = (int)($factData['id_factura_venta'] ?? 0);
        $importeAplicado = (float)($factData['importe_aplicado'] ?? 0);
        
        if ($facturaId <= 0 || $importeAplicado <= 0) continue;
        
        // Verificar que la factura existe y tiene saldo (con FOR UPDATE para concurrencia)
        // Primero hacer FOR UPDATE en la factura principal
        $stFact = $pdo->prepare("
            SELECT fv.id_factura_venta, fv.tipo_factura, fv.total_general, fv.estado,
                   COALESCE(cc.saldo_pendiente, fv.total_general) AS saldo_pendiente
            FROM factura_ventas fv
            LEFT JOIN cuentas_cobrar cc ON cc.id_factura_venta = fv.id_factura_venta
            WHERE fv.id_factura_venta = :fact_id
              AND fv.id_cliente = :cliente_id
              AND fv.estado = 'EMITIDA'
            FOR UPDATE OF fv
        ");
        $stFact->execute([':fact_id' => $facturaId, ':cliente_id' => $idCliente]);
        $factura = $stFact->fetch();
        
        // Calcular total cobrado previo en una consulta separada (sin FOR UPDATE)
        $totalCobradoPrevio = 0;
        if ($factura) {
            $stCobrado = $pdo->prepare("
                SELECT COALESCE(SUM(cd.importe_aplicado), 0) AS total_cobrado_previo
                FROM cobros_detalle cd
                INNER JOIN cobros c ON c.id_cobro = cd.id_cobro
                WHERE cd.id_factura_venta = :fact_id
                  AND c.estado = 'REGISTRADO'
            ");
            $stCobrado->execute([':fact_id' => $facturaId]);
            $totalCobradoPrevio = (float)$stCobrado->fetchColumn();
        }

        if (!$factura) {
            throw new Exception("Factura ID {$facturaId} no encontrada o no válida para este cliente.");
        }
        
        // Revalidar estado dentro de la transacción (concurrencia)
        if ($factura['estado'] !== 'EMITIDA') {
            throw new Exception("La factura ID {$facturaId} ya no está en estado EMITIDA. Puede que haya sido anulada simultáneamente.");
        }

        // Total Factura = total_general
        $totalFactura = (float)$factura['total_general'];
        
        // Saldo Pendiente = TotalFactura - SumaDeMediosDePagoAsociadosALaFactura
        $saldoPendienteActual = $totalFactura - $totalCobradoPrevio;
        
        // El importe_aplicado debe ser igual al total de la factura (siempre se cobra el total)
        // Validar que el importe_aplicado coincida con el total de la factura
        if (abs($importeAplicado - $totalFactura) > 0.01) {
            throw new Exception("El importe a cobrar ({$importeAplicado}) debe ser igual al total de la factura ({$totalFactura}) para la factura {$facturaId}.");
        }
        
        // Validar que aún haya saldo pendiente (no se haya cobrado completamente antes)
        if ($saldoPendienteActual <= 0.01) {
            throw new Exception("La factura {$facturaId} ya ha sido cobrada completamente. Saldo pendiente: {$saldoPendienteActual}.");
        }
        
        $facturasValidadas[$facturaId] = [
            'factura' => $factura,
            'importe_aplicado' => $importeAplicado, // Este es el total de la factura
            'total_factura' => $totalFactura,
            'saldo_pendiente_actual' => $saldoPendienteActual
        ];
    }

    // Insertar detalle de cobro y procesar cheques
    foreach ($pagos as $pago) {
        $facturaId = (int)($pago['id_factura_venta'] ?? 0);
        $tipoPago = strtoupper(trim($pago['tipo_pago'] ?? ''));
        $importe = (float)($pago['importe_aplicado'] ?? $pago['importe'] ?? 0);

        if ($facturaId <= 0 || empty($tipoPago) || $importe <= 0) continue;
        
        // Usar factura validada
        if (!isset($facturasValidadas[$facturaId])) {
            throw new Exception("Factura ID {$facturaId} no fue validada correctamente.");
        }
        
        $factura = $facturasValidadas[$facturaId]['factura'];

        // Insertar detalle
        execSQL(
            $pdo,
            'DETALLE',
            "INSERT INTO cobros_detalle (id_cobro, id_factura_venta, tipo_pago, importe_aplicado)
             VALUES (:cobro, :factura, :tipo, :importe)
             ON CONFLICT (id_cobro, id_factura_venta, tipo_pago) 
             DO UPDATE SET importe_aplicado = cobros_detalle.importe_aplicado + EXCLUDED.importe_aplicado",
            [
                ':cobro' => $idCobro,
                ':factura' => $facturaId,
                ':tipo' => $tipoPago,
                ':importe' => $importe
            ]
        );

        // Si es CHEQUE, insertar en tabla cheque y cobro_cheques
        if ($tipoPago === 'CHEQUE' && isset($pago['cheque'])) {
            $chequeData = $pago['cheque'];
            $idBanco = (int)($chequeData['id_banco'] ?? 0);
            $chequeNumero = trim($chequeData['cheque_numero'] ?? '');
            $chequeFechaEmision = substr($chequeData['cheque_fecha_emision'] ?? '', 0, 10);
            $chequeFechaVencimiento = substr($chequeData['cheque_fecha_vencimiento'] ?? '', 0, 10);
            $chequeTipo = strtoupper(trim($chequeData['cheque_tipo'] ?? 'PROPIO'));
            $montoCheque = (float)($chequeData['monto_cheque'] ?? $importe);

            // Validar datos del cheque
            if ($idBanco <= 0) throw new Exception("Banco inválido para el cheque.");
            if (empty($chequeNumero)) throw new Exception("Número de cheque requerido.");
            if (empty($chequeFechaEmision)) throw new Exception("Fecha de emisión del cheque requerida.");
            if (empty($chequeFechaVencimiento)) throw new Exception("Fecha de vencimiento del cheque requerida.");
            if (abs($montoCheque - $importe) > 0.01) {
                throw new Exception("El importe del pago ({$importe}) debe coincidir con el monto del cheque ({$montoCheque}).");
            }

            // Insertar cheque
            $stCheque = execSQL(
                $pdo,
                'CHEQUE',
                "INSERT INTO cheque 
                 (cheque_numero, cheque_fecha_emision, cheque_fecha_vencimiento, cheque_tipo, monto_cheque, cheque_estado, id_banco)
                 VALUES (:numero, :fecha_emision, :fecha_vencimiento, :tipo, :monto, 'PENDIENTE', :banco)
                 RETURNING id_cheque",
                [
                    ':numero' => $chequeNumero,
                    ':fecha_emision' => $chequeFechaEmision,
                    ':fecha_vencimiento' => $chequeFechaVencimiento,
                    ':tipo' => $chequeTipo,
                    ':monto' => $montoCheque,
                    ':banco' => $idBanco
                ]
            );
            $idCheque = (int)$stCheque->fetchColumn();
            if ($idCheque <= 0) throw new Exception("No se pudo insertar el cheque.");

            // Insertar relación cobro_cheques
            execSQL(
                $pdo,
                'COBRO_CHEQUES',
                "INSERT INTO cobro_cheques (id_cobro, id_cheque)
                 VALUES (:cobro, :cheque)",
                [
                    ':cobro' => $idCobro,
                    ':cheque' => $idCheque
                ]
            );

            bitacora($pdo, $idUsuario, 'ALTA', "Cheque registrado: id={$idCheque} | número={$chequeNumero} | monto={$montoCheque} | Cobro={$idCobro}", $idCheque);
        }
    }
    
    // Actualizar Cuentas a Cobrar (agrupado por factura para evitar duplicados)
    $facturasProcesadas = [];
    foreach ($pagos as $pago) {
        $facturaId = (int)($pago['id_factura_venta'] ?? 0);
        $importe = (float)($pago['importe_aplicado'] ?? 0);
        
        if ($facturaId <= 0 || $importe <= 0) continue;
        
        // Procesar cada factura solo una vez
        if (isset($facturasProcesadas[$facturaId])) continue;
        $facturasProcesadas[$facturaId] = true;
        
        if (!isset($facturasValidadas[$facturaId])) continue;
        $factura = $facturasValidadas[$facturaId]['factura'];
        
        // Calcular total de importe para esta factura
        $totalImporteFactura = 0;
        foreach ($pagos as $p) {
            if ((int)$p['id_factura_venta'] == $facturaId) {
                $totalImporteFactura += (float)($p['importe_aplicado'] ?? $p['importe'] ?? 0);
            }
        }
        
        // Actualizar Cuentas a Cobrar (solo para facturas de CREDITO)
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
                // Revalidar saldo dentro de la transacción (concurrencia)
                $saldoActual = (float)$cxc['saldo_pendiente'];
                if ($totalImporteFactura > $saldoActual) {
                    throw new Exception("El importe a cobrar ({$totalImporteFactura}) excede el saldo pendiente actual ({$saldoActual}) de la CxC. Puede que otro cobro haya sido registrado simultáneamente.");
                }
                
                $nuevoSaldo = max(0, $saldoActual - $totalImporteFactura);
                $nuevoEstado = $nuevoSaldo <= 0 ? 'PAGADA' : 'PARCIAL';

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

                bitacora($pdo, $idUsuario, 'MODIFICACION', "CxC actualizada: id={$cxc['id_cuenta_cobrar']} | saldo={$nuevoSaldo} | estado={$nuevoEstado}", (int)$cxc['id_cuenta_cobrar']);
            }
        }
        
        // Calcular total pagado de la factura (sumando todos los cobros registrados)
        // Esto aplica tanto para CONTADO como CREDITO
        $stTotalPagado = $pdo->prepare("
            SELECT COALESCE(SUM(cd.importe_aplicado), 0) AS total_pagado
            FROM cobros_detalle cd
            INNER JOIN cobros c ON c.id_cobro = cd.id_cobro
            WHERE cd.id_factura_venta = :fact_id
              AND c.estado = 'REGISTRADO'
        ");
        $stTotalPagado->execute([':fact_id' => $facturaId]);
        $totalPagado = (float)$stTotalPagado->fetchColumn();
        
        $totalFactura = (float)$factura['total_general'];
        
        // Si el total pagado >= total de la factura, actualizar estado de factura y pedido
        if ($totalPagado >= $totalFactura - 0.01) {
            // Actualizar estado de factura a PAGADA/FINALIZADA
            $stUpdFact = $pdo->prepare("
                UPDATE factura_ventas
                SET estado = 'PAGADA', factura_estado = 'PAGADA'
                WHERE id_factura_venta = :fact_id
            ");
            $stUpdFact->execute([':fact_id' => $facturaId]);
            bitacora($pdo, $idUsuario, 'MODIFICACION', "Factura #{$facturaId} → PAGADA (Total pagado: {$totalPagado})", $facturaId);
            
            // Si la factura tiene un pedido asociado, actualizar su estado a FINALIZADO
            $stPedido = $pdo->prepare("
                SELECT id_pedido_venta
                FROM factura_ventas
                WHERE id_factura_venta = :fact_id
            ");
            $stPedido->execute([':fact_id' => $facturaId]);
            $pedidoData = $stPedido->fetch();
            
            if ($pedidoData && !empty($pedidoData['id_pedido_venta'])) {
                $pedidoId = (int)$pedidoData['id_pedido_venta'];
                $stUpdPedido = $pdo->prepare("
                    UPDATE pedido_venta
                    SET pedido_estado = 'FINALIZADO'
                    WHERE id_pedido_venta = :pedido_id
                      AND pedido_estado != 'ANULADO'
                ");
                $stUpdPedido->execute([':pedido_id' => $pedidoId]);
                bitacora($pdo, $idUsuario, 'MODIFICACION', "Pedido #{$pedidoId} → FINALIZADO (Factura #{$facturaId} pagada completamente)", $pedidoId);
            }
        } else {
            // Si es parcial, la factura sigue EMITIDA pero con saldo pendiente
            // No se actualiza el estado de la factura ni del pedido
        }
    }

    // Actualizar arqueo_caja con los movimientos de cada medio de pago
    // Esto alimenta los totales de caja para el cierre
    $stArqueo = $pdo->prepare("
        SELECT id_arqueo, monto_efectivo, monto_cheque, monto_tarjeta
        FROM arqueo_caja
        WHERE id_apertura = :apertura
        FOR UPDATE
    ");
    $stArqueo->execute([':apertura' => $idAperturaCierre]);
    $arqueo = $stArqueo->fetch();

    if ($arqueo) {
        // Actualizar arqueo existente
        $montoEfectivo = (float)$arqueo['monto_efectivo'] ?? 0;
        $montoCheque = (float)$arqueo['monto_cheque'] ?? 0;
        $montoTarjeta = (float)$arqueo['monto_tarjeta'] ?? 0;

        // Sumar importes por tipo de pago
        // Nota: TRANSFERENCIA y BILLETERA se suman a monto_tarjeta (otros medios)
        foreach ($pagos as $pago) {
            $tipoPago = strtoupper(trim($pago['tipo_pago'] ?? ''));
            $importe = (float)($pago['importe_aplicado'] ?? $pago['importe'] ?? 0);
            
            switch ($tipoPago) {
                case 'EFECTIVO':
                    $montoEfectivo += $importe;
                    break;
                case 'CHEQUE':
                    $montoCheque += $importe;
                    break;
                case 'TARJETA':
                case 'TRANSFERENCIA':
                case 'BILLETERA':
                    // TRANSFERENCIA y BILLETERA se suman a monto_tarjeta (otros medios)
                    $montoTarjeta += $importe;
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

        bitacora($pdo, $idUsuario, 'MODIFICACION', "Arqueo actualizado: Efectivo={$montoEfectivo} | Cheque={$montoCheque} | Tarjeta/Otros={$montoTarjeta}", (int)$arqueo['id_arqueo']);
    } else {
        // Crear nuevo arqueo si no existe
        $montoEfectivo = 0;
        $montoCheque = 0;
        $montoTarjeta = 0;

        // Sumar importes por tipo de pago
        // Nota: TRANSFERENCIA y BILLETERA se suman a monto_tarjeta (otros medios)
        foreach ($pagos as $pago) {
            $tipoPago = strtoupper(trim($pago['tipo_pago'] ?? ''));
            $importe = (float)($pago['importe_aplicado'] ?? $pago['importe'] ?? 0);
            
            switch ($tipoPago) {
                case 'EFECTIVO':
                    $montoEfectivo += $importe;
                    break;
                case 'CHEQUE':
                    $montoCheque += $importe;
                    break;
                case 'TARJETA':
                case 'TRANSFERENCIA':
                case 'BILLETERA':
                    // TRANSFERENCIA y BILLETERA se suman a monto_tarjeta (otros medios)
                    $montoTarjeta += $importe;
                    break;
            }
        }

        $stInsArqueo = execSQL(
            $pdo,
            'ARQUEO',
            "INSERT INTO arqueo_caja (
                id_apertura,
                monto_efectivo,
                monto_cheque,
                monto_tarjeta,
                arqueo_inicial,
                arqueo_faltante,
                arqueo_sobrante,
                arqueo_estado,
                id_usuario,
                fecha_arqueo,
                hora_arqueo
            )
             VALUES (
                :apertura,
                :efectivo,
                :cheque,
                :tarjeta,
                'NORMAL',
                0,
                0,
                'REGISTRADO',
                :usuario,
                CURRENT_DATE,
                CURRENT_TIME
            )
             RETURNING id_arqueo",
            [
                ':apertura' => $idAperturaCierre,
                ':efectivo' => $montoEfectivo,
                ':cheque' => $montoCheque,
                ':tarjeta' => $montoTarjeta,
                ':usuario' => $idUsuario
            ]
        );
        $idArqueo = (int)$stInsArqueo->fetchColumn();
        bitacora($pdo, $idUsuario, 'ALTA', "Arqueo creado: id={$idArqueo} | Efectivo={$montoEfectivo} | Cheque={$montoCheque}", $idArqueo);
    }

    $pdo->commit();
    header("Location: view.php?alert=1");
    exit;

} catch (Throwable $e) {
    $pdo->rollBack();
    error_log("Error en proses.php: " . $e->getMessage());
    fail("Error al registrar el cobro: " . $e->getMessage());
}

