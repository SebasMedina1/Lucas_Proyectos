<?php
session_start();
require "../../config/database.php";

// Verificar si la sesión es válida
if (empty($_SESSION['username'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Token de sesión inválido']);
    exit;
}

// Función bitácora con savepoint (igual que en gestionar_compras)
function bitacora(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void {
    $savepointName = null;
    try {
        // Si estamos en una transacción, crear un savepoint para aislar errores de bitacora
        if ($pdo->inTransaction()) {
            $savepointName = 'bitacora_' . str_replace(['.', '-'], '_', uniqid('', true));
            $pdo->exec("SAVEPOINT {$savepointName}");
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ");
        $stmt->execute([
            ':id_usuario'  => $idUsuario,
            ':entidad'     => 'Nota de Crédito/Débito',
            ':id_registro' => $idRegistro,
            ':accion'      => strtoupper($accion),
            ':descripcion' => $descripcion
        ]);
        
        // Si usamos savepoint, liberarlo
        if ($savepointName !== null) {
            $pdo->exec("RELEASE SAVEPOINT {$savepointName}");
        }
    } catch (PDOException $e) {
        // Si hay un error SQL y usamos savepoint, hacer rollback al savepoint
        if ($savepointName !== null) {
            try {
                $pdo->exec("ROLLBACK TO SAVEPOINT {$savepointName}");
            } catch (PDOException $rollbackError) {
                error_log("Error al hacer rollback del savepoint de bitacora: ".$rollbackError->getMessage());
            }
        }
        error_log("Bitácora falló: ".$e->getMessage());
        // No re-lanzar la excepción para no afectar la transacción principal
    } catch (Throwable $e) {
        if ($savepointName !== null) {
            try {
                $pdo->exec("ROLLBACK TO SAVEPOINT {$savepointName}");
            } catch (PDOException $rollbackError) {
                error_log("Error al hacer rollback del savepoint de bitacora: ".$rollbackError->getMessage());
            }
        }
        error_log("Bitácora falló: ".$e->getMessage());
    }
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Obtener usuario actual
    $usuario_id = 0;
    if (!empty($_SESSION['id_usuario'])) {
        $usuario_id = (int)$_SESSION['id_usuario'];
    } else {
        $stmtUid = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
        $stmtUid->execute([':u' => $_SESSION['username'] ?? '']);
        $usuario_id = (int)$stmtUid->fetchColumn();
    }

    if ($usuario_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Usuario no válido']);
        exit;
    }

    // Obtener sucursal del usuario
    $stmtSuc = $pdo->prepare("SELECT id_sucursal FROM usuarios WHERE id_usuario = :id LIMIT 1");
    $stmtSuc->execute([':id' => $usuario_id]);
    $id_sucursal = (int)$stmtSuc->fetchColumn();
    if ($id_sucursal <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Usuario sin sucursal asociada']);
        exit;
    }

    // Validar acción
    if (($_GET['act'] ?? '') !== 'insert_nota') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Acción no soportada']);
        exit;
    }

    // =========================== INPUTS ===========================
    $idProveedor   = (int)($_POST['id_proveedor'] ?? 0);
    $factId        = (int)($_POST['fact_id'] ?? 0);
    $notaTipo      = strtoupper(trim($_POST['nota_tipo'] ?? ''));     // CREDITO | DEBITO
    $motivoId      = (int)($_POST['motivo_id'] ?? 0);
    $notaNroStr    = trim($_POST['nota_nro'] ?? '');
    // Extraer solo dígitos del número de nota
    $notaNroClean  = preg_replace('/\D+/', '', $notaNroStr);
    
    // IMPORTANTE: El número de nota puede tener hasta 13 dígitos (formato EEE-PPP-NNNNNNN)
    // PostgreSQL integer solo soporta hasta ~2.1 billones (10 dígitos)
    // Si el número excede 9 dígitos, usar solo los últimos 9 para evitar overflow
    // NOTA: La solución correcta es cambiar nota_nro a BIGINT en la BD
    if (strlen($notaNroClean) > 9) {
        // Usar los últimos 9 dígitos (máximo seguro para integer)
        $notaNro = (int)substr($notaNroClean, -9);
        error_log("ADVERTENCIA: Número de nota truncado de {$notaNroClean} a {$notaNro} (excede rango de integer)");
    } else {
        $notaNro = (int)$notaNroClean;
    }
    $timbrado      = trim($_POST['nota_timbrado'] ?? '');
    // El formulario envía 'nota_emision' como nombre del campo
    $notaInicio    = substr((string)($_POST['nota_emision'] ?? $_POST['nota_inicio'] ?? ''), 0, 10);      // Fecha inicio timbrado
    $notaVto       = substr((string)($_POST['nota_vto'] ?? ''), 0, 10);         // Fecha vencimiento timbrado
    $items         = json_decode($_POST['productos'] ?? '[]', true);
    $totalFrontNum = (int)($_POST['nota_total_num'] ?? 0);
    $costoAdicional = (int)($_POST['costo_adicional'] ?? 0); // Para ND con motivo 3 (COSTO ADICIONAL)
    
    if (!is_array($items)) $items = [];

    // =========================== VALIDACIONES BÁSICAS (Punto 15.a) ===========================
    if ($idProveedor <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Proveedor inválido']);
        exit;
    }
    if ($factId <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Factura no seleccionada']);
        exit;
    }
    if (!in_array($notaTipo, ['CREDITO', 'DEBITO'], true)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Tipo de Nota inválido']);
        exit;
    }
    if ($motivoId < 1) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Motivo inválido']);
        exit;
    }
    if ($notaNro <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'N° de Nota inválido']);
        exit;
    }
    if (!preg_match('/^\d{8}$/', $timbrado)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Timbrado inválido (debe tener 8 dígitos)']);
        exit;
    }
    if (!$notaInicio) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Fecha de inicio del timbrado obligatoria']);
        exit;
    }
    if (empty($items)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Debe incluir al menos un ítem']);
        exit;
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    // =========================== VALIDAR FACTURA (Punto 7) ===========================
    // Solo facturas en estado EMITIDA (según especificación punto 7)
    $stF = $pdo->prepare("
        SELECT 
            fc.id_factura_compra,
            fc.fact_fecha_compra::date AS fac_emision,
            fc.fac_estado,
            fc.fac_total,
            fc.tipo_operacion,
            oc.id_proveedor
        FROM factura_compra fc
        JOIN orden_de_compra oc ON oc.id_orden_compra = fc.id_orden_compra
        WHERE fc.id_factura_compra = :id
        FOR UPDATE
    ");
    $stF->execute([':id' => $factId]);
    $fac = $stF->fetch(PDO::FETCH_ASSOC);
    
    if (!$fac) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Factura inexistente']);
        exit;
    }
    
    // Guardar el total ORIGINAL de la factura ANTES de aplicar cualquier cambio
    // Esto se usará para la validación de auto-anulación
    $facTotalOriginal = (int)($fac['fac_total'] ?? 0);
    
    if ((int)$fac['id_proveedor'] !== $idProveedor) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'La factura no corresponde al proveedor seleccionado']);
        exit;
    }
    
    // Validar que la factura esté en estado EMITIDA (punto 7)
    $estadoFactura = strtoupper(trim((string)$fac['fac_estado']));
    if ($estadoFactura !== 'EMITIDA') {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => "La factura debe estar en estado EMITIDA para emitir notas. Estado actual: {$estadoFactura}"]);
        exit;
    }

    // =========================== VALIDAR CUENTA A PAGAR (Punto 15.b y Flujo Alternativo #3) ===========================
    // Verificar que la factura no esté pagada totalmente
    $stCta = $pdo->prepare("
        SELECT 
            cp.monto_total,
            cp.monto_pendiente,
            cp.estado
        FROM cuentas_pagar cp
        WHERE cp.id_factura_compra = :fid
        LIMIT 1
        FOR UPDATE
    ");
    $stCta->execute([':fid' => $factId]);
    $cuenta = $stCta->fetch(PDO::FETCH_ASSOC);
    
    if ($cuenta === false) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Cuenta a pagar no encontrada para la factura']);
        exit;
    }
    
    $estadoCta = strtoupper(trim((string)$cuenta['estado']));
    $montoPendiente = (int)($cuenta['monto_pendiente'] ?? 0);
    
    // Flujo Alternativo #3: Factura pagada totalmente → no disponible para notas
    if (in_array($estadoCta, ['FINALIZADO', 'PAGADO', 'PAGADO TOTAL'], true) || $montoPendiente <= 0) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'La factura está pagada totalmente. No se pueden emitir notas']);
        exit;
    }

    // =========================== VALIDAR FECHAS (Punto 15.b) ===========================
    $hoy = date('Y-m-d');
    $facEmision = (string)$fac['fac_emision'];
    
    // La emisión de la nota debe estar entre la fecha de la factura y hoy
    if ($notaInicio < $facEmision || $notaInicio > $hoy) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => "La fecha de emisión de la nota debe estar entre {$facEmision} y {$hoy}"]);
        exit;
    }
    
    if ($notaVto && $notaVto < $notaInicio) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'La fecha de vencimiento del timbrado no puede ser menor a la fecha de inicio']);
        exit;
    }

    // =========================== CALCULAR TOTALES (Punto 12) ===========================
    // Función para mapear descripción IVA a tasa
    $descripcionToTasa = function (?string $descr): int {
        $t = strtolower(trim((string)$descr));
        if ($t === 'iva_10' || $t === '10' || $t === '10%') return 10;
        if ($t === 'iva_5'  || $t === '5'  || $t === '5%')  return 5;
        return 0;
    };

    $totalBase = 0;
    $totalIVA5 = 0;
    $totalIVA10 = 0;
    $totalExento = 0;

    foreach ($items as $it) {
        $cant = (int)($it['cantidad'] ?? 0);
        $prec = (int)($it['precio'] ?? 0);
        if ($cant < 0 || $prec < 0) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Cantidad y precio no pueden ser negativos']);
            exit;
        }
        
        $subtotal = $cant * $prec;
        $totalBase += $subtotal;

        $tasa = $descripcionToTasa($it['iva_descri'] ?? null);
        if ($tasa === 10) {
            $totalIVA10 += (int)floor($subtotal / 11);
        } elseif ($tasa === 5) {
            $totalIVA5 += (int)floor($subtotal / 21);
        } else {
            $totalExento += $subtotal;
        }
    }

    // Para DEBITO con motivo 3 (COSTO ADICIONAL), agregar el costo adicional al total
    $totalCalc = $totalBase;
    if ($notaTipo === 'DEBITO' && $motivoId === 3 && $costoAdicional > 0) {
        $totalCalc += $costoAdicional;
    }
    
    // Para DEBITO con motivo 4 (DIFERENCIA) o CREDITO con motivo 2 (AJUSTE PARCIAL),
    // calcular la diferencia entre valores nuevos y originales
    $montoAAplicarNota = $totalCalc; // Por defecto, usar el total calculado
    if (($notaTipo === 'DEBITO' && $motivoId === 4) || ($notaTipo === 'CREDITO' && $motivoId === 2)) {
        // Obtener los valores originales de la factura
        $stDetFac = $pdo->prepare("
            SELECT id_materia_prima, fac_cantidad, fac_precio
            FROM factura_detalle_compra
            WHERE id_factura_compra = :fid
        ");
        $stDetFac->execute([':fid' => $factId]);
        $detallesFac = $stDetFac->fetchAll(PDO::FETCH_ASSOC);
        
        // Crear un mapa de valores originales por id_materia_prima
        $valoresOriginales = [];
        foreach ($detallesFac as $det) {
            $mpId = (int)$det['id_materia_prima'];
            $valoresOriginales[$mpId] = [
                'cantidad' => (int)$det['fac_cantidad'],
                'precio' => (int)$det['fac_precio'],
                'subtotal' => (int)$det['fac_cantidad'] * (int)$det['fac_precio']
            ];
        }
        
        // Calcular la diferencia según el tipo de nota
        // Para DEBITO: diferencia = subtotal_nuevo - subtotal_original (positivo si aumenta)
        // Para CREDITO: diferencia = subtotal_original - subtotal_nuevo (positivo si se acredita)
        $diferenciaTotal = 0;
        foreach ($items as $it) {
            $mpId = (int)($it['id_materia_prima'] ?? $it['id_producto'] ?? 0);
            if ($mpId <= 0 || !isset($valoresOriginales[$mpId])) continue;
            
            $cantNueva = (int)($it['cantidad'] ?? 0);
            $precNuevo = (int)($it['precio'] ?? 0);
            $subtotalNuevo = $cantNueva * $precNuevo;
            
            $subtotalOriginal = $valoresOriginales[$mpId]['subtotal'];
            
            if ($notaTipo === 'CREDITO') {
                // Para CREDITO: diferencia positiva = lo que se acredita
                $diferenciaTotal += ($subtotalOriginal - $subtotalNuevo);
            } else {
                // Para DEBITO: diferencia positiva = lo que se aumenta
                $diferenciaTotal += ($subtotalNuevo - $subtotalOriginal);
            }
        }
        
        // El monto a aplicar es la diferencia, no el total de los items nuevos
        $montoAAplicarNota = $diferenciaTotal;
        // El nota_total debe guardar la diferencia
        $totalCalc = $diferenciaTotal;
    }

    // Validar consistencia con el total del frontend
    // Para DIFERENCIA (ND motivo 4) y AJUSTE PARCIAL (NC motivo 2), el backend calcula la diferencia
    // El frontend envía el total de items nuevos para validación, pero el backend calcula la diferencia
    // Por lo tanto, para estos casos NO validamos contra el total enviado, solo validamos que la diferencia sea correcta
    // Para otros casos, validamos contra $totalCalc directamente
    $totalParaValidar = $totalCalc;
    
    // Para DIFERENCIA y AJUSTE PARCIAL, el frontend envía el total de items nuevos, no la diferencia
    // El backend calcula la diferencia internamente, así que no validamos contra el total enviado
    if (($notaTipo === 'DEBITO' && $motivoId === 4) || ($notaTipo === 'CREDITO' && $motivoId === 2)) {
        // Solo validamos que la diferencia sea válida (positiva para ambos casos)
        if ($totalCalc < 0) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => "La diferencia calculada no puede ser negativa ({$totalCalc})"]);
            exit;
        }
    } else {
        // Para otros casos, validar contra el total enviado
        if (abs($totalParaValidar - $totalFrontNum) > 1) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => "El total calculado ({$totalParaValidar}) no coincide con el total enviado ({$totalFrontNum})"]);
            exit;
        }
    }

    // =========================== VALIDACIONES ESPECÍFICAS (Punto 15.b y 15.c) ===========================
    
    // Validación NC: el total a acreditar ≤ saldo pendiente (punto 15.b)
    if ($notaTipo === 'CREDITO') {
        if ($totalCalc > $montoPendiente) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => "El total a acreditar ({$totalCalc}) no puede ser mayor al saldo pendiente de la factura ({$montoPendiente})"
            ]);
            exit;
        }
        
        // Validación Anulación total: mismo día de emisión (punto 15.b)
        // Asumiendo que motivo 1 = Anulación total (verificar con tabla motivo)
        if ($motivoId === 1 && $notaInicio !== $facEmision) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => "Para anulación total, la nota debe emitirse el mismo día que la factura ({$facEmision})"
            ]);
            exit;
        }
    }

    // Validación ND: el monto > 0 y justificación/motivo presente (punto 15.c)
    if ($notaTipo === 'DEBITO') {
        if ($totalCalc <= 0) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'El monto de la Nota de Débito debe ser mayor a cero']);
            exit;
        }
        // El motivo ya está validado arriba (motivoId >= 1)
    }

    // =========================== INSERTAR NOTA (Punto 16) ===========================
    // Incluir id_factura_compra ahora que existe en la BD
    $stCab = $pdo->prepare("
        INSERT INTO nota_compra
        (nota_compra_tipo, nota_compra_fecha, nota_nro, nota_compra_timbrado,
         nota_compra_inicio, nota_compra_vencimiento, nota_compra_estado, nota_total,
         id_usuario, id_sucursal, id_proveedor, id_motivo, id_factura_compra)
        VALUES
        (:tipo, CURRENT_DATE, :nro, :timbrado,
         :inicio, :vto, 'EMITIDA', :total,
         :idu, :ids, :idp, :idm, :fid)
        RETURNING id_nota_compra
    ");
    $stCab->execute([
        ':tipo'    => $notaTipo,
        ':nro'     => $notaNro,
        ':timbrado' => $timbrado,
        ':inicio'  => $notaInicio,
        ':vto'     => $notaVto ?: null,
        ':total'   => $totalCalc,
        ':idu'     => $usuario_id,
        ':ids'     => $id_sucursal,
        ':idp'     => $idProveedor,
        ':idm'     => $motivoId,
        ':fid'     => $factId
    ]);
    
    $idNota = (int)$stCab->fetchColumn();
    if ($idNota <= 0) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No se pudo generar la nota']);
        exit;
    }

    bitacora($pdo, $usuario_id, 'ALTA', "Nota de {$notaTipo} #{$idNota} emitida | Factura #{$factId} | Motivo: {$motivoId} | Total: {$totalCalc}", $idNota);

    // =========================== INSERTAR DETALLE (Punto 16) ===========================
    foreach ($items as $it) {
        $prod = (int)($it['id_materia_prima'] ?? $it['id_producto'] ?? $it['codigo'] ?? 0);
        $cant = (int)($it['cantidad'] ?? 0);
        $prec = (int)($it['precio'] ?? 0);
        $iva  = $descripcionToTasa($it['iva_descri'] ?? null);
        
        if ($prod <= 0) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Ítem sin id_materia_prima válido']);
            exit;
        }

        $stDet = $pdo->prepare("
            INSERT INTO nota_detalle_compra
            (id_materia_prima, id_nota_compra, nota_compra_cantidad, tipo_iva, nota_precio)
            VALUES
            (:mp, :nota, :cant, :iva, :precio)
        ");
        $stDet->execute([
            ':mp'     => $prod,
            ':nota'   => $idNota,
            ':cant'   => $cant,
            ':iva'    => $iva,
            ':precio' => $prec
        ]);

        bitacora($pdo, $usuario_id, 'ALTA', "Detalle Nota #{$idNota}: Materia Prima {$prod}, cantidad {$cant}, precio {$prec}, IVA {$iva}%", $idNota);
    }

    // =========================== GUARDAR VALORES ORIGINALES PARA STOCK ===========================
    // Leer los items originales ANTES de actualizar factura_detalle_compra para usar en stock
    $itemsOriginales = [];
    if ($notaTipo === 'CREDITO' && $motivoId === 2) {
        // Solo necesitamos los originales para NC motivo 2 (AJUSTE PARCIAL)
        $stDetFacOriginal = $pdo->prepare("
            SELECT 
                d.id_materia_prima,
                d.fac_cantidad,
                d.fac_precio,
                mp.iva_id
            FROM factura_detalle_compra d
            JOIN materia_prima mp ON mp.id_materia_prima = d.id_materia_prima
            WHERE d.id_factura_compra = :fid
        ");
        $stDetFacOriginal->execute([':fid' => $factId]);
        $detallesFacOriginal = $stDetFacOriginal->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($detallesFacOriginal as $det) {
            $mpId = (int)$det['id_materia_prima'];
            $itemsOriginales[$mpId] = [
                'cantidad' => (int)$det['fac_cantidad'],
                'precio' => (int)$det['fac_precio'],
                'iva_id' => (int)$det['iva_id']
            ];
        }
    }
    
    // =========================== ACTUALIZAR CUENTAS A PAGAR (Punto 16.a y 16.b) ===========================
    // Si es anulación total, no actualizar aquí (se hará después)
    $esAnulacionTotal = ($notaTipo === 'CREDITO' && $motivoId === 1);
    
    if (!$esAnulacionTotal) {
        $sign = ($notaTipo === 'DEBITO') ? +1 : -1;  // Débito aumenta, Crédito disminuye
        
        $montoTotal = (int)($cuenta['monto_total'] ?? 0);
        
        // Para ND con motivo 3 (COSTO ADICIONAL), solo se suma el costo adicional, no el total completo
        // Los items son informativos (vienen de la factura original), solo el costo adicional es nuevo
        // Para ND con motivo 4 (DIFERENCIA), se aplica la diferencia calculada arriba
        $montoAAplicar = $totalCalc;
        if ($notaTipo === 'DEBITO' && $motivoId === 3) {
            // Solo aplicar el costo adicional, no los items de la factura original
            $montoAAplicar = $costoAdicional;
        } elseif ($notaTipo === 'DEBITO' && $motivoId === 4) {
            // Para DIFERENCIA, usar la diferencia calculada (ya está en $totalCalc)
            $montoAAplicar = $totalCalc;
        }
        
        // Actualizar monto_total y monto_pendiente según el tipo de nota
        if ($notaTipo === 'DEBITO') {
            // Débito: aumenta tanto el total como el pendiente
            $nuevoMontoTotal = $montoTotal + $montoAAplicar;
            $nuevoMontoPendiente = $montoPendiente + $montoAAplicar;
        } else {
            // Crédito: disminuye tanto el total como el pendiente
            // El monto a acreditar se resta de ambos
            $nuevoMontoTotal = max(0, $montoTotal - $montoAAplicar);
            $nuevoMontoPendiente = max(0, $montoPendiente - $montoAAplicar);
        }
        
        $stUpdCta = $pdo->prepare("
            UPDATE cuentas_pagar
            SET monto_total = :total,
                monto_pendiente = :pendiente
            WHERE id_factura_compra = :fid
        ");
        $stUpdCta->execute([
            ':total'     => $nuevoMontoTotal,
            ':pendiente' => $nuevoMontoPendiente,
            ':fid'       => $factId
        ]);
        
        $rowsAffected = $stUpdCta->rowCount();
        
        // Verificar que se actualizó al menos una fila
        if ($rowsAffected === 0) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No se pudo actualizar la cuenta a pagar. Verifique que la factura tenga una cuenta asociada.']);
            exit;
        }
        
        // Log para depuración
        error_log("Nota #{$idNota}: Cuenta a pagar actualizada - Filas afectadas: {$rowsAffected} | Factura #{$factId} | Total: {$montoTotal} → {$nuevoMontoTotal} | Pendiente: {$montoPendiente} → {$nuevoMontoPendiente}");

        $fmt = fn($n) => number_format((int)$n, 0, ',', '.').' Gs';
        $descCp = sprintf(
            "Cuentas a Pagar actualizada: Factura #%d | %s %s | Total: %s → %s | Pendiente: %s → %s | Origen: Nota #%d (%s)%s",
            $factId,
            ($sign > 0 ? 'Aumento' : 'Disminución'),
            $fmt($montoAAplicar),
            $fmt($montoTotal),
            $fmt($nuevoMontoTotal),
            $fmt($montoPendiente),
            $fmt($nuevoMontoPendiente),
            $idNota,
            $notaTipo,
            ($notaTipo === 'DEBITO' && $motivoId === 3) ? ' (solo costo adicional aplicado)' : ''
        );
        bitacora($pdo, $usuario_id, 'MODIFICACION', $descCp, $factId);
        
        // =========================== ACTUALIZAR FACTURA (fac_total) ===========================
        // El fac_total se recalculará después de actualizar factura_detalle_compra
        // para que sea coherente con los items actualizados
        // Por ahora, actualizamos con el monto aplicado (se recalculará después si es necesario)
        $facTotalActual = (int)($fac['fac_total'] ?? 0);
        
        if ($facTotalActual <= 0) {
            error_log("ADVERTENCIA: Nota #{$idNota}: fac_total actual es 0 o inválido. Valor obtenido: " . ($fac['fac_total'] ?? 'NULL'));
        }
        
        if ($notaTipo === 'DEBITO') {
            // Débito: aumenta el total de la factura
            $nuevoFacTotal = $facTotalActual + $montoAAplicar;
        } else {
            // Crédito: disminuye el total de la factura
            $nuevoFacTotal = max(0, $facTotalActual - $montoAAplicar);
        }
        
        error_log("Nota #{$idNota}: Preparando actualización de factura - Factura #{$factId} | Tipo: {$notaTipo} | Total actual: {$facTotalActual} | Monto a aplicar: {$montoAAplicar} | Nuevo total: {$nuevoFacTotal}");
        
        $stUpdFac = $pdo->prepare("
            UPDATE factura_compra
            SET fac_total = :total
            WHERE id_factura_compra = :fid
        ");
        $stUpdFac->execute([
            ':total' => $nuevoFacTotal,
            ':fid'   => $factId
        ]);
        
        $rowsAffectedFac = $stUpdFac->rowCount();
        
        error_log("Nota #{$idNota}: UPDATE factura ejecutado - Filas afectadas: {$rowsAffectedFac}");
        
        if ($rowsAffectedFac === 0) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No se pudo actualizar el total de la factura.']);
            exit;
        }
        
        // Log para depuración
        error_log("Nota #{$idNota}: Factura actualizada exitosamente - Factura #{$factId} | Total: {$facTotalActual} → {$nuevoFacTotal}");
        
        $descFac = sprintf(
            "Factura actualizada: Factura #%d | %s %s | Total: %s → %s | Origen: Nota #%d (%s)%s",
            $factId,
            ($sign > 0 ? 'Aumento' : 'Disminución'),
            $fmt($montoAAplicar),
            $fmt($facTotalActual),
            $fmt($nuevoFacTotal),
            $idNota,
            $notaTipo,
            ($notaTipo === 'DEBITO' && $motivoId === 3) ? ' (solo costo adicional aplicado)' : ''
        );
        bitacora($pdo, $usuario_id, 'MODIFICACION', $descFac, $factId);
        
        // =========================== RECALCULAR IVA DE LA FACTURA ===========================
        // Recalcular el IVA de la factura con los valores actualizados después de aplicar la nota
        // Obtener todos los items de la factura (originales)
        $stDetFacCompleto = $pdo->prepare("
            SELECT 
                d.id_materia_prima,
                d.fac_cantidad,
                d.fac_precio,
                mp.iva_id
            FROM factura_detalle_compra d
            JOIN materia_prima mp ON mp.id_materia_prima = d.id_materia_prima
            WHERE d.id_factura_compra = :fid
        ");
        $stDetFacCompleto->execute([':fid' => $factId]);
        $detallesFacCompleto = $stDetFacCompleto->fetchAll(PDO::FETCH_ASSOC);
        
        // Crear un mapa de items actualizados por id_materia_prima
        // Si ya tenemos $itemsOriginales (definido arriba para stock), no redefinirlo
        // Solo crear $itemsActualizados desde el detalle actual
        $itemsActualizados = [];
        foreach ($detallesFacCompleto as $det) {
            $mpId = (int)$det['id_materia_prima'];
            // Si no existe $itemsOriginales, crearlo ahora (para otros casos que no sean NC motivo 2)
            if (empty($itemsOriginales) || !isset($itemsOriginales[$mpId])) {
                if (empty($itemsOriginales)) {
                    $itemsOriginales = [];
                }
                $itemsOriginales[$mpId] = [
                    'cantidad' => (int)$det['fac_cantidad'],
                    'precio' => (int)$det['fac_precio'],
                    'iva_id' => (int)$det['iva_id']
                ];
            }
            // Inicializar items actualizados con valores actuales del detalle
            $itemsActualizados[$mpId] = [
                'cantidad' => (int)$det['fac_cantidad'],
                'precio' => (int)$det['fac_precio'],
                'iva_id' => (int)$det['iva_id']
            ];
        }
        
        // Aplicar los cambios de la nota a los items
        if ($notaTipo === 'DEBITO' && $motivoId === 4) {
            // DIFERENCIA: actualizar precios de los items modificados
            foreach ($items as $it) {
                $mpId = (int)($it['id_materia_prima'] ?? $it['id_producto'] ?? 0);
                if ($mpId <= 0 || !isset($itemsActualizados[$mpId])) continue;
                
                $itemsActualizados[$mpId]['precio'] = (int)($it['precio'] ?? 0);
            }
        } elseif ($notaTipo === 'CREDITO' && $motivoId === 2) {
            // AJUSTE PARCIAL: actualizar cantidades y precios
            foreach ($items as $it) {
                $mpId = (int)($it['id_materia_prima'] ?? $it['id_producto'] ?? 0);
                if ($mpId <= 0 || !isset($itemsActualizados[$mpId])) continue;
                
                $itemsActualizados[$mpId]['cantidad'] = (int)($it['cantidad'] ?? 0);
                $itemsActualizados[$mpId]['precio'] = (int)($it['precio'] ?? 0);
            }
        }
        // Para COSTO ADICIONAL, los items no cambian, solo el total
        
        // Obtener información de tipos de IVA
        $stIva = $pdo->query("SELECT iva_id, iva_descri FROM tipo_iva");
        $ivaInfo = [];
        $divisorPorIva = [];
        foreach ($stIva->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $ivaId = (int)$r['iva_id'];
            $desc = strtolower(preg_replace('/\s|%/','', (string)$r['iva_descri']));
            $ivaInfo[$ivaId] = $desc;
            if (in_array($desc, ['iva10','iva_10','10','10porc','iva10%'])) {
                $divisorPorIva[$ivaId] = 11;
            } elseif (in_array($desc, ['iva5','iva_5','5','5porc','iva5%'])) {
                $divisorPorIva[$ivaId] = 21;
            } else {
                $divisorPorIva[$ivaId] = 0; // exento
            }
        }
        
        // Recalcular IVA total de la factura
        $ivaExentoTotal = 0;
        $iva5Total = 0;
        $iva10Total = 0;
        
        foreach ($itemsActualizados as $mpId => $item) {
            $cant = $item['cantidad'];
            $prec = $item['precio'];
            $ivaId = $item['iva_id'];
            
            if ($cant <= 0 || $prec <= 0) continue;
            
            $subtotal = $cant * $prec;
            $divisor = $divisorPorIva[$ivaId] ?? 0;
            
            if ($divisor === 11) {
                $iva10Total += (int)floor($subtotal / 11);
            } elseif ($divisor === 21) {
                $iva5Total += (int)floor($subtotal / 21);
            } else {
                $ivaExentoTotal += $subtotal;
            }
        }
        
        // Actualizar iva_compra
        $stUpdIva = $pdo->prepare("
            UPDATE iva_compra
            SET iva_exento = :ex,
                iva_5 = :iva5,
                iva_10 = :iva10
            WHERE id_factura_compra = :fid
        ");
        $stUpdIva->execute([
            ':ex' => $ivaExentoTotal,
            ':iva5' => $iva5Total,
            ':iva10' => $iva10Total,
            ':fid' => $factId
        ]);
        
        // Si no existe registro en iva_compra, crearlo
        if ($stUpdIva->rowCount() === 0) {
            $stInsIva = $pdo->prepare("
                INSERT INTO iva_compra (id_factura_compra, iva_fecha, iva_exento, iva_5, iva_10)
                VALUES (:fid, CURRENT_DATE, :ex, :iva5, :iva10)
            ");
            $stInsIva->execute([
                ':fid' => $factId,
                ':ex' => $ivaExentoTotal,
                ':iva5' => $iva5Total,
                ':iva10' => $iva10Total
            ]);
        }
        
        bitacora($pdo, $usuario_id, 'MODIFICACION', "IVA recalculado para Factura #{$factId} | Exento: {$ivaExentoTotal} | IVA5: {$iva5Total} | IVA10: {$iva10Total} | Origen: Nota #{$idNota}", $factId);
        
        // =========================== ACTUALIZAR FACTURA_DETALLE_COMPRA ===========================
        // Actualizar las cantidades y precios en factura_detalle_compra para que queden coherentes
        // Esto es necesario para que cuando se cargue otra nota, traiga las cantidades actualizadas
        if ($notaTipo === 'CREDITO' && $motivoId === 2) {
            // AJUSTE PARCIAL: actualizar cantidades, precios e IVA en factura_detalle_compra
            foreach ($itemsActualizados as $mpId => $itemActualizado) {
                // Calcular el IVA del item actualizado
                $subtotalItem = $itemActualizado['cantidad'] * $itemActualizado['precio'];
                $ivaIdItem = $itemActualizado['iva_id'];
                $divisor = $divisorPorIva[$ivaIdItem] ?? 0;
                $ivaMontoItem = 0;
                
                if ($divisor === 11) {
                    $ivaMontoItem = (int)floor($subtotalItem / 11);
                } elseif ($divisor === 21) {
                    $ivaMontoItem = (int)floor($subtotalItem / 21);
                }
                
                $stUpdDet = $pdo->prepare("
                    UPDATE factura_detalle_compra
                    SET fac_cantidad = :cant,
                        fac_precio = :prec,
                        fac_iva = :iva
                    WHERE id_factura_compra = :fid
                      AND id_materia_prima = :mp
                ");
                $stUpdDet->execute([
                    ':cant' => $itemActualizado['cantidad'],
                    ':prec' => $itemActualizado['precio'],
                    ':iva'  => $ivaMontoItem,
                    ':fid'  => $factId,
                    ':mp'   => $mpId
                ]);
                bitacora($pdo, $usuario_id, 'MODIFICACION', "Factura Detalle #{$factId} actualizado: MP {$mpId} → Cantidad: {$itemActualizado['cantidad']}, Precio: {$itemActualizado['precio']}, IVA: {$ivaMontoItem} | Nota #{$idNota}", $factId);
            }
            
            // Recalcular fac_total desde el detalle actualizado para que sea coherente
            $stRecalcTotal = $pdo->prepare("
                SELECT COALESCE(SUM(fac_cantidad * fac_precio), 0) AS nuevo_total
                FROM factura_detalle_compra
                WHERE id_factura_compra = :fid
            ");
            $stRecalcTotal->execute([':fid' => $factId]);
            $nuevoFacTotalRecalc = (int)$stRecalcTotal->fetchColumn();
            
            // Actualizar fac_total con el valor recalculado
            $stUpdFacTotal = $pdo->prepare("
                UPDATE factura_compra
                SET fac_total = :total
                WHERE id_factura_compra = :fid
            ");
            $stUpdFacTotal->execute([
                ':total' => $nuevoFacTotalRecalc,
                ':fid'   => $factId
            ]);
            bitacora($pdo, $usuario_id, 'MODIFICACION', "Factura #{$factId} fac_total recalculado desde detalle: {$nuevoFacTotalRecalc} | Nota #{$idNota}", $factId);
            
        } elseif ($notaTipo === 'DEBITO' && $motivoId === 4) {
            // DIFERENCIA: actualizar solo precios en factura_detalle_compra
            foreach ($itemsActualizados as $mpId => $itemActualizado) {
                $stUpdDet = $pdo->prepare("
                    UPDATE factura_detalle_compra
                    SET fac_precio = :prec
                    WHERE id_factura_compra = :fid
                      AND id_materia_prima = :mp
                ");
                $stUpdDet->execute([
                    ':prec' => $itemActualizado['precio'],
                    ':fid'  => $factId,
                    ':mp'   => $mpId
                ]);
                bitacora($pdo, $usuario_id, 'MODIFICACION', "Factura Detalle #{$factId} actualizado: MP {$mpId} → Precio: {$itemActualizado['precio']} | Nota #{$idNota}", $factId);
            }
            
            // Recalcular fac_total desde el detalle actualizado para que sea coherente
            $stRecalcTotal = $pdo->prepare("
                SELECT COALESCE(SUM(fac_cantidad * fac_precio), 0) AS nuevo_total
                FROM factura_detalle_compra
                WHERE id_factura_compra = :fid
            ");
            $stRecalcTotal->execute([':fid' => $factId]);
            $nuevoFacTotalRecalc = (int)$stRecalcTotal->fetchColumn();
            
            // Actualizar fac_total con el valor recalculado
            $stUpdFacTotal = $pdo->prepare("
                UPDATE factura_compra
                SET fac_total = :total
                WHERE id_factura_compra = :fid
            ");
            $stUpdFacTotal->execute([
                ':total' => $nuevoFacTotalRecalc,
                ':fid'   => $factId
            ]);
            bitacora($pdo, $usuario_id, 'MODIFICACION', "Factura #{$factId} fac_total recalculado desde detalle: {$nuevoFacTotalRecalc} | Nota #{$idNota}", $factId);
        }
        
        // Verificar auto-anulación: si la suma de NC parciales iguala el total ORIGINAL de la factura
        if ($notaTipo === 'CREDITO' && $motivoId === 2) { // Devolución (parcial)
            // Obtener el total de todas las notas de crédito (incluyendo esta)
            // Usar $facTotalOriginal que se guardó al inicio, antes de aplicar cambios
            $stSumNC = $pdo->prepare("
                SELECT COALESCE(SUM(nota_total), 0) AS total_creditos
                FROM nota_compra
                WHERE id_factura_compra = :fid
                  AND nota_compra_tipo = 'CREDITO'
                  AND nota_compra_estado = 'EMITIDA'
            ");
            $stSumNC->execute([':fid' => $factId]);
            $sumaNC = (int)$stSumNC->fetchColumn();
            
            // Verificar estado de la factura
            $stCheckEstado = $pdo->prepare("SELECT fac_estado FROM factura_compra WHERE id_factura_compra = :fid");
            $stCheckEstado->execute([':fid' => $factId]);
            $estadoActual = strtoupper(trim((string)$stCheckEstado->fetchColumn()));
            
            // Verificar monto pendiente en cuentas a pagar
            $stCheckPendiente = $pdo->prepare("SELECT monto_pendiente FROM cuentas_pagar WHERE id_factura_compra = :fid");
            $stCheckPendiente->execute([':fid' => $factId]);
            $montoPendienteActual = (int)$stCheckPendiente->fetchColumn();
            
            // Solo anular si:
            // 1. La suma de NC iguala o supera el total ORIGINAL de la factura
            // 2. La factura aún no está anulada
            // 3. El monto pendiente es 0 o muy cercano a 0 (tolerancia de 1 para redondeos)
            // Esto asegura que realmente no hay saldo pendiente antes de anular
            if ($sumaNC >= $facTotalOriginal && $estadoActual !== 'ANULADO' && $montoPendienteActual <= 1) {
                $stAnulFac = $pdo->prepare("
                    UPDATE factura_compra
                    SET fac_estado = 'ANULADO'
                    WHERE id_factura_compra = :fid
                ");
                $stAnulFac->execute([':fid' => $factId]);
                bitacora($pdo, $usuario_id, 'ANULACION', "Factura #{$factId} auto-anulada por suma de Notas de Crédito ({$sumaNC} >= {$facTotal})", $factId);
                
                $stAnulCta = $pdo->prepare("
                    UPDATE cuentas_pagar
                    SET estado = 'ANULADO',
                        monto_total = 0,
                        monto_pendiente = 0
                    WHERE id_factura_compra = :fid
                ");
                $stAnulCta->execute([':fid' => $factId]);
                bitacora($pdo, $usuario_id, 'ANULACION', "Cuenta a Pagar de Factura #{$factId} auto-anulada por suma de Notas de Crédito", $factId);
                
                // Revertir la orden de compra a su estado original (EMITIDA)
                $stOcAuto = $pdo->prepare("
                    SELECT oc.id_orden_compra
                    FROM orden_de_compra oc
                    JOIN factura_compra fc ON fc.id_orden_compra = oc.id_orden_compra
                    WHERE fc.id_factura_compra = :fid
                    LIMIT 1
                ");
                $stOcAuto->execute([':fid' => $factId]);
                $ocDataAuto = $stOcAuto->fetch(PDO::FETCH_ASSOC);
                
                if ($ocDataAuto && !empty($ocDataAuto['id_orden_compra'])) {
                    $idOcAuto = (int)$ocDataAuto['id_orden_compra'];
                    $stUpdOcAuto = $pdo->prepare("
                        UPDATE orden_de_compra
                        SET orden_estado = 'EMITIDA'
                        WHERE id_orden_compra = :oc
                    ");
                    $stUpdOcAuto->execute([':oc' => $idOcAuto]);
                    bitacora($pdo, $usuario_id, 'MODIFICACION', "OC #{$idOcAuto} → EMITIDA (Auto-anulación Factura #{$factId} por suma de Notas de Crédito)", $idOcAuto);
                }
            }
        }
    }

    // =========================== ACTUALIZAR STOCK (Punto 16.c) ===========================
    // Solo para NC por Devolución (asumiendo motivo 2 = Devolución, verificar con tabla motivo)
    // Cuando se devuelve mercadería al proveedor, el stock DISMINUYE (sale del depósito)
    // Ejemplo: tenía 10, quedan 8 → se devuelven 2 → stock disminuye en 2
    if ($notaTipo === 'CREDITO' && $motivoId === 2) {
        // Usar $itemsOriginales que tiene las cantidades ANTES de aplicar cambios
        foreach ($items as $it) {
            $prod = (int)($it['id_materia_prima'] ?? $it['id_producto'] ?? $it['codigo'] ?? 0);
            $cantNueva = (int)($it['cantidad'] ?? 0); // Cantidad que queda después de la devolución
            
            if ($prod <= 0 || $cantNueva < 0) continue;
            
            // Obtener la cantidad original desde $itemsOriginales (valores ANTES de aplicar cambios)
            $cantOriginal = isset($itemsOriginales[$prod]) ? (int)$itemsOriginales[$prod]['cantidad'] : 0;
            
            if ($cantOriginal <= 0) {
                error_log("ADVERTENCIA: Nota #{$idNota}: Materia prima {$prod} no encontrada en factura original");
                continue;
            }
            
            // Validar que la cantidad nueva no exceda la original
            if ($cantNueva > $cantOriginal) {
                $pdo->rollBack();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => "La cantidad ingresada ({$cantNueva}) no puede ser mayor a la facturada ({$cantOriginal}) para materia prima {$prod}"]);
                exit;
            }
            
            // Calcular la cantidad devuelta: diferencia entre original y nueva
            $cantDevuelta = $cantOriginal - $cantNueva;
            
            if ($cantDevuelta <= 0) {
                // No hay devolución para este item, continuar con el siguiente
                continue;
            }

            // Obtener el depósito: primero desde stock existente, luego desde orden de compra, finalmente usar sucursal
            $depositoId = null;
            
            // Intentar obtener desde stock existente
            $stStockInfo = $pdo->prepare("
                SELECT deposito_id, cantidad_existente
                FROM stock_materia_prima
                WHERE id_materia_prima = :mp
                LIMIT 1
            ");
            $stStockInfo->execute([':mp' => $prod]);
            $stockInfo = $stStockInfo->fetch(PDO::FETCH_ASSOC);

            if ($stockInfo !== false && !empty($stockInfo['deposito_id'])) {
                $depositoId = (int)$stockInfo['deposito_id'];
            } else {
                // Si no hay stock, intentar obtener desde la orden de compra de la factura
                $stOcDep = $pdo->prepare("
                    SELECT oc.id_sucursal
                    FROM factura_compra fc
                    JOIN orden_de_compra oc ON oc.id_orden_compra = fc.id_orden_compra
                    WHERE fc.id_factura_compra = :fid
                    LIMIT 1
                ");
                $stOcDep->execute([':fid' => $factId]);
                $ocDep = $stOcDep->fetch(PDO::FETCH_ASSOC);
                
                if ($ocDep && !empty($ocDep['id_sucursal'])) {
                    $depositoId = (int)$ocDep['id_sucursal'];
                } else {
                    // Finalmente, usar la sucursal del usuario
                    $depositoId = $id_sucursal;
                }
            }
            
            if ($depositoId <= 0) {
                $depositoId = 1; // Depósito por defecto
            }
            
            // DISMINUIR stock con la cantidad devuelta (salida de depósito al proveedor)
            $stStock = $pdo->prepare("
                UPDATE stock_materia_prima
                SET cantidad_existente = GREATEST(0, cantidad_existente - :c)
                WHERE id_materia_prima = :mp AND deposito_id = :d
            ");
            $stStock->execute([
                ':c'  => $cantDevuelta, // Restar la cantidad devuelta
                ':mp' => $prod,
                ':d'  => $depositoId
            ]);
            
            // Si no se actualizó ninguna fila, significa que no hay stock para este producto/depósito
            // En este caso, no creamos stock negativo, solo registramos en bitácora
            if ($stStock->rowCount() === 0) {
                bitacora($pdo, $usuario_id, 'ADVERTENCIA', "No se pudo disminuir stock: no existe registro para Materia Prima:{$prod} | Depósito:{$depositoId} | Cantidad devuelta: {$cantDevuelta} | Nota #{$idNota}", $idNota);
            } else {
                bitacora($pdo, $usuario_id, 'MODIFICACION', "Stock disminuido por devolución: -{$cantDevuelta} unid. (Original: {$cantOriginal}, Queda: {$cantNueva}) | Materia Prima:{$prod} | Depósito:{$depositoId} | Nota #{$idNota}", $idNota);
            }
        }
    }

    // =========================== ANULACIÓN TOTAL (Punto 16.d) ===========================
    // NC por Anulación total: marca Factura como Anulada
    // Asumiendo que motivo 1 = Anulación total (verificar con tabla motivo)
    if ($esAnulacionTotal) {
        // Ya validamos que sea el mismo día arriba
        $stAnulFac = $pdo->prepare("
            UPDATE factura_compra
            SET fac_estado = 'ANULADO'
            WHERE id_factura_compra = :fid
        ");
        $stAnulFac->execute([':fid' => $factId]);
        bitacora($pdo, $usuario_id, 'ANULACION', "Factura #{$factId} anulada por Nota de Crédito #{$idNota} (Anulación total)", $factId);

        // Anular también la cuenta a pagar (monto_total y monto_pendiente en 0)
        $stAnulCta = $pdo->prepare("
            UPDATE cuentas_pagar
            SET estado = 'ANULADO',
                monto_total = 0,
                monto_pendiente = 0
            WHERE id_factura_compra = :fid
        ");
        $stAnulCta->execute([':fid' => $factId]);
        bitacora($pdo, $usuario_id, 'ANULACION', "Cuenta a Pagar de Factura #{$factId} anulada por Nota de Crédito #{$idNota} (Anulación total)", $factId);
        
        // Revertir la orden de compra a su estado original (EMITIDA)
        $stOc = $pdo->prepare("
            SELECT oc.id_orden_compra
            FROM orden_de_compra oc
            JOIN factura_compra fc ON fc.id_orden_compra = oc.id_orden_compra
            WHERE fc.id_factura_compra = :fid
            LIMIT 1
        ");
        $stOc->execute([':fid' => $factId]);
        $ocData = $stOc->fetch(PDO::FETCH_ASSOC);
        
        if ($ocData && !empty($ocData['id_orden_compra'])) {
            $idOc = (int)$ocData['id_orden_compra'];
            $stUpdOc = $pdo->prepare("
                UPDATE orden_de_compra
                SET orden_estado = 'EMITIDA'
                WHERE id_orden_compra = :oc
            ");
            $stUpdOc->execute([':oc' => $idOc]);
            bitacora($pdo, $usuario_id, 'MODIFICACION', "OC #{$idOc} → EMITIDA (Anulación Factura #{$factId} por Nota de Crédito #{$idNota})", $idOc);
        }
    }

    // Commit de la transacción
    $pdo->commit();
    
    // Log para depuración
    error_log("Nota #{$idNota} registrada exitosamente - Tipo: {$notaTipo} | Total: {$totalCalc} | Factura #{$factId}");

    // Redirigir a la vista con mensaje de éxito (igual que otros módulos)
    header("Location: view.php?alert=1");
    exit;

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
