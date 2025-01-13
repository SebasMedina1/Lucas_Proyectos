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
                    $hora = date("H:i:s"); // Formato 24 horas (HH:mm:ss)
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
                    <!-- Campo de la condicion de pago -->
                    <div class="col-md-4">
                        <label for="condicion_pago" class="form-label">Condición de Pago</label>
                        <input type="text" class="form-control" id="condicion_pago" name="condicion_pago" value="Contado" readonly>
                    </div>



                    <!-- Campo para el número de factura -->
                    <div class="col-md-4">
                        <label for="numero_factura" class="form-label">Número de Factura</label>
                        <input type="text" class="form-control" id="numero_factura" name="numero_factura" 
                            placeholder="Ingrese el número de la factura" 
                            pattern="^[1-9\-]+$" 
                            title="Solo se permiten números del 1 al 9 y guiones (-)." 
                            required>
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
                        <input type="text" class="form-control" id="total_importe" name="total_importe" readonly>
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

    // Recorrer cada fila de la tabla de productos
    document.querySelectorAll('#tabla-productos tbody tr').forEach(row => {
        const cantidad = parseFloat(row.children[3].textContent) || 0; // Cantidad
        const precio = parseFloat(row.querySelector('.precio').textContent.replace(/\./g, '')) || 0; // Precio sin puntos
        const tipoIva = parseFloat(row.querySelector('.iva').textContent.replace('%', '')) || 0; // Tipo de IVA

        let montoIva = 0;

        // Calcular monto del IVA según el tipo de IVA
        if (tipoIva === 10) {
            montoIva = Math.floor(precio / 11); // IVA 10%
        } else if (tipoIva === 5) {
            montoIva = Math.floor(precio / 21); // IVA 5%
        } else {
            montoIva = 0; // IVA 0%
        }

        // Calcular el subtotal y el monto total del IVA
        const subtotal = cantidad * precio;
        const totalIva = montoIva * cantidad;

        // Actualizar los valores en la fila
        row.querySelector('.monto-iva').textContent = totalIva.toFixed(2);
        row.querySelector('.subtotal').textContent = subtotal.toFixed(2);

        // Sumar al total general (subtotal + IVA)
        totalImporte += subtotal + totalIva;
    });

    // Actualizar el total general en el campo correspondiente
    document.getElementById('total_importe').value = `${totalImporte.toFixed(2)} Gs`;

}





// Manejar la eliminación de filas visualmente
document.querySelector('#tabla-productos').addEventListener('click', function (e) {
    if (e.target.classList.contains('btn-quitar')) {
        const row = e.target.closest('tr');
        row.remove(); // Eliminar fila visualmente
        actualizarTotales(); // Recalcular totales
        limpiarFormulario();
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


    document.getElementById('numero_factura').addEventListener('blur', function() {
        const numeroFactura = this.value;

        if (numeroFactura !== '') {
            fetch(`verificar_factura.php?numero_factura=${numeroFactura}`)
                .then(response => response.json())
                .then(data => {
                    if (data.existe) {
                        alert('El número de factura ya existe. Por favor, ingrese un número diferente.');
                        this.value = ''; // Limpiar el campo
                        this.focus(); // Regresar el foco al campo
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    });


    // Obtener los elementos de los campos de fecha
    const vigenciaDesde = document.getElementById('vigencia_desde');
    const vigenciaHasta = document.getElementById('vigencia_hasta');

    // Establecer la fecha mínima para "Vigencia Desde" como la fecha actual
    const today = new Date().toISOString().split('T')[0];
    vigenciaDesde.setAttribute('min', today);

    // Validar que "Vigencia Hasta" sea mayor que "Vigencia Desde"
    vigenciaDesde.addEventListener('change', function () {
        // Ajustar la fecha mínima de "Vigencia Hasta" según "Vigencia Desde"
        vigenciaHasta.setAttribute('min', vigenciaDesde.value);

        // Limpiar el campo "Vigencia Hasta" si su valor es menor que "Vigencia Desde"
        if (vigenciaHasta.value && vigenciaHasta.value < vigenciaDesde.value) {
            vigenciaHasta.value = '';
        }
    });

    vigenciaHasta.addEventListener('change', function () {
        // Validar que "Vigencia Hasta" sea mayor que "Vigencia Desde"
        if (vigenciaHasta.value < vigenciaDesde.value) {
            alert('La fecha "Vigencia Hasta" debe ser mayor que "Vigencia Desde".');
            vigenciaHasta.value = '';
        }
    });

    // Función para limpiar los campos del formulario
    function limpiarFormulario() {
        document.getElementById('numero_factura').value = '';
        document.getElementById('timbrado').value = '';
        document.getElementById('vigencia_desde').value = '';
        document.getElementById('vigencia_hasta').value = '';
        document.getElementById('presupuesto').selectedIndex = 0;
        document.getElementById('total_importe').value = '';

        // Limpiar la tabla de productos si existe
        const tablaProductos = document.querySelector('#tabla-productos tbody');
        if (tablaProductos) {
            tablaProductos.innerHTML = '';
        }
    }

    // Ejecutar la función al cargar la página
    window.onload = limpiarFormulario;



</script>

<?php } ?>
