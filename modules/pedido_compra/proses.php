<?php
session_start();

// Mapeo de compatibilidad de variables de sesi�n
if (!isset($_SESSION["id_usuario"]) && isset($_SESSION["usua_id"])) { $_SESSION["id_usuario"] = $_SESSION["usua_id"]; }
if (!isset($_SESSION["id_sucursal"]) && isset($_SESSION["sucursal_id"])) { $_SESSION["id_sucursal"] = $_SESSION["sucursal_id"]; }


require "../../config/database.php"; // Asegúrate de tener la conexión a la base de datos

// Verificar si el usuario está autenticado
if (empty($_SESSION['username']) ) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}

function bitacora(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ");
        $stmt->execute([
            ':id_usuario'  => $idUsuario,
            ':entidad'     => 'pedido compra',
            ':id_registro' => $idRegistro,
            ':accion'      => strtoupper($accion),   // debe pasar el CHECK
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
        // Conectar a la base de datos
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Insertar un nuevo pedido con sus detalles
        if ($action == 'insert' && isset($_POST['Guardar'])) {
            
            // ID del pedido (readonly en el form)
            $pedido_id = isset($_POST['codigo']) ? (int)$_POST['codigo'] : 0;

            // Detalle del pedido desde hidden JSON
            $detalle = [];
            if (isset($_POST['productos']) && $_POST['productos'] !== '') {
                $tmp = json_decode($_POST['productos'], true);
                if (is_array($tmp)) { $detalle = $tmp; }
            }

            // Resolver usuario y sucursal desde la sesi�n
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

            if ($usuario_id <= 0 || $id_sucursal <= 0) {
                die('No se pudo obtener id_usuario o id_sucursal desde la sesi�n.');
            }

            if ($pedido_id <= 0) {
                die('Codigo de pedido invalido.');
            }
            if (!is_array($detalle) || count($detalle) === 0) {
                die('El pedido no contiene detalles.');
            }

            // Iniciar una transacci�n
            $pdo->beginTransaction();

            // Obtener observaciones del POST
            $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : null;

            // Insertar el pedido (sin proveedor, se asigna en el presupuesto)
            $query_pedido = $pdo->prepare("INSERT INTO pedidos_compra (id_pedido_compra, pedido_fecha_emision, pedido_estado, id_usuario, id_sucursal, pedido_observaciones, pedido_ultima_modificacion) 
                                           VALUES (:pedido_id, NOW(), 'PENDIENTE', :id_usuario, :id_sucursal, :observaciones, CURRENT_TIMESTAMP)");
            $query_pedido->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
            $query_pedido->bindParam(':id_usuario', $usuario_id, PDO::PARAM_INT);
            $query_pedido->bindParam(':id_sucursal', $id_sucursal, PDO::PARAM_INT);
            $query_pedido->bindParam(':observaciones', $observaciones, PDO::PARAM_STR);
            $query_pedido->execute();

            bitacora($pdo, $usuario_id, 'ALTA', 'Se inserta registro cabecera de Pedido Compra', $pedido_id);

            // Validar stock máximo antes de insertar detalles
            $query_validar_stock = $pdo->prepare("
                SELECT 
                    mp.materia_prima_descripcion,
                    COALESCE(smp.cantidad_existente, 0) as stock_actual,
                    COALESCE(smp.stock_cantidad_maxima, 0) as stock_maximo
                FROM materia_prima mp
                LEFT JOIN stock_materia_prima smp ON smp.id_materia_prima = mp.id_materia_prima
                WHERE mp.id_materia_prima = :id_materia_prima
                LIMIT 1
            ");

            $errores_stock = [];
            foreach ($detalle as $item) {
                $id_materia_prima = (int)$item['codigo'];
                $cantidad_pedido = (int)$item['cantidad'];
                
                if ($id_materia_prima <= 0 || $cantidad_pedido <= 0) {
                    continue;
                }

                $query_validar_stock->execute([':id_materia_prima' => $id_materia_prima]);
                $stock_data = $query_validar_stock->fetch(PDO::FETCH_ASSOC);
                
                if ($stock_data) {
                    $stock_actual = (int)$stock_data['stock_actual'];
                    $stock_maximo = (int)$stock_data['stock_maximo'];
                    
                    // Validar que stock_actual + cantidad_pedido no supere stock_maximo
                    if ($stock_maximo > 0 && ($stock_actual + $cantidad_pedido) > $stock_maximo) {
                        $errores_stock[] = sprintf(
                            "%s: Stock actual (%d) + cantidad a pedir (%d) = %d, supera el máximo permitido (%d)",
                            $stock_data['materia_prima_descripcion'],
                            $stock_actual,
                            $cantidad_pedido,
                            ($stock_actual + $cantidad_pedido),
                            $stock_maximo
                        );
                    }
                }
            }

            // Si hay errores de stock, rechazar el pedido
            if (!empty($errores_stock)) {
                $pdo->rollBack();
                $mensaje = "No se puede crear el pedido. Las siguientes materias primas superan el stock máximo:\n\n" . implode("\n", $errores_stock);
                header("Location: view.php?alert=4&msg=" . urlencode($mensaje));
                exit;
            }

            // Insertar los detalles del pedido
            $query_detalle = $pdo->prepare("
                INSERT INTO pedido_detalle_compra (id_pedido_compra, id_materia_prima, cantidad_pedido)
                VALUES (:pedido_id, :id_materia_prima, :cantidad)
                RETURNING id_pedido_compra, id_materia_prima
            ");

            foreach ($detalle as $item) {
            $query_detalle->execute([
                ':pedido_id'   => (int)$pedido_id,
                ':id_materia_prima' => (int)$item['codigo'],
                ':cantidad'    => (int)$item['cantidad']
            ]);

            $ret = $query_detalle->fetch(PDO::FETCH_ASSOC); // <-- devuelve ambas
            // Para bitácora podés guardar el id_materia_prima en texto:
            bitacora($pdo, $usuario_id, 'ALTA',
                    "Detalle agregado (pedido {$ret['id_pedido_compra']}, materia prima {$ret['id_materia_prima']}, cant {$item['cantidad']})",
                    (int)$ret['id_materia_prima']); // o null
            }

            // Confirmar la transacción
            $pdo->commit();

            // Redirigir con un mensaje de éxito
            header("Location: view.php?alert=1");
            
        }
        
        // Anular pedido
        elseif ($action == 'anular' && isset($_GET['ped_id'])) {

             $pedido_id = (int) $_GET['ped_id'];

            // Usuario actual
            if (!empty($_SESSION['usua_id'])) {
                $usuario_id = (int) $_SESSION['usua_id'];
            } else {
                $stmtUid = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
                $stmtUid->execute([':u' => $_SESSION['username'] ?? '']);
                $usuario_id = (int) $stmtUid->fetchColumn();
            }
            if ($usuario_id <= 0 || $pedido_id <= 0) {
                header("Location: view.php?alert=4"); // sesión o id inválido
                exit;
            }

            // Transacción
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
                header("Location: view.php?alert=4"); // no existe
                exit;
            }

            $estado = strtoupper(trim((string)$estado));

            // 2) Validar estado según especificación punto 22.3: Solo permitir anular si está en PENDIENTE
            if ($estado !== 'PENDIENTE') {
                $pdo->rollBack();
                header("Location: view.php?alert=5&msg=" . urlencode(
                    "Solo se pueden anular pedidos en estado PENDIENTE. Estado actual: {$estado}."
                ));
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

            // Si hay vínculos, rechazar anulación (punto 22.5)
            if (!empty($vinculos)) {
                $pdo->rollBack();
                $mensaje = "El Pedido #{$pedido_id} no puede anularse porque está vinculado a:\n" . implode("\n", $vinculos);
                header("Location: view.php?alert=5&msg=" . urlencode($mensaje));
                exit;
            }

            // 4) Anular el pedido (punto 22.4: "Si cumple condiciones, el sistema cambia el estado a 'Anulado'")
            $upd = $pdo->prepare("
                UPDATE pedidos_compra
                SET pedido_estado = 'ANULADO'
                WHERE id_pedido_compra = :id
            ");
            $upd->execute([':id' => $pedido_id]);

            // Bitácora
            bitacora($pdo, $usuario_id, 'INACTIVACION', "Se anula el Pedido de Compra #{$pedido_id}", $pedido_id);

            // Commit
            $pdo->commit();
            header("Location: view.php?alert=3"); // OK
            exit;
            }
        
        elseif ($action === 'update' && isset($_POST['Guardar'])) {
            // --- Identificar pedido y usuario ---
            $pedido_id  = isset($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : 0;
            $cantidades = isset($_POST['cantidad']) && is_array($_POST['cantidad']) ? $_POST['cantidad'] : [];
            $productos_eliminados = isset($_POST['productos_eliminados']) && $_POST['productos_eliminados'] !== '' 
                ? json_decode($_POST['productos_eliminados'], true) : [];
            $productos_nuevos = isset($_POST['productos_nuevos']) && $_POST['productos_nuevos'] !== '' 
                ? json_decode($_POST['productos_nuevos'], true) : [];

            // Usuario desde sesión (id) o fallback por username
            if (!empty($_SESSION['usua_id'])) {
                $usuario_id = (int) $_SESSION['usua_id'];
            } else {
                $stmtUid = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
                $stmtUid->execute([':u' => $_SESSION['username'] ?? '']);
                $usuario_id = (int) $stmtUid->fetchColumn();
            }

            if ($pedido_id <= 0 || $usuario_id <= 0) {
                header("Location: view.php?alert=4"); // operación inválida
                exit;
            }

            try {
                // Zona horaria para sellar fecha/hora en la descripción
                date_default_timezone_set('America/Asuncion');
                $stamp = date('Y-m-d H:i:s'); // ejemplo: 2025-10-26 14:32:11

                $pdo->beginTransaction();

                // Obtener observaciones del POST
                $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : null;
                $ultima_modificacion_form = isset($_POST['pedido_ultima_modificacion']) ? $_POST['pedido_ultima_modificacion'] : null;

                // 1) Verificar existencia/estado, última modificación y BLOQUEAR la cabecera
                $st = $pdo->prepare("
                    SELECT pedido_estado, pedido_ultima_modificacion
                    FROM pedidos_compra
                    WHERE id_pedido_compra = :id
                    FOR UPDATE
                ");
                $st->execute([':id' => $pedido_id]);
                $pedido_data = $st->fetch(PDO::FETCH_ASSOC);

                if ($pedido_data === false) {
                    $pdo->rollBack();
                    header("Location: view.php?alert=4"); // no encontrado
                    exit;
                }

                $estado = $pedido_data['pedido_estado'];
                $ultima_modificacion_bd = $pedido_data['pedido_ultima_modificacion'];

                // Validar concurrencia: si la última modificación en BD es diferente a la del formulario
                if ($ultima_modificacion_form && $ultima_modificacion_bd) {
                    if ($ultima_modificacion_form !== $ultima_modificacion_bd) {
                        $pdo->rollBack();
                        header("Location: view.php?alert=6&msg=" . urlencode(
                            "El pedido fue modificado por otro usuario. Por favor, recargue la página y vuelva a intentar."
                        ));
                        exit;
                    }
                }

                // Validar que el estado sea PENDIENTE
                $estadoNorm = strtoupper(trim((string)$estado));
                if ($estadoNorm !== 'PENDIENTE') {
                    $pdo->rollBack();
                    header("Location: view.php?alert=5&msg=" . urlencode(
                        "Solo se pueden editar pedidos en estado PENDIENTE. Estado actual: {$estadoNorm}"
                    ));
                    exit;
                }

                // 1.5) Actualizar observaciones en la cabecera
                $upd_observaciones = $pdo->prepare("
                    UPDATE pedidos_compra
                    SET pedido_observaciones = :observaciones,
                        pedido_ultima_modificacion = CURRENT_TIMESTAMP
                    WHERE id_pedido_compra = :id
                ");
                $upd_observaciones->execute([
                    ':observaciones' => $observaciones,
                    ':id' => $pedido_id
                ]);

                // 2) Cargar cantidades actuales para loguear cambios
                $act = $pdo->prepare("
                    SELECT id_materia_prima, cantidad_pedido
                    FROM pedido_detalle_compra
                    WHERE id_pedido_compra = :id
                ");
                $act->execute([':id' => $pedido_id]);
                $actuales = [];
                foreach ($act->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $actuales[(int)$r['id_materia_prima']] = (int)$r['cantidad_pedido'];
                }

                // 3) Preparar UPDATE por línea
                $upd = $pdo->prepare("
                    UPDATE pedido_detalle_compra
                    SET cantidad_pedido = :cantidad
                    WHERE id_pedido_compra = :pedido_id
                    AND id_materia_prima   = :id_materia_prima
                ");

                // 4) Eliminar productos marcados para eliminación
                if (!empty($productos_eliminados) && is_array($productos_eliminados)) {
                    $del = $pdo->prepare("
                        DELETE FROM pedido_detalle_compra
                        WHERE id_pedido_compra = :pedido_id
                        AND id_materia_prima = :id_materia_prima
                    ");
                    foreach ($productos_eliminados as $idEliminar) {
                        $idEliminar = (int)$idEliminar;
                        if ($idEliminar > 0) {
                            $del->execute([
                                ':pedido_id' => $pedido_id,
                                ':id_materia_prima' => $idEliminar
                            ]);
                            bitacora($pdo, $usuario_id, 'MODIFICACION', 
                                "[$stamp] Elimina producto {$idEliminar} del pedido {$pedido_id}", $pedido_id);
                        }
                    }
                }

                // 4.5) Validar stock máximo antes de agregar/modificar productos
                $query_validar_stock = $pdo->prepare("
                    SELECT 
                        mp.materia_prima_descripcion,
                        COALESCE(smp.cantidad_existente, 0) as stock_actual,
                        COALESCE(smp.stock_cantidad_maxima, 0) as stock_maximo
                    FROM materia_prima mp
                    LEFT JOIN stock_materia_prima smp ON smp.id_materia_prima = mp.id_materia_prima
                    WHERE mp.id_materia_prima = :id_materia_prima
                    LIMIT 1
                ");

                $errores_stock = [];
                
                // Validar productos nuevos
                if (!empty($productos_nuevos) && is_array($productos_nuevos)) {
                    foreach ($productos_nuevos as $idNuevo) {
                        $idNuevo = (int)$idNuevo;
                        $cantidadNueva = isset($cantidades[$idNuevo]) ? (int)$cantidades[$idNuevo] : 0;
                        
                        if ($idNuevo > 0 && $cantidadNueva > 0) {
                            $query_validar_stock->execute([':id_materia_prima' => $idNuevo]);
                            $stock_data = $query_validar_stock->fetch(PDO::FETCH_ASSOC);
                            
                            if ($stock_data) {
                                $stock_actual = (int)$stock_data['stock_actual'];
                                $stock_maximo = (int)$stock_data['stock_maximo'];
                                
                                if ($stock_maximo > 0 && ($stock_actual + $cantidadNueva) > $stock_maximo) {
                                    $errores_stock[] = sprintf(
                                        "%s: Stock actual (%d) + cantidad a pedir (%d) = %d, supera el máximo permitido (%d)",
                                        $stock_data['materia_prima_descripcion'],
                                        $stock_actual,
                                        $cantidadNueva,
                                        ($stock_actual + $cantidadNueva),
                                        $stock_maximo
                                    );
                                }
                            }
                        }
                    }
                }

                // Validar actualizaciones de cantidades existentes
                foreach ($cantidades as $idProd => $cant) {
                    $idProd = (int)$idProd;
                    $cant   = (int)$cant;

                    if ($idProd <= 0 || $cant < 1) {
                        continue;
                    }

                    // Si es producto nuevo, ya se validó arriba
                    if (in_array($idProd, $productos_nuevos)) {
                        continue;
                    }

                    // Si es producto existente que se está actualizando, validar
                    if (isset($actuales[$idProd]) && $actuales[$idProd] !== $cant) {
                        $query_validar_stock->execute([':id_materia_prima' => $idProd]);
                        $stock_data = $query_validar_stock->fetch(PDO::FETCH_ASSOC);
                        
                        if ($stock_data) {
                            $stock_actual = (int)$stock_data['stock_actual'];
                            $stock_maximo = (int)$stock_data['stock_maximo'];
                            
                            if ($stock_maximo > 0 && ($stock_actual + $cant) > $stock_maximo) {
                                $errores_stock[] = sprintf(
                                    "%s: Stock actual (%d) + cantidad a pedir (%d) = %d, supera el máximo permitido (%d)",
                                    $stock_data['materia_prima_descripcion'],
                                    $stock_actual,
                                    $cant,
                                    ($stock_actual + $cant),
                                    $stock_maximo
                                );
                            }
                        }
                    }
                }

                // Si hay errores de stock, rechazar la actualización
                if (!empty($errores_stock)) {
                    $pdo->rollBack();
                    $mensaje = "No se puede actualizar el pedido. Las siguientes materias primas superan el stock máximo:\n\n" . implode("\n", $errores_stock);
                    header("Location: view.php?alert=4&msg=" . urlencode($mensaje));
                    exit;
                }

                // 5) Agregar productos nuevos
                if (!empty($productos_nuevos) && is_array($productos_nuevos)) {
                    $ins = $pdo->prepare("
                        INSERT INTO pedido_detalle_compra (id_pedido_compra, id_materia_prima, cantidad_pedido)
                        VALUES (:pedido_id, :id_materia_prima, :cantidad)
                    ");
                    foreach ($productos_nuevos as $idNuevo) {
                        $idNuevo = (int)$idNuevo;
                        $cantidadNueva = isset($cantidades[$idNuevo]) ? (int)$cantidades[$idNuevo] : 0;
                        if ($idNuevo > 0 && $cantidadNueva > 0) {
                            $ins->execute([
                                ':pedido_id' => $pedido_id,
                                ':id_materia_prima' => $idNuevo,
                                ':cantidad' => $cantidadNueva
                            ]);
                            bitacora($pdo, $usuario_id, 'MODIFICACION', 
                                "[$stamp] Agrega producto {$idNuevo} (cant: {$cantidadNueva}) al pedido {$pedido_id}", $pedido_id);
                        }
                    }
                }

                // 6) Actualizar cantidades de productos existentes
                foreach ($cantidades as $idProd => $cant) {
                    $idProd = (int)$idProd;
                    $cant   = (int)$cant;

                    // Validación servidor: enteros positivos (>=1)
                    if ($idProd <= 0 || $cant < 1) {
                        continue;
                    }

                    // Si es producto nuevo, ya se insertó arriba, saltar
                    if (in_array($idProd, $productos_nuevos)) {
                        continue;
                    }

                    $anterior = $actuales[$idProd] ?? null;

                    // Solo actualizar si existe la línea y hay cambio
                    if ($anterior !== null && $anterior !== $cant) {
                        $upd->execute([
                            ':cantidad'    => $cant,
                            ':pedido_id'   => $pedido_id,
                            ':id_materia_prima' => $idProd
                        ]);

                        // Bitácora por línea modificada fecha/hora en descripción
                        $descripcion = "[$stamp] Modifica cantidad del producto {$idProd} en pedido {$pedido_id}: {$anterior} → {$cant}";
                        bitacora($pdo, $usuario_id, 'MODIFICACION', $descripcion, $pedido_id);
                    }
                }

                // 7) Validar que quede al menos un ítem
                $count_detalle = $pdo->prepare("SELECT COUNT(*) FROM pedido_detalle_compra WHERE id_pedido_compra = :id");
                $count_detalle->execute([':id' => $pedido_id]);
                $total_items = (int)$count_detalle->fetchColumn();
                
                if ($total_items === 0) {
                    $pdo->rollBack();
                    header("Location: view.php?alert=4&msg=" . urlencode("El pedido debe tener al menos un producto."));
                    exit;
                }

                $pdo->commit();
                header("Location: view.php?alert=2"); // Datos modificados correctamente
                exit;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                die("Error al actualizar el pedido: " . $e->getMessage());
            }
        }





    } catch (PDOException $e) {
        // Si ocurre un error, deshacer la transacción
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error en la operación con la base de datos: " . $e->getMessage());
    }
}
?>
