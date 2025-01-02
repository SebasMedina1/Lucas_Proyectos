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
                    $hora = date("H:i A"); // Formato: hh:mm:ss AM/PM (hora:minutos)
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
                    <div class="col-md-4">
                        <label for="motivo" class="form-label">Motivo</label>
                        <select class="form-control" id="motivo" name="motivo" required>
                            <option value="" selected>Seleccione un motivo</option>
                            <option value="faltante">Faltante</option>
                            <option value="sobrante">Sobrante</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    
                <div class="col-md-6">
                        <label for="producto" class="form-label">Producto</label>
                        
                        <select class="form-control select2" id="producto" name="producto" >
                            <option value="" selected>Seleccione un Producto</option>
                            <?php
                            try {
                                $query_dep = $pdo->query("SELECT cod_producto, p_descrip FROM producto ORDER BY cod_producto ASC");
                                while ($data_dep = $query_dep->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"{$data_dep['cod_producto']}\">{$data_dep['p_descrip']}</option>";
                                }
                            } catch (PDOException $e) {
                                die("Error en la conexión o consulta: " . $e->getMessage());
                            }
                            ?>
                        </select>


                    </div>
                </div>

                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-striped" id="tabla-ajustes">
                        <thead>
                            <tr>
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

                <input type="hidden" name="detalles" id="detalles">

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
document.getElementById('materia_prima').addEventListener('change', function () {
    const materiaId = this.value;
    const materiaText = this.options[this.selectedIndex].text;

    if (!materiaId) return;

    const tbody = document.querySelector('#tabla-ajustes tbody');
    const row = `
        <tr>
            <td>${materiaId}</td>
            <td>${materiaText}</td>
            <td><input type="number" class="form-control cantidad" min="1" required></td>
            <td>
                <button type="button" class="btn btn-danger btn-sm btn-quitar">Quitar</button>
            </td>
        </tr>`;
    tbody.insertAdjacentHTML('beforeend', row);

    // Resetear el selector de materia prima
    this.value = '';
});

document.querySelector('#tabla-ajustes').addEventListener('click', function (e) {
    if (e.target.classList.contains('btn-quitar')) {
        e.target.closest('tr').remove();
    }
});

document.getElementById('form-ajuste').addEventListener('submit', function (e) {
    const detalles = [];
    document.querySelectorAll('#tabla-ajustes tbody tr').forEach(row => {
        const codigo = row.children[0].textContent.trim();
        const cantidad = parseFloat(row.querySelector('.cantidad').value.trim()) || 0;

        if (codigo && cantidad > 0) {
            detalles.push({ codigo, cantidad });
        }
    });

    if (detalles.length === 0) {
        e.preventDefault();
        alert('Debe agregar al menos una materia prima con cantidad.');
        return;
    }

    document.getElementById('detalles').value = JSON.stringify(detalles);
});
</script>

<?php } ?>
