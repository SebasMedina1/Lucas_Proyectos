<?php
session_start();

if (!isset($_SESSION['id_usuario']) && isset($_SESSION['usua_id'])) {
    $_SESSION['id_usuario'] = $_SESSION['usua_id'];
}

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');

if (empty($_SESSION['username'])) {
    echo "<script>alert('Sesión inválida'); window.location.href='../../login.html';</script>";
    exit;
}

if (!check_permission('CONTROL_CALIDAD', false)) {
    header('Location: view.php?alert=4');
    exit;
}

function bitacoraCalidad(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void
{
    try {
        $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ")->execute([
            ':id_usuario' => $idUsuario,
            ':entidad' => 'control calidad',
            ':id_registro' => $idRegistro,
            ':accion' => strtoupper($accion),
            ':descripcion' => $descripcion,
        ]);
    } catch (Throwable $e) {
        error_log('Bitácora falló: ' . $e->getMessage());
    }
}

function resolverUsuario(PDO $pdo): int
{
    $usuarioId = (int)($_SESSION['usua_id'] ?? $_SESSION['id_usuario'] ?? 0);
    if ($usuarioId === 0) {
        $q = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1');
        $q->execute([':u' => $_SESSION['username'] ?? '']);
        $usuarioId = (int)$q->fetchColumn();
    }
    return $usuarioId;
}

