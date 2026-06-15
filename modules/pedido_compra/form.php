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
if (isset($_GET['form_pedido_compra']) && $_GET['form'] == 'add') { ?>
 <div class="container-fluid">
    <!-- Encabezado de página -->
    <h1 class="h3 mb-4 text-gray-800">
        <i class="fas fa-plus-circle"></i> Registrar Pedido
    </h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="view.php">Pedidos de Compras</a></li>
        <li class="breadcrumb-item active">Nuevo Pedido</li>
    </ol>

    <!-- Modal para producto duplicado -->
    <div class="modal fade" id="modalAviso" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Producto duplicado</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body"><p></p></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-primary" data-dismiss="modal">Entendido</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal para stock máximo excedido -->
    <div class="modal fade" id="modalStockMaximo" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title">
              <i class="fas fa-exclamation-triangle"></i> Stock Máximo Excedido
            </h5>
            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p class="mb-2"><strong id="modal-materia-prima"></strong></p>
            <p class="mb-1">No se puede solicitar la cantidad indicada ya que la materia prima tiene un tope de stock.</p>
            <div class="alert alert-info mt-3 mb-0">
              <strong>Cupo disponible:</strong> <span id="modal-cupo-disponible"></span> unidades
            </div>
            <hr>
            <small class="text-muted">
              <strong>Stock actual:</strong> <span id="modal-stock-actual"></span> unidades<br>
              <strong>Stock máximo:</strong> <span id="modal-stock-maximo"></span> unidades<br>
              <strong>Cantidad solicitada:</strong> <span id="modal-cantidad-solicitada"></span> unidades
            </small>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-primary" data-dismiss="modal">Entendido</button>
          </div>
        </div>
      </div>
    </div>


    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="proses.php?act=insert" method="POST">
                <!-- Información general -->
                <?php
                            try {
                                require "../../config/database.php";

                                // Crear conexión con PostgreSQL usando PDO
                                $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                                $pdo = new PDO($dsn, $user, $pass);
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                                // Obtener el máximo valor de cod_compra
                                $query = $pdo->query("SELECT MAX(id_pedido_compra) AS id FROM pedidos_compra");
                                $data = $query->fetch(PDO::FETCH_ASSOC);

                                // Generar nuevo código incrementado
                                $codigo = ($data['id'] !== null) ? $data['id'] + 1 : 1;

                                date_default_timezone_set('America/Asuncion');
                                // Obtener fecha y hora actuales
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
                                // Manejar errores de conexión o consulta
                                die("Error al obtener los datos: " . $e->getMessage());
                            }
                            ?>
                <div class="row mb-3">
                    <div class="col-md-2">
                        <label for="fecha" class="form-label">Fecha</label>
                        <input type="text" class="form-control" id="fecha" name="fecha" value="<?php echo $fecha; ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label for="hora" class="form-label">Hora</label>
                        <input type="text" class="form-control" id="hora" name="hora" value="<?php echo $hora; ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="codigo" class="form-label">Pedido N°</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo $codigo; ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="usuario" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="usuario" name="username" value="<?php echo $usuarioNombre; ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="sucursal" class="form-label">Sucursal</label>
                        <input type="text" class="form-control" id="sucursal" name="sucursal" value="<?php echo $sucursalNombre; ?>" readonly>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="observaciones" class="form-label">Observaciones (Opcional)</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="2" placeholder="Ingrese observaciones o notas adicionales"></textarea>
                    </div>
                </div>

                <!-- Detalle -->
                <div class="row align-items-end mb-3">
                    <div class="col-md-6">
                        <label for="producto" class="form-label">Producto</label>
                        
                        <select class="form-control select2" id="producto" name="producto"  >
                            <option value="" selected>Seleccione un Producto</option>
                            <?php
                            try {
                                $query_dep = $pdo->query("SELECT id_materia_prima, materia_prima_descripcion FROM materia_prima WHERE materia_prima_estado = 'ACTIVO' ORDER BY id_materia_prima ASC");
                                while ($data_dep = $query_dep->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"{$data_dep['id_materia_prima']}\">{$data_dep['materia_prima_descripcion']}</option>";
                                }
                            } catch (PDOException $e) {
                                die("Error en la conexión o consulta: " . $e->getMessage());
                            }
                            ?>
                        </select>


                    </div>
                    
                        <!-- -->



                    <div class="col-md-4">
                        <label for="cantidad_producto" class="form-label">Cantidad</label>
                        <input type="number" class="form-control" id="cantidad_producto" min="1"  placeholder="Ingrese cantidad" >
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-primary w-100" id="btn-agregar" >Agregar</button>
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
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>

                <input type="hidden" name="productos" id="productos">

                <!-- Botones -->
                <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-success me-2" name="Guardar" id="btn-guardar" >Guardar</button>
                    <a href="view.php" class="btn btn-warning me-2">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
} 
    
