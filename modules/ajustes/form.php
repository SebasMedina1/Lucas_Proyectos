<?php
// Verificar si existe el parámetro 'form' en la URL
if (isset($_GET['ajustes']) && $_GET['form'] == 'add') { ?>
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">
        <i class="fas fa-plus-circle"></i> Registrar Ajuste
    </h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="view.php">Ajustes</a></li>
        <li class="breadcrumb-item active">Nuevo Ajuste</li>
    </ol>

    <div class="card shadow mb-4">
        <div class="card-body">

            <form id="form-ajuste" action="proses.php?act=insert_ajuste" method="POST">
                <?php
                try {
                    require "../../config/database.php";
                    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                    $pdo = new PDO($dsn, $user, $pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Obtener el ID y fecha del ajuste
                    $query = $pdo->query("SELECT MAX(ajuste_id) AS id FROM ajustes");
                    $data = $query->fetch(PDO::FETCH_ASSOC);
                    $codigo = ($data['id'] !== null) ? $data['id'] + 1 : 1;
                    date_default_timezone_set('America/Asuncion');
                    $fecha = date("Y-m-d"); // Formato: YYYY-MM-DD (año:mes:dia)
                    $hora = date("H:i "); // Formato: hh:mm:ss AM/PM (hora:minutos)
                } catch (PDOException $e) {
                    die("Error: " . $e->getMessage());
                }
                ?>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="ajuste_fecha" class="form-label">Fecha</label>
                        <input type="text" class="form-control" id="ajuste_fecha" name="ajuste_fecha" value="<?php echo $fecha; ?>" readonly>
                    </div>

                    <div class="col-md-3">
                        <label for="hora" class="form-label">Hora</label>
                        <input type="text" class="form-control" id="hora" name="hora" value="<?php echo $hora; ?>" readonly>
                    </div>

                    <div class="col-md-4">
                        <label for="ajuste_id" class="form-label">Número de Ajuste</label>
                        <input type="text" class="form-control" id="ajuste_id" name="ajuste_id" value="<?php echo $codigo; ?>" readonly>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="producto" class="form-label">Producto</label>
                        <select class="form-control" id="producto" name="producto" required>
                            <option value="" selected>Seleccione un Producto</option>
                            <?php
                            try {
                                $query_prod = $pdo->query("SELECT cod_producto, p_descrip FROM producto ORDER BY cod_producto ASC");
                                while ($data_prod = $query_prod->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"{$data_prod['cod_producto']}\">{$data_prod['p_descrip']}</option>";
                                }
                            } catch (PDOException $e) {
                                die("Error en la conexión o consulta: " . $e->getMessage());
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="deposito" class="form-label">Depósito</label>
                        <select class="form-control" id="deposito" name="deposito" required>
                            <option value="" selected>Seleccione un Depósito</option>
                            <?php
                            try {
                                $query_dep = $pdo->query("SELECT cod_deposito, descrip FROM deposito ORDER BY cod_deposito ASC");
                                while ($data_dep = $query_dep->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"{$data_dep['cod_deposito']}\">{$data_dep['descrip']}</option>";
                                }
                            } catch (PDOException $e) {
                                die("Error en la conexión o consulta: " . $e->getMessage());
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="stock_existencia" class="form-label">Stock Existencia</label>
                        <input type="text" class="form-control" id="stock_existencia" name="stock_existencia" readonly>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="ajuste_cantidad" class="form-label">Cantidad Ajustada</label>
                        <input type="number" class="form-control" id="ajuste_cantidad" name="ajuste_cantidad" placeholder="Ingrese la cantidad ajustada" required min="1" step="1">
                    </div>

                    <div class="col-md-3">
                        <label for="motivo" class="form-label">Motivo</label>
                        <select class="form-control" id="motivo" name="motivo" required>
                            <option value="" selected>Seleccione un motivo</option>
                            <?php
                            try {
                                $query_prod = $pdo->query("SELECT motivo_id, motivo_descripcion FROM motivo_ajuste ORDER BY motivo_id ASC");
                                while ($data_prod = $query_prod->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"{$data_prod['motivo_id']}\">{$data_prod['motivo_descripcion']}</option>";
                                }
                            } catch (PDOException $e) {
                                die("Error en la conexión o consulta: " . $e->getMessage());
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="tipo_ajuste" class="form-label">Tipo de Ajuste</label>
                        <input type="text" class="form-control" id="tipo_ajuste" name="tipo_ajuste" value="Disminución del Stock" readonly>
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
    // Escucha cambios en el producto y depósito para consultar el stock existente
    const productoSelect = document.getElementById('producto');
    const depositoSelect = document.getElementById('deposito');
    const stockInput = document.getElementById('stock_existencia');
    const cantidadInput = document.getElementById('ajuste_cantidad');

    productoSelect.addEventListener('change', consultarStock);
    depositoSelect.addEventListener('change', consultarStock);

    async function consultarStock() {
        const productoId = productoSelect.value;
        const depositoId = depositoSelect.value;

        if (!productoId || !depositoId) {
            stockInput.value = '';
            return;
        }

        try {
            const response = await fetch(`get_stock.php?cod_producto=${productoId}&cod_deposito=${depositoId}`);
            const data = await response.json();

            if (data.stock_existencia !== undefined) {
                stockInput.value = data.stock_existencia > 0 ? data.stock_existencia : 'Sin existencias';
            } else {
                stockInput.value = 'Sin existencias';
            }
        } catch (error) {
            console.error('Error al consultar el stock:', error);
            stockInput.value = 'Error';
        }
    }

    // Validación de cantidad ajustada para evitar números negativos o cero
    cantidadInput.addEventListener('input', function () {
        if (this.value < 1) {
            this.value = '';
            alert('La cantidad ajustada debe ser un número positivo mayor que 0.');
        }
    });

    // Selección de elementos del DOM
    const cantidadAjustadaInput = document.getElementById('ajuste_cantidad');
    const stockExistenciaInput = document.getElementById('stock_existencia');
    const guardarBtn = document.querySelector('button[type="submit"]');

    // Deshabilitar el botón de Guardar inicialmente
    guardarBtn.disabled = true;

    // Validar cuando se ingrese una cantidad ajustada
    cantidadAjustadaInput.addEventListener('input', function () {
        const stockExistencia = parseInt(stockExistenciaInput.value);
        const cantidadAjustada = parseInt(cantidadAjustadaInput.value);

        // Habilitar o deshabilitar el botón de Guardar según la validación
        if (!isNaN(cantidadAjustada) && cantidadAjustada > 0 && cantidadAjustada <= stockExistencia) {
            guardarBtn.disabled = false;
        } else {
            guardarBtn.disabled = true;
        }
    });

    // Validar también cuando se actualice el stock existente
    stockExistenciaInput.addEventListener('input', function () {
        const stockExistencia = parseInt(stockExistenciaInput.value);
        const cantidadAjustada = parseInt(cantidadAjustadaInput.value);

        // Habilitar o deshabilitar el botón de Guardar según la validación
        if (!isNaN(cantidadAjustada) && cantidadAjustada > 0 && cantidadAjustada < stockExistencia) {
            guardarBtn.disabled = false;
        } else {
            guardarBtn.disabled = true;
        }
    });


</script>
<?php } ?>
