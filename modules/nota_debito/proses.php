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
        //$nota_timbrado = $_POST['nota_timbrado'];
        $nota_inicio = $_POST['nota_inicio'];
        $nota_vto = $_POST['nota_vto'];
        $nota_total = (float)$_POST['nota_total'];
        $motivo_id = $_POST['motivo_id'];
        $fact_id = $_POST['fact_id'];
        $detalles = json_decode($_POST['productos'], true);
        $id_usuario = $_SESSION['id_usuario'];

        $nota_hora = $_POST['hora'];
        // Validar y convertir el valor de nota_cargo
        $nota_cargo = isset($_POST['nota_cargo']) && $_POST['nota_cargo'] !== '' ? (float)$_POST['nota_cargo'] : 0.0;
        

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
        //if ($nota_total < $factura['fact_total']) {
         //   throw new Exception("El monto de la nota de debito no puede ser menor al monto de la factura.");
        //}

        // Validar fechas
        // Extraer el mes y año de las fechas
        $factura_mes = date('Y-m', strtotime($factura['fact_fecha'])); // Año y mes de la factura
        $nota_mes = date('Y-m', strtotime($nota_fecha)); // Año y mes de la nota de crédito

        // Validar que sean del mismo mes y año
        if ($factura_mes !== $nota_mes) {
            throw new Exception("La nota de crédito solo puede emitirse dentro del mismo mes que la factura.");
        }


        // Insertar en notas_debito
        $query_nota = $pdo->prepare("
            INSERT INTO nota_debito (
                nota_debito_id, nota_fecha, nota_nro, nota_vto, nota_inicio, nota_total, nota_estado, motivo_id, cod_proveedor, id_usuario, fact_id, nota_cargo, nota_hora
            ) VALUES (
                :nota_id, :nota_fecha, :nota_nro, :nota_vto, :nota_inicio, (:nota_total::numeric + :nota_cargo::numeric), 'PROCESADO',
                :motivo_id, (SELECT cod_proveedor FROM facturas_compra WHERE fact_id = :fact_id), :id_usuario, :fact_id, :nota_cargo, :nota_hora
            )
        ");
    
    
        $query_nota->execute([
            'nota_id' => $nota_id,
            'nota_fecha' => $nota_fecha,
            'nota_nro' => $nota_nro,
            'nota_vto' => $nota_vto,
            'nota_inicio' => $nota_inicio,
            'nota_total' => $nota_total,
            'motivo_id' => $motivo_id,
            'id_usuario' => $id_usuario,
            'nota_hora' => $nota_hora,
            'nota_cargo' => $nota_cargo,
            'fact_id' => $fact_id,
        ]);

        // Insertar detalles en notas_debito_detalle
        foreach ($detalles as $detalle) {
            $query_detalle = $pdo->prepare("
                INSERT INTO nota_debito_detalle (
                    nota_debito_id, cod_producto, nota_precio, nota_cantidad, nota_iva
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
                case 1: // Cargo adicional

                    $nuevo_total = 0;
                    $iva_5_total = 0;
                    $iva_10_total = 0;
        
                    // Obtener detalles desde nota_debito_detalle
                    $query_detalles_nota = $pdo->prepare("
                        SELECT ndd.cod_producto, ndd.nota_precio, ndd.nota_cantidad, p.iva_id
                        FROM nota_debito_detalle ndd
                        INNER JOIN producto p ON ndd.cod_producto = p.cod_producto
                        WHERE ndd.nota_debito_id = :nota_id
                    ");
                    $query_detalles_nota->execute(['nota_id' => $nota_id]);
                    $detalles_nota = $query_detalles_nota->fetchAll(PDO::FETCH_ASSOC);
        
                    foreach ($detalles_nota as $detalle) {
                        $cod_producto = $detalle['cod_producto'];
                        $cantidad = intval($detalle['nota_cantidad']);
                        $precio = floatval($detalle['nota_precio']);
                        $iva_id = intval($detalle['iva_id']);
        
                        // Calcular el subtotal
                        $subtotal = $cantidad * $precio;
        
                        // Calcular el IVA según el tipo
                        if ($iva_id == 1) { // IVA 5%
                            $iva_producto = intval($subtotal / 21);
                            $iva_5_total += $iva_producto;
                        } elseif ($iva_id == 2) { // IVA 10%
                            $iva_producto = intval($subtotal / 11);
                            $iva_10_total += $iva_producto;
                        }
        
                        // Sumar el subtotal al total
                        $nuevo_total += $subtotal;
                    }
        
                    // Sumar los IVAs al total general
                    $nuevo_total += $iva_5_total + $iva_10_total;

                    //  Sumar el cargo adicional al total
                    $nuevo_total += $nota_cargo;
        
                    // Actualizar estado en facturas_compra
                    $query_factura_update = $pdo->prepare("
                        UPDATE facturas_compra
                        SET fact_estado = 'FINALIZADO - CARGO ADICIONAL'
                        WHERE fact_id = :fact_id
                    ");
                    $query_factura_update->execute([
                        'fact_id' => $fact_id,
                    ]);
        
                    // Actualizar cuenta_pagar
                    $query_update_cuenta_pagar = $pdo->prepare("
                        UPDATE cuenta_pagar
                        SET cta_total = :nuevo_total, estado = 'FINALIZADO - CARGO ADICIONAL'
                        WHERE fact_id = :fact_id
                    ");
                    $query_update_cuenta_pagar->execute([
                        'nuevo_total' => $nuevo_total,
                        'fact_id' => $fact_id,
                    ]);
        
                    // Redirigir con mensaje de éxito
                    header("Location: view.php?alert=1");
                    exit();

                case 2: // corrección de errores
                    try {
                        // Inicia una transacción
                        $pdo->beginTransaction();
                    
                        // Paso 1: Obtener el fact_id desde nota_debito usando el nota_debito_id
                        $query_fact_id = $pdo->prepare("SELECT fact_id FROM nota_debito WHERE nota_debito_id = :nota_id");
                        $query_fact_id->execute(['nota_id' => $nota_id]);
                        $fact_id = $query_fact_id->fetchColumn();
                    
                        if (!$fact_id) {
                            throw new Exception("No se encontró el fact_id asociado al nota_id: $nota_id");
                        }
                    
                        // Paso 2: Obtener el orden_id desde facturas_compra usando el fact_id
                        $query_orden_id = $pdo->prepare("SELECT orden_id FROM facturas_compra WHERE fact_id = :fact_id");
                        $query_orden_id->execute(['fact_id' => $fact_id]);
                        $orden_id = $query_orden_id->fetchColumn();
                    
                        if (!$orden_id) {
                            throw new Exception("No se encontró el orden_id asociado al fact_id: $fact_id");
                        }
                    
                        // Paso 3: Obtener los detalles originales desde orden_detalle_compras
                        $query_detalles = $pdo->prepare("SELECT cod_producto, orden_cantidad FROM orden_detalle_compras WHERE orden_id = :orden_id");
                        $query_detalles->execute(['orden_id' => $orden_id]);
                        $detalles_originales = $query_detalles->fetchAll(PDO::FETCH_ASSOC);
                    
                        if (empty($detalles_originales)) {
                            throw new Exception("No se encontraron detalles en orden_detalle_compras para el orden_id: $orden_id");
                        }
                    
                        // Paso 4: Restar las cantidades originales del stock
                        foreach ($detalles_originales as $detalle) {
                            $cod_producto = $detalle['cod_producto'];
                            $cantidad_original = intval($detalle['orden_cantidad']);
                    
                            $query_restar_stock = $pdo->prepare("UPDATE stock SET stock_existencia = stock_existencia - :cantidad WHERE cod_producto = :cod_producto");
                            $query_restar_stock->execute(['cantidad' => $cantidad_original, 'cod_producto' => $cod_producto]);
                        }
                    
                        // Paso 5: Procesar los nuevos detalles enviados desde el formulario
                        $detalles_nuevos = json_decode($_POST['productos'], true);
                    
                        if (empty($detalles_nuevos)) {
                            throw new Exception("No se encontraron detalles de productos para procesar.");
                        }
                    
                        $nuevo_total = 0;
                        $iva_5_total = 0;
                        $iva_10_total = 0;
                    
                        foreach ($detalles_nuevos as $detalle) {
                            $cod_producto = $detalle['codigo'];
                            $cantidad_nueva = intval($detalle['cantidad']);
                            $precio = floatval($detalle['precio']);
                    
                            // Calcular el subtotal actualizado del producto
                            $subtotal_actualizado = $cantidad_nueva * $precio;
                    
                            // Obtener el iva_id del producto
                            $query_producto = $pdo->prepare("SELECT iva_id FROM producto WHERE cod_producto = :cod_producto");
                            $query_producto->execute(['cod_producto' => $cod_producto]);
                            $producto_iva_id = $query_producto->fetchColumn();
                    
                            // Calcular el IVA unitario y el IVA total del producto
                            $iva_producto_total = 0;
                            if ($producto_iva_id == 1) { // IVA 5%
                                $iva_producto_total = intval($subtotal_actualizado / 21);
                                $iva_5_total += $iva_producto_total;
                            } elseif ($producto_iva_id == 2) { // IVA 10%
                                $iva_producto_total = intval($subtotal_actualizado / 11);
                                $iva_10_total += $iva_producto_total;
                            }
                    
                            // Sumar el nuevo subtotal al total actualizado
                            $nuevo_total += $subtotal_actualizado;
                    
                            // Actualizar el stock con las nuevas cantidades
                            $query_sumar_stock = $pdo->prepare("UPDATE stock SET stock_existencia = stock_existencia + :cantidad WHERE cod_producto = :cod_producto");
                            $query_sumar_stock->execute(['cantidad' => $cantidad_nueva, 'cod_producto' => $cod_producto]);
                    
                            // **Actualizar el precio unitario e IVA unitario en facturas_detalle_compra**
                            $query_update_precio_iva = $pdo->prepare("UPDATE facturas_detalle_compra SET fact_precio = :precio_unitario, fact_iva = :iva_unitario, fact_cantidad = :cantidad_nueva WHERE fact_id = :fact_id AND cod_producto = :cod_producto");
                            $query_update_precio_iva->execute([
                                'precio_unitario' => $precio,
                                'iva_unitario' => intval($iva_producto_total / $cantidad_nueva),
                                'cantidad_nueva' => $cantidad_nueva,
                                'fact_id' => $fact_id,
                                'cod_producto' => $cod_producto,
                            ]);
                        }
                    
                        // Tomar solo la parte entera del total de IVA
                        $iva_5_total = intval($iva_5_total);
                        $iva_10_total = intval($iva_10_total);
                    
                        // Sumar el IVA total al total general
                        $nuevo_total += $iva_5_total + $iva_10_total;
                    
                        // **Actualizar el IVA total en iva_compras**
                        $query_iva_update = $pdo->prepare("UPDATE iva_compras SET iva_5 = :iva_5_total, iva_10 = :iva_10_total WHERE fact_id = :fact_id");
                        $query_iva_update->execute([
                            'iva_5_total' => $iva_5_total,
                            'iva_10_total' => $iva_10_total,
                            'fact_id' => $fact_id,
                        ]);
                    
                        // **Actualizar el total en cuenta_pagar**
                        $query_cta_pagar_update = $pdo->prepare("UPDATE cuenta_pagar SET cta_total = :nuevo_total, estado = 'FINALIZADO - CORRECCIÓN DE ERRORES' WHERE fact_id = :fact_id");
                        $query_cta_pagar_update->execute([
                            'nuevo_total' => $nuevo_total,
                            'fact_id' => $fact_id,
                        ]);
                    
                        // **Actualizar el total en facturas_compra**
                        $query_factura_update = $pdo->prepare("UPDATE facturas_compra SET fact_total = :nuevo_total, fact_estado = 'FINALIZADO - CORRECCIÓN DE ERRORES' WHERE fact_id = :fact_id");
                        $query_factura_update->execute([
                            'nuevo_total' => $nuevo_total,
                            'fact_id' => $fact_id,
                        ]);
                    
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
                    
                
          

            }

        }


        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
        exit();
    }
}


if ($_GET['act'] == 'anular_nota_debito') {
    try {

        // Configuración de la base de datos
        require "../../config/database.php";
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Recuperar el ID de la nota de crédito
        $nota_id = $_GET['nota_id'];

        $stmtNotaDebito = $pdo->prepare("
            SELECT nota_estado
            FROM nota_debito
            WHERE nota_debito_id = :nota_id
        ");
        $stmtNotaDebito->execute([':nota_id' => $nota_id]);
        $notaDebito = $stmtNotaDebito->fetch(PDO::FETCH_ASSOC);

        // Redirigir si la nota de débito ya está anulada
        if (!$notaDebito || $notaDebito['nota_estado'] === 'ANULADA') {
            header("Location: view.php?alert=5"); // Alertar al usuario que la nota ya está anulada
            exit;
        }

        // Validar que la nota de debito existe
        $query_nota = $pdo->prepare("
            SELECT nota_total, motivo_id, fact_id
            FROM nota_debito
            WHERE nota_debito_id = :nota_id AND nota_estado = 'PROCESADO'
        ");
        $query_nota->execute(['nota_id' => $nota_id]);
        $nota = $query_nota->fetch(PDO::FETCH_ASSOC);



        $nota_total = $nota['nota_total'];
        $motivo_id = $nota['motivo_id'];
        $fact_id = $nota['fact_id'];

        // Cambiar el estado de la nota de debito a "ANULADA"
        $query_update_nota = $pdo->prepare("
            UPDATE nota_debito
            SET nota_estado = 'ANULADA'
            WHERE nota_debito_id = :nota_id
        ");
        $query_update_nota->execute(['nota_id' => $nota_id]);

        // Manejar acciones según el motivo de la anulación
        switch ($motivo_id) {
            case 1: // Cargo adicional, anulación

                try {
                    // Inicia una transacción
                    $pdo->beginTransaction();
            
                    // Paso 1: Obtener el `fact_id` desde `nota_debito` usando el `nota_debito_id`
                    $query_fact_id = $pdo->prepare("
                        SELECT fact_id 
                        FROM nota_debito
                        WHERE nota_debito_id = :nota_debito_id
                    ");
                    $query_fact_id->execute(['nota_debito_id' => $nota_id]);
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
            
                    // Paso 4: Obtener los detalles originales desde `orden_detalle_compras`
                    $query_detalles = $pdo->prepare("
                        SELECT od.cod_producto, od.orden_cantidad, od.orden_precio, p.iva_id
                        FROM orden_detalle_compras od
                        INNER JOIN producto p ON od.cod_producto = p.cod_producto
                        WHERE od.orden_id = :orden_id
                    ");
                    $query_detalles->execute(['orden_id' => $orden_id]);
                    $detalles = $query_detalles->fetchAll(PDO::FETCH_ASSOC);
            
                    // Inicializar variables para el cálculo
                    $nuevo_total = 0;
                    $iva_5_total = 0;
                    $iva_10_total = 0;
            
                    // Paso 5: Recalcular el monto total con IVA
                    foreach ($detalles as $detalle) {
                        $cod_producto = $detalle['cod_producto'];
                        $orden_cantidad = intval($detalle['orden_cantidad']);
                        $precio = floatval($detalle['orden_precio']);
                        $iva_id = intval($detalle['iva_id']);
            
                        // Calcular el subtotal sin IVA
                        $subtotal = $orden_cantidad * $precio;
            
                        // Calcular el IVA según el tipo de IVA
                        if ($iva_id == 1) { // IVA 5%
                            $iva_producto_total = $subtotal / 21;
                            $iva_5_total += $iva_producto_total;
                        } elseif ($iva_id == 2) { // IVA 10%
                            $iva_producto_total = $subtotal / 11;
                            $iva_10_total += $iva_producto_total;
                        }
            
                        // Sumar el subtotal al total general
                        $nuevo_total += $subtotal;
                    }
            
                    // Sumar los IVAs al total general
                    $nuevo_total += $iva_5_total + $iva_10_total;
                    $nuevo_total = intval($nuevo_total);

            
                    // Paso 7: Actualizar el estado en `facturas_compra`
                    $query_update_factura = $pdo->prepare("
                        UPDATE facturas_compra
                        SET fact_estado = 'FINALIZADO'
                        WHERE fact_id = :fact_id
                    ");
                    $query_update_factura->execute([
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
                    // Si ocurre un error, se revierte la transacción
                    $pdo->rollBack();
                    die("Error: " . $e->getMessage());
                }
            

            case 2: // corrección de errores, anulación
                try {
                    // Inicia una transacción
                    $pdo->beginTransaction();
                
                    //Paso 1: Obtener los detalles de la nota de débito
                    $query_detalles_nota = $pdo->prepare("
                        SELECT ndd.cod_producto, ndd.nota_cantidad, ndd.nota_precio, p.iva_id
                        FROM nota_debito_detalle ndd
                        INNER JOIN producto p ON ndd.cod_producto = p.cod_producto
                        WHERE ndd.nota_debito_id = :nota_id
                    ");
                    $query_detalles_nota->execute(['nota_id' => $nota_id]);
                    $detalles_nota = $query_detalles_nota->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($detalles_nota)) {
                        throw new Exception("No se encontraron detalles en la nota de débito.");
                    }

                    //Paso 2: Revertir los cambios en stock
                    foreach ($detalles_nota as $detalle) {
                        $cod_producto = $detalle['cod_producto'];
                        $nota_cantidad = intval($detalle['nota_cantidad']);

                        $query_revertir_stock = $pdo->prepare("
                            UPDATE stock
                            SET stock_existencia = stock_existencia - :cantidad
                            WHERE cod_producto = :cod_producto
                        ");
                        $query_revertir_stock->execute([
                            'cantidad' => $nota_cantidad,
                            'cod_producto' => $cod_producto,
                        ]);
                    } 

                    //Paso 3: Obtener los detalles de la orden asociada
                    $query_orden_id = $pdo->prepare("SELECT orden_id FROM facturas_compra WHERE fact_id = :fact_id");
                    $query_orden_id->execute(['fact_id' => $fact_id]);
                    $orden_id = $query_orden_id->fetchColumn();

                    if (!$orden_id) {
                        throw new Exception("No se encontró el orden_id asociado a la factura.");
                    }

                    //Paso 4: Obtener los detalles de la orden
                    $query_detalles_orden = $pdo->prepare("
                        SELECT od.cod_producto, od.orden_cantidad, od.orden_precio, p.iva_id
                        FROM orden_detalle_compras od
                        INNER JOIN producto p ON od.cod_producto = p.cod_producto
                        WHERE od.orden_id = :orden_id
                    ");
                    $query_detalles_orden->execute(['orden_id' => $orden_id]);
                    $detalles_orden = $query_detalles_orden->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($detalles_orden)) {
                        throw new Exception("No se encontraron detalles en orden_detalle_compras.");
                    }

                    // Inicializar variables para el cálculo
                    $nuevo_total = 0;
                    $iva_5_total = 0;
                    $iva_10_total = 0;
                
                    // Paso 5: Recalcular el monto total con IVA y actualizar facturas_detalle_compra
                    foreach ($detalles_orden as $detalle) {
                        $cod_producto = $detalle['cod_producto'];
                        $orden_cantidad = intval($detalle['orden_cantidad']);
                        $precio = floatval($detalle['orden_precio']);
                        $iva_id = intval($detalle['iva_id']);
                
                        // Calcular el subtotal sin IVA
                        $subtotal = $orden_cantidad * $precio;
                
                        // Calcular el IVA según el tipo de IVA
                        $iva_producto_total = 0;
                        if ($iva_id == 1) { // IVA 5%
                            $iva_producto_total = intval($subtotal / 21);
                            $iva_5_total += $iva_producto_total;
                        } elseif ($iva_id == 2) { // IVA 10%
                            $iva_producto_total = intval($subtotal / 11);
                            $iva_10_total += $iva_producto_total;
                        }
                
                        // Calcular el total por producto (subtotal + IVA)
                        $total_producto = $subtotal + $iva_producto_total;
                
                        // Sumar el total por producto al total general
                        $nuevo_total += $total_producto;
                
                        // Actualizar facturas_detalle_compra
                        $query_update_fact_detalle = $pdo->prepare("
                            UPDATE facturas_detalle_compra
                            SET fact_precio = :precio_unitario
                            WHERE fact_id = :fact_id AND cod_producto = :cod_producto
                        ");
                        $query_update_fact_detalle->execute([
                            'precio_unitario' => $precio,
                            'fact_id' => $fact_id,
                            'cod_producto' => $cod_producto,
                        ]);

                        // Sumar la cantidad de la orden al stock
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
                
                    // Sumar los IVAs al total general
                    $nuevo_total += $iva_5_total + $iva_10_total;
                
                    // Paso 6: Actualizar facturas_compra
                    $query_update_factura = $pdo->prepare("
                        UPDATE facturas_compra
                        SET fact_total = :nuevo_total, fact_estado = 'FINALIZADO'
                        WHERE fact_id = :fact_id
                    ");
                    $query_update_factura->execute([
                        'nuevo_total' => $nuevo_total,
                        'fact_id' => $fact_id,
                    ]);
                
                    // Paso 7: Actualizar iva_compras
                    $query_update_iva = $pdo->prepare("
                        UPDATE iva_compras
                        SET iva_5 = :iva_5_total, iva_10 = :iva_10_total
                        WHERE fact_id = :fact_id
                    ");
                    $query_update_iva->execute([
                        'iva_5_total' => $iva_5_total,
                        'iva_10_total' => $iva_10_total,
                        'fact_id' => $fact_id,
                    ]);
                
                    // Paso 8: Actualizar cuenta_pagar
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
                    die("Error: " . $e->getMessage());
                }
                

                default:

        }

    } catch (Exception $e) {
        // Manejo de errores
        echo "Error: " . $e->getMessage();
        exit();
    }
}


?>

