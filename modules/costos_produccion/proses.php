<?php
session_start();

if (!isset($_SESSION['id_usuario']) && isset($_SESSION['usua_id'])) {
    $_SESSION['id_usuario'] = $_SESSION['usua_id'];
}

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');
require_once __DIR__ . '/costos_helper.php';

if (empty($_SESSION['username'])) {
    echo "<script>alert('Sesión inválida'); window.location.href='../../login.html';</script>";
    exit;
}

if (!check_permission('COSTOS_PRODUCCION', false)) {
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

    $usuarioId = resolverUsuarioCosto($pdo);

    if ($action === 'insert' && isset($_POST['Guardar'])) {
        $ordenId = (int)($_POST['orden_id'] ?? 0);
        $fecha = trim((string)($_POST['costo_fecha'] ?? date('Y-m-d')));
        $payload = json_decode($_POST['lineas'] ?? '{}', true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $lineas = parseLineasCosto($payload);

        if ($usuarioId <= 0 || $ordenId <= 0 || empty($lineas) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            header('Location: view.php?alert=4&msg=' . urlencode('Complete la OP y al menos una línea de costo.'));
            exit;
        }

        $pdo->beginTransaction();

        $stOp = $pdo->prepare('SELECT orden_prod_estado FROM orden_produccion WHERE orden_id = :id FOR UPDATE');
        $stOp->execute([':id' => $ordenId]);
        if (!$stOp->fetchColumn()) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('OP no encontrada.'));
            exit;
        }

        if (ordenTieneCostoActivo($pdo, $ordenId)) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('La OP ya tiene un costeo activo.'));
            exit;
        }

        $total = calcularTotalLineas($lineas);
        $ins = $pdo->prepare("
            INSERT INTO costo_produccion (costo_fecha, costo_estado, costo_total, id_usuario, orden_id)
            VALUES (:f, 'PENDIENTE', :t, :u, :o)
            RETURNING costo_id
        ");
        $ins->execute([':f' => $fecha, ':t' => $total, ':u' => $usuarioId, ':o' => $ordenId]);
        $costoId = (int)$ins->fetchColumn();
        insertarDetalleCosto($pdo, $costoId, $lineas);

        bitacoraCosto($pdo, $usuarioId, 'ALTA',
            "Costeo #{$costoId} OP #{$ordenId} — total {$total}", $costoId);

        $pdo->commit();
        header('Location: view.php?alert=1');
        exit;
    }

    if ($action === 'update' && isset($_POST['Guardar'])) {
        $costoId = (int)($_POST['costo_id'] ?? 0);
        $fecha = trim((string)($_POST['costo_fecha'] ?? ''));
        $payload = json_decode($_POST['lineas'] ?? '{}', true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $lineas = parseLineasCosto($payload);

        if ($costoId <= 0 || $usuarioId <= 0 || empty($lineas) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            header('Location: view.php?alert=4');
            exit;
        }

        $pdo->beginTransaction();

        $st = $pdo->prepare('SELECT costo_estado, orden_id FROM costo_produccion WHERE costo_id = :id FOR UPDATE');
        $st->execute([':id' => $costoId]);
        $cab = $st->fetch();
        if (!$cab || strtoupper(trim((string)$cab['costo_estado'])) !== 'PENDIENTE') {
            $pdo->rollBack();
            header('Location: view.php?alert=5&msg=' . urlencode('Solo se editan costeos PENDIENTE.'));
            exit;
        }

        $total = calcularTotalLineas($lineas);
        $pdo->prepare('DELETE FROM costo_detalle_produccion WHERE costo_id = :id')->execute([':id' => $costoId]);
        insertarDetalleCosto($pdo, $costoId, $lineas);
        $pdo->prepare('UPDATE costo_produccion SET costo_fecha = :f, costo_total = :t WHERE costo_id = :id')
            ->execute([':f' => $fecha, ':t' => $total, ':id' => $costoId]);

        bitacoraCosto($pdo, $usuarioId, 'MODIFICACION', "Costeo #{$costoId} actualizado", $costoId);

        $pdo->commit();
        header('Location: view.php?alert=2');
        exit;
    }

    if ($action === 'cerrar' && isset($_POST['costo_id'])) {
        $costoId = (int)$_POST['costo_id'];
        if ($costoId <= 0 || $usuarioId <= 0) {
            header('Location: view.php?alert=4');
            exit;
        }

        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT costo_estado FROM costo_produccion WHERE costo_id = :id FOR UPDATE');
        $st->execute([':id' => $costoId]);
        if (strtoupper(trim((string)$st->fetchColumn())) !== 'PENDIENTE') {
            $pdo->rollBack();
            header('Location: view.php?alert=5');
            exit;
        }
        $pdo->prepare("UPDATE costo_produccion SET costo_estado = 'CERRADO' WHERE costo_id = :id")
            ->execute([':id' => $costoId]);
        bitacoraCosto($pdo, $usuarioId, 'CIERRE', "Costeo #{$costoId} cerrado", $costoId);
        $pdo->commit();
        header('Location: view.php?alert=6');
        exit;
    }

    header('Location: view.php?alert=4');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: view.php?alert=4&msg=' . urlencode($e->getMessage()));
}
