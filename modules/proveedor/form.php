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
                        $query_id = $pdo->query("SELECT MAX(id_proveedor) AS id FROM proveedor;");
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
                        <input type="text" class="form-control" id="codigo" name="id_proveedor" value="<?php echo $codigo; ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="descrip_razon">Razón Social</label>
                        <input type="text" class="form-control" id="descrip_razon" name="razon_social" placeholder="Ingrese la razon social " required>
                    </div>

                    <div class="form-group">
                        <label for="descrip_ruc">Ruc</label>
                        <input
                            type="text"
                            class="form-control"
                            id="descrip_ruc"
                            name="ruc_proveedor"
                            placeholder="Ingrese el RUC"
                            required
                            inputmode="numeric"
                            pattern="^[0-9\-]+$"
                            title="Solo números y guion (-)"
                            >

                    </div>

                    <div class="form-group">
                        <label for="descrip_direccion">Dirección</label>
                        <input type="text" class="form-control" id="descrip_direccion" name="direccion_proveedor" placeholder="Ingrese la dirección " required>
                    </div>

                    <div class="form-group">
                        <label for="descrip_telefono">Teléfono</label>
                          <input
                            type="text"
                            class="form-control"
                            id="telefono_proveedor"
                            name="telefono_proveedor"
                            placeholder="Ingrese el número de teléfono"
                            required
                            inputmode="numeric"
                            pattern="^[0-9]+$"
                            title="Solo números permitidos"
                            maxlength="15"
                        >
                        </div>

                    <div class="form-group">
                        <label for="email_proveedor">Email</label>
                        <input type="email" class="form-control" id="email_proveedor" name="email_proveedor" placeholder="Ingrese el correo electrónico" required>
                    </div>

                    <button type="submit" class="btn btn-primary" name="Guardar">Guardar</button>
                    <a href="view.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>

    <script>
        function limpiarFormulario() {
        // Limpiar campos de texto
        document.getElementById('descrip_razon').value = '';
        document.getElementById('descrip_ruc').value = '';
        document.getElementById('descrip_direccion').value = '';
        document.getElementById('descrip_telefono').value = '';
    }

    window.onload = limpiarFormulario;


    </script>
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
            $query = $pdo->prepare("SELECT * FROM proveedor WHERE id_proveedor = :id");
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
                        <input type="text" class="form-control" id="codigo" name="id_proveedor" value="<?php echo htmlspecialchars($data['id_proveedor']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="razon_social">Razón Social</label>
                        <input type="text" class="form-control" id="razon_social" name="razon_social" value="<?php echo htmlspecialchars($data['razon_social']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="ruc_proveedor">RUC</label>
                        <input 
                            type="text"
                            class="form-control"
                            id="descrip_ruc"
                            name="ruc_proveedor"
                            placeholder="Ingrese el RUC"
                            required
                            inputmode="numeric"
                            pattern="^[0-9\-]+$"
                            title="Solo números y guion (-)"
                            value="<?php echo htmlspecialchars($data['ruc_proveedor'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="direccion_proveedor">Dirección</label>
                        <input type="text" class="form-control" id="direccion_proveedor" name="direccion_proveedor" value="<?php echo htmlspecialchars($data['direccion_proveedor'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="telefono_proveedor">Teléfono</label>
                          <input
                            type="text"
                            class="form-control"
                            id="telefono_proveedor"
                            name="telefono_proveedor"
                            placeholder="Ingrese el número de teléfono"
                            required
                            inputmode="numeric"
                            pattern="^[0-9]+$"
                            title="Solo números permitidos"
                            maxlength="15"
                            value="<?php echo htmlspecialchars($data['telefono_proveedor'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email_proveedor">Email</label>
                        <input type="email" class="form-control" id="email_proveedor" name="email_proveedor" value="<?php echo htmlspecialchars($data['email_proveedor'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="estado_proveedor">Estado</label>
                        <input type="text" class="form-control" id="estado_proveedor" name="estado_proveedor" readonly value="<?php echo htmlspecialchars($data['estado_proveedor'] ?? 'ACTIVO'); ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary" name="Guardar">Guardar</button>
                    <a href="view.php" class="btn btn-secondary">Cancelar</a>
                </form>
                
            </div>
        </div>
    </div>


    <script>
        function limpiarFormulario() {
        // Limpiar campos de texto
        document.getElementById('descrip_razon').value = '';
        document.getElementById('descrip_ruc').value = '';
        document.getElementById('descrip_direccion').value = '';
        document.getElementById('descrip_telefono').value = '';
    }

    window.onload = limpiarFormulario;


    </script>
<?php 
}


else { 
    // Si no existe 'form' en la URL o el valor no es válido, redirigir a la lista de ciudades
    header('Location: view.php');
}



?>