if (isset($_GET['form_pedido_compra']) && $_GET['form'] == 'edit') { 
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require "../../config/database.php";

$pedId = isset($_GET['ped_id']) ? (int)$_GET['ped_id'] : 0;
if ($pedId <= 0) { header("Location: view.php?alert=4"); exit; }

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Cabecera
    $cab = $pdo->prepare("
        SELECT pc.id_pedido_compra,
               pc.pedido_fecha_emision,
               pc.pedido_estado,
               pc.pedido_observaciones,
               pc.pedido_ultima_modificacion,
               u.username,
               s.descripcion_sucursal
        FROM pedidos_compra pc
        JOIN usuarios u ON u.id_usuario = pc.id_usuario
        JOIN sucursales s ON s.id_sucursal = pc.id_sucursal
        WHERE pc.id_pedido_compra = :id
        LIMIT 1
    ");
    $cab->execute([':id'=>$pedId]);
    $cabecera = $cab->fetch(PDO::FETCH_ASSOC);
    if (!$cabecera) { header("Location: view.php?alert=4"); exit; }

    // Detalle
    $det = $pdo->prepare("
        SELECT d.id_materia_prima,
               mp.materia_prima_descripcion,
               d.cantidad_pedido
        FROM pedido_detalle_compra d
        JOIN materia_prima mp ON mp.id_materia_prima = d.id_materia_prima
        WHERE d.id_pedido_compra = :id
        ORDER BY d.id_materia_prima
    ");
    $det->execute([':id'=>$pedId]);
    $detalles = $det->fetchAll(PDO::FETCH_ASSOC);

    
    date_default_timezone_set('America/Asuncion');  // zona horaria
    $fechaHoy = date('Y-m-d');
    $horaAhora = date('H:i:s');



} catch (PDOException $e) {
    die("Error al cargar el pedido: ".$e->getMessage());
}
?>

<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-edit"></i> Editar Pedido</h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
    <li class="breadcrumb-item"><a href="view.php">Pedidos de Compras</a></li>
    <li class="breadcrumb-item active">Editar</li>
  </ol>

  <!-- Modal para stock máximo excedido (edición) -->
  <div class="modal fade" id="modalStockMaximo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title">
            <i class="fas fa-exclamation-triangle"></i> Stock Máximo Excedido
          </h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p class="mb-2"><strong id="modal-materia-prima"></strong></p>
          <p class="mb-1">No se puede solicitar la cantidad indicada ya que la materia prima tiene un tope de stock.</p>
          <div class="alert alert-info mt-3 mb-0">
            <strong>Cupo disponible:</strong> <span id="modal-cupo-disponible"></span> unidades
          </div>
          <hr>
          <small class="text-muted">
            <strong>Stock actual:</strong> <span id="modal-stock-actual"></span> unidades<br>
            <strong>Stock máximo:</strong> <span id="modal-stock-maximo"></span> unidades<br>
            <strong>Cantidad solicitada:</strong> <span id="modal-cantidad-solicitada"></span> unidades
          </small>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" data-dismiss="modal">Entendido</button>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow mb-4">
    <div class="card-body">

      <!-- Importante: enviar a UPDATE -->
      <form action="proses.php?act=update" method="POST">
        <input type="hidden" name="pedido_id" value="<?= (int)$cabecera['id_pedido_compra'] ?>">

        <div class="row mb-3">
          <div class="col-md-3">
            <label class="form-label">Pedido N°</label>
            <input class="form-control" value="<?= (int)$cabecera['id_pedido_compra'] ?>" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label">Fecha</label>
            <input class="form-control"
                   value="<?= $fechaHoy ?>" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label">Hora</label>
            <input class="form-control"
                   value="<?= $horaAhora ?>" readonly>
          </div>

        </div>

        <div class="row mb-3">
          <div class="col-md-3">
            <label class="form-label">Estado</label>
            <input class="form-control" value="<?= htmlspecialchars($cabecera['pedido_estado']) ?>" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label">Usuario</label>
            <input class="form-control" value="<?= htmlspecialchars($cabecera['username']) ?>" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label">Sucursal</label>
            <input class="form-control" value="<?= htmlspecialchars($cabecera['descripcion_sucursal']) ?>" readonly>
          </div>
          <div class="col-md-3">
            <!-- Espacio vacío para mantener el layout -->
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-12">
            <label class="form-label">Observaciones</label>
            <textarea class="form-control" name="observaciones" rows="2" placeholder="Ingrese observaciones o notas adicionales"><?= htmlspecialchars($cabecera['pedido_observaciones'] ?? '') ?></textarea>
          </div>
        </div>
        <input type="hidden" name="pedido_ultima_modificacion" value="<?= htmlspecialchars($cabecera['pedido_ultima_modificacion'] ?? '') ?>">

        <!-- Editar detalle: permitir agregar/quitar/modificar -->
        <div class="row align-items-end mb-3">
          <div class="col-md-6">
            <label for="producto_edit" class="form-label">Agregar Producto</label>
            <select class="form-control" id="producto_edit" name="producto_edit">
              <option value="">Seleccione un Producto</option>
              <?php
              try {
                  $query_mp = $pdo->query("SELECT id_materia_prima, materia_prima_descripcion FROM materia_prima WHERE materia_prima_estado = 'ACTIVO' ORDER BY materia_prima_descripcion ASC");
                  while ($data_mp = $query_mp->fetch(PDO::FETCH_ASSOC)) {
                      echo "<option value=\"{$data_mp['id_materia_prima']}\">{$data_mp['materia_prima_descripcion']}</option>";
                  }
              } catch (PDOException $e) {
                  die("Error en la conexión o consulta: " . $e->getMessage());
              }
              ?>
            </select>
          </div>
          <div class="col-md-4">
            <label for="cantidad_edit" class="form-label">Cantidad</label>
            <input type="number" class="form-control" id="cantidad_edit" min="1" placeholder="Ingrese cantidad">
          </div>
          <div class="col-md-2">
            <button type="button" class="btn btn-primary w-100" id="btn-agregar-edit">Agregar</button>
          </div>
        </div>

        <div class="table-responsive mb-4">
          <table class="table table-bordered table-striped" id="tabla-detalle-edit">
            <thead class="table-dark">
              <tr>
                <th>#</th>
                <th>Código</th>
                <th>Producto</th>
                <th style="width:180px">Cantidad</th>
                <th style="width:100px">Acción</th>
              </tr>
            </thead>
            <tbody id="tbody-detalle-edit">
              <?php if ($detalles): $i=1; foreach ($detalles as $d): ?>
                <tr data-materia-prima="<?= (int)$d['id_materia_prima'] ?>">
                  <td><?= $i++ ?></td>
                  <td><?= (int)$d['id_materia_prima'] ?></td>
                  <td><?= htmlspecialchars($d['materia_prima_descripcion']) ?></td>
                  <td>
                    <input type="number"
                           class="form-control cantidad-edit"
                           name="cantidad[<?= (int)$d['id_materia_prima'] ?>]"
                           value="<?= (int)$d['cantidad_pedido'] ?>"
                           min="1" required>
                  </td>
                  <td>
                    <button type="button" class="btn btn-danger btn-sm btn-quitar-edit" title="Quitar">
                      <i class="fas fa-times"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="5" class="text-center">Sin detalles.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <input type="hidden" name="productos_eliminados" id="productos_eliminados" value="">
        <input type="hidden" name="productos_nuevos" id="productos_nuevos" value="">

        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-success me-2" name="Guardar">Guardar cambios</button>
          <a href="view.php" class="btn btn-warning">Cancelar</a>
        </div>
      </form>

    </div>
  </div>
</div>
    <?php } ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
    const inputs = document.querySelectorAll('input[name^="cantidad["]');

    inputs.forEach(input => {
        // Bloquear teclas no deseadas
        input.addEventListener('keydown', e => {
        const noPermitidos = ['e','E','+','-','.',','];
        if (noPermitidos.includes(e.key)) e.preventDefault();
        });

        // Limpiar mientras escribe, permitiendo vacío temporal
        input.addEventListener('input', e => {
        let v = e.target.value;

        // eliminar todo lo que no sea dígito
        v = v.replace(/\D/g, '');

        // quitar ceros a la izquierda (pero permitir vacío)
        v = v.replace(/^0+/, '');

        e.target.value = v; // puede quedar '' mientras escribe
        });

        // Al salir del campo, forzar mínimo 1
        input.addEventListener('blur', e => {
        if (e.target.value === '') e.target.value = '1';
        });
    });
    });
