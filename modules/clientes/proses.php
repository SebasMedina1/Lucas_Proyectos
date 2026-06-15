<?php
session_start();
require "../../config/database.php";

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['username'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['act']) && $_GET['act'] === 'delete') {
        echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
        exit;
    }
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}

// Función bitácora
function bitacora(PDO $pdo, int $idUsuario, string $accion, string $desc, ?int $id = null): void {
    $pdo->exec("SAVEPOINT sp_bit");
    try {
        $sql = "
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:u, 'Cliente', :id, :acc, :d)
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':u' => $idUsuario, ':id' => $id, ':acc' => strtoupper($accion), ':d' => $desc]);
    } catch (Throwable $e) {
        $pdo->exec("ROLLBACK TO SAVEPOINT sp_bit");
        error_log("Bitácora falló: " . $e->getMessage());
    }
}

// Normalizar texto
function normalize_text(string $s): string {
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

    // Detectar acción
    if (!isset($_GET['act'])) {
        header("Location: view.php?alert=4");
        exit();
    }

    $action = $_GET['act'];

    // INSERT
    if ($action == 'insert' && isset($_POST['Guardar'])) {
        $tipo_cliente = isset($_POST['tipo_cliente']) ? strtoupper(trim($_POST['tipo_cliente'])) : 'PERSONA';
        $nombre = normalize_text($_POST['cliente_nombre'] ?? '');
        $apellido = isset($_POST['cliente_apellido']) ? normalize_text($_POST['cliente_apellido']) : '';
        $ruc = trim($_POST['cliente_ruc'] ?? '');
        $ci = isset($_POST['cliente_ci']) ? trim($_POST['cliente_ci']) : null;
        $telefono = trim($_POST['cliente_telefono'] ?? '');
        $email = isset($_POST['cliente_email']) ? trim(strtolower($_POST['cliente_email'])) : null;
        $direccion = isset($_POST['cliente_direccion']) ? trim($_POST['cliente_direccion']) : null;

        // Validaciones
        if (empty($nombre) || empty($ruc) || empty($telefono)) {
            header("Location: view.php?alert=4");
            exit();
        }

        // Validar email si se proporciona
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header("Location: view.php?alert=4");
            exit();
        }

        try {
            $pdo->beginTransaction();

            // Validar duplicado por RUC
            $stmtCheck = $pdo->prepare("
                SELECT 1 FROM clientes
                WHERE cliente_ruc = :ruc
                LIMIT 1
            ");
            $stmtCheck->execute([':ruc' => $ruc]);

            if ($stmtCheck->fetchColumn()) {
                $pdo->rollBack();
                header("Location: view.php?alert=5");
                exit();
            }

            // Validar duplicado por CI si se proporciona
            if ($ci) {
                $stmtCheckCI = $pdo->prepare("
                    SELECT 1 FROM clientes
                    WHERE cliente_ci = :ci AND cliente_ci IS NOT NULL AND cliente_ci != ''
                    LIMIT 1
                ");
                $stmtCheckCI->execute([':ci' => $ci]);

                if ($stmtCheckCI->fetchColumn()) {
                    $pdo->rollBack();
                    header("Location: view.php?alert=5");
                    exit();
                }
            }

            // Insertar cliente
            $query = $pdo->prepare("
                INSERT INTO clientes (
                    cliente_nombre, cliente_apellido, cliente_ruc, cliente_ci,
                    cliente_telefono, cliente_email, cliente_direccion,
                    tipo_cliente, cliente_estado, id_usuario
                )
                VALUES (
                    :nombre, :apellido, :ruc, :ci,
                    :telefono, :email, :direccion,
                    :tipo_cliente, 'ACTIVO', :id_usuario
                )
                RETURNING id_cliente
            ");
            $query->execute([
                ':nombre' => $nombre,
                ':apellido' => $apellido,
                ':ruc' => $ruc,
                ':ci' => $ci,
                ':telefono' => $telefono,
                ':email' => $email,
                ':direccion' => $direccion,
                ':tipo_cliente' => $tipo_cliente,
                ':id_usuario' => $idUsuario
            ]);
            $clienteId = (int)$query->fetchColumn();

            // Registrar en historial
            try {
                $qHist = $pdo->prepare("
                    INSERT INTO historial_clientes (cliente_id, campo_modificado, valor_anterior, valor_nuevo, accion, id_usuario)
                    VALUES (:cliente_id, 'ALTA', NULL, :nombre, 'ALTA', :id_usuario)
                ");
                $qHist->execute([
                    ':cliente_id' => $clienteId,
                    ':nombre' => $nombre . ($apellido ? ' ' . $apellido : ''),
                    ':id_usuario' => $idUsuario
                ]);
            } catch (PDOException $e) {
                error_log("Historial no disponible: " . $e->getMessage());
            }

            bitacora($pdo, $idUsuario, 'ALTA', "Cliente creado: {$nombre} {$apellido} (ID: {$clienteId})", $clienteId);

            $pdo->commit();
            header("Location: view.php?alert=1");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error insert cliente: " . $e->getMessage());
            header("Location: view.php?alert=4");
            exit();
        }
    }

    // UPDATE
    elseif ($action == 'update' && isset($_POST['Guardar'])) {
        $cliente_id = isset($_POST['id_cliente']) ? (int)$_POST['id_cliente'] : 0;
        $tipo_cliente = isset($_POST['tipo_cliente']) ? strtoupper(trim($_POST['tipo_cliente'])) : 'PERSONA';
        $nombre = normalize_text($_POST['cliente_nombre'] ?? '');
        $apellido = isset($_POST['cliente_apellido']) ? normalize_text($_POST['cliente_apellido']) : '';
        $ruc = trim($_POST['cliente_ruc'] ?? '');
        $ci = isset($_POST['cliente_ci']) ? trim($_POST['cliente_ci']) : null;
        $telefono = trim($_POST['cliente_telefono'] ?? '');
        $email = isset($_POST['cliente_email']) ? trim(strtolower($_POST['cliente_email'])) : null;
        $direccion = isset($_POST['cliente_direccion']) ? trim($_POST['cliente_direccion']) : null;
        $estado = isset($_POST['cliente_estado']) ? strtoupper(trim($_POST['cliente_estado'])) : 'ACTIVO';

        if ($cliente_id <= 0 || empty($nombre) || empty($ruc) || empty($telefono)) {
            header("Location: view.php?alert=4");
            exit();
        }

        // Validar email si se proporciona
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header("Location: view.php?alert=4");
            exit();
        }

        try {
            $pdo->beginTransaction();

            // Obtener valores anteriores para historial
            $qAnterior = $pdo->prepare("
                SELECT cliente_nombre, cliente_apellido, cliente_ruc, cliente_ci,
                       cliente_telefono, cliente_email, cliente_direccion,
                       tipo_cliente, cliente_estado
                FROM clientes WHERE id_cliente = :id
            ");
            $qAnterior->execute([':id' => $cliente_id]);
            $anterior = $qAnterior->fetch();

            if (!$anterior) {
                $pdo->rollBack();
                header("Location: view.php?alert=4");
                exit();
            }

            // Validar duplicado por RUC (excluyendo el actual)
            $stmtCheck = $pdo->prepare("
                SELECT 1 FROM clientes
                WHERE cliente_ruc = :ruc AND id_cliente <> :id
                LIMIT 1
            ");
            $stmtCheck->execute([':ruc' => $ruc, ':id' => $cliente_id]);

            if ($stmtCheck->fetchColumn()) {
                $pdo->rollBack();
                header("Location: view.php?alert=5");
                exit();
            }

            // Validar duplicado por CI si se proporciona
            if ($ci) {
                $stmtCheckCI = $pdo->prepare("
                    SELECT 1 FROM clientes
                    WHERE cliente_ci = :ci AND cliente_ci IS NOT NULL AND cliente_ci != ''
                      AND id_cliente <> :id
                    LIMIT 1
                ");
                $stmtCheckCI->execute([':ci' => $ci, ':id' => $cliente_id]);

                if ($stmtCheckCI->fetchColumn()) {
                    $pdo->rollBack();
                    header("Location: view.php?alert=5");
                    exit();
                }
            }

            // Actualizar cliente
            $query = $pdo->prepare("
                UPDATE clientes 
                SET cliente_nombre = :nombre,
                    cliente_apellido = :apellido,
                    cliente_ruc = :ruc,
                    cliente_ci = :ci,
                    cliente_telefono = :telefono,
                    cliente_email = :email,
                    cliente_direccion = :direccion,
                    tipo_cliente = :tipo_cliente,
                    cliente_estado = :estado
                WHERE id_cliente = :id
            ");
            $query->execute([
                ':nombre' => $nombre,
                ':apellido' => $apellido,
                ':ruc' => $ruc,
                ':ci' => $ci,
                ':telefono' => $telefono,
                ':email' => $email,
                ':direccion' => $direccion,
                ':tipo_cliente' => $tipo_cliente,
                ':estado' => $estado,
                ':id' => $cliente_id
            ]);

            // Registrar cambios en historial
            try {
                $campos = array();
                $campos['cliente_nombre'] = array('anterior' => ($anterior['cliente_nombre'] ?? ''), 'nuevo' => $nombre);
                $campos['cliente_apellido'] = array('anterior' => ($anterior['cliente_apellido'] ?? ''), 'nuevo' => $apellido);
                $campos['cliente_ruc'] = array('anterior' => ($anterior['cliente_ruc'] ?? ''), 'nuevo' => $ruc);
                $campos['cliente_ci'] = array('anterior' => ($anterior['cliente_ci'] ?? ''), 'nuevo' => ($ci ?? ''));
                $campos['cliente_telefono'] = array('anterior' => ($anterior['cliente_telefono'] ?? ''), 'nuevo' => $telefono);
                $campos['cliente_email'] = array('anterior' => ($anterior['cliente_email'] ?? ''), 'nuevo' => ($email ?? ''));
                $campos['cliente_direccion'] = array('anterior' => ($anterior['cliente_direccion'] ?? ''), 'nuevo' => ($direccion ?? ''));
                $campos['tipo_cliente'] = array('anterior' => ($anterior['tipo_cliente'] ?? 'PERSONA'), 'nuevo' => $tipo_cliente);
                $campos['cliente_estado'] = array('anterior' => ($anterior['cliente_estado'] ?? 'ACTIVO'), 'nuevo' => $estado);

                $qHist = $pdo->prepare("
                    INSERT INTO historial_clientes (cliente_id, campo_modificado, valor_anterior, valor_nuevo, accion, id_usuario)
                    VALUES (:cliente_id, :campo, :valor_ant, :valor_nuevo, 'MODIFICACION', :id_usuario)
                ");

                foreach ($campos as $campo => $valores) {
                    if ($valores['anterior'] != $valores['nuevo']) {
                        $qHist->execute(array(
                            ':cliente_id' => $cliente_id,
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

            bitacora($pdo, $idUsuario, 'MODIFICACION', "Cliente actualizado: {$nombre} {$apellido} (ID: {$cliente_id})", $cliente_id);

            $pdo->commit();
            header("Location: view.php?alert=2");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error update cliente: " . $e->getMessage());
            header("Location: view.php?alert=4");
            exit();
        }
    }

    // DELETE
    elseif ($action == 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (ob_get_length()) { ob_clean(); }
        
        $cliente_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($cliente_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Obtener datos del cliente
            $qCliente = $pdo->prepare("SELECT cliente_nombre, cliente_apellido FROM clientes WHERE id_cliente = :id LIMIT 1");
            $qCliente->execute([':id' => $cliente_id]);
            $cliente = $qCliente->fetch();
            
            if (!$cliente) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
                exit;
            }

            // Verificar movimientos en pedidos
            $qPedidos = $pdo->prepare("
                SELECT COUNT(*) FROM pedido_venta WHERE id_cliente = :id
            ");
            $qPedidos->execute([':id' => $cliente_id]);
            $tienePedidos = (int)$qPedidos->fetchColumn() > 0;

            // Verificar movimientos en facturas
            $qFacturas = $pdo->prepare("
                SELECT COUNT(*) FROM factura_ventas WHERE id_cliente = :id
            ");
            $qFacturas->execute([':id' => $cliente_id]);
            $tieneFacturas = (int)$qFacturas->fetchColumn() > 0;

            // Verificar movimientos en cuentas por cobrar
            $qCxC = $pdo->prepare("
                SELECT COUNT(*) FROM cuentas_cobrar cc
                JOIN factura_ventas fv ON fv.id_factura_venta = cc.id_factura_venta
                WHERE fv.id_cliente = :id
            ");
            $qCxC->execute([':id' => $cliente_id]);
            $tieneCxC = (int)$qCxC->fetchColumn() > 0;

            // Si tiene movimientos, marcar como INACTIVO
            if ($tienePedidos || $tieneFacturas || $tieneCxC) {
                $qUpdate = $pdo->prepare("UPDATE clientes SET cliente_estado = 'INACTIVO' WHERE id_cliente = :id");
                $qUpdate->execute([':id' => $cliente_id]);
                
                // Registrar en historial
                try {
                    $qHist = $pdo->prepare("
                        INSERT INTO historial_clientes (cliente_id, campo_modificado, valor_anterior, valor_nuevo, accion, id_usuario)
                        VALUES (:cliente_id, 'cliente_estado', 'ACTIVO', 'INACTIVO', 'INACTIVACION', :id_usuario)
                    ");
                    $qHist->execute([
                        ':cliente_id' => $cliente_id,
                        ':id_usuario' => $idUsuario
                    ]);
                } catch (PDOException $e) {
                    error_log("Historial no disponible: " . $e->getMessage());
                }
                
                bitacora($pdo, $idUsuario, 'MODIFICACION', "Cliente #{$cliente_id} marcado como INACTIVO (no se pudo eliminar por tener movimientos)", $cliente_id);
                
                $pdo->commit();
                echo json_encode([
                    'success' => true, 
                    'message' => 'El cliente no puede ser eliminado porque tiene pedidos, ventas o cuentas por cobrar asociadas. Se ha marcado como INACTIVO.',
                    'inactivado' => true
                ]);
                exit;
            }

            // Si no tiene movimientos, eliminar
            // Registrar en historial antes de eliminar
            try {
                $nombreCompleto = $cliente['cliente_nombre'] . ($cliente['cliente_apellido'] ? ' ' . $cliente['cliente_apellido'] : '');
                $qHist = $pdo->prepare("
                    INSERT INTO historial_clientes (cliente_id, campo_modificado, valor_anterior, valor_nuevo, accion, id_usuario)
                    VALUES (:cliente_id, 'ELIMINACION', :nombre, NULL, 'ELIMINACION', :id_usuario)
                ");
                $qHist->execute([
                    ':cliente_id' => $cliente_id,
                    ':nombre' => $nombreCompleto,
                    ':id_usuario' => $idUsuario
                ]);
            } catch (PDOException $e) {
                error_log("Historial no disponible: " . $e->getMessage());
            }

            $qDelete = $pdo->prepare("DELETE FROM clientes WHERE id_cliente = :id");
            $qDelete->execute([':id' => $cliente_id]);

            $nombreCompleto = $cliente['cliente_nombre'] . ($cliente['cliente_apellido'] ? ' ' . $cliente['cliente_apellido'] : '');
            bitacora($pdo, $idUsuario, 'ELIMINACION', "Cliente eliminado: {$nombreCompleto} (ID: {$cliente_id})", $cliente_id);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Cliente eliminado correctamente']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error delete cliente: " . $e->getMessage());
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

