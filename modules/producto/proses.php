<?php
session_start();
require "../../config/database.php"; // Asegúrate de tener la conexión a la base de datos

// Verificar si el usuario está autenticado
if (empty($_SESSION['username']) || empty($_SESSION['password'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}
// Detectar la acción
if (isset($_GET['act'])) {
    $action = $_GET['act'];

    try {
        // Conectar a la base de datos
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Agregar producto
if ($action == 'insert' && isset($_POST['Guardar'])) {
    $unidad_medida = $_POST['unidad_medida'];
    $tipo_producto = $_POST['tipo_producto'];
    $tipo_iva = $_POST['tipo_iva'];
    $descrip_producto = $_POST['descrip_producto'];
    $descrip_precio = $_POST['descrip_precio'];
    $deposito = $_POST['cod_deposito'];

    try {
        // Insertar en la tabla `producto` (sin cod_producto)
        $query = $pdo->prepare("INSERT INTO producto (cod_tipo_producto, id_u_medida, p_descrip, precio, iva_id, cod_deposito) 
                                VALUES (:tipo_producto, :unidad_medida, :descrip_producto, :descrip_precio, :tipo_iva, :cod_deposito)");
        $query->bindParam(':tipo_producto', $tipo_producto, PDO::PARAM_INT);
        $query->bindParam(':unidad_medida', $unidad_medida, PDO::PARAM_INT);
        $query->bindParam(':descrip_producto', $descrip_producto, PDO::PARAM_STR);
        $query->bindParam(':descrip_precio', $descrip_precio, PDO::PARAM_INT);
        $query->bindParam(':tipo_iva', $tipo_iva, PDO::PARAM_INT);
        $query->bindParam(':cod_deposito', $deposito, PDO::PARAM_INT);
        $query->execute();

        // Obtener el último ID generado automáticamente (cod_producto)
        $codigo = $pdo->lastInsertId('producto_cod_producto_seq'); // Cambia el nombre de la secuencia según corresponda

        // Insertar en la tabla `stock` con stock inicial de 0
        $query_Stock = $pdo->prepare("INSERT INTO stock (cod_deposito, cod_producto, stock_existencia) 
                                      VALUES (:cod_deposito, :codigo, 0)");
        $query_Stock->bindParam(':cod_deposito', $deposito, PDO::PARAM_INT);
        $query_Stock->bindParam(':codigo', $codigo, PDO::PARAM_INT);
        $query_Stock->execute();

        // Redirigir a la vista principal con alerta
        header("Location: view.php?alert=1");
        exit();
    } catch (PDOException $e) {
        die("Error en la operación con la base de datos: " . $e->getMessage());
    }
}


        // Actualizar producto
        elseif ($action == 'update' && isset($_POST['Guardar'])) {
            $codigo = $_POST['codigo'];
            $unidad_medida = $_POST['unidad'];
            $tipo_producto = $_POST['tipo_producto'];
            $tipo_iva = $_POST['iva'];
            $descrip_producto = $_POST['descrip_producto'];
            $descrip_precio = $_POST['descrip_precio'];
            $deposito = $_POST['cod_deposito'];

            // Actualizar en la base de datos
            $query = $pdo->prepare("UPDATE producto 
                                    SET cod_tipo_producto = :tipo_producto,
                                        id_u_medida = :unidad_medida,
                                        p_descrip = :descrip_producto,
                                        precio = :descrip_precio, 
                                        iva_id = :tipo_iva,
                                        cod_deposito = :cod_deposito

                                    WHERE cod_producto = :codigo");
            $query->bindParam(':tipo_producto', $tipo_producto, PDO::PARAM_INT);
            $query->bindParam(':unidad_medida', $unidad_medida, PDO::PARAM_INT);
            $query->bindParam(':descrip_producto', $descrip_producto, PDO::PARAM_STR);
            $query->bindParam(':descrip_precio', $descrip_precio, PDO::PARAM_INT);
            $query->bindParam(':tipo_iva', $tipo_iva, PDO::PARAM_INT);
            $query->bindParam(':cod_deposito', $deposito, PDO::PARAM_INT);
            $query->bindParam(':codigo', $codigo, PDO::PARAM_INT);
            $query->execute();

            header("Location: view.php?alert=2");
        }

        // Eliminar materia prima
        elseif ($action == 'delete' && isset($_GET['id'])) {
            $codigo = $_GET['id'];

            // Eliminar de la base de datos
            $query = $pdo->prepare("DELETE FROM producto WHERE cod_producto = :codigo");
            $query->bindParam(':codigo', $codigo, PDO::PARAM_INT);
            $query->execute();

            header("Location: view.php?alert=3");
        }
    } catch (PDOException $e) {
        die("Error en la operación con la base de datos: " . $e->getMessage());
    }
}
?>
