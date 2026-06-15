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

if (!check_permission('PERDIDAS', false)) {
    header('Location: view.php?alert=4');
    exit;
}

function bitacoraPerdidas(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void
{
    try {
        $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ")->execute([
            ':id_usuario' => $idUsuario,
            ':entidad' => 'perdidas',
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

function descontarStock(PDO $pdo, int $productoId, int $depositoId, int $cantidad): bool
{
    if ($depositoId <= 0) {
        return false;
    }
    $st = $pdo->prepare("
        UPDATE stock_producto
        SET stock_prod_existente = GREATEST(0, stock_prod_existente - :cant)
        WHERE producto_id = :p AND deposito_id = :d AND stock_prod_existente >= :cant
    ");
    $st->execute([':cant' => $cantidad, ':p' => $productoId, ':d' => $depositoId]);
    return $st->rowCount() > 0;
}

function reponerStock(PDO $pdo, int $productoId, int $depositoId, int $cantidad): void
{
    if ($cantidad <= 0 || $depositoId <= 0) {
        return;
    }
    $st = $pdo->prepare("
        SELECT id_stock_productos FROM stock_producto
        WHERE producto_id = :p AND deposito_id = :d
    ");
    $st->execute([':p' => $productoId, ':d' => $depositoId]);
    if ($st->fetchColumn()) {
        $pdo->prepare("
            UPDATE stock_producto
            SET stock_prod_existente = stock_prod_existente + :cant
            WHERE producto_id = :p AND deposito_id = :d
        ")->execute([':cant' => $cantidad, ':p' => $productoId, ':d' => $depositoId]);
    }
}

function normalizarLineas(array $items): array
{
    $lineas = [];
    foreach ($items as $item) {
        $productoId = (int)($item['producto_id'] ?? 0);
        $cantidad = (int)($item['cantidad'] ?? $item['perdida_cantidad'] ?? 0);
        $motivo = trim((string)($item['motivo'] ?? $item['perdida_motivo'] ?? ''));
        $depositoId = (int)($item['deposito_id'] ?? 0);
        if ($productoId <= 0 || $cantidad <= 0 || $motivo === '') {
            continue;
        }
        if (strlen($motivo) > 30) {
            $motivo = substr($motivo, 0, 30);
        }
        if (!isset($lineas[$productoId])) {
            $lineas[$productoId] = [
                'producto_id' => $productoId,
                'cantidad' => 0,
                'motivo' => $motivo,
                'deposito_id' => $depositoId,
            ];
        }
        $lineas[$productoId]['cantidad'] += $cantidad;
        $lineas[$productoId]['motivo'] = $motivo;
        if ($depositoId > 0) {
            $lineas[$productoId]['deposito_id'] = $depositoId;
        }
    }
    return array_values($lineas);
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
        $calidadId = (int)($_POST['calidad_id'] ?? 0);
        $tipoPerdidaId = (int)($_POST['tipo_perdida_id'] ?? 1);
        $fecha = trim((string)($_POST['perdida_fecha'] ?? date('Y-m-d')));

        $items = [];
        if (!empty($_POST['items'])) {
            $tmp = json_decode($_POST['items'], true);
            if (is_array($tmp)) {
                $items = $tmp;
            }
        }
        $lineas = normalizarLineas($items);

        $usuarioId = resolverUsuario($pdo);
        if ($calidadId <= 0 || $tipoPerdidaId <= 0 || $usuarioId <= 0 || empty($lineas)) {
            header('Location: view.php?alert=4&msg=' . urlencode('Complete control de calidad, tipo y al menos un ítem.'));
            exit;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            header('Location: view.php?alert=4&msg=' . urlencode('Fecha inválida.'));
            exit;
        }

        $pdo->beginTransaction();

        $stCal = $pdo->prepare("
            SELECT calidad_estado, terminado_id
            FROM control_calidad_produccion
            WHERE calidad_id = :id FOR UPDATE
        ");
        $stCal->execute([':id' => $calidadId]);
        $cal = $stCal->fetch();
        if (!$cal || strtoupper(trim((string)$cal['calidad_estado'])) !== 'NO CONFORME') {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('Control de calidad no válido para pérdida.'));
            exit;
        }

        $stDup = $pdo->prepare("
            SELECT perdidas_id FROM perdidas
            WHERE calidad_id = :id AND UPPER(TRIM(perdida_estado)) <> 'ANULADO'
        ");
        $stDup->execute([':id' => $calidadId]);
        if ($stDup->fetchColumn()) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('Ya existe una pérdida para este control.'));
            exit;
        }

        $stTipo = $pdo->prepare('SELECT 1 FROM tipo_perdida WHERE tipo_perdida_id = :id');
        $stTipo->execute([':id' => $tipoPerdidaId]);
        if (!$stTipo->fetchColumn()) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('Tipo de pérdida inválido.'));
            exit;
        }

        foreach ($lineas as $ln) {
            $stMax = $pdo->prepare("
                SELECT MAX(cd.calidad_cantidad)::int
                FROM control_calidad_detalle cd
                WHERE cd.calidad_id = :c AND cd.producto_id = :p
            ");
            $stMax->execute([':c' => $calidadId, ':p' => $ln['producto_id']]);
            $maxCant = (int)$stMax->fetchColumn();
            if ($ln['cantidad'] > $maxCant) {
                $pdo->rollBack();
                header('Location: view.php?alert=4&msg=' . urlencode(
                    "Cantidad supera lo inspeccionado ({$maxCant}) para producto #{$ln['producto_id']}."
                ));
                exit;
            }

            if ($ln['deposito_id'] <= 0) {
                $stDep = $pdo->prepare("
                    SELECT ptd.deposito_id FROM productos_terminados_detalle ptd
                    WHERE ptd.terminado_id = :t AND ptd.producto_id = :p
                ");
                $stDep->execute([':t' => (int)$cal['terminado_id'], ':p' => $ln['producto_id']]);
                $ln['deposito_id'] = (int)$stDep->fetchColumn();
            }
        }

        $ins = $pdo->prepare("
            INSERT INTO perdidas (
                perdida_estado, perdida_fecha, tipo_perdida_id, calidad_id, control_id, id_usuario
            ) VALUES (
                'REGISTRADO', :fecha, :tipo, :calidad, NULL, :usuario
            )
            RETURNING perdidas_id
        ");
        $ins->execute([
            ':fecha' => $fecha,
            ':tipo' => $tipoPerdidaId,
            ':calidad' => $calidadId,
            ':usuario' => $usuarioId,
        ]);
        $perdidasId = (int)$ins->fetchColumn();

        $insDet = $pdo->prepare("
            INSERT INTO perdidas_detalle (perdidas_id, producto_id, perdida_cantidad, perdida_motivo)
            VALUES (:pid, :prod, :cant, :motivo)
        ");

        foreach ($lineas as $ln) {
            $dep = (int)$ln['deposito_id'];
            if (!descontarStock($pdo, $ln['producto_id'], $dep, $ln['cantidad'])) {
                $pdo->rollBack();
                header('Location: view.php?alert=4&msg=' . urlencode(
                    "Stock insuficiente para producto #{$ln['producto_id']} en depósito #{$dep}."
                ));
                exit;
            }
            $insDet->execute([
                ':pid' => $perdidasId,
                ':prod' => $ln['producto_id'],
                ':cant' => $ln['cantidad'],
                ':motivo' => $ln['motivo'],
            ]);
        }

        bitacoraPerdidas($pdo, $usuarioId, 'ALTA',
            "Pérdida #{$perdidasId} por control calidad #{$calidadId}, " . count($lineas) . ' ítem(s)',
            $perdidasId);

        $pdo->commit();
        header('Location: view.php?alert=1');
        exit;
    }

    if ($action === 'update' && isset($_POST['Guardar'])) {
        $perdidasId = (int)($_POST['perdidas_id'] ?? 0);
        $fecha = trim((string)($_POST['perdida_fecha'] ?? ''));
        $tipoPerdidaId = (int)($_POST['tipo_perdida_id'] ?? 0);

        $usuarioId = resolverUsuario($pdo);
        if ($perdidasId <= 0 || $tipoPerdidaId <= 0 || $usuarioId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            header('Location: view.php?alert=4');
            exit;
        }

        $pdo->beginTransaction();

        $st = $pdo->prepare("
            SELECT perdida_estado FROM perdidas WHERE perdidas_id = :id FOR UPDATE
        ");
        $st->execute([':id' => $perdidasId]);
        $estado = strtoupper(trim((string)$st->fetchColumn()));
        if ($estado !== 'REGISTRADO') {
            $pdo->rollBack();
            header('Location: view.php?alert=5');
            exit;
        }

        $pdo->prepare("
            UPDATE perdidas
            SET perdida_fecha = :f, tipo_perdida_id = :t
            WHERE perdidas_id = :id
        ")->execute([':f' => $fecha, ':t' => $tipoPerdidaId, ':id' => $perdidasId]);

        bitacoraPerdidas($pdo, $usuarioId, 'MODIFICACION',
            "Se actualiza fecha/tipo de pérdida #{$perdidasId}", $perdidasId);

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
