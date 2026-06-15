<?php
session_start();
require "../../config/database.php";

ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('America/Asuncion');

function bitacora(PDO $pdo, int $idUsuario, string $accion, string $desc, ?int $id = null): void {
    $pdo->exec("SAVEPOINT sp_bit");
    try {
        $sql = "
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:u, 'Nota de Crédito Venta', :id, :acc, :d)
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':u' => $idUsuario, ':id' => $id, ':acc' => strtoupper($accion), ':d' => $desc]);
    } catch (Throwable $e) {
        $pdo->exec("ROLLBACK TO SAVEPOINT sp_bit");
        error_log("Bitácora falló: " . $e->getMessage());
    }
}

function fail(string $msg) {
    echo "<script>alert(" . json_encode($msg) . ");window.history.back();</script>";
    exit;
}

function execSQL(PDO $pdo, string $label, string $sql, array $params = []): PDOStatement {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st;
    } catch (PDOException $e) {
        $detail = $e->errorInfo[2] ?? $e->getMessage();
        throw new Exception("[$label] ERROR: $detail");
    }
}

if (empty($_SESSION['username'])) {
    fail('Sesión expirada. Inicie sesión nuevamente.');
}
if (($_GET['act'] ?? '') !== 'insert_nota') {
    fail('Acción no soportada.');
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $pdo->exec("SET TIME ZONE 'America/Asuncion'");
} catch (Throwable $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Resolver usuario / sucursal
try {
    $idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
    if ($idUsuario <= 0) {
        $q = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username=:u LIMIT 1");
        $q->execute([':u' => $_SESSION['username']]);
        $idUsuario = (int)$q->fetchColumn();
    }
    if ($idUsuario <= 0) throw new Exception("No se pudo determinar el usuario.");

    $q = $pdo->prepare("SELECT id_sucursal FROM usuarios WHERE id_usuario=:id LIMIT 1");
    $q->execute([':id' => $idUsuario]);
    $idSucursal = (int)$q->fetchColumn();
    if ($idSucursal <= 0) throw new Exception("El usuario no tiene sucursal asociada.");
} catch (Throwable $e) {
    die("Error al resolver usuario/sucursal: " . $e->getMessage());
}

// Inputs
$idCliente = (int)($_POST['id_cliente'] ?? 0);
$factId = (int)($_POST['fact_id'] ?? 0);
$notaTipo = strtoupper(trim($_POST['nota_tipo'] ?? ''));
$motivoId = (int)($_POST['motivo_id'] ?? 0);
$descripcion = trim($_POST['descripcion'] ?? '');
$notaNroStr = trim($_POST['nota_nro'] ?? '');
// Extraer solo la parte numérica final (NNNNNNN) del formato EEE-PPP-NNNNNNN
// Si tiene formato con guiones, extraer la última parte; si no, extraer todos los dígitos
if (preg_match('/-(\d+)$/', $notaNroStr, $matches)) {
    $notaNro = (int)$matches[1]; // Extraer solo la parte final numérica
} else {
    // Si no tiene guiones, extraer todos los dígitos pero limitar a los últimos 7
    $digitos = preg_replace('/\D+/', '', $notaNroStr);
    $notaNro = (int)substr($digitos, -7); // Tomar los últimos 7 dígitos
}
$timbrado = trim($_POST['nota_timbrado'] ?? '');
$notaEmision = substr((string)($_POST['nota_emision'] ?? ''), 0, 10);
// Si no viene fecha de emisión, usar la fecha actual
if (empty($notaEmision) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $notaEmision)) {
    $notaEmision = date('Y-m-d');
}
$items = json_decode($_POST['productos'] ?? '[]', true);
$totalFrontNum = (float)($_POST['nota_total_num'] ?? 0);
$medioDevolucion = trim($_POST['medio_devolucion'] ?? 'EFECTIVO');
$subtotal = (float)($_POST['subtotal'] ?? 0);
$iva5 = (float)($_POST['iva_5'] ?? 0);
$iva10 = (float)($_POST['iva_10'] ?? 0);
$ivaExento = (float)($_POST['iva_exento'] ?? 0);