</script>





<!-- Scripts -->
<script>

    

    const productos = [];
    window.productos = productos;
    const tablaProductos = document.getElementById('tabla-productos').querySelector('tbody');

    const form = document.querySelector('form[action^="proses.php"]');

    if (form) {
    form.addEventListener('submit', function (e) {
        // Método A: usando tu arreglo
        const noHayProductos = (productos.length === 0);

        // Método B: por si querés confiar en el DOM
        // const noHayProductos = (tablaProductos.children.length === 0);

        if (noHayProductos) {
        e.preventDefault(); // cancela el submit
        alert('Por favor, agregar como mínimo un producto');
        }
    });
    }


    // Eliminar producto de la tabla y del arreglo
    tablaProductos.addEventListener('click', function (event) {
        if (event.target.classList.contains('btn-eliminar')) {
            const row = event.target.closest('tr');
            const index = Array.from(tablaProductos.children).indexOf(row);

            // Quitar el producto del arreglo
            productos.splice(index, 1);
            row.remove();

            // Actualizar el índice de la tabla
            Array.from(tablaProductos.children).forEach((row, idx) => {
                row.children[0].innerText = idx + 1;
            });

            document.getElementById('productos').value = JSON.stringify(productos);
        }
    });

    // vaidación para el boton agregar
    const btnAgregar = document.getElementById('btn-agregar');
    const cantidadProductoInput = document.getElementById('cantidad_producto');

    // Validar que la cantidad no sea menor a 1
    cantidadProductoInput.addEventListener('input', function () {
        const cantidad = parseInt(this.value);
        if (isNaN(cantidad) || cantidad < 1) {
            btnAgregar.disabled = true;
        } else {
            btnAgregar.disabled = false;
        }
    });

    // Validaci�n en el evento click del bot�n "Agregar"
    btnAgregar.addEventListener('click', function (e) {
        const producto = document.getElementById('producto');
        const cantidadProducto = parseInt(cantidadProductoInput.value, 10);

        // Validaciones b�sicas
        if (!producto.value || isNaN(cantidadProducto) || cantidadProducto < 1) {
            alert('Por favor, seleccione un producto y especifique una cantidad válida.');
            return;
        }

        // Validar stock máximo antes de agregar
        const formData = new FormData();
        formData.append('id_materia_prima', producto.value);
        formData.append('cantidad_pedido', cantidadProducto);

        // Deshabilitar botón mientras se valida
        btnAgregar.disabled = true;
        const textoOriginal = btnAgregar.textContent;
        btnAgregar.textContent = 'Validando...';

        fetch('validar_stock_maximo.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            btnAgregar.disabled = false;
            btnAgregar.textContent = textoOriginal;

            if (data.error) {
                alert('Error al validar stock: ' + data.error);
                return;
            }

            // Si supera el máximo, mostrar modal y no agregar
            if (data.supera_maximo && data.tiene_limite) {
                mostrarModalStockMaximo(data);
                return;
            }

            // Consolidar duplicados: si ya existe, validar y sumar cantidades
            const productoExistente = productos.find(p => String(p.codigo) === String(producto.value));
            if (productoExistente) {
                const nombre = producto.options[producto.selectedIndex]?.text || producto.value;
                const cantidadAnterior = productoExistente.cantidad;
                const cantidadNueva = cantidadProducto;
                const cantidadTotal = cantidadAnterior + cantidadNueva;
                
                // Validar que la cantidad total no supere el máximo
                const stockTotalConExistente = data.stock_actual + cantidadTotal;
                if (data.tiene_limite && stockTotalConExistente > data.stock_maximo) {
                    const cupoDisponible = data.stock_maximo - data.stock_actual;
                    mostrarModalStockMaximo({
                        ...data,
                        cantidad_pedido: cantidadTotal,
                        stock_total_calculado: stockTotalConExistente,
                        cupo_disponible: cupoDisponible
                    });
                    return;
                }
                
                // Actualizar cantidad en el arreglo
                productoExistente.cantidad = cantidadTotal;
                
                // Actualizar cantidad en la tabla
                const filas = tablaProductos.querySelectorAll('tr');
                filas.forEach(fila => {
                    const codigoFila = fila.querySelector('td:nth-child(2)').textContent.trim();
                    if (codigoFila === producto.value) {
                        fila.querySelector('td:nth-child(4)').textContent = cantidadTotal;
                    }
                });
                
                // Actualizar JSON hidden
                document.getElementById('productos').value = JSON.stringify(productos);
                
                // Limpiar campos
                producto.value = '';
                cantidadProductoInput.value = '';
                btnAgregar.disabled = true;
                
                // Mostrar mensaje informativo
                if (typeof showAlertModal === 'function') {
                    showAlertModal(`El producto "${nombre}" ya estaba en el detalle. Se sumó la cantidad: ${cantidadAnterior} + ${cantidadNueva} = ${cantidadTotal}`);
                } else {
                    alert(`El producto "${nombre}" ya estaba en el detalle. Se sumó la cantidad: ${cantidadAnterior} + ${cantidadNueva} = ${cantidadTotal}`);
                }
                return;
            }

            // Agregar fila a la tabla
            const row = `
                <tr>
                    <td>${productos.length + 1}</td>
                    <td>${producto.value}</td>
                    <td>${producto.options[producto.selectedIndex].text}</td>
                    <td>${cantidadProducto}</td>
                    <td><button type="button" class="btn btn-danger btn-sm btn-eliminar">Quitar</button></td>
                </tr>`;
            tablaProductos.innerHTML += row;

            // Agregar producto al arreglo
            productos.push({ codigo: producto.value, cantidad: cantidadProducto });
            document.getElementById('productos').value = JSON.stringify(productos);

            // Limpiar campos y deshabilitar
            producto.value = '';
            cantidadProductoInput.value = '';
            btnAgregar.disabled = true;
        })
        .catch(error => {
            btnAgregar.disabled = false;
            btnAgregar.textContent = textoOriginal;
            console.error('Error:', error);
            alert('Error al validar stock. Por favor, intente nuevamente.');
        });
    });

    // Función para validar stock máximo antes de agregar
    function validarYAgregarProducto(productoId, cantidad, callbackExito) {
        const formData = new FormData();
        formData.append('id_materia_prima', productoId);
        formData.append('cantidad_pedido', cantidad);

        return fetch('validar_stock_maximo.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            return data;
        });
    }

    // Función para mostrar modal de stock máximo
    function mostrarModalStockMaximo(data) {
        const modal = document.getElementById('modalStockMaximo');
        if (!modal) {
            alert(`No se puede solicitar ${data.cantidad_pedido} unidades de "${data.materia_prima}". Cupo disponible: ${data.cupo_disponible} unidades.`);
            return;
        }

        document.getElementById('modal-materia-prima').textContent = data.materia_prima;
        document.getElementById('modal-stock-actual').textContent = data.stock_actual.toLocaleString();
        document.getElementById('modal-stock-maximo').textContent = data.stock_maximo.toLocaleString();
        document.getElementById('modal-cantidad-solicitada').textContent = data.cantidad_pedido.toLocaleString();
        document.getElementById('modal-cupo-disponible').textContent = data.cupo_disponible !== null ? data.cupo_disponible.toLocaleString() : 'Sin límite';

        // Mostrar modal con jQuery (Bootstrap 4) o Bootstrap 5
        if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
            jQuery(modal).modal('show');
        } else if (window.bootstrap && window.bootstrap.Modal) {
            const bsModal = new window.bootstrap.Modal(modal);
            bsModal.show();
        } else {
            modal.style.display = 'block';
            modal.classList.add('show');
        }
    }


        // Modal de aviso reutilizable (Bootstrap o fallback)
        function showAlertModal(message) {
        const modalEl = document.getElementById('modalAviso');
        if (modalEl) {
            const title = modalEl.querySelector('.modal-title');
            const body = modalEl.querySelector('.modal-body');
            if (title) title.textContent = 'Producto duplicado';
            if (body) body.innerHTML = `<p>${message}</p>`;
            if (window.bootstrap?.Modal) new bootstrap.Modal(modalEl).show();
            else if (typeof showFallbackModal === 'function') showFallbackModal(message);
            else alert(message);
        } else {
            // Si no insertaste el HTML del modal, usa fallback
            if (typeof showFallbackModal === 'function') showFallbackModal(message);
            else alert(message);
        }
        }



