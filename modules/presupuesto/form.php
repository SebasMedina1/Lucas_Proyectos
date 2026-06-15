<?php 
if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}


// Verificar si existe el parámetro 'form' en la URL
if (isset($_GET['form_presupuesto']) && $_GET['form'] == 'add') { ?>
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">
        <i class="fas fa-plus-circle"></i> Registrar Presupuesto
    </h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="view.php">Presupuesto</a></li>
        <li class="breadcrumb-item active">Nuevo Presupuesto</li>
    </ol>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form id="form-presupuesto" action="proses.php?act=insert" method="POST">
                <?php
                try {
                    require "../../config/database.php";
                    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                    $pdo = new PDO($dsn, $user, $pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $query = $pdo->query("SELECT MAX(id_presupuesto_compra) AS id FROM presupuesto_compra");
                    $data = $query->fetch(PDO::FETCH_ASSOC);

                    $codigo = ($data['id'] !== null) ? $data['id'] + 1 : 1;
                    date_default_timezone_set('America/Asuncion'); 
                    $fecha = date("Y-m-d"); // Formato: YYYY-MM-DD (año:mes:dia)
                    $hora = date("H:i A"); // Formato: hh:mm:ss AM/PM (hora:minutos)

                    $userSesion = $_SESSION['username']; // ajusta si guardás email u otro dato

                    $sqlUser = "
                        SELECT 
                            u.id_usuario,
                            u.username,
                            u.id_sucursal,
                            s.descripcion_sucursal
                        FROM usuarios u
                        JOIN sucursales s ON s.id_sucursal = u.id_sucursal
                        WHERE u.username = :user
                        LIMIT 1;
                        ";
                        $q = $pdo->prepare($sqlUser);
                        $q->execute([':user' => $userSesion]);
                        $usr = $q->fetch(PDO::FETCH_ASSOC);

                        if (!$usr) {
                            throw new Exception('No se encontró el usuario logueado.');
                        }

                        $usuarioId      = (int)$usr['id_usuario'];
                        $usuarioNombre  = $usr['username'];
                        $sucursalId     = (int)$usr['id_sucursal'];
                        $sucursalNombre = $usr['descripcion_sucursal'];                    
                } catch (PDOException $e) {
                    die("Error: " . $e->getMessage());
                }
                ?>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="codigo" class="form-label">Presupuesto N°</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo $codigo; ?>" readonly>
                    </div>

                    <div class="col-md-3">
                        <label for="fecha" class="form-label">Fecha</label>
                        <input type="text" class="form-control" id="fecha" name="fecha" value="<?php echo $fecha; ?>" readonly>
                    </div>

                    <div class="col-md-3">
                        <label for="hora" class="form-label">Hora</label>
                        <input type="hora" class="form-control" id="hora" name="hora" value="<?php echo $hora; ?>" readonly>
                    </div>

                    <div class="col-md-3">
                        <label for="usuario" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="usuario" name="username" value="<?php echo $usuarioNombre; ?>" readonly>
                    </div>

                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="pedido" class="form-label">Pedidos de compras</label>
                        <select class="form-control" id="pedido" name="pedido" required>
                            <option value="" selected>Seleccione un Pedido</option>
                            <?php
                            $query_pedido = $pdo->query("SELECT id_pedido_compra FROM pedidos_compra WHERE pedido_estado IN ('PENDIENTE', 'APROBADO') ORDER BY id_pedido_compra ASC");
                            while ($pedido = $query_pedido->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value=\"{$pedido['id_pedido_compra']}\">Pedido N° {$pedido['id_pedido_compra']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    
                    <div class="col-md-4">
                        <label for="proveedor" class="form-label">Proveedor</label>
                        <select class="form-control" id="proveedor" name="proveedor" required>
                            <option value="" selected>Seleccione un Proveedor</option>
                            <?php
                            $query_proveedor = $pdo->query("SELECT id_proveedor, razon_social FROM proveedor ORDER BY id_proveedor ASC");
                            while ($proveedor = $query_proveedor->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value=\"{$proveedor['id_proveedor']}\">{$proveedor['razon_social']}</option>";
                            }
                            ?>
                        </select>
                   </div>

                    <div class="col-md-3">
                        <label for="sucursal" class="form-label">Sucursal</label>
                        <input type="text" class="form-control" id="sucursal" name="sucursal" value="<?php echo $sucursalNombre; ?>" readonly>
                    </div>
                    
                </div>



                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-striped" id="tabla-productos">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio</th>
                                <th>Descuento</th>
                                <th>Tipo IVA</th>
                                <th>Monto IVA</th>
                                <th>Sub total</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>

                <input type="hidden" name="productos" id="productos">

                <!-- Totales -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="total_importe">Total Importe</label>
                        <input type="number" class="form-control" id="total_importe" name="total_importe" readonly>
                    </div>

                    <div class="col-md-3">
                        <label for="descuento_total">Descuento Total</label>
                        <input type="number" class="form-control" id="descuento_total" name="descuento_total" min="0" step="0.01" value="0" readonly>
                    </div>

                    <div class="col-md-3">
                        <label for="total_iva">Total IVA</label>
                        <input type="number" class="form-control" id="total_iva" name="total_iva" readonly>
                    </div>

                    <div class="col-md-3">
                        <label for="total_general">Total General</label>
                        <input type="number" class="form-control" id="total_general" name="total_general" readonly>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="observaciones">Observaciones (Opcional)</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="2" placeholder="Ingrese observaciones o notas adicionales"></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-success mx-2" name="Guardar">Guardar</button>
                    <button type="button" id="btn-cancelar" class="btn btn-warning mx-2">Cancelar</button>
                    <a href="view.php" class="btn btn-danger mx-2">Cerrar</a>
                </div>

            </form>
        </div>
    </div>
</div> 
<?php } ?>


<?php
/* ======================== MODO EDICIÓN ======================== */
if (
    isset($_GET['form_presupuesto'], $_GET['form'], $_GET['pre_id']) &&
    $_GET['form_presupuesto'] === 'edit' &&
    $_GET['form'] === 'edit'
):
    try {
        require "../../config/database.php";
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $preId = (int)($_GET['pre_id'] ?? 0);
        if ($preId <= 0) throw new Exception("ID de presupuesto inválido.");

        // Usuario/Sucursal
        $userSesion = $_SESSION['username'];
        $sqlUser = "
            SELECT u.id_usuario, u.username, u.id_sucursal, s.descripcion_sucursal
            FROM usuarios u
            JOIN sucursales s ON s.id_sucursal = u.id_sucursal
            WHERE u.username = :user
            LIMIT 1";
        $q = $pdo->prepare($sqlUser);
        $q->execute([':user' => $userSesion]);
        $usr = $q->fetch(PDO::FETCH_ASSOC);
        if (!$usr) throw new Exception('No se encontró el usuario logueado.');

        $usuarioNombre  = $usr['username'];
        $sucursalNombre = $usr['descripcion_sucursal'];

        // Cabecera (fecha/hora separadas)
        $sqlCab = "
            SELECT 
                pc.id_presupuesto_compra,
                to_char(pc.presu_fecha,'YYYY-MM-DD') AS fecha,
                to_char(pc.presu_fecha,'HH24:MI:SS') AS hora,
                pc.id_pedido_compra,
                pc.id_proveedor,
                pv.razon_social AS proveedor_nombre,
                pc.id_sucursal,
                COALESCE(pc.presu_total,0)   AS total_importe,
                COALESCE(pc.presu_estado,'PENDIENTE') AS estado
            FROM presupuesto_compra pc
            JOIN proveedor pv ON pv.id_proveedor = pc.id_proveedor
            WHERE pc.id_presupuesto_compra = :id
            LIMIT 1";
        $stCab = $pdo->prepare($sqlCab);
        $stCab->execute([':id' => $preId]);
        $cab = $stCab->fetch(PDO::FETCH_ASSOC);
        if (!$cab) throw new Exception("No existe el presupuesto #{$preId}.");

        $editable     = ($cab['estado'] === 'EMITIDO');
        $isEdit       = true; // Variable para identificar modo edición
        $totalImporte = (float)$cab['total_importe'];

        // Detalles
        $sqlDet = "
            SELECT 
                d.id_materia_prima,
                mp.materia_prima_descripcion,
                d.detalle_presu_cantidad      AS cantidad,
                d.detalle_presu_precio_compra AS precio_unitario,
                COALESCE(d.descuento, 0) AS descuento,
                COALESCE(d.detalle_presu_iva, 0) AS iva,
                ti.iva_descri,
                pc.descuento_total,
                pc.presu_observaciones,
                pc.presu_ultima_modificacion
            FROM presupuesto_detalle_compra d
            JOIN materia_prima mp ON mp.id_materia_prima = d.id_materia_prima
            LEFT JOIN tipo_iva  ti ON ti.iva_id    = mp.iva_id
            JOIN presupuesto_compra pc ON pc.id_presupuesto_compra = d.id_presupuesto_compra
            WHERE d.id_presupuesto_compra = :id
            ORDER BY d.id_materia_prima ASC";
        $stDet = $pdo->prepare($sqlDet);
        $stDet->execute([':id' => $preId]);
        $detalles = $stDet->fetchAll(PDO::FETCH_ASSOC);

        date_default_timezone_set('America/Asuncion'); // tu zona
        $now  = new DateTime('now', new DateTimeZone('America/Asuncion'));
        $hoy  = $now->format('Y-m-d');
        $hora = $now->format('H:i:s');


    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
    ?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">
        <i class="fas fa-edit"></i> Editar Presupuesto
        <small class="text-muted">#<?= (int)$preId ?> (<?= htmlspecialchars($cab['estado']) ?>)</small>
    </h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="view.php">Presupuesto</a></li>
        <li class="breadcrumb-item active">Editar Presupuesto</li>
    </ol>

    <div class="card shadow mb-4">
        <div class="card-body">
            <?php if (!$editable): ?>
                <div class="alert alert-info mb-3">
                    Este presupuesto está en estado <strong><?= htmlspecialchars($cab['estado']) ?></strong>, por lo que no es editable.
                </div>
            <?php endif; ?>

            <form id="form-presupuesto" action="proses.php?act=update&pre_id=<?= (int)$preId; ?>" method="POST">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Presupuesto N°</label>
                        <input type="text" class="form-control" value="<?= (int)$cab['id_presupuesto_compra']; ?>" readonly>
                        <input type="hidden" name="codigo" value="<?= (int)$cab['id_presupuesto_compra']; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha</label>
                        <input type="text" class="form-control" value="<?= $hoy; ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Hora</label>
                        <input type="text" class="form-control" value="<?= $hora; ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($cab['estado']); ?>" readonly>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Proveedor</label>
                        <input type="text" id="proveedor_nombre_edit" class="form-control" value="<?= htmlspecialchars($cab['proveedor_nombre']); ?>" readonly>
                        <input type="hidden" name="proveedor" id="proveedor_hidden_edit" value="<?= (int)$cab['id_proveedor']; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Usuario</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($usuarioNombre); ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sucursal</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($sucursalNombre); ?>" readonly>
                        <input type="hidden" name="pedido" value="<?= (int)$cab['id_pedido_compra']; ?>">
                    </div>
                </div>

                <div class="table-responsive mb-4">
                    <table class="table table-bordered" id="tabla-productos">
                        <thead>
                          <tr>
                            <th>#</th>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio</th>
                            <th>Descuento</th>
                            <th>Tipo IVA</th>
                            <th>Monto IVA</th>
                            <th>Sub total</th>
                            <th>Acción</th>
                          </tr>
                        </thead>
                        <tbody>
                        <?php 
                        $descuento_total_edit = isset($detalles[0]['descuento_total']) ? (float)$detalles[0]['descuento_total'] : 0;
                        $observaciones_edit = isset($detalles[0]['presu_observaciones']) ? $detalles[0]['presu_observaciones'] : '';
                        $ultima_modificacion_edit = isset($detalles[0]['presu_ultima_modificacion']) ? $detalles[0]['presu_ultima_modificacion'] : '';
                        
                        // Calcular totales iniciales
                        $totalIvaCalculado = 0;
                        $totalDescuentoCalculado = 0;
                        $totalImporteCalculado = 0;
                        foreach ($detalles as $d) {
                          $cant = (float)$d['cantidad'];
                          $precio = (float)$d['precio_unitario'];
                          // Asegurar que el descuento se lea correctamente
                          $descuento_raw = $d['descuento'] ?? 0;
                          if (is_string($descuento_raw)) {
                            $descuento_item = (float)str_replace(',', '.', trim($descuento_raw));
                          } else {
                            $descuento_item = (float)$descuento_raw;
                          }
                          $iva_item = (float)($d['iva'] ?? 0);
                          $subtotal = ($cant * $precio) - $descuento_item;
                          
                          $totalImporteCalculado += $subtotal;
                          $totalIvaCalculado += $iva_item;
                          $totalDescuentoCalculado += $descuento_item;
                        }
                        $totalGeneralCalculado = $totalImporteCalculado + $totalIvaCalculado;
                        
                        $i=1; 
                        foreach ($detalles as $d):
                          $idProd   = (int)$d['id_materia_prima'];
                          $descProd = $d['materia_prima_descripcion'];
                          $cant     = (float)$d['cantidad'];
                          $precio   = (float)$d['precio_unitario'];
                          // Asegurar que el descuento se lea correctamente
                          $descuento_raw = $d['descuento'] ?? 0;
                          // Si viene como string numérico de PostgreSQL, convertirlo correctamente
                          if (is_string($descuento_raw)) {
                            // Remover cualquier formato y convertir a float
                            $descuento_item = (float)str_replace(',', '.', trim($descuento_raw));
                          } else {
                            $descuento_item = (float)$descuento_raw;
                          }
                          $iva_item = (float)($d['iva'] ?? 0);
                          $ivaDesc  = $d['iva_descri']; // 'iva_10' | 'iva_5'
                          $subtotal = ($cant * $precio) - $descuento_item;
                        ?>
                        <tr data-id="<?= $idProd; ?>">
                          <td><?= $i++; ?></td>
                          <td><?= $idProd; ?></td>
                          <td><?= htmlspecialchars($descProd); ?></td>
                                <td><input type="number" class="form-control cantidad" min="1" value="<?= $cant; ?>" <?= $editable ? '' : 'readonly'; ?>></td>
                                <td>
                                  <input type="number" class="form-control precio" min="0" step="1" inputmode="numeric" pattern="\d*" 
                                        value="<?= number_format($precio,0,'',''); ?>" <?= $editable ? '' : 'readonly'; ?>>
                                </td>
                                <td>
                                  <input type="number" class="form-control descuento" min="0" step="0.01" 
                                        value="<?= sprintf('%.2f', $descuento_item); ?>" <?= $editable ? '' : 'readonly'; ?>>
                                </td>
                                <td class="iva"><?= htmlspecialchars($ivaDesc); ?></td>
                                <td class="monto_iva"><?= number_format($iva_item, 0, '', ''); ?></td>
                                <td class="subtotal"><?= number_format($subtotal,0,'',''); ?></td>
                                <td class="text-center">
                                  <?php if ($editable): ?>
                                    <button type="button" class="btn btn-danger btn-sm btn-quitar">Quitar</button>
                                  <?php else: ?>
                                    <span class="text-muted">—</span>
                                  <?php endif; ?>
                                </td>
                              </tr>
                              <?php endforeach; ?>
                              </tbody>
                    </table>
                </div>

                <input type="hidden" name="productos" id="productos">

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Total Importe</label>
                        <input type="number" class="form-control" id="total_importe" name="total_importe" value="<?= number_format($totalImporteCalculado,0,'','') ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="descuento_total">Descuento Total</label>
                        <input type="number" class="form-control" id="descuento_total" name="descuento_total" min="0" step="0.01" value="<?= number_format($totalDescuentoCalculado, 2, '.', ''); ?>" readonly>
                    </div>
                      <div class="col-md-3">
                        <label for="total_iva">Total IVA</label>
                        <input type="number" class="form-control" id="total_iva" name="total_iva"
                              value="<?= number_format($totalIvaCalculado, 0, '', ''); ?>" readonly>
                      </div>
                      <div class="col-md-3">
                        <label for="total_general">Total General</label>
                        <input type="number" class="form-control" id="total_general" name="total_general" value="<?= number_format($totalGeneralCalculado, 0, '', ''); ?>" readonly>
                      </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="observaciones">Observaciones (Opcional)</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="2" <?= $editable ? '' : 'readonly'; ?>><?= htmlspecialchars($observaciones_edit); ?></textarea>
                    </div>
                </div>

                <input type="hidden" name="presu_ultima_modificacion" value="<?= htmlspecialchars($ultima_modificacion_edit); ?>">

                <div class="d-flex justify-content-end">
                    <?php if ($editable): ?>
                        <button type="submit" class="btn btn-success mx-2" name="Guardar">Guardar cambios</button>
                    <?php endif; ?>
                    <a href="view.php" class="btn btn-secondary mx-2">Cerrar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php endif; /* fin edit */ ?>

<script>
// Recalcular totales al cargar la página en modo edición
document.addEventListener('DOMContentLoaded', function() {
  // Si estamos en modo edición, recalcular todas las filas y totales
  <?php if (isset($isEdit) && $isEdit && isset($editable) && $editable): ?>
    console.log('[EDICION] Inicializando modo edición...');
    
    // Esperar a que el DOM esté completamente listo
    setTimeout(function() {
      // Verificar que la tabla existe
      const tabla = document.querySelector('#tabla-productos tbody');
      if (!tabla) {
        console.error('[EDICION] No se encontró la tabla de productos');
        return;
      }
      
      // Recalcular cada fila para asegurar que los valores estén correctos
      const filas = tabla.querySelectorAll('tr');
      console.log('[EDICION] Recalculando', filas.length, 'filas');
      
      filas.forEach(function(tr, idx) {
        // Leer valores actuales y verificar
        const cantidadInput = tr.querySelector('.cantidad');
        const precioInput = tr.querySelector('.precio');
        const descuentoInput = tr.querySelector('.descuento');
        
        if (cantidadInput && precioInput && descuentoInput) {
          // Guardar valores originales antes de procesar
          const valorOriginalCantidad = cantidadInput.value;
          const valorOriginalPrecio = precioInput.value;
          const valorOriginalDescuento = descuentoInput.value;
          
          // Convertir a números
          const cant = toNumber(valorOriginalCantidad);
          const prec = toNumber(valorOriginalPrecio);
          const desc = toNumber(valorOriginalDescuento);
          
          // Si los valores convertidos son diferentes a los originales, puede haber un problema
          // Asegurarse de que los valores en los inputs sean correctos
          if (cant > 0 && cant.toString() !== valorOriginalCantidad.trim()) {
            cantidadInput.value = cant.toString();
          }
          if (prec > 0 && prec.toString() !== valorOriginalPrecio.trim()) {
            precioInput.value = prec.toString();
          }
          if (desc >= 0 && desc.toString() !== valorOriginalDescuento.trim()) {
            // Para descuento, mantener decimales si los tiene
            descuentoInput.value = desc % 1 === 0 ? desc.toString() : desc.toFixed(2);
          }
          
          console.log(`[EDICION] Fila ${idx + 1}:`, {
            cantidad: { original: valorOriginalCantidad, convertido: cant },
            precio: { original: valorOriginalPrecio, convertido: prec },
            descuento: { original: valorOriginalDescuento, convertido: desc }
          });
        }
        
        // Recalcular la fila con los valores corregidos
        recalcularFila(tr);
      });
      
      // Actualizar totales
      actualizarTotales();
      console.log('[EDICION] Totales recalculados al cargar');
      
      // Verificar que los event listeners estén funcionando
      const testInput = tabla.querySelector('input.cantidad, input.precio, input.descuento');
      if (testInput) {
        console.log('[EDICION] Inputs encontrados, event listeners deberían estar activos');
        if (testInput.readOnly) {
          console.warn('[EDICION] ADVERTENCIA: Algunos inputs están en modo readonly');
        } else {
          console.log('[EDICION] Inputs editables detectados correctamente');
        }
      }
    }, 500);
  <?php elseif (isset($isEdit) && $isEdit): ?>
    // Modo edición pero no editable - solo recalcular para mostrar valores correctos
    setTimeout(function() {
      const tabla = document.querySelector('#tabla-productos tbody');
      if (tabla) {
        const filas = tabla.querySelectorAll('tr');
        filas.forEach(function(tr) {
          recalcularFila(tr);
        });
        actualizarTotales();
      }
    }, 300);
  <?php endif; ?>
});

// ---------- Utilidades ----------
function toNumber(val){
  if(val==null || val==='') return 0;
  if(typeof val==='number') return val;
  
  // Convertir a string y limpiar
  let str = String(val).trim();
  if(str==='') return 0;
  
  // Si ya es un número sin formato (solo dígitos), parsearlo directamente
  if(/^\d+$/.test(str)) {
    return parseFloat(str) || 0;
  }
  
  // Si tiene formato (puntos, comas), limpiarlo correctamente
  // Primero verificar si tiene punto decimal o coma decimal
  const tienePuntoDecimal = /\.\d+$/.test(str); // Ej: "5000.00"
  const tieneComaDecimal = /,\d+$/.test(str);   // Ej: "5000,00"
  
  if (tienePuntoDecimal) {
    // Tiene punto decimal: remover puntos de miles pero mantener el punto decimal
    // Ejemplo: "1.500.00" -> "1500.00"
    const partes = str.split('.');
    const parteDecimal = partes.pop(); // Última parte es decimal
    const parteEntera = partes.join(''); // Unir el resto sin puntos
    str = parteEntera + '.' + parteDecimal;
  } else if (tieneComaDecimal) {
    // Tiene coma decimal: remover puntos de miles y convertir coma a punto
    const partes = str.split(',');
    const parteDecimal = partes.pop();
    const parteEntera = partes.join('').replace(/\./g, '');
    str = parteEntera + '.' + parteDecimal;
  } else {
    // No tiene decimales: solo remover puntos de miles
    str = str.replace(/\./g, '').replace(',', '');
  }
  
  const num = parseFloat(str);
  return isNaN(num) ? 0 : num;
}
function getPrecioFromCell(cell){
  if(!cell) return 0;
  const input = cell.matches('input.precio') ? cell : cell.querySelector('input.precio');
  if (input) return toNumber(input.value);
  const raw = cell.dataset?.precio ?? cell.textContent;
  return toNumber(raw);
}
function reindexRows(){
  document.querySelectorAll('#tabla-productos tbody tr').forEach((tr,idx)=>{
    const cell = tr.children[0];
    if(cell) cell.textContent = String(idx+1);
  });
}

// ---------- Recalcular fila ----------
function recalcularFila(row){
  if (!row) return { subtotal: 0, montoIva: 0, descuento: 0 };
  
  const cantidadInput = row.querySelector('.cantidad');
  const precioInput = row.querySelector('.precio');
  const descuentoInput = row.querySelector('.descuento');
  
  const cantidad = toNumber(cantidadInput?.value || 0);
  const precio   = toNumber(precioInput?.value || 0);
  const descuento = toNumber(descuentoInput?.value || 0);
  const ivaTxt   = (row.querySelector('.iva')?.textContent || '').trim().toLowerCase();
  
  // Debug: verificar valores leídos
  if (cantidad <= 0 || precio <= 0) {
    console.warn('[RECALCULAR] Valores inválidos:', { cantidad, precio, descuento, ivaTxt });
  }

  // Calcular precio con descuento (base imponible)
  // El descuento por ítem se aplica al precio unitario
  const precioConDescuento = precio - (descuento / cantidad); // Descuento total dividido por cantidad
  const precioBaseImponible = precioConDescuento > 0 ? precioConDescuento : precio;

  // Calcular IVA sobre la base imponible (precio con descuento)
  // En Paraguay, el IVA se calcula sobre el precio neto (con descuento aplicado)
  let ivaUnit = 0;
  if (precioBaseImponible > 0 && ivaTxt) {
    // Detectar IVA 10% en cualquier formato: "iva_10", "iva 10%", "iva10", "10", etc.
    const ivaTxtNormalized = ivaTxt.replace(/[\s%_]/g, '');
    if (ivaTxtNormalized.includes('10') || ivaTxt === 'iva_10' || ivaTxt.includes('10%')) {
      ivaUnit = Math.floor(precioBaseImponible / 11);
    } else if (ivaTxtNormalized.includes('5') || ivaTxt === 'iva_5' || ivaTxt.includes('5%')) {
      ivaUnit = Math.floor(precioBaseImponible / 21);
    }
  }

  // Subtotal = (cantidad * precio) - descuento total del ítem
  // Asegurarse de que el subtotal no sea negativo (el descuento no puede ser mayor que el total)
  const subtotalBruto = (cantidad * precio) - descuento;
  const subtotal = Math.max(0, Math.floor(subtotalBruto)); // No permitir negativos
  
  // IVA = cantidad * IVA unitario (calculado sobre precio con descuento)
  const montoIva = Math.floor(cantidad * ivaUnit);

  const subEl = row.querySelector('.subtotal');
  const ivaEl = row.querySelector('.monto_iva');
  if (subEl) {
    subEl.textContent = String(subtotal);
    // Si el subtotal bruto era negativo, mostrar advertencia
    if (subtotalBruto < 0) {
      console.warn('[RECALCULAR] Subtotal negativo corregido:', {
        cantidad, precio, descuento, subtotalBruto, subtotal
      });
    }
  }
  if (ivaEl) ivaEl.textContent = String(montoIva);
  
  return { subtotal, montoIva, descuento };
}

// ---------- Totales ----------
function actualizarTotales(){
  let totalImporte=0, totalIva=0, totalDescuento=0;
  document.querySelectorAll('#tabla-productos tbody tr').forEach(tr=>{
    const {subtotal, montoIva, descuento} = recalcularFila(tr);
    totalImporte += subtotal;
    totalIva     += montoIva;
    totalDescuento += descuento || 0;
  });
  
  // El descuento total es la suma de todos los descuentos por ítem
  // Se calcula automáticamente, no es editable
  const descuentoTotalEl = document.getElementById('descuento_total');
  if (descuentoTotalEl) {
    // Mantener 2 decimales para el descuento total
    const descuentoTotalFormateado = totalDescuento.toFixed(2);
    descuentoTotalEl.value = descuentoTotalFormateado;
    console.log('[TOTALES] Descuento total calculado:', {
      totalDescuento,
      formateado: descuentoTotalFormateado
    });
  }
  
  // El total general = total importe (que ya incluye los descuentos por ítem en el subtotal)
  // No se aplica un descuento adicional al total general
  const totalGeneral = totalImporte + totalIva;
  
  const ti = document.getElementById('total_importe');
  const tv = document.getElementById('total_iva');
  const tg = document.getElementById('total_general');
  if (ti) ti.value = String(Math.floor(totalImporte));
  if (tv) tv.value = String(Math.floor(totalIva));
  if (tg) tg.value = String(Math.floor(totalGeneral));
  
  // Debug: mostrar valores calculados
  console.log('[TOTALES]', {
    totalImporte: totalImporte,
    totalIva: totalIva,
    totalDescuento: totalDescuento,
    descuentoTotalCalculado: totalDescuento,
    totalGeneral: totalGeneral,
    numFilas: document.querySelectorAll('#tabla-productos tbody tr').length
  });
}

// ---------- Delegaciones comunes ----------
// Función para manejar cambios en campos editables
function manejarCambioCampo(e) {
  const target = e.target;
  
  // Verificar que el target sea un elemento válido
  if (!target || !target.tagName) {
    return;
  }
  
  // Si no está en la tabla de productos, ignorar (excepto descuento_total que es readonly)
  if (!target.closest('#tabla-productos')) {
    return;
  }
  
  // Verificar si el campo es readonly (no procesar en ese caso)
  if (target.hasAttribute('readonly') || target.readOnly) {
    console.log('[CAMBIO] Campo ignorado porque es readonly:', target.className);
    return;
  }
  
  // Solo procesar si es cantidad, precio o descuento
  if (target.classList.contains('cantidad') || 
      target.classList.contains('precio') || 
      target.classList.contains('descuento')) {
    const row = target.closest('tr');
    if (row) {
      console.log('[CAMBIO] Procesando cambio en campo:', {
        tipo: target.className,
        valor: target.value,
        fila: row.querySelector('td:nth-child(2)')?.textContent,
        readonly: target.readOnly,
        evento: e.type
      });
      
      // Recalcular la fila afectada
      recalcularFila(row);
      // Actualizar todos los totales
      actualizarTotales();
      // Validar formulario
      validarFormulario();
    }
  }
}

// Escuchar eventos input (tiempo real) - usar delegación de eventos para que funcione en modo edición
// Estos listeners se agregan al document, por lo que funcionan incluso si los elementos se agregan después
// Se configuran inmediatamente, no necesitan esperar al DOMContentLoaded
document.addEventListener('input', manejarCambioCampo);

// Escuchar eventos change (cuando se pierde el foco)
document.addEventListener('change', manejarCambioCampo);

// También escuchar eventos keyup para mejor respuesta
document.addEventListener('keyup', function(e) {
  if (e.target && e.target.closest && e.target.closest('#tabla-productos')) {
    if (e.target.classList.contains('cantidad') || 
        e.target.classList.contains('precio') || 
        e.target.classList.contains('descuento')) {
      manejarCambioCampo(e);
    }
  }
});

document.addEventListener('click',(e)=>{
  if (e.target.classList.contains('btn-quitar')){
    const row = e.target.closest('tr');
    row.remove();
    reindexRows();
    actualizarTotales();
    validarFormulario();
  }
});

// ---------- Carga por pedido (solo add) ----------
const pedidoSel = document.getElementById('pedido');
if (pedidoSel){
  pedidoSel.addEventListener('change', async function(){
    const pedidoId = this.value;
    if(!pedidoId) return;
    try{
      const resp = await fetch(`get_pedido_detalle.php?pedido_id=${pedidoId}`);
      const detalles = await resp.json();
      const tbody = document.querySelector('#tabla-productos tbody');
      tbody.innerHTML = '';
      detalles.forEach((d,idx)=>{
        const cantidad = d.cantidad || 1;
        const precio   = d.precio   || 0;
        // Preservar el texto original del IVA tal como viene de la BD
        const iva      = (d.iva||'').trim();
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${idx+1}</td>
          <td>${d.codigo}</td>
          <td>${d.descripcion}</td>
          <td><input type="number" class="form-control cantidad" min="1" value="${cantidad}" required></td>
          <td><input type="number" class="form-control precio" min="0" step="1" inputmode="numeric" pattern="\\d*" value="${precio}" required></td>
          <td><input type="number" class="form-control descuento" min="0" step="0.01" value="0"></td>
          <td class="iva">${iva}</td>
          <td class="monto_iva">0</td>
          <td class="subtotal">0</td>
          <td><button type="button" class="btn btn-danger btn-sm btn-quitar">Quitar</button></td>
        `;
        tbody.appendChild(tr);
        
        // Debug: ver qué valor de IVA se está recibiendo
        console.log(`[CARGAR PEDIDO] Producto ${d.codigo}: iva recibido="${iva}"`);
      });
      
      // Después de agregar todas las filas, recalcular cada una
      setTimeout(() => {
        document.querySelectorAll('#tabla-productos tbody tr').forEach(tr => {
          recalcularFila(tr);
        });
        // Actualizar totales después de cargar todos los items
        actualizarTotales();
        validarFormulario();
      }, 200);
    }catch(err){
      console.error(err);
      mostrarModal("Error al cargar detalles","No se pudieron cargar los detalles del pedido.","danger");
    }
  });
}

// ---------- Submit (add + edit) ----------
const form = document.getElementById('form-presupuesto');
if (form){
  form.addEventListener('submit',(e)=>{
    // Recalcular todas las filas primero para asegurar que el IVA esté calculado
    document.querySelectorAll('#tabla-productos tbody tr').forEach(tr=>{
      recalcularFila(tr);
    });
    
    // Recalcular todos los totales antes de enviar
    actualizarTotales();
    
    const productos = [];
    document.querySelectorAll('#tabla-productos tbody tr').forEach(tr=>{
      const codigo   = (tr.children[1]?.textContent||'').trim();
      const cantidad = toNumber(tr.querySelector('.cantidad')?.value);
      const precio   = getPrecioFromCell(tr.querySelector('.precio'));
      const descuento = toNumber(tr.querySelector('.descuento')?.value || 0);
      const ivaTxt   = (tr.querySelector('.iva')?.textContent||'').trim();
      const ivaTxtLower = ivaTxt.toLowerCase();
      
      // Calcular IVA siempre, no confiar en el valor del DOM
      // El IVA se calcula sobre el precio con descuento (base imponible)
      let ivaCalculado = 0;
      if (precio > 0 && cantidad > 0 && ivaTxt) {
        // Calcular precio con descuento (base imponible)
        const precioConDescuento = precio - (descuento / cantidad);
        const precioBaseImponible = precioConDescuento > 0 ? precioConDescuento : precio;
        
        let ivaUnit = 0;
        // Normalizar el texto del IVA: eliminar espacios, %, guiones, guiones bajos
        const ivaTxtNormalized = ivaTxtLower.replace(/[\s%_\-]/g, '');
        
        // Detectar IVA 10% en cualquier formato posible
        if (ivaTxtNormalized.includes('10') || 
            ivaTxtLower === 'iva_10' || 
            ivaTxtLower === 'iva10' ||
            ivaTxtLower === '10' ||
            ivaTxtLower.includes('10%')) {
          ivaUnit = Math.floor(precioBaseImponible / 11);
        } 
        // Detectar IVA 5% en cualquier formato posible
        else if (ivaTxtNormalized.includes('5') || 
                 ivaTxtLower === 'iva_5' || 
                 ivaTxtLower === 'iva5' ||
                 ivaTxtLower === '5' ||
                 ivaTxtLower.includes('5%')) {
          ivaUnit = Math.floor(precioBaseImponible / 21);
        }
        
        ivaCalculado = Math.floor(cantidad * ivaUnit);
        
        // Debug detallado en consola
        console.log(`[IVA CALC] Producto ${codigo}:`, {
          ivaTxt: ivaTxt,
          ivaTxtLower: ivaTxtLower,
          ivaTxtNormalized: ivaTxtNormalized,
          precio: precio,
          cantidad: cantidad,
          ivaUnit: ivaUnit,
          ivaCalculado: ivaCalculado
        });
        
        // Actualizar el DOM también para que se vea correctamente
        const ivaEl = tr.querySelector('.monto_iva');
        if (ivaEl) {
          ivaEl.textContent = String(ivaCalculado);
        }
      } else {
        // Debug: ver por qué no se calcula
        console.warn(`[IVA CALC] Producto ${codigo}: NO se calculó IVA`, {
          precio: precio,
          cantidad: cantidad,
          ivaTxt: ivaTxt,
          ivaTxtLength: ivaTxt ? ivaTxt.length : 0
        });
      }
      
      if (codigo && cantidad>0 && precio>0){
        productos.push({ codigo, cantidad, precio, descuento, iva: ivaCalculado });
      }
    });
    if (productos.length===0){
      e.preventDefault();
      mostrarModal("No se puede guardar","Debe existir al menos un ítem válido.","warning",true);
      return;
    }
    
    // Recalcular totales una última vez antes de enviar
    // Esto actualizará automáticamente el descuento_total (suma de descuentos por ítem)
    actualizarTotales();
    
    // El total_importe es la suma de subtotales (ya con descuentos aplicados)
    // El total_general = total_importe + total_iva
    const totalImporte = toNumber(document.getElementById('total_importe')?.value || 0);
    const totalIva = toNumber(document.getElementById('total_iva')?.value || 0);
    const totalGeneral = totalImporte + totalIva;
    
    const totalGeneralEl = document.getElementById('total_general');
    if (totalGeneralEl) {
      totalGeneralEl.value = String(Math.floor(totalGeneral));
    }
    
    // Asegurar que descuento_total tenga un valor válido (ya calculado por actualizarTotales)
    const descuentoTotalEl = document.getElementById('descuento_total');
    if (descuentoTotalEl) {
      let descuentoTotalValue = descuentoTotalEl.value.trim();
      // Si está vacío o no es numérico, recalcular
      if (!descuentoTotalValue || descuentoTotalValue === '' || isNaN(descuentoTotalValue)) {
        // Recalcular sumando todos los descuentos por ítem
        let totalDescuento = 0;
        document.querySelectorAll('#tabla-productos tbody tr').forEach(tr => {
          const descuento = toNumber(tr.querySelector('.descuento')?.value || 0);
          totalDescuento += descuento;
        });
        descuentoTotalEl.value = String(Math.floor(totalDescuento));
      }
    }
    
    // Debug: mostrar en consola lo que se va a enviar
    const descuentoTotalFinal = document.getElementById('descuento_total')?.value || '0';
    console.log('=== DEBUG PRESUPUESTO ===');
    console.log('Productos a enviar:', JSON.stringify(productos, null, 2));
    console.log('Descuento Total (campo):', descuentoTotalFinal);
    console.log('Descuento Total (tipo):', typeof descuentoTotalFinal);
    console.log('Descuento Total (numérico):', parseFloat(descuentoTotalFinal));
    console.log('Total IVA:', document.getElementById('total_iva')?.value);
    console.log('Total General:', document.getElementById('total_general')?.value);
    console.log('Total Importe:', document.getElementById('total_importe')?.value);
    
    // Verificar que el campo descuento_total tenga el valor correcto
    const descuentoTotalInput = document.getElementById('descuento_total');
    if (descuentoTotalInput) {
      console.log('Campo descuento_total encontrado:', {
        id: descuentoTotalInput.id,
        name: descuentoTotalInput.name,
        value: descuentoTotalInput.value,
        type: descuentoTotalInput.type
      });
    } else {
      console.error('ERROR: Campo descuento_total NO encontrado en el DOM');
    }
    
    // Verificar cada producto individualmente
    productos.forEach((prod, idx) => {
      console.log(`Producto ${idx + 1}:`, {
        codigo: prod.codigo,
        cantidad: prod.cantidad,
        precio: prod.precio,
        descuento: prod.descuento,
        iva: prod.iva
      });
    });
    
    const hidden = document.getElementById('productos');
    if (hidden) hidden.value = JSON.stringify(productos);
  });
}

// ---------- Validación ----------
function validarFormulario(){
  const btn = document.querySelector('button[name="Guardar"]');
  if (!btn) return;
  let ok = true;
  document.querySelectorAll('#tabla-productos tbody tr').forEach(tr=>{
    const cantidad = toNumber(tr.querySelector('.cantidad')?.value);
    const precio   = getPrecioFromCell(tr.querySelector('.precio'));
    if (cantidad<1 || precio<1) ok = false;
  });
  btn.disabled = !ok;
}

// ---------- Modal Bootstrap simple ----------
function mostrarModal(titulo, mensaje, tipo, recargar=false){
  const id='modalMensaje';
  document.getElementById(id)?.remove();
  const html = `
    <div class="modal fade" id="${id}" tabindex="-1" aria-labelledby="${id}Label" aria-hidden="true">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header bg-${tipo}"><h5 class="modal-title text-white" id="${id}Label">${titulo}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">${mensaje}</div>
        <div class="modal-footer">
          <button type="button" class="btn btn-${tipo}" data-bs-dismiss="modal" id="${id}Close">Cerrar</button>
        </div>
      </div></div>
    </div>`;
  document.body.insertAdjacentHTML('beforeend', html);
  const modal = new bootstrap.Modal(document.getElementById(id));
  modal.show();
  document.getElementById(id+'Close').addEventListener('click', ()=>{
    if(recargar) location.reload();
    document.getElementById(id)?.remove();
  });
}

// ---------- Init ----------
document.addEventListener('DOMContentLoaded', ()=>{
  actualizarTotales();
  validarFormulario();
});
</script>


<script>
// Limpiar campos al presionar Cancelar (ADD y EDIT)
document.addEventListener('DOMContentLoaded', () => {
  function limpiarCamposPresupuesto() {
    // Combo de pedidos
    const pedido = document.getElementById('pedido');
    if (pedido) { pedido.value = ''; pedido.dispatchEvent(new Event('change', { bubbles: true })); }

    // Proveedor
    const provSel = document.getElementById('proveedor');
    if (provSel) { provSel.value = ''; }
    const provNombreEdit = document.getElementById('proveedor_nombre_edit');
    if (provNombreEdit) { provNombreEdit.value = ''; }
    const provHiddenEdit = document.getElementById('proveedor_hidden_edit');
    if (provHiddenEdit) { provHiddenEdit.value = ''; }

    // Detalle: remover filas
    const tbody = document.querySelector('#tabla-productos tbody');
    if (tbody) { tbody.innerHTML = ''; }

    // Totales
    const ti = document.getElementById('total_importe');
    const tv = document.getElementById('total_iva');
    const tg = document.getElementById('total_general');
    if (ti) ti.value = '';
    if (tv) tv.value = '';
    if (tg) tg.value = '';

    // Hidden productos
    const hidden = document.getElementById('productos');
    if (hidden) hidden.value = '';
  }

  const btnCancelarAdd = document.getElementById('btn-cancelar');
  if (btnCancelarAdd) {
    btnCancelarAdd.addEventListener('click', (e) => {
      e.preventDefault();
      limpiarCamposPresupuesto();
    });
  }
});
</script>

<script>
// ¿Es un input objetivo dentro de la tabla?
function isNumField(el){
  return el && el.closest('#tabla-productos') &&
         (el.classList.contains('cantidad') || el.classList.contains('precio'));
}

// Quita todo lo no numérico
function onlyDigits(s){ return String(s || '').replace(/\D+/g, ''); }

// Normaliza mientras se escribe: solo dígitos, sin ceros a la izquierda
// Permite vacío (para poder borrar todo). El "mínimo 1" se aplica en blur/submit.
function normalizeLive(el){
  let v = onlyDigits(el.value);

  // Si el usuario puso "0" como primer y único char, vaciamos para que pueda seguir escribiendo.
  if (v === '0') v = '';

  // Si hay más de un dígito y empiezan ceros -> quitar ceros a la izquierda (00025 -> 25)
  v = v.replace(/^0+/, '');

  el.value = v;
}

// En blur/submit forzamos mínimo 1 (si quedó vacío o 0)
function normalizeFinal(el){
  let v = onlyDigits(el.value);
  v = v.replace(/^0+/, '');
  if (v === '') v = '1';
  el.value = v;
}

// 1) Bloquear antes de que entre (teclado y pegado)
document.addEventListener('beforeinput', (e) => {
  const el = e.target;
  if (!isNumField(el)) return;

  if (e.inputType === 'insertText' && e.data != null) {
    if (!/^\d$/.test(e.data)) e.preventDefault(); // solo dígito 0-9
  }

  if (e.inputType === 'insertFromPaste') {
    const txt = (e.clipboardData || window.clipboardData)?.getData('text') || '';
    if (!/^\d+$/.test(txt)) e.preventDefault(); // bloquea pegado con no-dígitos
  }
});

// 2) Capa extra por teclado (algunos navegadores permiten "-", ".", ",", "e")
document.addEventListener('keydown', (e) => {
  const el = e.target;
  if (!isNumField(el)) return;

  const blocked = ['.', ',', '-', '+', 'e', 'E'];
  if (blocked.includes(e.key)) e.preventDefault();
});

// 3) Limpieza en vivo (permite vacío)
document.addEventListener('input', (e) => {
  const el = e.target;
  if (!isNumField(el)) return;
  normalizeLive(el);
});


</script>







<!-- Modal de Alerta -->
<div class="modal fade" id="alertModal" tabindex="-1" aria-labelledby="alertModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="alertModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="redirigirInicio()"></button>
            </div>
            <div class="modal-body" id="alertModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="redirigirInicio()">Cerrar</button>
            </div>
        </div>
    </div>
</div>


  











