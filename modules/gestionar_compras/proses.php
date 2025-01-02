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
        $fact_plazo = $_POST['cantidad_cuotas'];
        $fact_remision = isset($_POST['nota_remision']) ? true : false; // Booleano
        $tipo_id = (int)$_POST['tipo_factura'];
        $orden_id = (int)$_POST['presupuesto'];
        //$fact_hora = $_POST['hora'];
        $id_usuario = $_SESSION['id_usuario'];

        // Procesar la hora ingresada por el usuario
        $hora_usuario = $_POST['hora'] ?? ''; // Capturar la hora del formulario
        $hora = DateTime::createFromFormat('h:i A', $hora_usuario);

        // Validar si la hora fue convertida correctamente
        if ($hora) {
            $fact_hora = $hora->format('H:i:s'); // Convertir al formato 24 horas
        }else {
            $fact_hora = '00:00:00'; // Usar un valor predeterminado si no es válida
        }

        echo "Recuperando datos del formulario...<br>";

        // Recuperar datos relacionados con la orden de compra
        $query_orden = $pdo->prepare("SELECT * FROM orden_compras WHERE orden_id = :orden_id");
        $query_orden->execute(['orden_id' => $orden_id]);
        $orden = $query_orden->fetch(PDO::FETCH_ASSOC);

        if (!$orden) {
            throw new Exception("La orden de compra no existe.");
        }

        $cod_proveedor = $orden['cod_proveedor'];
        echo "Orden de compra encontrada...<br>";

        // Insertar en facturas_compra
        $query_factura = $pdo->prepare("
            INSERT INTO facturas_compra (
                fact_id, fact_nro, fact_timbrado, fact_vencimiento, fact_inicio, fact_fecha, fact_total,
                fact_estado, fact_plazo, fact_remision, tipo_id, id_usuario, cod_proveedor, orden_id, fact_hora
            ) 
                VALUES (
                :fact_id, :fact_nro, :fact_timbrado, :fact_vencimiento, :fact_inicio, :fact_fecha, :fact_total,
                :fact_estado, :fact_plazo, :fact_remision, :tipo_id, :id_usuario, :cod_proveedor, :orden_id, :fact_hora
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
            'fact_plazo' => $fact_plazo,
            'fact_remision' => $fact_remision,
            'tipo_id' => $tipo_id,
            'id_usuario' => $id_usuario,
            'cod_proveedor' => $cod_proveedor,
            'orden_id' => $orden_id,
            'fact_hora' => $fact_hora,
            
        ]);

        echo "Factura insertada correctamente...<br>";

        // Variables para calcular IVA
        $iva_5 = 0;
        $iva_10 = 0;
        $iva_exento = 0;

        // Insertar en facturas_detalle_compras y calcular IVA
        $productos = json_decode($_POST['productos'], true);
        foreach ($productos as $producto) {
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

            $subtotal = $producto['cantidad'] * $producto['precio'];
            if ($producto['iva'] == 5) {
                $iva_5 += $subtotal * 0.05;
            } elseif ($producto['iva'] == 10) {
                $iva_10 += $subtotal * 0.10;
            } else {
                $iva_exento += $subtotal;
            }

            if ($fact_remision) {
                // Obtener cod_deposito desde la tabla materias_primas
                $query_deposito = $pdo->prepare("
                    SELECT cod_deposito 
                    FROM producto 
                    WHERE cod_producto = :cod_producto
                ");
                $query_deposito->execute(['cod_producto' => $producto['codigo']]);
                $result_deposito = $query_deposito->fetch(PDO::FETCH_ASSOC);
            
                if ($result_deposito && isset($result_deposito['cod_deposito'])) {
                    $deposito_id = $result_deposito['cod_deposito'];
            
                    // Actualizar el stock en el depósito correcto
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
                } else {
                    throw new Exception("No se encontró el depósito asociado para el material con ID {$producto['codigo']}");
                }
            }
            
        }

        echo "Detalles e IVA procesados...<br>";

        // Obtener el último iva_id de la tabla iva_compras
        $query_last_iva = $pdo->query("SELECT MAX(iva_id) AS last_id FROM iva_compras");
        $last_iva = $query_last_iva->fetch(PDO::FETCH_ASSOC);
        $new_iva_id = ($last_iva['last_id'] !== null) ? $last_iva['last_id'] + 1 : 1;

        // Insertar en iva_compras
        $query_iva = $pdo->prepare("
            INSERT INTO iva_compras (iva_id, iva_5, iva_10, iva_exento, iva_fecha, fact_id)
            VALUES (:iva_id, :iva_5, :iva_10, 0, :iva_fecha, :fact_id)
        ");
        $query_iva->execute([
            'iva_id' => $new_iva_id,
            'iva_5' => $iva_5,
            'iva_10' => $iva_10,
            'iva_fecha' => $fact_fecha,
            'fact_id' => $fact_id,
        ]);


        echo "IVA insertado correctamente...<br>";

        // Insertar en cuenta_pagar
        $query_last_cta = $pdo->query("SELECT MAX(cta_id) AS last_id FROM cuenta_pagar");
        $last_cta = $query_last_cta->fetch(PDO::FETCH_ASSOC);
        $new_cta_id = ($last_cta['last_id'] !== null) ? $last_cta['last_id'] + 1 : 1;

        $query_cuenta = $pdo->prepare("
            INSERT INTO cuenta_pagar (cta_id, fact_id, cta_total)
            VALUES (:cta_id, :fact_id, :cta_total)
        ");
        $query_cuenta->execute([
            'cta_id' => $new_cta_id,
            'fact_id' => $fact_id,
            'cta_total' => $fact_total,
        ]);

        echo "Cuenta a pagar insertada correctamente...<br>";

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

        echo "Iniciando proceso de anulación para factura ID: $fact_id<br>";

        // Validar si la factura existe y obtener datos relacionados
        $query_factura = $pdo->prepare("
            SELECT fc.fact_id, fc.fact_remision, fc.orden_id
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

        // Revertir el impacto en el stock si la factura tenía nota de remisión
        if ($fact_remision) {
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

                // Obtener el depósito asociado a la materia prima
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
        }

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
