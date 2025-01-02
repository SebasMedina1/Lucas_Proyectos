<?php 
// Verificar si existe el parámetro 'form' en la URL
if (isset($_GET['gestionar_compras']) && $_GET['form'] == 'add') { ?>
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">
        <i class="fas fa-plus-circle"></i> Registrar Compra
    </h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="view.php">Gestionar compras</a></li>
        <li class="breadcrumb-item active">Nueva factura</li>
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

                    $query = $pdo->query("SELECT MAX(fact_id) AS id FROM facturas_compra");
                    $data = $query->fetch(PDO::FETCH_ASSOC);

                    $codigo = ($data['id'] !== null) ? $data['id'] + 1 : 1;
                    date_default_timezone_set('America/Asuncion');
                    $fecha = date("Y-m-d"); // Formato: YYYY-MM-DD (año:mes:dia)
                    $hora = date("h:i A"); // Formato: hh:mm:ss AM/PM (hora:minutos)
                } catch (PDOException $e) {
                    die("Error: " . $e->getMessage());
                }
                ?>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="fecha" class="form-label">Fecha</label>
                        <input type="text" class="form-control" id="fecha" name="fecha" value="<?php echo $fecha; ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="hora" class="form-label">Hora</label>
                        <input type="text" class="form-control" id="hora" name="hora" value="<?php echo $hora; ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="codigo" class="form-label">Factura ID</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo $codigo; ?>" readonly>
                    </div>
                </div>

                <!-- Campos adicionales -->
                <div class="row mb-3">
                    <!-- Campo para seleccionar el tipo de factura -->
                    <div class="col-md-4">
                        <label for="tipo_factura" class="form-label">Tipo de Factura</label>
                        <select class="form-control" id="tipo_factura" name="tipo_factura" required>
                            <option value="" selected>Seleccione el tipo de factura</option>
                            <?php
                            $query_tipo_factura = $pdo->query("SELECT tipo_id, tipo_descripcion FROM tipo_factura ORDER BY tipo_id ASC");
                            while ($tipo_factura = $query_tipo_factura->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value=\"{$tipo_factura['tipo_id']}\">{$tipo_factura['tipo_descripcion']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Campo para el número de factura -->
                    <div class="col-md-4">
                        <label for="numero_factura" class="form-label">Número de Factura</label>
                        <input type="text" class="form-control" id="numero_factura" name="numero_factura" placeholder="Ingrese el número de la factura" required>
                    </div>
                    <div class="col-md-4">
                        <label for="nota_remision" class="form-label">¿Desea Nota de Remisión?</label><br>
                        <input type="checkbox" id="nota_remision" name="nota_remision" value="1">
                        <label for="nota_remision">Sí, generar nota de remisión</label>
                    </div>
                    <div class="col-md-4">
                        <label for="cantidad_cuotas" class="form-label">Cantidad de cuotas</label>
                        <input type="text" class="form-control" id="cantidad_cuotas" name="cantidad_cuotas" placeholder="Ingrese la cantidad de cuotas" required>
                    </div>

                </div>

                <div class="row mb-3">
                    <!-- Campo para el timbrado -->
                    <div class="col-md-4">
                        <label for="timbrado" class="form-label">Timbrado</label>
                        <input type="text" class="form-control" id="timbrado" name="timbrado" placeholder="Ingrese el timbrado de la factura" required>
                    </div>

                    <!-- Campos para las fechas de vigencia -->
                    <div class="col-md-4">
                        <label for="vigencia_desde" class="form-label">Vigencia Desde</label>
                        <input type="date" class="form-control" id="vigencia_desde" name="vigencia_desde" required>
                    </div>
                    <div class="col-md-4">
                        <label for="vigencia_hasta" class="form-label">Vigencia Hasta</label>
                        <input type="date" class="form-control" id="vigencia_hasta" name="vigencia_hasta" required>
                    </div>
                </div>
                <!-- Fin campos adicionales -->

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="presupuesto" class="form-label">Gestionar compras</label>
                        <select class="form-control" id="presupuesto" name="presupuesto" required>
                            <option value="" selected>Seleccione una orden de compra</option>
                            <?php
                            $query_pedido = $pdo->query("SELECT orden_id FROM orden_compras WHERE orden_estado = 'PENDIENTE' ORDER BY orden_id ASC");
                            while ($presupuesto = $query_pedido->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value=\"{$presupuesto['orden_id']}\">Orden N° {$presupuesto['orden_id']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                </div>

                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-striped" id="tabla-productos">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Proveedor</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio</th>
                                <th>Tipo IVA</th>
                                <th>Monto Iva</th>
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
                    <div class="col-md-4">
                        <label for="total_importe">Total Importe</label>
                        <input type="number" class="form-control" id="total_importe" name="total_importe" readonly>
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

<!-- Scripts -->
<script>
// Manejar el cambio del pedido y cargar detalles
document.getElementById('presupuesto').addEventListener('change', async function () {
    const pedidoId = this.value;

    if (!pedidoId) return;

    try {
        const response = await fetch(`get_pedido_detalle.php?pedido_id=${pedidoId}`);
        const detalles = await response.json();

        const tbody = document.querySelector('#tabla-productos tbody');
        tbody.innerHTML = ''; // Limpiar la tabla antes de cargar nuevos detalles

        detalles.forEach((detalle, index) => {
            const row = `
                <tr>
                    <td>${detalle.codigo}</td>
                    <td>${detalle.proveedor}</td>
                    <td>${detalle.producto}</td>
                    <td>${detalle.cantidad}</td>
                    <td class="precio">${detalle.precio}</td>
                    <td class="iva">${detalle.iva} %</td>
                    <td class="monto-iva">0</td>
                    <td class="subtotal">0</td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm btn-quitar">Quitar</button>
                    </td>
                </tr>`;
            tbody.insertAdjacentHTML('beforeend', row);
        });

        actualizarTotales();
    } catch (error) {
        console.error('Error al cargar detalles:', error);
        alert('Ocurrió un error al intentar cargar los detalles del pedido.');
    }
});

// Función para calcular los totales (IVA y subtotal)
function actualizarTotales() {
    let totalImporte = 0;

    document.querySelectorAll('#tabla-productos tbody tr').forEach(row => {
        const cantidad = parseFloat(row.children[3].textContent) || 0; // Cantidad
        const precio = parseFloat(row.querySelector('.precio').textContent) || 0; // Precio
        const tipoIva = parseFloat(row.querySelector('.iva').textContent) || 0; // Tipo de IVA

        // Calcular monto del IVA
        const montoIva = cantidad * precio * (tipoIva / 100);

        // Calcular subtotal incluyendo IVA
        const subtotal = cantidad * precio + montoIva;

        // Actualizar los valores en la fila
        row.querySelector('.monto-iva').textContent = isNaN(montoIva) ? '0' : montoIva.toFixed(2);
        row.querySelector('.subtotal').textContent = isNaN(subtotal) ? '0' : subtotal.toFixed(2);

        // Sumar al total general
        totalImporte += subtotal;
    });

    // Actualizar el total general en el campo correspondiente
    document.getElementById('total_importe').value = totalImporte.toFixed(2);
}

// Manejar la eliminación de filas visualmente
document.querySelector('#tabla-productos').addEventListener('click', function (e) {
    if (e.target.classList.contains('btn-quitar')) {
        const row = e.target.closest('tr');
        row.remove(); // Eliminar fila visualmente
        actualizarTotales(); // Recalcular totales
    }
});

// Manejar el envío del formulario
document.getElementById('form-presupuesto').addEventListener('submit', function (e) {
    const productos = [];

    document.querySelectorAll('#tabla-productos tbody tr').forEach(row => {
        const codigo = row.children[0].textContent.trim();
        const cantidad = parseFloat(row.children[3].textContent.trim()) || 0;
        const precio = parseFloat(row.querySelector('.precio').textContent.trim()) || 0;
        const iva = parseFloat(row.querySelector('.iva').textContent.trim()) || 0;

        if (codigo && cantidad > 0 && precio > 0) {
            productos.push({
                codigo: codigo,
                cantidad: cantidad,
                precio: precio,
                iva: iva
            });
        }
    });

    if (productos.length === 0) {
        e.preventDefault(); // Detener el envío del formulario
        showErrorModal('No se puede guardar una factura sin detalles. Agregue al menos un producto.');
        return;
    }

    document.getElementById('productos').value = JSON.stringify(productos);
});

// Mostrar modal de error
function showErrorModal(message) {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.setAttribute('role', 'dialog');
    modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">Error</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>${message}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-dismiss="modal" id="error-close-button">Cerrar</button>
                </div>
            </div>
        </div>`;
    document.body.appendChild(modal);

    $(modal).modal('show');

    // Recargar la página al cerrar el modal
    document.getElementById('error-close-button').addEventListener('click', function () {
        $(modal).modal('hide');
        $(modal).on('hidden.bs.modal', function () {
            location.reload();
        });
    });

    $(modal).on('hidden.bs.modal', function () {
        modal.remove();
    });
}
</script>

<?php } ?>
