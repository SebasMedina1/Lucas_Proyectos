<?php
$file = realpath("../config/database.php");

if (!$file || !file_exists($file)) {
    die("Error: No se pudo encontrar el archivo en la ruta $file");
}
require_once $file;

// Obtener datos del formulario
$username = htmlspecialchars(trim($_POST['username'] ?? ''));
$password = md5(trim($_POST['password'] ?? ''));

try {
    // Crear conexión con PostgreSQL usando PDO (usa variables de ../config/database.php)
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Buscar usuario por nombre de usuario con información del cargo
    $stmt = $pdo->prepare("
        SELECT 
            u.id_usuario, 
            u.username, 
            u.usua_password, 
            u.estado_usuario, 
            u.id_sucursal, 
            u.modulo_id, 
            u.id_personal, 
            u.id_cargo,
            c.cargo_descripcion
        FROM usuarios u
        LEFT JOIN cargos c ON c.id_cargo = u.id_cargo AND c.estado_cargo = 'ACTIVO'
        WHERE u.username = :username
        LIMIT 1
    ");
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    session_start();
    // Inicializar estructura de intentos en sesión
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    if (!isset($_SESSION['login_attempts'][$username])) {
        $_SESSION['login_attempts'][$username] = 0;
    }

    // Verificar existencia del usuario
    if ($userData) {
        // Si el usuario está inactivo
        if ($userData['estado_usuario'] === 'INACTIVO') {
            $_SESSION['message'] = "El usuario está inactivo. Contacte al administrador.";
            $_SESSION['message_type'] = "error";
            header("Location: ../login.html");
            exit();
        }

        // Comparar contraseña (md5)
        if ($userData['usua_password'] === $password) {
            // Reiniciar intentos fallidos (en sesión) al iniciar correctamente
            $_SESSION['login_attempts'][$username] = 0;

            // Obtener y normalizar el nombre del cargo
            require_once realpath('../config/permissions.php');
            $idCargo = (int)($userData['id_cargo'] ?? 0);
            $cargoNombre = '';
            
            if ($idCargo > 0) {
                $cargoNombre = obtenerNombreCargo($pdo, $idCargo);
            }

            // Setear variables de sesión
            $_SESSION['usua_id']      = (int)$userData['id_usuario'];
            $_SESSION['id_usuario']  = (int)$userData['id_usuario'];
            $_SESSION['sucursal_id']  = (int)$userData['id_sucursal'];
            $_SESSION['username']   = $userData['username']; 
            $_SESSION['id_cargo']   = $idCargo;
            $_SESSION['cargo_nombre'] = $cargoNombre; // Nombre normalizado del cargo (ADMIN, JEFE_COMPRAS, etc.)
            $_SESSION['modulo_id']    = (int)$userData['modulo_id'];
            //$_SESSION['personal_id']  = isset($userData['id_personal']) ? (int)$userData['id_personal'] : null;

            $_SESSION['message'] = "¡Inicio de sesión exitoso!";
            $_SESSION['message_type'] = "success";

            header("Location: ../index.php");
            exit();
        } else {
            // Incrementar intentos fallidos (en sesión)
            $_SESSION['login_attempts'][$username]++;

            $intentos = (int)$_SESSION['login_attempts'][$username];

            if ($intentos >= 3) {
                // Desactivar usuario si falla 3 veces (sin columna intentos_fallidos en BD)
                $update = $pdo->prepare("UPDATE usuarios SET estado_usuario = 'INACTIVO' WHERE username = :username");
                $update->bindParam(':username', $username, PDO::PARAM_STR);
                $update->execute();

                $_SESSION['message'] = "Cuenta bloqueada por intentos fallidos. Contacte al administrador.";
                $_SESSION['message_type'] = "error";

                // Opcional: resetear el contador en sesión después del bloqueo
                $_SESSION['login_attempts'][$username] = 0;
            } else {
                $_SESSION['message'] = "Contraseña incorrecta. Intento $intentos de 3.";
                $_SESSION['message_type'] = "error";
            }

            header("Location: ../login.html");
            exit();
        }
    } else {
        // Usuario no existe
        $_SESSION['message'] = "Usuario no encontrado.";
        $_SESSION['message_type'] = "error";
        header("Location: ../login.html");
        exit();
    }

} catch (PDOException $e) {
    die("Error en la conexión: " . $e->getMessage());
}
