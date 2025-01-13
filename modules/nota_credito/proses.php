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


            // Verificar si el número de timbrado ya existe
        $query_timbrado = $pdo->prepare("SELECT COUNT(*) FROM notas_compra WHERE nota_timbrado = :nota_timbrado");
        $query_timbrado->bindParam(':nota_timbrado', $nota_timbrado);
        $query_timbrado->execute();
        $timbrado_existe = $query_timbrado->fetchColumn();

        if ($timbrado_existe > 0) {
            // Mostrar mensaje de error si el timbrado ya existe
            echo "<script>
                    alert('El número de timbrado ya existe. Por favor, ingrese un número diferente.');
                    window.location.href = 'view.php?gestionar_compras=add&form=add&act=insert';
                </script>";
            exit;
        }

        // Verificar si el número de nota ya existe
        $query_nota_nro = $pdo->prepare("SELECT COUNT(*) FROM notas_compra WHERE nota_nro = :nota_nro");
        $query_nota_nro->bindParam(':nota_nro', $nota_nro);
        $query_nota_nro->execute();
        $nota_nro_existe = $query_nota_nro->fetchColumn();

        if ($nota_nro_existe > 0) {
            // Mostrar mensaje de error si el número de nota ya existe
            echo "<script>
                    alert('El número de nota ya existe. Por favor, ingrese un número diferente.');
                    window.location.href = 'view.php?gestionar_compras=add&form=add&act=insert';
                </script>";
            exit;
        }
        

        // Validar factura
        $query_factura = $pdo->prepare("
            SELECT fact_total, fact_fecha, fact_estado
            FROM facturas_compra
            WHERE fact_id = :fact_id
        ");
        $query_factura->execute(['fact_id' => $fact_id]);
        $factura = $query_factura->fetch(PDO::FETCH_ASSOC);





        // Validar fechas
        // Extraer el mes y año de las fechas
        //$factura_mes = date('Y-m', strtotime($factura['fact_fecha'])); // Año y mes de la factura
        //$nota_mes = date('Y-m', strtotime($nota_fecha)); // Año y mes de la nota de crédito

        // Validar que sean del mismo mes y año
        //if ($factura_mes !== $nota_mes) {
        //    throw new Exception("La nota de crédito solo puede emitirse dentro del mismo mes que la factura.");
        //}


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
                $detalles = json_decode($_POST['productos'], true);

                if (empty($detalles)) {
                    throw new Exception("No se encontraron detalles de productos para procesar.");
                }

                // Inicializar variables
                $monto_total_devuelto = 0;
                $iva_5_devuelto_total = 0;
                $iva_10_devuelto_total = 0;

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

                    // Calcular el monto devuelto (subtotal sin IVA)
                    $monto_devuelto = $detalle['cantidad'] * $detalle['precio'];

                    // Obtener el iva_id del producto
                    $query_producto = $pdo->prepare("
                        SELECT iva_id
                        FROM producto
                        WHERE cod_producto = :cod_producto
                    ");
                    $query_producto->execute(['cod_producto' => $detalle['codigo']]);
                    $producto_iva_id = $query_producto->fetchColumn();

                    // Calcular el IVA devuelto
                    $iva_unitario = 0;
                    $iva_total_producto = 0;

                    if ($producto_iva_id == 1) { // IVA 5%
                        $iva_unitario = intval($detalle['precio'] / 21);
                        $iva_total_producto = intval($detalle['cantidad'] * $iva_unitario);
                        $iva_5_devuelto_total += $iva_total_producto;
                    } elseif ($producto_iva_id == 2) { // IVA 10%
                        $iva_unitario = intval($detalle['precio'] / 11);
                        $iva_total_producto = intval($detalle['cantidad'] * $iva_unitario);
                        $iva_10_devuelto_total += $iva_total_producto;
                    }

                    // Sumar el monto devuelto + IVA al total devuelto
                    $monto_total_devuelto += $monto_devuelto + $iva_total_producto;
                }

                // Actualizar el estado de la factura a "DEVOLUCIÓN PARCIAL"
                $query_factura_update = $pdo->prepare("
                    UPDATE facturas_compra
                    SET fact_estado = 'DEVOLUCIÓN PARCIAL'
                    WHERE fact_id = :fact_id
                ");
                $query_factura_update->execute(['fact_id' => $fact_id]);

                

                // Actualizar el monto total en cuenta_pagar
                $query_update_cuenta_pagar = $pdo->prepare("
                    UPDATE cuenta_pagar
                    SET cta_total = cta_total - :monto_total_devuelto, estado = 'DEVOLUCIÓN PARCIAL'
                    WHERE fact_id = :fact_id
                ");
                $query_update_cuenta_pagar->execute([
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
                        SET cta_total = :nuevo_total, estado = 'DESCUENTO POSTERIOR'
                        WHERE fact_id = :fact_id
                    ");
                    $query_cta_pagar_update->execute([
                        'nuevo_total' => $nuevo_total,
                        'fact_id' => $fact_id,
                    ]);
                
                    // Actualizar el total en la tabla facturas_compra
                    $query_factura_update = $pdo->prepare("
                        UPDATE facturas_compra
                        SET fact_total = :nuevo_total, fact_estado = 'FINALIZADO - DESCUENTO POSTERIOR'
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
                        SET estado = 'ANULADA - DEVOLUCIÓN TOTAL DE PRODUCTOS'
                        WHERE fact_id = :fact_id
                    ");
                    $query_cta_pagar_update->execute([
                        'fact_id' => $fact_id,
                    ]);

                    // Cambiar estado de la tabla orden
                    $query_update_orden = $pdo->prepare("
                        UPDATE orden_compras
                        SET orden_estado = 'EN PROCESO'
                        WHERE orden_id = (SELECT orden_id FROM facturas_compra WHERE fact_id = :fact_id)
                        ");
                    $query_update_orden->execute(['fact_id' => $fact_id]); 

                    // Cambiar estado de la tabla presupuesto
                    $query_update_presupuesto = $pdo->prepare("
                        UPDATE presupuesto_compra
                        SET pre_estado = 'PENDIENTE'
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
                        SET estado = 'PENDIENTE'
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

        $stmtNotaCredito = $pdo->prepare("
            SELECT nota_estado 
            FROM notas_compra 
            WHERE nota_id = :nota_id
        ");
        $stmtNotaCredito->execute([':nota_id' => $nota_id]);
        $notaCredito = $stmtNotaCredito->fetch(PDO::FETCH_ASSOC);

        // Redirigir si la nota de crédito ya está anulada
        if (!$notaCredito || $notaCredito['nota_estado'] === 'ANULADA') {
            header("Location: view.php?alert=5"); // Alertar al usuario que la nota ya está anulada
            exit;
        }

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
                    $query_fact_id = $pdo->prepare("SELECT fact_id FROM notas_compra WHERE nota_id = :nota_id");
                    $query_fact_id->execute(['nota_id' => $nota_id]);
                    $fact_id = $query_fact_id->fetchColumn();
            
                    if (!$fact_id) {
                        throw new Exception("No se encontró el fact_id asociado al nota_id: $nota_id");
                    }
            
                    // Paso 2: Obtener el `orden_id` desde `facturas_compra` usando el `fact_id`
                    $query_orden_id = $pdo->prepare("SELECT orden_id FROM facturas_compra WHERE fact_id = :fact_id");
                    $query_orden_id->execute(['fact_id' => $fact_id]);
                    $orden_id = $query_orden_id->fetchColumn();
            
                    // Paso 3: Obtener los detalles originales desde `orden_detalle_compras`
                    $query_detalles = $pdo->prepare("SELECT od.cod_producto, od.orden_cantidad, od.orden_precio, p.iva_id FROM orden_detalle_compras od INNER JOIN producto p ON od.cod_producto = p.cod_producto WHERE od.orden_id = :orden_id");
                    $query_detalles->execute(['orden_id' => $orden_id]);
                    $detalles = $query_detalles->fetchAll(PDO::FETCH_ASSOC);
            
                    // Inicializar variables para el cálculo
                    $nuevo_total = 0;
                    $total_iva = 0;
            
                    // Paso 4: Recalcular el monto total y el IVA
                    foreach ($detalles as $detalle) {
                        $cod_producto = $detalle['cod_producto'];
                        $orden_cantidad = intval($detalle['orden_cantidad']);
                        $precio = floatval($detalle['orden_precio']);
                        $iva_id = intval($detalle['iva_id']);
            
                        // Calcular el subtotal del producto
                        $subtotal = $orden_cantidad * $precio;
                        $nuevo_total += $subtotal;
            
                        // Calcular el IVA unitario y total
                        $iva_unitario = 0;
                        if ($iva_id == 1) { // IVA 5%
                            $iva_unitario = $precio / 21;
                        } elseif ($iva_id == 2) { // IVA 10%
                            $iva_unitario = $precio / 11;
                        }
                        $total_iva += $iva_unitario * $orden_cantidad;
            
                        // Actualizar el stock del producto (sumar la cantidad devuelta)
                        $query_stock_update = $pdo->prepare("UPDATE stock SET stock_existencia = stock_existencia + :cantidad WHERE cod_producto = :cod_producto");
                        $query_stock_update->execute([
                            'cantidad' => $orden_cantidad,
                            'cod_producto' => $cod_producto,
                        ]);
                    }
            
                    // Sumar el IVA total al nuevo total
                    $nuevo_total += $total_iva;
            
                    // Paso 5: Actualizar el total en `facturas_compra`
                    $query_update_factura = $pdo->prepare("UPDATE facturas_compra SET fact_total = :nuevo_total, fact_estado = 'FINALIZADO' WHERE fact_id = :fact_id");
                    $query_update_factura->execute([
                        'nuevo_total' => $nuevo_total,
                        'fact_id' => $fact_id,
                    ]);
            
                    // Paso 6: Actualizar el total en `cuenta_pagar`
                    $query_update_cta_pagar = $pdo->prepare("UPDATE cuenta_pagar SET cta_total = :nuevo_total, estado = 'FINALIZADO' WHERE fact_id = :fact_id");
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

                case 2: // Descuentos aplicados posteriormente

                    try {
                        // Inicia una transacción
                        $pdo->beginTransaction();
                
                        // Paso 1: Obtener el `fact_id` desde `notas_compra` usando el `nota_id`
                        $query_fact_id = $pdo->prepare("SELECT fact_id FROM notas_compra WHERE nota_id = :nota_id");
                        $query_fact_id->execute(['nota_id' => $nota_id]);
                        $fact_id = $query_fact_id->fetchColumn();
                
                        if (!$fact_id) {
                            throw new Exception("No se encontró el fact_id asociado al nota_id: $nota_id");
                        }
                
                        // Paso 2: Obtener el `orden_id` desde `facturas_compra` usando el `fact_id`
                        $query_orden_id = $pdo->prepare("SELECT orden_id FROM facturas_compra WHERE fact_id = :fact_id");
                        $query_orden_id->execute(['fact_id' => $fact_id]);
                        $orden_id = $query_orden_id->fetchColumn();
                
                        if (!$orden_id) {
                            throw new Exception("No se encontró el orden_id asociado al fact_id: $fact_id");
                        }
                
                        // Paso 3: Obtener los detalles originales desde `orden_detalle_compras`
                        $query_detalles = $pdo->prepare(
                            "SELECT od.cod_producto, od.orden_cantidad, od.orden_precio, p.iva_id
                             FROM orden_detalle_compras od
                             INNER JOIN producto p ON od.cod_producto = p.cod_producto
                             WHERE od.orden_id = :orden_id"
                        );
                        $query_detalles->execute(['orden_id' => $orden_id]);
                        $detalles = $query_detalles->fetchAll(PDO::FETCH_ASSOC);
                
                        if (empty($detalles)) {
                            throw new Exception("No se encontraron detalles en orden_detalle_compras para el orden_id: $orden_id");
                        }
                
                        // Inicializar variables para el cálculo
                        $nuevo_total = 0;
                        $iva_5_total = 0;
                        $iva_10_total = 0;
                
                        // Paso 4: Recalcular el monto total y el IVA
                        foreach ($detalles as $detalle) {
                            $cod_producto = $detalle['cod_producto'];
                            $orden_cantidad = intval($detalle['orden_cantidad']);
                            $precio = floatval($detalle['orden_precio']);
                            $iva_id = intval($detalle['iva_id']);
                
                            // Calcular el subtotal del producto
                            $subtotal = $orden_cantidad * $precio;
                            $nuevo_total += $subtotal;
                
                            // Calcular IVA según el tipo
                            $iva_producto_unitario = 0;
                            $iva_producto_total = 0;
                            if ($iva_id == 1) { // IVA 5%
                                $iva_producto_unitario = (int)($precio / 21);
                                $iva_producto_total = (int)($subtotal / 21);
                                $iva_5_total += $iva_producto_total;
                            } elseif ($iva_id == 2) { // IVA 10%
                                $iva_producto_unitario = (int)($precio / 11);
                                $iva_producto_total = (int)($subtotal / 11);
                                $iva_10_total += $iva_producto_total;
                            }
                
                            // Actualizar el precio unitario e IVA unitario en `facturas_detalle_compra`
                            $query_update_precio_iva = $pdo->prepare(
                                "UPDATE facturas_detalle_compra
                                 SET fact_precio = :precio_unitario
                                 WHERE fact_id = :fact_id AND cod_producto = :cod_producto"
                            );
                            $query_update_precio_iva->execute([
                                'precio_unitario' => $precio,
                                'fact_id' => $fact_id,
                                'cod_producto' => $cod_producto,
                            ]);
                        }
                
                        // Sumar el IVA total al nuevo total
                        $nuevo_total += $iva_5_total + $iva_10_total;
                
                        // Paso 5: Actualizar el IVA total en `iva_compras`
                        $query_update_iva = $pdo->prepare("UPDATE iva_compras SET iva_5 = :iva_5_total, iva_10 = :iva_10_total WHERE fact_id = :fact_id");
                        $query_update_iva->execute([
                            'iva_5_total' => $iva_5_total,
                            'iva_10_total' => $iva_10_total,
                            'fact_id' => $fact_id,
                        ]);
                
                        // Paso 6: Actualizar el total en `facturas_compra`
                        $query_update_factura = $pdo->prepare("UPDATE facturas_compra SET fact_total = :nuevo_total, fact_estado = 'FINALIZADO' WHERE fact_id = :fact_id");
                        $query_update_factura->execute([
                            'nuevo_total' => $nuevo_total,
                            'fact_id' => $fact_id,
                        ]);
                
                        // Paso 7: Actualizar el total en `cuenta_pagar`
                        $query_update_cta_pagar = $pdo->prepare("UPDATE cuenta_pagar SET cta_total = :nuevo_total, estado = 'FINALIZADO' WHERE fact_id = :fact_id");
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
                        die("Error: " . $e->getMessage());
                    }
                

            case 5: // agregar nuevamente los productos al stock que fueron devueltos en su momento
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
            
                    // Paso 3: Obtener los detalles originales desde `orden_detalle_compras`
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
            
                    // Paso 4: Recuperar las cantidades al stock
                    foreach ($detalles as $detalle) {
                        $cod_producto = $detalle['cod_producto'];
                        $orden_cantidad = intval($detalle['orden_cantidad']);
                        $precio = floatval($detalle['orden_precio']);
                        $iva_id = intval($detalle['iva_id']); 
            
                        // Actualizar el stock, aumentando el stock de los productos que fueron devueltos
                        $query_update_stock = $pdo->prepare("
                            UPDATE stock
                            SET stock_existencia = stock_existencia + :cantidad
                            WHERE cod_producto = :cod_producto
                        ");
                        $query_update_stock->execute([
                            'cantidad' => $orden_cantidad,
                            'cod_producto' => $cod_producto,
                        ]);
            

                        
                    }
            
                    // Tomar solo la parte entera del total de IVA
                    $iva_5_total = (int)$iva_5_total; 
                    $iva_10_total = (int)$iva_10_total;                    

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

                    // Paso 7: Actualizar el estado en `facturas_compra`
                    $query_update_factura = $pdo->prepare("
                        UPDATE facturas_compra
                        SET fact_estado = 'FINALIZADO'
                        WHERE fact_id = :fact_id
                    ");
                    $query_update_factura->execute([
                        'fact_id' => $fact_id,
                    ]);

                    // Paso 8: Actualizar el estado en `cuenta_pagar`
                    $query_update_cta_pagar = $pdo->prepare("
                        UPDATE cuenta_pagar
                        SET estado = 'FINALIZADO'
                        WHERE fact_id = :fact_id
                    ");
                    $query_update_cta_pagar->execute([
                        'fact_id' => $fact_id,
                    ]);
            
                    // Paso 9: Cambiar estados de tablas relacionadas a "FINALIZADO"
                    $query_update_orden = $pdo->prepare("
                        UPDATE orden_compras
                        SET orden_estado = 'FINALIZADO'
                        WHERE orden_id = :orden_id
                    ");
                    $query_update_orden->execute(['orden_id' => $orden_id]);

                    $query_presupuesto_id = $pdo->prepare("
                        SELECT presupuesto_id 
                        FROM orden_compras 
                        WHERE orden_id = :orden_id
                    ");
                    $query_presupuesto_id->execute(['orden_id' => $orden_id]);
                    $presupuesto_id = $query_presupuesto_id->fetchColumn();

                    if ($presupuesto_id) {
                        $query_update_presupuesto = $pdo->prepare("
                            UPDATE presupuesto_compra
                            SET pre_estado = 'FINALIZADO'
                            WHERE presupuesto_id = :presupuesto_id
                        ");
                        $query_update_presupuesto->execute(['presupuesto_id' => $presupuesto_id]);

                        $query_pedido_id = $pdo->prepare("
                            SELECT pedido_id 
                            FROM presupuesto_compra 
                            WHERE presupuesto_id = :presupuesto_id
                        ");
                        $query_pedido_id->execute(['presupuesto_id' => $presupuesto_id]);
                        $pedido_id = $query_pedido_id->fetchColumn();

                        if ($pedido_id) {
                            $query_update_pedido = $pdo->prepare("
                                UPDATE pedidos_compras
                                SET estado = 'FINALIZADO'
                                WHERE pedido_id = :pedido_id
                            ");
                            $query_update_pedido->execute(['pedido_id' => $pedido_id]);
                        }
                    }
            
                    // Confirmar la transacción
                    $pdo->commit();
            
                    // Redirigir con mensaje de éxito
                    header("Location: view.php?alert=1");
                    exit();
                } catch (Exception $e) {
                    // Revertir la transacción en caso de error
                    $pdo->rollBack();
                    die("Error: " . $e->getMessage());
                }
                break;
            


                default:



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

