<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/admin-style.css">
    <link rel="stylesheet" href="../../assets/css/formulario.css">

    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../../vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">

    <link href="../../css/sb-admin-2.css" rel="stylesheet">

    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <title>Editar Usuario</title>
</head>
<body>
<div id="wrapper">
    <!-- Sidebar -->
    <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

        <hr class="sidebar-divider my-0">

        <!-- Nav Item - Dashboard -->
        <li class="nav-item active">
            <a class="nav-link" href="../../index.php">
                <i class="fas fa-fw fa-tachometer-alt"></i>
                <span>Inicio</span></a>
        </li>
        <li class="nav-item active">
            <a class="nav-link" href="./manual.pdf" target="_blank">
                <i class="fas fa-fw fa-tachometer-alt"></i>
                <span>Manual de Usuario</span>
            </a>
        </li>

        <hr class="sidebar-divider">

        <!-- Heading -->
        <div class="sidebar-heading">
            Referenciales
        </div>

        <!-- Nav Item - Pages Collapse Menu -->
        <li class="nav-item">
            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseTwo"
                aria-expanded="true" aria-controls="collapseTwo">
                <i class="fas fa-fw fa-cog"></i>
                <span>Compras</span>
            </a>
            <div id="collapseTwo" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
                <div class="bg-white py-2 collapse-inner rounded">
                    <a class="collapse-item" href="../pedido_compra/view.php">Pedidos de compras</a>
                    <a class="collapse-item" href="../presupuesto/view.php">Presupuesto</a>
                    <a class="collapse-item" href="../orden_compra/view.php">Orden de compra</a>
                    <a class="collapse-item" href="../gestionar_compras/view.php">Gestionar Compras</a>
                </div>
            </div>
        </li>

        <hr class="sidebar-divider">

        <!-- Heading -->
        <div class="sidebar-heading">
            Movimientos
        </div>

        <!-- Nav Item - Pages Collapse Menu -->
        <li class="nav-item">
            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapsePages"
                aria-expanded="true" aria-controls="collapsePages">
                <i class="fas fa-fw fa-folder"></i>
                <span>Referenciales</span>
            </a>
            <div id="collapsePages" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar">
                <div class="bg-white py-2 collapse-inner rounded">
                    <!-- Categoría: Ajustes -->
                    <h6 class="collapse-header">Ajustes:</h6>
                    <a class="collapse-item" href="../ajustes/view.php">Ajuste de Inventario</a>
                    <a class="collapse-item" href="../stock/view.php">Stock</a>
                    <a class="collapse-item" href="../nota_credito/view.php">Nota Crédito</a>
                    <a class="collapse-item" href="../nota_debito/view.php">Nota Débito</a>
                    <!-- Divisor -->
                    <div class="collapse-divider"></div>
                    <!-- Categoría: Productos -->
                    <h6 class="collapse-header">Gestión de Productos:</h6>
                    <a class="collapse-item" href="../producto/view.php">Producto</a>
                    <a class="collapse-item" href="../u_medida/view.php">Unidades de Medida</a>
                    <!-- Divisor -->
                    <div class="collapse-divider"></div>
                    <!-- Categoría: Proveedores y Depósitos -->
                    <h6 class="collapse-header">Proveedores y Depósitos:</h6>
                    <a class="collapse-item" href="../proveedor/view.php">Proveedores</a>
                    <a class="collapse-item" href="../deposito/view.php">Depósito</a>
                </div>
            </div>
        </li>

        <!-- Nav Item - Pages Collapse Menu -->
        <li class="nav-item">
            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseAdm"
                aria-expanded="true" aria-controls="collapseAdm">
                <i class="fas fa-fw fa-folder"></i>
                <span>Administración</span>
            </a>
            <div id="collapseAdm" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar">
                <div class="bg-white py-2 collapse-inner rounded">
                    <a class="collapse-item" href="../usuario/view.php">Usuarios</a>
                    <a class="collapse-item" href="../reset_password/reset.php">Cambiar contraseña</a>
                </div>
            </div>
        </li>
    </ul>
    <!-- End of Sidebar -->

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <!-- Topbar -->
            <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                    <i class="fa fa-bars"></i>
                </button>
            </nav>
            <!-- End of Topbar -->

            <div class="container-fluid">
                <?php
                if (isset($_GET['gestionar_compras']) && $_GET['gestionar_compras'] == 'edit' && isset($_GET['id'])) {
                    $id_usuario = $_GET['id'];
                    require_once '../../config/database.php';
                    try {
                        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                        $pdo = new PDO($dsn, $user, $pass);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $query = $pdo->prepare("SELECT u.id_usuario, u.username, u.email, u.permisos_acceso, u.estado, u.sucursal_id, u.personal_id, p.personal_nombre, p.personal_apellido, p.personal_telefono, p.personal_ci, p.personal_direccion FROM usuarios u LEFT JOIN personal p ON u.personal_id = p.personal_id WHERE u.id_usuario = :id");
                        $query->execute(['id' => $id_usuario]);
                        $usuario = $query->fetch(PDO::FETCH_ASSOC);
                        date_default_timezone_set('America/Asuncion');
                        $fecha = date("Y-m-d");
                        $hora = date("H:i:s");
                        if ($usuario) {
                            $personal_id = $usuario['personal_id'];
                            $usuario_id = $usuario['id_usuario'];
                            $nombre = $usuario['username'];
                            $email = $usuario['email'];
                            $permisos_acceso = $usuario['permisos_acceso'];
                            $estado = $usuario['estado'];
                            $personal_nombre = $usuario['personal_nombre'];
                            $personal_apellido = $usuario['personal_apellido'];
                            $personal_telefono = $usuario['personal_telefono'];
                            $personal_ci = $usuario['personal_ci'];
                            //$cargo_descripcion = $usuario['cargo_descripcion'];
                            $personal_direccion = $usuario['personal_direccion'];
                        }
                    } catch (PDOException $e) {
                        die("Error en la conexión a la base de datos: " . $e->getMessage());
                    }
                ?>

                <h1 class="h3 mb-4 text-gray-800">
                    <i class="fas fa-edit"></i> Editar Usuario
                </h1>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
                    <li class="breadcrumb-item"><a href="view.php">Users</a></li>
                    <li class="breadcrumb-item active">Editar Usuario</li>
                </ol>

                <div class="card shadow mb-4">
                    <div class="card-body">
                        <form action="proses.php?act=update_user" method="POST">
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
                            <h5>Datos Personales</h5>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="personal_nombre" class="form-label">Nombre</label>
                                    <input type="text" class="form-control" id="personal_nombre" name="personal_nombre" value="<?php echo $personal_nombre; ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="personal_apellido" class="form-label">Apellido</label>
                                    <input type="text" class="form-control" id="personal_apellido" name="personal_apellido" value="<?php echo $personal_apellido; ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="personal_ci" class="form-label">Cédula</label>
                                    <input type="text" class="form-control" id="personal_ci" name="personal_ci" value="<?php echo $personal_ci; ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="personal_telefono" class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" id="personal_telefono" name="personal_telefono" value="<?php echo $personal_telefono; ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="personal_direccion" class="form-label">Dirección</label>
                                    <input type="text" class="form-control" id="personal_direccion" name="personal_direccion" value="<?php echo $personal_direccion; ?>" required>
                                </div>

                                <div class="col-md-4">
                                    <label for="cargo_id" class="form-label">Cargo</label>
                                    <select class="form-control" id="cargo_id" name="cargo_id" required>
                                        <?php
                                        $cargos = $pdo->query("SELECT cargo_id, cargo_descripcion FROM cargo");
                                        while ($cargo = $cargos->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = ($cargo['cargo_id'] == $cargo_id) ? 'selected' : '';
                                            echo "<option value='{$cargo['cargo_id']}' $selected>{$cargo['cargo_descripcion']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <h5>Datos de Usuario</h5>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="username" class="form-label">Nombre de Usuario</label>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo $nombre; ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="email" class="form-label">Correo Electrónico</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="permisos_acceso" class="form-label">Permisos de Acceso</label>
                                    <select class="form-control" id="permisos_acceso" name="permisos_acceso" required>
                                        <?php
                                        $permisos = ["Administrador", "Supervisor", "Empleado"];
                                        foreach ($permisos as $permiso) {
                                            $selected = ($permiso == $permisos_acceso) ? 'selected' : '';
                                            echo "<option value='$permiso' $selected>$permiso</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-success mx-2" name="Guardar">Guardar</button>
                                <a href="view.php" class="btn btn-danger mx-2">Cerrar</a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Archivos JS -->
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/admin-script.js"></script>
    <script src="../../vendor/jquery/jquery.min.js"></script>
    <script src="../../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../../js/sb-admin-2.min.js"></script>
    <script src="../../vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="../../vendor/datatables/dataTables.bootstrap4.min.js"></script>
</body>
</html>
