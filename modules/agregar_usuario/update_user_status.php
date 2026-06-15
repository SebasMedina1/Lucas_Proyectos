<?php
// Incluir la conexión a la base de datos
require '../../config/database.php';

// Verificar si la solicitud es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capturar los datos enviados desde AJAX
    $id_usuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);
    $estado = filter_input(INPUT_POST, 'estado', FILTER_SANITIZE_SPECIAL_CHARS);

    // Validar que los datos no estén vacíos
    if ($id_usuario && $estado) {
        try {
            // Crear conexión con PostgreSQL usando PDO
            $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Actualizar el estado del usuario
            $query = "UPDATE usuarios SET estado = :estado WHERE id_usuario = :id_usuario";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':estado', $estado);
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->execute();

            // Enviar respuesta de éxito
            echo "Estado del usuario actualizado correctamente.";
        } catch (PDOException $e) {
            // En caso de error, enviar un mensaje
            error_log("Error al actualizar el estado del usuario: " . $e->getMessage());
            echo "Error al actualizar el estado del usuario.";
        }
    } else {
        // Si los datos son inválidos, enviar un mensaje de error
        echo "Datos inválidos.";
    }
} else {
    // Si la solicitud no es POST, enviar un mensaje de error
    echo "Método no permitido.";
}
