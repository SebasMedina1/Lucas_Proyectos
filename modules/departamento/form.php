<?php
require "../../config/database.php";

// Verificar si existe el parámetro 'form_departamento' en la URL
if (isset($_GET['form_departamento']) && $_GET['form'] == 'add') { ?>
    <div class="container-fluid">
        <!-- Encabezado de página -->
        <h1 class="h3 mb-4 text-gray-800">
            <i class="fas fa-plus-circle"></i> Agregar Departamento
        </h1>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="view.php">Departamento</a></li>
            <li class="breadcrumb-item active">Agregar</li>
        </ol>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="proses.php?act=insert" method="POST">
                    <?php
                    try {
                        // Conexión a PostgreSQL usando PDO
                        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                        $pdo = new PDO($dsn, $user, $pass);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        // Generar el código automáticamente
                        $query = $pdo->query("SELECT MAX(id_departamento) AS id FROM departamento");
                        $data = $query->fetch(PDO::FETCH_ASSOC);
                        $codigo = ($data['id'] !== null) ? $data['id'] + 1 : 1;
                    } catch (PDOException $e) {
                        die("Error en la conexión o consulta: " . $e->getMessage());
                    }
                    ?>
                    <div class="form-group">
                        <label for="id_departamento">Código</label>
                        <input type="text" class="form-control" id="id_departamento" name="id_departamento" value="<?php echo $codigo; ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="dep_descripcion">Descripción del Departamento</label>
                        <input type="text" class="form-control" id="dep_descripcion" name="dep_descripcion" placeholder="Ingrese descripción del departamento" required>
                    </div>

                    <button type="submit" class="btn btn-primary" name="Guardar">Guardar</button>
                    <a href="view.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
<?php
} elseif (isset($_GET['form_departamento']) && $_GET['form'] == 'edit') {
    if (isset($_GET['id'])) {
        try {
            // Conexión a PostgreSQL usando PDO
            $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Consultar los datos del departamento
            $query = $pdo->prepare("SELECT id_departamento, dep_descripcion FROM departamento WHERE id_departamento = :id");
            $query->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
            $query->execute();
            $data = $query->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Error en la conexión o consulta: " . $e->getMessage());
        }
    }
    ?>
    <div class="container-fluid">
        <!-- Encabezado de página -->
        <h1 class="h3 mb-4 text-gray-800">
            <i class="fas fa-edit"></i> Modificar Departamento
        </h1>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="view.php">Departamento</a></li>
            <li class="breadcrumb-item active">Modificar</li>
        </ol>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="proses.php?act=update" method="POST">
                    <div class="form-group">
                        <label for="id_departamento">Código</label>
                        <input type="text" class="form-control" id="id_departamento" name="id_departamento" value="<?php echo htmlspecialchars($data['id_departamento']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="dep_descripcion">Descripción</label>
                        <input type="text" class="form-control" id="dep_descripcion" name="dep_descripcion" value="<?php echo htmlspecialchars($data['dep_descripcion']); ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary" name="Guardar">Guardar</button>
                    <a href="view.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
<?php
} else {
    // Si no existe 'form' en la URL o el valor no es válido, redirigir a la lista de departamentos
    header('Location: view.php');
    exit();
}
?>
