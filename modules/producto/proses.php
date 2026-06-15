<?php
session_start();
require "../../config/database.php";

// NO establecer Content-Type aquí porque algunas acciones hacen redirects HTML
// Se establecerá solo cuando sea necesario para respuestas JSON

// Función bitácora
function bitacora(PDO $pdo, int $idUsuario, string $accion, string $desc, ?int $id = null): void {
    $pdo->exec("SAVEPOINT sp_bit");
    try {
        $sql = "
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:u, 'Producto', :id, :acc, :d)
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':u' => $idUsuario, ':id' => $id, ':acc' => strtoupper($accion), ':d' => $desc]);
    } catch (Throwable $e) {
        $pdo->exec("ROLLBACK TO SAVEPOINT sp_bit");
        error_log("Bitácora falló: " . $e->getMessage());
    }
}

// Normalizar descripción
function normalize_desc(string $s): string {
    $s = trim($s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return mb_strtoupper($s, 'UTF-8');
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Obtener usuario
    $qUsuario = $pdo->prepare("SELECT id_usuario, id_sucursal FROM usuarios WHERE username = :u LIMIT 1");
    $qUsuario->execute([':u' => $_SESSION['username']]);
    $usuario = $qUsuario->fetch();
    
    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }
    $idUsuario = (int)$usuario['id_usuario'];

    // Toggle estado (AJAX)
    if (isset($_GET['act']) && $_GET['act'] === 'toggle_estado' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=UTF-8');
        if (ob_get_length()) { ob_clean(); }
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $estado = isset($_POST['estado']) ? strtoupper(trim($_POST['estado'])) : '';

        if ($estado === 'ANULAR' || $estado === 'ANULADA') {
            $estado = 'INACTIVO';
        }

        if (!$id || !in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            echo json_encode(['ok' => false, 'msg' => 'Parámetros inválidos']);
            exit;
        }

        try {
            $pdo->beginTransaction();
            
            $q = $pdo->prepare("UPDATE productos SET producto_estado = :estado WHERE producto_id = :id");
            $q->execute([':estado' => $estado, ':id' => $id]);
            
            bitacora($pdo, $idUsuario, 'MODIFICACION', "Estado del producto #{$id} cambiado a {$estado}", $id);
            
            $pdo->commit();
            echo json_encode(['ok' => true, 'estado' => $estado]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'msg' => 'DB: ' . $e->getMessage()]);
        }
        exit;
    }

    // Detectar acción
    if (!isset($_GET['act'])) {
        header("Location: view.php?alert=4");
        exit();
    }

    $action = $_GET['act'];

    // INSERT
    if ($action == 'insert' && isset($_POST['Guardar'])) {
        $descripcion = normalize_desc($_POST['producto_descri'] ?? '');
        $precio = isset($_POST['producto_precio']) ? (int)str_replace(['.', ','], '', $_POST['producto_precio']) : 0;
        $id_unidad = isset($_POST['id_unidad']) ? (int)$_POST['id_unidad'] : 0;
        $iva_id = isset($_POST['iva_id']) ? (int)$_POST['iva_id'] : null;
        $id_tipo_producto = isset($_POST['id_tipo_producto']) && $_POST['id_tipo_producto'] !== '' ? (int)$_POST['id_tipo_producto'] : null;
        $deposito_predeterminado_id = isset($_POST['deposito_predeterminado_id']) && $_POST['deposito_predeterminado_id'] !== '' ? (int)$_POST['deposito_predeterminado_id'] : 0;

        // Validaciones
        if (empty($descripcion)) {
            header("Location: view.php?alert=4&msg=" . urlencode("La descripción del producto es obligatoria."));
            exit();
        }
        if ($precio < 0) {
            header("Location: view.php?alert=4&msg=" . urlencode("El precio debe ser mayor o igual a cero."));
            exit();
        }
        if ($id_unidad <= 0) {
            header("Location: view.php?alert=4&msg=" . urlencode("Debe seleccionar una unidad de medida."));
            exit();
        }

        try {
            $pdo->beginTransaction();

            // Validar duplicado
            $stmtCheck = $pdo->prepare("
                SELECT 1 FROM productos
                WHERE UPPER(TRIM(REGEXP_REPLACE(producto_descri, '\\s+', ' ', 'g'))) = :desc_norm
                LIMIT 1
            ");
            $stmtCheck->execute([':desc_norm' => $descripcion]);

            if ($stmtCheck->fetchColumn()) {
                $pdo->rollBack();
                header("Location: view.php?alert=5");
                exit();
            }

            // Insertar producto (sin deposito_predeterminado_id ya que esa columna no existe)
            $query = $pdo->prepare("
                INSERT INTO productos (producto_descri, producto_precio, producto_estado, id_unidad, iva_id, id_tipo_producto, id_usuario)
                VALUES (:descri, :precio, 'ACTIVO', :id_unidad, :iva_id, :id_tipo_producto, :id_usuario)
                RETURNING producto_id
            ");
            $query->execute([
                ':descri' => $descripcion,
                ':precio' => $precio,
                ':id_unidad' => $id_unidad,
                ':iva_id' => $iva_id,
                ':id_tipo_producto' => $id_tipo_producto,
                ':id_usuario' => $idUsuario
            ]);
            $productoId = (int)$query->fetchColumn();

            // Crear registro de stock inicial en el depósito predeterminado (si se proporcionó)
            // Usar SAVEPOINT para aislar errores y no abortar la transacción principal
            if ($deposito_predeterminado_id > 0) {
                $pdo->exec("SAVEPOINT sp_stock");
                try {
                    // Verificar primero si ya existe
                    $checkStock = $pdo->prepare("
                        SELECT 1 FROM stock_producto 
                        WHERE producto_id = :producto_id AND deposito_id = :deposito_id
                        LIMIT 1
                    ");
                    $checkStock->execute([
                        ':producto_id' => $productoId,
                        ':deposito_id' => $deposito_predeterminado_id
                    ]);
                    
                    if (!$checkStock->fetchColumn()) {
                        $queryStock = $pdo->prepare("
                            INSERT INTO stock_producto (producto_id, deposito_id, stock_prod_existente, id_usuario)
                            VALUES (:producto_id, :deposito_id, 0, :id_usuario)
                        ");
                        $queryStock->execute([
                            ':producto_id' => $productoId,
                            ':deposito_id' => $deposito_predeterminado_id,
                            ':id_usuario' => $idUsuario
                        ]);
                    }
                    $pdo->exec("RELEASE SAVEPOINT sp_stock");
                } catch (PDOException $e) {
                    $pdo->exec("ROLLBACK TO SAVEPOINT sp_stock");
                    error_log("Error al crear stock inicial: " . $e->getMessage());
                    // Continuar aunque falle, el producto ya está creado
                }
            }

            // Registrar en historial (opcional, no debe abortar la transacción)
            $pdo->exec("SAVEPOINT sp_historial");
            try {
                $qHist = $pdo->prepare("
                    INSERT INTO historial_productos (producto_id, campo_modificado, valor_anterior, valor_nuevo, accion, id_usuario)
                    VALUES (:producto_id, 'ALTA', NULL, :descri, 'ALTA', :id_usuario)
                ");
                $qHist->execute([
                    ':producto_id' => $productoId,
                    ':descri' => $descripcion,
                    ':id_usuario' => $idUsuario
                ]);
                $pdo->exec("RELEASE SAVEPOINT sp_historial");
            } catch (PDOException $e) {
                $pdo->exec("ROLLBACK TO SAVEPOINT sp_historial");
                // Si la tabla no existe, continuar sin error
                error_log("Historial no disponible: " . $e->getMessage());
            }

            bitacora($pdo, $idUsuario, 'ALTA', "Producto creado: {$descripcion} (ID: {$productoId})", $productoId);

            $pdo->commit();
            header("Location: view.php?alert=1");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errorMsg = $e->getMessage();
            error_log("Error insert producto: " . $errorMsg);
            
            // Mensaje de error más específico
            $userMessage = 'Error al crear el producto. Verifique los datos ingresados.';
            
            // Detectar errores comunes de PostgreSQL
            if (stripos($errorMsg, 'column') !== false && stripos($errorMsg, 'does not exist') !== false) {
                $userMessage = 'Error: Una columna no existe. Verifique la estructura de la tabla productos.';
            } elseif (stripos($errorMsg, 'null value') !== false && stripos($errorMsg, 'violates not-null constraint') !== false) {
                $userMessage = 'Error: Faltan datos obligatorios. Complete todos los campos requeridos.';
            } elseif (stripos($errorMsg, 'duplicate key') !== false || stripos($errorMsg, 'unique') !== false) {
                $userMessage = 'Error: Ya existe un producto con esa descripción.';
            } elseif (stripos($errorMsg, 'foreign key') !== false) {
                if (stripos($errorMsg, 'id_unidad') !== false) {
                    $userMessage = 'Error: La unidad de medida seleccionada no es válida.';
                } elseif (stripos($errorMsg, 'iva_id') !== false) {
                    $userMessage = 'Error: El tipo de IVA seleccionado no es válido.';
                } elseif (stripos($errorMsg, 'id_tipo_producto') !== false) {
                    $userMessage = 'Error: El tipo de producto seleccionado no es válido.';
                } else {
                    $userMessage = 'Error de relación de datos. Verifique las selecciones.';
                }
            }
            
            // Detectar errores de transacción abortada
            if (stripos($errorMsg, 'transaction is aborted') !== false || stripos($errorMsg, '25P02') !== false) {
                $userMessage = 'Error: Ocurrió un problema durante el proceso. Por favor, verifique que todos los datos sean correctos e intente nuevamente.';
            }
            
            header("Location: view.php?alert=4&msg=" . urlencode($userMessage));
            exit();
        }
    }

    // UPDATE
    elseif ($action == 'update' && isset($_POST['Guardar'])) {
        $producto_id = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : 0;
        $descripcion = normalize_desc($_POST['producto_descri'] ?? '');
        $precio = isset($_POST['producto_precio']) ? (int)str_replace(['.', ','], '', $_POST['producto_precio']) : 0;
        $id_unidad = isset($_POST['id_unidad']) ? (int)$_POST['id_unidad'] : 0;
        $iva_id = isset($_POST['iva_id']) ? (int)$_POST['iva_id'] : null;
        $id_tipo_producto = isset($_POST['id_tipo_producto']) && $_POST['id_tipo_producto'] !== '' ? (int)$_POST['id_tipo_producto'] : null;
        $deposito_predeterminado_id = isset($_POST['deposito_predeterminado_id']) && $_POST['deposito_predeterminado_id'] !== '' ? (int)$_POST['deposito_predeterminado_id'] : 0;
        $estado = isset($_POST['producto_estado']) ? strtoupper(trim($_POST['producto_estado'])) : 'ACTIVO';

        if ($producto_id <= 0) {
            header("Location: view.php?alert=4&msg=" . urlencode("ID de producto inválido."));
            exit();
        }
        if (empty($descripcion)) {
            header("Location: view.php?alert=4&msg=" . urlencode("La descripción del producto es obligatoria."));
            exit();
        }
        if ($precio < 0) {
            header("Location: view.php?alert=4&msg=" . urlencode("El precio debe ser mayor o igual a cero."));
            exit();
        }
        if ($id_unidad <= 0) {
            header("Location: view.php?alert=4&msg=" . urlencode("Debe seleccionar una unidad de medida."));
            exit();
        }

        try {
            $pdo->beginTransaction();

            // Validar duplicado (excluyendo el actual)
            $stmtCheck = $pdo->prepare("
                SELECT 1 FROM productos
                WHERE UPPER(TRIM(REGEXP_REPLACE(producto_descri, '\\s+', ' ', 'g'))) = :desc_norm
                  AND producto_id <> :id
                LIMIT 1
            ");
            $stmtCheck->execute([':desc_norm' => $descripcion, ':id' => $producto_id]);

            if ($stmtCheck->fetchColumn()) {
                $pdo->rollBack();
                header("Location: view.php?alert=5");
                exit();
            }

            // Obtener valores anteriores para historial
            $qAnterior = $pdo->prepare("
                SELECT producto_descri, producto_precio, producto_estado, id_unidad, iva_id, id_tipo_producto
                FROM productos WHERE producto_id = :id
            ");
            $qAnterior->execute([':id' => $producto_id]);
            $anterior = $qAnterior->fetch();

            // Actualizar producto (sin deposito_predeterminado_id ya que esa columna no existe)
            $query = $pdo->prepare("
                UPDATE productos 
                SET producto_descri = :descri, 
                    producto_precio = :precio, 
                    producto_estado = :estado,
                    id_unidad = :id_unidad, 
                    iva_id = :iva_id,
                    id_tipo_producto = :id_tipo_producto
                WHERE producto_id = :id
            ");
            $query->execute([
                ':descri' => $descripcion,
                ':precio' => $precio,
                ':estado' => $estado,
                ':id_unidad' => $id_unidad,
                ':iva_id' => $iva_id,
                ':id_tipo_producto' => $id_tipo_producto,
                ':id' => $producto_id
            ]);

            // Registrar cambios en historial
            try {
                $campos = array();
                $campos['producto_descri'] = array('anterior' => ($anterior['producto_descri'] ?? ''), 'nuevo' => $descripcion);
                $campos['producto_precio'] = array('anterior' => ($anterior['producto_precio'] ?? 0), 'nuevo' => $precio);
                $campos['producto_estado'] = array('anterior' => ($anterior['producto_estado'] ?? ''), 'nuevo' => $estado);
                $campos['id_unidad'] = array('anterior' => ($anterior['id_unidad'] ?? 0), 'nuevo' => $id_unidad);
                $campos['iva_id'] = array('anterior' => ($anterior['iva_id'] ?? null), 'nuevo' => $iva_id);
                $campos['id_tipo_producto'] = array('anterior' => ($anterior['id_tipo_producto'] ?? null), 'nuevo' => $id_tipo_producto);
                // deposito_predeterminado_id no existe en la tabla productos, se maneja a través de stock_producto

                $qHist = $pdo->prepare("
                    INSERT INTO historial_productos (producto_id, campo_modificado, valor_anterior, valor_nuevo, accion, id_usuario)
                    VALUES (:producto_id, :campo, :valor_ant, :valor_nuevo, 'MODIFICACION', :id_usuario)
                ");

                foreach ($campos as $campo => $valores) {
                    if ($valores['anterior'] != $valores['nuevo']) {
                        $qHist->execute(array(
                            ':producto_id' => $producto_id,
                            ':campo' => $campo,
                            ':valor_ant' => (string)$valores['anterior'],
                            ':valor_nuevo' => (string)$valores['nuevo'],
                            ':id_usuario' => $idUsuario
                        ));
                    }
                }
            } catch (PDOException $e) {
                error_log("Historial no disponible: " . $e->getMessage());
            }

            // Verificar si existe stock en el depósito predeterminado, si no, crearlo (solo si se proporcionó depósito)
            if ($deposito_predeterminado_id > 0) {
                try {
                    $qStock = $pdo->prepare("
                        SELECT 1 FROM stock_producto 
                        WHERE producto_id = :producto_id AND deposito_id = :deposito_id
                        LIMIT 1
                    ");
                    $qStock->execute([':producto_id' => $producto_id, ':deposito_id' => $deposito_predeterminado_id]);
                    if (!$qStock->fetchColumn()) {
                        $qInsertStock = $pdo->prepare("
                            INSERT INTO stock_producto (producto_id, deposito_id, stock_prod_existente, id_usuario)
                            VALUES (:producto_id, :deposito_id, 0, :id_usuario)
                            ON CONFLICT DO NOTHING
                        ");
                        $qInsertStock->execute([
                            ':producto_id' => $producto_id,
                            ':deposito_id' => $deposito_predeterminado_id,
                            ':id_usuario' => $idUsuario
                        ]);
                    }
                } catch (PDOException $e) {
                    error_log("Error al actualizar stock: " . $e->getMessage());
                    // Continuar aunque falle
                }
            }

            bitacora($pdo, $idUsuario, 'MODIFICACION', "Producto actualizado: {$descripcion} (ID: {$producto_id})", $producto_id);

            $pdo->commit();
            header("Location: view.php?alert=2");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errorMsg = $e->getMessage();
            error_log("Error update producto: " . $errorMsg);
            
            // Mensaje de error más específico
            $userMessage = 'Error al actualizar el producto. Verifique los datos ingresados.';
            
            // Detectar errores comunes de PostgreSQL
            if (stripos($errorMsg, 'column') !== false && stripos($errorMsg, 'does not exist') !== false) {
                $userMessage = 'Error: Una columna no existe. Verifique la estructura de la tabla productos.';
            } elseif (stripos($errorMsg, 'null value') !== false && stripos($errorMsg, 'violates not-null constraint') !== false) {
                $userMessage = 'Error: Faltan datos obligatorios. Complete todos los campos requeridos.';
            } elseif (stripos($errorMsg, 'duplicate key') !== false || stripos($errorMsg, 'unique') !== false) {
                $userMessage = 'Error: Ya existe un producto con esa descripción.';
            } elseif (stripos($errorMsg, 'foreign key') !== false) {
                if (stripos($errorMsg, 'id_unidad') !== false) {
                    $userMessage = 'Error: La unidad de medida seleccionada no es válida.';
                } elseif (stripos($errorMsg, 'iva_id') !== false) {
                    $userMessage = 'Error: El tipo de IVA seleccionado no es válido.';
                } elseif (stripos($errorMsg, 'id_tipo_producto') !== false) {
                    $userMessage = 'Error: El tipo de producto seleccionado no es válido.';
                } else {
                    $userMessage = 'Error de relación de datos. Verifique las selecciones.';
                }
            }
            
            // Detectar errores de transacción abortada
            if (stripos($errorMsg, 'transaction is aborted') !== false || stripos($errorMsg, '25P02') !== false) {
                $userMessage = 'Error: Ocurrió un problema durante el proceso. Por favor, verifique que todos los datos sean correctos e intente nuevamente.';
            }
            
            header("Location: view.php?alert=4&msg=" . urlencode($userMessage));
            exit();
        }
    }

    // DELETE
    elseif ($action == 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=UTF-8');
        if (ob_get_length()) { ob_clean(); }
        
        $producto_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($producto_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Obtener datos del producto
            $qProd = $pdo->prepare("SELECT producto_descri FROM productos WHERE producto_id = :id LIMIT 1");
            $qProd->execute([':id' => $producto_id]);
            $prod = $qProd->fetch();
            
            if (!$prod) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
                exit;
            }

            // SIEMPRE inactivar, nunca eliminar físicamente
            // Obtener el estado actual antes de cambiarlo
            $qEstadoActual = $pdo->prepare("SELECT producto_estado FROM productos WHERE producto_id = :id LIMIT 1");
            $qEstadoActual->execute([':id' => $producto_id]);
            $estadoActual = $qEstadoActual->fetchColumn();
            $estadoActual = strtoupper(trim($estadoActual ?? 'ACTIVO'));

            // Solo cambiar a INACTIVO si está ACTIVO
            if ($estadoActual === 'ACTIVO') {
                $qUpdate = $pdo->prepare("UPDATE productos SET producto_estado = 'INACTIVO' WHERE producto_id = :id");
                $qUpdate->execute([':id' => $producto_id]);
                
                // Registrar en historial
                $pdo->exec("SAVEPOINT sp_historial_inactivar");
                try {
                    $qHist = $pdo->prepare("
                        INSERT INTO historial_productos (producto_id, campo_modificado, valor_anterior, valor_nuevo, accion, id_usuario)
                        VALUES (:producto_id, 'producto_estado', 'ACTIVO', 'INACTIVO', 'INACTIVACION', :id_usuario)
                    ");
                    $qHist->execute([
                        ':producto_id' => $producto_id,
                        ':id_usuario' => $idUsuario
                    ]);
                    $pdo->exec("RELEASE SAVEPOINT sp_historial_inactivar");
                } catch (PDOException $e) {
                    $pdo->exec("ROLLBACK TO SAVEPOINT sp_historial_inactivar");
                    error_log("Historial no disponible: " . $e->getMessage());
                }
                
                bitacora($pdo, $idUsuario, 'MODIFICACION', "Producto #{$producto_id} ({$prod['producto_descri']}) marcado como INACTIVO", $producto_id);
                
                $pdo->commit();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Producto inactivado correctamente.',
                    'inactivado' => true
                ]);
            } else {
                // Ya está inactivo
                $pdo->commit();
                echo json_encode([
                    'success' => true, 
                    'message' => 'El producto ya está inactivo.',
                    'inactivado' => false
                ]);
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error delete producto: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    else {
        header("Location: view.php?alert=4");
        exit();
    }

} catch (PDOException $e) {
    error_log("Error conexión: " . $e->getMessage());
    header("Location: view.php?alert=4");
    exit();
}