if (!is_array($items)) $items = [];

// Validaciones
if ($idCliente <= 0) fail("Cliente inválido.");
if ($factId <= 0) fail("Factura no seleccionada.");
// Solo se manejan Notas de Crédito
if ($notaTipo !== 'CREDITO') {
    $notaTipo = 'CREDITO'; // Forzar a CREDITO si viene otro valor
}
if ($motivoId < 1) fail("Motivo inválido.");
if ($notaNro <= 0) fail("N° de Nota inválido.");
if (!preg_match('/^\d{8}$/', $timbrado)) fail("Timbrado inválido (8 dígitos).");
// Validar formato de fecha
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $notaEmision)) {
    fail("Fecha de emisión inválida. Formato esperado: YYYY-MM-DD");
}
if (empty($items)) fail("Debe incluir al menos un ítem.");

// Validar timbrado vigente
try {
    $stTimbrado = $pdo->prepare("
        SELECT ct.id_timbrado, ct.id_caja, t.timbrado_numero AS timbrado, 
               ct.fecha_vencimiento, ct.estado
        FROM caja_timbrado ct
        JOIN timbrado t ON t.id_timbrado = ct.id_timbrado
        WHERE t.timbrado_numero = :timbrado
          AND ct.estado = 'ACTIVO'
          AND ct.fecha_vencimiento >= CURRENT_DATE
        LIMIT 1
    ");
    $stTimbrado->execute([':timbrado' => $timbrado]);
    $timbradoData = $stTimbrado->fetch();
    
    if (!$timbradoData) {
        fail("El timbrado no está vigente o ha vencido. Verifique el timbrado y su fecha de vencimiento.");
    }
} catch (Throwable $e) {
    // Si la tabla no existe, solo mostrar advertencia pero continuar
    error_log("Advertencia: No se pudo validar timbrado: " . $e->getMessage());
}

// Validar que el número de nota no esté duplicado
try {
    $stDuplicado = $pdo->prepare("
        SELECT COUNT(*) 
        FROM nota_venta 
        WHERE nota_nro = :nro AND nota_venta_timbrado = :timbrado
    ");
    $stDuplicado->execute([':nro' => $notaNroStr, ':timbrado' => $timbrado]);
    $existeDuplicado = (int)$stDuplicado->fetchColumn();
    
    if ($existeDuplicado > 0) {
        fail("El número de nota ya existe para este timbrado. Por favor, verifique el número.");
    }
} catch (Throwable $e) {
    error_log("Advertencia: No se pudo validar duplicado: " . $e->getMessage());
}

// Confirmar factura existe/estado/cliente
$stF = $pdo->prepare("
    SELECT fv.id_factura_venta, fv.fecha_emision::date AS fac_emision,
           fv.estado, fv.total_general, fv.id_cliente, fv.tipo_factura, fv.id_pedido_venta
    FROM factura_ventas fv
    WHERE fv.id_factura_venta = :id
    LIMIT 1
");
$stF->execute([':id' => $factId]);
$fac = $stF->fetch();
if (!$fac) fail("Factura inexistente.");
if ((int)$fac['id_cliente'] !== $idCliente) fail("La factura no corresponde al cliente.");
if ($fac['estado'] !== 'EMITIDA') {
    fail("La factura no está en un estado válido para emitir notas (debe estar EMITIDA).");
}

// Validar tope de notas de crédito (validación inicial, se revalidará en transacción)
$stTop = $pdo->prepare("
    SELECT 
        fv.total_general AS factura_total,
        COALESCE(SUM(nv.nota_total), 0) AS total_creditos
    FROM factura_ventas fv
    LEFT JOIN nota_venta nv
           ON nv.id_factura_venta = fv.id_factura_venta
          AND nv.nota_venta_tipo = 'CREDITO'
          AND nv.nota_venta_estado NOT IN ('ANULADA')
    WHERE fv.id_factura_venta = :fid
    GROUP BY fv.total_general
");
$stTop->execute([':fid' => $factId]);
$rowTop = $stTop->fetch(PDO::FETCH_ASSOC);

if (!$rowTop) fail("Factura no encontrada para validación de tope.");

$facTotal = (float)$rowTop['factura_total'];
$creditosYa = (float)$rowTop['total_creditos'];
$creditosConNueva = $creditosYa + $totalFrontNum;
$disponible = max(0, $facTotal - $creditosYa);

if ($creditosConNueva > $facTotal) {
    header("Location: view.php?nueva_nota=add&form=add&err=limite_credito&fac={$factId}&total_fac={$facTotal}&ya={$creditosYa}&disp={$disponible}");
    exit;
}

// Validar fechas
$hoy = (new DateTime('now', new DateTimeZone('America/Asuncion')))->format('Y-m-d');
$facEmision = $fac['fac_emision'] ?? $hoy;
if ($notaEmision < $facEmision || $notaEmision > $hoy) {
    fail("La emisión de la nota debe estar entre {$facEmision} y {$hoy}.");
}

// Validar que las cantidades no excedan las de la factura (considerando otras NC ya emitidas)
foreach ($items as $it) {
    $productoId = (int)($it['producto_id'] ?? 0);
    $cantidadNC = (int)($it['cantidad'] ?? 0);
    
    // Obtener cantidad en la factura
    $stDetFact = $pdo->prepare("
        SELECT cantidad
        FROM factura_detalle_venta
        WHERE id_factura_venta = :fact_id AND producto_id = :prod_id
    ");
    $stDetFact->execute([':fact_id' => $factId, ':prod_id' => $productoId]);
    $detFact = $stDetFact->fetch();
    
    if (!$detFact) {
        fail("El producto ID {$productoId} no existe en la factura.");
    }
    
    $cantidadFact = (int)$detFact['cantidad'];
    
    // Obtener cantidad ya usada en otras NC (no anuladas)
    $stCantNC = $pdo->prepare("
        SELECT COALESCE(SUM(ndv.cantidad_nota), 0) AS cantidad_usada
        FROM nota_detalle_venta ndv
        JOIN nota_venta nv ON nv.id_nota_venta = ndv.id_nota_venta
        WHERE nv.id_factura_venta = :fact_id
          AND ndv.producto_id = :prod_id
          AND nv.nota_venta_estado != 'ANULADA'
          AND nv.nota_venta_tipo = 'CREDITO'
    ");
    $stCantNC->execute([':fact_id' => $factId, ':prod_id' => $productoId]);
    $cantidadUsada = (int)$stCantNC->fetchColumn();
    
    $cantidadDisponible = $cantidadFact - $cantidadUsada;
    
    if ($cantidadNC > $cantidadDisponible) {
        fail("La cantidad de la nota ({$cantidadNC}) no puede exceder la cantidad disponible ({$cantidadDisponible}) para el producto ID {$productoId}. Cantidad factura: {$cantidadFact}, ya usada en otras NC: {$cantidadUsada}.");
    }
}

// Verificar caja abierta si hay reintegro en efectivo (según especificación: solo si habrá reintegro en efectivo)
// Nota: nota_venta.id_apertura_cierre referencia a apertura_cierre, pero validamos con apertura_cierre_caja
// Como id_apertura_cierre es nullable, lo dejamos como null pero validamos que la caja esté abierta
$idAperturaCierre = null;
$requiereCajaAbierta = ($medioDevolucion === 'EFECTIVO' || ($notaTipo === 'CREDITO' && $fac['tipo_factura'] === 'CONTADO'));
if ($requiereCajaAbierta) {
    $stCaja = $pdo->prepare("
        SELECT acc.id_apertura
        FROM apertura_cierre_caja acc
        WHERE acc.id_sucursal = :sucursal_id
          AND acc.apertura_estado = 'ABIERTA'
        LIMIT 1
    ");
    $stCaja->execute([':sucursal_id' => $idSucursal]);
    $cajaAbierta = $stCaja->fetch();
    if (!$cajaAbierta) {
        fail("No hay caja abierta en la sucursal. Se requiere caja abierta para reintegro en efectivo.");
    }
    // id_apertura_cierre se deja como null porque referencia a apertura_cierre, no a apertura_cierre_caja
    // La validación de caja abierta se hace con apertura_cierre_caja según la especificación
}

// TRANSACCIÓN
try {
    $pdo->beginTransaction();

    // Validación de concurrencia: Bloquear factura con FOR UPDATE
    $stFactConcurrencia = $pdo->prepare("
        SELECT 
            fv.total_general AS factura_total,
            fv.estado AS factura_estado
        FROM factura_ventas fv
        WHERE fv.id_factura_venta = :fid
        FOR UPDATE
    ");
    $stFactConcurrencia->execute([':fid' => $factId]);
    $factConcurrencia = $stFactConcurrencia->fetch();

    if (!$factConcurrencia) {
        throw new Exception("Factura no encontrada para validación de concurrencia.");
    }

    // Validar estado de factura nuevamente (concurrencia)
    if ($factConcurrencia['factura_estado'] !== 'EMITIDA') {
        throw new Exception("La factura ya no está en estado EMITIDA. Puede que haya sido anulada por otra nota de crédito emitida simultáneamente.");
    }

    // Calcular total de créditos ya emitidos (consulta separada sin FOR UPDATE)
    $stCreditos = $pdo->prepare("
        SELECT COALESCE(SUM(nota_total), 0) AS total_creditos
        FROM nota_venta
        WHERE id_factura_venta = :fid
          AND nota_venta_tipo = 'CREDITO'
          AND nota_venta_estado NOT IN ('ANULADA')
    ");
    $stCreditos->execute([':fid' => $factId]);
    $creditosYa = (float)$stCreditos->fetchColumn();

    // Revalidar tope de notas de crédito (concurrencia)
    $facTotal = (float)$factConcurrencia['factura_total'];
    $creditosConNueva = $creditosYa + $totalFrontNum;
    $disponible = max(0, $facTotal - $creditosYa);

    if ($creditosConNueva > $facTotal) {
        throw new Exception("El monto de la nota excede el disponible. Otra nota de crédito puede haber sido emitida simultáneamente. Disponible: " . number_format($disponible, 0, ',', '.') . " Gs");
    }

    // Insertar cabecera de nota
    // Nota: fecha_emision, monto_total y nota_numero son NOT NULL
    $stCab = execSQL(
        $pdo,
        'CABECERA',
        "INSERT INTO nota_venta
         (nota_venta_tipo, nota_venta_fecha, nota_nro, nota_venta_timbrado,
          nota_venta_estado, nota_total, subtotal, iva_5, iva_10, iva_exento,
          id_usuario, id_sucursal, id_cliente, id_motivo, fecha_emision, nota_venta_emision,
          descripcion, medio_devolucion, id_factura_venta, id_apertura_cierre,
          monto_total, nota_numero, nota_estado)
         VALUES
         (:tipo, CURRENT_DATE, :nro, :timbrado,
          'EMITIDA', :total, :subtotal, :iva5, :iva10, :exento,
          :idu, :ids, :idc, :idm, :emi, :emi,
          :desc, :medio, :fid, :apertura,
          :monto_total, :nota_numero, 'EMITIDA')
         RETURNING id_nota_venta",
        [
            ':tipo' => $notaTipo,
            ':nro' => $notaNroStr,
            ':timbrado' => $timbrado,
            ':total' => $totalFrontNum,
            ':subtotal' => $subtotal,
            ':iva5' => $iva5,
            ':iva10' => $iva10,
            ':exento' => $ivaExento,
            ':idu' => $idUsuario,
            ':ids' => $idSucursal,
            ':idc' => $idCliente,
            ':idm' => $motivoId,
            ':emi' => $notaEmision,
            ':desc' => ($descripcion !== '' ? $descripcion : null),
            ':medio' => $medioDevolucion,
            ':fid' => $factId,
            ':apertura' => $idAperturaCierre,
            ':monto_total' => (int)round($totalFrontNum), // monto_total es integer NOT NULL
            ':nota_numero' => $notaNro // nota_numero es integer NOT NULL
        ]
    );
    $idNota = (int)$stCab->fetchColumn();
    if ($idNota <= 0) throw new Exception("No se obtuvo el ID de la nota.");

    bitacora(
        $pdo,
        $idUsuario,
        'ALTA',
        "Alta Nota Venta: id={$idNota} | tipo={$notaTipo} | motivo={$motivoId} | cliente={$idCliente} | total={$totalFrontNum} | factura={$factId}",
        $idNota
    );

    // Insertar detalle
    foreach ($items as $it) {
        $productoId = (int)($it['producto_id'] ?? 0);
        $cantidad = (int)($it['cantidad'] ?? 0);
        $precio = (float)($it['precio'] ?? 0);
        $ivaPorcentaje = (float)($it['iva_porcentaje'] ?? 0);
        $subtotalLinea = $cantidad * $precio;
        $ivaMonto = $subtotalLinea * ($ivaPorcentaje / 100);
        $totalLinea = $subtotalLinea + $ivaMonto;

        if ($productoId <= 0 || $cantidad <= 0 || $precio <= 0) continue;

        execSQL(
            $pdo,
            'DETALLE',
            "INSERT INTO nota_detalle_venta
             (id_nota_venta, producto_id, cantidad_nota, nota_precio, nota_iva,
              subtotal, iva_porcentaje, iva_monto, total_linea)
             VALUES
             (:nota, :prod, :cant, :precio, :nota_iva,
              :subtotal, :iva_pct, :iva_monto, :total)",
            [
                ':nota' => $idNota,
                ':prod' => $productoId,
                ':cant' => $cantidad,
                ':precio' => (int)round($precio), // nota_precio es integer NOT NULL
                ':nota_iva' => $ivaMonto, // nota_iva es numeric NOT NULL
                ':subtotal' => $subtotalLinea,
                ':iva_pct' => $ivaPorcentaje,
                ':iva_monto' => $ivaMonto,
                ':total' => $totalLinea
            ]
        );
    }

    // Ajustar IVA en iva_venta (revertir)
    $stIva = $pdo->prepare("
        SELECT iva_exento, iva_5, iva_10
        FROM iva_venta
        WHERE id_factura_venta = :fact_id
        LIMIT 1
    ");
    $stIva->execute([':fact_id' => $factId]);
    $ivaFact = $stIva->fetch();

    if ($ivaFact) {
        // Actualizar o insertar registro de IVA ajustado
        $stUpdIva = $pdo->prepare("
            UPDATE iva_venta
            SET iva_exento = GREATEST(0, iva_exento - :exento),
                iva_5 = GREATEST(0, iva_5 - :iva5),
                iva_10 = GREATEST(0, iva_10 - :iva10)
            WHERE id_factura_venta = :fact_id
        ");
        $stUpdIva->execute([
            ':exento' => $ivaExento,
            ':iva5' => $iva5,
            ':iva10' => $iva10,
            ':fact_id' => $factId
        ]);
    }

    // Verificar el motivo para determinar si es Anulación Total o Devolución
    $stMotivo = $pdo->prepare("
        SELECT motivo_descripcion, categoria_motivo
        FROM motivo
        WHERE id_motivo = :motivo_id
        LIMIT 1
    ");
    $stMotivo->execute([':motivo_id' => $motivoId]);
    $motivo = $stMotivo->fetch();
    $esAnulacionTotal = false;
    $esDevolucion = false;
    if ($motivo) {
        $motivoDesc = strtoupper(trim($motivo['motivo_descripcion']));
        $categoriaMotivo = strtoupper(trim($motivo['categoria_motivo'] ?? ''));
        
        // Verificar si es Anulación Total - buscar "ANULACION", "ANULACIÓN" o "ANULAC" en el nombre
        // También verificar si contiene "TOTAL" para asegurar que es anulación total
        $tieneAnulacion = (
            strpos($motivoDesc, 'ANULACION') !== false || 
            strpos($motivoDesc, 'ANULACIÓN') !== false || 
            strpos($motivoDesc, 'ANULAC') !== false
        );
        $tieneTotal = strpos($motivoDesc, 'TOTAL') !== false;
        
        $esAnulacionTotal = (
            ($categoriaMotivo === 'NOTA_CREDITO_VENTA' || $categoriaMotivo === 'NOTA_CREDITO') && 
            $tieneAnulacion && 
            $tieneTotal
        );
        
        // Verificar si es Devolución de Mercadería
        $esDevolucion = (
            ($categoriaMotivo === 'NOTA_CREDITO_VENTA' || $categoriaMotivo === 'NOTA_CREDITO') && 
            (strpos($motivoDesc, 'DEVOLUCION') !== false || 
             strpos($motivoDesc, 'DEVOLUCIÓN') !== false ||
             strpos($motivoDesc, 'DEVOLUC') !== false) &&
            !$tieneAnulacion // No es devolución si tiene anulación
        );
        
        // Log para depuración
        error_log("Nota Crédito - Motivo ID: {$motivoId}, Desc: '{$motivoDesc}', Categoría: '{$categoriaMotivo}', esAnulacionTotal: " . ($esAnulacionTotal ? 'true' : 'false') . ", tieneAnulacion: " . ($tieneAnulacion ? 'true' : 'false') . ", tieneTotal: " . ($tieneTotal ? 'true' : 'false'));
    }
    
    // Reponer stock SOLO si es Anulación Total (según especificación del usuario)
    if ($esAnulacionTotal) {
        error_log("Nota Crédito - Iniciando reposición de stock para Anulación Total. Items: " . count($items));
        foreach ($items as $it) {
            $productoId = (int)($it['producto_id'] ?? 0);
            $cantidad = (int)($it['cantidad'] ?? 0);
            
            if ($productoId <= 0 || $cantidad <= 0) continue;

            // Verificar si el producto es inventariable
            // Asumimos que si tiene registro en stock_producto o si tiene producto_estado = 'ACTIVO', es inventariable
            $stProd = $pdo->prepare("
                SELECT producto_id, producto_estado,
                       CASE 
                           WHEN EXISTS (SELECT 1 FROM stock_producto WHERE producto_id = p.producto_id LIMIT 1) 
                           THEN true 
                           ELSE false 
                       END AS es_inventariable
                FROM productos p
                WHERE p.producto_id = :producto_id
                LIMIT 1
            ");
            $stProd->execute([':producto_id' => $productoId]);
            $producto = $stProd->fetch();
            
            // Si el producto no existe o no es inventariable, saltar reposición de stock
            if (!$producto) {
                error_log("Advertencia: Producto ID {$productoId} no encontrado, no se repone stock.");
                continue;
            }
            
            // Solo reponer stock si el producto es inventariable (tiene registro en stock_producto)
            // O si tiene producto_estado = 'ACTIVO' (asumimos que productos activos son inventariables)
            $esInventariable = $producto['es_inventariable'] === true || 
                               ($producto['producto_estado'] === 'ACTIVO');
            
            if (!$esInventariable) {
                error_log("Advertencia: Producto ID {$productoId} no es inventariable, no se repone stock.");
                continue;
            }

            // Obtener depósito desde el stock existente del producto, o usar depósito por defecto
            $depositoId = 1; // Por defecto
            $stDep = $pdo->prepare("
                SELECT deposito_id
                FROM stock_producto
                WHERE producto_id = :producto_id
                LIMIT 1
            ");
            $stDep->execute([':producto_id' => $productoId]);
            $dep = $stDep->fetch();
            if ($dep && !empty($dep['deposito_id'])) {
                $depositoId = (int)$dep['deposito_id'];
            }

            // Actualizar stock
            $stStock = $pdo->prepare("
                UPDATE stock_producto
                SET stock_prod_existente = stock_prod_existente + :cantidad
                WHERE producto_id = :producto AND deposito_id = :deposito
                RETURNING id_stock_productos
            ");
            $stStock->execute([
                ':cantidad' => $cantidad,
                ':producto' => $productoId,
                ':deposito' => $depositoId
            ]);
            
            if ($stStock->rowCount() === 0) {
                // Si no existe, crear registro
                $stInsStock = $pdo->prepare("
                    INSERT INTO stock_producto (producto_id, deposito_id, stock_prod_existente, stock_prod_ven, id_usuario)
                    VALUES (:producto, :deposito, :cantidad, CURRENT_DATE, :usuario)
                ");
                $stInsStock->execute([
                    ':producto' => $productoId,
                    ':deposito' => $depositoId,
                    ':cantidad' => $cantidad,
                    ':usuario' => $idUsuario
                ]);
            }
            
            bitacora($pdo, $idUsuario, 'MODIFICACION', "Stock repuesto: +{$cantidad} unid. | Producto:{$productoId} | Depósito:{$depositoId} | Nota:{$idNota}", $idNota);
            error_log("Nota Crédito - Stock repuesto: +{$cantidad} unidades para producto {$productoId} en depósito {$depositoId}");
        }
    } else {
        error_log("Nota Crédito - NO se repone stock. esAnulacionTotal: " . ($esAnulacionTotal ? 'true' : 'false') . ", motivo_id: {$motivoId}");
    }

    // Ajustar CxC si es crédito
    if ($fac['tipo_factura'] === 'CREDITO') {
        $stCxC = $pdo->prepare("
            SELECT id_cuenta_cobrar, saldo_pendiente
            FROM cuentas_cobrar
            WHERE id_factura_venta = :fact_id AND estado = 'PENDIENTE'
            LIMIT 1
        ");
        $stCxC->execute([':fact_id' => $factId]);
        $cxc = $stCxC->fetch();

        if ($cxc) {
            $nuevoSaldo = max(0, (float)$cxc['saldo_pendiente'] - $totalFrontNum);
            $stUpdCxC = $pdo->prepare("
                UPDATE cuentas_cobrar
                SET saldo_pendiente = :saldo
                WHERE id_cuenta_cobrar = :cxc_id
            ");
            $stUpdCxC->execute([
                ':saldo' => $nuevoSaldo,
                ':cxc_id' => (int)$cxc['id_cuenta_cobrar']
            ]);

            if ($nuevoSaldo <= 0) {
                $stCerrarCxC = $pdo->prepare("
                    UPDATE cuentas_cobrar
                    SET estado = 'PAGADA'
                    WHERE id_cuenta_cobrar = :cxc_id
                ");
                $stCerrarCxC->execute([':cxc_id' => (int)$cxc['id_cuenta_cobrar']]);
            }
        }
    }

    // Verificar si es anulación total (incluyendo la nota actual)
    $stTotalNC = $pdo->prepare("
        SELECT COALESCE(SUM(nota_total), 0) AS total_nc
        FROM nota_venta
        WHERE id_factura_venta = :fact_id
          AND nota_venta_estado != 'ANULADA'
          AND nota_venta_tipo = 'CREDITO'
    ");
    $stTotalNC->execute([':fact_id' => $factId]);
    $totalNC = (float)$stTotalNC->fetchColumn();
    
    // Incluir la nota actual en el total
    $totalNC += $totalFrontNum;

    // Si el total de NC >= total de factura, anular factura y actualizar pedido
    if ($totalNC >= (float)$fac['total_general']) {
        $stAnularFact = $pdo->prepare("
            UPDATE factura_ventas
            SET estado = 'ANULADA', factura_estado = 'ANULADA'
            WHERE id_factura_venta = :fact_id
        ");
        $stAnularFact->execute([':fact_id' => $factId]);
        bitacora($pdo, $idUsuario, 'MODIFICACION', "Factura #{$factId} → ANULADA (Nota de Crédito #{$idNota})", $factId);
        
        // Actualizar estado del pedido si existe
        if (!empty($fac['id_pedido_venta']) && $fac['id_pedido_venta'] > 0) {
            $stPedido = $pdo->prepare("
                UPDATE pedido_venta
                SET pedido_estado = 'ANULADO'
                WHERE id_pedido_venta = :pedido_id
            ");
            $stPedido->execute([':pedido_id' => (int)$fac['id_pedido_venta']]);
            bitacora($pdo, $idUsuario, 'MODIFICACION', "Pedido #{$fac['id_pedido_venta']} → ANULADO (Factura #{$factId} anulada)", (int)$fac['id_pedido_venta']);
        }
    }

    $pdo->commit();
    header("Location: view.php?alert=1");
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fail("Error al procesar la nota: " . $e->getMessage());
}
?>

