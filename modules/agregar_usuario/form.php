<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/admin-style.css">
    <link rel="stylesheet" href="../../assets/css/formulario.css">
    <title>Editar Usuario</title>
</head>
<body>
<div class="container-fluid">

<?php 


// Verificar si existe el parámetro 'form' en la URL
if (isset($_GET['gestionar_compras']) && isset($_GET['form']) && $_GET['form'] == 'add') { ?>
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">
        <i class="fas fa-plus-circle"></i> Registrar nuevo usuario
    </h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="view.php">Users</a></li>
        <li class="breadcrumb-item active">Agregar usuarios</li>
    </ol>

    <?php
    // Incluir la conexión a la base de datos
    require_once '../../config/database.php';

    try {
        // Configuración de conexión PDO
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Obtener fecha y hora actual
        date_default_timezone_set('America/Asuncion');
        $fecha = date("Y-m-d");
        $hora = date("H:i:s");

        // Obtener el próximo ID de personal
        $query_personal = $pdo->query("SELECT COALESCE(MAX(personal_id), 0) + 1 AS next_id FROM personal");
        $personal_id = $query_personal->fetch(PDO::FETCH_ASSOC)['next_id'];

        // Obtener el próximo ID de usuarios
        $query_usuario = $pdo->query("SELECT COALESCE(MAX(id_usuario), 0) + 1 AS next_id FROM usuarios");
        $usuario_id = $query_usuario->fetch(PDO::FETCH_ASSOC)['next_id'];

    } catch (PDOException $e) {
        die("Error en la conexión a la base de datos: " . $e->getMessage());
    }
    ?>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="proses.php?act=insert_user" method="POST">
                <!-- Mostrar el próximo ID de personal y usuario -->
                <div class="row mb-3">
                    <div class="col-md-2">
                        <label for="personal_id" class="form-label">ID de Personal</label>
                        <input type="text" class="form-control" id="personal_id" name="personal_id" value="<?php echo $personal_id; ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label for="usuario_id" class="form-label">ID de Usuario</label>
                        <input type="text" class="form-control" id="usuario_id" name="usuario_id" value="<?php echo $usuario_id; ?>" readonly>
                    </div>

                    <div class="col-md-3">
                        <label for="fecha" class="form-label">Fecha</label>
                        <input type="text" class="form-control" id="fecha" name="fecha" value="<?php echo $fecha; ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="hora" class="form-label">Hora</label>
                        <input type="text" class="form-control" id="hora" name="hora" value="<?php echo $hora; ?>" readonly>
                    </div>
                </div>

                <!-- Datos Personales -->
                <h5>Datos Personales</h5>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="personal_nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="personal_nombre" name="personal_nombre" placeholder="Ingrese el nombre" required>
                    </div>
                    <div class="col-md-4">
                        <label for="personal_apellido" class="form-label">Apellido</label>
                        <input type="text" class="form-control" id="personal_apellido" name="personal_apellido" placeholder="Ingrese el apellido" required>
                    </div>
                    <div class="col-md-4">
                        <label for="personal_ci" class="form-label">Cédula</label>
                        <input type="text" class="form-control" id="personal_ci" name="personal_ci" placeholder="Ingrese la cédula" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="personal_telefono" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="personal_telefono" name="personal_telefono" placeholder="Ingrese el teléfono" required>
                    </div>
                    <div class="col-md-4">
                        <label for="personal_direccion" class="form-label">Dirección</label>
                        <input type="text" class="form-control" id="personal_direccion" name="personal_direccion" placeholder="Ingrese la dirección" required>
                    </div>
                </div>

                <!-- Datos de Usuario -->
                <h5>Datos de Usuario</h5>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="username" class="form-label">Nombre de Usuario</label>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Ingrese el nombre de usuario" autocomplete="off" required>
                    </div>
                    <div class="col-md-4">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Ingrese la contraseña" autocomplete="new-password" required>
                    </div>
                    <div class="col-md-4">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="Ingrese el correo electrónico" required>
                    </div>
                </div>




                <!-- Cargo -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="permisos_acceso" class="form-label">Permisos de Acceso</label>
                        <select class="form-control" id="permisos_acceso" name="permisos_acceso" required>
                            <option value="" selected>Seleccione un permiso</option>
                            <option value="Administrador">Administrador</option>
                            <option value="Supervisor">Supervisor</option>
                            <option value="Empleado">Empleado</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="cargo_id" class="form-label">Cargo</label>
                        <select class="form-control" id="cargo_id" name="cargo_id" required>
                            <option value="" selected disabled>Seleccione un cargo</option>
                            <?php
                            try {
                                // Obtener los cargos desde la base de datos
                                $query_cargo = $pdo->query("SELECT cargo_id, cargo_descripcion FROM cargo ORDER BY cargo_descripcion ASC");
                                while ($cargo = $query_cargo->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"{$cargo['cargo_id']}\">{$cargo['cargo_descripcion']}</option>";
                                }
                            } catch (PDOException $e) {
                                echo "Error al cargar cargos: " . $e->getMessage();
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-control" id="estado" name="estado" required>
                            <option value="ACTIVO">ACTIVO</option>
                            <option value="INACTIVO">INACTIVO</option>
                        </select>
                    </div>
                </div>

                <!-- Botones de acción -->
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-success mx-2" name="Guardar">Guardar</button>
                    <a href="view.php" class="btn btn-danger mx-2">Cerrar</a>
                </div>
            </form>
        </div>
    </div>
</div>






<?php } 








