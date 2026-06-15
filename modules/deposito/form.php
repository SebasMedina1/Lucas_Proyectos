<?php
require "../../config/database.php";

if (isset($_GET['form_deposito']) && $_GET['form'] == 'add') { ?>
    <div class="container-fluid">
        <!-- Encabezado de página -->
        <h1 class="h3 mb-4 text-gray-800">
            <i class="fas fa-plus-circle"></i> Agregar Depósito
        </h1>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="view.php">Depósitos</a></li>
            <li class="breadcrumb-item active">Agregar</li>
        </ol>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="proses.php?act=insert" method="POST">
                    <?php
                    try {
                        // Crear conexión con PostgreSQL usando PDO
                        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                        $pdo = new PDO($dsn, $user, $pass);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        // Generar el código automáticamente
                        $query = $pdo->query("SELECT MAX(deposito_id) AS id FROM deposito");
                        $data = $query->fetch(PDO::FETCH_ASSOC);
                        $codigo = ($data['id'] !== null) ? $data['id'] + 1 : 1;
                    } catch (PDOException $e) {
                        die("Error en la conexión o consulta: " . $e->getMessage());
                    }
                    ?>
                    <div class="form-group">
                        <label for="codigo">Código</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo $codigo; ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="descrip_deposito">Descripción Depósito</label>
                        <input type="text" class="form-control" id="descrip_deposito" name="descrip_deposito" placeholder="Ingrese descripción del Depósito" required>
                    </div>

                    <button type="submit" class="btn btn-primary" name="Guardar">Guardar</button>
                    <a href="view.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
<?php
} elseif (isset($_GET['form_deposito']) && $_GET['form'] == 'edit') {
    if (isset($_GET['id'])) {
        try {
            // Crear conexión con PostgreSQL usando PDO
            $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Consultar los datos del depósito
            $query = $pdo->prepare("SELECT deposito_id, deposito_descri FROM deposito WHERE deposito_id = :id");
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
            <i class="fas fa-edit"></i> Modificar Depósito
        </h1>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="view.php">Depósitos</a></li>
            <li class="breadcrumb-item active">Modificar</li>
        </ol>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="proses.php?act=update" method="POST">
                    <div class="form-group">
                        <label for="codigo">Código</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo htmlspecialchars($data['deposito_id']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="descrip">Descripción</label>
                        <input type="text" class="form-control" id="descrip" name="descrip" value="<?php echo htmlspecialchars($data['deposito_descri']); ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary" name="Guardar">Guardar</button>
                    <a href="view.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
<?php
} else {
    // Si no existe 'form' en la URL o el valor no es válido, redirigir a la lista de depósitos
    header('Location: view.php');
    exit();
}
?>
