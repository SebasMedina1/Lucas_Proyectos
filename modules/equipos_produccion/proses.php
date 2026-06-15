<?php
session_start();

if (!isset($_SESSION['id_usuario']) && isset($_SESSION['usua_id'])) {
    $_SESSION['id_usuario'] = $_SESSION['usua_id'];
}

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');
require_once __DIR__ . '/equipos_helper.php';

if (empty($_SESSION['username'])) {
    echo "<script>alert('Sesión inválida'); window.location.href='../../login.html';</script>";
    exit;
}

if (!check_permission('EQUIPOS_PRODUCCION', false)) {
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

    [$usuarioId, $sucursalId] = resolverUsuarioEquipo($pdo);
    $fechaHoy = trim((string)($_POST['equipo_fecha'] ?? date('Y-m-d')));

    if ($action === 'insert' && isset($_POST['Guardar'])) {
        $ordenId = (int)($_POST['orden_id'] ?? 0);
        $descri = trim((string)($_POST['equipo_descri'] ?? ''));
        $etapaNombre = trim((string)($_POST['etapa_nombre'] ?? ''));
        $payload = json_decode($_POST['miembros'] ?? '[]', true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $miembros = parseMiembrosEquipo($payload);

        if ($usuarioId <= 0 || $ordenId <= 0 || $etapaNombre === '' || empty($miembros)) {
            header('Location: view.php?alert=4&msg=' . urlencode('Complete OP, etapa y al menos un trabajador.'));
            exit;
        }

        if ($descri === '') {
            $descri = sprintf('Eq. OP-%d / %s', $ordenId, substr($etapaNombre, 0, 15));
        }
        $descri = substr($descri, 0, 30);

        $pdo->beginTransaction();

        $stOp = $pdo->prepare('SELECT orden_prod_estado FROM orden_produccion WHERE orden_id = :id FOR UPDATE');
        $stOp->execute([':id' => $ordenId]);
        $estOp = strtoupper(trim((string)$stOp->fetchColumn()));
        if (!in_array($estOp, ['PENDIENTE', 'EN_PROCESO'], true)) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('OP no disponible para asignar equipos.'));
            exit;
        }

        $etapas = etapasDeOrden($pdo, $ordenId);
        $nombresValidos = array_map(static fn($e) => trim((string)$e['etapa_nombre']), $etapas);
        if (!in_array($etapaNombre, $nombresValidos, true)) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('Etapa no válida para los productos de esta OP.'));
            exit;
        }

        foreach ($miembros as $m) {
            $stT = $pdo->prepare("
                SELECT COUNT(*) FROM trabajadores
                WHERE trabajadores_id = :id AND UPPER(TRIM(trabajador_estado)) = 'ACTIVO'
            ");
            $stT->execute([':id' => $m['trabajadores_id']]);
            if ((int)$stT->fetchColumn() === 0) {
                $pdo->rollBack();
                header('Location: view.php?alert=4&msg=' . urlencode('Trabajador no activo.'));
                exit;
            }
        }

        $ins = $pdo->prepare("
            INSERT INTO equipos_produccion (equipo_descri, id_usuario, equipo_estado, orden_id, equipo_fecha, id_sucursal)
            VALUES (:d, :u, 'ACTIVO', :o, :f, :s)
            RETURNING equipo_id
        ");
        $ins->execute([
            ':d' => $descri,
            ':u' => $usuarioId,
            ':o' => $ordenId,
            ':f' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHoy) ? $fechaHoy : date('Y-m-d'),
            ':s' => $sucursalId > 0 ? $sucursalId : null,
        ]);
        $equipoId = (int)$ins->fetchColumn();

        $insDet = $pdo->prepare("
            INSERT INTO equipo_detalle (equipo_id, trabajadores_id, tarea_rol)
            VALUES (:eid, :tid, :rol)
        ");
        foreach ($miembros as $m) {
            $insDet->execute([
                ':eid' => $equipoId,
                ':tid' => $m['trabajadores_id'],
                ':rol' => $m['tarea_rol'] !== '' ? $m['tarea_rol'] : $etapaNombre,
            ]);
        }

        bitacoraEquipo($pdo, $usuarioId, 'ALTA',
            "Equipo #{$equipoId} OP #{$ordenId} etapa {$etapaNombre} (" . count($miembros) . ' trab.)', $equipoId);

        $pdo->commit();
        header('Location: view.php?alert=1');
        exit;
    }

    if ($action === 'update' && isset($_POST['Guardar'])) {
        $equipoId = (int)($_POST['equipo_id'] ?? 0);
        $descri = trim((string)($_POST['equipo_descri'] ?? ''));
        $payload = json_decode($_POST['miembros'] ?? '[]', true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $miembros = parseMiembrosEquipo($payload);

        if ($equipoId <= 0 || $usuarioId <= 0 || $descri === '' || empty($miembros)) {
            header('Location: view.php?alert=4');
            exit;
        }

        $pdo->beginTransaction();

        $st = $pdo->prepare('SELECT equipo_estado, orden_id FROM equipos_produccion WHERE equipo_id = :id FOR UPDATE');
        $st->execute([':id' => $equipoId]);
        $cab = $st->fetch();
        if (!$cab || !in_array(strtoupper(trim((string)$cab['equipo_estado'])), ['PENDIENTE', 'ACTIVO'], true)) {
            $pdo->rollBack();
            header('Location: view.php?alert=5&msg=' . urlencode('No se puede editar este equipo.'));
            exit;
        }

        $pdo->prepare('UPDATE equipos_produccion SET equipo_descri = :d, equipo_fecha = :f WHERE equipo_id = :id')
            ->execute([
                ':d' => substr($descri, 0, 30),
                ':f' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHoy) ? $fechaHoy : date('Y-m-d'),
                ':id' => $equipoId,
            ]);

        $pdo->prepare('DELETE FROM equipo_detalle WHERE equipo_id = :id')->execute([':id' => $equipoId]);
        $insDet = $pdo->prepare("INSERT INTO equipo_detalle (equipo_id, trabajadores_id, tarea_rol) VALUES (:eid, :tid, :rol)");
        foreach ($miembros as $m) {
            $insDet->execute([':eid' => $equipoId, ':tid' => $m['trabajadores_id'], ':rol' => $m['tarea_rol']]);
        }

        bitacoraEquipo($pdo, $usuarioId, 'MODIFICACION', "Equipo #{$equipoId} actualizado", $equipoId);

        $pdo->commit();
        header('Location: view.php?alert=2');
        exit;
    }

    header('Location: view.php?alert=4');
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('equipos_produccion/proses.php: ' . $e->getMessage());
    header('Location: view.php?alert=4&msg=' . urlencode('Error: ' . $e->getMessage()));
    exit;
}
