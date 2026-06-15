<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Mapeo de compatibilidad de variables de sesión
if (!isset($_SESSION["id_usuario"]) && isset($_SESSION["usua_id"])) { 
    $_SESSION["id_usuario"] = $_SESSION["usua_id"]; 
}
if (!isset($_SESSION["id_sucursal"]) && isset($_SESSION["sucursal_id"])) { 
    $_SESSION["id_sucursal"] = $_SESSION["sucursal_id"]; 
}

require "../../config/database.php";

// Verificar si el usuario está autenticado
if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
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
            ':entidad'     => 'presupuesto venta',
            ':id_registro' => $idRegistro,
            ':accion'      => strtoupper($accion),
            ':descripcion' => $descripcion
        ]);
    } catch (Throwable $e) {
        error_log("Bitácora falló: ".$e->getMessage());
    }
}

// Inicializar conexión PDO (fuera de bloques condicionales)
try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

// Detectar la acción
if (isset($_GET['act'])) {
    $action = $_GET['act'];
    
    try {

        // INSERT - Crear nuevo presupuesto
        if ($action == 'insert' && isset($_POST['Guardar'])) {
            
            $presupuesto_id = isset($_POST['codigo']) ? (int)$_POST['codigo'] : 0;
            $cliente_id = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
            $tipo_presupuesto = isset($_POST['tipo_presupuesto']) ? trim($_POST['tipo_presupuesto']) : null;
            $validez_dias = isset($_POST['validez']) ? (int)$_POST['validez'] : null;
            $observacion = isset($_POST['observacion']) ? trim($_POST['observacion']) : null;
            
            // Validar validez
            if ($validez_dias === null || $validez_dias < 1) {
                die('La validez en días debe ser mayor a 0.');
            }
            
            // Calcular fecha de vencimiento (validez es DATE en BD, pero recibimos días)
            $fecha_presupuesto = date('Y-m-d');
            $fecha_vencimiento = date('Y-m-d', strtotime("+{$validez_dias} days"));
            $validez = $fecha_vencimiento; // Guardar como fecha en BD
            $pedido_venta_id = isset($_POST['pedido_venta_id']) && $_POST['pedido_venta_id'] !== '' ? (int)$_POST['pedido_venta_id'] : null;
            $monto_total = isset($_POST['monto_total']) ? (float)$_POST['monto_total'] : 0;

            // Detalle del presupuesto desde hidden JSON
            $detalle = [];
            if (isset($_POST['productos']) && $_POST['productos'] !== '') {
                $tmp = json_decode($_POST['productos'], true);
                if (is_array($tmp)) { 
                    $detalle = $tmp; 
                }
            }

            // Resolver usuario y sucursal
            $usuario_id = 0;
            $id_sucursal = 0;
            
            if (!empty($_SESSION['usua_id'])) {
                $usuario_id = (int)$_SESSION['usua_id'];
            }
            if (!empty($_SESSION['sucursal_id'])) {
                $id_sucursal = (int)$_SESSION['sucursal_id'];
            }

            if ($usuario_id > 0 && $id_sucursal === 0) {
                $q = $pdo->prepare("SELECT id_sucursal FROM usuarios WHERE id_usuario = :id LIMIT 1");
                $q->execute([':id' => $usuario_id]);
                $id_sucursal = (int)$q->fetchColumn();
            }

            if ($usuario_id === 0) {
                $q = $pdo->prepare("SELECT id_usuario, id_sucursal FROM usuarios WHERE username = :u LIMIT 1");
                $q->execute([':u' => $_SESSION['username'] ?? '']);
                $usr = $q->fetch(PDO::FETCH_ASSOC);
                if ($usr) {
                    $usuario_id  = (int)$usr['id_usuario'];
                    $id_sucursal = (int)$usr['id_sucursal'];
                }
            }

            // Validaciones
            if ($usuario_id <= 0 || $id_sucursal <= 0) {
                die('No se pudo obtener id_usuario o id_sucursal desde la sesión.');
            }
            if ($presupuesto_id <= 0) {
                die('Código de presupuesto inválido.');
            }
            if ($cliente_id <= 0) {
                die('Debe seleccionar un cliente.');
            }
            if (!is_array($detalle) || count($detalle) === 0) {
                die('El presupuesto debe contener al menos un producto.');
            }

            // Validar que el cliente esté activo
            $qCliente = $pdo->prepare("SELECT cliente_estado FROM clientes WHERE id_cliente = :id LIMIT 1");
            $qCliente->execute([':id' => $cliente_id]);
            $estadoCliente = $qCliente->fetchColumn();
            if ($estadoCliente !== 'ACTIVO') {
                die('El cliente seleccionado no está activo.');
            }

            // Validar productos y cantidades
            foreach ($detalle as $item) {
                $prodId = (int)($item['codigo'] ?? 0);
                $cant = (int)($item['cantidad'] ?? 0);
                
                if ($prodId <= 0 || $cant <= 0) {
                    die('Producto o cantidad inválida en el detalle.');
                }
                
                // Validar que el producto exista y esté activo
                $qProd = $pdo->prepare("SELECT producto_estado FROM productos WHERE producto_id = :id LIMIT 1");
                $qProd->execute([':id' => $prodId]);
                $estadoProd = $qProd->fetchColumn();
                if ($estadoProd !== 'ACTIVO') {
                    die("El producto con ID {$prodId} no está activo.");
                }
            }

            // Iniciar transacción
            $pdo->beginTransaction();

            // Insertar cabecera del presupuesto
            $query_presupuesto = $pdo->prepare("
                INSERT INTO presupuesto_venta (
                    id_presupuesto_venta, 
                    fecha_presupuesto, 
                    estado, 
                    id_cliente, 
                    id_usuario, 
                    id_sucursal,
                    validez,
                    observacion,
                    id_pedido_venta,
                    monto_total
                ) 
                VALUES (
                    :presupuesto_id, 
                    CURRENT_DATE, 
                    'PENDIENTE', 
                    :id_cliente, 
                    :id_usuario, 
                    :id_sucursal,
                    :validez,
                    :observacion,
                    :id_pedido_venta,
                    :monto_total
                )
            ");
            $query_presupuesto->execute([
                ':presupuesto_id' => $presupuesto_id,
                ':id_cliente' => $cliente_id,
                ':id_usuario' => $usuario_id,
                ':id_sucursal' => $id_sucursal,
                ':validez' => $validez ?: null,
                ':observacion' => $observacion ?: null,
                ':id_pedido_venta' => $pedido_venta_id,
                ':monto_total' => $monto_total
            ]);

            bitacora($pdo, $usuario_id, 'ALTA', "Se inserta registro cabecera de Presupuesto Venta #{$presupuesto_id}", $presupuesto_id);

            // Insertar detalles
            $query_detalle = $pdo->prepare("
                INSERT INTO detalle_presupuesto_venta (
                    id_presupuesto_venta, 
                    producto_id, 
                    cantidad, 
                    precio_unitario,
                    iva
                )
                VALUES (
                    :presupuesto_id, 
                    :producto_id, 
                    :cantidad, 
                    :precio_unitario,
                    :iva
                )
            ");

            foreach ($detalle as $item) {
                $prodId = (int)$item['codigo'];
                $cant = (int)$item['cantidad'];
                $precio = (float)($item['precio'] ?? 0);
                $ivaPorcentaje = (float)($item['ivaPorcentaje'] ?? 0);
                
                $query_detalle->execute([
                    ':presupuesto_id' => $presupuesto_id,
                    ':producto_id' => $prodId,
                    ':cantidad' => $cant,
                    ':precio_unitario' => $precio,
                    ':iva' => $ivaPorcentaje
                ]);

                bitacora($pdo, $usuario_id, 'ALTA',
                    "Detalle agregado (presupuesto {$presupuesto_id}, producto {$prodId}, cantidad {$cant})",
                    $presupuesto_id);
            }

            $pdo->commit();
            header("Location: view.php?alert=1");
            exit;
        }
        
        // UPDATE - Editar presupuesto
        elseif ($action === 'update' && isset($_POST['Guardar'])) {
            $presupuesto_id  = isset($_POST['presupuesto_id']) ? (int)$_POST['presupuesto_id'] : 0;
            $cliente_id = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
            $tipo_presupuesto = isset($_POST['tipo_presupuesto']) ? trim($_POST['tipo_presupuesto']) : '';
            $validez_dias = isset($_POST['validez']) ? (int)$_POST['validez'] : null;
            $observacion = isset($_POST['observacion']) ? trim($_POST['observacion']) : null;
            $pedido_venta_id = isset($_POST['pedido_venta_id']) && $_POST['pedido_venta_id'] !== '' ? (int)$_POST['pedido_venta_id'] : null;
            
            // Detalle del presupuesto desde hidden JSON
            $detalle = [];
            if (isset($_POST['productos']) && $_POST['productos'] !== '') {
                $tmp = json_decode($_POST['productos'], true);
                if (is_array($tmp)) { 
                    $detalle = $tmp; 
                }
            }
            
            // También obtener cantidades del formulario (para compatibilidad)
            $cantidades = isset($_POST['cantidad']) && is_array($_POST['cantidad']) ? $_POST['cantidad'] : [];

            // Usuario desde sesión
            if (!empty($_SESSION['usua_id'])) {
                $usuario_id = (int) $_SESSION['usua_id'];
            } else {
                $stmtUid = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
                $stmtUid->execute([':u' => $_SESSION['username'] ?? '']);
                $usuario_id = (int) $stmtUid->fetchColumn();
            }

            if ($presupuesto_id <= 0 || $usuario_id <= 0) {
                header("Location: view.php?alert=4");
                exit;
            }
            
            // Validaciones
            if ($cliente_id <= 0) {
                die('Debe seleccionar un cliente.');
            }
            if ($validez_dias === null || $validez_dias < 1) {
                die('La validez en días debe ser mayor a 0.');
            }
            if (!is_array($detalle) || count($detalle) === 0) {
                // Si no hay detalle en JSON, usar cantidades del formulario
                if (empty($cantidades)) {
                    die('El presupuesto debe contener al menos un producto.');
                }
            }

            try {
                date_default_timezone_set('America/Asuncion');
                $stamp = date('Y-m-d H:i:s');

                $pdo->beginTransaction();

                // Verificar existencia/estado y BLOQUEAR
                $st = $pdo->prepare("
                    SELECT estado, fecha_presupuesto
                    FROM presupuesto_venta
                    WHERE id_presupuesto_venta = :id
                    FOR UPDATE
                ");
                $st->execute([':id' => $presupuesto_id]);
                $presupuestoActual = $st->fetch(PDO::FETCH_ASSOC);

                if (!$presupuestoActual) {
                    $pdo->rollBack();
                    header("Location: view.php?alert=4");
                    exit;
                }

                $estado = $presupuestoActual['estado'];
                $fecha_presupuesto = $presupuestoActual['fecha_presupuesto'];

                $estadoNorm = strtoupper(trim((string)$estado));
                if (!in_array($estadoNorm, ['PENDIENTE', 'EMITIDO'], true)) {
                    $pdo->rollBack();
                    header("Location: view.php?alert=5");
                    exit;
                }
                
                // Verificar si fue convertido en Factura de Venta (conversión directa)
                $stFactura = $pdo->prepare("
                    SELECT 1 
                    FROM factura_ventas 
                    WHERE id_presupuesto_venta = :id 
                    LIMIT 1
                ");
                $stFactura->execute([':id' => $presupuesto_id]);
                if ($stFactura->fetchColumn()) {
                    $pdo->rollBack();
                    header("Location: view.php?alert=6");
                    exit;
                }

                // Validar cliente activo
                $qCliente = $pdo->prepare("SELECT cliente_estado FROM clientes WHERE id_cliente = :id LIMIT 1");
                $qCliente->execute([':id' => $cliente_id]);
                $estadoCliente = $qCliente->fetchColumn();
                if ($estadoCliente !== 'ACTIVO') {
                    $pdo->rollBack();
                    die('El cliente seleccionado no está activo.');
                }

                // Calcular fecha de vencimiento desde días
                $fecha_vencimiento = date('Y-m-d', strtotime("$fecha_presupuesto +{$validez_dias} days"));

                // Actualizar cabecera
                $updCabecera = $pdo->prepare("
                    UPDATE presupuesto_venta
                    SET id_cliente = :cliente_id,
                        validez = :validez,
                        observacion = :observacion,
                        id_pedido_venta = :pedido_venta_id
                    WHERE id_presupuesto_venta = :presupuesto_id
                ");
                $updCabecera->execute([
                    ':cliente_id' => $cliente_id,
                    ':validez' => $fecha_vencimiento,
                    ':observacion' => $observacion ?: null,
                    ':pedido_venta_id' => $pedido_venta_id,
                    ':presupuesto_id' => $presupuesto_id
                ]);

                bitacora($pdo, $usuario_id, 'MODIFICACION', 
                    "Actualiza cabecera presupuesto #{$presupuesto_id}: Cliente, Validez, Observación", 
                    $presupuesto_id);

                // Obtener productos actuales del detalle
                $act = $pdo->prepare("
                    SELECT producto_id, cantidad, precio_unitario, iva
                    FROM detalle_presupuesto_venta
                    WHERE id_presupuesto_venta = :id
                ");
                $act->execute([':id' => $presupuesto_id]);
                $productosActuales = [];
                foreach ($act->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $productosActuales[(int)$r['producto_id']] = [
                        'cantidad' => (int)$r['cantidad'],
                        'precio' => (float)$r['precio_unitario'],
                        'iva' => (int)$r['iva']
                    ];
                }

                // Obtener precios actuales de productos y calcular IVA
                $precios = [];
                $ivas = [];
                $qPrecios = $pdo->query("
                    SELECT p.producto_id, p.producto_precio, 
                           COALESCE(p.iva_id, 0) AS iva_id,
                           COALESCE(ti.iva_descri, 'N/A') AS iva_descri
                    FROM productos p
                    LEFT JOIN tipo_iva ti ON p.iva_id = ti.iva_id
                    WHERE p.producto_estado = 'ACTIVO'
                ");
                foreach ($qPrecios->fetchAll(PDO::FETCH_ASSOC) as $p) {
                    $precios[(int)$p['producto_id']] = (float)$p['producto_precio'];
                    $ivas[(int)$p['producto_id']] = [
                        'iva_id' => (int)$p['iva_id'],
                        'iva_descri' => $p['iva_descri']
                    ];
                }

                // Procesar detalle desde JSON (si existe) o desde cantidades
                $productosNuevos = [];
                $productosActualizar = [];
                $productosEliminar = array_keys($productosActuales);

                if (!empty($detalle) && is_array($detalle)) {
                    // Usar detalle desde JSON (nuevo método)
                    foreach ($detalle as $item) {
                        $prodId = (int)($item['codigo'] ?? 0);
                        $cant = (int)($item['cantidad'] ?? 0);
                        $precio = (float)($item['precio'] ?? 0);
                        $ivaPorcentaje = (float)($item['ivaPorcentaje'] ?? 0);
                        
                        if ($prodId <= 0 || $cant < 1) {
                            continue;
                        }
                        
                        // Validar producto activo
                        if (!isset($precios[$prodId])) {
                            continue; // Producto no existe o no está activo
                        }
                        
                        // Usar precio actual del producto (puede haber cambiado)
                        $precioActual = $precios[$prodId];
                        
                        // Calcular IVA desde el producto actual
                        $ivaInfo = $ivas[$prodId] ?? null;
                        $ivaPct = 0;
                        if ($ivaInfo && $ivaInfo['iva_id'] > 0) {
                            $ivaMatch = preg_match('/(\d+)/', $ivaInfo['iva_descri'], $matches);
                            if ($ivaMatch && isset($matches[1])) {
                                $ivaPct = (int)$matches[1];
                            }
                        }
                        
                        $existe = isset($productosActuales[$prodId]);
                        $productosEliminar = array_diff($productosEliminar, [$prodId]);
                        
                        if ($existe) {
                            // Actualizar producto existente
                            $productosActualizar[$prodId] = [
                                'cantidad' => $cant,
                                'precio' => $precioActual,
                                'iva' => $ivaPct
                            ];
                        } else {
                            // Nuevo producto
                            $productosNuevos[$prodId] = [
                                'cantidad' => $cant,
                                'precio' => $precioActual,
                                'iva' => $ivaPct
                            ];
                        }
                    }
                } else {
                    // Método antiguo: solo actualizar cantidades de productos existentes
                    foreach ($cantidades as $idProd => $cant) {
                        $idProd = (int)$idProd;
                        $cant = (int)$cant;
                        
                        if ($idProd <= 0 || $cant < 1) {
                            continue;
                        }
                        
                        if (isset($productosActuales[$idProd])) {
                            $productosActualizar[$idProd] = [
                                'cantidad' => $cant,
                                'precio' => $productosActuales[$idProd]['precio'],
                                'iva' => $productosActuales[$idProd]['iva']
                            ];
                            $productosEliminar = array_diff($productosEliminar, [$idProd]);
                        }
                    }
                }

                // Eliminar productos que ya no están
                $delDetalle = $pdo->prepare("
                    DELETE FROM detalle_presupuesto_venta
                    WHERE id_presupuesto_venta = :presupuesto_id
                    AND producto_id = :producto_id
                ");
                foreach ($productosEliminar as $prodId) {
                    $delDetalle->execute([
                        ':presupuesto_id' => $presupuesto_id,
                        ':producto_id' => $prodId
                    ]);
                    bitacora($pdo, $usuario_id, 'MODIFICACION', 
                        "Elimina producto {$prodId} del presupuesto #{$presupuesto_id}", 
                        $presupuesto_id);
                }

                // Actualizar productos existentes
                $updDetalle = $pdo->prepare("
                    UPDATE detalle_presupuesto_venta
                    SET cantidad = :cantidad,
                        precio_unitario = :precio,
                        iva = :iva
                    WHERE id_presupuesto_venta = :presupuesto_id
                    AND producto_id = :producto_id
                ");
                foreach ($productosActualizar as $prodId => $datos) {
                    $updDetalle->execute([
                        ':cantidad' => $datos['cantidad'],
                        ':precio' => $datos['precio'],
                        ':iva' => $datos['iva'],
                        ':presupuesto_id' => $presupuesto_id,
                        ':producto_id' => $prodId
                    ]);
                    bitacora($pdo, $usuario_id, 'MODIFICACION', 
                        "Actualiza producto {$prodId} en presupuesto #{$presupuesto_id}: cantidad {$datos['cantidad']}", 
                        $presupuesto_id);
                }

                // Insertar nuevos productos
                $insDetalle = $pdo->prepare("
                    INSERT INTO detalle_presupuesto_venta (
                        id_presupuesto_venta,
                        producto_id,
                        cantidad,
                        precio_unitario,
                        iva
                    )
                    VALUES (
                        :presupuesto_id,
                        :producto_id,
                        :cantidad,
                        :precio,
                        :iva
                    )
                ");
                foreach ($productosNuevos as $prodId => $datos) {
                    $insDetalle->execute([
                        ':presupuesto_id' => $presupuesto_id,
                        ':producto_id' => $prodId,
                        ':cantidad' => $datos['cantidad'],
                        ':precio' => $datos['precio'],
                        ':iva' => $datos['iva']
                    ]);
                    bitacora($pdo, $usuario_id, 'MODIFICACION', 
                        "Agrega producto {$prodId} al presupuesto #{$presupuesto_id}: cantidad {$datos['cantidad']}", 
                        $presupuesto_id);
                }

                // Recalcular monto_total desde el detalle actualizado
                $calcTotal = $pdo->prepare("
                    SELECT 
                        SUM(
                            CASE 
                                WHEN d.iva > 0 THEN (d.cantidad * d.precio_unitario * (1 + d.iva / 100))
                                ELSE (d.cantidad * d.precio_unitario)
                            END
                        ) AS total
                    FROM detalle_presupuesto_venta d
                    WHERE d.id_presupuesto_venta = :id
                ");
                $calcTotal->execute([':id' => $presupuesto_id]);
                $nuevoTotal = (float)($calcTotal->fetchColumn() ?? 0);

                // Actualizar monto_total en cabecera
                $updTotal = $pdo->prepare("
                    UPDATE presupuesto_venta
                    SET monto_total = :monto_total
                    WHERE id_presupuesto_venta = :presupuesto_id
                ");
                $updTotal->execute([
                    ':monto_total' => $nuevoTotal,
                    ':presupuesto_id' => $presupuesto_id
                ]);

                $pdo->commit();
                header("Location: view.php?alert=2");
                exit;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { 
                    $pdo->rollBack(); 
                }
                die("Error al actualizar el presupuesto: " . $e->getMessage());
            }
        }
        
        // ANULAR PRESUPUESTO
        elseif ($action === 'anular') {
            $pre_id = isset($_GET['pre_id']) ? (int)$_GET['pre_id'] : 0;
            
            if ($pre_id <= 0) {
                header("Location: view.php?alert=4");
                exit;
            }
            
            // Obtener usuario
            if (!empty($_SESSION['usua_id'])) {
                $usuario_id = (int) $_SESSION['usua_id'];
            } else {
                $stmtUid = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
                $stmtUid->execute([':u' => $_SESSION['username'] ?? '']);
                $usuario_id = (int) $stmtUid->fetchColumn();
            }
            
            if ($usuario_id <= 0) {
                header("Location: view.php?alert=4");
                exit;
            }
            
            try {
                $pdo->beginTransaction();
                
                // Verificar que el estado sea PENDIENTE
                $st = $pdo->prepare("
                    SELECT estado
                    FROM presupuesto_venta
                    WHERE id_presupuesto_venta = :id
                    FOR UPDATE
                ");
                $st->execute([':id' => $pre_id]);
                $estado = $st->fetchColumn();
                
                if (!$estado) {
                    $pdo->rollBack();
                    header("Location: view.php?alert=4");
                    exit;
                }
                
                $estadoNormalizado = strtoupper(trim($estado));
                if ($estadoNormalizado !== 'PENDIENTE') {
                    $pdo->rollBack();
                    header("Location: view.php?alert=4");
                    exit;
                }
                
                // Verificar que no haya factura de venta asociada
                $stFactura = $pdo->prepare("
                    SELECT 1 
                    FROM factura_ventas 
                    WHERE id_presupuesto_venta = :id 
                    LIMIT 1
                ");
                $stFactura->execute([':id' => $pre_id]);
                if ($stFactura->fetchColumn()) {
                    $pdo->rollBack();
                    header("Location: view.php?alert=6");
                    exit;
                }
                
                // Actualizar estado a ANULADO
                $upd = $pdo->prepare("
                    UPDATE presupuesto_venta
                    SET estado = 'ANULADO'
                    WHERE id_presupuesto_venta = :id
                ");
                $upd->execute([':id' => $pre_id]);
                
                $rowsAffected = $upd->rowCount();
                
                // Verificar que se actualizó
                if ($rowsAffected === 0) {
                    $pdo->rollBack();
                    die("ERROR: No se actualizó ninguna fila. ID: {$pre_id}, Estado antes: {$estadoNormalizado}");
                }
                
                // Commit de la transacción PRIMERO
                $pdo->commit();
                
                // Registrar en bitácora DESPUÉS del commit (para evitar que afecte la transacción)
                try {
                    bitacora($pdo, $usuario_id, 'ANULACION', 
                        "Presupuesto #{$pre_id} anulado", 
                        $pre_id);
                } catch (Throwable $e) {
                    // Ignorar errores de bitácora, no deben bloquear la anulación
                }
                
                header("Location: view.php?alert=3");
                exit;
                
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                die("Error al anular el presupuesto: " . $e->getMessage());
            }
        }

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error en la operación con la base de datos: " . $e->getMessage());
    }
} else {
    header("Location: view.php?alert=4");
    exit;
}
?>