</script>

<!-- JavaScript para formulario de edición -->
<?php if (isset($_GET['form_pedido_compra']) && $_GET['form'] == 'edit'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.getElementById('tbody-detalle-edit');
    if (!tbody) return;
    
    const productoSelect = document.getElementById('producto_edit');
    const cantidadInput = document.getElementById('cantidad_edit');
    const btnAgregar = document.getElementById('btn-agregar-edit');
    const productosEliminados = [];
    const productosNuevos = [];
    let contador = tbody.querySelectorAll('tr').length;

    // Obtener todas las materias primas disponibles
    const materiasPrimasDisponibles = [];
    <?php
    try {
        $query_mp_all = $pdo->query("SELECT id_materia_prima, materia_prima_descripcion FROM materia_prima WHERE materia_prima_estado = 'ACTIVO' ORDER BY materia_prima_descripcion ASC");
        while ($data_mp_all = $query_mp_all->fetch(PDO::FETCH_ASSOC)) {
            echo "materiasPrimasDisponibles.push({id: {$data_mp_all['id_materia_prima']}, nombre: '" . addslashes($data_mp_all['materia_prima_descripcion']) . "'});\n";
        }
    } catch (PDOException $e) {
        // Ignorar error
    }
    ?>

    function getNombreMateriaPrima(id) {
        const mp = materiasPrimasDisponibles.find(m => m.id == id);
        return mp ? mp.nombre : 'Producto #' + id;
    }

    // Función para mostrar modal de stock máximo (edición)
    function mostrarModalStockMaximoEdit(data) {
        const modal = document.getElementById('modalStockMaximo');
        if (!modal) {
            alert(`No se puede solicitar ${data.cantidad_pedido} unidades de "${data.materia_prima}". Cupo disponible: ${data.cupo_disponible} unidades.`);
            return;
        }

        document.getElementById('modal-materia-prima').textContent = data.materia_prima;
        document.getElementById('modal-stock-actual').textContent = data.stock_actual.toLocaleString();
        document.getElementById('modal-stock-maximo').textContent = data.stock_maximo.toLocaleString();
        document.getElementById('modal-cantidad-solicitada').textContent = data.cantidad_pedido.toLocaleString();
        document.getElementById('modal-cupo-disponible').textContent = data.cupo_disponible !== null ? data.cupo_disponible.toLocaleString() : 'Sin límite';

        // Mostrar modal con jQuery (Bootstrap 4) o Bootstrap 5
        if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
            jQuery(modal).modal('show');
        } else if (window.bootstrap && window.bootstrap.Modal) {
            const bsModal = new window.bootstrap.Modal(modal);
            bsModal.show();
        } else {
            modal.style.display = 'block';
            modal.classList.add('show');
        }
    }

    // Agregar nuevo ítem
    if (btnAgregar) {
        btnAgregar.addEventListener('click', function() {
            const productoId = productoSelect.value;
            const cantidad = parseInt(cantidadInput.value);

            if (!productoId || isNaN(cantidad) || cantidad < 1) {
                alert('Por favor, seleccione un producto y especifique una cantidad válida.');
                return;
            }

            // Validar stock máximo antes de agregar
            const formData = new FormData();
            formData.append('id_materia_prima', productoId);
            formData.append('cantidad_pedido', cantidad);

            // Deshabilitar botón mientras se valida
            btnAgregar.disabled = true;
            const textoOriginal = btnAgregar.textContent;
            btnAgregar.textContent = 'Validando...';

            fetch('validar_stock_maximo.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btnAgregar.disabled = false;
                btnAgregar.textContent = textoOriginal;

                if (data.error) {
                    alert('Error al validar stock: ' + data.error);
                    return;
                }

                // Verificar si ya existe
                const existe = Array.from(tbody.querySelectorAll('tr')).some(tr => {
                    return tr.getAttribute('data-materia-prima') == productoId;
                });

                if (existe) {
                    // Sumar cantidad y validar
                    const fila = Array.from(tbody.querySelectorAll('tr')).find(tr => {
                        return tr.getAttribute('data-materia-prima') == productoId;
                    });
                    const inputCantidad = fila.querySelector('.cantidad-edit');
                    const cantidadAnterior = parseInt(inputCantidad.value) || 0;
                    const cantidadTotal = cantidadAnterior + cantidad;
                    
                    // Validar que la cantidad total no supere el máximo
                    const stockTotalConExistente = data.stock_actual + cantidadTotal;
                    if (data.tiene_limite && stockTotalConExistente > data.stock_maximo) {
                        const cupoDisponible = data.stock_maximo - data.stock_actual;
                        mostrarModalStockMaximoEdit({
                            ...data,
                            cantidad_pedido: cantidadTotal,
                            stock_total_calculado: stockTotalConExistente,
                            cupo_disponible: cupoDisponible
                        });
                        return;
                    }
                    
                    inputCantidad.value = cantidadTotal;
                    alert(`El producto ya estaba en el detalle. Se sumó la cantidad: ${cantidadAnterior} + ${cantidad} = ${cantidadTotal}`);
                } else {
                    // Si supera el máximo, mostrar modal y no agregar
                    if (data.supera_maximo && data.tiene_limite) {
                        mostrarModalStockMaximoEdit(data);
                        return;
                    }

                    // Agregar nueva fila
                    contador++;
                    const nuevaFila = document.createElement('tr');
                    nuevaFila.setAttribute('data-materia-prima', productoId);
                    nuevaFila.innerHTML = `
                        <td>${contador}</td>
                        <td>${productoId}</td>
                        <td>${getNombreMateriaPrima(productoId)}</td>
                        <td>
                            <input type="number" class="form-control cantidad-edit" 
                                   name="cantidad[${productoId}]" value="${cantidad}" min="1" required>
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm btn-quitar-edit" title="Quitar">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(nuevaFila);

                    // Agregar a productos nuevos si no estaba en el detalle original
                    const estabaEnOriginal = <?= json_encode(array_column($detalles ?? [], 'id_materia_prima')) ?>;
                    if (!estabaEnOriginal.includes(parseInt(productoId))) {
                        productosNuevos.push(parseInt(productoId));
                        document.getElementById('productos_nuevos').value = JSON.stringify(productosNuevos);
                    }
                }

                productoSelect.value = '';
                cantidadInput.value = '';
            })
            .catch(error => {
                btnAgregar.disabled = false;
                btnAgregar.textContent = textoOriginal;
                console.error('Error:', error);
                alert('Error al validar stock. Por favor, intente nuevamente.');
            });
        });
    }

    // Quitar ítem
    tbody.addEventListener('click', function(e) {
        if (e.target.closest('.btn-quitar-edit')) {
            const fila = e.target.closest('tr');
            const materiaPrimaId = parseInt(fila.getAttribute('data-materia-prima'));

            const estabaEnOriginal = <?= json_encode(array_column($detalles ?? [], 'id_materia_prima')) ?>;
            if (estabaEnOriginal.includes(materiaPrimaId)) {
                if (!productosEliminados.includes(materiaPrimaId)) {
                    productosEliminados.push(materiaPrimaId);
                    document.getElementById('productos_eliminados').value = JSON.stringify(productosEliminados);
                }
            } else {
                const index = productosNuevos.indexOf(materiaPrimaId);
                if (index > -1) {
                    productosNuevos.splice(index, 1);
                    document.getElementById('productos_nuevos').value = JSON.stringify(productosNuevos);
                }
            }

            fila.remove();
            Array.from(tbody.querySelectorAll('tr')).forEach((tr, idx) => {
                tr.querySelector('td:first-child').textContent = idx + 1;
            });
            contador = tbody.querySelectorAll('tr').length;
        }
    });

    // Validar que haya al menos un ítem y validar stock máximo antes de guardar
    const formEdit = document.querySelector('form[action="proses.php?act=update"]');
    if (formEdit) {
        let validacionCompletada = false;
        let submitHandler = null;
        
        submitHandler = function(e) {
            // Si la validación ya pasó, permitir envío normal
            if (validacionCompletada) {
                return true;
            }

            const filas = tbody.querySelectorAll('tr');
            
            // Validar que haya al menos un ítem
            if (filas.length === 0) {
                e.preventDefault();
                alert('El pedido debe tener al menos un producto.');
                return false;
            }

            // Prevenir envío hasta validar stock
            e.preventDefault();

            // Validar stock máximo para todos los productos
            const validaciones = [];
            const submitButton = formEdit.querySelector('button[type="submit"]');
            const textoOriginal = submitButton ? submitButton.textContent : '';
            
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Validando...';
            }

            // Recorrer todas las filas y validar stock
            filas.forEach((fila, index) => {
                const materiaPrimaId = fila.getAttribute('data-materia-prima');
                const inputCantidad = fila.querySelector('.cantidad-edit');
                const cantidad = parseInt(inputCantidad ? inputCantidad.value : 0);

                if (materiaPrimaId && cantidad > 0) {
                    const formData = new FormData();
                    formData.append('id_materia_prima', materiaPrimaId);
                    formData.append('cantidad_pedido', cantidad);

                    validaciones.push(
                        fetch('validar_stock_maximo.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.error) {
                                throw new Error(`Error al validar ${materiaPrimaId}: ${data.error}`);
                            }
                            
                            // Si supera el máximo, lanzar error
                            if (data.supera_maximo && data.tiene_limite) {
                                return {
                                    error: true,
                                    materia_prima: data.materia_prima,
                                    stock_actual: data.stock_actual,
                                    stock_maximo: data.stock_maximo,
                                    cantidad_pedido: data.cantidad_pedido,
                                    cupo_disponible: data.cupo_disponible
                                };
                            }
                            
                            return { error: false };
                        })
                    );
                }
            });

            // Si no hay validaciones que hacer, permitir envío directamente
            if (validaciones.length === 0) {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = textoOriginal;
                }
                validacionCompletada = true;
                formEdit.removeEventListener('submit', submitHandler);
                formEdit.submit();
                return false;
            }

            // Esperar a que todas las validaciones terminen
            Promise.all(validaciones)
                .then(resultados => {
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = textoOriginal;
                    }

                    // Buscar si hay algún error
                    const errores = resultados.filter(r => r.error);
                    
                    if (errores.length > 0) {
                        // Mostrar el primer error en el modal
                        const primerError = errores[0];
                        mostrarModalStockMaximoEdit(primerError);
                        return;
                    }

                    // Si todo está bien, marcar como validado y enviar
                    validacionCompletada = true;
                    formEdit.removeEventListener('submit', submitHandler);
                    formEdit.submit();
                })
                .catch(error => {
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = textoOriginal;
                    }
                    console.error('Error:', error);
                    alert('Error al validar stock. Por favor, intente nuevamente.');
                });

            return false;
        };
        
        formEdit.addEventListener('submit', submitHandler);
    }
});
</script>
<?php endif; ?>

<!-- Bloqueo de guiones para formulario de alta (insert) -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  const formInsert = document.querySelector('form[action="proses.php?act=insert"]');
  if (!formInsert) return;

  // Evitar teclear '-'
  formInsert.addEventListener('keydown', (e) => {
    if (e.key === '-') {
      e.preventDefault();
    }
  });

  // Limpiar guiones en pegado/edición de inputs de texto
  const sanitize = (el) => {
    if (el && el.tagName === 'INPUT') {
      const type = (el.getAttribute('type') || 'text').toLowerCase();
      if (['text','search','tel','email','password'].includes(type)) {
        el.value = el.value.replace(/-/g, '');
      }
    }
  };

  formInsert.addEventListener('input', (e) => sanitize(e.target));

  formInsert.addEventListener('paste', (e) => {
    const t = e.target;
    if (t && t.tagName === 'INPUT') {
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text');
      const sanitized = String(text || '').replace(/-/g, '');
      // insert sanitized text at cursor
      if (document.execCommand) {
        document.execCommand('insertText', false, sanitized);
      } else if (typeof t.setRangeText === 'function') {
        const start = t.selectionStart || 0;
        const end = t.selectionEnd || 0;
        t.setRangeText(sanitized, start, end, 'end');
      } else {
        t.value = (t.value || '') + sanitized;
      }
    }
  });
});
</script>



