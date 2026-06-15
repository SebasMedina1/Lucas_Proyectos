<?php 
// Verificar si existe el parámetro 'form' en la URL
if (isset($_GET['form_ciudad']) && $_GET['form'] == 'add') { 
    require "../../config/database.php";
    
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Generar el código automáticamente
        $query = $pdo->query("SELECT MAX(cod_ciudad) as id FROM ciudad");
        $data_id = $query->fetch(PDO::FETCH_ASSOC);
        $codigo = ($data_id['id'] !== null) ? $data_id['id'] + 1 : 1;
    } catch (PDOException $e) {
        $codigo = 1;
    }
    ?>
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
                    <div class="form-group">
                        <label for="codigo">Código</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo htmlspecialchars($codigo); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="departamento">Departamento</label>
                        <select name="departamento" id="departamento" class="form-control" required>
                            <option value="">Seleccione un departamento</option>
                            <?php 
                            try {
                                $query_dep = $pdo->query("SELECT id_departamento, dep_descripcion FROM departamento ORDER BY id_departamento ASC");
                                while ($data_dep = $query_dep->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"" . htmlspecialchars($data_dep['id_departamento']) . "\">" . htmlspecialchars($data_dep['id_departamento']) . " | " . htmlspecialchars($data_dep['dep_descripcion']) . "</option>";
                                }
                            } catch (PDOException $e) {
                                echo "<option value=\"\">Error al cargar departamentos</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="descrip_ciudad">Descripción Ciudad</label>
                        <input type="text" class="form-control" id="descrip_ciudad" name="descrip_ciudad" placeholder="Ingrese descripcion de la ciudad" required>
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
        require "../../config/database.php";
        
        try {
            $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Consultar los datos de la ciudad
            $query = $pdo->prepare("SELECT cod_ciudad, descrip_ciudad, id_departamento FROM ciudad WHERE cod_ciudad = :id");
            $query->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
            $query->execute();
            $data = $query->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) {
                header('Location: view.php');
                exit();
            }
        } catch (PDOException $e) {
            header('Location: view.php');
            exit();
        }
    } else {
        header('Location: view.php');
        exit();
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
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo htmlspecialchars($data['cod_ciudad']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="descrip">Descripción</label>
                        <input type="text" class="form-control" id="descrip" name="descrip" value="<?php echo htmlspecialchars($data['descrip_ciudad']); ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary" name="Guardar">Guardar</button>
                    <a href="view.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
<?php 
} else { 
    // Si no existe 'form' en la URL o el valor no es válido, redirigir a la lista
    header('Location: view.php');
    exit();
}
?>
