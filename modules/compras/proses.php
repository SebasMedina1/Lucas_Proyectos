<?php
session_start();
require_once '../../config/database.php';

if (empty($_SESSION['username']) && empty($_SESSION['password'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            setTimeout(function() {
                window.location.href = '../../login.html';
            }, 1500);
          </script>";
    exit();
} else {
    if ($_GET['act'] == 'insert') {
        if ($_POST) {
            $codigo = $_POST['codigo'];
            $codigo_deposito = $_POST['codigo_deposito'];
            $codigo_proveedor = $_POST['codigo_proveedor'];
            $fecha = $_POST['fecha'];
            $hora = $_POST['hora'];
            $estado = 'activo';
            $productos = json_decode($_POST['productos'], true); // Decodificar JSON

            if (!$productos || count($productos) === 0) {
                die('Error: No se enviaron productos para registrar.');
            }

            // **Obtener el último número de factura**
            $query_factura = mysqli_query($mysqli, "SELECT MAX(nro_factura) as ultimo FROM compra");
            if ($row_factura = mysqli_fetch_assoc($query_factura)) {
                $nro_factura = $row_factura['ultimo'] + 1; // Incrementar el último número
            } else {
                $nro_factura = 1; // Valor inicial si no hay facturas
            }

            // **Paso 1: Insertar en la tabla `compra`**
            $sql_compra = "INSERT INTO compra (cod_compra, cod_proveedor, nro_factura, fecha, estado, cod_deposito, hora, total_compra, id_user)
                VALUES ($codigo, $codigo_proveedor, '$nro_factura', '$fecha', '$estado', $codigo_deposito, '$hora', 0, 2)";
            $query_compra = mysqli_query($mysqli, $sql_compra) or die('Error al insertar la cabecera de compra: ' . mysqli_error($mysqli));

            if ($query_compra) {
                $total_compra = 0;

                // **Paso 2: Insertar productos en la tabla `detalle_compra` y actualizar stock**
                foreach ($productos as $producto) {
                    $codigo_producto = $producto['codigo'];
                    $cantidad = $producto['cantidad'];
                    $precio = $producto['precio'];

                    $total_producto = $cantidad * $precio;
                    $total_compra += $total_producto;

                    // Insertar en `detalle_compra`
                    $sql_detalle = "INSERT INTO detalle_compra (cod_producto, cod_compra, cod_deposito, cantidad, precio) 
                        VALUES ($codigo_producto, $codigo, $codigo_deposito, $cantidad, $precio)";
                    mysqli_query($mysqli, $sql_detalle) or die('Error al insertar detalle de compra: ' . mysqli_error($mysqli));

                    // Actualizar stock
                    $query_stock = mysqli_query($mysqli, "SELECT * FROM stock WHERE cod_producto=$codigo_producto AND cod_deposito=$codigo_deposito");
                    if (mysqli_num_rows($query_stock) == 0) {
                        $sql_stock = "INSERT INTO stock (cod_deposito, cod_producto, cantidad) 
                            VALUES ($codigo_deposito, $codigo_producto, $cantidad)";
                        mysqli_query($mysqli, $sql_stock) or die('Error al insertar stock: ' . mysqli_error($mysqli));
                    } else {
                        $sql_update_stock = "UPDATE stock SET cantidad = cantidad + $cantidad 
                            WHERE cod_producto=$codigo_producto AND cod_deposito=$codigo_deposito";
                        mysqli_query($mysqli, $sql_update_stock) or die('Error al actualizar stock: ' . mysqli_error($mysqli));
                    }
                }

                // **Paso 3: Actualizar el total de la compra**
                $sql_update_compra = "UPDATE compra SET total_compra = $total_compra WHERE cod_compra = $codigo";
                mysqli_query($mysqli, $sql_update_compra) or die('Error al actualizar el total de la compra: ' . mysqli_error($mysqli));

                

                // Redireccionar si todo es exitoso
                header("Location: view.php?alert=1");
            } else {
                header("Location: ../../main.php?module=compras&alert=3");
            }
        }
    } elseif ($_GET['act'] == 'anular') {
        if (isset($_GET['cod_compra'])) {
            $codigo = $_GET['cod_compra'];

            // Anular la cabecera de compra
            $sql_anular_compra = "UPDATE compra SET estado='ANULADO' WHERE cod_compra=$codigo";
            mysqli_query($mysqli, $sql_anular_compra) or die('Error al anular la compra: ' . mysqli_error($mysqli));

            // Revertir el stock de los productos
            $sql_detalles = "SELECT * FROM detalle_compra WHERE cod_compra=$codigo";
            $result_detalles = mysqli_query($mysqli, $sql_detalles);
            while ($detalle = mysqli_fetch_assoc($result_detalles)) {
                $codigo_producto = $detalle['cod_producto'];
                $codigo_deposito = $detalle['cod_deposito'];
                $cantidad = $detalle['cantidad'];

                $sql_revertir_stock = "UPDATE stock SET cantidad = cantidad - $cantidad 
                    WHERE cod_producto=$codigo_producto AND cod_deposito=$codigo_deposito";
                mysqli_query($mysqli, $sql_revertir_stock) or die('Error al revertir stock: ' . mysqli_error($mysqli));
            }

            // Eliminar los detalles de la compra
            //$sql_eliminar_detalles = "DELETE FROM detalle_compra WHERE cod_compra=$codigo";
           // mysqli_query($mysqli, $sql_eliminar_detalles) or die('Error al eliminar los detalles de la compra: ' . mysqli_error($mysqli));

            header("Location: view.php?alert=2");
        }
    }
}
