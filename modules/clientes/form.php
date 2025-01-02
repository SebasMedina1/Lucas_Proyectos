<?php
// Verificar si existe el parámetro 'form' en la URL
if (isset($_GET['form_clientes']) && $_GET['form'] == 'add') { ?>
    <div class="container-fluid">
        <!-- Encabezado de página -->
        <h1 class="h3 mb-4 text-gray-800">
            <i class="fas fa-plus-circle"></i> Agregar Cliente
        </h1>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="view.php">Clientes</a></li>
            <li class="breadcrumb-item active">Agregar</li>
        </ol>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="proses.php?act=insert" method="POST">
                    <?php
                    require "../../config/database.php";
                    // Generar el código automáticamente
                    $query_id = mysqli_query($mysqli, "SELECT MAX(id_cliente) as id FROM clientes") or die('Error ' . mysqli_error($mysqli));
                    $count = mysqli_num_rows($query_id);
                    if ($count <> 0) {
                        $data_id = mysqli_fetch_assoc($query_id);
                        $codigo = $data_id['id'] + 1;
                    } else {
                        $codigo = 1;
                    }
                    ?>
                    <div class="form-group">
                        <label for="id_cliente">Id Cliente</label>
                        <input type="text" class="form-control" id="id_cliente" name="id_cliente"
                            value="<?php echo $codigo; ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="ci_ruc">CI o RUC</label>
                        <input type="text" class="form-control" id="ci_ruc" name="ci_ruc"
                            placeholder="Ingrese la CI o RUC del Cliente " required>
                    </div>

                    <div class="form-group">
                        <label for="cli_nombre">Nombre</label>
                        <input type="text" class="form-control" id="cli_nombre" name="cli_nombre"
                            placeholder="Ingrese Nombre del Cliente " required>
                    </div>

                    <div class="form-group">
                        <label for="cli_apellido">Apellido</label>
                        <input type="text" class="form-control" id="cli_apellido" name="cli_apellido"
                            placeholder="Ingrese Apellido del Cliente " required>
                    </div>

                    <div class="form-group">
                        <label for="cli_direccion">Direccion</label>
                        <input type="text" class="form-control" id="cli_direccion" name="cli_direccion"
                            placeholder="Ingrese Direccion del Cliente " required>
                    </div>

                    <div class="form-group">
                        <label for="cli_telefono">Telefono</label>
                        <input type="text" class="form-control" id="cli_telefono" name="cli_telefono"
                            placeholder="Ingrese Telefono del Cliente " required>
                    </div>

                    <div class="form-group">
                        <label for="cod_ciudad">Ciudad</label>
                        <select name="cod_ciudad" id="cod_ciudad" class="form-control">
                            <option value="">Seleccione una ciudad</option>
                            <?php
                            $query_dep = mysqli_query($mysqli, "SELECT cod_ciudad, descrip_ciudad FROM ciudad ORDER BY cod_ciudad ASC") or die('Error ' . mysqli_error($mysqli));
                            while ($data_dep = mysqli_fetch_assoc($query_dep)) {
                                echo "<option value=\"$data_dep[cod_ciudad]\">$data_dep[cod_ciudad] | $data_dep[descrip_ciudad]</option>";
                            }
                            ?>
                        </select>
                    </div>



                    <button type="submit" class="btn btn-primary" name="Guardar">Guardar</button>
                    <a href="view.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
    <?php
} elseif (isset($_GET['form_clientes']) && $_GET['form'] == 'edit') {
    if (isset($_GET['id'])) {
        // Consultar los datos de la ciudad
        $query = mysqli_query($mysqli, "select id_cliente, ci_ruc, cli_nombre, cli_apellido, cli_direccion, cli_telefono, cod_ciudad 
        from clientes where id_cliente = '$_GET[id]'") or die('Error: ' . mysqli_error($mysqli));
        $data = mysqli_fetch_assoc($query);
        
        $selected_cod_ciudad = $data['cod_ciudad'];
    }
    ?>
    <div class="container-fluid">
        <!-- Encabezado de página -->
        <h1 class="h3 mb-4 text-gray-800">
            <i class="fas fa-edit"></i> Modificar Cliente
        </h1>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="view.php">Depósito</a></li>
            <li class="breadcrumb-item active">Modificar</li>
        </ol>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="proses.php?act=update" method="POST">
                    <div class="form-group">
                        <label for="id_cliente">Id Cliente</label>
                        <input type="text" class="form-control" id="id_cliente" name="id_cliente"
                            value="<?php echo $data['id_cliente']; ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="ci_ruc">CI o RUC</label>
                        <input type="text" class="form-control" id="ci_ruc" name="ci_ruc"
                            value="<?php echo $data['ci_ruc']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="cli_nombre">Nombre</label>
                        <input type="text" class="form-control" id="cli_nombre" name="cli_nombre"
                            value="<?php echo $data['cli_nombre']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="cli_apellido">Apellido</label>
                        <input type="text" class="form-control" id="cli_apellido" name="cli_apellido"
                            value="<?php echo $data['cli_apellido']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="cli_direccion">Direccion</label>
                        <input type="text" class="form-control" id="cli_direccion" name="cli_direccion"
                            value="<?php echo $data['cli_direccion']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="cli_telefono">Telefono</label>
                        <input type="text" class="form-control" id="cli_telefono" name="cli_telefono"
                            value="<?php echo $data['cli_telefono']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="Ciudad">Ciudad</label>
                        <select name="cod_ciudad" id="cod_ciudad" class="form-control">
                            <option value="">Seleccione una ciudad</option>
                            <?php
                            $query_dep = mysqli_query($mysqli, "SELECT cod_ciudad, descrip_ciudad FROM ciudad ORDER BY cod_ciudad ASC") or die('Error ' . mysqli_error($mysqli));
                            while ($data_dep = mysqli_fetch_assoc($query_dep)) {
                                $selected = ($data_dep['cod_ciudad'] == $selected_cod_ciudad) ? 'selected' : ''; 
                                echo "<option value=\"$data_dep[cod_ciudad]\" $selected>$data_dep[cod_ciudad] | $data_dep[descrip_ciudad]</option>";
                            }
                            ?>
                        </select>
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