<?php
session_start();
require "../../config/database.php"; // Asegúrate de tener la conexión a la base de datos

// Detectar la acción
if (isset($_GET['act'])) {
    $action = $_GET['act'];
    //echo ('Llegué 1: ' . $action);

    try {
        // Crear conexión con PostgreSQL usando PDO
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Insertar departamento
        if ($action == 'insert' && isset($_POST['Guardar'])) {
            $codigo = $_POST['id_departamento'];
            $descripcion = $_POST['dep_descripcion'];

            // Preparar e insertar en la base de datos
            $query = $pdo->prepare("INSERT INTO departamento (id_departamento, dep_descripcion) VALUES (:codigo, :descripcion)");
            $query->bindParam(':codigo', $codigo, PDO::PARAM_INT);
            $query->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
            $query->execute();

            // Redirigir con un mensaje de éxito
            header("Location: view.php?alert=1");
        }

        // Actualizar departamento
        elseif ($action == 'update' && isset($_POST['Guardar'])) {
            $codigo = $_POST['id_departamento'];
            $descripcion = $_POST['dep_descripcion'];

            // Preparar y actualizar en la base de datos
            $query = $pdo->prepare("UPDATE departamento SET dep_descripcion = :descripcion WHERE id_departamento = :codigo");
            $query->bindParam(':codigo', $codigo, PDO::PARAM_INT);
            $query->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
            $query->execute();

            // Redirigir con un mensaje de éxito
            header("Location: view.php?alert=2");
        }

        // Eliminar departamento
        elseif ($action == 'delete' && isset($_GET['id'])) {
            $codigo = $_GET['id'];

            // Preparar y eliminar de la base de datos
            $query = $pdo->prepare("DELETE FROM departamento WHERE id_departamento = :codigo");
            $query->bindParam(':codigo', $codigo, PDO::PARAM_INT);
            $query->execute();

            // Redirigir con un mensaje de éxito
            header("Location: view.php?alert=3");
        }
    } catch (PDOException $e) {
        // Manejar errores de conexión o consulta
        die("Error en la base de datos: " . $e->getMessage());
    }
}
?>
