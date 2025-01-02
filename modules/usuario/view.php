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
require '../../config/database.php'; // Conexión PostgreSQL

$username = $_SESSION['username'];

// Consultar los datos del usuario autenticado desde la base de datos
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = :username");
$stmt->bindParam(':username', $username);
$stmt->execute();

// Obtener los datos del usuario autenticado
$auth_user = $stmt->fetch(PDO::FETCH_ASSOC);

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
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="../../index.php">
                <div class="sidebar-brand-icon rotate-n-15">
                    <i class="fas fa-laugh-wink"></i>
                </div>
                <div class="sidebar-brand-text mx-3">web</div>
            </a>
            <hr class="sidebar-divider">
            <li class="nav-item active">
                <a class="nav-link" href="view.php">
                    <i class="fas fa-user"></i>
                    <span>Perfil</span>
                </a>
            </li>
            <li class="nav-item active">
                <a class="nav-link" href="view.php">
                    <i class="fas fa-user"></i>
                    <span>Inicio</span>
                </a>
            </li>
        </ul>
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
                                    <?php echo htmlspecialchars($auth_user['name_user']); ?>
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
                                    <h4><?php echo htmlspecialchars($auth_user['name_user']); ?></h4>
                                    <p><strong>Nombre de usuario:</strong> <?php echo htmlspecialchars($auth_user['username']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($auth_user['email']); ?></p>
                                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($auth_user['telefono']); ?></p>
                                    <p><strong>Estado:</strong> <?php echo htmlspecialchars($auth_user['estado']); ?></p>
                                    <a href="../../index.php" class="btn btn-primary">Inicio</a> 
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
                        <span>Copyright &copy; web - Nicolas Dominguez - 2024</span>
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
