<?php
session_start();

require "../../config/database.php"; // Conexión a la base de datos

// Verificar si el usuario está autenticado
if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}

function bitacora(PDO $pdo, int $idUsuario, string $accion, string $descripcion, ?int $idRegistro = null): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, :id_registro, :accion, :descripcion)
        ");
        $stmt->execute([
            ':id_usuario'  => $idUsuario,
            ':entidad'     => 'Presupuesto compra',
            ':id_registro' => $idRegistro,
            ':accion'      => strtoupper($accion),   // debe pasar el CHECK
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
        // Conectar a la base de datos
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Insertar un nuevo presupuesto con sus detalles
        // --------------------- NUEVO PRESUPUESTO ---------------------
        if ($action == 'insert' && isset($_POST['Guardar'])) {
            
            $presupuesto_id = $_POST['codigo'];                 // número mostrado en la UI
            $pedido_id      = $_POST['pedido'];
            $cod_proveedor  = $_POST['proveedor'];
            $detalle        = json_decode($_POST['productos'], true);
            // El total_importe ahora es el total_general (con descuento_total aplicado)
            $total_importe  = isset($_POST['total_importe']) ? (float)$_POST['total_importe'] : 0;
            $usuario_id     = $_SESSION['id_usuario'];
    
            // Validar detalle del presupuesto
            if (empty($detalle)) {
                throw new Exception("El detalle del presupuesto está vacío o malformado.");
            }

            // Validar duplicidad: mismo pedido/proveedor ya tiene presupuesto pendiente/finalizado
            $stmtDup = $pdo->prepare("
                SELECT 1
                FROM presupuesto_compra
                WHERE id_pedido_compra = :pedido
                  AND id_proveedor = :prov
                  AND presu_estado <> 'ANULADO'
                LIMIT 1
            ");
            $stmtDup->execute([
                ':pedido' => $pedido_id,
                ':prov'   => $cod_proveedor
            ]);
            if ($stmtDup->fetchColumn()) {
                header("Location: view.php?alert=7");
                exit;
            }
    
            // Obtener sucursal del usuario
            $query = $pdo->prepare("SELECT id_sucursal FROM usuarios WHERE id_usuario = :id_usuario LIMIT 1");
            $query->bindParam(':id_usuario', $usuario_id, PDO::PARAM_INT);
            $query->execute();
            $id_sucursal = $query->fetchColumn();

            $pdo->exec("SET TIME ZONE 'America/Asuncion'");

    
            if (!$id_sucursal) {
                throw new Exception("El usuario no tiene una sucursal asignada.");
            }
    
            // Iniciar transacción
            $pdo->beginTransaction();

    
            // Obtener descuento_total y observaciones del POST
            $descuento_total_raw = isset($_POST['descuento_total']) ? trim($_POST['descuento_total']) : '0';
            // Asegurar que sea numérico y válido
            if (empty($descuento_total_raw) || !is_numeric($descuento_total_raw)) {
                $descuento_total = 0;
            } else {
                $descuento_total = (float)$descuento_total_raw;
                if ($descuento_total < 0) {
                    $descuento_total = 0;
                }
            }
            $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : null;
            
            // Debug temporal - escribir en archivo de log
            $log_file = __DIR__ . '/debug_presupuesto.log';
            $log_content = date('Y-m-d H:i:s') . " === PRESUPUESTO INSERT ===\n";
            $log_content .= "descuento_total recibido (raw): " . var_export($_POST['descuento_total'] ?? 'NO EXISTE', true) . "\n";
            $log_content .= "descuento_total recibido (tipo): " . gettype($_POST['descuento_total'] ?? null) . "\n";
            $log_content .= "descuento_total procesado: " . $descuento_total . "\n";
            $log_content .= "total_importe: " . $total_importe . "\n";
            $log_content .= "detalle JSON: " . ($_POST['productos'] ?? 'NO EXISTE') . "\n";
            $log_content .= "POST completo: " . print_r($_POST, true) . "\n\n";
            file_put_contents($log_file, $log_content, FILE_APPEND);
            
            // CABECERA: insertar y obtener ID real
            $sqlCab = "
                INSERT INTO presupuesto_compra
                    (presu_total, presu_fecha, presu_estado, descuento_total, presu_observaciones, presu_ultima_modificacion,
                     id_pedido_compra, id_usuario, id_sucursal, id_proveedor)
                VALUES
                    (:total_importe, CURRENT_TIMESTAMP(0), 'EMITIDO', :descuento_total, :observaciones, CURRENT_TIMESTAMP,
                     :pedido_id, :id_usuario, :id_sucursal, :cod_proveedor)
                RETURNING id_presupuesto_compra
            ";
            $stCab = $pdo->prepare($sqlCab);
            $stCab->execute([
                ':total_importe' => $total_importe,   // total con IVA (no se vuelve a sumar)
                ':descuento_total' => $descuento_total,
                ':observaciones' => $observaciones,
                ':pedido_id'     => $pedido_id,
                ':id_usuario'    => $usuario_id,
                ':id_sucursal'   => $id_sucursal,
                ':cod_proveedor' => $cod_proveedor
            ]);

            $presupuesto_id_db = (int)$stCab->fetchColumn();
            if (!$presupuesto_id_db) {
                throw new Exception("No se pudo obtener el ID del presupuesto.");
            }

            // BITÁCORA: cabecera (ALTA)
            bitacora(
                $pdo,
                (int)$usuario_id,
                'ALTA',
                "Presupuesto Compra: se crea cabecera #{$presupuesto_id_db} (UI Presupuesto: #{$presupuesto_id}) por pedido {$pedido_id}. Total: {$total_importe}.",
                $presupuesto_id_db
            );
    
            // DETALLES
            $query_detalle = $pdo->prepare("
                INSERT INTO presupuesto_detalle_compra (id_presupuesto_compra, id_materia_prima, detalle_presu_cantidad, detalle_presu_precio_compra, descuento, detalle_presu_iva) 
                VALUES (:presupuesto_id, :cod_materia_prima, :cantidad, :precio, :descuento, :iva)
            ");
    
            foreach ($detalle as $item) {
                if (!isset($item['codigo'], $item['cantidad'], $item['precio'])) {
                    throw new Exception("Falta información en un elemento del detalle: " . json_encode($item));
                }

                // FUNCIÓN PARA CALCULAR IVA DESDE EL BACKEND (respaldo si el frontend no lo envía)
                // El IVA se calcula sobre el precio con descuento (base imponible)
                $calcularIvaBackend = function($codigo_materia, $precio, $cantidad, $descuento = 0) use ($pdo) {
                    try {
                        $query_iva = $pdo->prepare("
                            SELECT COALESCE(ti.iva_descri, '') AS iva_descri 
                            FROM materia_prima mp 
                            LEFT JOIN tipo_iva ti ON mp.iva_id = ti.iva_id 
                            WHERE mp.id_materia_prima = :cod
                        ");
                        $query_iva->bindParam(':cod', $codigo_materia, PDO::PARAM_INT);
                        $query_iva->execute();
                        $iva_data = $query_iva->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$iva_data || empty($iva_data['iva_descri'])) {
                            return 0;
                        }
                        
                        $iva_descri = strtolower(trim($iva_data['iva_descri']));
                        $iva_descri_normalized = preg_replace('/[\s%_\-]/', '', $iva_descri);
                        
                        // Calcular precio con descuento (base imponible)
                        // El descuento por ítem se aplica al precio unitario
                        $precio_con_descuento = $precio - ($descuento / max(1, $cantidad));
                        $precio_base_imponible = $precio_con_descuento > 0 ? $precio_con_descuento : $precio;
                        
                        $iva_unit = 0;
                        // Detectar IVA 10%
                        if (strpos($iva_descri_normalized, '10') !== false || 
                            $iva_descri === 'iva_10' || 
                            strpos($iva_descri, '10%') !== false ||
                            $iva_descri === '10') {
                            $iva_unit = floor($precio_base_imponible / 11);
                        } 
                        // Detectar IVA 5%
                        elseif (strpos($iva_descri_normalized, '5') !== false || 
                                $iva_descri === 'iva_5' || 
                                strpos($iva_descri, '5%') !== false ||
                                $iva_descri === '5') {
                            $iva_unit = floor($precio_base_imponible / 21);
                        }
                        
                        return floor($cantidad * $iva_unit);
                    } catch (Exception $e) {
                        return 0;
                    }
                };
                
                // Intentar usar el IVA del frontend, pero si es 0, calcularlo en el backend
                $iva_calculado = isset($item['iva']) ? (float)$item['iva'] : 0;
                $descuento_item = isset($item['descuento']) ? (float)$item['descuento'] : 0;
                
                // Si el IVA viene como 0 pero debería tener valor, calcularlo desde el backend
                // Pasar también el descuento para calcular sobre la base imponible correcta
                if ($iva_calculado == 0 && $precio > 0 && $cantidad > 0) {
                    $iva_calculado = $calcularIvaBackend($item['codigo'], $precio, $cantidad, $descuento_item);
                }
                
                // Debug temporal - escribir en archivo de log
                $log_file = __DIR__ . '/debug_presupuesto.log';
                $log_content = "  Item - codigo: {$item['codigo']}, iva recibido: " . var_export($item['iva'] ?? 'NO EXISTE', true) . ", iva procesado: {$iva_calculado}\n";
                $log_content .= "  Item - descuento recibido: " . var_export($item['descuento'] ?? 'NO EXISTE', true) . ", descuento procesado: {$descuento_item}\n";
                $log_content .= "  Item completo: " . print_r($item, true) . "\n";
                file_put_contents($log_file, $log_content, FILE_APPEND);

                // Usar el ID real de cabecera para FK
                $query_detalle->bindValue(':presupuesto_id', $presupuesto_id_db, PDO::PARAM_INT);
                $query_detalle->bindValue(':cod_materia_prima', (int)$item['codigo'], PDO::PARAM_INT);
                $query_detalle->bindValue(':cantidad', (int)$item['cantidad'], PDO::PARAM_INT);
                $query_detalle->bindValue(':precio', (string)$item['precio'], PDO::PARAM_STR);
                $query_detalle->bindValue(':descuento', $descuento_item);
                $query_detalle->bindValue(':iva', $iva_calculado);
                $query_detalle->execute();

                // BITÁCORA: detalle (ALTA)
                bitacora(
                    $pdo,
                    (int)$usuario_id,
                    'ALTA',
                    "Detalle Presupuesto Compra: presupuesto #{$presupuesto_id_db}, materia prima: {$item['codigo']}, cantidad: {$item['cantidad']}, precio {$item['precio']}.",
                    $presupuesto_id_db
                );
            }
    
            // ACTUALIZAR ESTADO DEL PEDIDO  
            $query_update_pedido = $pdo->prepare("
                UPDATE pedidos_compra
                   SET pedido_estado = 'APROBADO'
                 WHERE id_pedido_compra = :pedido_id
            ");
            $query_update_pedido->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
            $query_update_pedido->execute();

            // BITÁCORA: update de pedido (MODIFICACION)
            bitacora(
                $pdo,
                (int)$usuario_id,
                'MODIFICACION',
                "Update estado de Pedido Compra: pedido {$pedido_id} → APROBADO por presupuesto #{$presupuesto_id_db}.",
                $pedido_id
            );
    
            // Confirmar transacción
            $pdo->commit();
    
            // Redirigir con un mensaje de éxito
            header("Location: view.php?alert=1");
            exit();
        } 

        // ACTUALIZAR PEDIDO SI SU ESTADO ESTA EN PENDIENTE
        else if ($action === 'update' && isset($_GET['pre_id']) && isset($_POST['Guardar'])) {
            $preId = (int) $_GET['pre_id'];
            if ($preId <= 0) {
                throw new Exception("ID de presupuesto inválido.");
            }

            // Usuario actual
            $pdo->exec("SET TIME ZONE 'America/Asuncion'");
            $userName = $_SESSION['username'] ?? null;
            $userId   = $_SESSION['id_usuario'] ?? null;
            if (!$userId) {
                $qU = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
                $qU->execute([':u' => $userName]);
                $userId = (int)$qU->fetchColumn();
                if (!$userId) throw new Exception("No se pudo determinar el usuario.");
            }
            $stamp = date('Y-m-d H:i:s');

            // Payload
            $pedido_id     = isset($_POST['pedido']) ? (int)$_POST['pedido'] : null;      
            $cod_proveedor = isset($_POST['proveedor']) ? (int)$_POST['proveedor'] : null;
            // El total_importe ahora es el total_general (con descuento_total aplicado)
            $total_importe_raw = isset($_POST['total_importe']) ? $_POST['total_importe'] : '0';
            $total_importe = is_numeric($total_importe_raw) ? (float)$total_importe_raw : 0;
            $detalle       = json_decode($_POST['productos'] ?? '[]', true);
            
            // Debug: verificar payload recibido - escribir en archivo de log
            $log_file = __DIR__ . '/debug_presupuesto.log';
            $log_content = date('Y-m-d H:i:s') . " === PRESUPUESTO UPDATE ===\n";
            $log_content .= "total_importe recibido: " . var_export($_POST['total_importe'] ?? 'NO EXISTE', true) . "\n";
            $log_content .= "total_importe procesado: " . $total_importe . "\n";
            $log_content .= "descuento_total recibido: " . var_export($_POST['descuento_total'] ?? 'NO EXISTE', true) . "\n";
            $log_content .= "productos JSON: " . ($_POST['productos'] ?? 'NO EXISTE') . "\n";
            $log_content .= "POST completo: " . print_r($_POST, true) . "\n\n";
            file_put_contents($log_file, $log_content, FILE_APPEND);

            if (!is_array($detalle)) $detalle = [];
            // Mapa payload: id_producto => [cant, precio, descuento, iva]
            $payload = [];
            foreach ($detalle as $item) {
                if (!isset($item['codigo'], $item['cantidad'], $item['precio'])) continue;
                $pid = (int)$item['codigo'];
                $payload[$pid] = [
                    'cantidad' => (int)$item['cantidad'],
                    'precio'   => (int)$item['precio'],
                    'descuento' => isset($item['descuento']) ? (float)$item['descuento'] : 0,
                    'iva' => isset($item['iva']) ? (float)$item['iva'] : 0,
                ];
            }
            
            // Debug: verificar payload construido
            $log_file = __DIR__ . '/debug_presupuesto.log';
            $log_content = "Payload construido: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";
            file_put_contents($log_file, $log_content, FILE_APPEND);

            // 1) Validar cabecera, estado y vínculos
            $stCab = $pdo->prepare("
                SELECT id_presupuesto_compra, presu_estado, id_pedido_compra, id_proveedor, presu_total, presu_ultima_modificacion
                FROM presupuesto_compra
                WHERE id_presupuesto_compra = :id
                FOR UPDATE
            ");
            $stCab->execute([':id' => $preId]);
            $cab = $stCab->fetch(PDO::FETCH_ASSOC);
            if (!$cab) throw new Exception("No existe el presupuesto #{$preId}.");
            
            $estadoUpper = strtoupper(trim($cab['presu_estado']));
            if ($estadoUpper !== 'EMITIDO') {
                throw new Exception("El presupuesto #{$preId} no está en estado EMITIDO (estado actual: {$estadoUpper}).");
            }
            
            // Verificar vínculos con orden_de_compra
            $stVinculo = $pdo->prepare("
                SELECT id_orden_compra
                FROM orden_de_compra
                WHERE id_presupuesto_compra = :id
                LIMIT 1
            ");
            $stVinculo->execute([':id' => $preId]);
            if ($stVinculo->fetchColumn()) {
                throw new Exception("El presupuesto #{$preId} está vinculado a una Orden de Compra y no puede editarse.");
            }
            
            // Validar concurrencia
            $ultima_modificacion_form = isset($_POST['presu_ultima_modificacion']) ? $_POST['presu_ultima_modificacion'] : null;
            $ultima_modificacion_bd = $cab['presu_ultima_modificacion'];
            if ($ultima_modificacion_form && $ultima_modificacion_bd) {
                if ($ultima_modificacion_form !== $ultima_modificacion_bd) {
                    throw new Exception("El presupuesto fue modificado por otro usuario. Por favor, recargue la página y vuelva a intentar.");
                }
            }

            // 2) Traer detalles actuales (para comparar y bitácora)
            $stDetAll = $pdo->prepare("
                SELECT id_materia_prima, detalle_presu_cantidad AS cant, detalle_presu_precio_compra AS precio, 
                       COALESCE(descuento, 0) AS descuento, COALESCE(detalle_presu_iva, 0) AS iva
                FROM presupuesto_detalle_compra
                WHERE id_presupuesto_compra = :id
            ");
            $stDetAll->execute([':id' => $preId]);
            $actuales = [];
            while ($r = $stDetAll->fetch(PDO::FETCH_ASSOC)) {
                $actuales[(int)$r['id_materia_prima']] = [
                    'cant'   => (int)$r['cant'],
                    'precio' => (int)$r['precio'],
                    'descuento' => (float)$r['descuento'],
                    'iva' => (float)$r['iva'],
                ];
            }

            $pdo->beginTransaction();
            
            // FUNCIÓN PARA CALCULAR IVA EN UPDATE (definida una sola vez fuera del foreach)
            // El IVA se calcula sobre el precio con descuento (base imponible)
            $calcularIvaBackendUpdate = function($codigo_materia, $precio, $cantidad, $descuento = 0) use ($pdo) {
                try {
                    $query_iva = $pdo->prepare("
                        SELECT COALESCE(ti.iva_descri, '') AS iva_descri 
                        FROM materia_prima mp 
                        LEFT JOIN tipo_iva ti ON mp.iva_id = ti.iva_id 
                        WHERE mp.id_materia_prima = :cod
                    ");
                    $query_iva->bindParam(':cod', $codigo_materia, PDO::PARAM_INT);
                    $query_iva->execute();
                    $iva_data = $query_iva->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$iva_data || empty($iva_data['iva_descri'])) {
                        return 0;
                    }
                    
                    $iva_descri = strtolower(trim($iva_data['iva_descri']));
                    $iva_descri_normalized = preg_replace('/[\s%_\-]/', '', $iva_descri);
                    
                    // Calcular precio con descuento (base imponible)
                    $precio_con_descuento = $precio - ($descuento / max(1, $cantidad));
                    $precio_base_imponible = $precio_con_descuento > 0 ? $precio_con_descuento : $precio;
                    
                    $iva_unit = 0;
                    if (strpos($iva_descri_normalized, '10') !== false || 
                        $iva_descri === 'iva_10' || 
                        strpos($iva_descri, '10%') !== false ||
                        $iva_descri === '10') {
                        $iva_unit = floor($precio_base_imponible / 11);
                    } elseif (strpos($iva_descri_normalized, '5') !== false || 
                              $iva_descri === 'iva_5' || 
                              strpos($iva_descri, '5%') !== false ||
                              $iva_descri === '5') {
                        $iva_unit = floor($precio_base_imponible / 21);
                    }
                    
                    return floor($cantidad * $iva_unit);
                } catch (Exception $e) {
                    return 0;
                }
            };

            // 3) MODIFICAR SOLO LÍNEAS EXISTENTES; si no existe en BD, no se inserta (regla del negocio)
            $stUpd = $pdo->prepare("
                UPDATE presupuesto_detalle_compra
                SET detalle_presu_cantidad = :cant,
                    detalle_presu_precio_compra = :precio,
                    descuento = :descuento,
                    detalle_presu_iva = :iva
                WHERE id_presupuesto_compra = :pre
                AND id_materia_prima = :prod
            ");

            foreach ($payload as $prodId => $vals) {
                
                $old = $actuales[$prodId];
                $newCant = (int)$vals['cantidad'];
                $newPrecio = (int)$vals['precio'];
                $newDescuento = isset($vals['descuento']) ? (float)$vals['descuento'] : 0;
                $newIva = isset($vals['iva']) ? (float)$vals['iva'] : 0;
                
                // Si el IVA viene como 0 pero debería tener valor, calcularlo desde el backend
                // Pasar también el descuento para calcular sobre la base imponible correcta
                if ($newIva == 0 && $newPrecio > 0 && $newCant > 0) {
                    $newIva = $calcularIvaBackendUpdate($prodId, $newPrecio, $newCant, $newDescuento);
                }

                // Solo actualizar si hubo cambio
                if ($old['cant'] !== $newCant || $old['precio'] !== $newPrecio || 
                    $old['descuento'] != $newDescuento || $old['iva'] != $newIva) {
                    $stUpd->execute([
                        ':cant'  => $newCant,
                        ':precio'=> $newPrecio,
                        ':descuento' => $newDescuento,
                        ':iva' => $newIva,
                        ':pre'   => $preId,
                        ':prod'  => $prodId,
                    ]);
                    
                    // Debug temporal
                    $log_file = __DIR__ . '/debug_presupuesto.log';
                    $log_content = "  UPDATE Item - prodId: {$prodId}, iva: {$newIva}, descuento: {$newDescuento}\n";
                    $log_content .= "  vals completo: " . print_r($vals, true) . "\n";
                    file_put_contents($log_file, $log_content, FILE_APPEND);
                    // Bitácora de modificación de línea
                    $desc = "[$stamp] Modifica DETALLE presu #{$preId}, prod {$prodId}: ".
                            "cant {$old['cant']} → {$newCant}, precio {$old['precio']} → {$newPrecio}, ".
                            "descuento {$old['descuento']} → {$newDescuento}, iva {$old['iva']} → {$newIva}";
                    bitacora($pdo, $userId, 'MODIFICACION', $desc, $preId);
                }
            }

            // 4) BORRAR líneas que fueron “Quitadas” en la UI (no vienen en el payload)
            $idsPayload   = array_map('intval', array_keys($payload));
            $idsActuales  = array_map('intval', array_keys($actuales));
            $paraBorrar   = array_diff($idsActuales, $idsPayload); // existentes que no están en el payload

            if (!empty($paraBorrar)) {
                $stDel = $pdo->prepare("
                    DELETE FROM presupuesto_detalle_compra
                    WHERE id_presupuesto_compra = :pre
                    AND id_materia_prima = :prod
                ");
                foreach ($paraBorrar as $prodId) {
                    $stDel->execute([':pre' => $preId, ':prod' => $prodId]);
                    $desc = "[$stamp] Eliminacion DETALLE presu #{$preId}, prod {$prodId}.";
                    bitacora($pdo, $userId, 'ELIMINACION', $desc, $preId);
                }
            }

            // 5) Actualizar CABECERA (total, descuento_total, observaciones y opcionalmente proveedor/pedido)
            $descuento_total_raw = isset($_POST['descuento_total']) ? trim($_POST['descuento_total']) : '0';
            // Asegurar que sea numérico y válido
            if (empty($descuento_total_raw) || !is_numeric($descuento_total_raw)) {
                $descuento_total = 0;
            } else {
                $descuento_total = (float)$descuento_total_raw;
                if ($descuento_total < 0) {
                    $descuento_total = 0;
                }
            }
            
            // Debug para UPDATE
            $log_file = __DIR__ . '/debug_presupuesto.log';
            $log_content = date('Y-m-d H:i:s') . " === PRESUPUESTO UPDATE ===\n";
            $log_content .= "descuento_total recibido: " . var_export($_POST['descuento_total'] ?? 'NO EXISTE', true) . "\n";
            $log_content .= "descuento_total procesado: " . $descuento_total . "\n";
            file_put_contents($log_file, $log_content, FILE_APPEND);
            $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : null;
            
            $set = [
                "presu_total = :total",
                "descuento_total = :descuento_total",
                "presu_observaciones = :observaciones",
                "presu_ultima_modificacion = CURRENT_TIMESTAMP"
            ];
            $params = [
                ':total' => $total_importe, 
                ':descuento_total' => $descuento_total,
                ':observaciones' => $observaciones,
                ':id' => $preId
            ];

            if ($pedido_id !== null) {
                $set[] = "id_pedido_compra = :pedido";
                $params[':pedido'] = $pedido_id;
            }
            if ($cod_proveedor !== null) {
                $set[] = "id_proveedor = :prov";
                $params[':prov'] = $cod_proveedor;
            }

            $sqlUpdCab = "UPDATE presupuesto_compra SET ".implode(', ', $set)." WHERE id_presupuesto_compra = :id";
            $pdo->prepare($sqlUpdCab)->execute($params);

            // Bitácora de cabecera (resumen)
            $descCab = "[$stamp] MODIFICA CABECERA presu #{$preId}";
            $descCab .= " | total={$cab['presu_total']}→{$total_importe}";
            if ($pedido_id !== null && (int)$cab['id_pedido_compra'] !== $pedido_id) {
                $descCab .= " | pedido={$cab['id_pedido_compra']}→{$pedido_id}";
            }
            if ($cod_proveedor !== null && (int)$cab['id_proveedor'] !== $cod_proveedor) {
                $descCab .= " | proveedor={$cab['id_proveedor']}→{$cod_proveedor}";
            }
            bitacora($pdo, $userId, 'MODIFICACION', $descCab, $preId);

            $pdo->commit();

            header("Location: view.php?alert=2");
            exit();
        }

        // --------------------- ANULAR PRESUPUESTO ---------------------
        elseif ($action === 'anular' && isset($_GET['pre_id'])) {
            $preId = (int) $_GET['pre_id'];

            // Obtener id de usuario (de sesión o por username)
            if (!empty($_SESSION['id_usuario'])) {
                $userId = (int) $_SESSION['id_usuario'];
            } else {
                $qUid = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE username = :u LIMIT 1");
                $qUid->execute([':u' => $_SESSION['username']]);
                $userId = (int) $qUid->fetchColumn();
            }
            if ($userId <= 0) {
                header("Location: view.php?alert=4"); // usuario no resuelto
                exit;
            }

            try {
                $pdo->beginTransaction();
                $pdo->exec("SET TIME ZONE 'America/Asuncion'");

                // 1) Leer cabecera y bloquear
                $st = $pdo->prepare("
                    SELECT presu_estado, id_pedido_compra
                    FROM presupuesto_compra
                    WHERE id_presupuesto_compra = :id
                    FOR UPDATE
                ");
                $st->execute([':id' => $preId]);
                $row = $st->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    $pdo->rollBack();
                    header("Location: view.php?alert=4"); // no existe
                    exit;
                }

                $estado = strtoupper(trim((string)$row['presu_estado']));

                // 2) Validar estado según especificación: Solo permitir anular si está en EMITIDO
                // (punto 15: "El Encargado selecciona un presupuesto en Emitido y pulsa Editar")
                // (punto 12: "deja el presupuesto en Emitido")
                if ($estado !== 'EMITIDO') {
                    $pdo->rollBack();
                    header("Location: view.php?alert=5&msg=" . urlencode(
                        "Solo se pueden anular presupuestos en estado EMITIDO. Estado actual: {$estado}."
                    ));
                    exit;
                }

                // 3) Verificar vínculos según especificación punto 22.3: 
                // "el sistema verifica que el presupuesto no esté vinculado a Orden de Compra"
                $stOrden = $pdo->prepare("
                    SELECT id_orden_compra, orden_estado 
                    FROM orden_de_compra 
                    WHERE id_presupuesto_compra = :id 
                    LIMIT 1
                ");
                $stOrden->execute([':id' => $preId]);
                $orden = $stOrden->fetch(PDO::FETCH_ASSOC);
                
                if ($orden) {
                    $pdo->rollBack();
                    // Punto 22.5: "Si no cumple, el sistema rechaza la anulación e informa el motivo"
                    header("Location: view.php?alert=8&msg=" . urlencode(
                        "El presupuesto #{$preId} no puede anularse porque está vinculado a la Orden de Compra #{$orden['id_orden_compra']} (Estado: {$orden['orden_estado']})."
                    ));
                    exit;
                }

                // 4) Anular presupuesto y revertir pedido a PENDIENTE (punto 22.4)
                $upd = $pdo->prepare("
                    UPDATE presupuesto_compra
                    SET presu_estado = 'ANULADO'
                    WHERE id_presupuesto_compra = :id
                ");
                $upd->execute([':id' => $preId]);

                // Revertir el estado del pedido asociado (si existe)
                $pedidoVinc = isset($row['id_pedido_compra']) ? (int)$row['id_pedido_compra'] : 0;
                if ($pedidoVinc > 0) {
                    $updPed = $pdo->prepare("UPDATE pedidos_compra
                                               SET pedido_estado = 'PENDIENTE'
                                             WHERE id_pedido_compra = :id");
                    $updPed->execute([':id' => $pedidoVinc]);
                }

                // 4) Bitácora
                date_default_timezone_set('America/Asuncion');
                $stamp = date('Y-m-d H:i:s');
                bitacora(
                    $pdo,
                    $userId,
                    'INACTIVACION',
                    "[$stamp] Se ANULA el Presupuesto de Compra #{$preId}.".
                    ($pedidoVinc>0 ? " Pedido #{$pedidoVinc} vuelve a PENDIENTE." : ''),
                    $preId
                );

                

                $pdo->commit();
                header("Location: view.php?alert=3"); // éxito
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                die("Error al anular: ".$e->getMessage());
            }
        }

        

    } catch (Exception $e) {
        // Si ocurre un error, deshacer la transacción
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error en la operación con la base de datos: " . $e->getMessage());
    }
}
?>
