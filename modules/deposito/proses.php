<?php
session_start();
require "../../config/database.php"; // Asegúrate de tener la conexión a la base de datos

// Detectar la acción
if (isset($_GET['act'])) {
    $action = $_GET['act'];
    echo ('Llegué 1: ' . $action);

    try {
        // Crear conexión con PostgreSQL usando PDO
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Insertar depósito
        if ($action == 'insert' && isset($_POST['Guardar'])) {
            $codigo = $_POST['codigo'];
            $descripcion = $_POST['descrip_deposito'];

            // Preparar e insertar en la base de datos
            $query = $pdo->prepare("INSERT INTO deposito (cod_deposito, descrip) VALUES (:codigo, :descripcion)");
            $query->bindParam(':codigo', $codigo, PDO::PARAM_INT);
            $query->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
            $query->execute();

            // Redirigir con un mensaje de éxito
            header("Location: view.php?alert=1");
        }

        // Actualizar depósito
        elseif ($action == 'update' && isset($_POST['Guardar'])) {
            $codigo = $_POST['codigo'];
            $descripcion = $_POST['descrip'];

            // Preparar y actualizar en la base de datos
            $query = $pdo->prepare("UPDATE deposito SET descrip = :descripcion WHERE cod_deposito = :codigo");
            $query->bindParam(':codigo', $codigo, PDO::PARAM_INT);
            $query->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
            $query->execute();

            // Redirigir con un mensaje de éxito
            header("Location: view.php?alert=2");
        }

        // Eliminar depósito
        elseif ($action == 'delete' && isset($_GET['id'])) {
            $codigo = $_GET['id'];

            // Preparar y eliminar de la base de datos
            $query = $pdo->prepare("DELETE FROM deposito WHERE cod_deposito = :codigo");
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
