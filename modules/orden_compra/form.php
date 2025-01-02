<?php 
// Verificar si existe el parámetro 'form' en la URL
if (isset($_GET['form_orden']) && $_GET['form'] == 'add') { ?>
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">
        <i class="fas fa-plus-circle"></i> Registrar orden de compra
    </h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="view.php">Orden de compra</a></li>
        <li class="breadcrumb-item active">Nueva Orden de compra</li>
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

                    $query = $pdo->query("SELECT MAX(orden_id) AS id FROM orden_compras");
                    $data = $query->fetch(PDO::FETCH_ASSOC);

                    $codigo = ($data['id'] !== null) ? $data['id'] + 1 : 1;
                    date_default_timezone_set('America/Asuncion');
                    $fecha = date("Y-m-d"); // Formato: YYYY-MM-DD (año:mes:dia)
                    $hora = date("H:i A"); // Formato: hh:mm:ss AM/PM (hora:minutos)
                } catch (PDOException $e) {
                    die("Error: " . $e->getMessage());
                }
                ?>
                <div class="row mb-3">

                    <div class="col-md-4">
                        <label for="codigo" class="form-label">Orden N°</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo $codigo; ?>" readonly>
                    </div>

                    <div class="col-md-4">
                        <label for="fecha" class="form-label">Fecha</label>
                        <input type="text" class="form-control" id="fecha" name="fecha" value="<?php echo $fecha; ?>" readonly>
                    </div>

                    <div class="col-md-4">
                        <label for="hora" class="form-label">Hora</label>
                        <input type="text" class="form-control" id="hora" name="hora" value="<?php echo $hora; ?>" readonly>
                    </div>

                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="presupuesto" class="form-label">Presupuestos</label>
                        <select class="form-control" id="presupuesto" name="presupuesto" required>
                            <option value="" selected>Seleccione un presupuesto</option>
                            <?php
                            $query_pedido = $pdo->query("SELECT presupuesto_id FROM presupuesto_compra WHERE pre_estado = 'PENDIENTE' ORDER BY presupuesto_id ASC");
                            while ($presupuesto = $query_pedido->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value=\"{$presupuesto['presupuesto_id']}\">Presupuesto N° {$presupuesto['presupuesto_id']}</option>";
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
                                <th>#</th>
                                <th>Código</th>
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
// Manejar el botón Cancelar para limpiar el formulario y la tabla
document.getElementById('btn-cancelar').addEventListener('click', function () {
    // Limpiar la tabla de productos
    document.querySelector('#tabla-productos tbody').innerHTML = '';

    // Resetear campos del formulario
    document.getElementById('presupuesto').value = ''; // Resetear el combo box de presupuestos
    document.getElementById('total_importe').value = '0.00'; // Resetear el total importe

    // Insertar la alerta dinámicamente
    const alertContainer = document.createElement('div');
    alertContainer.id = 'alert-message';
    alertContainer.className = 'alert alert-success alert-dismissible fade show';
    alertContainer.role = 'alert';
    alertContainer.innerHTML = `
        <strong>Datos limpiados correctamente.</strong>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    `;

    // Insertar la alerta en el DOM (al inicio del formulario)
    const formContainer = document.querySelector('.card-body');
    formContainer.insertBefore(alertContainer, formContainer.firstChild);

    // Ocultar automáticamente la alerta después de 3 segundos
    setTimeout(function () {
        if (alertContainer) {
            alertContainer.remove();
        }
    }, 3000);
});




// Manejar el cambio del presupuesto y cargar detalles
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
                    <td>${index + 1}</td>
                    <td>${detalle.codigo}</td>
                    <td>${detalle.descripcion}</td>
                    <td>
                        <input type="number" class="form-control cantidad" min="1" value="${detalle.cantidad}" required>
                    </td>
                    <td>
                        <input type="number" class="form-control precio" min="0" step="0.01" value="${detalle.precio}" required readonly>
                    </td>
                    <td class="iva">${detalle.iva}%</td>
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
        const cantidad = parseFloat(row.querySelector('.cantidad').value) || 0;
        const precio = parseFloat(row.querySelector('.precio').value) || 0;
        const tipoIva = parseFloat(row.querySelector('.iva').textContent) || 0;

        // Calcular monto del IVA
        const montoIva = cantidad * precio * (tipoIva / 100);

        // Calcular subtotal incluyendo IVA
        const subtotal = cantidad * precio + montoIva;

        // Actualizar los valores en la fila
        row.querySelector('.monto-iva').textContent = montoIva.toFixed(2);
        row.querySelector('.subtotal').textContent = subtotal.toFixed(2);

        // Sumar al total general
        totalImporte += subtotal;
    });

    // Actualizar el total general en el campo correspondiente
    document.getElementById('total_importe').value = totalImporte.toFixed(2);
}

// Manejar la modificación de cantidad o precio para recalcular en tiempo real
document.querySelector('#tabla-productos').addEventListener('input', function (e) {
    if (e.target.classList.contains('cantidad') || e.target.classList.contains('precio')) {
        actualizarTotales(); // Recalcular totales cuando se modifica cantidad o precio
    }
});

// Manejar la eliminación de filas visualmente
document.querySelector('#tabla-productos').addEventListener('click', function (e) {
    if (e.target.classList.contains('btn-quitar')) {
        e.target.closest('tr').remove(); // Eliminar fila visualmente
        actualizarTotales(); // Recalcular totales
    }
});

// Manejar el envío del formulario
document.getElementById('form-presupuesto').addEventListener('submit', function (e) {
    const productos = [];

    document.querySelectorAll('#tabla-productos tbody tr').forEach(row => {
        const codigo = row.children[1].textContent.trim();
        const cantidad = parseFloat(row.querySelector('.cantidad').value) || 0;
        const precio = parseFloat(row.querySelector('.precio').value) || 0;
        const iva = parseFloat(row.querySelector('.iva').textContent) || 0;

        if (codigo && cantidad > 0 && precio > 0) {
            productos.push({ codigo, cantidad, precio, iva });
        }
    });

    if (productos.length === 0) {
        showErrorModal('Debe agregar al menos un producto con cantidad válida.');
        e.preventDefault();
    } else {
        document.getElementById('productos').value = JSON.stringify(productos);
    }
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
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>${message}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>`;
    document.body.appendChild(modal);
    $(modal).modal('show');
    $(modal).on('hidden.bs.modal', function () {
        modal.remove();
    });
}
</script>






<?php } ?>

