<?php
session_start();

require "../../config/database.php"; // Conexión a la base de datos

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

        // Insertar un nuevo presupuesto con sus detalles
        if ($action == 'insert' && isset($_POST['Guardar'])) {
            
            $presupuesto_id = $_POST['codigo'];
            $pedido_id = $_POST['pedido'];
            $cod_proveedor = $_POST['proveedor'];
            $detalle = json_decode($_POST['productos'], true);
            $total_importe = $_POST['total_importe'];
            $usuario_id = $_SESSION['id_usuario'];
    
            // Validar detalle del presupuesto
            if (empty($detalle)) {
                throw new Exception("El detalle del presupuesto está vacío o malformado.");
            }
    
            // Obtener sucursal
            $query = $pdo->prepare("SELECT sucursal_id FROM usuarios WHERE id_usuario = :id_usuario");
            $query->bindParam(':id_usuario', $usuario_id, PDO::PARAM_INT);
            $query->execute();
            $id_sucursal = $query->fetchColumn();
    
            if (!$id_sucursal) {
                throw new Exception("El usuario no tiene una sucursal asignada.");
            }
    
            // Iniciar transacción
            $pdo->beginTransaction();
    
            // Insertar presupuesto
            $query_presupuesto = $pdo->prepare("
                INSERT INTO presupuesto_compra 
                (presupuesto_id, pre_fecha, pre_hora, pre_estado, cod_proveedor, pedido_id, id_usuario, total_importe) 
                VALUES (:presupuesto_id, CURRENT_DATE, CURRENT_TIME(0), 'PENDIENTE', :cod_proveedor, :pedido_id, :id_usuario, :total_importe)
            ");
            $query_presupuesto->bindParam(':presupuesto_id', $presupuesto_id, PDO::PARAM_INT);
            $query_presupuesto->bindParam(':cod_proveedor', $cod_proveedor, PDO::PARAM_INT);
            $query_presupuesto->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
            $query_presupuesto->bindParam(':id_usuario', $usuario_id, PDO::PARAM_INT);
            $query_presupuesto->bindParam(':total_importe', $total_importe, PDO::PARAM_STR);
            $query_presupuesto->execute();
    
            // Insertar detalles del presupuesto
            $query_detalle = $pdo->prepare("
                INSERT INTO presupuesto_detalle_compra (presupuesto_id, cod_producto, pre_cantidad, pre_precio) 
                VALUES (:presupuesto_id, :cod_producto, :cantidad, :precio)
            ");
    
            foreach ($detalle as $item) {
                if (!isset($item['codigo'], $item['cantidad'], $item['precio'])) {
                    throw new Exception("Falta información en un elemento del detalle: " . json_encode($item));
                }
    
                $query_detalle->bindParam(':presupuesto_id', $presupuesto_id, PDO::PARAM_INT);
                $query_detalle->bindParam(':cod_producto', $item['codigo'], PDO::PARAM_INT);
                $query_detalle->bindParam(':cantidad', $item['cantidad'], PDO::PARAM_INT);
                $query_detalle->bindParam(':precio', $item['precio'], PDO::PARAM_STR);
                $query_detalle->execute();
            }
    
            // Actualizar estado del pedido
            $query_update_pedido = $pdo->prepare("
                UPDATE pedidos_compras SET estado = 'PROCESADO' WHERE pedido_id = :pedido_id
            ");
            $query_update_pedido->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
            $query_update_pedido->execute();
    
            // Confirmar transacción
            $pdo->commit();
    
            // Redirigir con un mensaje de éxito
            header("Location: view.php?alert=1");
            exit();
        }
        
        // Anular presupuesto
        elseif ($action == 'anular' && isset($_GET['pre_id'])) {
            
            $presupuesto_id = $_GET['pre_id'];

            // Iniciar una transacción
            $pdo->beginTransaction();

            // Cambiar estado del presupuesto a 'ANULADO'
            $query_anular_presupuesto = $pdo->prepare("
                UPDATE presupuesto_compra SET pre_estado = 'ANULADO' WHERE presupuesto_id = :presupuesto_id
            ");
            $query_anular_presupuesto->bindParam(':presupuesto_id', $presupuesto_id, PDO::PARAM_INT);
            $query_anular_presupuesto->execute();

            // Recuperar pedido asociado
            $query_pedido = $pdo->prepare("
                SELECT pedido_id FROM presupuesto_compra WHERE presupuesto_id = :presupuesto_id
            ");
            $query_pedido->bindParam(':presupuesto_id', $presupuesto_id, PDO::PARAM_INT);
            $query_pedido->execute();
            $pedido_id = $query_pedido->fetchColumn();

            if (!$pedido_id) {
                throw new Exception("No se encontró el pedido asociado al presupuesto.");
            }

            // Cambiar estado del pedido a 'PENDIENTE'
            $query_anular_pedido = $pdo->prepare("
                UPDATE pedidos_compras SET estado = 'PENDIENTE' WHERE pedido_id = :pedido_id
            ");
            $query_anular_pedido->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
            $query_anular_pedido->execute();

            // Confirmar la transacción
            $pdo->commit();

            // Redirigir con un mensaje de éxito
            header("Location: view.php?alert=3");
            exit();
        }
    } catch (Exception $e) {
        // Si ocurre un error, deshacer la transacción
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error en la operación con la base de datos: " . $e->getMessage());
    }
}
?>
