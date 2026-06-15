<?php
session_start();

require "../../config/database.php";

// Verificar si el usuario está autenticado
if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}

// Función de bitácora
function bitacora(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void {
    try {
        $check = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'bitacora' LIMIT 1");
        if ($check->rowCount() === 0) {
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ");
        $stmt->execute([
            ':id_usuario'  => $idUsuario,
            ':entidad'     => 'apertura cierre caja',
            ':id_registro' => $idRegistro,
            ':accion'      => strtoupper($accion),
            ':descripcion' => $descripcion
        ]);
    } catch (Throwable $e) {
        error_log("Bitácora falló: ".$e->getMessage());
    }
}

// Detectar la acción
if (isset($_GET['act'])) {
    $action = $_GET['act'];
    
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // INSERT - Crear nueva apertura
        if ($action == 'insert' && isset($_POST['Guardar'])) {
            
            $caja_id = isset($_POST['caja_id']) ? (int)$_POST['caja_id'] : 0;
            $cajero_id = isset($_POST['cajero_id']) ? (int)$_POST['cajero_id'] : 0;
            $monto_inicial = isset($_POST['monto_inicial']) ? (float)$_POST['monto_inicial'] : 0;
            $numero_apertura = isset($_POST['numero_apertura']) ? (int)$_POST['numero_apertura'] : 0;

            // Resolver usuario y sucursal
            $usuario_id = 0;
            $id_sucursal = 0;
            
            if (!empty($_SESSION['usua_id'])) {
                $usuario_id = (int)$_SESSION['usua_id'];
            }
            if (!empty($_SESSION['sucursal_id'])) {
                $id_sucursal = (int)$_SESSION['sucursal_id'];
            }

            if ($usuario_id > 0 && $id_sucursal === 0) {
                $q = $pdo->prepare("SELECT id_sucursal FROM usuarios WHERE id_usuario = :id LIMIT 1");
                $q->execute([':id' => $usuario_id]);
                $id_sucursal = (int)$q->fetchColumn();
            }

            if ($usuario_id === 0) {
                $q = $pdo->prepare("SELECT id_usuario, id_sucursal FROM usuarios WHERE username = :u LIMIT 1");
                $q->execute([':u' => $_SESSION['username'] ?? '']);
                $usr = $q->fetch(PDO::FETCH_ASSOC);
                if ($usr) {
                    $usuario_id  = (int)$usr['id_usuario'];
                    $id_sucursal = (int)$usr['id_sucursal'];
                }
            }

            // Validaciones
            if ($usuario_id <= 0 || $id_sucursal <= 0) {
                die('No se pudo obtener id_usuario o id_sucursal desde la sesión.');
            }
            if ($caja_id <= 0) {
                die('Debe seleccionar una caja.');
            }
            if ($cajero_id <= 0) {
                die('Debe seleccionar un cajero.');
            }
            if ($monto_inicial < 0) {
                die('El monto inicial no puede ser negativo.');
            }

            // Validar que la caja exista y pertenezca a la sucursal
            $qCaja = $pdo->prepare("
                SELECT estado, id_sucursal 
                FROM caja 
                WHERE id_caja = :id 
                LIMIT 1
            ");
            $qCaja->execute([':id' => $caja_id]);
            $cajaData = $qCaja->fetch(PDO::FETCH_ASSOC);
            
            if (!$cajaData) {
                error_log("Apertura caja: Caja ID {$caja_id} no encontrada en la base de datos");
                header("Location: view.php?alert=7");
                exit;
            }
            
            // Validar que la caja pertenezca a la misma sucursal
            if ((int)$cajaData['id_sucursal'] !== $id_sucursal) {
                error_log("Apertura caja: Caja ID {$caja_id} pertenece a sucursal {$cajaData['id_sucursal']}, pero usuario está en sucursal {$id_sucursal}");
                header("Location: view.php?alert=7");
                exit;
            }
            
            // Validar que la caja NO tenga una apertura activa en apertura_cierre_caja
            $qAperturaActiva = $pdo->prepare("
                SELECT COUNT(*) 
                FROM apertura_cierre_caja 
                WHERE id_caja = :id_caja 
                  AND apertura_estado = 'ABIERTA'
            ");
            $qAperturaActiva->execute([':id_caja' => $caja_id]);
            if ($qAperturaActiva->fetchColumn() > 0) {
                header("Location: view.php?alert=7");
                exit;
            }

            // Validar que el cajero exista y esté activo
            $qCajeroCheck = $pdo->prepare("
                SELECT c.cajero_id, c.cajero_estado, p.id_sucursal
                FROM cajero c
                JOIN personal p ON c.id_personal = p.id_personal
                WHERE c.cajero_id = :cajero_id
                LIMIT 1
            ");
            $qCajeroCheck->execute([':cajero_id' => $cajero_id]);
            $cajeroData = $qCajeroCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$cajeroData) {
                error_log("Apertura caja: Cajero ID {$cajero_id} no encontrado");
                header("Location: view.php?alert=7");
                exit;
            }
            
            if (strtoupper(trim($cajeroData['cajero_estado'])) !== 'ACTIVO') {
                error_log("Apertura caja: Cajero ID {$cajero_id} no está ACTIVO (estado: {$cajeroData['cajero_estado']})");
                header("Location: view.php?alert=7");
                exit;
            }
            
            // Validar que el cajero pertenezca a la misma sucursal
            if ((int)$cajeroData['id_sucursal'] !== $id_sucursal) {
                error_log("Apertura caja: Cajero ID {$cajero_id} no pertenece a sucursal {$id_sucursal}");
                header("Location: view.php?alert=7");
                exit;
            }
            
            // Validar que el cajero no tenga otra caja abierta
            $qCajero = $pdo->prepare("
                SELECT COUNT(*) 
                FROM apertura_cierre_caja 
                WHERE cajero_id = :cajero_id 
                  AND apertura_estado = 'ABIERTA'
            ");
            $qCajero->execute([':cajero_id' => $cajero_id]);
            $cajeroTieneApertura = $qCajero->fetchColumn() > 0;
            if ($cajeroTieneApertura) {
                error_log("Apertura caja: Cajero ID {$cajero_id} ya tiene una caja abierta");
                header("Location: view.php?alert=7");
                exit;
            }

            // Iniciar transacción
            $pdo->beginTransaction();

            // Insertar apertura en apertura_cierre_caja
            $query_apertura = $pdo->prepare("
                INSERT INTO apertura_cierre_caja (
                    fecha_apertura,
                    hora_apertura,
                    apertura_estado,
                    monto_apertura,
                    apertura_efectivo,
                    apertura_tarjeta,
                    apertura_cheque,
                    monto_cierre,
                    fecha_cierre,
                    hora_cierre,
                    id_caja,
                    id_usuario,
                    id_sucursal,
                    cajero_id
                ) 
                VALUES (
                    CURRENT_DATE,
                    CURRENT_TIME,
                    'ABIERTA',
                    :monto_apertura,
                    0,
                    0,
                    0,
                    0,
                    CURRENT_DATE,
                    CURRENT_TIME,
                    :id_caja,
                    :id_usuario,
                    :id_sucursal,
                    :cajero_id
                )
                RETURNING id_apertura
            ");
            $query_apertura->execute([
                ':monto_apertura' => (int)$monto_inicial,
                ':id_caja' => $caja_id,
                ':id_usuario' => $usuario_id,
                ':id_sucursal' => $id_sucursal,
                ':cajero_id' => $cajero_id
            ]);
            $apertura_id = $query_apertura->fetchColumn();

            // Actualizar estado de la caja
            $updCaja = $pdo->prepare("UPDATE caja SET estado = 'ABIERTA' WHERE id_caja = :id");
            $updCaja->execute([':id' => $caja_id]);

            bitacora($pdo, $usuario_id, 'ALTA', "Se abre caja #{$caja_id} con monto inicial {$monto_inicial}", $apertura_id);

            $pdo->commit();
            header("Location: view.php?alert=1");
            exit;
        }
        
        // UPDATE - Editar apertura
        elseif ($action === 'update' && isset($_POST['Guardar'])) {
            $apertura_id  = isset($_POST['apertura_id']) ? (int)$_POST['apertura_id'] : 0;
            $cajero_id = isset($_POST['cajero_id']) ? (int)$_POST['cajero_id'] : 0;
            $monto_inicial = isset($_POST['monto_inicial']) ? (float)$_POST['monto_inicial'] : 0;

            // Usuario desde sesión
            if (!empty($_SESSION['usua_id'])) {
                $usuario_id = (int) $_SESSION['usua_id'];
            } else {
                $stmtUid = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
                $stmtUid->execute([':u' => $_SESSION['username'] ?? '']);
                $usuario_id = (int) $stmtUid->fetchColumn();
            }

            if ($apertura_id <= 0 || $usuario_id <= 0) {
                header("Location: view.php?alert=4");
                exit;
            }

            if ($monto_inicial < 0) {
                header("Location: view.php?alert=4");
                exit;
            }

            try {
                $pdo->beginTransaction();

                // Verificar existencia/estado y BLOQUEAR
                $st = $pdo->prepare("
                    SELECT apertura_estado, fecha_apertura
                    FROM apertura_cierre_caja
                    WHERE id_apertura = :id
                    FOR UPDATE
                ");
                $st->execute([':id' => $apertura_id]);
                $apertura = $st->fetch(PDO::FETCH_ASSOC);

                if ($apertura === false) {
                    $pdo->rollBack();
                    header("Location: view.php?alert=4");
                    exit;
                }

                $estadoNorm = strtoupper(trim((string)$apertura['apertura_estado']));
                if ($estadoNorm !== 'ABIERTA') {
                    $pdo->rollBack();
                    header("Location: view.php?alert=5");
                    exit;
                }

                // Verificar que no tenga cobros (en tabla cobros)
                // Nota: cobros.id_apertura referencia a apertura_cierre_caja.id_apertura
                $qCobros = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM cobros 
                    WHERE id_apertura = :id
                      AND estado != 'ANULADO'
                ");
                $qCobros->execute([':id' => $apertura_id]);
                if ($qCobros->fetchColumn() > 0) {
                    $pdo->rollBack();
                    header("Location: view.php?alert=5");
                    exit;
                }

                // Actualizar apertura
                $upd = $pdo->prepare("
                    UPDATE apertura_cierre_caja
                    SET monto_apertura = :monto_apertura,
                        cajero_id = :cajero_id
                    WHERE id_apertura = :apertura_id
                ");
                $upd->execute([
                    ':monto_apertura' => (int)$monto_inicial,
                    ':cajero_id' => $cajero_id,
                    ':apertura_id' => $apertura_id
                ]);

                bitacora($pdo, $usuario_id, 'MODIFICACION', 
                    "Se modifica apertura {$apertura_id}: monto inicial y cajero", 
                    $apertura_id);

                $pdo->commit();
                header("Location: view.php?alert=2");
                exit;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { 
                    $pdo->rollBack(); 
                }
                die("Error al actualizar la apertura: " . $e->getMessage());
            }
        }

        // UPDATE ARQUEO - Editar arqueo existente
        elseif ($action === 'update_arqueo' && isset($_POST['arqueo_id']) && isset($_POST['efectivo_contado'])) {
            $arqueo_id = isset($_POST['arqueo_id']) ? (int)$_POST['arqueo_id'] : 0;
            $apertura_id = isset($_POST['apertura_id']) ? (int)$_POST['apertura_id'] : 0;
            $efectivo_contado = isset($_POST['efectivo_contado']) ? (float)$_POST['efectivo_contado'] : 0;
            $cheques_contados = isset($_POST['cheques_contados']) ? (float)$_POST['cheques_contados'] : 0;
            $otros_contados = isset($_POST['otros_contados']) ? (float)$_POST['otros_contados'] : 0;
            $observacion = isset($_POST['observacion']) ? trim($_POST['observacion']) : '';

            if (!empty($_SESSION['usua_id'])) {
                $usuario_id = (int) $_SESSION['usua_id'];
            } else {
                $stmtUid = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
                $stmtUid->execute([':u' => $_SESSION['username'] ?? '']);
                $usuario_id = (int) $stmtUid->fetchColumn();
            }

            if ($arqueo_id <= 0 || $apertura_id <= 0) {
                header("Location: view.php?alert=4");
                exit;
            }

            try {
                $pdo->beginTransaction();

                // Verificar que la apertura esté ABIERTA y sea del mismo día
                $qApertura = $pdo->prepare("
                    SELECT apertura_estado, fecha_apertura, monto_apertura
                    FROM apertura_cierre_caja
                    WHERE id_apertura = :id
                    FOR UPDATE
                ");
                $qApertura->execute([':id' => $apertura_id]);
                $apertura = $qApertura->fetch(PDO::FETCH_ASSOC);

                if (!$apertura) {
                    $pdo->rollBack();
                    header("Location: view.php?alert=4");
                    exit;
                }

                if (strtoupper(trim($apertura['apertura_estado'])) !== 'ABIERTA') {
                    $pdo->rollBack();
                    header("Location: view.php?alert=5");
                    exit;
                }

                // Verificar que el arqueo sea del mismo día
                $qArqueo = $pdo->prepare("
                    SELECT fecha_arqueo
                    FROM arqueo_caja
                    WHERE id_arqueo = :id
                ");
                $qArqueo->execute([':id' => $arqueo_id]);
                $arqueo = $qArqueo->fetch(PDO::FETCH_ASSOC);

                if (!$arqueo || date('Y-m-d') !== $arqueo['fecha_arqueo']) {
                    $pdo->rollBack();
                    header("Location: view.php?alert=5");
                    exit;
                }

                // Obtener total de cobros en efectivo (de facturas y cobros)
                // Facturas de contado en efectivo
                $qFacturas = $pdo->prepare("
                    SELECT COALESCE(SUM(total_general), 0)
                    FROM factura_ventas
                    WHERE id_apertura_cierre = :id
                      AND tipo_pago = 'EFECTIVO'
                      AND estado = 'EMITIDA'
                ");
                $qFacturas->execute([':id' => $apertura_id]);
                $total_efectivo_facturas = (float)$qFacturas->fetchColumn();
                
                // Cobros en efectivo
                // Nota: cobros.id_apertura referencia a apertura_cierre_caja.id_apertura
                $qCobros = $pdo->prepare("
                    SELECT COALESCE(SUM(cd.importe_aplicado), 0)
                    FROM cobros c
                    JOIN cobros_detalle cd ON cd.id_cobro = c.id_cobro
                    WHERE c.id_apertura = :id
                      AND cd.tipo_pago = 'EFECTIVO'
                      AND c.estado = 'REGISTRADO'
                ");
                $qCobros->execute([':id' => $apertura_id]);
                $total_efectivo_cobros = (float)$qCobros->fetchColumn();
                
                $total_efectivo_cobrado = $total_efectivo_facturas + $total_efectivo_cobros;

                // Calcular diferencia
                $efectivo_esperado = (float)$apertura['monto_inicial'] + $total_efectivo_cobrado;
                $diferencia_efectivo = $efectivo_esperado - $efectivo_contado;

                // Actualizar arqueo
                $updArqueo = $pdo->prepare("
                    UPDATE arqueo_caja
                    SET efectivo_contado = :efectivo_contado,
                        cheques_contados = :cheques_contados,
                        otros_contados = :otros_contados,
                        diferencia_efectivo = :diferencia_efectivo,
                        observacion = :observacion
                    WHERE id_arqueo = :arqueo_id
                ");
                $updArqueo->execute([
                    ':efectivo_contado' => $efectivo_contado,
                    ':cheques_contados' => $cheques_contados,
                    ':otros_contados' => $otros_contados,
                    ':diferencia_efectivo' => $diferencia_efectivo,
                    ':observacion' => $observacion ?: null,
                    ':arqueo_id' => $arqueo_id
                ]);

                bitacora($pdo, $usuario_id, 'MODIFICACION', 
                    "Arqueo actualizado para apertura {$apertura_id}. Nueva diferencia: {$diferencia_efectivo}", 
                    $apertura_id);

                $pdo->commit();
                header("Location: view.php?alert=10");
                exit;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { 
                    $pdo->rollBack(); 
                }
                die("Error al actualizar el arqueo: " . $e->getMessage());
            }
        }

        // ARQUEO - Registrar arqueo
        elseif ($action === 'arqueo' && isset($_POST['efectivo_contado'])) {
            $apertura_id = isset($_POST['apertura_id']) ? (int)$_POST['apertura_id'] : 0;
            $efectivo_contado = isset($_POST['efectivo_contado']) ? (float)$_POST['efectivo_contado'] : 0;
            $cheques_contados = isset($_POST['cheques_contados']) ? (float)$_POST['cheques_contados'] : 0;
            $otros_contados = isset($_POST['otros_contados']) ? (float)$_POST['otros_contados'] : 0;
            $observacion = isset($_POST['observacion']) ? trim($_POST['observacion']) : '';

            if (!empty($_SESSION['usua_id'])) {
                $usuario_id = (int) $_SESSION['usua_id'];
            } else {
                $stmtUid = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
                $stmtUid->execute([':u' => $_SESSION['username'] ?? '']);
                $usuario_id = (int) $stmtUid->fetchColumn();
            }

            if ($apertura_id <= 0) {
                header("Location: view.php?alert=4");
                exit;
            }

            try {
                $pdo->beginTransaction();

                // Obtener monto inicial y total efectivo esperado
                $qApertura = $pdo->prepare("
                    SELECT 
                        monto_apertura,
                        id_caja, 
                        id_sucursal, 
                        cajero_id,
                        monto_apertura AS monto_inicial,
                        cajero_id AS id_cajero
                    FROM apertura_cierre_caja
                    WHERE id_apertura = :id
                    FOR UPDATE
                ");
                $qApertura->execute([':id' => $apertura_id]);
                $apertura = $qApertura->fetch(PDO::FETCH_ASSOC);

                if (!$apertura) {
                    $pdo->rollBack();
                    header("Location: view.php?alert=4");
                    exit;
                }

                // Obtener total de cobros en efectivo (de facturas y cobros)
                // Facturas de contado en efectivo
                // Nota: factura_ventas.id_apertura_cierre puede referenciar a apertura_cierre, verificar
                $qFacturas = $pdo->prepare("
                    SELECT COALESCE(SUM(total_general), 0)
                    FROM factura_ventas
                    WHERE id_apertura_cierre = :id
                      AND tipo_pago = 'EFECTIVO'
                      AND estado = 'EMITIDA'
                ");
                $qFacturas->execute([':id' => $apertura_id]);
                $total_efectivo_facturas = (float)$qFacturas->fetchColumn();
                
                // Cobros en efectivo
                // Nota: cobros.id_apertura referencia a apertura_cierre_caja.id_apertura
                $qCobros = $pdo->prepare("
                    SELECT COALESCE(SUM(cd.importe_aplicado), 0)
                    FROM cobros c
                    JOIN cobros_detalle cd ON cd.id_cobro = c.id_cobro
                    WHERE c.id_apertura = :id
                      AND cd.tipo_pago = 'EFECTIVO'
                      AND c.estado = 'REGISTRADO'
                ");
                $qCobros->execute([':id' => $apertura_id]);
                $total_efectivo_cobros = (float)$qCobros->fetchColumn();
                
                $total_efectivo_cobrado = $total_efectivo_facturas + $total_efectivo_cobros;

                // Calcular diferencia
                $efectivo_esperado = (float)$apertura['monto_apertura'] + $total_efectivo_cobrado;
                $diferencia_efectivo = $efectivo_esperado - $efectivo_contado;

                // Insertar arqueo
                // Usamos apertura_cierre_caja como tabla definitiva
                // Necesitamos obtener o crear el id_apertura en apertura_cierre_caja
                
                // Usar directamente el id_apertura que ya tenemos
                // Ya que estamos usando apertura_cierre_caja como tabla definitiva,
                // el $apertura_id ES el id_apertura de apertura_cierre_caja
                $aperturaCajaId = $apertura_id;
                
                // Calcular monto_efectivo, arqueo_faltante y arqueo_sobrante
                $monto_efectivo = (int)$efectivo_contado;
                $monto_cheque = (int)$cheques_contados;
                $arqueo_faltante = $diferencia_efectivo < 0 ? (int)abs($diferencia_efectivo) : 0;
                $arqueo_sobrante = $diferencia_efectivo > 0 ? (int)$diferencia_efectivo : 0;
                
                $insArqueo = $pdo->prepare("
                    INSERT INTO arqueo_caja (
                        id_apertura,
                        id_apertura_cierre,
                        monto_efectivo,
                        monto_cheque,
                        monto_tarjeta,
                        arqueo_inicial,
                        arqueo_faltante,
                        arqueo_sobrante,
                        arqueo_estado,
                        id_usuario,
                        fecha_arqueo,
                        hora_arqueo,
                        efectivo_contado,
                        cheques_contados,
                        otros_contados,
                        diferencia_efectivo,
                        observacion
                    )
                    VALUES (
                        :id_apertura,
                        NULL,
                        :monto_efectivo,
                        :monto_cheque,
                        0,
                        'NORMAL',
                        :arqueo_faltante,
                        :arqueo_sobrante,
                        'REGISTRADO',
                        :usuario_id,
                        CURRENT_DATE,
                        CURRENT_TIME,
                        :efectivo_contado,
                        :cheques_contados,
                        :otros_contados,
                        :diferencia_efectivo,
                        :observacion
                    )
                ");
                $insArqueo->execute([
                    ':id_apertura' => $aperturaCajaId,
                    ':monto_efectivo' => $monto_efectivo,
                    ':monto_cheque' => $monto_cheque,
                    ':arqueo_faltante' => $arqueo_faltante,
                    ':arqueo_sobrante' => $arqueo_sobrante,
                    ':usuario_id' => $usuario_id,
                    ':efectivo_contado' => $efectivo_contado,
                    ':cheques_contados' => $cheques_contados,
                    ':otros_contados' => $otros_contados,
                    ':diferencia_efectivo' => $diferencia_efectivo,
                    ':observacion' => $observacion ?: null
                ]);

                bitacora($pdo, $usuario_id, 'ALTA', 
                    "Arqueo registrado para apertura {$apertura_id}. Diferencia: {$diferencia_efectivo}", 
                    $apertura_id);

                $pdo->commit();
                header("Location: view.php?alert=8");
                exit;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { 
                    $pdo->rollBack(); 
                }
                die("Error al registrar el arqueo: " . $e->getMessage());
            }
        }

        // CIERRE - Cerrar caja
        elseif ($action === 'cierre' && isset($_POST['apertura_id'])) {
            $apertura_id = isset($_POST['apertura_id']) ? (int)$_POST['apertura_id'] : 0;
            $efectivo_contado = isset($_POST['efectivo_contado']) ? (float)$_POST['efectivo_contado'] : null;
            $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';

            if (!empty($_SESSION['usua_id'])) {
                $usuario_id = (int) $_SESSION['usua_id'];
            } else {
                $stmtUid = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
                $stmtUid->execute([':u' => $_SESSION['username'] ?? '']);
                $usuario_id = (int) $stmtUid->fetchColumn();
            }

            if ($apertura_id <= 0) {
                header("Location: view.php?alert=4");
                exit;
            }

            try {
                $pdo->beginTransaction();

                // Obtener información de la apertura y BLOQUEAR
                $qApertura = $pdo->prepare("
                    SELECT id_caja, monto_apertura
                    FROM apertura_cierre_caja
                    WHERE id_apertura = :id
                      AND apertura_estado = 'ABIERTA'
                    FOR UPDATE
                ");
                $qApertura->execute([':id' => $apertura_id]);
                $apertura = $qApertura->fetch(PDO::FETCH_ASSOC);

                if (!$apertura) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    header("Location: view.php?alert=4");
                    exit;
                }

                // Obtener totales por tipo de pago (de facturas y cobros)
                // Facturas de contado
                // Nota: factura_ventas.id_apertura_cierre puede referenciar a apertura_cierre, verificar
                $qFacturas = $pdo->prepare("
                    SELECT 
                        COALESCE(SUM(CASE WHEN tipo_pago = 'EFECTIVO' THEN total_general ELSE 0 END), 0) AS total_efectivo_facturas,
                        COALESCE(SUM(CASE WHEN tipo_pago = 'TARJETA' THEN total_general ELSE 0 END), 0) AS total_tarjeta_facturas,
                        COALESCE(SUM(CASE WHEN tipo_pago = 'TRANSFERENCIA' THEN total_general ELSE 0 END), 0) AS total_transferencia_facturas,
                        COALESCE(SUM(CASE WHEN tipo_pago = 'CHEQUE' THEN total_general ELSE 0 END), 0) AS total_cheque_facturas,
                        COALESCE(SUM(CASE WHEN tipo_pago = 'BILLETERA' THEN total_general ELSE 0 END), 0) AS total_billetera_facturas
                    FROM factura_ventas
                    WHERE id_apertura_cierre = :id
                      AND estado = 'EMITIDA'
                ");
                $qFacturas->execute([':id' => $apertura_id]);
                $totalesFacturas = $qFacturas->fetch(PDO::FETCH_ASSOC);
                
                // Cobros
                // Nota: cobros.id_apertura referencia a apertura_cierre_caja.id_apertura
                $qCobros = $pdo->prepare("
                    SELECT 
                        COALESCE(SUM(CASE WHEN cd.tipo_pago = 'EFECTIVO' THEN cd.importe_aplicado ELSE 0 END), 0) AS total_efectivo_cobros,
                        COALESCE(SUM(CASE WHEN cd.tipo_pago = 'TARJETA' THEN cd.importe_aplicado ELSE 0 END), 0) AS total_tarjeta_cobros,
                        COALESCE(SUM(CASE WHEN cd.tipo_pago = 'TRANSFERENCIA' THEN cd.importe_aplicado ELSE 0 END), 0) AS total_transferencia_cobros,
                        COALESCE(SUM(CASE WHEN cd.tipo_pago = 'CHEQUE' THEN cd.importe_aplicado ELSE 0 END), 0) AS total_cheque_cobros,
                        COALESCE(SUM(CASE WHEN cd.tipo_pago = 'BILLETERA' THEN cd.importe_aplicado ELSE 0 END), 0) AS total_billetera_cobros
                    FROM cobros c
                    JOIN cobros_detalle cd ON cd.id_cobro = c.id_cobro
                    WHERE c.id_apertura = :id
                      AND c.estado = 'REGISTRADO'
                ");
                $qCobros->execute([':id' => $apertura_id]);
                $totalesCobros = $qCobros->fetch(PDO::FETCH_ASSOC);
                
                // Combinar totales
                $total_efectivo = (float)($totalesFacturas['total_efectivo_facturas'] ?? 0) + (float)($totalesCobros['total_efectivo_cobros'] ?? 0);
                $total_tarjeta = (float)($totalesFacturas['total_tarjeta_facturas'] ?? 0) + (float)($totalesCobros['total_tarjeta_cobros'] ?? 0);
                $total_transferencia = (float)($totalesFacturas['total_transferencia_facturas'] ?? 0) + (float)($totalesCobros['total_transferencia_cobros'] ?? 0);
                $total_cheque = (float)($totalesFacturas['total_cheque_facturas'] ?? 0) + (float)($totalesCobros['total_cheque_cobros'] ?? 0);
                $total_billetera = (float)($totalesFacturas['total_billetera_facturas'] ?? 0) + (float)($totalesCobros['total_billetera_cobros'] ?? 0);
                $total_general = $total_efectivo + $total_tarjeta + $total_transferencia + $total_cheque + $total_billetera;

                // Calcular sobrante/faltante si se proporcionó efectivo contado
                $sobrante = 0;
                $faltante = 0;
                if ($efectivo_contado !== null) {
                    $efectivo_esperado = (float)$apertura['monto_apertura'] + $total_efectivo;
                    $diferencia = $efectivo_contado - $efectivo_esperado;
                    if ($diferencia > 0) {
                        $sobrante = $diferencia;
                    } else {
                        $faltante = abs($diferencia);
                    }
                }

                // Actualizar apertura con cierre
                // Nota: apertura_cierre_caja tiene campos: apertura_efectivo, apertura_tarjeta, apertura_cheque
                // No tiene campos separados para transferencia y billetera, se suman en apertura_tarjeta o apertura_cheque
                // monto_cierre almacena el total general
                $updApertura = $pdo->prepare("
                    UPDATE apertura_cierre_caja
                    SET fecha_cierre = CURRENT_DATE,
                        hora_cierre = CURRENT_TIME,
                        apertura_estado = 'CERRADA',
                        apertura_efectivo = :total_efectivo,
                        apertura_tarjeta = :total_tarjeta,
                        apertura_cheque = :total_cheque,
                        monto_cierre = :total_general
                    WHERE id_apertura = :apertura_id
                      AND apertura_estado = 'ABIERTA'
                ");
                $updApertura->execute([
                    ':total_efectivo' => (int)$total_efectivo,
                    ':total_tarjeta' => (int)($total_tarjeta + $total_transferencia + $total_billetera), // Sumar transferencia y billetera en tarjeta
                    ':total_cheque' => (int)$total_cheque,
                    ':total_general' => (int)$total_general,
                    ':apertura_id' => $apertura_id
                ]);
                
                // Verificar que el UPDATE afectó al menos una fila
                $rowsAffected = $updApertura->rowCount();
                if ($rowsAffected === 0) {
                    $pdo->rollBack();
                    error_log("Cierre caja: No se pudo actualizar la apertura {$apertura_id}. Posiblemente ya está cerrada o no existe.");
                    header("Location: view.php?alert=4");
                    exit;
                }
                
                error_log("Cierre caja: Apertura {$apertura_id} actualizada correctamente. Filas afectadas: {$rowsAffected}");

                // Actualizar estado de la caja
                $updCaja = $pdo->prepare("UPDATE caja SET estado = 'CERRADA' WHERE id_caja = :id");
                $updCaja->execute([':id' => $apertura['id_caja']]);
                
                if ($updCaja->rowCount() === 0) {
                    error_log("Cierre caja: Advertencia - No se pudo actualizar el estado de la caja {$apertura['id_caja']}");
                }

                // Hacer commit primero del UPDATE principal (antes de operaciones opcionales)
                $pdo->commit();
                
                // Después del commit, intentar operaciones opcionales (fuera de la transacción)
                // Generar recaudaciones para efectivo y cheques (opcional)
                // Nota: recaudaciones_depositar.id_apertura referencia a apertura_cierre_caja.id_apertura
                if ($total_efectivo > 0) {
                    try {
                        $insRec = $pdo->prepare("
                            INSERT INTO recaudaciones_depositar (
                                id_apertura,
                                tipo_medio,
                                monto,
                                estado,
                                id_usuario
                            )
                            VALUES (
                                :apertura_id,
                                'EFECTIVO',
                                :monto,
                                'PENDIENTE',
                                :usuario_id
                            )
                        ");
                        $insRec->execute([
                            ':apertura_id' => $apertura_id,
                            ':monto' => (int)$total_efectivo,
                            ':usuario_id' => $usuario_id
                        ]);
                    } catch (PDOException $eRec) {
                        error_log("Cierre caja: Advertencia - No se pudo generar recaudación de efectivo: " . $eRec->getMessage());
                    }
                }

                if ($total_cheque > 0) {
                    try {
                        $insRec = $pdo->prepare("
                            INSERT INTO recaudaciones_depositar (
                                id_apertura,
                                tipo_medio,
                                monto,
                                estado,
                                id_usuario
                            )
                            VALUES (
                                :apertura_id,
                                'CHEQUE',
                                :monto,
                                'PENDIENTE',
                                :usuario_id
                            )
                        ");
                        $insRec->execute([
                            ':apertura_id' => $apertura_id,
                            ':monto' => (int)$total_cheque,
                            ':usuario_id' => $usuario_id
                        ]);
                    } catch (PDOException $eRec) {
                        error_log("Cierre caja: Advertencia - No se pudo generar recaudación de cheque: " . $eRec->getMessage());
                    }
                }
                
                // Después del commit, intentar operaciones opcionales (fuera de la transacción)
                // Intentar registrar en bitácora (opcional - si falla, no afecta el cierre)
                try {
                    bitacora($pdo, $usuario_id, 'CIERRE', 
                        "Se cierra caja para apertura {$apertura_id}. Total: {$total_general}", 
                        $apertura_id);
                } catch (Exception $eBit) {
                    error_log("Cierre caja: Advertencia - No se pudo registrar en bitácora: " . $eBit->getMessage());
                    // Continuar sin bitácora
                }
                
                header("Location: view.php?alert=9");
                exit;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { 
                    $pdo->rollBack(); 
                }
                die("Error al cerrar la caja: " . $e->getMessage());
            }
        }

        // ANULAR - Anular apertura
        elseif ($action === 'anular' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $apertura_id = isset($input['apertura_id']) ? (int)$input['apertura_id'] : 0;

            if (!empty($_SESSION['usua_id'])) {
                $usuario_id = (int) $_SESSION['usua_id'];
            } else {
                $stmtUid = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
                $stmtUid->execute([':u' => $_SESSION['username'] ?? '']);
                $usuario_id = (int) $stmtUid->fetchColumn();
            }

            if ($apertura_id <= 0) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'ID de apertura inválido']);
                exit;
            }

            try {
                $pdo->beginTransaction();

                // Verificar estado y BLOQUEAR
                $st = $pdo->prepare("
                    SELECT apertura_estado, id_caja
                    FROM apertura_cierre_caja
                    WHERE id_apertura = :id
                    FOR UPDATE
                ");
                $st->execute([':id' => $apertura_id]);
                $apertura = $st->fetch(PDO::FETCH_ASSOC);

                if ($apertura === false) {
                    $pdo->rollBack();
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Apertura no encontrada']);
                    exit;
                }

                if (strtoupper(trim($apertura['apertura_estado'])) !== 'ABIERTA') {
                    $pdo->rollBack();
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Solo se pueden anular aperturas ABIERTAS']);
                    exit;
                }

                // Verificar que no tenga cobros
                // Nota: cobros.id_apertura referencia a apertura_cierre_caja.id_apertura
                $qCobros = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM cobros 
                    WHERE id_apertura = :id
                      AND estado != 'ANULADO'
                ");
                $qCobros->execute([':id' => $apertura_id]);
                if ($qCobros->fetchColumn() > 0) {
                    $pdo->rollBack();
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'No se puede anular una apertura que tiene cobros asociados']);
                    exit;
                }

                // Anular apertura
                $upd = $pdo->prepare("
                    UPDATE apertura_cierre_caja
                    SET apertura_estado = 'ANULADA'
                    WHERE id_apertura = :id
                ");
                $upd->execute([':id' => $apertura_id]);

                // Actualizar estado de la caja
                $updCaja = $pdo->prepare("UPDATE caja SET estado = 'DISPONIBLE' WHERE id_caja = :id");
                $updCaja->execute([':id' => $apertura['id_caja']]);

                bitacora($pdo, $usuario_id, 'INACTIVACION', 
                    "Se anula apertura {$apertura_id}", 
                    $apertura_id);

                $pdo->commit();
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Apertura anulada correctamente']);

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { 
                    $pdo->rollBack(); 
                }
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
        }

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error en la operación con la base de datos: " . $e->getMessage());
    }
} else {
    header("Location: view.php?alert=4");
    exit;
}
?>

