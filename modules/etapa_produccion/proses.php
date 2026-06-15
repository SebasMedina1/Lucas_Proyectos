<?php
session_start();

if (!isset($_SESSION['id_usuario']) && isset($_SESSION['usua_id'])) {
    $_SESSION['id_usuario'] = $_SESSION['usua_id'];
}

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');
require_once __DIR__ . '/etapas_helper.php';

if (empty($_SESSION['username'])) {
    echo "<script>alert('Sesión inválida'); window.location.href='../../login.html';</script>";
    exit;
}

if (!check_permission('ETAPAS_PRODUCCION', false)) {
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

    $usuarioId = resolverUsuarioEtapa($pdo);
    $fechaHoy = date('Y-m-d');

    if ($action === 'insert' && isset($_POST['Guardar'])) {
        $productoId = (int)($_POST['producto_id'] ?? 0);
        $payload = json_decode($_POST['etapas'] ?? '[]', true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $lineas = parseEtapasPayload($payload);
        $err = validarLineasEtapas($lineas);

        if ($usuarioId <= 0 || $productoId <= 0 || $err !== null) {
            header('Location: view.php?alert=4&msg=' . urlencode($err ?: 'Datos incompletos.'));
            exit;
        }

        $pdo->beginTransaction();

        $stProd = $pdo->prepare("
            SELECT producto_id FROM productos
            WHERE producto_id = :id AND UPPER(TRIM(producto_estado)) = 'ACTIVO'
            FOR UPDATE
        ");
        $stProd->execute([':id' => $productoId]);
        if (!$stProd->fetchColumn()) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('Producto no encontrado o inactivo.'));
            exit;
        }

        if (productoTieneRutaActiva($pdo, $productoId)) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('El producto ya tiene una ruta activa. Use editar.'));
            exit;
        }

        foreach ($lineas as $ln) {
            $codigo = $ln['etapa_descri'] !== ''
                ? substr($ln['etapa_descri'], 0, 30)
                : generarCodigoEtapa($productoId, $ln['etapa_secuencia'], $ln['etapa_nombre']);

            $ins = $pdo->prepare("
                INSERT INTO etapa_produccion (etapa_fecha, etapa_descri, id_usuario, producto_id, etapa_estado)
                VALUES (:f, :d, :u, :p, 'ACTIVA')
                RETURNING etapa_id
            ");
            $ins->execute([
                ':f' => $fechaHoy,
                ':d' => $codigo,
                ':u' => $usuarioId,
                ':p' => $productoId,
            ]);
            $etapaId = (int)$ins->fetchColumn();

            $pdo->prepare("
                INSERT INTO etapa_detalle_produccion
                    (etapa_id, producto_id, etapa_nombre, etapa_procedimiento, etapa_secuencia, etapa_tiempo_estimado, etapa_observaciones)
                VALUES (:eid, :pid, :nom, :proc, :seq, :min, :obs)
            ")->execute([
                ':eid' => $etapaId,
                ':pid' => $productoId,
                ':nom' => substr($ln['etapa_nombre'], 0, 30),
                ':proc' => $ln['etapa_procedimiento'],
                ':seq' => $ln['etapa_secuencia'],
                ':min' => $ln['etapa_tiempo_estimado'],
                ':obs' => $ln['etapa_observaciones'],
            ]);
        }

        bitacoraEtapa($pdo, $usuarioId, 'ALTA',
            "Ruta de etapas creada para producto #{$productoId} (" . count($lineas) . ' pasos)', $productoId);

        $pdo->commit();
        header('Location: view.php?alert=1');
        exit;
    }

    if ($action === 'update' && isset($_POST['Guardar'])) {
        $productoId = (int)($_POST['producto_id'] ?? 0);
        $payload = json_decode($_POST['etapas'] ?? '[]', true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $lineas = parseEtapasPayload($payload);
        $err = validarLineasEtapas($lineas);

        if ($usuarioId <= 0 || $productoId <= 0 || $err !== null) {
            header('Location: view.php?alert=4&msg=' . urlencode($err ?: 'Datos incompletos.'));
            exit;
        }

        $pdo->beginTransaction();

        $existentes = cargarEtapasProducto($pdo, $productoId, true);
        if (empty($existentes)) {
            $pdo->rollBack();
            header('Location: view.php?alert=4&msg=' . urlencode('No hay ruta activa para editar.'));
            exit;
        }

        $idsEnviados = array_filter(array_column($lineas, 'etapa_id'));
        $idsExistentes = array_map(static fn($r) => (int)$r['etapa_id'], $existentes);

        foreach ($idsExistentes as $eid) {
            if (!in_array($eid, $idsEnviados, true)) {
                if (etapaEnUso($pdo, $eid)) {
                    $pdo->rollBack();
                    header('Location: view.php?alert=4&msg=' . urlencode('No puede quitar etapas ya usadas en control de producción.'));
                    exit;
                }
                $pdo->prepare("UPDATE etapa_produccion SET etapa_estado = 'ANULADA' WHERE etapa_id = :id")
                    ->execute([':id' => $eid]);
            }
        }

        foreach ($lineas as $ln) {
            $eid = (int)$ln['etapa_id'];
            if ($eid > 0) {
                if (!in_array($eid, $idsExistentes, true)) {
                    $pdo->rollBack();
                    header('Location: view.php?alert=4&msg=' . urlencode('Etapa inválida para este producto.'));
                    exit;
                }
                $enUso = etapaEnUso($pdo, $eid);
                if ($enUso) {
                    $pdo->prepare("
                        UPDATE etapa_detalle_produccion
                        SET etapa_observaciones = :obs
                        WHERE etapa_id = :eid AND producto_id = :pid
                    ")->execute([
                        ':obs' => $ln['etapa_observaciones'],
                        ':eid' => $eid,
                        ':pid' => $productoId,
                    ]);
                } else {
                    $codigo = $ln['etapa_descri'] !== ''
                        ? substr($ln['etapa_descri'], 0, 30)
                        : generarCodigoEtapa($productoId, $ln['etapa_secuencia'], $ln['etapa_nombre']);
                    $pdo->prepare("UPDATE etapa_produccion SET etapa_descri = :d WHERE etapa_id = :id")
                        ->execute([':d' => $codigo, ':id' => $eid]);
                    $pdo->prepare("
                        UPDATE etapa_detalle_produccion
                        SET etapa_nombre = :nom, etapa_procedimiento = :proc, etapa_secuencia = :seq,
                            etapa_tiempo_estimado = :min, etapa_observaciones = :obs
                        WHERE etapa_id = :eid AND producto_id = :pid
                    ")->execute([
                        ':nom' => substr($ln['etapa_nombre'], 0, 30),
                        ':proc' => $ln['etapa_procedimiento'],
                        ':seq' => $ln['etapa_secuencia'],
                        ':min' => $ln['etapa_tiempo_estimado'],
                        ':obs' => $ln['etapa_observaciones'],
                        ':eid' => $eid,
                        ':pid' => $productoId,
                    ]);
                }
            } else {
                $codigo = $ln['etapa_descri'] !== ''
                    ? substr($ln['etapa_descri'], 0, 30)
                    : generarCodigoEtapa($productoId, $ln['etapa_secuencia'], $ln['etapa_nombre']);
                $ins = $pdo->prepare("
                    INSERT INTO etapa_produccion (etapa_fecha, etapa_descri, id_usuario, producto_id, etapa_estado)
                    VALUES (:f, :d, :u, :p, 'ACTIVA')
                    RETURNING etapa_id
                ");
                $ins->execute([
                    ':f' => $fechaHoy,
                    ':d' => $codigo,
                    ':u' => $usuarioId,
                    ':p' => $productoId,
                ]);
                $nuevoId = (int)$ins->fetchColumn();
                $pdo->prepare("
                    INSERT INTO etapa_detalle_produccion
                        (etapa_id, producto_id, etapa_nombre, etapa_procedimiento, etapa_secuencia, etapa_tiempo_estimado, etapa_observaciones)
                    VALUES (:eid, :pid, :nom, :proc, :seq, :min, :obs)
                ")->execute([
                    ':eid' => $nuevoId,
                    ':pid' => $productoId,
                    ':nom' => substr($ln['etapa_nombre'], 0, 30),
                    ':proc' => $ln['etapa_procedimiento'],
                    ':seq' => $ln['etapa_secuencia'],
                    ':min' => $ln['etapa_tiempo_estimado'],
                    ':obs' => $ln['etapa_observaciones'],
                ]);
            }
        }

        bitacoraEtapa($pdo, $usuarioId, 'MODIFICACION', "Ruta actualizada producto #{$productoId}", $productoId);

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
    error_log('etapa_produccion/proses.php: ' . $e->getMessage());
    header('Location: view.php?alert=4&msg=' . urlencode('Error al guardar: ' . $e->getMessage()));
    exit;
}
