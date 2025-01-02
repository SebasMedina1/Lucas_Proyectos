<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../../vendor/autoload.php';

require '../../../config/database.php'; // 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']); // Limpiar espacios en blanco

    try {
        // Verificar si el correo existe en la base de datos
        $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE LOWER(email) = LOWER(:email)");
        $stmt->bindParam(':email', $email);

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            echo "Error en la consulta: " . $e->getMessage();
        }

        if ($stmt->rowCount() === 1) {
            echo "Correo encontrado.<br>";
            $token = bin2hex(random_bytes(50));
            $expires = date("U") + 1800;

            $stmt = $pdo->prepare("INSERT INTO password_reset (email, token, expires) VALUES (:email, :token, :expires)");
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':expires', $expires);
            $stmt->execute();

            $resetLink = "http://localhost/proyecto_taller/modules/reset_password/forgot-password/reset_password.php?token=" . $token;

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'nicolasdominguez180804@gmail.com';
                $mail->Password = 'gmfy tqej mbbw ygzz';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('nicolasdominguez180804@gmail.com', 'DebianDEV');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Restablecer tu contraseña';
                $mail->Body = "Haz clic en el siguiente enlace para restablecer tu contraseña: <a href='$resetLink'>$resetLink</a>";

                $mail->send();
                echo 'Se ha enviado un correo para restablecer tu contraseña.';
            } catch (Exception $e) {
                echo 'No se pudo enviar el correo. Error: ' . $mail->ErrorInfo;
            }
        } else {
            echo 'El correo no está registrado.';
        }
    } catch (PDOException $e) {
        echo 'Error en la conexión: ' . $e->getMessage();
    }
}
?>
