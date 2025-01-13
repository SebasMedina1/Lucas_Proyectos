<?php
// Iniciar la sesión
session_start();

// Verificar si la sesión es válida
if (empty($_SESSION['username']) || empty($_SESSION['password'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}

// Conexión a la base de datos
$file = realpath("../../config/database.php");

if (!$file || !file_exists($file)) {
    die("Error: No se pudo encontrar el archivo en la ruta $file");
}

require_once $file;

// Obtener el nombre de usuario de la sesión
$username = $_SESSION['username'];

try {
    // Crear conexión con PostgreSQL usando PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Preparar consulta para obtener datos del usuario y del personal relacionado
    $query = $pdo->prepare("
        SELECT u.*, p.personal_nombre, p.personal_apellido, p.personal_telefono, p.personal_ci, p.personal_direccion 
        FROM usuarios u
        INNER JOIN personal p ON u.personal_id = p.personal_id
        WHERE u.username = :username
    ");
    $query->bindParam(':username', $username, PDO::PARAM_STR);

    // Ejecutar consulta
    $query->execute();

    // Obtener los datos del usuario autenticado junto con los datos del personal
    $auth_user = $query->fetch(PDO::FETCH_ASSOC);

    // Verificar si se encontraron datos del usuario
    if (!$auth_user) {
        // Si no se encuentra al usuario, destruir la sesión y redirigir al login
        session_destroy();
        echo "<script>
                alert('Usuario no encontrado, serás redirigido al inicio de sesión');
                window.location.href = '../../login.html';
              </script>";
        exit();
    }
} catch (PDOException $e) {
    die("Error en la conexión a la base de datos: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Perfil de Usuario">
    <meta name="author" content="">

    <title>Perfil de Usuario</title>

    <!-- Estilos -->
    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">
</head>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="../../index.php">
                <div class="sidebar-brand-icon rotate-n-15">
                    <i class="fas fa-laugh-wink"></i>
                </div>
                <div class="sidebar-brand-text mx-3">web</div>
            </a>

            <!-- Divider -->
            <hr class="sidebar-divider my-0">

            <!-- Nav Item - Inicio -->
            <li class="nav-item active">
                <a class="nav-link" href="../../index.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Inicio</span>
                </a>
            </li>

            <!-- Nav Item - Manual de Usuario -->
            <li class="nav-item active">
                <a class="nav-link" href="./manual.pdf" target="_blank">
                    <i class="fas fa-fw fa-book"></i>
                    <span>Manual de Usuario</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Referenciales
            </div>

            <!-- Nav Item - Compras -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseCompras"
                    aria-expanded="true" aria-controls="collapseCompras">
                    <i class="fas fa-fw fa-cog"></i>
                    <span>Compras</span>
                </a>
                <div id="collapseCompras" class="collapse" aria-labelledby="headingCompras" data-parent="#accordionSidebar">
                    <div class="bg-white py-2 collapse-inner rounded">
                        <a class="collapse-item" href="../pedido_compra/view.php">Pedidos de compras</a>
                        <a class="collapse-item" href="../presupuesto/view.php">Presupuesto</a>
                        <a class="collapse-item" href="../orden_compra/view.php">Orden de compra</a>
                        <a class="collapse-item" href="../gestionar_compras/view.php">Gestionar Compras</a>
                    </div>
                </div>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Movimientos
            </div>

            <!-- Nav Item - Referenciales -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseReferenciales"
                    aria-expanded="true" aria-controls="collapseReferenciales">
                    <i class="fas fa-fw fa-folder"></i>
                    <span>Referenciales</span>
                </a>
                <div id="collapseReferenciales" class="collapse" aria-labelledby="headingReferenciales" data-parent="#accordionSidebar">
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

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Nav Item - Administración -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseAdministracion"
                    aria-expanded="true" aria-controls="collapseAdministracion">
                    <i class="fas fa-fw fa-folder"></i>
                    <span>Administración</span>
                </a>
                <div id="collapseAdministracion" class="collapse" aria-labelledby="headingAdministracion" data-parent="#accordionSidebar">
                    <div class="bg-white py-2 collapse-inner rounded">
                        <a class="collapse-item" href="../usuario/view.php">Usuarios</a>
                        <a class="collapse-item" href="../reset_password/reset.php">Cambiar contraseña</a>
                    </div>
                </div>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

        </ul>
        <!-- End of Sidebar -->

        <!-- Fin del Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">

                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <ul class="navbar-nav ml-auto">
                        <!-- Dropdown de Usuario -->
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                                    <?php echo htmlspecialchars($auth_user['username']); ?>
                                </span>
                                <img class="img-profile rounded-circle" src="../../img/undraw_profile.svg">
                            </a>
                            <!-- Dropdown - User Information -->
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="../usuario/view.php">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Perfil
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Cerrar sesión
                                </a>
                            </div>
                        </li>
                    </ul>
                </nav>
                <!-- Fin del Topbar -->

                <!-- Contenido Principal -->
                <div class="container-fluid">
                    <!-- Título de Página -->
                    <h1 class="h3 mb-4 text-gray-800">Perfil de Usuario</h1>

                    <!-- Información del Usuario -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Información Personal</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 text-center">
                                    <img class="img-profile rounded-circle" src="../../img/undraw_profile.svg" width="100%">
                                </div>
                                <div class="col-md-9">
                                <h4><?php echo htmlspecialchars(strtoupper($auth_user['personal_nombre'] . ' ' . $auth_user['personal_apellido'])); ?></h4>
                                    <p><strong>Nombre de usuario:</strong> <?php echo htmlspecialchars($auth_user['username']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($auth_user['email']); ?></p>
                                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($auth_user['personal_telefono']); ?></p>
                                    <p><strong>Cédula de Identidad:</strong> <?php echo htmlspecialchars($auth_user['personal_ci']); ?></p>
                                    <p><strong>Dirección:</strong> <?php echo htmlspecialchars($auth_user['personal_direccion']); ?></p>
                                    <p><strong>Permisos de Acceso:</strong> <?php echo htmlspecialchars($auth_user['permisos_acceso']); ?></p>
                                    <p><strong>Estado:</strong> <?php echo htmlspecialchars($auth_user['estado']); ?></p>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fin del Contenido Principal -->
            </div>
            <!-- Fin del Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; web - Nicolas Dominguez - 2025</span>
                    </div>
                </div>
            </footer>
        </div>
        <!-- Fin del Content Wrapper -->
    </div>
    <!-- Fin del Page Wrapper -->

    <!-- Modal para Cerrar Sesión -->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">¿Listo para salir?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">Selecciona "Cerrar sesión" si estás listo para finalizar tu sesión actual.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancelar</button>
                    <a class="btn btn-primary" href="../../login.html">Cerrar sesión</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../vendor/jquery/jquery.min.js"></script>
    <script src="../../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../../js/sb-admin-2.min.js"></script>
</body>

</html>
