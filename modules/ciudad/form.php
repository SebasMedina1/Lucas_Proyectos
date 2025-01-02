<?php 
// Verificar si existe el parámetro 'form' en la URL
if (isset($_GET['form_ciudad']) && $_GET['form'] == 'add') { ?>
    <div class="container-fluid">
        <!-- Encabezado de página -->
        <h1 class="h3 mb-4 text-gray-800">
            <i class="fas fa-plus-circle"></i> Agregar Ciudad
        </h1>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="view.php">Ciudad</a></li>
            <li class="breadcrumb-item active">Agregar</li>
        </ol>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="proses.php?act=insert" method="POST">
                    <?php
                    require "../../config/database.php";
                    // Generar el código automáticamente
                    $query_id = mysqli_query($mysqli, "SELECT MAX(cod_ciudad) as id FROM ciudad") or die('Error ' . mysqli_error($mysqli));
                    $count = mysqli_num_rows($query_id);  
                    if ($count <> 0) {
                        $data_id = mysqli_fetch_assoc($query_id);
                        $codigo = $data_id['id'] + 1;
                    } else {
                        $codigo = 1;
                    }
                    ?>
                    <div class="form-group">
                        <label for="codigo">Código</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo $codigo; ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="departamento">Departamento</label>
                        <select name="departamento" id="departamento" class="form-control">
                            <option value="">Seleccione un departamento</option>
                            <?php 
                            $query_dep = mysqli_query($mysqli, "SELECT id_departamento, dep_descripcion FROM departamento ORDER BY id_departamento ASC") or die('Error ' . mysqli_error($mysqli));
                            while ($data_dep = mysqli_fetch_assoc($query_dep)) {
                                echo "<option value=\"$data_dep[id_departamento]\">$data_dep[id_departamento] | $data_dep[dep_descripcion]</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="descrip_ciudad">Descripción Ciudad</label>
                        <input type="text" class="form-control" id="descrip_ciudad" name="descrip_ciudad" placeholder="Ingrese descripcion del Deposito " required>
                    </div>

                    <button type="submit" class="btn btn-primary" name="Guardar">Guardar</button>
                    <a href="view.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
<?php
} elseif (isset($_GET['form_ciudad']) && $_GET['form'] == 'edit') { 
    if (isset($_GET['id'])) {
        // Consultar los datos de la ciudad
        $query = mysqli_query($mysqli, "select cod_ciudad, descrip_ciudad from ciudad where cod_ciudad = '$_GET[id]'") or die('Error: ' . mysqli_error($mysqli));
        $data = mysqli_fetch_assoc($query);
    }
    ?>
    <div class="container-fluid">
        <!-- Encabezado de página -->
        <h1 class="h3 mb-4 text-gray-800">
            <i class="fas fa-edit"></i> Modificar Ciudad
        </h1>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="view.php">Ciudad</a></li>
            <li class="breadcrumb-item active">Modificar</li>
        </ol>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="proses.php?act=update" method="POST">
                    <div class="form-group">
                        <label for="codigo">Código</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo $data['cod_ciudad']; ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="descrip">Descripción</label>
                        <input type="text" class="form-control" id="descrip" name="descrip" value="<?php echo $data['descrip_ciudad']; ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary" name="Guardar">Guardar</button>
                    <a href="view.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
<?php 
} else { 
    // Si no existe 'form' en la URL o el valor no es válido, redirigir a la lista de ciudades
    header('Location: view.php');
}
?>
