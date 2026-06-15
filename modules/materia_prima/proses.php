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
            VALUES (:u, 'MateriaPrima', :id, :acc, :d)
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
    if (empty($_SESSION['username'])) {
        header("Location: view.php?alert=4&msg=" . urlencode("Sesión inválida"));
        exit();
    }

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
            
            $q = $pdo->prepare("UPDATE materia_prima SET materia_prima_estado = :estado WHERE id_materia_prima = :id");
            $q->execute([':estado' => $estado, ':id' => $id]);
            
            bitacora($pdo, $idUsuario, 'MODIFICACION', "Estado de la materia prima #{$id} cambiado a {$estado}", $id);
            
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
        $descripcion = normalize_desc($_POST['materia_prima_descripcion'] ?? '');
        $id_unidad = isset($_POST['id_unidad']) ? (int)$_POST['id_unidad'] : 0;
        $iva_id = isset($_POST['iva_id']) ? (int)$_POST['iva_id'] : null;
        $deposito_predeterminado_id = isset($_POST['deposito_predeterminado_id']) && $_POST['deposito_predeterminado_id'] !== '' ? (int)$_POST['deposito_predeterminado_id'] : 0;

        // Validaciones
        if (empty($descripcion)) {
            header("Location: view.php?alert=4&msg=" . urlencode("La descripción es obligatoria."));
            exit();
        }
        if ($id_unidad <= 0) {
            header("Location: view.php?alert=4&msg=" . urlencode("Debe seleccionar una unidad de medida."));
            exit();
        }
        if ($iva_id === null) {
            header("Location: view.php?alert=4&msg=" . urlencode("Debe seleccionar un tipo de IVA."));
            exit();
        }

        try {
            $pdo->beginTransaction();

            // Insertar materia prima (solo con las columnas que existen)
            $query = $pdo->prepare("
                INSERT INTO materia_prima (materia_prima_descripcion, materia_prima_estado, id_unidad, iva_id, id_usuario)
                VALUES (:descri, 'ACTIVO', :id_unidad, :iva_id, :id_usuario)
                RETURNING id_materia_prima
            ");
            $query->execute([
                ':descri' => $descripcion,
                ':id_unidad' => $id_unidad,
                ':iva_id' => $iva_id,
                ':id_usuario' => $idUsuario
            ]);
            $materiaPrimaId = (int)$query->fetchColumn();

            // Crear registro de stock inicial en el depósito predeterminado (si se proporcionó)
            // Usar SAVEPOINT para aislar errores y no abortar la transacción principal
            if ($deposito_predeterminado_id > 0) {
                $pdo->exec("SAVEPOINT sp_stock");
                try {
                    // Verificar primero si ya existe
                    $checkStock = $pdo->prepare("
                        SELECT 1 FROM stock_materia_prima 
                        WHERE id_materia_prima = :materia_prima_id AND deposito_id = :deposito_id
                        LIMIT 1
                    ");
                    $checkStock->execute([
                        ':materia_prima_id' => $materiaPrimaId,
                        ':deposito_id' => $deposito_predeterminado_id
                    ]);
                    
                    if (!$checkStock->fetchColumn()) {
                        $queryStock = $pdo->prepare("
                            INSERT INTO stock_materia_prima (id_materia_prima, deposito_id, cantidad_existente, stock_cantidad_minima, stock_cantidad_maxima, id_usuario)
                            VALUES (:materia_prima_id, :deposito_id, 0, 0, 0, :id_usuario)
                        ");
                        $queryStock->execute([
                            ':materia_prima_id' => $materiaPrimaId,
                            ':deposito_id' => $deposito_predeterminado_id,
                            ':id_usuario' => $idUsuario
                        ]);
                    }
                    $pdo->exec("RELEASE SAVEPOINT sp_stock");
                } catch (PDOException $e) {
                    $pdo->exec("ROLLBACK TO SAVEPOINT sp_stock");
                    error_log("Error al crear stock inicial: " . $e->getMessage());
                    // Continuar aunque falle, la materia prima ya está creada
                }
            }

            bitacora($pdo, $idUsuario, 'ALTA', "Materia prima creada: {$descripcion} (ID: {$materiaPrimaId})", $materiaPrimaId);

            $pdo->commit();
            header("Location: view.php?alert=1");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errorMsg = $e->getMessage();
            error_log("Error insert materia_prima: " . $errorMsg);
            
            // Mensaje de error más específico
            $userMessage = 'Error al crear la materia prima. Verifique los datos ingresados.';
            
            // Detectar errores comunes de PostgreSQL
            if (stripos($errorMsg, 'column') !== false && stripos($errorMsg, 'does not exist') !== false) {
                $userMessage = 'Error: Una columna no existe. Verifique la estructura de la tabla materia_prima.';
            } elseif (stripos($errorMsg, 'null value') !== false && stripos($errorMsg, 'violates not-null constraint') !== false) {
                $userMessage = 'Error: Faltan datos obligatorios. Complete todos los campos requeridos.';
            } elseif (stripos($errorMsg, 'foreign key') !== false) {
                if (stripos($errorMsg, 'id_unidad') !== false) {
                    $userMessage = 'Error: La unidad de medida seleccionada no es válida.';
                } elseif (stripos($errorMsg, 'iva_id') !== false) {
                    $userMessage = 'Error: El tipo de IVA seleccionado no es válido.';
                } else {
                    $userMessage = 'Error de relación de datos. Verifique las selecciones.';
                }
            }
            
            // Detectar errores de transacción abortada
            if (stripos($errorMsg, 'transaction is aborted') !== false || stripos($errorMsg, '25P02') !== false) {
                $userMessage = 'Error: Ocurrió un problema durante el proceso. Por favor, verifique que todos los datos sean correctos e intente nuevamente.';
            }
            
            // Mostrar el error real para debugging (temporalmente)
            // TODO: Remover después de identificar el problema
            if (empty($userMessage) || $userMessage === 'Error al crear la materia prima. Verifique los datos ingresados.') {
                $userMessage = 'Error: ' . htmlspecialchars($errorMsg);
            }
            
            header("Location: view.php?alert=4&msg=" . urlencode($userMessage));
            exit();
        }
    }

    // UPDATE
    elseif ($action == 'update' && isset($_POST['Guardar'])) {
        $materia_prima_id = isset($_POST['id_materia_prima']) ? (int)$_POST['id_materia_prima'] : 0;
        $descripcion = normalize_desc($_POST['materia_prima_descripcion'] ?? '');
        $id_unidad = isset($_POST['id_unidad']) ? (int)$_POST['id_unidad'] : 0;
        $iva_id = isset($_POST['iva_id']) ? (int)$_POST['iva_id'] : null;
        $deposito_predeterminado_id = isset($_POST['deposito_predeterminado_id']) && $_POST['deposito_predeterminado_id'] !== '' ? (int)$_POST['deposito_predeterminado_id'] : 0;
        $estado = isset($_POST['materia_prima_estado']) ? strtoupper(trim($_POST['materia_prima_estado'])) : 'ACTIVO';

        if ($materia_prima_id <= 0) {
            header("Location: view.php?alert=4&msg=" . urlencode("ID de materia prima inválido."));
            exit();
        }
        if (empty($descripcion)) {
            header("Location: view.php?alert=4&msg=" . urlencode("La descripción es obligatoria."));
            exit();
        }
        if ($id_unidad <= 0) {
            header("Location: view.php?alert=4&msg=" . urlencode("Debe seleccionar una unidad de medida."));
            exit();
        }
        if ($iva_id === null) {
            header("Location: view.php?alert=4&msg=" . urlencode("Debe seleccionar un tipo de IVA."));
            exit();
        }

        try {
            $pdo->beginTransaction();

            // Actualizar materia prima (solo con las columnas que existen)
            $query = $pdo->prepare("
                UPDATE materia_prima 
                SET materia_prima_descripcion = :descri, 
                    materia_prima_estado = :estado,
                    id_unidad = :id_unidad, 
                    iva_id = :iva_id
                WHERE id_materia_prima = :id
            ");
            $query->execute([
                ':descri' => $descripcion,
                ':estado' => $estado,
                ':id_unidad' => $id_unidad,
                ':iva_id' => $iva_id,
                ':id' => $materia_prima_id
            ]);

            // Verificar si existe stock en el depósito predeterminado, si no, crearlo (solo si se proporcionó depósito)
            if ($deposito_predeterminado_id > 0) {
                $pdo->exec("SAVEPOINT sp_stock");
                try {
                    $qStock = $pdo->prepare("
                        SELECT 1 FROM stock_materia_prima 
                        WHERE id_materia_prima = :materia_prima_id AND deposito_id = :deposito_id
                        LIMIT 1
                    ");
                    $qStock->execute([':materia_prima_id' => $materia_prima_id, ':deposito_id' => $deposito_predeterminado_id]);
                    if (!$qStock->fetchColumn()) {
                        $qInsertStock = $pdo->prepare("
                            INSERT INTO stock_materia_prima (id_materia_prima, deposito_id, cantidad_existente, stock_cantidad_minima, stock_cantidad_maxima, id_usuario)
                            VALUES (:materia_prima_id, :deposito_id, 0, 0, 0, :id_usuario)
                            ON CONFLICT DO NOTHING
                        ");
                        $qInsertStock->execute([
                            ':materia_prima_id' => $materia_prima_id,
                            ':deposito_id' => $deposito_predeterminado_id,
                            ':id_usuario' => $idUsuario
                        ]);
                    }
                    $pdo->exec("RELEASE SAVEPOINT sp_stock");
                } catch (PDOException $e) {
                    $pdo->exec("ROLLBACK TO SAVEPOINT sp_stock");
                    error_log("Error al actualizar stock: " . $e->getMessage());
                    // Continuar aunque falle
                }
            }

            bitacora($pdo, $idUsuario, 'MODIFICACION', "Materia prima actualizada: {$descripcion} (ID: {$materia_prima_id})", $materia_prima_id);

            $pdo->commit();
            header("Location: view.php?alert=2");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errorMsg = $e->getMessage();
            error_log("Error update materia_prima: " . $errorMsg);
            
            // Mensaje de error más específico
            $userMessage = 'Error al actualizar la materia prima. Verifique los datos ingresados.';
            
            // Detectar errores comunes de PostgreSQL
            if (stripos($errorMsg, 'column') !== false && stripos($errorMsg, 'does not exist') !== false) {
                $userMessage = 'Error: Una columna no existe. Verifique la estructura de la tabla materia_prima.';
            } elseif (stripos($errorMsg, 'null value') !== false && stripos($errorMsg, 'violates not-null constraint') !== false) {
                $userMessage = 'Error: Faltan datos obligatorios. Complete todos los campos requeridos.';
            } elseif (stripos($errorMsg, 'foreign key') !== false) {
                if (stripos($errorMsg, 'id_unidad') !== false) {
                    $userMessage = 'Error: La unidad de medida seleccionada no es válida.';
                } elseif (stripos($errorMsg, 'iva_id') !== false) {
                    $userMessage = 'Error: El tipo de IVA seleccionado no es válido.';
                } else {
                    $userMessage = 'Error de relación de datos. Verifique las selecciones.';
                }
            }
            
            // Detectar errores de transacción abortada
            if (stripos($errorMsg, 'transaction is aborted') !== false || stripos($errorMsg, '25P02') !== false) {
                $userMessage = 'Error: Ocurrió un problema durante el proceso. Por favor, verifique que todos los datos sean correctos e intente nuevamente.';
            }
            
            // Mostrar el error real para debugging (temporalmente)
            // TODO: Remover después de identificar el problema
            if (empty($userMessage) || $userMessage === 'Error al actualizar la materia prima. Verifique los datos ingresados.') {
                $userMessage = 'Error: ' . htmlspecialchars($errorMsg);
            }
            
            header("Location: view.php?alert=4&msg=" . urlencode($userMessage));
            exit();
        }
    }

    // DELETE (solo inactivar, nunca eliminar físicamente)
    elseif ($action == 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=UTF-8');
        if (ob_get_length()) { ob_clean(); }
        
        $materia_prima_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($materia_prima_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Obtener datos de la materia prima
            $qMp = $pdo->prepare("SELECT materia_prima_descripcion FROM materia_prima WHERE id_materia_prima = :id LIMIT 1");
            $qMp->execute([':id' => $materia_prima_id]);
            $mp = $qMp->fetch();
            
            if (!$mp) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Materia prima no encontrada']);
                exit;
            }

            // SIEMPRE inactivar, nunca eliminar físicamente
            // Obtener el estado actual antes de cambiarlo
            $qEstadoActual = $pdo->prepare("SELECT materia_prima_estado FROM materia_prima WHERE id_materia_prima = :id LIMIT 1");
            $qEstadoActual->execute([':id' => $materia_prima_id]);
            $estadoActual = $qEstadoActual->fetchColumn();
            $estadoActual = strtoupper(trim($estadoActual ?? 'ACTIVO'));

            // Solo cambiar a INACTIVO si está ACTIVO
            if ($estadoActual === 'ACTIVO') {
                $qUpdate = $pdo->prepare("UPDATE materia_prima SET materia_prima_estado = 'INACTIVO' WHERE id_materia_prima = :id");
                $qUpdate->execute([':id' => $materia_prima_id]);
                
                bitacora($pdo, $idUsuario, 'MODIFICACION', "Materia prima #{$materia_prima_id} ({$mp['materia_prima_descripcion']}) marcada como INACTIVA", $materia_prima_id);
                
                $pdo->commit();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Materia prima inactivada correctamente.',
                    'inactivado' => true
                ]);
            } else {
                // Ya está inactiva
                $pdo->commit();
                echo json_encode([
                    'success' => true, 
                    'message' => 'La materia prima ya está inactiva.',
                    'inactivado' => false
                ]);
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error delete materia_prima: " . $e->getMessage());
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
} catch (Exception $e) {
    error_log("Error general: " . $e->getMessage());
    header("Location: view.php?alert=4&msg=" . urlencode($e->getMessage()));
    exit();
}

