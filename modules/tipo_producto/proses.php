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

        // Agregar tipo de producto
        if ($action == 'insert' && isset($_POST['Guardar'])) {
            $codigo = $_POST['cod_tipo_prod'];
            $descripcion = $_POST['t_p_descrip'];

            // Insertar en la base de datos
            $query = $pdo->prepare("INSERT INTO tipo_producto (cod_tipo_prod, t_p_descrip) VALUES (:codigo, :descripcion)");
            $query->bindParam(':codigo', $codigo, PDO::PARAM_INT);
            $query->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);

            if ($query->execute()) {
                header("Location: view.php?alert=1");
            } else {
                header("Location: view.php?alert=4");
            }
        }

        // Actualizar tipo de producto
        elseif ($action == 'update' && isset($_POST['Guardar'])) {
            $codigo = $_POST['cod_tipo_prod'];
            $descripcion = $_POST['t_p_descrip'];

            // Actualizar en la base de datos
            $query = $pdo->prepare("UPDATE tipo_producto SET t_p_descrip = :descripcion WHERE cod_tipo_prod = :codigo");
            $query->bindParam(':codigo', $codigo, PDO::PARAM_INT);
            $query->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);

            if ($query->execute()) {
                header("Location: view.php?alert=2");
            } else {
                header("Location: view.php?alert=4");
            }
        }

        // Eliminar tipo de producto
        elseif ($action == 'delete' && isset($_GET['id'])) {
            $codigo = $_GET['id'];

            // Eliminar de la base de datos
            $query = $pdo->prepare("DELETE FROM tipo_producto WHERE cod_tipo_prod = :codigo");
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

