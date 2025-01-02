<?php
session_start();
require "../../config/database.php"; // Asegúrate de tener la conexión a la base de datos

if (empty($_SESSION['username']) || empty($_SESSION['password'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}
else {

    // Verificar si existe la acción
    if (isset($_GET['act'])) {
        $action = $_GET['act'];

        try {
            // Configurar conexión PDO
            $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Agregar proveedor
            if ($action == 'insert' && isset($_POST['Guardar'])) {
                $codigo = $_POST['codigo'];
                $razon_social = $_POST['descrip_razon'];
                $ruc = $_POST['descrip_ruc'];
                $direccion = $_POST['descrip_direccion'];
                $telefono = $_POST['descrip_telefono'];

                // Insertar en la base de datos
                $query = $pdo->prepare("INSERT INTO proveedor (cod_proveedor, razon_social, ruc, direccion, telefono) 
                                        VALUES (:codigo, :razon_social, :ruc, :direccion, :telefono)");
                $query->bindParam(':codigo', $codigo, PDO::PARAM_INT);
                $query->bindParam(':razon_social', $razon_social, PDO::PARAM_STR);
                $query->bindParam(':ruc', $ruc, PDO::PARAM_STR);
                $query->bindParam(':direccion', $direccion, PDO::PARAM_STR);
                $query->bindParam(':telefono', $telefono, PDO::PARAM_STR);
                $query->execute();

                // Redirigir con un mensaje de éxito
                header("Location: view.php?alert=1");
            }

            // Actualizar proveedor
            elseif ($action == 'update' && isset($_POST['Guardar'])) {
                $codigo = $_POST['codigo'];
                $razon_social = $_POST['descrip_razon'];
                $ruc = $_POST['descrip_ruc'];
                $direccion = $_POST['descrip_direccion'];
                $telefono = $_POST['descrip_telefono'];

                // Actualizar en la base de datos
                $query = $pdo->prepare("UPDATE proveedor 
                                        SET razon_social = :razon_social,
                                            ruc = :ruc,
                                            direccion = :direccion,
                                            telefono = :telefono
                                        WHERE cod_proveedor = :codigo");
                $query->bindParam(':codigo', $codigo, PDO::PARAM_INT);
                $query->bindParam(':razon_social', $razon_social, PDO::PARAM_STR);
                $query->bindParam(':ruc', $ruc, PDO::PARAM_STR);
                $query->bindParam(':direccion', $direccion, PDO::PARAM_STR);
                $query->bindParam(':telefono', $telefono, PDO::PARAM_STR);
                
                $query->execute();

                // Redirigir con un mensaje de éxito
                header("Location: view.php?alert=2");
            }

            // Eliminar proveedor
            elseif ($action == 'delete' && isset($_GET['id'])) {
                $codigo = $_GET['id'];

                // Eliminar de la base de datos
                $query = $pdo->prepare("DELETE FROM proveedor WHERE cod_proveedor = :codigo");
                $query->bindParam(':codigo', $codigo, PDO::PARAM_INT);
                $query->execute();

                // Redirigir con un mensaje de éxito
                header("Location: view.php?alert=3");
            }
        } catch (PDOException $e) {
            // En caso de error, mostrar el mensaje
            die("Error en la operación con la base de datos: " . $e->getMessage());
        }
    }
}
?>
