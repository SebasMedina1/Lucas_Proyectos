<?php 
// Verificar si existe el parámetro 'form' en la URL
if (isset($_GET['form_umedida']) && $_GET['form'] == 'add') { ?>
    <div class="container-fluid">
        <!-- Encabezado de página -->
        <h1 class="h3 mb-4 text-gray-800">
            <i class="fas fa-plus-circle"></i> Agregar Unidad de Medida
        </h1>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../../index.html">Inicio</a></li>
            <li class="breadcrumb-item"><a href="view.php">Unidad de Medida</a></li>
            <li class="breadcrumb-item active">Agregar</li>
        </ol>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="proses.php?act=insert" method="POST">
                    <?php
                    require "../../config/database.php";
                    // Generar el código automáticamente
                    $query_id = mysqli_query($mysqli, "SELECT MAX(id_u_medida) as id FROM u_medida") or die('Error ' . mysqli_error($mysqli));
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

                    <!--<div class="form-group">
                        <label for="departamento">Departamento</label>
                        <select name="departamento" id="departamento" class="form-control">
                            <option value="">Seleccione un departamento</option>
                            <?php /*
                            $query_dep = mysqli_query($mysqli, "SELECT id_departamento, dep_descripcion FROM departamento ORDER BY id_departamento ASC") or die('Error ' . mysqli_error($mysqli));
                            while ($data_dep = mysqli_fetch_assoc($query_dep)) {
                                echo "<option value=\"$data_dep[id_departamento]\">$data_dep[id_departamento] | $data_dep[dep_descripcion]</option>";
                            }*/
                            ?>
                        </select>
                    </div>-->

                    <div class="form-group">
                        <label for="descrip_umedida">Descripción de la unidad de medida</label>
                        <input type="text" class="form-control" id="descrip_umedida" name="descrip_umedida" placeholder="Ingrese descripcion del Deposito " required>
                    </div>

                    <button type="submit" class="btn btn-primary" name="Guardar">Guardar</button>
                    <a href="view.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
<?php
} elseif (isset($_GET['form_umedida']) && $_GET['form'] == 'edit') { 
    if (isset($_GET['id'])) {
        // Consultar los datos de la ciudad
        $query = mysqli_query($mysqli, "SELECT * FROM u_medida where id_u_medida = '$_GET[id]'") or die('Error: ' . mysqli_error($mysqli));
        $data = mysqli_fetch_assoc($query);
    }
    ?>
    <div class="container-fluid">
        <!-- Encabezado de página -->
        <h1 class="h3 mb-4 text-gray-800">
            <i class="fas fa-edit"></i> Modificar unidad de medida
        </h1>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../../index.html">Inicio</a></li>
            <li class="breadcrumb-item"><a href="view.php">Unidad de Medida</a></li>
            <li class="breadcrumb-item active">Modificar</li>
        </ol>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="proses.php?act=update" method="POST">
                    <div class="form-group">
                        <label for="codigo">Código</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo $data['id_u_medida']; ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="u_descrip">Descripción</label>
                        <input type="text" class="form-control" id="u_descrip" name="u_descrip" value="<?php echo $data['u_descrip']; ?>" required>
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
