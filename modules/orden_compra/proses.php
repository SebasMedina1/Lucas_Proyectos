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

        // Insertar orden de compra y actualizar estado del presupuesto
        if ($action == 'insert' && isset($_POST['Guardar'])) {
            // Validar datos del formulario
            $orden_id = $_POST['codigo'] ?? null; // ID de la orden proporcionado por el formulario
            $presupuesto_id = $_POST['presupuesto'] ?? null; // ID del presupuesto seleccionado
            $usuario_id = $_SESSION['id_usuario']; // ID del usuario de la sesión
            $detalle = json_decode($_POST['productos'], true); // Detalle en JSON
            $total_importe = $_POST['total_importe']; // Total importe

            if (empty($orden_id)) {
                throw new Exception("El campo 'orden_id' no está definido o es nulo.");
            }
            if (empty($presupuesto_id)) {
                throw new Exception("El campo 'presupuesto' no está definido o es nulo.");
            }
            if (empty($detalle)) {
                throw new Exception("El detalle del presupuesto está vacío o malformado.");
            }

            // Consultar el proveedor asociado al presupuesto
            $query_proveedor = $pdo->prepare("SELECT cod_proveedor FROM presupuesto_compra WHERE presupuesto_id = :presupuesto_id");
            $query_proveedor->bindParam(':presupuesto_id', $presupuesto_id, PDO::PARAM_INT);
            $query_proveedor->execute();
            $proveedor_id = $query_proveedor->fetchColumn();

            if (!$proveedor_id) {
                throw new Exception("No se encontró un proveedor asociado al presupuesto.");
            }

            // Iniciar una transacción
            $pdo->beginTransaction();

            // Insertar en la tabla `orden_compra`
            $query_orden = $pdo->prepare("
                INSERT INTO orden_compras (orden_id, orden_fecha, orden_hora, orden_estado, orden_total, presupuesto_id, cod_proveedor, id_usuario) 
                VALUES (:orden_id, CURRENT_DATE, CURRENT_TIME(0), 'PENDIENTE',  :total_importe, :presupuesto_id, :proveedor_id, :usuario_id)
            ");
            $query_orden->bindParam(':orden_id', $orden_id, PDO::PARAM_INT);
            $query_orden->bindParam(':total_importe', $total_importe, PDO::PARAM_STR);
            $query_orden->bindParam(':presupuesto_id', $presupuesto_id, PDO::PARAM_INT);
            $query_orden->bindParam(':proveedor_id', $proveedor_id, PDO::PARAM_INT);
            $query_orden->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            
            $query_orden->execute();

            // Insertar los detalles en `orden_detalle_compra`
            $query_detalle = $pdo->prepare("
                INSERT INTO orden_detalle_compras (orden_id, cod_producto, orden_precio, orden_iva, orden_cantidad) 
                VALUES (:orden_id, :cod_producto, :precio, :orden_iva, :cantidad)
            ");
            foreach ($detalle as $item) {
                if (!isset($item['codigo'], $item['cantidad'], $item['precio'])) {
                    throw new Exception("Falta información en un elemento del detalle: " . json_encode($item));
                }

                $query_detalle->bindParam(':orden_id', $orden_id, PDO::PARAM_INT);
                $query_detalle->bindParam(':cod_producto', $item['codigo'], PDO::PARAM_INT);
                $query_detalle->bindParam(':cantidad', $item['cantidad'], PDO::PARAM_INT);
                $query_detalle->bindParam(':orden_iva', $item['iva'], PDO::PARAM_STR);
                $query_detalle->bindParam(':precio', $item['precio'], PDO::PARAM_STR);
                
                $query_detalle->execute();
            }

            // Actualizar estado del presupuesto a `PROCESADO`
            $query_update_presupuesto = $pdo->prepare("
                UPDATE presupuesto_compra SET pre_estado = 'PROCESADO' WHERE presupuesto_id = :presupuesto_id
            ");
            $query_update_presupuesto->bindParam(':presupuesto_id', $presupuesto_id, PDO::PARAM_INT);
            $query_update_presupuesto->execute();

            // Confirmar la transacción
            $pdo->commit();

            // Redirigir con un mensaje de éxito
            header("Location: view.php?alert=1");
            exit();
        }

        // Anular orden
        if ($action === 'anular' && isset($_GET['orden_id'])) {
            $ordenId = $_GET['orden_id'];

            // Iniciar una transacción
            $pdo->beginTransaction();

            // Actualizar estado de la orden a "ANULADO"
            $updateOrden = $pdo->prepare("UPDATE orden_compras SET orden_estado = 'ANULADO' WHERE orden_id = :orden_id");
            $updateOrden->bindParam(':orden_id', $ordenId, PDO::PARAM_INT);
            $updateOrden->execute();

            // Verificar si la orden está asociada a un presupuesto
            $queryPresupuesto = $pdo->prepare("SELECT presupuesto_id FROM orden_compras WHERE orden_id = :orden_id");
            $queryPresupuesto->bindParam(':orden_id', $ordenId, PDO::PARAM_INT);
            $queryPresupuesto->execute();
            $preId = $queryPresupuesto->fetchColumn();

            if ($preId) {
                // Cambiar el estado del presupuesto a "ANULADO"
                $updatePresupuesto = $pdo->prepare("UPDATE presupuesto_compra SET pre_estado = 'ANULADO' WHERE presupuesto_id = :presupuesto_id");
                $updatePresupuesto->bindParam(':presupuesto_id', $preId, PDO::PARAM_INT);
                $updatePresupuesto->execute();

                // Obtener el pedido asociado al presupuesto
                $queryPedido = $pdo->prepare("SELECT pedido_id FROM presupuesto_compra WHERE presupuesto_id = :presupuesto_id");
                $queryPedido->bindParam(':presupuesto_id', $preId, PDO::PARAM_INT);
                $queryPedido->execute();
                $pedidoId = $queryPedido->fetchColumn();

                if ($pedidoId) {
                    // Cambiar el estado del pedido a "PENDIENTE"
                    $updatePedido = $pdo->prepare("UPDATE pedidos_compras SET estado = 'PENDIENTE' WHERE pedido_id = :pedido_id");
                    $updatePedido->bindParam(':pedido_id', $pedidoId, PDO::PARAM_INT);
                    $updatePedido->execute();
                }
            }

            // Confirmar la transacción
            $pdo->commit();

            // Redirigir con mensaje de éxito
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
