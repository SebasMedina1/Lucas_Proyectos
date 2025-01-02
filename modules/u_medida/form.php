<?php 
// Verificar si existe el parámetro 'form' en la URL
if (isset($_GET['form_umedida']) && $_GET['form'] == 'add') { ?>
    <div class="container-fluid">
        <!-- Encabezado de página -->
        <h1 class="h3 mb-4 text-gray-800">
            <i class="fas fa-plus-circle"></i> Agregar Unidad de Medida
        </h1>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="view.php">Unidad de Medida</a></li>
            <li class="breadcrumb-item active">Agregar</li>
        </ol>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="proses.php?act=insert" method="POST">
                <?php
                require "../../config/database.php";

                try {
                    // Conexión a PostgreSQL con PDO
                    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                    $pdo = new PDO($dsn, $user, $pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Generar el código automáticamente
                    $query_id = $pdo->query("SELECT MAX(id_u_medida) AS id FROM u_medida");
                    $data_id = $query_id->fetch(PDO::FETCH_ASSOC);

                    if ($data_id['id'] !== null) {
                        $codigo = $data_id['id'] + 1;
                    } else {
                        $codigo = 1;
                    }
                } catch (PDOException $e) {
                    die("Error: " . $e->getMessage());
                }
                ?>

                    <div class="form-group">
                        <label for="codigo">Código</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo $codigo; ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="descrip_umedida">Descripción de la unidad de medida</label>
                        <input type="text" class="form-control" id="descrip_umedida" name="descrip_umedida" placeholder="Ingrese la descripción del Depósito." required>
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
        try {
            // Consultar los datos
            $query = $pdo->prepare("SELECT * FROM u_medida WHERE id_u_medida = :id");
            $query->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
            $query->execute();
    
            // Obtener los datos como un array asociativo
            $data = $query->fetch(PDO::FETCH_ASSOC);
    
            if (!$data) {
                throw new Exception("No se encontraron datos para el ID proporcionado.");
            }
        } catch (PDOException $e) {
            die("Error en la consulta: " . $e->getMessage());
        } catch (Exception $e) {
            die("Error: " . $e->getMessage());
        }
    }
    
    ?>
    <div class="container-fluid">
        <!-- Encabezado de página -->
        <h1 class="h3 mb-4 text-gray-800">
            <i class="fas fa-edit"></i> Modificar unidad de medida
        </h1>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
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