function normalizarEvaluaciones(array $items): array
{
    $out = [];
    foreach ($items as $item) {
        $productoId = (int)($item['producto_id'] ?? 0);
        $cantidad = (int)($item['calidad_cantidad'] ?? $item['cantidad'] ?? 0);
        if ($productoId <= 0 || $cantidad <= 0) {
            continue;
        }
        $params = [];
        foreach ($item['parametros'] ?? [] as $p) {
            $paramId = (int)($p['parametro_id'] ?? 0);
            if ($paramId <= 0) {
                continue;
            }
            $cumple = !empty($p['cumple']) || (isset($p['cumple_parametro']) && $p['cumple_parametro']);
            $params[] = [
                'parametro_id' => $paramId,
                'valor_medido' => isset($p['valor_medido']) ? trim((string)$p['valor_medido']) : null,
                'cumple' => $cumple,
            ];
        }
        if (!empty($params)) {
            $out[] = [
                'producto_id' => $productoId,
                'calidad_cantidad' => $cantidad,
                'parametros' => $params,
            ];
        }
    }
    return $out;
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
        $terminadoId = (int)($_POST['terminado_id'] ?? 0);
        $inspectorId = (int)($_POST['id_inspectores'] ?? 0);
        $fecha = trim((string)($_POST['calidad_fecha'] ?? date('Y-m-d')));

        $evaluaciones = [];
        if (!empty($_POST['evaluaciones'])) {
            $tmp = json_decode($_POST['evaluaciones'], true);
            if (is_array($tmp)) {
                $evaluaciones = normalizarEvaluaciones($tmp);
            }
        }

        $usuarioId = resolverUsuario($pdo);
        if ($terminadoId <= 0 || $inspectorId <= 0 || $usuarioId <= 0 || empty($evaluaciones)) {
            header('Location: view.php?alert=4&msg=' . urlencode('Complete lote, inspector y evaluación de parámetros.'));
            exit;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            header('Location: view.php?alert=4&msg=' . urlencode('Fecha inválida.'));
            exit;
        }

        $pdo->beginTransaction();

        $stPt = $pdo->prepare('SELECT orden_id FROM producto_terminado WHERE terminado_id = :id FOR UPDATE');
        $stPt->execute([':id' => $terminadoId]);
        if (!$stPt->fetchColumn()) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('Lote PT no encontrado.'));
            exit;
        }

        $stDup = $pdo->prepare("
            SELECT calidad_id FROM control_calidad_produccion
            WHERE terminado_id = :id AND UPPER(TRIM(calidad_estado)) <> 'ANULADO'
            LIMIT 1
        ");
        $stDup->execute([':id' => $terminadoId]);
        if ($stDup->fetchColumn()) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('El lote ya tiene un control de calidad activo.'));
            exit;
        }

        $stInsp = $pdo->prepare("
            SELECT 1 FROM inspectores WHERE id_inspectores = :id AND UPPER(TRIM(inspector_estado)) = 'ACTIVO'
        ");
        $stInsp->execute([':id' => $inspectorId]);
        if (!$stInsp->fetchColumn()) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('Inspector no válido.'));
            exit;
        }

        $veredictoGlobal = 'APROBADO';
        $lineasDetalle = [];

        foreach ($evaluaciones as $ev) {
            $productoId = $ev['producto_id'];
            $stCant = $pdo->prepare("
                SELECT terminado_cantidad FROM productos_terminados_detalle
                WHERE terminado_id = :t AND producto_id = :p
            ");
            $stCant->execute([':t' => $terminadoId, ':p' => $productoId]);
            $cantPt = (int)$stCant->fetchColumn();
            if ($cantPt <= 0) {
                $pdo->rollBack();
                header('Location: view.php?alert=4&msg=' . urlencode("Producto #{$productoId} no pertenece al lote."));
                exit;
            }
            if ($ev['calidad_cantidad'] > $cantPt) {
                $pdo->rollBack();
                header('Location: view.php?alert=4&msg=' . urlencode(
                    "Cantidad inspeccionada mayor a la del lote ({$cantPt})."
                ));
                exit;
            }

            $productoOk = true;
            foreach ($ev['parametros'] as $par) {
                $stPar = $pdo->prepare("
                    SELECT 1 FROM parametros_control
                    WHERE parametro_id = :par AND producto_id = :prod
                      AND UPPER(TRIM(parametro_estado)) = 'ACTIVO'
                ");
                $stPar->execute([':par' => $par['parametro_id'], ':prod' => $productoId]);
                if (!$stPar->fetchColumn()) {
                    $pdo->rollBack();
                    header('Location: view.php?alert=4&msg=' . urlencode('Parámetro no válido para el producto.'));
                    exit;
                }
                if (!$par['cumple']) {
                    $productoOk = false;
                }
            }
            $estadoProducto = $productoOk ? 'APROBADO' : 'NO CONFORME';
            if (!$productoOk) {
                $veredictoGlobal = 'NO CONFORME';
            }
            foreach ($ev['parametros'] as $par) {
                $lineasDetalle[] = [
                    'producto_id' => $productoId,
                    'calidad_cantidad' => $ev['calidad_cantidad'],
                    'parametro_id' => $par['parametro_id'],
                    'valor_medido' => $par['valor_medido'],
                    'cumple' => $par['cumple'],
                    'estado_producto' => $estadoProducto,
                ];
            }
        }

        $ins = $pdo->prepare("
            INSERT INTO control_calidad_produccion (
                calidad_fecha, calidad_estado, id_inspectores, id_usuario, terminado_id
            ) VALUES (
                :fecha, :estado, :inspector, :usuario, :terminado
            )
            RETURNING calidad_id
        ");
        $ins->execute([
            ':fecha' => $fecha,
            ':estado' => $veredictoGlobal,
            ':inspector' => $inspectorId,
            ':usuario' => $usuarioId,
            ':terminado' => $terminadoId,
        ]);
        $calidadId = (int)$ins->fetchColumn();

        $insDet = $pdo->prepare("
            INSERT INTO control_calidad_detalle (
                calidad_id, producto_id, calidad_estado, calidad_cantidad,
                parametro_id, valor_medido, cumple_parametro
            ) VALUES (
                :cid, :prod, :est, :cant, :par, :valor, :cumple
            )
        ");
        foreach ($lineasDetalle as $ln) {
            $insDet->execute([
                ':cid' => $calidadId,
                ':prod' => $ln['producto_id'],
                ':est' => $ln['estado_producto'],
                ':cant' => $ln['calidad_cantidad'],
                ':par' => $ln['parametro_id'],
                ':valor' => $ln['valor_medido'] !== '' ? $ln['valor_medido'] : null,
                ':cumple' => $ln['cumple'] ? 'true' : 'false',
            ]);
        }

        bitacoraCalidad($pdo, $usuarioId, 'ALTA',
            "Control de calidad #{$calidadId} — lote PT #{$terminadoId}, veredicto {$veredictoGlobal}",
            $calidadId);

        $pdo->commit();

        $alert = $veredictoGlobal === 'NO CONFORME' ? '6' : '1';
        header('Location: view.php?alert=' . $alert);
        exit;
    }

    if ($action === 'update' && isset($_POST['Guardar'])) {
        $calidadId = (int)($_POST['calidad_id'] ?? 0);
        $inspectorId = (int)($_POST['id_inspectores'] ?? 0);
        $fecha = trim((string)($_POST['calidad_fecha'] ?? ''));

        $usuarioId = resolverUsuario($pdo);
        if ($calidadId <= 0 || $inspectorId <= 0 || $usuarioId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            header('Location: view.php?alert=4');
            exit;
        }

        $pdo->beginTransaction();

        $st = $pdo->prepare("
            SELECT calidad_estado FROM control_calidad_produccion WHERE calidad_id = :id FOR UPDATE
        ");
        $st->execute([':id' => $calidadId]);
        $estado = strtoupper(trim((string)$st->fetchColumn()));
        if ($estado === 'ANULADO' || $estado === '') {
            $pdo->rollBack();
            header('Location: view.php?alert=5');
            exit;
        }

        $stPer = $pdo->prepare('SELECT COUNT(*) FROM perdidas WHERE calidad_id = :id');
        $stPer->execute([':id' => $calidadId]);
        if ((int)$stPer->fetchColumn() > 0) {
            $pdo->rollBack();
            header('Location: view.php?alert=5&msg=' . urlencode('No se puede editar: tiene pérdidas registradas.'));
            exit;
        }

        $stInsp = $pdo->prepare("
            SELECT 1 FROM inspectores WHERE id_inspectores = :id AND UPPER(TRIM(inspector_estado)) = 'ACTIVO'
        ");
        $stInsp->execute([':id' => $inspectorId]);
        if (!$stInsp->fetchColumn()) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('Inspector no válido.'));
            exit;
        }

        $pdo->prepare("
            UPDATE control_calidad_produccion
            SET calidad_fecha = :f, id_inspectores = :insp
            WHERE calidad_id = :id
        ")->execute([':f' => $fecha, ':insp' => $inspectorId, ':id' => $calidadId]);

        bitacoraCalidad($pdo, $usuarioId, 'MODIFICACION',
            "Se actualizan fecha/inspector del control de calidad #{$calidadId}", $calidadId);

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
