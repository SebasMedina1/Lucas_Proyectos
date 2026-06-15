<?php
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

// Función de bitácora (si existe la tabla, si no, se omite silenciosamente)
function bitacora(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void {
    try {
        // Verificar si existe la tabla bitacora
        $check = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'bitacora' LIMIT 1");
        if ($check->rowCount() === 0) {
            return; // No existe la tabla, salir silenciosamente
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

// Detectar la acción
if (isset($_GET['act'])) {
    $action = $_GET['act'];
    
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // INSERT - Crear nuevo pedido
        if ($action == 'insert' && isset($_POST['Guardar'])) {
            
            $pedido_id = isset($_POST['codigo']) ? (int)$_POST['codigo'] : 0;
            $cliente_id = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;

            // Detalle del pedido desde hidden JSON
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

            // Si no está en sesión, obtener desde POST o consultar BD
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
            if ($pedido_id <= 0) {
                die('Código de pedido inválido.');
            }
            if ($cliente_id <= 0) {
                die('Debe seleccionar un cliente.');
            }
            if (!is_array($detalle) || count($detalle) === 0) {
                die('El pedido debe contener al menos un producto.');
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

            // Insertar cabecera del pedido
            $query_pedido = $pdo->prepare("
                INSERT INTO pedido_venta (id_pedido_venta, pedido_fecha, pedido_estado, id_cliente, id_usuario, id_sucursal) 
                VALUES (:pedido_id, CURRENT_DATE, 'PENDIENTE', :id_cliente, :id_usuario, :id_sucursal)
            ");
            $query_pedido->execute([
                ':pedido_id' => $pedido_id,
                ':id_cliente' => $cliente_id,
                ':id_usuario' => $usuario_id,
                ':id_sucursal' => $id_sucursal
            ]);

            bitacora($pdo, $usuario_id, 'ALTA', "Se inserta registro cabecera de Pedido Venta #{$pedido_id}", $pedido_id);

            // Insertar detalles
            $query_detalle = $pdo->prepare("
                INSERT INTO detalle_pedido_venta (id_pedido_venta, producto_id, cantidad_pedido, pedido_precio_total)
                VALUES (:pedido_id, :producto_id, :cantidad, :precio_total)
            ");

            foreach ($detalle as $item) {
                $prodId = (int)$item['codigo'];
                $cant = (int)$item['cantidad'];
                $precio = (float)($item['precio'] ?? 0);
                
                // Calcular precio total (con IVA si aplica)
                $precioTotal = (float)($item['subtotalConIva'] ?? ($item['subtotal'] ?? ($precio * $cant)));

                $query_detalle->execute([
                    ':pedido_id' => $pedido_id,
                    ':producto_id' => $prodId,
                    ':cantidad' => $cant,
                    ':precio_total' => $precioTotal
                ]);

                bitacora($pdo, $usuario_id, 'ALTA',
                    "Detalle agregado (pedido {$pedido_id}, producto {$prodId}, cantidad {$cant})",
                    $pedido_id);
            }

            $pdo->commit();
            header("Location: view.php?alert=1");
            exit;
        }
        
        // UPDATE - Editar pedido
        elseif ($action === 'update' && isset($_POST['Guardar'])) {
            $pedido_id  = isset($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : 0;
            $cantidades = isset($_POST['cantidad']) && is_array($_POST['cantidad']) ? $_POST['cantidad'] : [];

            // Usuario desde sesión
            if (!empty($_SESSION['usua_id'])) {
                $usuario_id = (int) $_SESSION['usua_id'];
            } else {
                $stmtUid = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
                $stmtUid->execute([':u' => $_SESSION['username'] ?? '']);
                $usuario_id = (int) $stmtUid->fetchColumn();
            }

            if ($pedido_id <= 0 || $usuario_id <= 0) {
                header("Location: view.php?alert=4");
                exit;
            }

            if (empty($cantidades)) {
                header("Location: view.php?alert=4");
                exit;
            }

            try {
                date_default_timezone_set('America/Asuncion');
                $stamp = date('Y-m-d H:i:s');

                $pdo->beginTransaction();

                // Verificar existencia/estado y BLOQUEAR
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
                    header("Location: view.php?alert=4");
                    exit;
                }

                $estadoNorm = strtoupper(trim((string)$estado));
                if ($estadoNorm !== 'PENDIENTE') {
                    $pdo->rollBack();
                    header("Location: view.php?alert=5");
                    exit;
                }
                
                // Verificar que no tenga documentos posteriores (presupuesto o factura)
                $qPresupuesto = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM presupuesto_venta 
                    WHERE id_pedido_venta = :id
                ");
                $qPresupuesto->execute([':id' => $pedido_id]);
                $tienePresupuesto = $qPresupuesto->fetchColumn() > 0;

                $qFactura = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM factura_ventas 
                    WHERE id_pedido_venta = :id
                ");
                $qFactura->execute([':id' => $pedido_id]);
                $tieneFactura = $qFactura->fetchColumn() > 0;

                if ($tienePresupuesto || $tieneFactura) {
                    $pdo->rollBack();
                    header("Location: view.php?alert=6");
                    exit;
                }

                // Cargar cantidades actuales
                $act = $pdo->prepare("
                    SELECT producto_id, cantidad_pedido
                    FROM detalle_pedido_venta
                    WHERE id_pedido_venta = :id
                ");
                $act->execute([':id' => $pedido_id]);
                $actuales = [];
                foreach ($act->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $actuales[(int)$r['producto_id']] = (int)$r['cantidad_pedido'];
                }

                // Preparar UPDATE
                $upd = $pdo->prepare("
                    UPDATE detalle_pedido_venta
                    SET cantidad_pedido = :cantidad,
                        pedido_precio_total = :precio_total
                    WHERE id_pedido_venta = :pedido_id
                    AND producto_id = :producto_id
                ");

                // Obtener precios de productos y calcular IVA si aplica
                $precios = [];
                $ivas = [];
                $qPrecios = $pdo->query("
                    SELECT p.producto_id, p.producto_precio, 
                           COALESCE(p.iva_id, 0) AS iva_id,
                           COALESCE(ti.iva_descri, 'N/A') AS iva_descri
                    FROM productos p
                    LEFT JOIN tipo_iva ti ON p.iva_id = ti.iva_id
                ");
                foreach ($qPrecios->fetchAll(PDO::FETCH_ASSOC) as $p) {
                    $precios[(int)$p['producto_id']] = (float)$p['producto_precio'];
                    $ivas[(int)$p['producto_id']] = [
                        'iva_id' => (int)$p['iva_id'],
                        'iva_descri' => $p['iva_descri']
                    ];
                }

                // Recorrer cantidades nuevas
                foreach ($cantidades as $idProd => $cant) {
                    $idProd = (int)$idProd;
                    $cant   = (int)$cant;

                    if ($idProd <= 0 || $cant < 1) {
                        continue;
                    }

                    $anterior = $actuales[$idProd] ?? null;
                    if ($anterior === null) {
                        continue; // Producto no existe en el pedido original
                    }

                    if ($anterior !== $cant) {
                        $precio = $precios[$idProd] ?? 0;
                        $subtotal = $precio * $cant;
                        
                        // Calcular IVA si aplica
                        $ivaInfo = $ivas[$idProd] ?? null;
                        $precioTotal = $subtotal;
                        
                        if ($ivaInfo && $ivaInfo['iva_id'] > 0) {
                            // Extraer porcentaje de iva_descri
                            $ivaMatch = preg_match('/(\d+)/', $ivaInfo['iva_descri'], $matches);
                            if ($ivaMatch && isset($matches[1])) {
                                $porcentajeIva = (float)$matches[1] / 100;
                                $precioTotal = $subtotal * (1 + $porcentajeIva);
                            }
                        }

                        $upd->execute([
                            ':cantidad'    => $cant,
                            ':precio_total' => $precioTotal,
                            ':pedido_id'   => $pedido_id,
                            ':producto_id' => $idProd
                        ]);

                        $descripcion = "[$stamp] Modifica cantidad del producto {$idProd} en pedido {$pedido_id}: {$anterior} → {$cant}";
                        bitacora($pdo, $usuario_id, 'MODIFICACION', $descripcion, $pedido_id);
                    }
                }

                $pdo->commit();
                header("Location: view.php?alert=2");
                exit;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { 
                    $pdo->rollBack(); 
                }
                die("Error al actualizar el pedido: " . $e->getMessage());
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

