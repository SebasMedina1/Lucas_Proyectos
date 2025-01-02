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

        // Insertar un nuevo pedido con sus detalles
        if ($action == 'insert' && isset($_POST['Guardar'])) {
            
            $pedido_id = $_POST['codigo']; // ID del pedido
            $usuario_id = $_SESSION['id_usuario']; // Asegúrate de tener el ID del usuario en la sesión
            $detalle = json_decode($_POST['productos'], true); // Datos del detalle en JSON

            $query = $pdo->prepare("SELECT sucursal_id FROM usuarios WHERE id_usuario = :id_usuario");
            $query->bindParam(':id_usuario', $usuario_id, PDO::PARAM_INT);
            $query->execute();
        
            // Obtener los datos del usuario autenticado
            $id_sucursal = $query->fetchColumn();
            
            // Iniciar una transacción
            $pdo->beginTransaction();

            // Insertar el pedido
            $query_pedido = $pdo->prepare("INSERT INTO pedidos_compras (pedido_id, pedido_fecha, estado, id_usuario, pedido_hora,sucursal_id) 
                                           VALUES (:pedido_id, CURRENT_DATE, 'PENDIENTE', :usuario_id, CURRENT_TIME(0), :id_sucursal)");
            $query_pedido->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
            $query_pedido->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            $query_pedido->bindParam(':id_sucursal', $id_sucursal, PDO::PARAM_INT);
            $query_pedido->execute();

            // Insertar los detalles del pedido
            $query_detalle = $pdo->prepare("INSERT INTO detalle_pedidos (pedido_id, cod_producto, cantidad) 
                                            VALUES (:pedido_id, :cod_producto, :cantidad)");
            foreach ($detalle as $item) {
                $query_detalle->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
                $query_detalle->bindParam(':cod_producto', $item['codigo'], PDO::PARAM_INT);
                $query_detalle->bindParam(':cantidad', $item['cantidad'], PDO::PARAM_INT);
                $query_detalle->execute();
            }

            // Confirmar la transacción
            $pdo->commit();

            // Redirigir con un mensaje de éxito
            header("Location: view.php?alert=1");
            
        }
        
        // Eliminar pedido
        elseif ($action == 'anular' && isset($_GET['ped_id'])) {
            
            $pedido_id = $_GET['ped_id'];

            // Iniciar una transacción
            $pdo->beginTransaction();

            // Eliminar los detalles del pedido
            $query_detalle = $pdo->prepare("UPDATE pedidos_compras SET estado='ANULADO' WHERE pedido_id = :pedido_id");
            $query_detalle->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
            $query_detalle->execute();

            // Confirmar la transacción
            $pdo->commit();

            // Redirigir con un mensaje de éxito
            header("Location: view.php?alert=3");
        }
    } catch (PDOException $e) {
        // Si ocurre un error, deshacer la transacción
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error en la operación con la base de datos: " . $e->getMessage());
    }
}
?>
