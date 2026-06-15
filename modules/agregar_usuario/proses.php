<?php
// Iniciar sesión
session_start();

// Incluir la conexión a la base de datos
require '../../config/database.php';

// Verificar si el usuario está autenticado
if (empty($_SESSION['username']) || empty($_SESSION['password'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}


// Verificar si la acción es 'insert_user'
// Verificar si la acción es 'insert_user'
if (isset($_GET['act']) && $_GET['act'] == 'insert_user') {
    try {
        // Configurar la conexión PDO
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Capturar datos del formulario
        $personal_id = (int) $_POST['personal_id']; // ID de Personal
        $usuario_id = (int) $_POST['usuario_id'];   // ID de Usuario

        $personal_nombre = trim($_POST['personal_nombre']);
        $personal_apellido = trim($_POST['personal_apellido']);
        $personal_ci = trim($_POST['personal_ci']);
        $personal_telefono = trim($_POST['personal_telefono']);
        $personal_direccion = trim($_POST['personal_direccion']);
        $cargo_id = $_POST['cargo_id'];

        $username = trim($_POST['username']);
        $password = md5(trim($_POST['password'])); // Encriptar la contraseña
        $email = trim($_POST['email']);
        $permisos_acceso = $_POST['permisos_acceso'];
        $estado = $_POST['estado'];

        echo "<script>console.log('Datos recibidos:');</script>";
        echo "<script>console.log('Cédula: {$personal_ci}');</script>";
        echo "<script>console.log('Teléfono: {$personal_telefono}');</script>";
        echo "<script>console.log('Correo electrónico: {$email}');</script>";
        echo "<script>console.log('Username: {$username}');</script>";

        // Validar si la cédula ya existe
        $query_ci = $pdo->prepare("SELECT COUNT(*) FROM personal WHERE personal_ci = :ci");
        $query_ci->execute([':ci' => $personal_ci]);
        $ci_existe = $query_ci->fetchColumn();

        if ($ci_existe > 0) {
            echo "<script>
                    console.error('Validación fallida: La cédula ya existe - Valor: {$personal_ci}');
                    alert('La cédula ya existe. Por favor, ingrese una cédula diferente.');
                    window.history.back();
                  </script>";
            exit;
        }

        // Validar si el teléfono ya existe
        $query_telefono = $pdo->prepare("SELECT COUNT(*) FROM personal WHERE personal_telefono = :telefono");
        $query_telefono->execute([':telefono' => $personal_telefono]);
        $telefono_existe = $query_telefono->fetchColumn();

        if ($telefono_existe > 0) {
            echo "<script>
                    console.error('Validación fallida: El teléfono ya existe - Valor: {$personal_telefono}');
                    alert('El teléfono ya existe. Por favor, ingrese un teléfono diferente.');
                    window.history.back();
                  </script>";
            exit;
        }

        // Validar si el correo electrónico ya existe
        $query_email = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email");
        $query_email->execute([':email' => $email]);
        $email_existe = $query_email->fetchColumn();

        if ($email_existe > 0) {
            echo "<script>
                    console.error('Validación fallida: El correo ya existe - Valor: {$email}');
                    alert('El correo ya existe. Por favor, ingrese un correo electrónico diferente.');
                    window.history.back();
                  </script>";
            exit;
        }

        // Validar si el username ya existe
        $query_username = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE username = :username");
        $query_username->execute([':username' => $username]);
        $username_existe = $query_username->fetchColumn();

        if ($username_existe > 0) {
            echo "<script>
                    console.error('Validación fallida: El nombre de usuario ya existe - Valor: {$username}');
                    alert('El nombre de usuario ya existe. Por favor, ingrese un nombre de usuario diferente.');
                    window.history.back();
                  </script>";
            exit;
        }

        echo "<script>console.log('Validaciones completadas. Procediendo con la inserción.');</script>";

        // Iniciar transacción
        $pdo->beginTransaction();

        // Insertar datos en la tabla `personal`
        $stmt_personal = $pdo->prepare("
            INSERT INTO personal (personal_id, personal_nombre, personal_apellido, personal_ci, personal_telefono, personal_direccion, cargo_id)
            VALUES (:personal_id, :personal_nombre, :personal_apellido, :personal_ci, :personal_telefono, :personal_direccion, :cargo_id)
        ");
        $stmt_personal->bindParam(':personal_id', $personal_id);
        $stmt_personal->bindParam(':personal_nombre', $personal_nombre);
        $stmt_personal->bindParam(':personal_apellido', $personal_apellido);
        $stmt_personal->bindParam(':personal_ci', $personal_ci);
        $stmt_personal->bindParam(':personal_telefono', $personal_telefono);
        $stmt_personal->bindParam(':personal_direccion', $personal_direccion);
        $stmt_personal->bindParam(':cargo_id', $cargo_id);
        $stmt_personal->execute();
        echo "<script>console.log('Inserción en la tabla personal completada.');</script>";

        // Insertar datos en la tabla `usuarios`
        $stmt_usuario = $pdo->prepare("
            INSERT INTO usuarios (id_usuario, username, usua_password, email, permisos_acceso, estado, sucursal_id, personal_id, cargo_id)
            VALUES (:id_usuario, :username, :usua_password, :email, :permisos_acceso, :estado, 1, :personal_id, :cargo_id)
        ");
        $stmt_usuario->bindParam(':id_usuario', $usuario_id);
        $stmt_usuario->bindParam(':username', $username);
        $stmt_usuario->bindParam(':usua_password', $password);
        $stmt_usuario->bindParam(':email', $email);
        $stmt_usuario->bindParam(':permisos_acceso', $permisos_acceso);
        $stmt_usuario->bindParam(':estado', $estado);
        $stmt_usuario->bindParam(':personal_id', $personal_id);
        $stmt_usuario->bindParam(':cargo_id', $cargo_id);
        $stmt_usuario->execute();
        echo "<script>console.log('Inserción en la tabla usuarios completada.');</script>";

        // Confirmar transacción
        $pdo->commit();
        echo "<script>console.log('Transacción completada con éxito.');</script>";

        // Redirigir con mensaje de éxito
        header("Location: view.php?alert=1");

    } catch (PDOException $e) {
        // En caso de error, revertir la transacción
        $pdo->rollBack();
        error_log("Error: " . $e->getMessage());
        echo "<script>console.error('Error durante la transacción: " . $e->getMessage() . "');</script>";
        header("Location: view.php?alert=4");
        exit();
    }
}


// Verificar si la acción es 'update_estado'
if (isset($_GET['act']) && $_GET['act'] == 'update_user') {
    require_once '../../config/database.php';

    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Capturar los datos del formulario
        $usuario_id = $_POST['usuario_id'] ?? null;
        $personal_id = $_POST['personal_id'] ?? null;
        $personal_nombre = $_POST['personal_nombre'] ?? null;
        $personal_apellido = $_POST['personal_apellido'] ?? null;
        $personal_ci = $_POST['personal_ci'] ?? null;
        $personal_telefono = $_POST['personal_telefono'] ?? null;
        $personal_direccion = $_POST['personal_direccion'] ?? null;
        $cargo_id = $_POST['cargo_id'] ?? null;
        $username = $_POST['username'] ?? null;
        $email = $_POST['email'] ?? null;
        $permisos_acceso = $_POST['permisos_acceso'] ?? null;

        // Validar que los IDs no estén vacíos
        if (!$usuario_id || !$personal_id) {
            header("Location: view.php?alert=4");
            exit();
        }

        // Actualizar la tabla personal
        $query_personal = "UPDATE personal SET personal_nombre = :personal_nombre, personal_apellido = :personal_apellido, personal_ci = :personal_ci, personal_telefono = :personal_telefono, personal_direccion = :personal_direccion, cargo_id = :cargo_id WHERE personal_id = :personal_id";
        $stmt_personal = $pdo->prepare($query_personal);
        $stmt_personal->execute([
            ':personal_nombre' => $personal_nombre,
            ':personal_apellido' => $personal_apellido,
            ':personal_ci' => $personal_ci,
            ':personal_telefono' => $personal_telefono,
            ':personal_direccion' => $personal_direccion,
            ':cargo_id' => $cargo_id,
            ':personal_id' => $personal_id
        ]);

        // Actualizar la tabla usuarios
        $query_usuario = "UPDATE usuarios SET username = :username, email = :email, permisos_acceso = :permisos_acceso WHERE id_usuario = :id_usuario";
        $stmt_usuario = $pdo->prepare($query_usuario);
        $stmt_usuario->execute([
            ':username' => $username,
            ':email' => $email,
            ':permisos_acceso' => $permisos_acceso,
            ':id_usuario' => $usuario_id
        ]);

        header("Location: view.php?alert=2");
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
} else {
    header("Location: view.php?alert=4");
    exit();
}

// modificar estado


?>



