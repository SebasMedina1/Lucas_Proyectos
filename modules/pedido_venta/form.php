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
if (isset($_GET['form_pedido_venta']) && $_GET['form'] == 'add') { 
    require "../../config/database.php";
?>
 <div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">
        <i class="fas fa-plus-circle"></i> Registrar Pedido de Venta
    </h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="view.php">Pedidos de Ventas</a></li>
        <li class="breadcrumb-item active">Nuevo Pedido</li>
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
            <form action="proses.php?act=insert" method="POST" id="formPedido" novalidate>
                <?php
                try {
                    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                    $pdo = new PDO($dsn, $user, $pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Obtener el máximo valor de id_pedido_venta
                    $query = $pdo->query("SELECT MAX(id_pedido_venta) AS id FROM pedido_venta");
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
                        <label for="hora" class="form-label">Hora</label>
                        <input type="text" class="form-control" id="hora" name="hora" value="<?= $hora ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label for="codigo" class="form-label">Pedido N°</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?= $codigo ?>" readonly>
                    </div>
                    <div class="col-md-3">
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
                                // Intentar obtener IVA si existe el campo iva_id en productos
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
                                // Si falla por falta de campo iva_id, intentar sin IVA
                                try {
                                    $query_prod = $pdo->query("
                                        SELECT p.producto_id, p.producto_descri, p.producto_precio, 
                                               um.unidad_descri
                                        FROM productos p
                                        JOIN unidad_medida um ON p.id_unidad = um.id_unidad
                                        WHERE p.producto_estado = 'ACTIVO'
                                        ORDER BY p.producto_descri ASC
                                    ");
                                    while ($prod = $query_prod->fetch(PDO::FETCH_ASSOC)) {
                                        $precio = number_format($prod['producto_precio'], 0, ',', '.');
                                        echo "<option value=\"{$prod['producto_id']}\" 
                                                data-precio=\"{$prod['producto_precio']}\" 
                                                data-iva=\"N/A\"
                                                data-iva-id=\"0\"
                                                data-unidad=\"{$prod['unidad_descri']}\">
                                                {$prod['producto_descri']} - Precio: {$precio} - Unidad: {$prod['unidad_descri']}
                                              </option>";
                                    }
                                } catch (PDOException $e2) {
                                    die("Error al cargar productos: " . $e2->getMessage());
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="cantidad_producto" class="form-label">Cantidad</label>
                        <input type="number" class="form-control" id="cantidad_producto" min="1" step="1" placeholder="Ingrese cantidad">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Stock Disponible</label>
                        <input type="text" class="form-control" id="stock_disponible" readonly placeholder="Seleccione producto">
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
                                <td class="font-weight-bold" id="total-pedido">0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <input type="hidden" name="productos" id="productos">

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-success me-2" name="Guardar" id="btn-guardar">Guardar</button>
                    <a href="view.php" class="btn btn-warning me-2">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
} elseif (isset($_GET['form_pedido_venta']) && $_GET['form'] == 'edit') { 
    require "../../config/database.php";

    $pedId = isset($_GET['ped_id']) ? (int)$_GET['ped_id'] : 0;
    if ($pedId <= 0) { 
        header("Location: view.php?alert=4"); 
        exit; 
    }

    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Cabecera
        $cab = $pdo->prepare("
            SELECT pv.id_pedido_venta,
                   pv.pedido_fecha,
                   pv.pedido_estado,
                   pv.id_cliente,
                   c.cliente_nombre || ' ' || c.cliente_apellido AS cliente_nombre,
                   u.username,
                   s.descripcion_sucursal
            FROM pedido_venta pv
            JOIN clientes c ON c.id_cliente = pv.id_cliente
            JOIN usuarios u ON u.id_usuario = pv.id_usuario
            JOIN sucursales s ON s.id_sucursal = pv.id_sucursal
            WHERE pv.id_pedido_venta = :id
            LIMIT 1
        ");
        $cab->execute([':id'=>$pedId]);
        $cabecera = $cab->fetch(PDO::FETCH_ASSOC);
        if (!$cabecera) { 
            header("Location: view.php?alert=4"); 
            exit; 
        }

        // Detalle
        $det = $pdo->prepare("
            SELECT d.producto_id,
                   p.producto_descri,
                   d.cantidad_pedido,
                   p.producto_precio
            FROM detalle_pedido_venta d
            JOIN productos p ON p.producto_id = d.producto_id
            WHERE d.id_pedido_venta = :id
            ORDER BY d.producto_id
        ");
        $det->execute([':id'=>$pedId]);
        $detalles = $det->fetchAll(PDO::FETCH_ASSOC);

        date_default_timezone_set('America/Asuncion');
        $fechaHoy = date('Y-m-d');
        $horaAhora = date('H:i:s');

    } catch (PDOException $e) {
        die("Error al cargar el pedido: ".$e->getMessage());
    }
?>

<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-edit"></i> Editar Pedido de Venta</h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
    <li class="breadcrumb-item"><a href="view.php">Pedidos de Ventas</a></li>
    <li class="breadcrumb-item active">Editar</li>
  </ol>

  <div class="card shadow mb-4">
    <div class="card-body">
      <form action="proses.php?act=update" method="POST">
        <input type="hidden" name="pedido_id" value="<?= (int)$cabecera['id_pedido_venta'] ?>">

        <div class="row mb-3">
          <div class="col-md-3">
            <label class="form-label">Pedido N°</label>
            <input class="form-control" value="<?= (int)$cabecera['id_pedido_venta'] ?>" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label">Fecha</label>
            <input class="form-control" value="<?= $fechaHoy ?>" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label">Hora</label>
            <input class="form-control" value="<?= $horaAhora ?>" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label">Estado</label>
            <input class="form-control" value="<?= htmlspecialchars($cabecera['pedido_estado']) ?>" readonly>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Cliente</label>
            <input class="form-control" value="<?= htmlspecialchars($cabecera['cliente_nombre']) ?>" readonly>
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

        <div class="table-responsive mb-4">
          <table class="table table-bordered table-striped">
            <thead class="table-dark">
              <tr>
                <th>#</th>
                <th>Código</th>
                <th>Producto</th>
                <th style="width:180px">Cantidad</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($detalles): $i=1; foreach ($detalles as $d): ?>
                <tr>
                  <td><?= $i++ ?></td>
                  <td><?= (int)$d['producto_id'] ?></td>
                  <td><?= htmlspecialchars($d['producto_descri']) ?></td>
                  <td>
                    <input type="number"
                           class="form-control cantidad-edit"
                           name="cantidad[<?= (int)$d['producto_id'] ?>]"
                           value="<?= (int)$d['cantidad_pedido'] ?>"
                           min="1" 
                           required>
                  </td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="4" class="text-center">Sin detalles.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-success me-2" name="Guardar">Guardar cambios</button>
          <a href="view.php" class="btn btn-warning">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const inputs = document.querySelectorAll('input.cantidad-edit');
    inputs.forEach(input => {
        input.addEventListener('keydown', e => {
            const noPermitidos = ['e','E','+','-','.',','];
            if (noPermitidos.includes(e.key)) e.preventDefault();
        });
        input.addEventListener('input', e => {
            let v = e.target.value.replace(/\D/g, '');
            v = v.replace(/^0+/, '');
            e.target.value = v;
        });
        input.addEventListener('blur', e => {
            if (e.target.value === '' || parseInt(e.target.value) < 1) {
                e.target.value = '1';
            }
        });
    });
});
</script>

<?php } ?>

<!-- Scripts para formulario de alta -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const productos = [];
    window.productos = productos;
    const tablaProductos = document.getElementById('tabla-productos');
    const tbody = tablaProductos ? tablaProductos.querySelector('tbody') : null;
    const form = document.getElementById('formPedido');

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
    });

    // Función para calcular totales
    function calcularTotales() {
        let subtotal = 0;
        let totalIva = 0;
        let total = 0;
        
        productos.forEach(p => {
            subtotal += p.subtotal;
            totalIva += p.ivaCalculado || 0;
            total += p.subtotalConIva || p.subtotal;
        });
        
        const totalEl = document.getElementById('total-pedido');
        if (totalEl) {
            // Mostrar total con IVA incluido
            totalEl.textContent = new Intl.NumberFormat('es-PY').format(total);
        }
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
    const stockInput = document.getElementById('stock_disponible');

    // Cargar stock cuando se selecciona un producto
    if (productoSelect && stockInput) {
        productoSelect.addEventListener('change', function() {
            const productoId = this.value;
            if (productoId) {
                // Obtener stock disponible (opcional - puede no existir stock para todos los productos)
                fetch(`get_stock.php?producto_id=${productoId}`)
                    .then(resp => resp.json())
                    .then(data => {
                        if (data.stock_existencia !== undefined) {
                            stockInput.value = data.stock_existencia;
                            stockInput.style.backgroundColor = data.stock_existencia > 0 ? '#d4edda' : '#f8d7da';
                        } else {
                            stockInput.value = 'N/A';
                            stockInput.style.backgroundColor = '#fff';
                        }
                    })
                    .catch(() => {
                        stockInput.value = 'N/A';
                        stockInput.style.backgroundColor = '#fff';
                    });
            } else {
                stockInput.value = '';
                stockInput.style.backgroundColor = '#fff';
            }
        });
    }

    if (btnAgregar && cantidadInput && productoSelect) {
        btnAgregar.addEventListener('click', function (e) {
            const productoId = productoSelect.value;
            const cantidad = parseInt(cantidadInput.value, 10);
            const option = productoSelect.options[productoSelect.selectedIndex];

            if (!productoId || isNaN(cantidad) || cantidad < 1) {
                alert('Por favor, seleccione un producto y especifique una cantidad válida.');
                return;
            }

            // Validar stock disponible (opcional - solo si hay stock configurado)
            if (stockInput && stockInput.value !== '' && stockInput.value !== 'N/A') {
                const stockDisponible = parseInt(stockInput.value, 10);
                if (!isNaN(stockDisponible) && cantidad > stockDisponible) {
                    if (!confirm(`La cantidad solicitada (${cantidad}) excede el stock disponible (${stockDisponible}). ¿Desea continuar de todas formas?`)) {
                        return;
                    }
                }
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
            
            // Calcular IVA según tasa (si existe)
            if (ivaId > 0 && iva !== 'N/A') {
                // Extraer porcentaje de iva_descri (ej: "IVA_10" -> 10, "IVA_5" -> 5)
                const ivaMatch = iva.match(/(\d+)/);
                if (ivaMatch) {
                    const porcentajeIva = parseFloat(ivaMatch[1]) / 100;
                    ivaCalculado = subtotal * porcentajeIva;
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

