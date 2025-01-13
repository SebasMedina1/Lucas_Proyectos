<?php 
if (empty($_SESSION['username']) || empty($_SESSION['password'])) {
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

                    $query = $pdo->query("SELECT MAX(presupuesto_id) AS id FROM presupuesto_compra");
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
                        <label for="codigo" class="form-label">Presupuesto N°</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo $codigo; ?>" readonly>
                    </div>

                    <div class="col-md-4">
                        <label for="fecha" class="form-label">Fecha</label>
                        <input type="text" class="form-control" id="fecha" name="fecha" value="<?php echo $fecha; ?>" readonly>
                    </div>

                    <div class="col-md-4">
                        <label for="hora" class="form-label">Fecha</label>
                        <input type="hora" class="form-control" id="hora" name="hora" value="<?php echo $hora; ?>" readonly>
                    </div>

                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="pedido" class="form-label">Pedidos de compras</label>
                        <select class="form-control" id="pedido" name="pedido" required>
                            <option value="" selected>Seleccione un Pedido</option>
                            <?php
                            $query_pedido = $pdo->query("SELECT pedido_id FROM pedidos_compras WHERE estado = 'PENDIENTE' ORDER BY pedido_id ASC");
                            while ($pedido = $query_pedido->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value=\"{$pedido['pedido_id']}\">Pedido N° {$pedido['pedido_id']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    
                    <div class="col-md-4">
                        <label for="proveedor" class="form-label">Proveedor</label>
                        <select class="form-control" id="proveedor" name="proveedor" required>
                            <option value="" selected>Seleccione un Proveedor</option>
                            <?php
                            $query_proveedor = $pdo->query("SELECT cod_proveedor, razon_social FROM proveedor ORDER BY cod_proveedor ASC");
                            while ($proveedor = $query_proveedor->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value=\"{$proveedor['cod_proveedor']}\">{$proveedor['razon_social']}</option>";
                            }
                            ?>
                        </select>
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
document.getElementById('pedido').addEventListener('change', async function () {
    const pedidoId = this.value;

    if (!pedidoId) return;

    try {
        const response = await fetch(`get_pedido_detalle.php?pedido_id=${pedidoId}`);
        const detalles = await response.json();

        const tbody = document.querySelector('#tabla-productos tbody');
        tbody.innerHTML = ''; // Limpiar la tabla antes de cargar nuevos detalles

        // Generar filas de la tabla
        detalles.forEach((detalle, index) => {
            const cantidad = detalle.cantidad || 1; // Si no hay cantidad, usa 1 por defecto
            const precio = detalle.precio || 0; // Si no hay precio, usa 0 por defecto
            const iva = detalle.iva || 0; // Si no hay IVA, usa 0 por defecto

            // Generar la fila
            const row = `
                <tr>
                    <td>${index + 1}</td>
                    <td>${detalle.codigo}</td>
                    <td>${detalle.descripcion}</td>
                    <td>
                        <input type="number" class="form-control cantidad" min="1" value="${cantidad}" required>
                    </td>
                    <td>
                        <input type="number" class="form-control precio" min="0" step="0.01" value="${precio}" required>
                    </td>
                    <td class="iva">${iva}</td>
                    <td class="monto_iva">0</td>
                    <td class="subtotal">0</td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm btn-quitar">Quitar</button>
                    </td>
                </tr>`;
            tbody.insertAdjacentHTML('beforeend', row);

            // Ejecutar la lógica de cálculo (simular interacción)
            const nuevaFila = tbody.lastElementChild;
            recalcularFila(nuevaFila);
        });

        actualizarTotales(); // Recalcular los totales generales
    } catch (error) {
        console.error('Error al cargar detalles:', error);
        mostrarModal(
            "Error al cargar detalles",
            "Ocurrió un error al intentar cargar los detalles del pedido. Por favor, inténtelo nuevamente.",
            "danger"
        );
    }
});



// Función para recalcular IVA y Subtotal en una fila específica
function recalcularFila(row) {
    const cantidad = parseFloat(row.querySelector('.cantidad').value) || 0;
    const precio = parseFloat(row.querySelector('.precio').value.replace(/\./g, '').replace(',', '.')) || 0;
    const iva = parseFloat(row.querySelector('.iva').textContent) || 0;

    let montoIVA = 0;

    if (iva === 10) {
        montoIVA = Math.floor(precio / 11); // Tomar solo la parte entera
    } else if (iva === 5) {
        montoIVA = Math.floor(precio / 21); // Tomar solo la parte entera
    }

    // Calcular el subtotal
    const subtotal = cantidad * precio;

    // Actualizar los valores en la tabla
    row.querySelector('.subtotal').textContent = `${subtotal}.00`;
    row.querySelector('.monto_iva').textContent = `${montoIVA * cantidad}.00`;
}





// Calcular y mostrar los totales al cambiar cantidad o precio
document.querySelector('#tabla-productos').addEventListener('input', function (e) {
    if (e.target.classList.contains('cantidad') || e.target.classList.contains('precio')) {
        const row = e.target.closest('tr');
        recalcularFila(row); // Reusar la función para recalcular
        actualizarTotales(); // Actualizar los totales generales
    }
});

// Función para calcular los totales de la tabla
function actualizarTotales() {
    let totalImporte = 0;

    document.querySelectorAll('#tabla-productos tbody tr').forEach(row => {
        const subtotal = parseFloat(row.querySelector('.subtotal').textContent) || 0;
        const montoIVA = parseFloat(row.querySelector('.monto_iva').textContent) || 0;

        // Sumar al total el subtotal más el monto del IVA
        totalImporte += subtotal + montoIVA;
    });

    // Mostrar el total actualizado con IVA incluido y separador de miles
    document.getElementById('total_importe').value = `${Math.floor(totalImporte)}.00`;
}

// Manejar la eliminación de filas
document.querySelector('#tabla-productos').addEventListener('click', function (e) {
    if (e.target.classList.contains('btn-quitar')) {
        const row = e.target.closest('tr');
        row.remove(); // Eliminar la fila
        actualizarTotales(); // Recalcular totales después de eliminar
    }
});

// Manejar el envío del formulario
document.getElementById('form-presupuesto').addEventListener('submit', function (e) {
    const productos = [];

    // Recopilar los datos de los productos
    document.querySelectorAll('#tabla-productos tbody tr').forEach(row => {
        const codigo = row.children[1].textContent.trim();
        const cantidad = parseFloat(row.querySelector('.cantidad').value) || 0;
        const precio = parseFloat(row.querySelector('.precio').value) || 0;

        if (codigo && cantidad > 0 && precio > 0) {
            productos.push({ codigo, cantidad, precio });
        }
    });

    // Validar si no hay productos
    if (productos.length === 0) {
        e.preventDefault(); // Detener el envío del formulario
        mostrarModal(
            "No se puede generar el presupuesto",
            "Debe haber al menos un detalle en el pedido para generar el presupuesto.",
            "warning",
            true
        );
        return;
    }

    // Asignar los productos al campo oculto
    document.getElementById('productos').value = JSON.stringify(productos);
});

// Función para mostrar una modal emergente
function mostrarModal(titulo, mensaje, tipo, recargar = false) {
    // Crear contenido del modal
    const modalHtml = `
        <div class="modal fade" id="modalMensaje" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-${tipo}">
                        <h5 class="modal-title text-white" id="modalLabel">${titulo}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        ${mensaje}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-${tipo}" data-bs-dismiss="modal" id="cerrarModal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Insertar el modal en el DOM
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Mostrar el modal
    const modal = new bootstrap.Modal(document.getElementById('modalMensaje'));
    modal.show();

    // Opcional: Recargar la página al cerrar el modal
    document.getElementById('cerrarModal').addEventListener('click', () => {
        if (recargar) location.reload();
        document.getElementById('modalMensaje').remove(); // Eliminar el modal del DOM
    });
}



    const btnGuardar = document.querySelector('button[name="Guardar"]');
    const tablaProductos = document.getElementById('tabla-productos').querySelector('tbody');

    // Validar cantidad y precio en tiempo real
    tablaProductos.addEventListener('input', function (e) {
        if (e.target.classList.contains('cantidad') || e.target.classList.contains('precio')) {
            validarFormulario();
        }
    });

    // Validar que el formulario esté completo y correcto
    function validarFormulario() {
        let formularioValido = true;

        document.querySelectorAll('#tabla-productos tbody tr').forEach(row => {
            const cantidad = parseFloat(row.querySelector('.cantidad').value) || 0;
            const precio = parseFloat(row.querySelector('.precio').value) || 0;

            if (cantidad < 1 || precio < 1) {
                formularioValido = false;
            }
        });

        btnGuardar.disabled = !formularioValido;
    }

    // Inicializar el estado del botón "Guardar"
    validarFormulario();

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












<?php } ?>

