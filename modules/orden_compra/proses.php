<?php
session_start();
require "../../config/database.php";

/* ===== Bitácora ===== */
function bitacora(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ");
        $stmt->execute([
            ':id_usuario'  => $idUsuario,
            ':entidad'     => 'Orden compra',      // ← para este módulo
            ':id_registro' => $idRegistro,
            ':accion'      => strtoupper($accion),
            ':descripcion' => $descripcion
        ]);
    } catch (Throwable $e) {
        error_log("Bitácora falló: ".$e->getMessage());
    }
}

/* ===== Seguridad ===== */
if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href='../../login.html';
          </script>";
    exit();
}

if (!isset($_GET['act'])) {
    http_response_code(400);
    exit('Acción no especificada.');
}

$action = $_GET['act'];

try {
    /* ===== Conexión ===== */
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    if ($action === 'insert' && isset($_POST['Guardar'])) {
        /* ====== 1) Recolectar y validar ====== */
        // Cabecera del form
        $idOc         = (int)($_POST['oc_codigo']     ?? 0);
        $idPresu      = (int)($_POST['presupuesto']   ?? 0);
        $idProvForm   = (int)($_POST['proveedor']     ?? 0);
        $idSucursal   = (int)($_POST['oc_sucursal']   ?? 0);
        $idUsuario    = (int)($_POST['oc_usuario']    ?? 0);
        $condicion    = strtoupper(trim($_POST['orden_condicion'] ?? 'CONTADO'));  // CONTADO|CREDITO
        $observaciones = trim($_POST['oc_observaciones'] ?? '');                // Observaciones opcionales
        $totalImporte = (int)preg_replace('/\D+/', '', (string)($_POST['total_importe'] ?? '0'));
        $jsonDetalle  = $_POST['productos'] ?? '[]';
        $detalle      = json_decode($jsonDetalle, true);

        if ($idOc <= 0)              throw new Exception("Código de orden inválido.");
        if ($idPresu <= 0)           throw new Exception("Seleccione un presupuesto.");
        if ($idProvForm <= 0)        throw new Exception("Seleccione un proveedor.");
        if ($idSucursal <= 0)        throw new Exception("Sucursal inválida.");
        if ($idUsuario <= 0)         throw new Exception("Usuario inválido.");
        if (!in_array($condicion, ['CONTADO','CREDITO'], true)) {
            throw new Exception("Condición inválida.");
        }
        if (!is_array($detalle) || count($detalle) === 0) {
            throw new Exception("El detalle está vacío.");
        }
        
        // Validar que el detalle sea idéntico al presupuesto (según especificación)
        // Obtener detalle del presupuesto para comparar
        $stPresuDet = $pdo->prepare("
            SELECT id_materia_prima, detalle_presu_cantidad, detalle_presu_precio_compra
            FROM presupuesto_detalle_compra
            WHERE id_presupuesto_compra = :id
            ORDER BY id_materia_prima ASC
        ");
        $stPresuDet->execute([':id' => $idPresu]);
        $detallePresu = $stPresuDet->fetchAll(PDO::FETCH_ASSOC);
        
        // Validar que tengan la misma cantidad de ítems
        if (count($detalle) !== count($detallePresu)) {
            throw new Exception("El detalle de la OC debe ser idéntico al presupuesto. No se pueden agregar o quitar ítems.");
        }
        
        // Crear mapa del detalle del presupuesto para comparación rápida
        $mapPresu = [];
        foreach ($detallePresu as $dp) {
            $mapPresu[(int)$dp['id_materia_prima']] = [
                'cantidad' => (int)$dp['detalle_presu_cantidad'],
                'precio' => (int)$dp['detalle_presu_precio_compra']
            ];
        }
        
        foreach ($detalle as $i => $it) {
            if (!isset($it['codigo'], $it['cantidad'], $it['precio'])) {
                throw new Exception("Detalle incompleto en la fila ".($i+1).".");
            }
            if ((int)$it['cantidad'] <= 0 || (int)$it['precio'] <= 0) {
                throw new Exception("Cantidad/Precio inválidos en la fila ".($i+1).".");
            }
            
            $codigo = (int)$it['codigo'];
            $cantidad = (int)$it['cantidad'];
            $precio = (int)$it['precio'];
            
            // Validar que el ítem exista en el presupuesto
            if (!isset($mapPresu[$codigo])) {
                throw new Exception("El producto con código {$codigo} no existe en el presupuesto. No se pueden agregar ítems nuevos.");
            }
            
            // Validar que cantidad y precio sean idénticos
            if ($cantidad !== $mapPresu[$codigo]['cantidad']) {
                throw new Exception("La cantidad del producto con código {$codigo} debe ser idéntica al presupuesto ({$mapPresu[$codigo]['cantidad']}). No se pueden modificar cantidades.");
            }
            if ($precio !== $mapPresu[$codigo]['precio']) {
                throw new Exception("El precio del producto con código {$codigo} debe ser idéntico al presupuesto ({$mapPresu[$codigo]['precio']}). No se pueden modificar precios.");
            }
        }

        // Verificar que el presupuesto exista, pertenezca al mismo proveedor y esté en estado EMITIDO
        $stChk = $pdo->prepare("
            SELECT id_proveedor, presu_estado
            FROM presupuesto_compra
            WHERE id_presupuesto_compra = :id
            LIMIT 1
        ");
        $stChk->execute([':id' => $idPresu]);
        $presu = $stChk->fetch(PDO::FETCH_ASSOC);
        if (!$presu) throw new Exception("Presupuesto no encontrado.");
        if ((int)$presu['id_proveedor'] !== $idProvForm) {
            throw new Exception("El presupuesto no pertenece al proveedor seleccionado.");
        }
        // Validar que el presupuesto esté en estado EMITIDO según especificación
        $estadoPresu = strtoupper(trim((string)$presu['presu_estado']));
        if ($estadoPresu !== 'EMITIDO') {
            throw new Exception("El presupuesto debe estar en estado EMITIDO para crear una Orden de Compra. Estado actual: {$estadoPresu}.");
        }

        // Verificar duplicidad de Orden vs Presupuesto/Proveedor
        $stmtDup = $pdo->prepare("
            SELECT 1
            FROM orden_de_compra
            WHERE id_presupuesto_compra = :presu
              AND id_proveedor = :prov
              AND orden_estado <> 'ANULADO'
            LIMIT 1
        ");
        $stmtDup->execute([
            ':presu' => $idPresu,
            ':prov'  => $idProvForm
        ]);
        if ($stmtDup->fetchColumn()) {
            header("Location: view.php?alert=7");
            exit;
        }


        // Campo plazo de entrega eliminado según requerimiento

        /* ====== 2) Transacción ====== */
        $pdo->beginTransaction();

        // 2.1 Cabecera (usamos CURRENT_TIMESTAMP en la DB para orden_fecha)
        // Estado inicial: EMITIDA según especificación
        $sqlCab = "
            INSERT INTO orden_de_compra
                (id_orden_compra, orden_fecha, orden_estado, orden_total,
                 id_presupuesto_compra, id_proveedor, id_sucursal, id_usuario, orden_condicion)
            VALUES
                (:id, CURRENT_TIMESTAMP, 'EMITIDA', :tot,
                 :presu, :prov, :suc, :usr, :cond)
        ";
        $pdo->prepare($sqlCab)->execute([
            ':id'    => $idOc,
            ':tot'   => $totalImporte,
            ':presu' => $idPresu,
            ':prov'  => $idProvForm,
            ':suc'   => $idSucursal,
            ':usr'   => $idUsuario,
            ':cond'  => $condicion
        ]);
        
        // Actualizar campo orden_observaciones si existe
        $pdo->exec("SAVEPOINT sp_observaciones");
        try {
            $pdo->prepare("
                UPDATE orden_de_compra 
                SET orden_observaciones = :obs 
                WHERE id_orden_compra = :id
            ")->execute([
                ':obs' => ($observaciones ?: null),
                ':id'  => $idOc
            ]);
            $pdo->exec("RELEASE SAVEPOINT sp_observaciones");
        } catch (PDOException $e) {
            // Si el campo no existe, hacer rollback al savepoint y continuar
            $pdo->exec("ROLLBACK TO SAVEPOINT sp_observaciones");
            error_log("Campo orden_observaciones no existe: " . $e->getMessage());
        }

        bitacora(
            $pdo, $idUsuario, 'ALTA',
            "Crea Orden de Compra #{$idOc} (presupuesto {$idPresu}, proveedor {$idProvForm}, condicion {$condicion})",
            $idOc
        );

        // insertar detalle orden compra y bitacora
        $query_detalle = $pdo->prepare("
            INSERT INTO orden_detalle_compra
                (id_orden_compra, id_materia_prima, oc_cantidad_compra, oc_precio_compra)
            VALUES
                (:orden_id, :cod_materia_prima, :cantidad, :precio)
        ");

        foreach ($detalle as $item) {
            if (!isset($item['codigo'], $item['cantidad'], $item['precio'])) {
                throw new Exception("Falta información en un elemento del detalle: " . json_encode($item));
            }

            // Usar el ID real de cabecera para la FK
            $query_detalle->bindValue(':orden_id',     (int)$idOc,             PDO::PARAM_INT);
            $query_detalle->bindValue(':cod_materia_prima', (int)$item['codigo'],   PDO::PARAM_INT);
            $query_detalle->bindValue(':cantidad',     (int)$item['cantidad'], PDO::PARAM_INT);
            $query_detalle->bindValue(':precio',       (string)$item['precio'],PDO::PARAM_STR);
            $query_detalle->execute();

            // BITÁCORA: detalle (ALTA)
            bitacora(
                $pdo,
                (int)$idUsuario,
                'ALTA',
                "Detalle Orden de Compra: orden #{$idOc}, materia prima: {$item['codigo']}, cantidad: {$item['cantidad']}, precio {$item['precio']}.",
                (int)$idOc
            );
        }

        // 2.3 Actualizar presupuesto a PROCESADO
        $pdo->prepare("
            UPDATE presupuesto_compra
            SET presu_estado = 'APROBADO'
            WHERE id_presupuesto_compra = :id
        ")->execute([':id' => $idPresu]);

        bitacora(
            $pdo, $idUsuario, 'MODIFICACION',
            "Actualiza presupuesto {$idPresu} a APROBADO  por generación de Orden #{$idOc}",
            $idPresu
        );

        $pdo->commit();

        header("Location: view.php?alert=1"); // éxito
        exit();
    }

        // PARA ACTUALIZAR LA ORDEN DE COMRA, SOLAMENTE CONDICIONES
        else if ($action === 'update' && (isset($_POST['Actualizar']) || isset($_POST['Guardar']))) {
            // Usuario de sesión (para bitácora)
            $idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
            if ($idUsuario <= 0) {
                header("Location: view.php?alert=4"); // sesión inválida
                exit;
            }

            /* ===== 1) Recolectar y validar ===== */
            // Solo se modifican estos campos: condición y observaciones
            $condicion    = strtoupper(trim((string)($_POST['orden_condicion'] ?? '')));
            $observaciones = trim((string)($_POST['oc_observaciones'] ?? ''));
            $idOc = (int)($_POST['oc_codigo'] ?? 0);
            if ($idOc <= 0) {
                header("Location: view.php?alert=4"); // id inválido
                exit;
            }

            if (!in_array($condicion, ['CONTADO','CREDITO'], true)) {
                header("Location: view.php?alert=4"); // condición inválida
                exit;
            }

            /* ===== 2) Obtener estado y valores actuales ===== */
            // Leer solo campos que sabemos que existen
            $st = $pdo->prepare("
                SELECT orden_estado
                FROM orden_de_compra
                WHERE id_orden_compra = :id
                LIMIT 1
            ");
            $st->execute([':id' => $idOc]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                header("Location: view.php?alert=4"); // no existe
                exit;
            }

            $estadoActual = strtoupper(trim((string)$row['orden_estado']));

            // Gate: solo edita si está EMITIDA (según especificación)
            if ($estadoActual !== 'EMITIDA') {
                header("Location: view.php?alert=5&msg=" . urlencode("Solo se pueden editar OCs en estado EMITIDA. Estado actual: {$estadoActual}."));
                exit;
            }

            // Detectar cambios
            $cambios = [];
            if ($condActual !== strtoupper($condicion)) {
                $cambios[] = "condición {$condActual} → " . strtoupper($condicion);
            }
            
            // Verificar cambios en observaciones si el campo existe
            $obsActual = '';
            try {
                $stObs = $pdo->prepare("SELECT COALESCE(orden_observaciones, '') AS orden_observaciones FROM orden_de_compra WHERE id_orden_compra = :id LIMIT 1");
                $stObs->execute([':id' => $idOc]);
                $obsRow = $stObs->fetch(PDO::FETCH_ASSOC);
                if ($obsRow && isset($obsRow['orden_observaciones'])) {
                    $obsActual = trim((string)$obsRow['orden_observaciones']);
                }
            } catch (PDOException $e) {
                // Campo no existe, ignorar
                error_log("Campo orden_observaciones no existe: " . $e->getMessage());
            }
            
            $obsNueva = trim((string)($observaciones ?? ''));
            if ($obsActual !== $obsNueva) {
                $cambios[] = "observaciones";
            }

            // Si no hay cambios, igual redirigir “modificado OK” para UX consistente
            if (empty($cambios)) {
                header("Location: view.php?alert=2");
                exit;
            }

            /* ===== 3) Actualizar ===== */
            $pdo->beginTransaction();

            // Actualizar orden_condicion
            $pdo->prepare("
                UPDATE orden_de_compra
                SET orden_condicion = :cond
                WHERE id_orden_compra = :id
            ")->execute([
                ':cond'  => $condicion,
                ':id'    => $idOc
            ]);
            
            // Actualizar campo orden_observaciones si existe
            $pdo->exec("SAVEPOINT sp_observaciones");
            try {
                $pdo->prepare("
                    UPDATE orden_de_compra 
                    SET orden_observaciones = :obs 
                    WHERE id_orden_compra = :id
                ")->execute([
                    ':obs' => ($observaciones ?: null),
                    ':id'  => $idOc
                ]);
                $pdo->exec("RELEASE SAVEPOINT sp_observaciones");
            } catch (PDOException $e) {
                // Si el campo no existe, hacer rollback al savepoint y continuar
                $pdo->exec("ROLLBACK TO SAVEPOINT sp_observaciones");
                error_log("Campo orden_observaciones no existe: " . $e->getMessage());
            }

            // Bitácora
            $desc = "Actualiza Orden de Compra #{$idOc}: " . implode(' | ', $cambios);
            bitacora($pdo, $idUsuario, 'MODIFICACION', $desc, $idOc);

            $pdo->commit();

            header("Location: view.php?alert=2"); // Datos modificados correctamente
            exit();
        }



        /* ====== Anular Orden de Compra ====== */
else if ($action === 'anular' && isset($_GET['orden_id'])) {
    try {
        $idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
        if ($idUsuario <= 0) { header("Location: view.php?alert=4"); exit; }

        $idOc = (int)$_GET['orden_id'];
        if ($idOc <= 0) { header("Location: view.php?alert=4"); exit; }

        // Leer estado actual (opcional: bloquear fila para evitar carrera)
        $stEstado = $pdo->prepare("
            SELECT orden_estado, id_presupuesto_compra
            FROM orden_de_compra
            WHERE id_orden_compra = :id
            LIMIT 1
        ");
        $stEstado->execute([':id' => $idOc]);
        $row = $stEstado->fetch(PDO::FETCH_ASSOC);

        if (!$row) { header("Location: view.php?alert=4"); exit; }

        $estadoActual = strtoupper(trim((string)$row['orden_estado']));
        $idPresu      = (int)$row['id_presupuesto_compra'];

        // ---- VALIDACIÓN CLAVE según especificación punto 20.3 ----
        // Solo permitir anular si está EMITIDA
        if ($estadoActual !== 'EMITIDA') {
            header("Location: view.php?alert=5&msg=" . urlencode("Solo se pueden anular órdenes en estado EMITIDA. Estado actual: {$estadoActual}."));
            exit;
        }

        // Verificar que no tenga facturas asociadas (punto 20.3: "el sistema verifica que la OC no tenga facturas asociadas")
        $stFacturas = $pdo->prepare("
            SELECT COUNT(*) 
            FROM factura_compra 
            WHERE id_orden_compra = :id
        ");
        $stFacturas->execute([':id' => $idOc]);
        $tieneFacturas = $stFacturas->fetchColumn() > 0;
        
        if ($tieneFacturas) {
            // Punto 20.5: "Si no cumple, el sistema rechaza la anulación e informa el motivo"
            header("Location: view.php?alert=8&msg=" . urlencode("La Orden #{$idOc} no puede anularse porque tiene factura(s) asociada(s)."));
            exit;
        }
        // ---- FIN VALIDACIÓN ----

        $pdo->beginTransaction();

        // 1) Cambiar estado de la OC a ANULADO
        $pdo->prepare("
            UPDATE orden_de_compra
            SET orden_estado = 'ANULADO'
            WHERE id_orden_compra = :id
        ")->execute([':id' => $idOc]);

        // 2) Bitácora
        bitacora($pdo, $idUsuario, 'INACTIVACION', "Anula Orden de Compra #{$idOc}", $idOc);

        // 3) Revertir presupuesto a EMITIDO (según especificación)
        if ($idPresu > 0) {
            $pdo->prepare("
                UPDATE presupuesto_compra
                SET presu_estado = 'EMITIDO'
                WHERE id_presupuesto_compra = :id
            ")->execute([':id' => $idPresu]);

            bitacora($pdo, $idUsuario, 'MODIFICACION',
                     "Revierte presupuesto {$idPresu} a EMITIDO (por anulación de OC #{$idOc})", $idPresu);
        }

        $pdo->commit();
        header("Location: view.php?alert=3"); // OK
        exit;

    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
        header("Location: view.php?alert=4&msg=" . urlencode("Error al anular: ".$e->getMessage()));
        exit;
    }
}





    // Si llegó aquí sin coincidir acción:
    http_response_code(400);
    echo 'Acción no soportada.';

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    // Para depurar mejor en pantalla (puedes reemplazar por redirección con ?alert=error)
    die("Error: ".$e->getMessage());
}
