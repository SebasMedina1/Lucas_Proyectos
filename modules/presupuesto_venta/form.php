<?php 
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Verificar si la sesión es válida
if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}

// Verificar si existe el parámetro 'form' en la URL
if (isset($_GET['form_presupuesto_venta']) && $_GET['form'] == 'add') { 
    require "../../config/database.php";
?>
 <div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">
        <i class="fas fa-plus-circle"></i> Registrar Presupuesto de Venta
    </h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="view.php">Presupuestos de Ventas</a></li>
        <li class="breadcrumb-item active">Nuevo Presupuesto</li>
    </ol>

    <div class="modal fade" id="modalAviso" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Producto duplicado</h5>
            <button type="button" class="btn-close" data-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body"><p></p></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-primary" data-dismiss="modal">Entendido</button>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="proses.php?act=insert" method="POST" id="formPresupuesto" novalidate>
                <?php
                try {
                    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                    $pdo = new PDO($dsn, $user, $pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Obtener el máximo valor de id_presupuesto_venta
                    $query = $pdo->query("SELECT MAX(id_presupuesto_venta) AS id FROM presupuesto_venta");
                    $data = $query->fetch(PDO::FETCH_ASSOC);
                    $codigo = ($data['id'] !== null) ? $data['id'] + 1 : 1;

                    date_default_timezone_set('America/Asuncion');
                    $fecha = date("Y-m-d");
                    $hora = date("H:i:s");

                    $userSesion = $_SESSION['username'];
                    $sqlUser = "
                        SELECT 
                            u.id_usuario,
                            u.username,
                            u.id_sucursal,
                            s.descripcion_sucursal
                        FROM usuarios u
                        JOIN sucursales s ON s.id_sucursal = u.id_sucursal
                        WHERE u.username = :user
                        LIMIT 1
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
                    die("Error al obtener los datos: " . $e->getMessage());
                }
                ?>
                
                <input type="hidden" name="usuario_id" value="<?= $usuarioId ?>">
                <input type="hidden" name="sucursal_id" value="<?= $sucursalId ?>">
                
                <div class="row mb-3">
                    <div class="col-md-2">
                        <label for="fecha" class="form-label">Fecha</label>
                        <input type="text" class="form-control" id="fecha" name="fecha" value="<?= $fecha ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label for="codigo" class="form-label">Presupuesto N°</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?= $codigo ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label for="usuario" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="usuario" value="<?= htmlspecialchars($usuarioNombre) ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="sucursal" class="form-label">Sucursal</label>
                        <input type="text" class="form-control" id="sucursal" value="<?= htmlspecialchars($sucursalNombre) ?>" readonly>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="cliente" class="form-label">Cliente <span class="text-danger">*</span></label>
                        <select class="form-control" id="cliente" name="cliente_id" required>
                            <option value="" selected>Seleccione un Cliente</option>
                            <?php
                            try {
                                $query_cli = $pdo->query("
                                    SELECT id_cliente, cliente_nombre || ' ' || cliente_apellido AS nombre_completo, cliente_ruc
                                    FROM clientes 
                                    WHERE cliente_estado = 'ACTIVO'
                                    ORDER BY cliente_nombre ASC
                                ");
                                while ($cli = $query_cli->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"{$cli['id_cliente']}\">{$cli['nombre_completo']} - RUC: {$cli['cliente_ruc']}</option>";
                                }
                            } catch (PDOException $e) {
                                die("Error al cargar clientes: " . $e->getMessage());
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="pedido_venta" class="form-label">Vincular a Pedido (Opcional)</label>
                        <select class="form-control" id="pedido_venta" name="pedido_venta_id">
                            <option value="">Sin vincular</option>
                            <?php
                            try {
                                $query_ped = $pdo->query("
                                    SELECT pv.id_pedido_venta, 
                                           pv.pedido_fecha,
                                           c.cliente_nombre || ' ' || c.cliente_apellido AS cliente_nombre
                                    FROM pedido_venta pv
                                    JOIN clientes c ON c.id_cliente = pv.id_cliente
                                    WHERE pv.pedido_estado = 'PENDIENTE'
                                    ORDER BY pv.id_pedido_venta DESC
                                    LIMIT 50
                                ");
                                while ($ped = $query_ped->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"{$ped['id_pedido_venta']}\">Pedido #{$ped['id_pedido_venta']} - {$ped['cliente_nombre']} ({$ped['pedido_fecha']})</option>";
                                }
                            } catch (PDOException $e) {
                                // Ignorar si no existe la tabla
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="validez" class="form-label">Validez (días) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="validez" name="validez" min="1" value="30" required placeholder="Ej: 30">
                    </div>
                    <div class="col-md-3">
                        <label for="fecha_vencimiento" class="form-label">Fecha de Vencimiento</label>
                        <input type="date" class="form-control" id="fecha_vencimiento" readonly>
                    </div>
                    <div class="col-md-6">
                        <label for="observacion" class="form-label">Observación</label>
                        <textarea class="form-control" id="observacion" name="observacion" rows="2" placeholder="Observaciones adicionales"></textarea>
                    </div>
                </div>

                <hr>
                <h5>Agregar Productos</h5>
                
                <div class="row align-items-end mb-3">
                    <div class="col-md-5">
                        <label for="producto" class="form-label">Producto</label>
                        <select class="form-control" id="producto" name="producto">
                            <option value="" selected>Seleccione un Producto</option>
                            <?php
                            try {
                                $query_prod = $pdo->query("
                                    SELECT 
                                        p.producto_id, 
                                        p.producto_descri, 
                                        p.producto_precio, 
                                        um.unidad_descri,
                                        COALESCE(ti.iva_descri, 'N/A') AS iva_descri,
                                        COALESCE(p.iva_id, 0) AS iva_id
                                    FROM productos p
                                    JOIN unidad_medida um ON p.id_unidad = um.id_unidad
                                    LEFT JOIN tipo_iva ti ON p.iva_id = ti.iva_id
                                    WHERE p.producto_estado = 'ACTIVO'
                                    ORDER BY p.producto_descri ASC
                                ");
                                while ($prod = $query_prod->fetch(PDO::FETCH_ASSOC)) {
                                    $precio = number_format($prod['producto_precio'], 0, ',', '.');
                                    $iva = $prod['iva_descri'];
                                    $ivaId = (int)$prod['iva_id'];
                                    echo "<option value=\"{$prod['producto_id']}\" 
                                            data-precio=\"{$prod['producto_precio']}\" 
                                            data-iva=\"{$iva}\"
                                            data-iva-id=\"{$ivaId}\"
                                            data-unidad=\"{$prod['unidad_descri']}\">
                                            {$prod['producto_descri']} - Precio: {$precio} - IVA: {$iva} - Unidad: {$prod['unidad_descri']}
                                          </option>";
                                }
                            } catch (PDOException $e) {
                                die("Error al cargar productos: " . $e->getMessage());
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="cantidad_producto" class="form-label">Cantidad</label>
                        <input type="number" class="form-control" id="cantidad_producto" min="1" step="1" placeholder="Ingrese cantidad">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-primary w-100" id="btn-agregar">Agregar</button>
                    </div>
                </div>

                <!-- Tabla de productos -->
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-striped" id="tabla-productos">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio Unit.</th>
                                <th>IVA</th>
                                <th>Subtotal</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                        <tfoot>
                            <tr class="table-info">
                                <td colspan="6" class="text-right font-weight-bold">TOTAL:</td>
                                <td class="font-weight-bold" id="total-presupuesto">0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Subtotal (sin IVA):</label>
                        <input type="text" class="form-control" id="subtotal-sin-iva" readonly value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Total IVA:</label>
                        <input type="text" class="form-control" id="total-iva" readonly value="0">
                    </div>
                </div>

                <input type="hidden" name="productos" id="productos">
                <input type="hidden" name="monto_total" id="monto_total">

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-success me-2" name="Guardar" id="btn-guardar">Guardar</button>
                    <a href="view.php" class="btn btn-warning me-2">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
} elseif (isset($_GET['form_presupuesto_venta']) && $_GET['form'] == 'edit') { 
    require "../../config/database.php";

    $preId = isset($_GET['pre_id']) ? (int)$_GET['pre_id'] : 0;
    if ($preId <= 0) { 
        header("Location: view.php?alert=4"); 
        exit; 
    }

    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Cabecera
        $cab = $pdo->prepare("
            SELECT pv.id_presupuesto_venta,
                   pv.fecha_presupuesto,
                   pv.estado,
                   pv.id_cliente,
                   pv.validez,
                   pv.observacion,
                   pv.id_pedido_venta,
                   c.cliente_nombre || ' ' || c.cliente_apellido AS cliente_nombre,
                   u.username,
                   s.descripcion_sucursal
            FROM presupuesto_venta pv
            JOIN clientes c ON c.id_cliente = pv.id_cliente
            JOIN usuarios u ON u.id_usuario = pv.id_usuario
            JOIN sucursales s ON s.id_sucursal = pv.id_sucursal
            WHERE pv.id_presupuesto_venta = :id
            LIMIT 1
        ");
        $cab->execute([':id'=>$preId]);
        $cabecera = $cab->fetch(PDO::FETCH_ASSOC);
        if (!$cabecera) { 
            header("Location: view.php?alert=4"); 
            exit; 
        }

        // Detalle con información de IVA
        $det = $pdo->prepare("
            SELECT d.producto_id,
                   p.producto_descri,
                   d.cantidad,
                   d.precio_unitario,
                   d.iva,
                   COALESCE(ti.iva_descri, 'N/A') AS iva_descri
            FROM detalle_presupuesto_venta d
            JOIN productos p ON p.producto_id = d.producto_id
            LEFT JOIN tipo_iva ti ON p.iva_id = ti.iva_id
            WHERE d.id_presupuesto_venta = :id
            ORDER BY d.producto_id
        ");
        $det->execute([':id'=>$preId]);
        $detalles = $det->fetchAll(PDO::FETCH_ASSOC);

        date_default_timezone_set('America/Asuncion');
        $fechaHoy = date('Y-m-d');
        $horaAhora = date('H:i:s');

    } catch (PDOException $e) {
        die("Error al cargar el presupuesto: ".$e->getMessage());
    }
?>

<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-edit"></i> Editar Presupuesto de Venta</h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
    <li class="breadcrumb-item"><a href="view.php">Presupuestos de Ventas</a></li>
    <li class="breadcrumb-item active">Editar</li>
  </ol>

  <div class="card shadow mb-4">
    <div class="card-body">
      <form action="proses.php?act=update" method="POST" id="formPresupuestoEdit" novalidate>
        <input type="hidden" name="presupuesto_id" value="<?= (int)$cabecera['id_presupuesto_venta'] ?>">
        <input type="hidden" name="productos" id="productos-edit">
        <input type="hidden" name="monto_total" id="monto_total-edit">

        <div class="row mb-3">
          <div class="col-md-2">
            <label class="form-label">Presupuesto N°</label>
            <input class="form-control" value="<?= (int)$cabecera['id_presupuesto_venta'] ?>" readonly>
          </div>
          <div class="col-md-2">
            <label class="form-label">Fecha</label>
            <input class="form-control" value="<?= htmlspecialchars($cabecera['fecha_presupuesto']) ?>" readonly>
          </div>
          <div class="col-md-2">
            <label class="form-label">Estado</label>
            <input class="form-control" value="<?= htmlspecialchars($cabecera['estado']) ?>" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label">Usuario</label>
            <input class="form-control" value="<?= htmlspecialchars($cabecera['username']) ?>" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label">Sucursal</label>
            <input class="form-control" value="<?= htmlspecialchars($cabecera['descripcion_sucursal']) ?>" readonly>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label for="cliente_edit" class="form-label">Cliente <span class="text-danger">*</span></label>
            <select class="form-control" id="cliente_edit" name="cliente_id" required>
              <option value="">Seleccione un Cliente</option>
              <?php
              try {
                  $query_cli = $pdo->query("
                      SELECT id_cliente, cliente_nombre || ' ' || cliente_apellido AS nombre_completo, cliente_ruc
                      FROM clientes 
                      WHERE cliente_estado = 'ACTIVO'
                      ORDER BY cliente_nombre ASC
                  ");
                  while ($cli = $query_cli->fetch(PDO::FETCH_ASSOC)) {
                      $selected = ((int)$cli['id_cliente'] === (int)$cabecera['id_cliente']) ? 'selected' : '';
                      echo "<option value=\"{$cli['id_cliente']}\" {$selected}>{$cli['nombre_completo']} - RUC: {$cli['cliente_ruc']}</option>";
                  }
              } catch (PDOException $e) {
                  die("Error al cargar clientes: " . $e->getMessage());
              }
              ?>
            </select>
          </div>
          <div class="col-md-3">
            <label for="pedido_venta_edit" class="form-label">Vincular a Pedido (Opcional)</label>
            <select class="form-control" id="pedido_venta_edit" name="pedido_venta_id">
              <option value="">Sin vincular</option>
              <?php
              try {
                  $query_ped = $pdo->query("
                      SELECT pv.id_pedido_venta, 
                             pv.pedido_fecha,
                             c.cliente_nombre || ' ' || c.cliente_apellido AS cliente_nombre
                      FROM pedido_venta pv
                      JOIN clientes c ON c.id_cliente = pv.id_cliente
                      WHERE pv.pedido_estado = 'PENDIENTE'
                      ORDER BY pv.id_pedido_venta DESC
                      LIMIT 50
                  ");
                  while ($ped = $query_ped->fetch(PDO::FETCH_ASSOC)) {
                      $selected = ((int)$ped['id_pedido_venta'] === (int)($cabecera['id_pedido_venta'] ?? 0)) ? 'selected' : '';
                      echo "<option value=\"{$ped['id_pedido_venta']}\" {$selected}>Pedido #{$ped['id_pedido_venta']} - {$ped['cliente_nombre']} ({$ped['pedido_fecha']})</option>";
                  }
              } catch (PDOException $e) {
                  // Ignorar si no existe la tabla
              }
              ?>
            </select>
          </div>
        </div>

        <div class="row mb-3">
          <?php
          // Calcular días de validez desde la fecha de vencimiento
          $validez_dias = 30;
          if (!empty($cabecera['validez'])) {
              $fecha_presupuesto = new DateTime($cabecera['fecha_presupuesto']);
              $fecha_validez = new DateTime($cabecera['validez']);
              $diff = $fecha_presupuesto->diff($fecha_validez);
              $validez_dias = $diff->days;
          }
          ?>
          <div class="col-md-3">
            <label for="validez_edit" class="form-label">Validez (días) <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="validez_edit" name="validez" min="1" value="<?= $validez_dias ?>" required placeholder="Ej: 30">
          </div>
          <div class="col-md-3">
            <label for="fecha_vencimiento_edit" class="form-label">Fecha de Vencimiento</label>
            <input type="date" class="form-control" id="fecha_vencimiento_edit" value="<?= htmlspecialchars($cabecera['validez']) ?>" readonly>
          </div>
          <div class="col-md-6">
            <label for="observacion_edit" class="form-label">Observación</label>
            <textarea class="form-control" id="observacion_edit" name="observacion" rows="2" placeholder="Observaciones adicionales"><?= htmlspecialchars($cabecera['observacion'] ?? '') ?></textarea>
          </div>
        </div>

        <hr>
        <h5>Productos del Presupuesto</h5>
        
        <div class="row align-items-end mb-3">
          <div class="col-md-5">
            <label for="producto_edit" class="form-label">Agregar Producto</label>
            <select class="form-control" id="producto_edit" name="producto">
              <option value="" selected>Seleccione un Producto</option>
              <?php
              try {
                  $query_prod = $pdo->query("
                      SELECT 
                          p.producto_id, 
                          p.producto_descri, 
                          p.producto_precio, 
                          um.unidad_descri,
                          COALESCE(ti.iva_descri, 'N/A') AS iva_descri,
                          COALESCE(p.iva_id, 0) AS iva_id
                      FROM productos p
                      JOIN unidad_medida um ON p.id_unidad = um.id_unidad
                      LEFT JOIN tipo_iva ti ON p.iva_id = ti.iva_id
                      WHERE p.producto_estado = 'ACTIVO'
                      ORDER BY p.producto_descri ASC
                  ");
                  while ($prod = $query_prod->fetch(PDO::FETCH_ASSOC)) {
                      $precio = number_format($prod['producto_precio'], 0, ',', '.');
                      $iva = $prod['iva_descri'];
                      $ivaId = (int)$prod['iva_id'];
                      echo "<option value=\"{$prod['producto_id']}\" 
                              data-precio=\"{$prod['producto_precio']}\" 
                              data-iva=\"{$iva}\"
                              data-iva-id=\"{$ivaId}\"
                              data-unidad=\"{$prod['unidad_descri']}\">
                              {$prod['producto_descri']} - Precio: {$precio} - IVA: {$iva} - Unidad: {$prod['unidad_descri']}
                            </option>";
                  }
              } catch (PDOException $e) {
                  die("Error al cargar productos: " . $e->getMessage());
              }
              ?>
            </select>
          </div>
          <div class="col-md-2">
            <label for="cantidad_producto_edit" class="form-label">Cantidad</label>
            <input type="number" class="form-control" id="cantidad_producto_edit" min="1" step="1" placeholder="Cantidad">
          </div>
          <div class="col-md-2">
            <label class="form-label">&nbsp;</label>
            <button type="button" class="btn btn-primary w-100" id="btn-agregar-edit">Agregar</button>
          </div>
        </div>

        <div class="table-responsive mb-4">
          <table class="table table-bordered table-striped" id="tabla-productos-edit">
            <thead class="table-dark">
              <tr>
                <th>#</th>
                <th>Código</th>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio Unit.</th>
                <th>IVA</th>
                <th>Subtotal</th>
                <th>Acción</th>
              </tr>
            </thead>
            <tbody id="tbody-productos-edit">
              <?php if ($detalles): $i=1; foreach ($detalles as $d): 
                $precio = (float)$d['precio_unitario'];
                $cant = (int)$d['cantidad'];
                $ivaPct = (int)$d['iva'];
                $subtotal = $precio * $cant;
                $ivaCalc = $subtotal * ($ivaPct / 100);
                $subtotalConIva = $subtotal + $ivaCalc;
                $ivaLabel = $ivaPct > 0 ? $ivaPct . '%' : 'Exento';
              ?>
                <tr data-producto="<?= (int)$d['producto_id'] ?>" data-existente="1">
                  <td><?= $i++ ?></td>
                  <td><?= (int)$d['producto_id'] ?></td>
                  <td><?= htmlspecialchars($d['producto_descri']) ?></td>
                  <td>
                    <input type="number"
                           class="form-control cantidad-edit"
                           name="cantidad[<?= (int)$d['producto_id'] ?>]"
                           value="<?= $cant ?>"
                           min="1" 
                           data-precio="<?= $precio ?>"
                           data-iva="<?= $ivaPct ?>"
                           required>
                  </td>
                  <td><?= number_format($precio, 0, ',', '.') ?></td>
                  <td><?= htmlspecialchars($ivaLabel) ?></td>
                  <td class="subtotal-linea"><?= number_format($subtotalConIva, 0, ',', '.') ?></td>
                  <td>
                    <button type="button" class="btn btn-danger btn-sm btn-quitar-edit" data-producto="<?= (int)$d['producto_id'] ?>">Quitar</button>
                  </td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="8" class="text-center">Sin detalles. Agregue productos.</td></tr>
              <?php endif; ?>
            </tbody>
            <tfoot>
              <tr class="table-info">
                <td colspan="6" class="text-right font-weight-bold">TOTAL:</td>
                <td class="font-weight-bold" id="total-presupuesto-edit">0</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Subtotal (sin IVA):</label>
            <input type="text" class="form-control" id="subtotal-sin-iva-edit" readonly value="0">
          </div>
          <div class="col-md-6">
            <label class="form-label">Total IVA:</label>
            <input type="text" class="form-control" id="total-iva-edit" readonly value="0">
          </div>
        </div>

        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-success me-2" name="Guardar" id="btn-guardar-edit">Guardar cambios</button>
          <a href="view.php" class="btn btn-warning">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const productosEdit = [];
    const tbodyEdit = document.getElementById('tbody-productos-edit');
    const formEdit = document.getElementById('formPresupuestoEdit');
    const productoSelectEdit = document.getElementById('producto_edit');
    const cantidadInputEdit = document.getElementById('cantidad_producto_edit');
    const btnAgregarEdit = document.getElementById('btn-agregar-edit');
    const validezInputEdit = document.getElementById('validez_edit');
    const fechaVencimientoEdit = document.getElementById('fecha_vencimiento_edit');
    const fechaPresupuesto = '<?= htmlspecialchars($cabecera['fecha_presupuesto']) ?>';

    // Cargar productos existentes en el array
    if (tbodyEdit) {
        tbodyEdit.querySelectorAll('tr[data-producto]').forEach(row => {
            const productoId = row.getAttribute('data-producto');
            const cantidadInput = row.querySelector('.cantidad-edit');
            const precio = parseFloat(cantidadInput.getAttribute('data-precio')) || 0;
            const ivaPct = parseFloat(cantidadInput.getAttribute('data-iva')) || 0;
            const cantidad = parseInt(cantidadInput.value) || 0;
            const subtotal = precio * cantidad;
            const ivaCalc = subtotal * (ivaPct / 100);
            const subtotalConIva = subtotal + ivaCalc;
            
            productosEdit.push({
                codigo: productoId,
                cantidad: cantidad,
                precio: precio,
                ivaPorcentaje: ivaPct,
                ivaCalculado: ivaCalc,
                subtotal: subtotal,
                subtotalConIva: subtotalConIva,
                existente: true
            });
        });
    }

    // Calcular fecha de vencimiento
    function calcularFechaVencimientoEdit() {
        const dias = parseInt(validezInputEdit.value) || 0;
        if (dias > 0 && fechaPresupuesto) {
            const fechaObj = new Date(fechaPresupuesto);
            fechaObj.setDate(fechaObj.getDate() + dias);
            const fechaVenc = fechaObj.toISOString().split('T')[0];
            fechaVencimientoEdit.value = fechaVenc;
        }
    }
    
    if (validezInputEdit && fechaVencimientoEdit) {
        validezInputEdit.addEventListener('input', calcularFechaVencimientoEdit);
        calcularFechaVencimientoEdit();
    }

    // Función para recalcular totales
    function recalcularTotalesEdit() {
        let subtotal = 0;
        let totalIva = 0;
        let total = 0;
        
        productosEdit.forEach(p => {
            subtotal += p.subtotal;
            totalIva += p.ivaCalculado;
            total += p.subtotalConIva;
        });
        
        document.getElementById('subtotal-sin-iva-edit').value = new Intl.NumberFormat('es-PY').format(subtotal);
        document.getElementById('total-iva-edit').value = new Intl.NumberFormat('es-PY').format(totalIva);
        document.getElementById('total-presupuesto-edit').textContent = new Intl.NumberFormat('es-PY').format(total);
        document.getElementById('monto_total-edit').value = total;
        document.getElementById('productos-edit').value = JSON.stringify(productosEdit);
    }

    // Actualizar totales cuando cambian las cantidades
    if (tbodyEdit) {
        tbodyEdit.addEventListener('input', function(e) {
            if (e.target.classList.contains('cantidad-edit')) {
                const row = e.target.closest('tr');
                const productoId = row.getAttribute('data-producto');
                const cantidad = parseInt(e.target.value) || 0;
                const precio = parseFloat(e.target.getAttribute('data-precio')) || 0;
                const ivaPct = parseFloat(e.target.getAttribute('data-iva')) || 0;
                
                const subtotal = precio * cantidad;
                const ivaCalc = subtotal * (ivaPct / 100);
                const subtotalConIva = subtotal + ivaCalc;
                
                // Actualizar en array
                const index = productosEdit.findIndex(p => String(p.codigo) === String(productoId));
                if (index >= 0) {
                    productosEdit[index].cantidad = cantidad;
                    productosEdit[index].subtotal = subtotal;
                    productosEdit[index].ivaCalculado = ivaCalc;
                    productosEdit[index].subtotalConIva = subtotalConIva;
                }
                
                // Actualizar subtotal en fila
                row.querySelector('.subtotal-linea').textContent = new Intl.NumberFormat('es-PY').format(subtotalConIva);
                
                recalcularTotalesEdit();
            }
        });
    }

    // Agregar nuevo producto
    if (btnAgregarEdit && productoSelectEdit && cantidadInputEdit && tbodyEdit) {
        btnAgregarEdit.addEventListener('click', function() {
            const productoId = productoSelectEdit.value;
            const cantidad = parseInt(cantidadInputEdit.value, 10);
            const option = productoSelectEdit.options[productoSelectEdit.selectedIndex];

            if (!productoId || isNaN(cantidad) || cantidad < 1) {
                alert('Por favor, seleccione un producto y especifique una cantidad válida.');
                return;
            }

            // Evitar duplicados
            const yaExiste = productosEdit.some(p => String(p.codigo) === String(productoId));
            if (yaExiste) {
                const nombre = option.text.split(' - ')[0];
                alert(`El producto "${nombre}" ya está en el detalle.`);
                return;
            }

            const precio = parseFloat(option.getAttribute('data-precio')) || 0;
            const iva = option.getAttribute('data-iva') || 'N/A';
            const ivaId = parseInt(option.getAttribute('data-iva-id')) || 0;
            
            // Calcular subtotal e IVA
            const subtotal = precio * cantidad;
            let ivaCalculado = 0;
            let subtotalConIva = subtotal;
            let ivaPorcentaje = 0;
            
            if (ivaId > 0 && iva !== 'N/A') {
                const ivaMatch = iva.match(/(\d+)/);
                if (ivaMatch) {
                    ivaPorcentaje = parseFloat(ivaMatch[1]);
                    ivaCalculado = subtotal * (ivaPorcentaje / 100);
                    subtotalConIva = subtotal + ivaCalculado;
                }
            }

            // Agregar al array
            productosEdit.push({
                codigo: productoId,
                cantidad: cantidad,
                precio: precio,
                iva: iva,
                ivaId: ivaId,
                ivaPorcentaje: ivaPorcentaje,
                ivaCalculado: ivaCalculado,
                subtotal: subtotal,
                subtotalConIva: subtotalConIva,
                existente: false
            });

            // Agregar fila
            const row = document.createElement('tr');
            row.setAttribute('data-producto', productoId);
            row.setAttribute('data-existente', '0');
            row.innerHTML = `
                <td>${productosEdit.length}</td>
                <td>${productoId}</td>
                <td>${option.text.split(' - ')[0]}</td>
                <td>
                    <input type="number"
                           class="form-control cantidad-edit"
                           name="cantidad[${productoId}]"
                           value="${cantidad}"
                           min="1"
                           data-precio="${precio}"
                           data-iva="${ivaPorcentaje}"
                           required>
                </td>
                <td>${new Intl.NumberFormat('es-PY').format(precio)}</td>
                <td>${iva}</td>
                <td class="subtotal-linea">${new Intl.NumberFormat('es-PY').format(subtotalConIva)}</td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm btn-quitar-edit" data-producto="${productoId}">Quitar</button>
                </td>
            `;
            tbodyEdit.appendChild(row);

            // Limpiar campos
            productoSelectEdit.value = '';
            cantidadInputEdit.value = '';

            recalcularTotalesEdit();
        });
    }

    // Quitar productos
    if (tbodyEdit) {
        tbodyEdit.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-quitar-edit')) {
                if (confirm('¿Está seguro que desea quitar este producto?')) {
                    const row = e.target.closest('tr');
                    const productoId = row.getAttribute('data-producto');
                    
                    // Remover del array
                    const index = productosEdit.findIndex(p => String(p.codigo) === String(productoId));
                    if (index >= 0) {
                        productosEdit.splice(index, 1);
                    }
                    
                    row.remove();
                    
                    // Actualizar índices
                    Array.from(tbodyEdit.children).forEach((r, idx) => {
                        if (r.querySelector('td')) {
                            r.querySelector('td').innerText = idx + 1;
                        }
                    });
                    
                    recalcularTotalesEdit();
                }
            }
        });
    }

    // Validar antes de enviar
    if (formEdit) {
        formEdit.addEventListener('submit', function(e) {
            if (productosEdit.length === 0) {
                e.preventDefault();
                alert('Por favor, agregue como mínimo un producto');
                return false;
            }
            
            const cliente = document.getElementById('cliente_edit');
            if (!cliente || !cliente.value) {
                e.preventDefault();
                alert('Por favor, seleccione un cliente');
                cliente.focus();
                return false;
            }
            
            // Remover required de campos auxiliares para evitar validación HTML5
            const cantidadAux = document.getElementById('cantidad_producto_edit');
            if (cantidadAux) {
                cantidadAux.removeAttribute('required');
            }
            
            // Validar que todos los inputs de cantidad en la tabla tengan valor
            const inputsCantidad = formEdit.querySelectorAll('input.cantidad-edit');
            let hayError = false;
            inputsCantidad.forEach(input => {
                const valor = parseInt(input.value) || 0;
                if (valor < 1) {
                    hayError = true;
                    input.focus();
                }
            });
            
            if (hayError) {
                e.preventDefault();
                alert('Por favor, verifique que todas las cantidades sean mayores a 0');
                return false;
            }
        });
    }

    // Validación de inputs numéricos
    const inputs = document.querySelectorAll('input.cantidad-edit');
    inputs.forEach(input => {
        input.addEventListener('keydown', e => {
            const noPermitidos = ['e','E','+','-','.',','];
            if (noPermitidos.includes(e.key)) e.preventDefault();
        });
        input.addEventListener('blur', e => {
            if (e.target.value === '' || parseInt(e.target.value) < 1) {
                e.target.value = '1';
            }
        });
    });

    // Calcular totales iniciales
    recalcularTotalesEdit();
});
</script>

<?php } ?>

<!-- Scripts para formulario de alta -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Calcular fecha de vencimiento desde días de validez
    const validezInput = document.getElementById('validez');
    const fechaVencimientoInput = document.getElementById('fecha_vencimiento');
    const fechaInput = document.getElementById('fecha');
    
    if (validezInput && fechaVencimientoInput && fechaInput) {
        function calcularFechaVencimiento() {
            const dias = parseInt(validezInput.value) || 0;
            const fecha = fechaInput.value;
            
            if (dias > 0 && fecha) {
                const fechaObj = new Date(fecha);
                fechaObj.setDate(fechaObj.getDate() + dias);
                const fechaVencimiento = fechaObj.toISOString().split('T')[0];
                fechaVencimientoInput.value = fechaVencimiento;
            } else {
                fechaVencimientoInput.value = '';
            }
        }
        
        validezInput.addEventListener('input', calcularFechaVencimiento);
        fechaInput.addEventListener('change', calcularFechaVencimiento);
        
        // Calcular al cargar
        calcularFechaVencimiento();
    }
    
    const productos = [];
    window.productos = productos;
    const tablaProductos = document.getElementById('tabla-productos');
    const tbody = tablaProductos ? tablaProductos.querySelector('tbody') : null;
    const form = document.getElementById('formPresupuesto');

    if (!form || !tbody) return;

    // Validar que haya productos antes de enviar
    form.addEventListener('submit', function (e) {
        if (productos.length === 0) {
            e.preventDefault();
            alert('Por favor, agregue como mínimo un producto');
            return false;
        }
        
        // Validar que se haya seleccionado un cliente
        const cliente = document.getElementById('cliente');
        if (!cliente || !cliente.value) {
            e.preventDefault();
            alert('Por favor, seleccione un cliente');
            cliente.focus();
            return false;
        }
        
        // Remover required de campos auxiliares para evitar validación HTML5
        const cantidadAux = document.getElementById('cantidad_producto');
        if (cantidadAux) {
            cantidadAux.removeAttribute('required');
        }
        
        // Validar que todos los inputs de cantidad en la tabla tengan valor
        const inputsCantidad = form.querySelectorAll('input[type="number"][name^="cantidad"]');
        let hayError = false;
        inputsCantidad.forEach(input => {
            // Solo validar los que están en la tabla, no los auxiliares
            if (input.closest('tbody')) {
                const valor = parseInt(input.value) || 0;
                if (valor < 1) {
                    hayError = true;
                    input.focus();
                }
            }
        });
        
        if (hayError) {
            e.preventDefault();
            alert('Por favor, verifique que todas las cantidades sean mayores a 0');
            return false;
        }
    });

    // Función para calcular totales
    function calcularTotales() {
        let subtotal = 0;
        let totalIva5 = 0;
        let totalIva10 = 0;
        let totalIvaExento = 0;
        let total = 0;
        
        productos.forEach(p => {
            subtotal += p.subtotal;
            const ivaPorcentaje = p.ivaPorcentaje || 0;
            if (ivaPorcentaje === 5) {
                totalIva5 += p.ivaCalculado || 0;
            } else if (ivaPorcentaje === 10) {
                totalIva10 += p.ivaCalculado || 0;
            } else {
                totalIvaExento += 0;
            }
            total += p.subtotalConIva || p.subtotal;
        });
        
        // Actualizar totales
        document.getElementById('subtotal-sin-iva').value = new Intl.NumberFormat('es-PY').format(subtotal);
        document.getElementById('total-iva').value = new Intl.NumberFormat('es-PY').format(totalIva5 + totalIva10);
        document.getElementById('total-presupuesto').textContent = new Intl.NumberFormat('es-PY').format(total);
        document.getElementById('monto_total').value = total;
        document.getElementById('productos').value = JSON.stringify(productos);
    }

    // Eliminar producto
    if (tbody) {
        tbody.addEventListener('click', function (event) {
            if (event.target.classList.contains('btn-eliminar')) {
                const row = event.target.closest('tr');
                const index = Array.from(tbody.children).indexOf(row);
                productos.splice(index, 1);
                row.remove();
                
                // Actualizar índices
                Array.from(tbody.children).forEach((row, idx) => {
                    row.children[0].innerText = idx + 1;
                });
                
                calcularTotales();
            }
        });
    }

    // Agregar producto
    const btnAgregar = document.getElementById('btn-agregar');
    const cantidadInput = document.getElementById('cantidad_producto');
    const productoSelect = document.getElementById('producto');

    if (btnAgregar && cantidadInput && productoSelect) {
        btnAgregar.addEventListener('click', function (e) {
            const productoId = productoSelect.value;
            const cantidad = parseInt(cantidadInput.value, 10);
            const option = productoSelect.options[productoSelect.selectedIndex];

            if (!productoId || isNaN(cantidad) || cantidad < 1) {
                alert('Por favor, seleccione un producto y especifique una cantidad válida.');
                return;
            }

            // Evitar duplicados
            const yaExiste = productos.some(p => String(p.codigo) === String(productoId));
            if (yaExiste) {
                const nombre = option.text.split(' - ')[0];
                alert(`El producto "${nombre}" ya fue agregado al detalle.`);
                return;
            }

            const precio = parseFloat(option.getAttribute('data-precio')) || 0;
            const iva = option.getAttribute('data-iva') || 'N/A';
            const ivaId = parseInt(option.getAttribute('data-iva-id')) || 0;
            
            // Calcular subtotal e IVA
            const subtotal = precio * cantidad;
            let ivaCalculado = 0;
            let subtotalConIva = subtotal;
            let ivaPorcentaje = 0;
            
            // Calcular IVA según tasa (si existe)
            if (ivaId > 0 && iva !== 'N/A') {
                // Extraer porcentaje de iva_descri (ej: "IVA_10" -> 10, "IVA_5" -> 5)
                const ivaMatch = iva.match(/(\d+)/);
                if (ivaMatch) {
                    ivaPorcentaje = parseFloat(ivaMatch[1]);
                    ivaCalculado = subtotal * (ivaPorcentaje / 100);
                    subtotalConIva = subtotal + ivaCalculado;
                }
            }

            // Agregar al array
            productos.push({
                codigo: productoId,
                cantidad: cantidad,
                precio: precio,
                iva: iva,
                ivaId: ivaId,
                ivaPorcentaje: ivaPorcentaje,
                ivaCalculado: ivaCalculado,
                subtotal: subtotal,
                subtotalConIva: subtotalConIva
            });

            // Agregar fila
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${productos.length}</td>
                <td>${productoId}</td>
                <td>${option.text.split(' - ')[0]}</td>
                <td>${cantidad}</td>
                <td>${new Intl.NumberFormat('es-PY').format(precio)}</td>
                <td>${iva}</td>
                <td>${new Intl.NumberFormat('es-PY').format(subtotalConIva)}</td>
                <td><button type="button" class="btn btn-danger btn-sm btn-eliminar">Quitar</button></td>
            `;
            tbody.appendChild(row);

            // Limpiar campos
            productoSelect.value = '';
            cantidadInput.value = '';

            calcularTotales();
        });
    }
});
</script>

