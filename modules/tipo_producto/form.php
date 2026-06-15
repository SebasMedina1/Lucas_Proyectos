<?php 
// Verificar si existe el parámetro 'form' en la URL
if (isset($_GET['form_tproducto']) && $_GET['form'] == 'add') { 
    require "../../config/database.php";
    
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Generar el código automáticamente
        $query = $pdo->query("SELECT MAX(cod_tipo_prod) as id FROM tipo_producto");
        $data_id = $query->fetch(PDO::FETCH_ASSOC);
        $codigo = ($data_id['id'] !== null) ? $data_id['id'] + 1 : 1;
    } catch (PDOException $e) {
        $codigo = 1;
    }
    ?>
    <div class="container-fluid">
        <!-- Encabezado de página -->
        <h1 class="h3 mb-4 text-gray-800">
            <i class="fas fa-plus-circle"></i> Agregar Tipo de Producto
        </h1>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="view.php">Tipo de Producto</a></li>
            <li class="breadcrumb-item active">Agregar</li>
        </ol>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="proses.php?act=insert" method="POST">
                    <div class="form-group">
                        <label for="cod_tipo_prod">Código</label>
                        <input type="text" class="form-control" id="cod_tipo_prod" name="cod_tipo_prod" value="<?php echo htmlspecialchars($codigo); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="t_p_descrip">Descripción del Tipo de Producto</label>
                        <input type="text" class="form-control" id="t_p_descrip" name="t_p_descrip" placeholder="Ingrese descripcion del tipo de producto" required>
                    </div>

                    <button type="submit" class="btn btn-primary" name="Guardar">Guardar</button>
                    <a href="view.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
<?php
} elseif (isset($_GET['form_tproducto']) && $_GET['form'] == 'edit') { 
    if (isset($_GET['id'])) {
        require "../../config/database.php";
        
        try {
            $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Consultar los datos del tipo de producto
            $query = $pdo->prepare("SELECT * FROM tipo_producto WHERE cod_tipo_prod = :id");
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
            <i class="fas fa-edit"></i> Modificar Tipo de Producto
        </h1>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="view.php">Tipo de Producto</a></li>
            <li class="breadcrumb-item active">Modificar</li>
        </ol>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="proses.php?act=update" method="POST">
                    <div class="form-group">
                        <label for="cod_tipo_prod">Código</label>
                        <input type="text" class="form-control" id="cod_tipo_prod" name="cod_tipo_prod" value="<?php echo htmlspecialchars($data['cod_tipo_prod']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="t_p_descrip">Descripción</label>
                        <input type="text" class="form-control" id="t_p_descrip" name="t_p_descrip" value="<?php echo htmlspecialchars($data['t_p_descrip']); ?>" required>
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
