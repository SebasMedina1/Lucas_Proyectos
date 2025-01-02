<?php
require "../../config/database.php";

if (isset($_GET['act']) && $_GET['act'] == 'insert_nota_remision') {
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Datos de la cabecera
        $remision_id = $_POST['remision_id'];
        $remision_fecha = $_POST['remision_fecha'];
        $remision_nro = $_POST['remision_nro'];
        $vehiculo_id = $_POST['vehiculo_id'];
        $conductor_id = $_POST['conductor_id'];
        $fact_id = $_POST['fact_id'];
        $total_importe = $_POST['total_importe'];
        $productos = json_decode($_POST['productos'], true);

        // Datos adicionales
        $remision_estado = 'PROCESADO';
        session_start(); // Asegúrate de iniciar la sesión
        $usua_id = $_SESSION['usua_id'] ?? null; // ID del usuario autenticado
        $prov_id = null;

        // Depuración: verificar datos de entrada
        echo "<h3>Datos recibidos:</h3>";
        var_dump($remision_id, $remision_fecha, $remision_nro, $vehiculo_id, $conductor_id, $fact_id, $total_importe, $productos);
        echo "<hr>";

        // Obtener el proveedor de la factura
        $queryProveedor = $pdo->prepare("SELECT prov_id FROM facturas_compra WHERE fact_id = :fact_id");
        $queryProveedor->execute([':fact_id' => $fact_id]);
        $proveedor = $queryProveedor->fetch(PDO::FETCH_ASSOC);
        $prov_id = $proveedor['prov_id'] ?? null;

        // Determinar depósito desde la primera materia prima en productos
        $deposito_id = null;
        if (!empty($productos)) {
            $queryDeposito = $pdo->prepare("SELECT deposito_id FROM materias_primas WHERE mat_id = :mat_id LIMIT 1");
            $queryDeposito->execute([':mat_id' => $productos[0]['codigo']]);
            $deposito = $queryDeposito->fetch(PDO::FETCH_ASSOC);
            $deposito_id = $deposito['deposito_id'] ?? null;
        }

        // Validación adicional para asegurarte de que el depósito fue encontrado
        if (is_null($deposito_id)) {
            throw new Exception("No se pudo determinar el depósito asociado a la materia prima.");
        }

        echo "<h3>Depósito determinado: {$deposito_id}</h3>";

        // Insertar cabecera
        $stmt = $pdo->prepare("
            INSERT INTO nota_remision_compra (
                remision_id, remision_fecha, remision_nro, remision_vehiculo, 
                remision_conductor, remision_estado, remision_total, 
                fact_id, usua_id, deposito_id, prov_id
            ) VALUES (
                :remision_id, :remision_fecha, :remision_nro, :vehiculo_id, 
                :conductor_id, :remision_estado, :total_importe, 
                :fact_id, :usua_id, :deposito_id, :prov_id
            )
        ");
        $stmt->execute([
            ':remision_id' => $remision_id,
            ':remision_fecha' => $remision_fecha,
            ':remision_nro' => $remision_nro,
            ':vehiculo_id' => $vehiculo_id,
            ':conductor_id' => $conductor_id,
            ':remision_estado' => $remision_estado,
            ':total_importe' => $total_importe,
            ':fact_id' => $fact_id,
            ':usua_id' => $usua_id,
            ':deposito_id' => $deposito_id,
            ':prov_id' => $prov_id
        ]);

        echo "<h3>Cabecera insertada con éxito.</h3>";

        // Insertar detalles
        $stmtDetalle = $pdo->prepare("
            INSERT INTO nota_remision_compra_detalle (
                remision_id, mat_id, remision_cantidad, remision_iva
            ) VALUES (:remision_id, :mat_id, :remision_cantidad, :remision_iva)
        ");

        foreach ($productos as $producto) {
            $stmtDetalle->execute([
                ':remision_id' => $remision_id,
                ':mat_id' => $producto['codigo'],
                ':remision_cantidad' => $producto['cantidad'],
                ':remision_iva' => $producto['iva']
            ]);
        }

        echo "<h3>Detalles insertados con éxito.</h3>";

        // Actualizar stock si la factura no está marcada como "nota de remisión"

            $stmtStock = $pdo->prepare("
                UPDATE stock_materias
                SET stock_existencia = stock_existencia + :cantidad
                WHERE mat_id = :mat_id
            ");

            foreach ($productos as $producto) {
                $stmtStock->execute([
                    ':cantidad' => $producto['cantidad'],
                    ':mat_id' => $producto['codigo']
                ]);
            }


        

        echo "<h3>Stock actualizado y factura marcada como procesada.</h3>";

        // Redirigir con éxito
        header("Location: view.php?alert=1");
        exit;
    } catch (PDOException $e) {
        echo "<h3>Error detectado:</h3>";
        echo $e->getMessage();
        die();
    } catch (Exception $e) {
        echo "<h3>Error lógico:</h3>";
        echo $e->getMessage();
        die();
    }
}

if (isset($_GET['act']) && $_GET['act'] == 'anular_remision') {
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Obtener el ID de la remisión desde la URL
        $remision_id = $_GET['remision_id'];

        // Obtener el estado actual de la nota de remisión
        $queryEstado = $pdo->prepare("SELECT remision_estado FROM nota_remision_compra WHERE remision_id = :remision_id");
        $queryEstado->execute([':remision_id' => $remision_id]);
        $estado = $queryEstado->fetch(PDO::FETCH_ASSOC);

        if ($estado['remision_estado'] !== 'PROCESADO') {
            // Si la nota ya está anulada, redirigir con error
            header("Location: view.php?alert=4");
            exit;
        }

        // Obtener los detalles de la remisión
        $queryDetalles = $pdo->prepare("SELECT mat_id, remision_cantidad FROM nota_remision_compra_detalle WHERE remision_id = :remision_id");
        $queryDetalles->execute([':remision_id' => $remision_id]);
        $detalles = $queryDetalles->fetchAll(PDO::FETCH_ASSOC);

        // Revertir el stock de cada materia prima
        $stmtStock = $pdo->prepare("UPDATE stock_materias
                SET stock_existencia = stock_existencia - :cantidad
                WHERE mat_id = :mat_id");
        foreach ($detalles as $detalle) {
            $stmtStock->execute([
                ':cantidad' => $detalle['remision_cantidad'],
                ':mat_id' => $detalle['mat_id']
            ]);
        }

        // Cambiar el estado de la nota de remisión a "ANULADO"
        $stmtAnular = $pdo->prepare("UPDATE nota_remision_compra SET remision_estado = 'ANULADO' WHERE remision_id = :remision_id");
        $stmtAnular->execute([':remision_id' => $remision_id]);

        // Redirigir con éxito
        header("Location: view.php?alert=3");
        exit;
    } catch (PDOException $e) {
        error_log("Error al anular la nota de remisión: " . $e->getMessage());
        header("Location: view.php?alert=4");
        exit;
    }
}
?>
