<?php 
/**
 * Procesamiento del cambio de contraseña
 * 
 * Valida que la contraseña actual sea correcta y actualiza con la nueva contraseña.
 */

session_start();

if (empty($_SESSION['username']) || empty($_SESSION['id_usuario'])) {
    header("Location: reset.php?alert=4");
    exit();
}

require_once realpath("../../config/database.php");

// Verificar que se haya enviado el formulario
if (!isset($_POST['Guardar'])) {
    header("Location: reset.php?alert=4");
    exit();
}

// Validar campos vacíos
if (empty($_POST['old_pass']) || empty($_POST['new_pass']) || empty($_POST['retype_pass'])) {
    header("Location: reset.php?alert=4");
    exit();
}

$old_pass = trim($_POST['old_pass']);
$new_pass = trim($_POST['new_pass']);
$retype_pass = trim($_POST['retype_pass']);

// Validar longitud y formato de la nueva contraseña
function isValidPassword(string $password): bool {
    $len = strlen($password);
    return $len >= 8 && $len <= 15 && preg_match('/\d/', $password);
}

if (!isValidPassword($new_pass)) {
    header("Location: reset.php?alert=4&msg=" . urlencode("La contraseña debe tener entre 8 y 15 caracteres e incluir al menos un número."));
    exit();
}

// Validar que las nuevas contraseñas coincidan
if ($new_pass !== $retype_pass) {
    header("Location: reset.php?alert=2");
    exit();
}

$id_user = (int)$_SESSION['id_usuario'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Consultar la contraseña actual de la BD
    $stmt = $pdo->prepare("SELECT usua_password FROM usuarios WHERE id_usuario = :id_user LIMIT 1");
    $stmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetch();

    if (!$data) {
        header("Location: reset.php?alert=4&msg=" . urlencode("Usuario no encontrado."));
        exit();
    }

    // Validar contraseña actual (MD5)
    $old_pass_hash = md5($old_pass);
    if ($old_pass_hash !== $data['usua_password']) {
        header("Location: reset.php?alert=1");
        exit();
    }

    // Validar que la nueva contraseña sea diferente a la actual
    $new_pass_hash = md5($new_pass);
    if ($old_pass_hash === $new_pass_hash) {
        header("Location: reset.php?alert=4&msg=" . urlencode("La nueva contraseña debe ser diferente a la actual."));
        exit();
    }

    // Actualizar la contraseña en la BD
    $update_stmt = $pdo->prepare("UPDATE usuarios SET usua_password = :new_pass WHERE id_usuario = :id_user");
    $update_stmt->bindParam(':new_pass', $new_pass_hash);
    $update_stmt->bindParam(':id_user', $id_user, PDO::PARAM_INT);
    $update_stmt->execute();

    // Redirigir con mensaje de éxito
    header("Location: reset.php?alert=3");
    exit();

} catch (PDOException $e) {
    error_log("Error al cambiar contraseña: " . $e->getMessage());
    header("Location: reset.php?alert=4&msg=" . urlencode("Error al procesar la solicitud. Intente nuevamente."));
    exit();
}
?>
