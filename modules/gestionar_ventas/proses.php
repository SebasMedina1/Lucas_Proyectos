<?php
session_start();
if (empty($_SESSION['username'])) {
    die("Sesión expirada.");
}

$accion = $_GET['act'] ?? '';
$accionesPermitidas = ['insert', 'anular'];
if (!in_array($accion, $accionesPermitidas, true)) {
    http_response_code(400);
    die("Acción inválida.");
}

require "../../config/database.php";
$dsn = "pgsql:host=$host;port=$port;dbname=$database;";

/** ========= BITÁCORA ========= */
function bitacora(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void {
    $useSavepoint = false;
    try {
        if ($pdo->inTransaction()) {
            $useSavepoint = true;
            $pdo->exec('SAVEPOINT bitacora_sp');
        }
        $stmt = $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ");
        $stmt->execute([
            ':id_usuario'  => $idUsuario,
            ':entidad'     => 'Gestionar Venta / Factura',
            ':id_registro' => $idRegistro,
            ':accion'      => strtoupper($accion),
            ':descripcion' => $descripcion
        ]);
        if ($useSavepoint) {
            $pdo->exec('RELEASE SAVEPOINT bitacora_sp');
        }
    } catch (Throwable $e) {
        if ($useSavepoint) {
            try { $pdo->exec('ROLLBACK TO SAVEPOINT bitacora_sp'); } catch (Throwable $inner) {}
            try { $pdo->exec('RELEASE SAVEPOINT bitacora_sp'); } catch (Throwable $inner) {}
        }
        error_log("Bitácora falló: ".$e->getMessage());
    }
}

function execOrFail(PDO $pdo, PDOStatement $stmt, array $params, string $label) {
    if (!$stmt->execute($params)) {
        $err = $stmt->errorInfo();
        throw new Exception("Fallo SQL en $label: ".$err[2]);
    }
    return $stmt->rowCount();
}

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    if ($accion === 'insert') {
        date_default_timezone_set('America/Asuncion');

        $username = $_SESSION['username'];
        $fecha = $_POST['fecha'] ?? date('Y-m-d');
        $hora = $_POST['hora'] ?? date('H:i:s');
        $fecha_hora = trim("$fecha $hora");
        $fecha_emision = $_POST['fecha_emision'] ?? $fecha;

        // Obtener usuario
        $stmtUser = $pdo->prepare("
            SELECT u.id_usuario, u.id_sucursal
            FROM usuarios u
            WHERE u.username = :u
            LIMIT 1
        ");
        execOrFail($pdo, $stmtUser, [':u' => $username], 'SELECT usuario');
        $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if (!$userRow) throw new Exception("Usuario no encontrado.");
        $idUsuario = (int)$userRow['id_usuario'];
        $idSucursal = (int)$userRow['id_sucursal'];

        // Validar caja abierta
        $aperturaId = isset($_POST['apertura_cierre_id']) ? (int)$_POST['apertura_cierre_id'] : 0;
        if ($aperturaId <= 0) {
            throw new Exception("No hay caja abierta en la sucursal.");
        }

        $stmtCaja = $pdo->prepare("
            SELECT acc.id_apertura, acc.id_caja, acc.apertura_estado AS estado
            FROM apertura_cierre_caja acc
            WHERE acc.id_apertura = :id AND acc.apertura_estado = 'ABIERTA'
            FOR UPDATE
        ");
        execOrFail($pdo, $stmtCaja, [':id' => $aperturaId], 'SELECT apertura_cierre_caja');
        $cajaAbierta = $stmtCaja->fetch(PDO::FETCH_ASSOC);
        if (!$cajaAbierta) {
            throw new Exception("La caja no está abierta o no existe.");
        }

        // Validar timbrado
        // La tabla caja_timbrado tiene clave primaria compuesta (id_timbrado, id_caja)
        $idTimbrado = isset($_POST['id_timbrado']) ? (int)$_POST['id_timbrado'] : 0;
        $idCajaTimbrado = isset($_POST['id_caja_timbrado']) ? (int)$_POST['id_caja_timbrado'] : 0;
        
        if ($idTimbrado <= 0 || $idCajaTimbrado <= 0) {
            throw new Exception("No hay timbrado vigente disponible.");
        }

        $stmtTimbrado = $pdo->prepare("
            SELECT ct.id_timbrado, ct.id_caja, t.timbrado_numero AS timbrado, 
                   ct.punto_expedicion, ct.numero_inicial, ct.numero_final, 
                   ct.numero_actual, ct.fecha_vencimiento
            FROM caja_timbrado ct
            JOIN timbrado t ON t.id_timbrado = ct.id_timbrado
            WHERE ct.id_timbrado = :id_timbrado 
              AND ct.id_caja = :id_caja
              AND ct.estado = 'ACTIVO' 
              AND ct.fecha_vencimiento >= CURRENT_DATE
            FOR UPDATE
        ");
        execOrFail($pdo, $stmtTimbrado, [
            ':id_timbrado' => $idTimbrado,
            ':id_caja' => $idCajaTimbrado
        ], 'SELECT caja_timbrado');
        $timbrado = $stmtTimbrado->fetch(PDO::FETCH_ASSOC);
        if (!$timbrado) {
            throw new Exception("El timbrado no está vigente o ha vencido.");
        }

        $numeroActual = $timbrado['numero_actual'] ?? ($timbrado['numero_inicial'] - 1);
        $proximoNumero = $numeroActual + 1;
        if ($proximoNumero > $timbrado['numero_final']) {
            throw new Exception("No hay números disponibles en el timbrado.");
        }

        $numeroFactura = $timbrado['punto_expedicion'] . '-' . str_pad($proximoNumero, 7, '0', STR_PAD_LEFT);

        // Validar cliente
        $clienteId = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
        if ($clienteId <= 0 && $_POST['cliente_id'] !== '0') {
            throw new Exception("Debe seleccionar un cliente.");
        }

        // Si es consumidor final, crear registro temporal o usar ID 0
        if ($clienteId === 0) {
            // Verificar si existe cliente "Consumidor Final"
            $stmtConsumidor = $pdo->prepare("SELECT id_cliente FROM clientes WHERE cliente_ruc = '9999999' LIMIT 1");
            $stmtConsumidor->execute();
            $consumidor = $stmtConsumidor->fetch(PDO::FETCH_ASSOC);
            if ($consumidor) {
                $clienteId = (int)$consumidor['id_cliente'];
            } else {
                // Crear consumidor final si no existe
                $stmtInsConsumidor = $pdo->prepare("
                    INSERT INTO clientes (cliente_nombre, cliente_apellido, cliente_ruc, cliente_estado, id_usuario)
                    VALUES ('Consumidor', 'Final', '9999999', 'ACTIVO', :uid)
                    ON CONFLICT DO NOTHING
                    RETURNING id_cliente
                ");
                $stmtInsConsumidor->execute([':uid' => $idUsuario]);
                $consumidorNuevo = $stmtInsConsumidor->fetch(PDO::FETCH_ASSOC);
                if ($consumidorNuevo) {
                    $clienteId = (int)$consumidorNuevo['id_cliente'];
                } else {
                    // Si no se pudo crear, usar el primero disponible
                    $stmtConsumidor->execute();
                    $consumidor = $stmtConsumidor->fetch(PDO::FETCH_ASSOC);
                    if ($consumidor) {
                        $clienteId = (int)$consumidor['id_cliente'];
                    } else {
                        throw new Exception("No se pudo asignar cliente.");
                    }
                }
            }
        }

        // Tipo de factura
        $tipoFactura = strtoupper(trim($_POST['tipo_factura'] ?? 'CONTADO'));
        if (!in_array($tipoFactura, ['CONTADO', 'CREDITO'], true)) {
            $tipoFactura = 'CONTADO';
        }
        
        // Mapear tipo_factura a id_tipo_operacion
        // CONTADO = 2, CREDITO = 1 (según tabla tipo_operacion)
        $idTipoOperacion = ($tipoFactura === 'CONTADO') ? 2 : 1;

        // Campos de crédito
        $cuotas = ($tipoFactura === 'CREDITO') ? (int)($_POST['cuotas'] ?? 1) : 0;
        $interesPct = ($tipoFactura === 'CREDITO') ? (float)($_POST['interes_pct'] ?? 0) : 0;
        $plazo = ($tipoFactura === 'CREDITO') ? trim($_POST['plazo'] ?? '') : '';
        
        // Tipo de pago (para facturas de CONTADO y CRÉDITO)
        $tipoPago = strtoupper(trim($_POST['tipo_pago'] ?? 'EFECTIVO'));
        if (!in_array($tipoPago, ['EFECTIVO', 'TARJETA', 'TRANSFERENCIA', 'CHEQUE', 'BILLETERA'], true)) {
            $tipoPago = 'EFECTIVO';
        }
        
        // Validar que si es CRÉDITO, el tipo de pago debe ser TARJETA
        if ($tipoFactura === 'CREDITO' && $tipoPago !== 'TARJETA') {
            throw new Exception("Para facturas a crédito, el tipo de pago debe ser Tarjeta.");
        }

        // Pedido de venta (opcional, pero requerido por la BD)
        $pedidoVentaId = isset($_POST['pedido_venta_id']) && $_POST['pedido_venta_id'] !== '' ? (int)$_POST['pedido_venta_id'] : null;
        
        // TODO: Implementar manejo de presupuestos según especificación
        // La factura puede venir de un Pedido o de un Presupuesto
        // $presupuestoVentaId = isset($_POST['presupuesto_venta_id']) && $_POST['presupuesto_venta_id'] !== '' ? (int)$_POST['presupuesto_venta_id'] : null;

        // Validar productos
        $productosJson = $_POST['productos'] ?? '[]';
        $productos = json_decode($productosJson, true);
        if (!is_array($productos) || empty($productos)) {
            throw new Exception("Debe agregar al menos un producto.");
        }

        // Calcular totales
        $subtotal = (float)($_POST['subtotal'] ?? 0);
        $iva5 = (float)($_POST['iva_5'] ?? 0);
        $iva10 = (float)($_POST['iva_10'] ?? 0);
        $ivaExento = (float)($_POST['iva_exento'] ?? 0);
        $totalGeneral = (float)($_POST['total_general'] ?? 0);

        if ($totalGeneral <= 0) {
            throw new Exception("El total de la factura debe ser mayor a cero.");
        }

        // Observaciones
        $observaciones = trim($_POST['observaciones'] ?? '');

        // Validar stock antes de emitir factura (según especificación punto 11 y flujo alternativo)
        foreach ($productos as $prod) {
            $productoId = (int)($prod['codigo'] ?? 0);
            $cantidad = (int)($prod['cantidad'] ?? 0);
            
            if ($productoId <= 0 || $cantidad <= 0) {
                continue;
            }
            
            // Verificar stock disponible
            $stmtStock = $pdo->prepare("
                SELECT stock_prod_existente, p.producto_descri
                FROM stock_producto sp
                JOIN productos p ON p.producto_id = sp.producto_id
                WHERE sp.producto_id = :producto_id
                LIMIT 1
            ");
            $stmtStock->execute([':producto_id' => $productoId]);
            $stockData = $stmtStock->fetch(PDO::FETCH_ASSOC);
            
            if (!$stockData) {
                throw new Exception("No se encontró información de stock para el producto ID: $productoId");
            }
            
            $stockDisponible = (float)($stockData['stock_prod_existente'] ?? 0);
            $productoNombre = $stockData['producto_descri'] ?? "ID: $productoId";
            
            if ($stockDisponible < $cantidad) {
                // Según especificación: "si no hay stock suficiente al emitir, informar y permitir ajuste"
                throw new Exception("Stock insuficiente para el producto '$productoNombre'. Disponible: $stockDisponible, Solicitado: $cantidad");
            }
        }

        $pdo->beginTransaction();

        // Si no hay pedido, crear uno temporal "SIN PEDIDO" (id_pedido_venta es NOT NULL)
        if ($pedidoVentaId === null) {
            // Obtener el próximo ID disponible (máximo + 1) dentro de la transacción
            $stmtMaxId = $pdo->prepare("SELECT COALESCE(MAX(id_pedido_venta), 0) + 1 FROM pedido_venta");
            execOrFail($pdo, $stmtMaxId, [], 'SELECT MAX id_pedido_venta');
            $nextId = (int)$stmtMaxId->fetchColumn();
            
            $stmtPedidoTemp = $pdo->prepare("
                INSERT INTO pedido_venta (id_pedido_venta, pedido_fecha, pedido_estado, id_cliente, id_usuario, id_sucursal)
                VALUES (:pedido_id, CURRENT_DATE, 'FINALIZADO', :cliente, :usuario, :sucursal)
                RETURNING id_pedido_venta
            ");
            execOrFail($pdo, $stmtPedidoTemp, [
                ':pedido_id' => $nextId,
                ':cliente' => $clienteId,
                ':usuario' => $idUsuario,
                ':sucursal' => $idSucursal
            ], 'INSERT pedido_venta temporal');
            $pedidoTemp = $stmtPedidoTemp->fetch(PDO::FETCH_ASSOC);
            if (!$pedidoTemp) {
                throw new Exception("No se pudo crear el pedido temporal.");
            }
            $pedidoVentaId = (int)$pedidoTemp['id_pedido_venta'];
        }

        // Insertar factura
        // Usar id_apertura_cierre (FK a apertura_cierre_caja.id_apertura)
        $sqlFactura = $pdo->prepare("
            INSERT INTO factura_ventas (
                numero_factura, factura_numero, timbrado, id_timbrado, fecha_factura, hora_factura, fecha_emision,
                tipo_factura, id_tipo_operacion, id_cliente, id_pedido_venta, id_apertura_cierre,
                id_usuario, id_sucursal, estado, factura_estado,
                subtotal, iva_5, iva_10, iva_exento, total_general, factura_total,
                tipo_pago, plazo, cuotas, factura_cuotas, interes_pct, observaciones
            )
            VALUES (
                :nro, :nro, :tim, :id_timbrado, :fecha, :hora, :fecha_emision,
                :tipo, :id_tipo_operacion, :cliente, :pedido, :apertura,
                :usuario, :sucursal, 'EMITIDA', 'EMITIDA',
                :subtotal, :iva5, :iva10, :exento, :total, :factura_total,
                :tipo_pago, :plazo, :cuotas, :cuotas, :interes, :obs
            )
            RETURNING id_factura_venta
        ");

        execOrFail($pdo, $sqlFactura, [
            ':nro' => $numeroFactura,
            ':tim' => $timbrado['timbrado'],
            ':id_timbrado' => $timbrado['id_timbrado'],
            ':fecha' => $fecha,
            ':hora' => $hora,
            ':fecha_emision' => $fecha_emision,
            ':tipo' => $tipoFactura,
            ':id_tipo_operacion' => $idTipoOperacion,
            ':cliente' => $clienteId,
            ':pedido' => $pedidoVentaId,
            ':apertura' => $aperturaId,
            ':usuario' => $idUsuario,
            ':sucursal' => $idSucursal,
            ':subtotal' => $subtotal,
            ':iva5' => $iva5,
            ':iva10' => $iva10,
            ':exento' => $ivaExento,
            ':total' => $totalGeneral,
            ':factura_total' => $totalGeneral,
            ':tipo_pago' => $tipoPago,
            ':plazo' => $plazo,
            ':cuotas' => $cuotas,
            ':interes' => $interesPct,
            ':obs' => $observaciones
        ], 'INSERT factura_ventas');

        $facturaId = (int)$sqlFactura->fetchColumn();
        if ($facturaId <= 0) {
            throw new Exception("No se pudo crear la factura.");
        }

        // Insertar detalle
        // Según la estructura de factura_detalle_venta: id_factura_venta, producto_id, cantidad,
        // precio_unitario, subtotal, iva_porcentaje, iva_monto, total_linea
        $sqlDetalle = $pdo->prepare("
            INSERT INTO factura_detalle_venta (
                id_factura_venta, producto_id, cantidad,
                precio_unitario, subtotal, iva_porcentaje, iva_monto, total_linea
            )
            VALUES (
                :factura, :producto, :cantidad,
                :precio, :subtotal, :iva_pct, :iva_monto, :total
            )
        ");

        foreach ($productos as $prod) {
            $productoId = (int)($prod['codigo'] ?? 0);
            $cantidad = (int)($prod['cantidad'] ?? 0);
            $precio = (float)($prod['precio'] ?? 0);
            $ivaPorcentaje = (float)($prod['ivaPorcentaje'] ?? 0);
            $subtotalLinea = $cantidad * $precio;
            $ivaMonto = $subtotalLinea * ($ivaPorcentaje / 100);
            $totalLinea = $subtotalLinea + $ivaMonto;

            if ($productoId <= 0 || $cantidad <= 0 || $precio <= 0) {
                continue;
            }

            execOrFail($pdo, $sqlDetalle, [
                ':factura' => $facturaId,
                ':producto' => $productoId,
                ':cantidad' => $cantidad,
                ':precio' => $precio,
                ':subtotal' => $subtotalLinea,
                ':iva_pct' => $ivaPorcentaje,
                ':iva_monto' => $ivaMonto,
                ':total' => $totalLinea
            ], "INSERT detalle producto $productoId");

            // Descontar stock
            $sqlStock = $pdo->prepare("
                UPDATE stock_producto
                SET stock_prod_existente = GREATEST(0, stock_prod_existente - :cantidad)
                WHERE producto_id = :producto
                RETURNING id_stock_productos
            ");
            $sqlStock->execute([':cantidad' => $cantidad, ':producto' => $productoId]);
            $stockRow = $sqlStock->fetch(PDO::FETCH_ASSOC);
            
            if (!$stockRow) {
                // Si no existe stock, crear registro con cantidad negativa o lanzar error
                throw new Exception("No hay stock disponible para el producto ID: $productoId");
            }
        }

        // Registrar IVA
        $sqlIva = $pdo->prepare("
            INSERT INTO iva_venta (id_factura_venta, iva_fecha, iva_exento, iva_5, iva_10)
            VALUES (:factura, :fecha, :exento, :iva5, :iva10)
        ");
        execOrFail($pdo, $sqlIva, [
            ':factura' => $facturaId,
            ':fecha' => $fecha,
            ':exento' => $ivaExento,
            ':iva5' => $iva5,
            ':iva10' => $iva10
        ], 'INSERT iva_venta');

        // Generar Cuenta por Cobrar si es crédito
        // Según especificación: "Crédito → el sistema genera Cuenta por Cobrar"
        if ($tipoFactura === 'CREDITO') {
            $sqlCxC = $pdo->prepare("
                INSERT INTO cuentas_cobrar (
                    id_factura_venta, id_cliente, monto_total, saldo_pendiente,
                    fecha_vencimiento, estado, cuotas, interes_pct, observaciones, id_usuario
                )
                VALUES (
                    :factura, :cliente, :total, :total,
                    :vencimiento, 'PENDIENTE', :cuotas, :interes, :obs, :usuario
                )
                RETURNING id_cuenta_cobrar
            ");

            $fechaVencimiento = date('Y-m-d', strtotime("+30 days")); // Por defecto 30 días
            if (!empty($plazo)) {
                // Intentar extraer días del plazo
                if (preg_match('/(\d+)/', $plazo, $matches)) {
                    $dias = (int)$matches[1];
                    $fechaVencimiento = date('Y-m-d', strtotime("+$dias days"));
                }
            }

            execOrFail($pdo, $sqlCxC, [
                ':factura' => $facturaId,
                ':cliente' => $clienteId,
                ':total' => $totalGeneral,
                ':vencimiento' => $fechaVencimiento,
                ':cuotas' => $cuotas,
                ':interes' => $interesPct,
                ':obs' => $observaciones,
                ':usuario' => $idUsuario
            ], 'INSERT cuentas_cobrar');

            $cxcId = (int)$sqlCxC->fetchColumn();
            try {
                bitacora($pdo, $idUsuario, 'ALTA', "CxC #$cxcId | Factura #$facturaId | Total:$totalGeneral | Cuotas:$cuotas", $cxcId);
            } catch (Throwable $e) {}
        }

        // Actualizar estado de pedido si existe (según especificación punto 16)
        // Si la factura proviene de Pedido, lo marca como Facturado
        if ($pedidoVentaId > 0) {
            $sqlPedido = $pdo->prepare("
                UPDATE pedido_venta
                SET pedido_estado = 'FACTURADO'
                WHERE id_pedido_venta = :pedido
            ");
            $sqlPedido->execute([':pedido' => $pedidoVentaId]);
            try {
                bitacora($pdo, $idUsuario, 'MODIFICACION', "Pedido #$pedidoVentaId → FACTURADO (Factura #$facturaId)", $pedidoVentaId);
            } catch (Throwable $e) {}
        }

        // Actualizar número de timbrado (según especificación punto 12)
        // Usar clave primaria compuesta (id_timbrado, id_caja)
        // Usar el id_caja del SELECT para asegurar consistencia
        $sqlUpdTimbrado = $pdo->prepare("
            UPDATE caja_timbrado
            SET numero_actual = :numero
            WHERE id_timbrado = :id_timbrado AND id_caja = :id_caja
        ");
        execOrFail($pdo, $sqlUpdTimbrado, [
            ':numero' => $proximoNumero,
            ':id_timbrado' => $timbrado['id_timbrado'],
            ':id_caja' => $timbrado['id_caja']
        ], 'UPDATE caja_timbrado');

        // Registrar bitácora
        try {
            bitacora($pdo, $idUsuario, 'ALTA', "Factura #$facturaId | Cliente:$clienteId | Total:$totalGeneral | Tipo:$tipoFactura", $facturaId);
        } catch (Throwable $e) {}

        $pdo->commit();
        header("Location: view.php?alert=1");
        exit;

    } elseif ($accion === 'anular') {
        // Según especificación punto 21: "Anular" dentro de Facturas: reemplazar por emitir Nota de Crédito.
        // Cambiar estado de la factura por sistema sin documento fiscal no es válido.
        // La NC revierte IVA y, si aplica, stock (si la devolución afecta inventario) y CxC.
        // Por lo tanto, redirigir a Nota de Crédito en lugar de anular directamente.
        $factId = isset($_GET['fact_id']) ? (int)$_GET['fact_id'] : 0;
        if ($factId <= 0) {
            header("Location: view.php?alert=4");
            exit;
        }
        
        // Redirigir al módulo de Nota de Crédito con el ID de la factura
        header("Location: ../nota_credito_venta/form.php?fact_id=$factId");
        exit;
    }

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error en gestionar_ventas/proses.php: " . $e->getMessage());
    header("Location: view.php?alert=4&msg=" . urlencode($e->getMessage()));
    exit;
}
?>

