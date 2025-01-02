<?php
// Verificar si existe el parámetro 'form' en la URL
if (isset($_GET['nota_remision']) && $_GET['form'] == 'add') { ?>
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">
        <i class="fas fa-plus-circle"></i> Registrar Nota de Remisión
    </h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="view.php">Notas de Remisión</a></li>
        <li class="breadcrumb-item active">Nueva Nota de Remisión</li>
    </ol>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form id="form-nota-remision" action="proses.php?act=insert_nota_remision" method="POST">
                <?php
                try {
                    require "../../config/database.php";
                    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                    $pdo = new PDO($dsn, $user, $pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Obtener el ID y fecha de la nota
                    $query = $pdo->query("SELECT MAX(remision_id) AS id FROM nota_remision_compra");
                    $data = $query->fetch(PDO::FETCH_ASSOC);
                    $codigo = ($data['id'] !== null) ? $data['id'] + 1 : 1;
                    $fecha = date("Y-m-d");
                } catch (PDOException $e) {
                    die("Error: " . $e->getMessage());
                }
                ?>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="remision_fecha" class="form-label">Fecha</label>
                        <input type="text" class="form-control" id="remision_fecha" name="remision_fecha" value="<?php echo $fecha; ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label for="remision_id" class="form-label">Número de Remisión</label>
                        <input type="text" class="form-control" id="remision_id" name="remision_id" value="<?php echo $codigo; ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label for="remision_nro" class="form-label">Número de Nota de Remisión</label>
                        <input type="text" class="form-control" id="remision_nro" name="remision_nro" placeholder="Ingrese el número de remisión" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="vehiculo_id" class="form-label">Vehículo</label>
                        <select class="form-control" id="vehiculo_id" name="vehiculo_id" required>
                            <option value="" selected>Seleccione un vehículo</option>
                            <?php
                            $query_vehiculos = $pdo->query("SELECT vehiculo_id, vehiculo_descripcion FROM vehiculos ORDER BY vehiculo_id ASC");
                            while ($vehiculo = $query_vehiculos->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value=\"{$vehiculo['vehiculo_id']}\">{$vehiculo['vehiculo_descripcion']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="conductor_id" class="form-label">Conductor</label>
                        <select class="form-control" id="conductor_id" name="conductor_id" required>
                            <option value="" selected>Seleccione un conductor</option>
                            <?php
                            $query_conductores = $pdo->query("SELECT conductor_id, conductor_nombre, conductor_apellido FROM conductores ORDER BY conductor_id ASC");
                            while ($conductor = $query_conductores->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value=\"{$conductor['conductor_id']}\">{$conductor['conductor_nombre']} {$conductor['conductor_apellido']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="fact_id" class="form-label">Factura</label>
                        <select class="form-control" id="fact_id" name="fact_id" required>
                            <option value="" selected>Seleccione una factura</option>
                            <?php
                            $query_facturas = $pdo->query("SELECT fact_id, fact_nro FROM facturas_compra WHERE fact_remision = 'false' ORDER BY fact_id ASC");
                            while ($factura = $query_facturas->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value=\"{$factura['fact_id']}\">Factura N° {$factura['fact_nro']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-striped" id="tabla-productos">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Proveedor</th>
                                <th>Materia Prima</th>
                                <th>Cantidad</th>
                                <th>Precio</th>
                                <th>Tipo IVA</th>
                                <th>Monto IVA</th>
                                <th>Subtotal</th>
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
                    <a href="view.php" class="btn btn-danger mx-2">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script>
document.getElementById('fact_id').addEventListener('change', async function () {
    const facturaId = this.value;

    if (!facturaId) return;

    try {
        const response = await fetch(`get_pedido_detalle.php?fact_id=${facturaId}`);
        const detalles = await response.json();

        const tbody = document.querySelector('#tabla-productos tbody');
        tbody.innerHTML = '';

        detalles.forEach(detalle => {
            const row = `
                <tr>
                    <td>${detalle.codigo}</td>
                    <td>${detalle.proveedor}</td>
                    <td>${detalle.materia_prima}</td>
                    <td>${detalle.cantidad}</td>
                    <td>${detalle.precio}</td>
                    <td>${detalle.iva}%</td>
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
        alert('Ocurrió un error al intentar cargar los detalles de la factura.');
    }
});

function actualizarTotales() {
    let totalImporte = 0;

    document.querySelectorAll('#tabla-productos tbody tr').forEach(row => {
        const cantidad = parseFloat(row.children[3].textContent) || 0;
        const precio = parseFloat(row.children[4].textContent) || 0;
        const tipoIva = parseFloat(row.children[5].textContent) || 0;

        const montoIva = cantidad * precio * (tipoIva / 100);
        const subtotal = cantidad * precio + montoIva;

        row.querySelector('.monto-iva').textContent = montoIva.toFixed(2);
        row.querySelector('.subtotal').textContent = subtotal.toFixed(2);

        totalImporte += subtotal;
    });

    document.getElementById('total_importe').value = totalImporte.toFixed(2);
}

document.querySelector('#tabla-productos').addEventListener('click', function (e) {
    if (e.target.classList.contains('btn-quitar')) {
        const row = e.target.closest('tr');
        row.remove();
        actualizarTotales();
    }
});

document.getElementById('form-nota-remision').addEventListener('submit', function (e) {
    const productos = [];
    document.querySelectorAll('#tabla-productos tbody tr').forEach(row => {
        const codigo = row.children[0].textContent.trim();
        const cantidad = parseFloat(row.children[3].textContent.trim()) || 0;
        const precio = parseFloat(row.children[4].textContent.trim()) || 0;
        const iva = parseFloat(row.children[5].textContent.trim()) || 0;

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
        e.preventDefault();
        alert('Debe agregar al menos un producto.');
        return;
    }

    document.getElementById('productos').value = JSON.stringify(productos);
});
</script>

<?php } ?>
