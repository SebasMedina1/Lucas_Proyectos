<?php
require "../../config/database.php";

if (isset($_GET['act']) && $_GET['act'] == 'insert_ajuste') {
    try {
        session_start(); // Asegúrate de iniciar la sesión para capturar el usuario
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Capturar datos del formulario
        $ajuste_id = $_POST['ajuste_id'];
        $ajuste_fecha = $_POST['ajuste_fecha'];
        $ajuste_motivo = $_POST['motivo'];
        $detalles = json_decode($_POST['detalles'], true); // Detalles enviados como JSON
        $usua_id = $_SESSION['usua_id'] ?? null; // Usuario autenticado

        if (!$usua_id) {
            throw new Exception("Usuario no autenticado.");
        }

        // Obtener el `deposito_id` basándose en la primera materia prima (o implementar lógica personalizada)
        $deposito_id = null;
        if (!empty($detalles)) {
            $mat_id = $detalles[0]['codigo']; // Usar la primera materia prima como referencia
            $queryDeposito = $pdo->prepare("SELECT deposito_id FROM stock_materias WHERE mat_id = :mat_id LIMIT 1");
            $queryDeposito->execute([':mat_id' => $mat_id]);
            $deposito = $queryDeposito->fetch(PDO::FETCH_ASSOC);
            $deposito_id = $deposito['deposito_id'] ?? null;

            if (is_null($deposito_id)) {
                throw new Exception("No se pudo determinar el depósito para la materia prima con ID: {$mat_id}");
            }
        }

        // Insertar en la tabla ajustes
        $stmtAjuste = $pdo->prepare("
            INSERT INTO ajustes (ajuste_id, ajuste_fecha, ajuste_motivo, deposito_id, usua_id,ajuste_estado)
            VALUES (:ajuste_id, :ajuste_fecha, :ajuste_motivo, :deposito_id, :usua_id,'PROCESADO')
        ");
        $stmtAjuste->execute([
            ':ajuste_id' => $ajuste_id,
            ':ajuste_fecha' => $ajuste_fecha,
            ':ajuste_motivo' => $ajuste_motivo,
            ':deposito_id' => $deposito_id,
            ':usua_id' => $usua_id
        ]);

        // Insertar detalles y actualizar stock
        $stmtDetalle = $pdo->prepare("
            INSERT INTO ajuste_detalle (ajuste_id, mat_id, ajuste_cantidad)
            VALUES (:ajuste_id, :mat_id, :ajuste_cantidad)
        ");
        $stmtStock = $pdo->prepare("
            UPDATE stock_materias
            SET stock_existencia = stock_existencia + :ajuste_cantidad
            WHERE mat_id = :mat_id AND deposito_id = :deposito_id
        ");

        foreach ($detalles as $detalle) {
            $mat_id = $detalle['codigo'];
            $ajuste_cantidad = $detalle['cantidad'];

            // Insertar en ajuste_detalle
            $stmtDetalle->execute([
                ':ajuste_id' => $ajuste_id,
                ':mat_id' => $mat_id,
                ':ajuste_cantidad' => $ajuste_cantidad
            ]);

            // Calcular el ajuste para el stock
            $ajuste_cantidad = ($ajuste_motivo === 'faltante') ? $ajuste_cantidad : -$ajuste_cantidad;

            // Actualizar stock
            $stmtStock->execute([
                ':ajuste_cantidad' => $ajuste_cantidad,
                ':mat_id' => $mat_id,
                ':deposito_id' => $deposito_id
            ]);
        }

        // Redirigir con éxito
        header("Location: view.php?alert=1");
        exit;
    } catch (PDOException $e) {
        error_log("Error en la operación de ajuste: " . $e->getMessage());
        header("Location: view.php?alert=4");
    } catch (Exception $e) {
        error_log("Error general: " . $e->getMessage());
        header("Location: view.php?alert=4");
    }
}

if (isset($_GET['act']) && $_GET['act'] == 'anular') {
    try {
        session_start();
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Capturar el ID del ajuste
        $ajuste_id = $_GET['ajuste_id'];

        // Verificar si el ajuste existe y está activo
        $stmtAjuste = $pdo->prepare("SELECT ajuste_motivo FROM ajustes WHERE ajuste_id = :ajuste_id");
        $stmtAjuste->execute([':ajuste_id' => $ajuste_id]);
        $ajuste = $stmtAjuste->fetch(PDO::FETCH_ASSOC);

        if (!$ajuste) {
            throw new Exception("El ajuste no existe o ya está anulado.");
        }

        $ajuste_motivo = $ajuste['ajuste_motivo'];

        // Obtener los detalles del ajuste
        $stmtDetalles = $pdo->prepare("SELECT mat_id, ajuste_cantidad FROM ajuste_detalle WHERE ajuste_id = :ajuste_id");
        $stmtDetalles->execute([':ajuste_id' => $ajuste_id]);
        $detalles = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);

        // Actualizar el stock según el motivo del ajuste
        $stmtStock = $pdo->prepare("
            UPDATE stock_materias
            SET stock_existencia = stock_existencia + :cantidad
            WHERE mat_id = :mat_id
        ");

        foreach ($detalles as $detalle) {
            $ajuste_cantidad = $detalle['ajuste_cantidad'];
            if ($ajuste_motivo === 'faltante') {
                $ajuste_cantidad = -$ajuste_cantidad; // Si era faltante, ahora sumamos
            } else if ($ajuste_motivo === 'sobrante') {
                $ajuste_cantidad = abs($ajuste_cantidad); // Si era sobrante, ahora restamos
            }

            $stmtStock->execute([
                ':cantidad' => $ajuste_cantidad,
                ':mat_id' => $detalle['mat_id']
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
        header("Location: view.php?alert=4");
    } catch (Exception $e) {
        error_log("Error general: " . $e->getMessage());
        header("Location: view.php?alert=4");
    }
}
?>
