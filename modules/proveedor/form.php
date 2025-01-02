<?php 
 
// Verificar si existe el parámetro 'form' en la URL
if (isset($_GET['form_proveedor']) && $_GET['form'] == 'add') { ?>
    <div class="container-fluid">
        <!-- Encabezado de página -->
        <h1 class="h3 mb-4 text-gray-800">
            <i class="fas fa-plus-circle"></i> Agregar Proveedor
        </h1>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="view.php">Proveedor</a></li>
            <li class="breadcrumb-item active">Agregar</li>
        </ol>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="proses.php?act=insert" method="POST">
                    <?php
                    require "../../config/database.php";

                    try {
                        // Configurar conexión PDO
                        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                        $pdo = new PDO($dsn, $user, $pass);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        // Generar el código automáticamente
                        $query_id = $pdo->query("SELECT MAX(cod_proveedor) AS id FROM proveedor;");
                        $data_id = $query_id->fetch(PDO::FETCH_ASSOC);

                        if ($data_id && $data_id['id'] !== null) {
                            $codigo = $data_id['id'] + 1;
                        } else {
                            $codigo = 1;
                        }
                    } catch (PDOException $e) {
                        echo "Error: " . $e->getMessage();
                        exit;
                    }
                    ?>
                    <div class="form-group">
                        <label for="codigo">Código</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo $codigo; ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="descrip_razon">Razón Social</label>
                        <input type="text" class="form-control" id="descrip_razon" name="descrip_razon" placeholder="Ingrese la razon social " required>
                    </div>

                    <div class="form-group">
                        <label for="descrip_ruc">Ruc</label>
                        <input type="text" class="form-control" id="descrip_ruc" name="descrip_ruc" placeholder="Ingrese el ruc " required>
                    </div>

                    <div class="form-group">
                        <label for="descrip_direccion">Dirección</label>
                        <input type="text" class="form-control" id="descrip_direccion" name="descrip_direccion" placeholder="Ingrese la dirección " required>
                    </div>

                    <div class="form-group">
                        <label for="descrip_telefono">Teléfono</label>
                        <input type="text" class="form-control" id="descrip_telefono" name="descrip_telefono" placeholder="Ingrese el número de teléfono" required>
                    </div>

                    <button type="submit" class="btn btn-primary" name="Guardar">Guardar</button>
                    <a href="view.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
<?php
}
 
elseif (isset($_GET['form_proveedor']) && $_GET['form'] == 'edit') { 
    if (isset($_GET['id'])) {
        try {
            require "../../config/database.php";

            // Configurar conexión PDO
            $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Consultar los datos del proveedor
            $query = $pdo->prepare("SELECT * FROM proveedor WHERE cod_proveedor = :id");
            $query->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
            $query->execute();
            $data = $query->fetch(PDO::FETCH_ASSOC);

            if (!$data) {
                die("Proveedor no encontrado.");
            }
        } catch (PDOException $e) {
            die("Error: " . $e->getMessage());
        }
    }
    ?>
    <div class="container-fluid">
        <!-- Encabezado de página -->
        <h1 class="h3 mb-4 text-gray-800">
            <i class="fas fa-edit"></i> Modificar Proveedor
        </h1>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="view.php">Proveedor</a></li>
            <li class="breadcrumb-item active">Modificar</li>
        </ol>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="proses.php?act=update" method="POST">
                    <div class="form-group">
                        <label for="codigo">Código</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo htmlspecialchars($data['cod_proveedor']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="descrip_razon">Razón Social</label>
                        <input type="text" class="form-control" id="descrip_razon" name="descrip_razon" value="<?php echo htmlspecialchars($data['razon_social']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="descrip_ruc">RUC</label>
                        <input type="text" class="form-control" id="descrip_ruc" name="descrip_ruc" value="<?php echo htmlspecialchars($data['ruc']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="descrip_direccion">Dirección</label>
                        <input type="text" class="form-control" id="descrip_direccion" name="descrip_direccion" value="<?php echo htmlspecialchars($data['direccion']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="descrip_telefono">Teléfono</label>
                        <input type="text" class="form-control" id="descrip_telefono" name="descrip_telefono" value="<?php echo htmlspecialchars($data['telefono']); ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary" name="Guardar">Guardar</button>
                    <a href="view.php" class="btn btn-secondary">Cancelar</a>
                </form>
                
            </div>
        </div>
    </div>
<?php 
}


else { 
    // Si no existe 'form' en la URL o el valor no es válido, redirigir a la lista de ciudades
    header('Location: view.php');
}
?>
