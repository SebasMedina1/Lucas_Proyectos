<?php
session_start();
require "../../config/database.php"; // Asegúrate de tener la conexión a la base de datos

// Verificar si el usuario está autenticado
//if (empty($_SESSION['username']) && empty($_SESSION['password'])) {
//    header("Location: ../../index.php?alert=3");
//    exit;
//} else {
    // Detectar la acción
    if (isset($_GET['act'])) {
        $action = $_GET['act'];
        echo "llegue 1 $action ";
        // Agregar deposito
        if ($action == 'insert' && isset($_POST['Guardar'])) {
            $codigo = $_POST['id_cliente'];
            $CI = $_POST['ci_ruc'];
            $nombre = $_POST['cli_nombre'];
            $apellido = $_POST['cli_apellido'];
            $direccion = $_POST['cli_direccion'];
            $telefono = $_POST['cli_telefono'];
            $cod_ciudad = $_POST['cod_ciudad'];

            echo "llegue 2 $action $codigo $CI $nombre $apellido $direccion $telefono $cod_ciudad ";

            // Insertar en la base de datos
            $query = mysqli_query($mysqli, "insert into clientes (id_cliente, ci_ruc, cli_nombre, cli_apellido, 
            cli_direccion, cli_telefono, cod_ciudad) values ('$codigo','$CI','$nombre','$apellido','$direccion','$telefono','$cod_ciudad')") 
                    or die('Error: ' . mysqli_error($mysqli));
            echo"\ninsert into clientes (id_cliente, ci_ruc, cli_nombre, cli_apellido, cli_direccion, cli_telefono, cod_ciudad) values ('$codigo','$CI','$nombre','$apellido','$direccion','$telefono','$cod_ciudad')";
            // Redirigir con un mensaje de éxito o error
            if ($query) {
                echo 'llegue 3';
                header("Location: view.php?true");
            } else {
                echo 'llegue 4';
                header("Location: view.php?fail");
            }
        }

        // Actualizar deposito
        elseif ($action == 'update' && isset($_POST['Guardar'])) {
            $codigo = $_POST['id_cliente'];
            $CI = $_POST['ci_ruc'];
            $nombre = $_POST['cli_nombre'];
            $apellido = $_POST['cli_apellido'];
            $direccion = $_POST['cli_direccion'];
            $telefono = $_POST['cli_telefono'];
            $cod_ciudad = $_POST['cod_ciudad'];

            // Actualizar en la base de datos
            $query = mysqli_query($mysqli, "update clientes 
            set ci_ruc='$CI',cli_nombre='$nombre',cli_apellido ='$apellido', cli_direccion='$direccion', cli_telefono='$telefono',cod_ciudad='$cod_ciudad'
            where id_cliente ='$codigo'") 
                    or die('Error: ' . mysqli_error($mysqli));

            // Redirigir con un mensaje
            if ($query) {
                header("Location: view.php?true");
            } else {
                header("Location: view.php?fail");
            }
        }

        // Eliminar Ciudad
        elseif ($action == 'delete' && isset($_GET['id'])) {
            $codigo = $_GET['id'];

            // Eliminar de la base de datos
            $query = mysqli_query($mysqli, "DELETE FROM clientes WHERE id_cliente='$codigo'") 
                     or die('Error: ' . mysqli_error($mysqli));

            // Redirigir con un mensaje
            if ($query) {
                header("Location: view.php?true");
            } else {
                header("Location: view.php?fail");
            }
        }
    }
//}
?>
