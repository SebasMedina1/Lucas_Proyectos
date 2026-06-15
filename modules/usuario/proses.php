<?php
session_start();

if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}

$file = realpath("../../config/database.php");
if (!$file || !file_exists($file)) {
    die("Error: No se pudo encontrar el archivo en la ruta $file");
}

require_once $file;
require_once realpath(__DIR__ . '/../../config/modulo_cargo_map.php');

function redirectWithAlert(int $code, string $message = ''): void {
    $location = "view.php?alert={$code}";
    if ($message !== '') {
        $location .= '&msg=' . urlencode($message);
    }
    header("Location: {$location}");
    exit();
}

$action = $_GET['act'] ?? '';

function toUpper(?string $value): string {
    return strtoupper(trim($value ?? ''));
}

function isValidPassword(string $password): bool {
    $len = strlen($password);
    return $len >= 8 && $len <= 15 && preg_match('/\d/', $password);
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    error_log("Usuarios::conexion -> " . $e->getMessage());
    redirectWithAlert(4);
}

try {
    if ($action === 'insert') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $moduloId = (int)($_POST['modulo_id'] ?? 0);
        $sucursalId = (int)($_POST['id_sucursal'] ?? 0);
        $cargoId  = (int)($_POST['id_cargo'] ?? 0);
        $personalIdSeleccionado = !empty($_POST['id_personal']) ? (int)$_POST['id_personal'] : 0;

        // Validaciones básicas
        if ($username === '' || $password === '' || $moduloId <= 0 || $sucursalId <= 0 || $cargoId <= 0) {
            redirectWithAlert(4, 'Complete todos los campos obligatorios.');
        }
        
        if (strlen($username) > 30) {
            redirectWithAlert(4, 'El usuario excede el máximo permitido (30).');
        }

        // Validar que el username no exista
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE LOWER(username) = LOWER(:username)");
        $stmt->execute([':username' => $username]);
        if ($stmt->fetchColumn() > 0) {
            redirectWithAlert(4, 'El nombre de usuario ya existe.');
        }

        // Validar que el personal seleccionado no esté ya asociado a otro usuario
        if ($personalIdSeleccionado > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id_personal = :id_personal");
            $stmt->execute([':id_personal' => $personalIdSeleccionado]);
            if ($stmt->fetchColumn() > 0) {
                redirectWithAlert(4, 'El personal seleccionado ya está asociado a otro usuario.');
            }
        }

        if (!isValidPassword($password)) {
            redirectWithAlert(4, 'La contraseña debe tener entre 8 y 15 caracteres e incluir al menos un número.');
        }

        // Validar que el cargo esté permitido para el módulo seleccionado
        if (!validarCargoParaModulo($pdo, $moduloId, $cargoId)) {
            redirectWithAlert(4, 'El cargo seleccionado no está permitido para el módulo elegido.');
        }

        $hash = md5($password);
        
        // Validar que el personal seleccionado existe y está activo (si se seleccionó uno)
        $personalId = null;
        if ($personalIdSeleccionado > 0) {
            $stmtPersonal = $pdo->prepare("
                SELECT id_personal 
                FROM personal 
                WHERE id_personal = :id 
                AND personal_estado = 'ACTIVO'
                LIMIT 1
            ");
            $stmtPersonal->execute([':id' => $personalIdSeleccionado]);
            $personalData = $stmtPersonal->fetch();
            
            if ($personalData) {
                $personalId = $personalIdSeleccionado;
            } else {
                redirectWithAlert(4, 'El personal seleccionado no existe o no está activo.');
            }
        }

        // Verificar si la columna id_personal existe antes de usarla
        $columnExists = false;
        try {
            $stmtCheck = $pdo->query("
                SELECT column_name 
                FROM information_schema.columns 
                WHERE table_schema = 'public' 
                AND table_name = 'usuarios' 
                AND column_name = 'id_personal'
            ");
            $columnExists = $stmtCheck->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error al verificar columna id_personal: " . $e->getMessage());
        }
        
        // Insertar usuario solo con las columnas que existen en la tabla
        if ($columnExists) {
            $insert = $pdo->prepare("
                INSERT INTO usuarios (
                    username, 
                    usua_password,
                    estado_usuario, 
                    id_sucursal, 
                    modulo_id, 
                    id_cargo,
                    id_personal
                ) VALUES (
                    :username, 
                    :password,
                    'ACTIVO', 
                    :sucursal, 
                    :modulo, 
                    :cargo,
                    :personal_id
                )
            ");
            $insert->execute([
                ':username'  => $username,
                ':password'  => $hash,
                ':sucursal'  => $sucursalId,
                ':modulo'    => $moduloId,
                ':cargo'     => $cargoId,
                ':personal_id' => $personalId ?: null,
            ]);
        } else {
            // Si no existe la columna id_personal, insertar sin ella
            $insert = $pdo->prepare("
                INSERT INTO usuarios (
                    username, 
                    usua_password,
                    estado_usuario, 
                    id_sucursal, 
                    modulo_id, 
                    id_cargo
                ) VALUES (
                    :username, 
                    :password,
                    'ACTIVO', 
                    :sucursal, 
                    :modulo, 
                    :cargo
                )
            ");
            $insert->execute([
                ':username'  => $username,
                ':password'  => $hash,
                ':sucursal'  => $sucursalId,
                ':modulo'    => $moduloId,
                ':cargo'     => $cargoId,
            ]);
        }

        redirectWithAlert(1);
    } elseif ($action === 'update') {
        $id        = (int)($_POST['id_usuario'] ?? 0);
        $username  = trim($_POST['username'] ?? '');
        $password  = trim($_POST['password'] ?? '');
        $moduloId  = (int)($_POST['modulo_id'] ?? 0);
        $sucursalId= (int)($_POST['id_sucursal'] ?? 0);
        $cargoId   = (int)($_POST['id_cargo'] ?? 0);
        $personalIdSeleccionado = !empty($_POST['id_personal']) ? (int)$_POST['id_personal'] : 0;

        // Validaciones básicas
        if ($id <= 0 || $username === '' || $moduloId <= 0 || $sucursalId <= 0 || $cargoId <= 0) {
            redirectWithAlert(4, 'Complete todos los campos obligatorios.');
        }
        
        if (strlen($username) > 30) {
            redirectWithAlert(4, 'El usuario excede el máximo permitido (30).');
        }

        // Validar que el username no exista en otro usuario
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE LOWER(username) = LOWER(:username) AND id_usuario <> :id");
        $stmt->execute([
            ':username' => $username,
            ':id'       => $id,
        ]);
        if ($stmt->fetchColumn() > 0) {
            redirectWithAlert(4, 'El nombre de usuario ya existe.');
        }

        // Validar que el personal seleccionado no esté ya asociado a otro usuario
        if ($personalIdSeleccionado > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id_personal = :id_personal AND id_usuario <> :id");
            $stmt->execute([
                ':id_personal' => $personalIdSeleccionado,
                ':id' => $id
            ]);
            if ($stmt->fetchColumn() > 0) {
                redirectWithAlert(4, 'El personal seleccionado ya está asociado a otro usuario.');
            }
        }

        // Manejar personal en actualización
        // Primero obtener el id_personal actual del usuario para mantenerlo si no se selecciona otro
        $stmtUser = $pdo->prepare("SELECT id_personal FROM usuarios WHERE id_usuario = :id LIMIT 1");
        $stmtUser->execute([':id' => $id]);
        $userData = $stmtUser->fetch();
        $personalIdActual = !empty($userData['id_personal']) ? (int)$userData['id_personal'] : null;
        
        $personalIdFinal = $personalIdActual; // Por defecto, mantener el actual
        
        if ($personalIdSeleccionado > 0) {
            // Se seleccionó un personal existente
            // Validar que existe y está activo
            $stmtPersonal = $pdo->prepare("
                SELECT id_personal 
                FROM personal 
                WHERE id_personal = :id 
                AND personal_estado = 'ACTIVO'
                LIMIT 1
            ");
            $stmtPersonal->execute([':id' => $personalIdSeleccionado]);
            $personalData = $stmtPersonal->fetch();
            
            if ($personalData) {
                $personalIdFinal = $personalIdSeleccionado;
            } else {
                redirectWithAlert(4, 'El personal seleccionado no existe o no está activo.');
            }
        } else {
            // Si se envió el campo pero está vacío (0), se quita la asociación
            // Si el campo tiene valor "-- Sin personal asociado --" (vacío), también se quita
            $personalIdFinal = null;
        }

        // Verificar si la columna id_personal existe antes de usarla
        $columnExists = false;
        try {
            $stmtCheck = $pdo->query("
                SELECT column_name 
                FROM information_schema.columns 
                WHERE table_schema = 'public' 
                AND table_name = 'usuarios' 
                AND column_name = 'id_personal'
            ");
            $columnExists = $stmtCheck->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error al verificar columna id_personal: " . $e->getMessage());
        }
        
        // Actualizar solo las columnas que existen en la tabla usuarios
        $fields = "
            username = :username,
            modulo_id = :modulo,
            id_sucursal = :sucursal,
            id_cargo = :cargo
        ";
        $params = [
            ':username' => $username,
            ':modulo'   => $moduloId,
            ':sucursal' => $sucursalId,
            ':cargo'    => $cargoId,
            ':id'       => $id,
        ];
        
        // Solo incluir id_personal si la columna existe
        if ($columnExists) {
            $fields .= ", id_personal = :personal_id";
            $params[':personal_id'] = $personalIdFinal ?: null;
        }

        if ($password !== '') {
            if (!isValidPassword($password)) {
                redirectWithAlert(4, 'La contraseña debe tener entre 8 y 15 caracteres e incluir al menos un número.');
            }
            $hash = md5($password);
            $fields .= ", usua_password = :password";
            $params[':password'] = $hash;
        }

        // Validar que el cargo esté permitido para el módulo seleccionado
        if (!validarCargoParaModulo($pdo, $moduloId, $cargoId)) {
            redirectWithAlert(4, 'El cargo seleccionado no está permitido para el módulo elegido.');
        }

        $update = $pdo->prepare("UPDATE usuarios SET {$fields} WHERE id_usuario = :id");
        $update->execute($params);

        redirectWithAlert(2);
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id_usuario'] ?? 0);
        $estado = $_POST['estado'] ?? '';
        if ($id <= 0 || !in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            redirectWithAlert(4, 'Solicitud inválida.');
        }
        $stmt = $pdo->prepare("UPDATE usuarios SET estado_usuario = :estado WHERE id_usuario = :id");
        $stmt->execute([
            ':estado' => $estado,
            ':id'     => $id,
        ]);
        redirectWithAlert(3);
    } else {
        redirectWithAlert(4);
    }
} catch (PDOException $e) {
    $errorMsg = $e->getMessage();
    $errorCode = $e->getCode();
    $file = $e->getFile();
    $line = $e->getLine();
    
    error_log("Usuarios::accion -> Error: " . $errorMsg . " | Código: " . $errorCode . " | Archivo: " . $file . " | Línea: " . $line);
    
    // Mensajes de error más específicos según el código de error
    $userMessage = 'Error al procesar la solicitud. Verifique los datos ingresados.';
    
    // Detectar errores comunes de PostgreSQL
    if (stripos($errorMsg, 'column') !== false && stripos($errorMsg, 'does not exist') !== false) {
        $userMessage = 'Error: Una columna no existe. Verifique la estructura de la tabla usuarios.';
    } elseif (stripos($errorMsg, 'null value') !== false && stripos($errorMsg, 'violates not-null constraint') !== false) {
        $userMessage = 'Error: Faltan datos obligatorios. Complete todos los campos requeridos.';
    } elseif (stripos($errorMsg, 'duplicate key') !== false || stripos($errorMsg, 'unique') !== false) {
        $userMessage = 'Error: Ya existe un usuario con ese nombre de usuario o el personal ya está asociado.';
    } elseif (stripos($errorMsg, 'foreign key') !== false) {
        if (stripos($errorMsg, 'id_cargo') !== false) {
            $userMessage = 'Error: El cargo seleccionado no es válido.';
        } elseif (stripos($errorMsg, 'modulo_id') !== false) {
            $userMessage = 'Error: El módulo seleccionado no es válido.';
        } elseif (stripos($errorMsg, 'id_sucursal') !== false) {
            $userMessage = 'Error: La sucursal seleccionada no es válida.';
        } elseif (stripos($errorMsg, 'id_personal') !== false) {
            $userMessage = 'Error: El personal seleccionado no es válido.';
        } else {
            $userMessage = 'Error: Los datos seleccionados no son válidos. Verifique módulo, sucursal, cargo o personal.';
        }
    } elseif (stripos($errorMsg, 'syntax error') !== false) {
        $userMessage = 'Error: Error de sintaxis en la consulta SQL. Contacte al administrador.';
    }
    
    // TEMPORAL: Mostrar error completo para debug - DESACTIVAR EN PRODUCCIÓN
    // Mostrar el error completo para diagnóstico
    $userMessage = 'ERROR: ' . substr($errorMsg, 0, 300);
    if (strlen($errorMsg) > 300) {
        $userMessage .= '...';
    }
    
    redirectWithAlert(4, $userMessage);
} catch (Exception $e) {
    // Capturar cualquier otro tipo de error
    $errorMsg = $e->getMessage();
    $errorCode = $e->getCode();
    $file = $e->getFile();
    $line = $e->getLine();
    
    error_log("Usuarios::accion -> Error general: " . $errorMsg . " | Código: " . $errorCode . " | Archivo: " . $file . " | Línea: " . $line);
    
    $userMessage = 'Error inesperado: ' . substr($errorMsg, 0, 100);
    redirectWithAlert(4, $userMessage);
}
