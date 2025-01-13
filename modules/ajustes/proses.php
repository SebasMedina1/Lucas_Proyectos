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

if (isset($_GET['act']) && $_GET['act'] == 'insert_ajuste') {
    try {
        // Conectar a la base de datos
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Capturar datos del formulario
        $ajuste_id = $_POST['ajuste_id'];
        $ajuste_fecha = $_POST['ajuste_fecha'];
        $ajuste_hora = $_POST['hora'];
        $cod_producto = $_POST['producto'];
        $cod_deposito = $_POST['deposito'];
        $cantidad_ajustada = (int)$_POST['ajuste_cantidad'];
        $motivo_id = $_POST['motivo'];
        $tipo_ajuste = "Disminución del Stock"; // Tipo de ajuste fijo
        $id_usuario = $_SESSION['id_usuario'] ?? null; // Usuario autenticado

        // Log de depuración en la consola
echo "<script>console.log('Datos capturados: ajuste_id={$ajuste_id}, fecha={$ajuste_fecha}, hora={$ajuste_hora}, producto={$cod_producto}, deposito={$cod_deposito}, cantidad={$cantidad_ajustada}, motivo={$motivo_id}, usuario={$id_usuario}');</script>";

        if (!$id_usuario) {
            throw new Exception("Usuario no autenticado.");
        }

        // Validar que la cantidad ajustada no sea mayor al stock existente
        $stmtValidarStock = $pdo->prepare("
            SELECT stock_existencia FROM stock
            WHERE cod_producto = :cod_producto AND cod_deposito = :cod_deposito
        ");
        $stmtValidarStock->execute([
            ':cod_producto' => $cod_producto,
            ':cod_deposito' => $cod_deposito
        ]);
        $stock = $stmtValidarStock->fetch(PDO::FETCH_ASSOC);

        if (!$stock || $cantidad_ajustada > $stock['stock_existencia']) {
            throw new Exception("La cantidad ajustada excede el stock existente.");
        }

        // Insertar en la tabla ajustes
        $stmtAjuste = $pdo->prepare("
            INSERT INTO ajustes (ajuste_id, ajuste_fecha, ajuste_hora, cod_deposito, id_usuario, ajuste_estado, motivo_id, tipo_ajuste)
            VALUES (:ajuste_id, :ajuste_fecha, :ajuste_hora, :cod_deposito, :id_usuario, 'PROCESADO', :motivo_id, :tipo_ajuste)
        ");
        $stmtAjuste->execute([
            ':ajuste_id' => $ajuste_id,
            ':ajuste_fecha' => $ajuste_fecha,
            ':ajuste_hora' => $ajuste_hora,
            ':cod_deposito' => $cod_deposito,
            ':id_usuario' => $id_usuario,
            ':motivo_id' => $motivo_id,
            ':tipo_ajuste' => $tipo_ajuste
        ]);


        // Insertar en la tabla ajuste_detalle
        $stmtDetalle = $pdo->prepare("
            INSERT INTO ajuste_detalle (ajuste_id, cod_producto, ajuste_cantidad, cod_deposito)
            VALUES (:ajuste_id, :cod_producto, :ajuste_cantidad, :cod_deposito)
        ");
        $stmtDetalle->execute([
            ':ajuste_id' => $ajuste_id,
            ':cod_producto' => $cod_producto,
            ':ajuste_cantidad' => $cantidad_ajustada,
            ':cod_deposito' => $cod_deposito
        ]);

        // Actualizar la tabla stock
        $stmtStock = $pdo->prepare("
            UPDATE stock
            SET stock_existencia = GREATEST(stock_existencia - :ajuste_cantidad, 0)
            WHERE cod_producto = :cod_producto AND cod_deposito = :cod_deposito
        ");
        $stmtStock->execute([
            ':ajuste_cantidad' => $cantidad_ajustada,
            ':cod_producto' => $cod_producto,
            ':cod_deposito' => $cod_deposito
        ]);

        // Redirigir con éxito
        header("Location: view.php?alert=1");
        exit;
    } catch (PDOException $e) {
        echo "<script>console.error('Error en la operación de ajuste: " . $e->getMessage() . "');</script>";
        header("Location: view.php?alert=4");
    } catch (Exception $e) {
        echo "<script>console.error('Error general: " . $e->getMessage() . "');</script>";
        header("Location: view.php?alert=4");
    }
}

if (isset($_GET['act']) && $_GET['act'] == 'anular') {
    try {
        // Conectar a la base de datos
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Capturar el ID del ajuste
        $ajuste_id = $_GET['ajuste_id'];

        // Verificar si el ajuste existe y está activo
        $stmtAjuste = $pdo->prepare("SELECT ajuste_estado FROM ajustes WHERE ajuste_id = :ajuste_id");
        $stmtAjuste->execute([':ajuste_id' => $ajuste_id]);
        $ajuste = $stmtAjuste->fetch(PDO::FETCH_ASSOC);

        if (!$ajuste || $ajuste['ajuste_estado'] === 'ANULADO') {
            header("Location: view.php?alert=5");
            exit;
        }

        // Obtener los detalles del ajuste
        $stmtDetalles = $pdo->prepare("SELECT cod_producto, ajuste_cantidad, cod_deposito FROM ajuste_detalle WHERE ajuste_id = :ajuste_id");
        $stmtDetalles->execute([':ajuste_id' => $ajuste_id]);
        $detalles = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);

        // Actualizar el stock sumando la cantidad ajustada
        $stmtStock = $pdo->prepare("
            UPDATE stock
            SET stock_existencia = stock_existencia + :ajuste_cantidad
            WHERE cod_producto = :cod_producto AND cod_deposito = :cod_deposito
        ");

        foreach ($detalles as $detalle) {
            $stmtStock->execute([
                ':ajuste_cantidad' => $detalle['ajuste_cantidad'],
                ':cod_producto' => $detalle['cod_producto'],
                ':cod_deposito' => $detalle['cod_deposito']
            ]);
        }

        // Anular el ajuste
        $stmtAnular = $pdo->prepare("UPDATE ajustes SET ajuste_estado = 'ANULADO' WHERE ajuste_id = :ajuste_id");
        $stmtAnular->execute([':ajuste_id' => $ajuste_id]);

        // Redirigir con éxito
        header("Location: view.php?alert=3");
        exit;

    } catch (PDOException $e) {
        error_log("Error al anular el ajuste: " . $e->getMessage());
        echo "<script>console.error('Error al anular el ajuste: " . $e->getMessage() . "');</script>";
    } catch (Exception $e) {
        error_log("Error general: " . $e->getMessage());
        echo "<script>console.error('Error general: " . $e->getMessage() . "');</script>";
    }
}

?>
