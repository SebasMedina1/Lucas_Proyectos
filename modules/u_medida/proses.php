<?php
session_start();
require "../../config/database.php"; 

try {
    // Conexión a PostgreSQL con PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Detectar la acción
    if (isset($_GET['act'])) {
        $action = $_GET['act'];

        // Insertar unidad de medida
        if ($action == 'insert' && isset($_POST['Guardar'])) {
            $codigo = $_POST['codigo'];
            $descripcion = $_POST['descrip_umedida'];

            // Insertar en la base de datos
            $query = $pdo->prepare("INSERT INTO u_medida (id_u_medida, u_descrip) VALUES (:codigo, :descripcion)");
            $query->bindParam(':codigo', $codigo, PDO::PARAM_INT);
            $query->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);

            if ($query->execute()) {
                header("Location: view.php?alert=1");
            } else {
                header("Location: view.php?alert=4");
            }
        }

        // Actualizar unidad de medida
        elseif ($action == 'update' && isset($_POST['Guardar'])) {
            $codigo = $_POST['codigo'];
            $descripcion = $_POST['u_descrip'];

            // Actualizar en la base de datos
            $query = $pdo->prepare("UPDATE u_medida SET u_descrip = :descripcion WHERE id_u_medida = :codigo");
            $query->bindParam(':codigo', $codigo, PDO::PARAM_INT);
            $query->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);

            if ($query->execute()) {
                header("Location: view.php?alert=2");
            } else {
                header("Location: view.php?alert=4");
            }
        }

        // Eliminar unidad de medida
        elseif ($action == 'delete' && isset($_GET['id'])) {
            $codigo = $_GET['id'];

            // Eliminar de la base de datos
            $query = $pdo->prepare("DELETE FROM u_medida WHERE id_u_medida = :codigo");
            $query->bindParam(':codigo', $codigo, PDO::PARAM_INT);

            if ($query->execute()) {
                header("Location: view.php?alert=3");
            } else {
                header("Location: view.php?alert=4");
            }
        }
    }
} catch (PDOException $e) {
    die("Error en la conexión o consulta: " . $e->getMessage());
}
?>
