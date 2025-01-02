<?php 


// Verificar si la sesión es válida
if (empty($_SESSION['username']) || empty($_SESSION['password'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}


// Verificar si existe el parámetro 'form' en la URL
if (isset($_GET['form_producto']) && $_GET['form'] == 'add') { ?>
    <div class="container-fluid">
        <!-- Encabezado de página -->

        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="view.php">Producto</a></li>
            <li class="breadcrumb-item active">Agregar</li>
        </ol>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="proses.php?act=insert" method="POST">
                    <?php
                    require "../../config/database.php";

                    try {
                        // Crear conexión con PostgreSQL usando PDO
                        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                        $pdo = new PDO($dsn, $user, $pass);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        // Generar el código automáticamente
                        $query_id = $pdo->query("SELECT MAX(cod_producto) as id FROM producto");
                        $data_id = $query_id->fetch(PDO::FETCH_ASSOC);

                        $codigo = ($data_id['id'] !== null) ? $data_id['id'] + 1 : 1;
                    } catch (PDOException $e) {
                        die("Error en la conexión o consulta a la base de datos: " . $e->getMessage());
                    }
                    ?>
                    <div class="col-md-4">
                        <label for="codigo">Código</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo $codigo; ?>" readonly>
                    </div>

                    <div class="col-md-6">
                        <br>
                        <label for="descrip_producto">Descripción del Producto</label>
                        <input type="text" class="form-control" id="descrip_producto" name="descrip_producto" placeholder="Ingrese descripción de la materia prima" required>
                    </div>

                    <div class="col-md-6">
                        <br>
                        <label for="descrip_precio">Precio</label>
                        <input type="text" class="form-control" id="descrip_precio" name="descrip_precio" placeholder="Ingrese el precio del producto" required>
                    </div>

                    <div class="col-md-6">
                        <br>
                        <label for="tipo_producto">Tipo Producto</label>
                        <select name="tipo_producto" id="tipo_producto" class="form-control">
                            <option value="">Seleccione el tipo de producto</option>
                            <?php 
                            try {
                                $query_dep = $pdo->query("SELECT cod_tipo_producto, t_p_descrip FROM tipo_producto ORDER BY cod_tipo_producto ASC");
                                while ($data_dep = $query_dep->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"{$data_dep['cod_tipo_producto']}\">{$data_dep['cod_tipo_producto']} | {$data_dep['t_p_descrip']}</option>";
                                }
                            } catch (PDOException $e) {
                                die("Error en la conexión o consulta a la base de datos: " . $e->getMessage());
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <br>
                        <label for="unidad_medida">Unidad de medida</label>
                        <select name="unidad_medida" id="unidad_medida" class="form-control">
                            <option value="">Seleccione una unidad de medida</option>
                            <?php 
                            try {
                                $query_dep = $pdo->query("SELECT id_u_medida, u_descrip FROM u_medida ORDER BY id_u_medida ASC");
                                while ($data_dep = $query_dep->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"{$data_dep['id_u_medida']}\">{$data_dep['id_u_medida']} | {$data_dep['u_descrip']}</option>";
                                }
                            } catch (PDOException $e) {
                                die("Error en la conexión o consulta a la base de datos: " . $e->getMessage());
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <br>
                        <label for="tipo_iva">Tipo Iva</label>
                        <select name="tipo_iva" id="tipo_iva" class="form-control">
                            <option value="">Seleccione el tipo de Iva</option>
                            <?php 
                            try {
                                $query_dep = $pdo->query("SELECT iva_id, porcentaje_tipo_iva FROM tipo_iva ORDER BY iva_id ASC");
                                while ($data_dep = $query_dep->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"{$data_dep['iva_id']}\">{$data_dep['porcentaje_tipo_iva']} %</option>";
                                }
                            } catch (PDOException $e) {
                                die("Error en la conexión o consulta a la base de datos: " . $e->getMessage());
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <br>
                        <label for="cod_deposito">Depósito, en dónde se almacenará el producto</label>
                        <select name="cod_deposito" id="cod_deposito" class="form-control">
                            <option value="">Seleccione el depósito</option>
                            <?php 
                            try {
                                $query_dep = $pdo->query("SELECT cod_deposito, descrip FROM deposito ORDER BY cod_deposito ASC");
                                while ($data_dep = $query_dep->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"{$data_dep['cod_deposito']}\">{$data_dep['descrip']}</option>";
                                }
                            } catch (PDOException $e) {
                                die("Error en la conexión o consulta a la base de datos: " . $e->getMessage());
                            }
                            ?>
                        </select>
                    </div>

                    <br>
                    <button type="submit" class="btn btn-primary" name="Guardar">Guardar</button>
                    <a href="view.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
<?php
} elseif (isset($_GET['form_producto']) && $_GET['form'] == 'edit') { 
    if (isset($_GET['id'])) {
        try {
            // Consultar los datos del producto
            $query = $pdo->prepare("SELECT * FROM producto WHERE cod_producto = :id");
            $query->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
            $query->execute();
            $data = $query->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Error en la conexión o consulta a la base de datos: " . $e->getMessage());
        }
    }
    ?>
    <div class="container-fluid">
        <!-- Encabezado de página -->
        <h1 class="h3 mb-4 text-gray-800">
            <i class="fas fa-edit"></i> Modificar Producto
        </h1>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="view.php">Producto</a></li>
            <li class="breadcrumb-item active">Modificar</li>
        </ol>

        <div class="card shadow mb-4">
            <div class="col-md-6">
                <form action="proses.php?act=update" method="POST">
                    <div class="form-group">
                        <label for="codigo">Código</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo $data['cod_producto']; ?>" readonly>
                    </div>

                    <div class="form-group">
                        
                        <label for="descrip_producto">Descripción</label>
                        <input type="text" class="form-control" id="descrip_producto" name="descrip_producto" value="<?php echo $data['p_descrip']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="descrip_precio">Precio</label>
                        <input type="text" class="form-control" id="descrip_precio" name="descrip_precio" value="<?php echo $data['precio']; ?>">
                    </div>

                    <div class="form-group">
                        <label for="tipo_producto">Tipo Producto</label>
                        <select name="tipo_producto" id="tipo_producto" class="form-control">
                            <?php 
                            try {
                                $query_dep = $pdo->query("SELECT cod_tipo_producto, t_p_descrip FROM tipo_producto ORDER BY cod_tipo_producto ASC");
                                while ($data_dep = $query_dep->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = ($data_dep['cod_tipo_producto'] == $data['cod_tipo_producto']) ? 'selected' : '';
                                    echo "<option value=\"{$data_dep['cod_tipo_producto']}\">{$data_dep['cod_tipo_producto']} | {$data_dep['t_p_descrip']}</option>";
                                }
                            } catch (PDOException $e) {
                                die("Error en la conexión o consulta a la base de datos: " . $e->getMessage());
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="unidad">Unidad de medida</label>
                        <select name="unidad" id="unidad" class="form-control">
                            
                            <?php 
                            try {
                                $query_dep = $pdo->query("SELECT id_u_medida, u_descrip FROM u_medida ORDER BY id_u_medida ASC");
                                while ($data_dep = $query_dep->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = ($data_dep['id_u_medida'] == $data['id_u_medida']) ? 'selected' : '';
                                    echo "<option value=\"{$data_dep['id_u_medida']}\">{$data_dep['id_u_medida']} | {$data_dep['u_descrip']}</option>";
                                }
                            } catch (PDOException $e) {
                                die("Error en la conexión o consulta a la base de datos: " . $e->getMessage());
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="iva">Tipo Iva</label>
                        <select name="iva" id="iva" class="form-control">
                            
                            <?php 
                            try {
                                $query_dep = $pdo->query("SELECT iva_id, porcentaje_tipo_iva FROM tipo_iva ORDER BY iva_id ASC");
                                while ($data_dep = $query_dep->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = ($data_dep['iva_id'] == $data['iva_id']) ? 'selected' : '';
                                    echo "<option value=\"{$data_dep['iva_id']}\">{$data_dep['porcentaje_tipo_iva']} %</option>";
                                }
                            } catch (PDOException $e) {
                                die("Error en la conexión o consulta a la base de datos: " . $e->getMessage());
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="cod_deposito">Depósito</label>
                        <select name="cod_deposito" id="cod_deposito" class="form-control">
                            
                        <?php 
                            try {
                                $query_dep = $pdo->query("SELECT cod_deposito, descrip FROM deposito ORDER BY cod_deposito ASC");
                                while ($data_dep = $query_dep->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = ($data_dep['cod_deposito'] == $data['cod_deposito']) ? 'selected' : '';
                                    echo "<option value=\"{$data_dep['cod_deposito']}\" $selected>{$data_dep['descrip']}</option>";
                                }
                            } catch (PDOException $e) {
                                die("Error en la conexión o consulta a la base de datos: " . $e->getMessage());
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
    exit();
}
?>
