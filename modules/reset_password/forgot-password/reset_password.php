<?php
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Conexión a la base de datos
    require '../../../config/database.php'; // Conexión PostgreSQL existente

    try {
        // Verificar si el token es válido
        $stmt = $pdo->prepare("SELECT email FROM password_reset WHERE token = :token AND expires >= :now");
        $stmt->bindParam(':token', $token);
        $now = date("U");
        $stmt->bindParam(':now', $now);
        $stmt->execute();

        if ($stmt->rowCount() === 1) {
            // Token válido, mostrar formulario
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $email = $row['email'];

            echo '
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Restablecer Contraseña</title>
                <!-- CSS de SB Admin 2 -->
                <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
                <link href="https://startbootstrap.github.io/startbootstrap-sb-admin-2/css/sb-admin-2.min.css" rel="stylesheet">
            </head>
            <body class="bg-gradient-primary">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-xl-10 col-lg-12 col-md-9">
                            <div class="card o-hidden border-0 shadow-lg my-5">
                                <div class="card-body p-0">
                                    <div class="row">
                                        <div class="col-lg-6 d-none d-lg-block bg-password-image"></div>
                                        <div class="col-lg-6">
                                            <div class="p-5">
                                                <div class="text-center">
                                                    <h1 class="h4 text-gray-900 mb-4">Restablecer Contraseña</h1>
                                                </div>
                                                <form class="user" method="post" action="process_reset.php" onsubmit="return validatePasswords()">
                                                    <input type="hidden" name="email" value="' . $email . '">
                                                    <input type="hidden" name="token" value="' . $token . '">
                                                    <div class="form-group">
                                                        <label for="new_password">Nueva Contraseña:</label>
                                                        <input type="password" class="form-control form-control-user" name="new_password" id="new_password" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="confirm_password">Confirmar Contraseña:</label>
                                                        <input type="password" class="form-control form-control-user" name="confirm_password" id="confirm_password" required>
                                                    </div>
                                                    <button type="submit" class="btn btn-primary btn-user btn-block">Restablecer Contraseña</button>
                                                </form>
                                                <hr>
                                                <div class="text-center">
                                                    <a class="small" href="http://localhost/proyecto_taller/login.html">Volver al Login</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- JS de SB Admin 2 -->
                <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
                <script src="https://startbootstrap.github.io/startbootstrap-sb-admin-2/js/sb-admin-2.min.js"></script>
                <script>
                    function validatePasswords() {
                        const password = document.getElementById("new_password").value;
                        const confirmPassword = document.getElementById("confirm_password").value;
                        if (password !== confirmPassword) {
                            alert("Las contraseñas no coinciden. Por favor, inténtelo de nuevo.");
                            return false;
                        }
                        return true;
                    }
                </script>
            </body>
            </html>';
        } else {
            echo '<div class="container"><div class="alert alert-danger mt-5">El enlace ha expirado o es inválido.</div></div>';
        }
    } catch (PDOException $e) {
        echo '<div class="container"><div class="alert alert-danger mt-5">Error en la conexión: ' . $e->getMessage() . '</div></div>';
    }
}
?>
