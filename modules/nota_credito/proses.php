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

if ($_GET['act'] == 'insert_nota_credito') {
    try {
        require "../../config/database.php";
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Recuperar datos del formulario
        $nota_id = $_POST['nota_id'];
        $nota_fecha = $_POST['nota_fecha'];
        $nota_nro = $_POST['nota_nro'];
        $nota_timbrado = $_POST['nota_timbrado'];
        $nota_inicio = $_POST['nota_inicio'];
        $nota_vto = $_POST['nota_vto'];
        $nota_total = (float)$_POST['nota_total'];
        $motivo_id = $_POST['motivo_id'];
        $fact_id = $_POST['fact_id'];
        $detalles = json_decode($_POST['productos'], true);
        $id_usuario = $_SESSION['id_usuario'];

        $nota_hora = $_POST['hora'];

        

        // Validar factura
        $query_factura = $pdo->prepare("
            SELECT fact_total, fact_fecha, fact_estado
            FROM facturas_compra
            WHERE fact_id = :fact_id
        ");
        $query_factura->execute(['fact_id' => $fact_id]);
        $factura = $query_factura->fetch(PDO::FETCH_ASSOC);

        if (!$factura) {
            throw new Exception("Factura no encontrada.");
        }

        // Validar monto de la nota de crédito
        if ($nota_total > $factura['fact_total']) {
            throw new Exception("El monto de la nota de crédito no puede ser mayor al monto de la factura.");
        }

        // Validar fechas
        // Extraer el mes y año de las fechas
        $factura_mes = date('Y-m', strtotime($factura['fact_fecha'])); // Año y mes de la factura
        $nota_mes = date('Y-m', strtotime($nota_fecha)); // Año y mes de la nota de crédito

        // Validar que sean del mismo mes y año
        if ($factura_mes !== $nota_mes) {
            throw new Exception("La nota de crédito solo puede emitirse dentro del mismo mes que la factura.");
        }


        // Insertar en notas_compra
        $query_nota = $pdo->prepare("
            INSERT INTO notas_compra (
                nota_id, nota_fecha, nota_nro, nota_hora, nota_timbrado, nota_vto, nota_inicio,
                nota_total, nota_estado, motivo_id, cod_proveedor, id_usuario, fact_id
            ) VALUES (
                :nota_id, :nota_fecha, :nota_nro, :nota_hora, :nota_timbrado, :nota_vto, :nota_inicio, 
                :nota_total, 'PROCESADO', :motivo_id, (SELECT cod_proveedor FROM facturas_compra WHERE fact_id = :fact_id),
                :id_usuario,  :fact_id
            )
        ");
        $query_nota->execute([
            'nota_id' => $nota_id,
            'nota_fecha' => $nota_fecha,
            'nota_nro' => $nota_nro,
            'nota_hora' => $nota_hora,
            'nota_timbrado' => $nota_timbrado,
            'nota_vto' => $nota_vto,
            'nota_inicio' => $nota_inicio,
            'nota_total' => $nota_total,
            'motivo_id' => $motivo_id,
            'id_usuario' => $id_usuario,
            'fact_id' => $fact_id,
            
        ]);

        // Insertar detalles en notas_compra_detalle
        foreach ($detalles as $detalle) {
            $query_detalle = $pdo->prepare("
                INSERT INTO notas_compra_detalle (
                    nota_id, cod_producto, nota_precio, nota_cantidad, nota_iva
                ) VALUES (
                    :nota_id, :cod_producto, :nota_precio, :nota_cantidad, :nota_iva
                )
            ");
            $query_detalle->execute([
                'nota_id' => $nota_id,
                'cod_producto' => $detalle['codigo'],
                'nota_precio' => $detalle['precio'],
                'nota_cantidad' => $detalle['cantidad'],
                'nota_iva' => $detalle['iva'],
            ]);

            // Manejo de cada caso
            switch ($motivo_id) {
                case 1: // Devolución de productos, parcialmente

                    // Obtener los detalles de los productos devueltos
                    $detalles = json_decode($_POST['productos'], true); // Detalles enviados desde el formulario
                
                    if (empty($detalles)) {
                        throw new Exception("No se encontraron detalles de productos para procesar.");
                    }
                
                    $monto_total_devuelto = 0; // Inicializar el monto total devuelto
                    $iva_5_devuelto_total = 0; // Inicializar el total del IVA devuelto al 5%
                    $iva_10_devuelto_total = 0; // Inicializar el total del IVA devuelto al 10%
                
                    foreach ($detalles as $detalle) {
                        // Actualizar el stock del producto devuelto
                        $query_stock = $pdo->prepare("
                            UPDATE stock
                            SET stock_existencia = GREATEST(stock_existencia - :cantidad, 0)
                            WHERE cod_producto = :cod_producto
                        ");
                        $query_stock->execute([
                            'cantidad' => $detalle['cantidad'],
                            'cod_producto' => $detalle['codigo'],
                        ]);
                
                        // Calcular el monto devuelto para este producto
                        $monto_devuelto = $detalle['cantidad'] * $detalle['precio'];
                        $monto_total_devuelto += $monto_devuelto; // Acumular el monto total devuelto
                
                        // Obtener el iva_id del producto
                        $query_producto = $pdo->prepare("
                            SELECT iva_id
                            FROM producto
                            WHERE cod_producto = :cod_producto
                        ");
                        $query_producto->execute(['cod_producto' => $detalle['codigo']]);
                        $producto_iva_id = $query_producto->fetchColumn();
                
                        // Calcular el IVA unitario y el IVA total devuelto para este producto
                        $iva_unitario = 0;
                        $iva_total_producto = 0;
                        if ($producto_iva_id == 1) { // IVA 5%
                            $iva_unitario = intval($detalle['precio'] / 21);
                            $iva_total_producto = intval($detalle['cantidad'] * $iva_unitario);
                            $iva_5_devuelto_total += $iva_total_producto; // Acumular al total de IVA 5% devuelto
                        } elseif ($producto_iva_id == 2) { // IVA 10%
                            $iva_unitario = intval($detalle['precio'] / 11);
                            $iva_total_producto = intval($detalle['cantidad'] * $iva_unitario);
                            $iva_10_devuelto_total += $iva_total_producto; // Acumular al total de IVA 10% devuelto
                        }
                
                        // Actualizar fact_iva y cantidad en facturas_detalle_compra
                        $query_detalle_update = $pdo->prepare("
                            UPDATE facturas_detalle_compra
                            SET 
                                fact_iva = :iva_unitario,
                                fact_cantidad = GREATEST(fact_cantidad - :cantidad, 0)
                            WHERE fact_id = :fact_id AND cod_producto = :cod_producto
                        ");
                        $query_detalle_update->execute([
                            'iva_unitario' => $iva_unitario,
                            'cantidad' => $detalle['cantidad'],
                            'fact_id' => $fact_id,
                            'cod_producto' => $detalle['codigo'],
                        ]);
                    }
                
                    // **Actualizar el total del IVA en iva_compras**
                    $query_iva_update = $pdo->prepare("
                        UPDATE iva_compras
                        SET 
                            iva_5 = GREATEST(iva_5 - :iva_5_devuelto, 0),
                            iva_10 = GREATEST(iva_10 - :iva_10_devuelto, 0)
                        WHERE fact_id = :fact_id
                    ");
                    $query_iva_update->execute([
                        'iva_5_devuelto' => $iva_5_devuelto_total,
                        'iva_10_devuelto' => $iva_10_devuelto_total,
                        'fact_id' => $fact_id,
                    ]);
                
                    // **Actualizar fact_total y estado en facturas_compra**
                    $query_factura_update = $pdo->prepare("
                        UPDATE facturas_compra
                        SET 
                            fact_total = GREATEST(fact_total - :monto_total_devuelto, 0),
                            fact_estado = CASE 
                                WHEN fact_total - :monto_total_devuelto = 0 THEN 'ANULADA'
                                ELSE 'FINALIZADO - DEVOLUCIÓN PARCIAL'
                            END
                        WHERE fact_id = :fact_id
                    ");
                    $query_factura_update->execute([
                        'monto_total_devuelto' => $monto_total_devuelto,
                        'fact_id' => $fact_id,
                    ]);


                
                    // Redirigir con mensaje de éxito
                    header("Location: view.php?alert=1");
                    exit();
                
                    break;
                


                case 2: // Descuentos aplicados posteriormente
                    // Obtener el total antes de la actualización
                    $nota_total = intval($_POST['nota_total']); // Total inicial de la nota de crédito (parte entera)
                    $detalles = json_decode($_POST['productos'], true); // Detalles enviados desde el formulario
                
                    if (empty($detalles)) {
                        throw new Exception("No se encontraron detalles de productos para procesar.");
                    }
                
                    $nuevo_total = 0; // Inicializar el nuevo total
                    $iva_5_total = 0; // Inicializar IVA total al 5%
                    $iva_10_total = 0; // Inicializar IVA total al 10%
                
                    foreach ($detalles as $detalle) {
                        // Calcular el subtotal actualizado del producto
                        $subtotal_actualizado = intval($detalle['cantidad']) * intval($detalle['precio']);
                
                        // Obtener el iva_id del producto
                        $query_producto = $pdo->prepare("
                            SELECT iva_id
                            FROM producto
                            WHERE cod_producto = :cod_producto
                        ");
                        $query_producto->execute(['cod_producto' => $detalle['codigo']]);
                        $producto_iva_id = $query_producto->fetchColumn();
                
                        // Calcular el IVA unitario y el IVA total del producto
                        $iva_producto_unitario = 0; // IVA para una sola unidad
                        $iva_producto_total = 0;   // IVA para todas las unidades del producto
                        if ($producto_iva_id == 1) { // IVA 5%
                            $iva_producto_unitario = intval($detalle['precio'] / 21); // IVA unitario (precio de una unidad)
                            $iva_producto_total = intval($subtotal_actualizado / 21); // IVA total (precio * cantidad)
                            $iva_5_total += $iva_producto_total; // Sumar al total de IVA 5%
                        } elseif ($producto_iva_id == 2) { // IVA 10%
                            $iva_producto_unitario = intval($detalle['precio'] / 11); // IVA unitario (precio de una unidad)
                            $iva_producto_total = intval($subtotal_actualizado / 11); // IVA total (precio * cantidad)
                            $iva_10_total += $iva_producto_total; // Sumar al total de IVA 10%
                        }
                
                        // Sumar el nuevo subtotal al total actualizado
                        $nuevo_total += $subtotal_actualizado;
                
                        // **Actualizar el IVA unitario de este producto en facturas_detalle_compra**
                        $query_precio_iva_update = $pdo->prepare("
                            UPDATE facturas_detalle_compra
                            SET 
                                fact_precio = :nuevo_precio, 
                                fact_iva = :iva_producto_unitario
                            WHERE fact_id = :fact_id 
                            AND cod_producto = :cod_producto
                        ");
                        $query_precio_iva_update->execute([
                            'nuevo_precio' => intval($detalle['precio']),
                            'iva_producto_unitario' => $iva_producto_unitario, // IVA unitario del producto
                            'fact_id' => $fact_id,
                            'cod_producto' => $detalle['codigo'],
                        ]);
                    }
                
                    // **Actualizar el IVA total en iva_compras**
                    $query_iva_update = $pdo->prepare("
                        UPDATE iva_compras
                        SET 
                            iva_5 = :iva_5_total,
                            iva_10 = :iva_10_total
                        WHERE fact_id = :fact_id
                    ");
                    $query_iva_update->execute([
                        'iva_5_total' => $iva_5_total,
                        'iva_10_total' => $iva_10_total,
                        'fact_id' => $fact_id,
                    ]);
                
                    // Actualizar el monto total en la tabla cuenta_pagar
                    $query_cta_pagar_update = $pdo->prepare("
                        UPDATE cuenta_pagar
                        SET cta_total = :nuevo_total, estado = 'FINALIZADO - CREDITO'
                        WHERE fact_id = :fact_id
                    ");
                    $query_cta_pagar_update->execute([
                        'nuevo_total' => $nuevo_total,
                        'fact_id' => $fact_id,
                    ]);
                
                    // Actualizar el total en la tabla facturas_compra
                    $query_factura_update = $pdo->prepare("
                        UPDATE facturas_compra
                        SET fact_total = :nuevo_total, fact_estado = 'FINALIZADO - CREDITO'
                        WHERE fact_id = :fact_id
                    ");
                    $query_factura_update->execute([
                        'nuevo_total' => $nuevo_total,
                        'fact_id' => $fact_id,
                    ]);
                
                    // Redirigir con mensaje de éxito
                    header("Location: view.php?alert=1");
                    exit();
                    break;
                
          
                case 5: // devolucion de todos los productos

                    // Actualizar stock si es devolución
                        $query_stock = $pdo->prepare("
                        UPDATE stock
                        SET stock_existencia = GREATEST(stock_existencia - :cantidad, 0)
                        WHERE cod_producto = :cod_producto
                    ");
                    $query_stock->execute([
                        'cantidad' => $detalle['cantidad'],
                        'cod_producto' => $detalle['codigo'],
                    ]);
                
                    // Cambiar estado de factura
                    $query_update_factura = $pdo->prepare("
                        UPDATE facturas_compra
                        SET fact_estado = 'ANULADA'
                        WHERE fact_id = :fact_id
                    ");
                    $query_update_factura->execute(['fact_id' => $fact_id]);  
                    
                    
                    // Reestructurar cuenta a pagar en caso de devolución completa
                    $query_cta_pagar_update = $pdo->prepare("
                        UPDATE cuenta_pagar
                        SET estado = 'ANULADA'
                        WHERE fact_id = :fact_id
                    ");
                    $query_cta_pagar_update->execute([
                        'fact_id' => $fact_id,
                    ]);

                    // Cambiar estado de la tabla orden
                    $query_update_orden = $pdo->prepare("
                        UPDATE orden_compras
                        SET orden_estado = 'ANULADA'
                        WHERE orden_id = (SELECT orden_id FROM facturas_compra WHERE fact_id = :fact_id)
                        ");
                    $query_update_orden->execute(['fact_id' => $fact_id]); 

                    // Cambiar estado de la tabla presupuesto
                    $query_update_presupuesto = $pdo->prepare("
                        UPDATE presupuesto_compra
                        SET pre_estado = 'ANULADA'
                        WHERE presupuesto_id = (
                            SELECT presupuesto_id 
                            FROM orden_compras 
                            WHERE orden_id = (SELECT orden_id FROM facturas_compra WHERE fact_id = :fact_id)
                        )
                        ");
                    $query_update_presupuesto->execute(['fact_id' => $fact_id]);
                    
                    // Cambiar estado de la tabla pedidos_compras a 'ANULADA'
                    $query_update_pedidos = $pdo->prepare("
                        UPDATE pedidos_compras
                        SET estado = 'ANULADA'
                        WHERE pedido_id = (
                            SELECT pedido_id 
                            FROM presupuesto_compra 
                            WHERE presupuesto_id = (
                                SELECT presupuesto_id 
                                FROM orden_compras 
                                WHERE orden_id = (
                                    SELECT orden_id 
                                    FROM facturas_compra 
                                    WHERE fact_id = :fact_id
                                )
                            )
                        )
                    ");
                    $query_update_pedidos->execute(['fact_id' => $fact_id]);
                    
                    
                    // Redirigir con mensaje de éxito
                    header("Location: view.php?alert=1");
                    exit();
                break;

                default:
                    // Redirigir con mensaje de éxito
                    header("Location: view.php?alert=1");
                    exit();
            }

        }


        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
        exit();
    }
}


if ($_GET['act'] == 'anular_nota_credito') {
    try {

        // Configuración de la base de datos
        require "../../config/database.php";
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "Conexión a la base de datos establecida...<br>";

        // Recuperar el ID de la nota de crédito
        $nota_id = $_GET['nota_id'];

        // Validar que la nota de crédito existe
        $query_nota = $pdo->prepare("
            SELECT nota_total, motivo_id, fact_id
            FROM notas_compra
            WHERE nota_id = :nota_id AND nota_estado = 'PROCESADO'
        ");
        $query_nota->execute(['nota_id' => $nota_id]);
        $nota = $query_nota->fetch(PDO::FETCH_ASSOC);

        if (!$nota) {
            throw new Exception("Nota de crédito no encontrada o ya anulada.");
        }

        $nota_total = $nota['nota_total'];
        $motivo_id = $nota['motivo_id'];
        $fact_id = $nota['fact_id'];

        // Cambiar el estado de la nota de crédito a "ANULADA"
        echo "Cambiando el estado de la nota de crédito a 'ANULADA'...<br>";
        $query_update_nota = $pdo->prepare("
            UPDATE notas_compra
            SET nota_estado = 'ANULADA'
            WHERE nota_id = :nota_id
        ");
        $query_update_nota->execute(['nota_id' => $nota_id]);
        echo "Nota de crédito anulada correctamente.<br>";

        // Manejar acciones según el motivo de la anulación
        switch ($motivo_id) {
            case 1: // Devolución de productos, parcialmente (anulación)

                try {
                    // Inicia una transacción
                    $pdo->beginTransaction();
            
                    // Paso 1: Obtener el `fact_id` desde `notas_compra` usando el `nota_id`
                    $query_fact_id = $pdo->prepare("
                        SELECT fact_id 
                        FROM notas_compra 
                        WHERE nota_id = :nota_id
                    ");
                    $query_fact_id->execute(['nota_id' => $nota_id]);
                    $fact_id = $query_fact_id->fetchColumn();
            
                    if (!$fact_id) {
                        throw new Exception("No se encontró el fact_id asociado al nota_id: $nota_id");
                    }
            
                    // Paso 2: Obtener el `orden_id` desde `facturas_compra` usando el `fact_id`
                    $query_orden_id = $pdo->prepare("
                        SELECT orden_id 
                        FROM facturas_compra 
                        WHERE fact_id = :fact_id
                    ");
                    $query_orden_id->execute(['fact_id' => $fact_id]);
                    $orden_id = $query_orden_id->fetchColumn();
            
                    if (!$orden_id) {
                        throw new Exception("No se encontró el orden_id asociado al fact_id: $fact_id");
                    }
            
                    // Paso 3: Resetear las cantidades a 0 en `facturas_detalle_compra`
                    $query_reset_cantidad = $pdo->prepare("
                        UPDATE facturas_detalle_compra
                        SET fact_cantidad = 0
                        WHERE fact_id = :fact_id
                    ");
                    $query_reset_cantidad->execute(['fact_id' => $fact_id]);
            
                    // Paso 4: Obtener los detalles originales desde `orden_detalle_compras`
                    $query_detalles = $pdo->prepare("
                        SELECT od.cod_producto, od.orden_cantidad, od.orden_precio, p.iva_id
                        FROM orden_detalle_compras od
                        INNER JOIN producto p ON od.cod_producto = p.cod_producto
                        WHERE od.orden_id = :orden_id
                    ");
                    $query_detalles->execute(['orden_id' => $orden_id]);
                    $detalles = $query_detalles->fetchAll(PDO::FETCH_ASSOC);
            
                    if (empty($detalles)) {
                        throw new Exception("No se encontraron detalles en orden_detalle_compras para el orden_id: $orden_id");
                    }
            
                    // Inicializar variables para el cálculo
                    $nuevo_total = 0;
                    $iva_5_total = 0;
                    $iva_10_total = 0;
            
                    // Paso 5: Restaurar las cantidades en `facturas_detalle_compra` y recalcular
                    foreach ($detalles as $detalle) {
                        $cod_producto = $detalle['cod_producto'];
                        $orden_cantidad = intval($detalle['orden_cantidad']);
                        $precio = floatval($detalle['orden_precio']);
                        $iva_id = intval($detalle['iva_id']);
            
                        // Restaurar la cantidad en `facturas_detalle_compra`
                        $query_update_cantidad = $pdo->prepare("
                            UPDATE facturas_detalle_compra
                            SET fact_cantidad = :orden_cantidad
                            WHERE fact_id = :fact_id AND cod_producto = :cod_producto
                        ");
                        $query_update_cantidad->execute([
                            'orden_cantidad' => $orden_cantidad,
                            'fact_id' => $fact_id,
                            'cod_producto' => $cod_producto,
                        ]);
            
                        // Calcular subtotales e IVA
                        $subtotal = $orden_cantidad * $precio; // Subtotal del producto
                        $iva_producto_unitario = 0; // IVA unitario para el producto
                        $iva_producto_total = 0; // IVA total para todas las unidades del producto
                        
                        if ($iva_id == 1) { // IVA 5%
                            $iva_producto_unitario = $precio / 21; // Cálculo del IVA para una unidad
                            $iva_producto_total = $subtotal / 21; // Cálculo del IVA total (subtotal/21)
                            $iva_5_total += $iva_producto_total; // Acumular IVA 5%
                        } elseif ($iva_id == 2) { // IVA 10%
                            $iva_producto_unitario = $precio / 11; // Cálculo del IVA para una unidad
                            $iva_producto_total = $subtotal / 11; // Cálculo del IVA total (subtotal/11)
                            $iva_10_total += $iva_producto_total; // Acumular IVA 10%
                        }
            
                        // Sumar el subtotal al total general
                        $nuevo_total += $subtotal;
            
                        // Actualizar el precio unitario e IVA en `facturas_detalle_compra`
                        $query_update_precio_iva = $pdo->prepare("
                            UPDATE facturas_detalle_compra
                            SET 
                                fact_precio = :precio_unitario,
                                fact_iva = :iva_unitario
                            WHERE fact_id = :fact_id AND cod_producto = :cod_producto
                        ");
                        $query_update_precio_iva->execute([
                            'precio_unitario' => $precio,
                            'iva_unitario' => $iva_producto_total,
                            'fact_id' => $fact_id,
                            'cod_producto' => $cod_producto,
                        ]);
                    }
            
                    // Paso 6: Actualizar el IVA total en `iva_compras`
                    $query_update_iva = $pdo->prepare("
                        UPDATE iva_compras
                        SET 
                            iva_5 = :iva_5_total,
                            iva_10 = :iva_10_total
                        WHERE fact_id = :fact_id
                    ");
                    $query_update_iva->execute([
                        'iva_5_total' => $iva_5_total,
                        'iva_10_total' => $iva_10_total,
                        'fact_id' => $fact_id,
                    ]);
            
                    // Paso 7: Actualizar el total en `facturas_compra`
                    $query_update_factura = $pdo->prepare("
                        UPDATE facturas_compra
                        SET fact_total = :nuevo_total, fact_estado = 'FINALIZADO'
                        WHERE fact_id = :fact_id
                    ");
                    $query_update_factura->execute([
                        'nuevo_total' => $nuevo_total,
                        'fact_id' => $fact_id,
                    ]);
            
                    // Paso 8: Actualizar el total en `cuenta_pagar`
                    $query_update_cta_pagar = $pdo->prepare("
                        UPDATE cuenta_pagar
                        SET cta_total = :nuevo_total, estado = 'FINALIZADO'
                        WHERE fact_id = :fact_id
                    ");
                    $query_update_cta_pagar->execute([
                        'nuevo_total' => $nuevo_total,
                        'fact_id' => $fact_id,
                    ]);
            
                    // Confirmar la transacción
                    $pdo->commit();
            
                    // Redirigir con mensaje de éxito
                    header("Location: view.php?alert=3");
                    exit();
            
                } catch (Exception $e) {
                    // Revertir la transacción en caso de error
                    $pdo->rollBack();
                    echo "Error: " . $e->getMessage();
                }
            
                break;
            
            
            

            case 2: // Descuentos aplicados posteriormente
                echo "Motivo: Descuentos aplicados posteriormente<br>";
                // Lógica específica para descuentos
                // Ejemplo: Registrar en una tabla de ajustes
                $query_registrar_descuento = $pdo->prepare("
                    INSERT INTO ajustes_descuentos (fact_id, monto, descripcion)
                    VALUES (:fact_id, :nota_total, 'Descuento aplicado por anulación de nota de crédito')
                ");
                $query_registrar_descuento->execute([
                    'fact_id' => $fact_id,
                    'nota_total' => $nota_total
                ]);
                echo "Descuento registrado en la tabla de ajustes.<br>";
                break;
                






            case 5: // Devolución de todos los productos
                echo "Motivo: Devolución de todos los productos<br>";
                // Lógica específica para devolución total
                // Ejemplo: Actualizar múltiples inventarios
                $query_update_inventario_total = $pdo->prepare("
                    UPDATE producto
                    SET stock = stock + :nota_total
                    WHERE fact_id = :fact_id
                ");
                $query_update_inventario_total->execute([
                    'nota_total' => $nota_total,
                    'fact_id' => $fact_id
                ]);
                echo "Inventario actualizado para devolución de todos los productos.<br>";
                break;

            default:
                echo "Motivo no reconocido.<br>";
                break;
        }

        // Redirigir con mensaje de éxito
        header("Location: view.php?alert=1");
        exit();
    } catch (Exception $e) {
        // Manejo de errores
        echo "Error: " . $e->getMessage();
        exit();
    }
}


?>

