<?php
$file = realpath("../config/database.php");

if (!$file || !file_exists($file)) {
    die("Error: No se pudo encontrar el archivo en la ruta $file");
}

require_once $file;

// Obtener datos del formulario
$username = htmlspecialchars(trim($_POST['username']));
$password = md5(htmlspecialchars(trim($_POST['password'])));

try {
    // Crear conexión con PostgreSQL usando PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);

    // Configurar excepciones para errores
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Preparar consulta
    $query = $pdo->prepare("SELECT * FROM usuarios WHERE username = :username AND usua_password = :password AND estado_usuario = 'ACTIVO'");
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->bindParam(':password', $password, PDO::PARAM_STR);

    // Ejecutar consulta
    $query->execute();

    // Verificar si hay resultados
    $rows = $query->rowCount();

    session_start();

    if ($rows > 0) {
        echo "Usuario encontrado.<br>"; // Confirmación adicional
        $data = $query->fetch(PDO::FETCH_ASSOC);

        $_SESSION['id_usuario'] = $data['id_usuario'];
        $_SESSION['username'] = $data['username'];
        $_SESSION['password'] = $data['usua_password'];
        $_SESSION['id_cargo'] = (int)($data['id_cargo'] ?? 0);
        $_SESSION['message'] = "¡Inicio de sesión exitoso!";
        $_SESSION['message_type'] = "success";

        header("Location: ../index.php");
        exit();
    } else {
        echo "Usuario o contraseña incorrectos.<br>"; // Mensaje en caso de no encontrar coincidencias
        $_SESSION['message'] = "Usuario o contraseña incorrectos";
        $_SESSION['message_type'] = "error";

        header("Location: ../login.html");
        exit();
    }
} catch (PDOException $e) {
    die("Error en la conexión: " . $e->getMessage());
}
