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

if ($_GET['act'] == 'insert') {
    try {
        require "../../config/database.php";
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Recuperar datos del formulario
        $fact_id = $_POST['codigo'];
        $fact_nro = $_POST['numero_factura'];
        $fact_timbrado = $_POST['timbrado'];
        $fact_inicio = $_POST['vigencia_desde'];
        $fact_vencimiento = $_POST['vigencia_hasta'];
        $fact_fecha = $_POST['fecha'];
        $fact_total = (float)$_POST['total_importe'];
        $fact_estado = 'FINALIZADO';
        $condicion_pago = $_POST['condicion_pago'];
        $orden_id = (int)$_POST['presupuesto'];
        $fact_hora = $_POST['hora'];
        $id_usuario = $_SESSION['id_usuario'];

        // Recuperar datos relacionados con la orden de compra
        $query_orden = $pdo->prepare("SELECT * FROM orden_compras WHERE orden_id = :orden_id");
        $query_orden->execute(['orden_id' => $orden_id]);
        $orden = $query_orden->fetch(PDO::FETCH_ASSOC);

        if (!$orden) {
            throw new Exception("La orden de compra no existe.");
        }

        $cod_proveedor = $orden['cod_proveedor'];

        // Verificar si el número de factura ya existe
        $query = $pdo->prepare("SELECT COUNT(*) FROM facturas_compra WHERE fact_nro = :fact_nro");
        $query->bindParam(':fact_nro', $fact_nro);
        $query->execute();
        $existe = $query->fetchColumn();

        if ($existe > 0) {
            // Mostrar mensaje de error si la factura ya existe
            echo "<script>
                    alert('El número de factura ya existe. Por favor, ingrese un número diferente.');
                    window.location.href = 'view.php?gestionar_compras=add&form=add&act=insert';
                </script>";
            exit;
        }

        // Verificar si el número de timbrado ya existe
        $query = $pdo->prepare("SELECT COUNT(*) FROM facturas_compra WHERE fact_timbrado = :fact_timbrado");
        $query->bindParam(':fact_timbrado', $fact_timbrado);
        $query->execute();
        $existe = $query->fetchColumn();

        if ($existe > 0) {
            // Mostrar mensaje de error si la factura ya existe
            echo "<script>
                    alert('El número de timbrado ya existe. Por favor, ingrese un número diferente.');
                    window.location.href = 'view.php?gestionar_compras=add&form=add&act=insert';
                </script>";
            exit;
        }

        // Insertar en facturas_compra
        $query_factura = $pdo->prepare("
            INSERT INTO facturas_compra (
                fact_id, fact_nro, fact_timbrado, fact_vencimiento, fact_inicio, fact_fecha, fact_total,
                fact_estado, id_usuario, cod_proveedor, orden_id, fact_hora, condicion_pago
            ) 
            VALUES (
                :fact_id, :fact_nro, :fact_timbrado, :fact_vencimiento, :fact_inicio, :fact_fecha, :fact_total,
                :fact_estado, :id_usuario, :cod_proveedor, :orden_id, :fact_hora, :condicion_pago
            )
        ");
        $query_factura->execute([
            'fact_id' => $fact_id,
            'fact_nro' => $fact_nro,
            'fact_timbrado' => $fact_timbrado,
            'fact_vencimiento' => $fact_vencimiento,
            'fact_inicio' => $fact_inicio,
            'fact_fecha' => $fact_fecha,
            'fact_total' => $fact_total,
            'fact_estado' => $fact_estado,
            'id_usuario' => $id_usuario,
            'cod_proveedor' => $cod_proveedor,
            'orden_id' => $orden_id,
            'fact_hora' => $fact_hora,
            'condicion_pago' => $condicion_pago
            
        ]);


        // Recuperar productos del formulario (JSON)
        $productos = isset($_POST['productos']) ? json_decode($_POST['productos'], true) : [];

        // Inicializar variables de IVA
        $iva_5 = 0;
        $iva_10 = 0;
        $iva_exento = 0;

        foreach ($productos as $producto) {
            // Insertar en la tabla facturas_detalle_compra
            $query_detalle = $pdo->prepare("
                INSERT INTO facturas_detalle_compra (fact_id, cod_producto, fact_iva, fact_cantidad, fact_precio)
                VALUES (:fact_id, :cod_producto, :fact_iva, :fact_cantidad, :fact_precio)
            ");
            $query_detalle->execute([
                'fact_id' => $fact_id,
                'cod_producto' => $producto['codigo'],
                'fact_iva' => $producto['iva'],
                'fact_cantidad' => $producto['cantidad'],
                'fact_precio' => $producto['precio'],
            ]);
        
            echo "Detalle insertado: " . json_encode($producto) . "<br>";
        
            // Calcular el subtotal y el IVA para cada producto
            $subtotal = $producto['cantidad'] * $producto['precio'];
            $iva_unitario = 0;
            $iva_total_producto = 0;
        
            if ($producto['iva'] == 5) {
                $iva_unitario = intval($producto['precio'] / 21); // Cálculo para IVA 5%
                $iva_total_producto = $producto['cantidad'] * $iva_unitario;
                $iva_5 += $iva_total_producto;
            } elseif ($producto['iva'] == 10) {
                $iva_unitario = intval($producto['precio'] / 11); // Cálculo para IVA 10%
                $iva_total_producto = $producto['cantidad'] * $iva_unitario;
                $iva_10 += $iva_total_producto;
            } else {
                $iva_exento += $subtotal; // Si no tiene IVA, se considera exento
            }
        
            // Obtener el código del depósito asociado al producto
            $query_deposito = $pdo->prepare("
                SELECT cod_deposito 
                FROM producto 
                WHERE cod_producto = :cod_producto
            ");
            $query_deposito->execute(['cod_producto' => $producto['codigo']]);
            $result_deposito = $query_deposito->fetch(PDO::FETCH_ASSOC);
        
            if ($result_deposito && isset($result_deposito['cod_deposito'])) {
                $deposito_id = $result_deposito['cod_deposito'];
        
                // Actualizar el stock en el depósito correspondiente
                $query_stock = $pdo->prepare("
                    UPDATE stock
                    SET stock_existencia = stock_existencia + :cantidad
                    WHERE cod_producto = :cod_producto AND cod_deposito = :cod_deposito
                ");
                $query_stock->execute([
                    'cantidad' => $producto['cantidad'],
                    'cod_producto' => $producto['codigo'],
                    'cod_deposito' => $deposito_id,
                ]);
            }
        }
        
        
        // Obtener el último iva_id de la tabla iva_compras
        $query_last_iva = $pdo->query("SELECT MAX(iva_id) AS last_id FROM iva_compras");
        $last_iva = $query_last_iva->fetch(PDO::FETCH_ASSOC);
        $new_iva_id = ($last_iva['last_id'] !== null) ? $last_iva['last_id'] + 1 : 1;
        
        // Insertar en la tabla iva_compras
        $query_iva = $pdo->prepare("
            INSERT INTO iva_compras (iva_id, iva_5, iva_10, iva_exento, iva_fecha, fact_id)
            VALUES (:iva_id, :iva_5, :iva_10, :iva_exento, :iva_fecha, :fact_id)
        ");
        $query_iva->execute([
            'iva_id' => $new_iva_id,
            'iva_5' => $iva_5,
            'iva_10' => $iva_10,
            'iva_exento' => $iva_exento,
            'iva_fecha' => $fact_fecha,
            'fact_id' => $fact_id,
        ]);
        
        
        // Obtener el último cta_id de la tabla cuenta_pagar
        $query_last_cta = $pdo->query("SELECT MAX(cta_id) AS last_id FROM cuenta_pagar");
        $last_cta = $query_last_cta->fetch(PDO::FETCH_ASSOC);
        $new_cta_id = ($last_cta['last_id'] !== null) ? $last_cta['last_id'] + 1 : 1;
        
        // Insertar en la tabla cuenta_pagar
        $query_cuenta = $pdo->prepare("
            INSERT INTO cuenta_pagar (cta_id, fact_id, cta_total, estado)
            VALUES (:cta_id, :fact_id, :cta_total, 'FINALIZADO')
        ");
        $query_cuenta->execute([
            'cta_id' => $new_cta_id,
            'fact_id' => $fact_id,
            'cta_total' => $fact_total,
        ]);
        

        // Actualizar estados
        $query_update_orden = $pdo->prepare("UPDATE orden_compras SET orden_estado = 'FINALIZADO' WHERE orden_id = :orden_id");
        $query_update_orden->execute(['orden_id' => $orden_id]);
        // Actualizar el estado del presupuesto y pedido asociados
        $query_update_presupuesto = $pdo->prepare("
            UPDATE presupuesto_compra
            SET pre_estado = 'FINALIZADO'
            WHERE presupuesto_id = (SELECT presupuesto_id FROM orden_compras WHERE orden_id = :orden_id LIMIT 1)
        ");
        $query_update_presupuesto->execute(['orden_id' => $orden_id]);

        $query_update_pedido = $pdo->prepare("
            UPDATE pedidos_compras
            SET estado = 'FINALIZADO'
            WHERE pedido_id = (SELECT pc.pedido_id FROM presupuesto_compra pc
                            JOIN orden_compras oc ON pc.presupuesto_id = oc.presupuesto_id
                            WHERE oc.orden_id = :orden_id LIMIT 1)
        ");
        $query_update_pedido->execute(['orden_id' => $orden_id]);

        // Redirigir con mensaje de éxito
        header("Location: view.php?alert=1");
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
if ($_GET['act'] == 'anular') {
    try {
        require "../../config/database.php";
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Obtener el ID de la factura desde la URL
        $fact_id = $_GET['fact_id'];

        // Verificar el estado de la factura de compra
        $stmtFactura = $pdo->prepare("SELECT fact_estado FROM facturas_compra WHERE fact_id = :fact_id");
        $stmtFactura->execute([':fact_id' => $fact_id]);
        $factura = $stmtFactura->fetch(PDO::FETCH_ASSOC);

        // Redirigir si la factura está anulada
        if (!$factura || $factura['fact_estado'] === 'ANULADA') {
            header("Location: view.php?alert=5"); // Alertar al usuario que la factura está anulada
            exit;
        }

        // Validar si la factura existe y obtener datos relacionados
        $query_factura = $pdo->prepare("
            SELECT fc.fact_id, fc.orden_id
            FROM facturas_compra fc
            WHERE fc.fact_id = :fact_id
        ");
        $query_factura->execute(['fact_id' => $fact_id]);
        $factura = $query_factura->fetch(PDO::FETCH_ASSOC);

        if (!$factura) {
            throw new Exception("Factura no encontrada.");
        }



        echo "Factura encontrada: " . json_encode($factura) . "<br>";

        $fact_remision = $factura['fact_remision'];
        $orden_id = $factura['orden_id'];

        // Actualizar el estado de la factura a "ANULADA"
        $query_anular_factura = $pdo->prepare("UPDATE facturas_compra SET fact_estado = 'ANULADA' WHERE fact_id = :fact_id");
        $query_anular_factura->execute(['fact_id' => $fact_id]);

        echo "Estado de la factura actualizado a 'ANULADA'<br>";

        // Revertir el impacto en el stock 
            echo "La factura tiene nota de remisión. Procesando ajuste de stock...<br>";
            $query_detalle = $pdo->prepare("
                SELECT fdc.cod_producto, fdc.fact_cantidad
                FROM facturas_detalle_compra fdc
                WHERE fdc.fact_id = :fact_id
            ");
            $query_detalle->execute(['fact_id' => $fact_id]);
            $detalles = $query_detalle->fetchAll(PDO::FETCH_ASSOC);

            foreach ($detalles as $detalle) {
                echo "Procesando ajuste de stock para material: " . json_encode($detalle) . "<br>";

                // Obtener el depósito asociado al producto
                $query_deposito = $pdo->prepare("
                    SELECT cod_deposito
                    FROM producto
                    WHERE cod_producto = :cod_producto
                ");
                $query_deposito->execute(['cod_producto' => $detalle['cod_producto']]);
                $deposito = $query_deposito->fetch(PDO::FETCH_ASSOC);

                if ($deposito) {
                    $query_revert_stock = $pdo->prepare("
                        UPDATE stock
                        SET stock_existencia = stock_existencia - :cantidad
                        WHERE cod_producto = :cod_producto AND cod_deposito = :cod_deposito
                    ");
                    $query_revert_stock->execute([
                        'cantidad' => $detalle['fact_cantidad'],
                        'cod_producto' => $detalle['cod_producto'],
                        'cod_deposito' => $deposito['cod_deposito'],
                    ]);
                    echo "Stock ajustado para el producto ID: " . $detalle['cod_producto'] . "<br>";
                } else {
                    echo "No se encontró depósito asociado para el producto ID: " . $detalle['cod_producto'] . "<br>";
                }
            }

        // Actualizar el estado de la cuenta a pagar a "ANULADA"
        $query_anular_cuenta = $pdo->prepare("UPDATE cuenta_pagar SET estado = 'ANULADA' WHERE fact_id = :fact_id");
        $query_anular_cuenta->execute(['fact_id' => $fact_id]);
        

        // Actualizar el estado de la orden de compra a "PENDIENTE"
        $query_update_orden = $pdo->prepare("UPDATE orden_compras SET orden_estado = 'PENDIENTE' WHERE orden_id = :orden_id");
        $query_update_orden->execute(['orden_id' => $orden_id]);

        echo "Estado de la orden de compra actualizado a 'PENDIENTE'<br>";

        // Actualizar el estado del presupuesto a "PROCESADO"
        $query_update_presupuesto = $pdo->prepare("
            UPDATE presupuesto_compra
            SET pre_estado = 'PROCESADO'
            WHERE presupuesto_id = (SELECT presupuesto_id FROM orden_compras WHERE orden_id = :orden_id LIMIT 1)
        ");
        $query_update_presupuesto->execute(['orden_id' => $orden_id]);

        echo "Estado del presupuesto actualizado a 'PROCESADO'<br>";

        // Actualizar el estado del pedido a "PROCESADO"
        $query_update_pedido = $pdo->prepare("
            UPDATE pedidos_compras
            SET estado = 'PROCESADO'
            WHERE pedido_id = (SELECT pc.pedido_id FROM presupuesto_compra pc
                            JOIN orden_compras oc ON pc.presupuesto_id = oc.presupuesto_id
                            WHERE oc.orden_id = :orden_id LIMIT 1)
        ");
        $query_update_pedido->execute(['orden_id' => $orden_id]);

        echo "Estado del pedido actualizado a 'PROCESADO'<br>";

        // Redirigir con mensaje de éxito
        header("Location: view.php?alert=3");
    } catch (Exception $e) {
        echo "Error durante la anulación: " . $e->getMessage();
    }
}
?>
