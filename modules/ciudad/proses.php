<?php
session_start();
require "../../config/database.php";

// Verificar si el usuario está autenticado
if (empty($_SESSION['username'])) {
    header("Location: ../../login.html");
    exit();
}

try {
    // Crear conexión con PostgreSQL usando PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Detectar la acción
    if (isset($_GET['act'])) {
        $action = $_GET['act'];

        // Agregar ciudad
        if ($action == 'insert' && isset($_POST['Guardar'])) {
            $codigo = $_POST['codigo'];
            $departamento = $_POST['departamento'];
            $descripcion = $_POST['descrip_ciudad'];

            // Insertar en la base de datos
            $query = $pdo->prepare("INSERT INTO ciudad (cod_ciudad, descrip_ciudad, id_departamento) VALUES (:codigo, :descripcion, :departamento)");
            $query->bindParam(':codigo', $codigo, PDO::PARAM_INT);
            $query->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
            $query->bindParam(':departamento', $departamento, PDO::PARAM_INT);

            if ($query->execute()) {
                header("Location: view.php?alert=1");
            } else {
                header("Location: view.php?alert=4");
            }
        }

        // Actualizar ciudad
        elseif ($action == 'update' && isset($_POST['Guardar'])) {
            $codigo = $_POST['codigo'];
            $descripcion = $_POST['descrip'];

            // Actualizar en la base de datos
            $query = $pdo->prepare("UPDATE ciudad SET descrip_ciudad = :descripcion WHERE cod_ciudad = :codigo");
            $query->bindParam(':codigo', $codigo, PDO::PARAM_INT);
            $query->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);

            if ($query->execute()) {
                header("Location: view.php?alert=2");
            } else {
                header("Location: view.php?alert=4");
            }
        }

        // Eliminar ciudad
        elseif ($action == 'delete' && isset($_GET['id'])) {
            $codigo = $_GET['id'];

            // Eliminar de la base de datos
            $query = $pdo->prepare("DELETE FROM ciudad WHERE cod_ciudad = :codigo");
            $query->bindParam(':codigo', $codigo, PDO::PARAM_INT);

            if ($query->execute()) {
                header("Location: view.php?alert=3");
            } else {
                header("Location: view.php?alert=4");
            }
        }
    }
} catch (PDOException $e) {
    header("Location: view.php?alert=4");
    exit();
}
?>

