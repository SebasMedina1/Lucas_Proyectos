<?php
session_start();

if (!isset($_SESSION['id_usuario']) && isset($_SESSION['usua_id'])) {
    $_SESSION['id_usuario'] = $_SESSION['usua_id'];
}

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');
require_once __DIR__ . '/reposicion_helper.php';

if (empty($_SESSION['username'])) {
    echo "<script>alert('Sesión inválida'); window.location.href='../../login.html';</script>";
    exit;
}

if (!check_permission('REPOSICION_MATERIA', false)) {
    header('Location: view.php?alert=4');
    exit;
}

if (!isset($_GET['act'])) {
    header('Location: view.php?alert=4');
    exit;
}

$action = $_GET['act'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    if ($action === 'insert' && isset($_POST['Guardar'])) {
        $pedidoId = (int)($_POST['id_pedido_mat_prod'] ?? 0);
        $fecha = trim((string)($_POST['reposicion_fecha'] ?? date('Y-m-d')));

        $items = [];
        if (!empty($_POST['items'])) {
            $tmp = json_decode($_POST['items'], true);
            if (is_array($tmp)) {
                $items = $tmp;
            }
        }
        $lineas = normalizarLineasRep($items);

        $usuarioId = resolverUsuarioRep($pdo);
        if ($usuarioId <= 0 || $pedidoId <= 0 || empty($lineas)) {
            header('Location: view.php?alert=4&msg=' . urlencode('Seleccione pedido e ingrese al menos una cantidad.'));
            exit;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            header('Location: view.php?alert=4&msg=' . urlencode('Fecha inválida.'));
            exit;
        }

        $pdo->beginTransaction();

        $stPed = $pdo->prepare("
            SELECT deposito_id, ped_mat_prod_estado
            FROM pedido_materia_produccion
            WHERE id_pedido_mat_prod = :id FOR UPDATE
        ");
        $stPed->execute([':id' => $pedidoId]);
        $ped = $stPed->fetch();
        if (!$ped) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('Pedido no encontrado.'));
            exit;
        }
        $estPed = strtoupper(trim((string)$ped['ped_mat_prod_estado']));
        if (!in_array($estPed, ['PENDIENTE', 'PARCIAL'], true)) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('El pedido no está disponible para reposición.'));
            exit;
        }
        $depositoId = (int)$ped['deposito_id'];

        foreach ($lineas as $mpId => $cant) {
            $stDet = $pdo->prepare("
                SELECT ped_mat_prod_cantidad, cantidad_repuesta
                FROM pedido_materia_detalle_produccion
                WHERE id_pedido_mat_prod = :p AND id_materia_prima = :mp
                FOR UPDATE
            ");
            $stDet->execute([':p' => $pedidoId, ':mp' => $mpId]);
            $det = $stDet->fetch();
            if (!$det) {
                $pdo->rollBack();
                header('Location: view.php?alert=4&msg=' . urlencode("Materia prima #{$mpId} no pertenece al pedido."));
                exit;
            }
            $pendiente = (int)$det['ped_mat_prod_cantidad'] - (int)$det['cantidad_repuesta'];
            if ($cant > $pendiente) {
                $pdo->rollBack();
                header('Location: view.php?alert=4&msg=' . urlencode(
                    "Cantidad supera lo pendiente para MP #{$mpId} (máx. {$pendiente})."
                ));
                exit;
            }
        }

        $ins = $pdo->prepare("
            INSERT INTO reposicion_materia (
                reposicion_fecha, reposicion_estado, deposito_id, id_usuario, id_pedido_mat_prod
            ) VALUES (:fecha, 'REGISTRADO', :dep, :u, :ped)
            RETURNING reposicion_id
        ");
        $ins->execute([
            ':fecha' => $fecha,
            ':dep' => $depositoId,
            ':u' => $usuarioId,
            ':ped' => $pedidoId,
        ]);
        $reposicionId = (int)$ins->fetchColumn();

        $insDet = $pdo->prepare("
            INSERT INTO reposicion_materia_detalle (reposicion_id, id_materia_prima, reposicion_cantidad)
            VALUES (:r, :mp, :cant)
        ");
        $updDet = $pdo->prepare("
            UPDATE pedido_materia_detalle_produccion
            SET cantidad_repuesta = cantidad_repuesta + :cant
            WHERE id_pedido_mat_prod = :p AND id_materia_prima = :mp
        ");

        foreach ($lineas as $mpId => $cant) {
            $insDet->execute([':r' => $reposicionId, ':mp' => $mpId, ':cant' => $cant]);
            $updDet->execute([':cant' => $cant, ':p' => $pedidoId, ':mp' => $mpId]);
            incrementarStockMp($pdo, $mpId, $depositoId, $cant, $usuarioId);
        }

        actualizarEstadoPedido($pdo, $pedidoId);

        bitacoraRep($pdo, $usuarioId, 'ALTA',
            "Reposición #{$reposicionId} — pedido MP #{$pedidoId}, " . count($lineas) . ' ítem(s)',
            $reposicionId);

        $pdo->commit();
        header('Location: view.php?alert=1');
        exit;
    }

    if ($action === 'update' && isset($_POST['Guardar'])) {
        $reposicionId = (int)($_POST['reposicion_id'] ?? 0);
        $fecha = trim((string)($_POST['reposicion_fecha'] ?? ''));

        $usuarioId = resolverUsuarioRep($pdo);
        if ($reposicionId <= 0 || $usuarioId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            header('Location: view.php?alert=4');
            exit;
        }

        $pdo->beginTransaction();

        $st = $pdo->prepare("
            SELECT reposicion_estado FROM reposicion_materia WHERE reposicion_id = :id FOR UPDATE
        ");
        $st->execute([':id' => $reposicionId]);
        $est = strtoupper(trim((string)$st->fetchColumn()));
        if ($est !== 'REGISTRADO') {
            $pdo->rollBack();
            header('Location: view.php?alert=5&msg=' . urlencode('Solo se puede editar una reposición REGISTRADA.'));
            exit;
        }

        $pdo->prepare('UPDATE reposicion_materia SET reposicion_fecha = :f WHERE reposicion_id = :id')
            ->execute([':f' => $fecha, ':id' => $reposicionId]);

        bitacoraRep($pdo, $usuarioId, 'MODIFICACION',
            "Fecha actualizada reposición #{$reposicionId}", $reposicionId);

        $pdo->commit();
        header('Location: view.php?alert=2');
        exit;
    }

    header('Location: view.php?alert=4');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: view.php?alert=4&msg=' . urlencode($e->getMessage()));
}
