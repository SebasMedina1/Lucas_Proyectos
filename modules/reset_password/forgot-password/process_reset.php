<?php
if (isset($_POST['token'], $_POST['email'], $_POST['new_password'])) {
    $token = $_POST['token'];
    $email = $_POST['email'];
    $passwordMD5 = md5($_POST['new_password']); // Aplicar MD5 al texto plano de la contraseña

    // Conexión a la base de datos
    require '../../../config/database.php'; // Este archivo debe contener la configuración de la conexión con PostgreSQL

    try {
        // Verificar si el token es válido
        $stmt = $pdo->prepare(
            "SELECT email FROM password_reset WHERE token = :token AND email = :email AND expires >= :now"
        );
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':email', $email);
        $now = date("U");
        $stmt->bindParam(':now', $now);
        $stmt->execute();

        if ($stmt->rowCount() === 1) {
            // Actualizar contraseña
            $stmt = $pdo->prepare("UPDATE usuarios SET usua_password = :password WHERE email = :email");
            $stmt->bindParam(':password', $passwordMD5);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            // Eliminar token usado
            $stmt = $pdo->prepare("DELETE FROM password_reset WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            echo 'Contraseña actualizada correctamente.';
        } else {
            echo 'El enlace es inválido o ha expirado.';
        }
    } catch (PDOException $e) {
        echo 'Error en la conexión: ' . $e->getMessage();
    }
}
?>
