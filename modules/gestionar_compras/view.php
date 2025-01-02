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

    // Configurar excepciones para errores
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Preparar consulta para obtener datos del usuario autenticado
    $query = $pdo->prepare("SELECT * FROM usuarios WHERE username = :username");
    $query->bindParam(':username', $username, PDO::PARAM_STR);

    // Ejecutar consulta
    $query->execute();

    // Obtener los datos del usuario autenticado
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
    <meta name="description" content="">
    <meta name="author" content="">
    <title>Gestionar compras</title>

    <!-- Custom fonts for this template-->
    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../../vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">

    <link href="../../css/sb-admin-2.css" rel="stylesheet">

    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">

</head>

<body id="page-top">

    <div id="toast-container"></div>

    <!-- Page Wrapper -->
    <div id="wrapper">

 <!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

<!-- Sidebar - Brand -->
<a class="sidebar-brand d-flex align-items-center justify-content-center" href="../../index.php">
    <div class="sidebar-brand-icon rotate-n-15">
        <i class="fas fa-laugh-wink"></i>
    </div>
    <div class="sidebar-brand-text mx-3">web<sup></sup></div>
    
</a>

<!-- Divider -->
<hr class="sidebar-divider my-0">

<!-- Nav Item - Dashboard -->
<li class="nav-item active">
    <a class="nav-link" href="../../index.php">
        <i class="fas fa-fw fa-tachometer-alt"></i>
        <span>Inicio</span></a>
</li>

<!-- Divider -->
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
                    <a class="collapse-item" href="../materia_prima/view.php">Materia prima</a>
                    <a class="collapse-item" href="../proveedores/view.php">Proveedores</a>
                    <a class="collapse-item" href="../u_medida/view.php">Unidades de Medida</a>
                    <a class="collapse-item" href="../deposito/view.php">Depósito</a>
                    </div>
                </div>
</li>

<!-- Nav Item - Utilities Collapse Menu -->


<!-- Divider -->
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
                <span>Compras</span>
            </a>
            <div id="collapsePages" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar">
                <div class="bg-white py-2 collapse-inner rounded">
                    <a class="collapse-item" href="../pedido_compra/view.php">Pedidos de compras</a>
                    <a class="collapse-item" href="../presupuesto/view.php">Presupuesto</a>
                    <a class="collapse-item" href="../orden_compra/view.php">Orden de compra</a>
                    <a class="collapse-item" href="../gestionar_compras/view.php">Gestionar Compras</a>
                    <a class="collapse-item" href="../nota_credito/view.php">Nota de credito</a>
                        <a class="collapse-item" href="../nota_remision/view.php">Nota de Remision</a>
                        <a class="collapse-item" href="../ajustes/view.php">Ajustes</a>
                    <div class="collapse-divider"></div>
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
            <a class="collapse-item" href="../usuario/ciew.php">Usuarios</a>
            <a class="collapse-item" href="../reset_password/reset.php">Cambiar contraseña</a>
        </div>
    </div>
</li>


<!-- Divider -->
<hr class="sidebar-divider d-none d-md-block">

<!-- Sidebar Toggler (Sidebar) -->
<div class="text-center d-none d-md-inline">
    <button class="rounded-circle border-0" id="sidebarToggle"></button>
</div>

<!-- Sidebar Message >
<div class="sidebar-card d-none d-lg-flex">
    <img class="sidebar-card-illustration mb-2" src="img/undraw_rocket.svg" alt="...">
    <p class="text-center mb-2"><strong>SB Admin Pro</strong> is packed with premium features, components, and more!</p>
    <a class="btn btn-success btn-sm" href="https://startbootstrap.com/theme/sb-admin-pro">Upgrade to Pro!</a>
</div-->

</ul>
<!-- End of Sidebar -->

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

<!-- Main Content -->
<div id="content">

    <!-- Topbar -->
    <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

        <!-- Sidebar Toggle (Topbar) -->
        <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
            <i class="fa fa-bars"></i>
        </button>

        <!-- Topbar Search >
        <form
            class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search">
            <div class="input-group">
                <input type="text" class="form-control bg-light border-0 small" placeholder="Search for..."
                    aria-label="Search" aria-describedby="basic-addon2">
                <div class="input-group-append">
                    <button class="btn btn-primary" type="button">
                        <i class="fas fa-search fa-sm"></i>
                    </button>
                </div>
            </div>
        </form-->

        <!-- Topbar Navbar -->
        <ul class="navbar-nav ml-auto">

            <!-- Nav Item - Search Dropdown (Visible Only XS)>
            <li class="nav-item dropdown no-arrow d-sm-none">
                <a class="nav-link dropdown-toggle" href="#" id="searchDropdown" role="button"
                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-search fa-fw"></i>
                </a>
                <!-- Dropdown - Messages>
                <div class="dropdown-menu dropdown-menu-right p-3 shadow animated--grow-in"
                    aria-labelledby="searchDropdown">
                    <form class="form-inline mr-auto w-100 navbar-search">
                        <div class="input-group">
                            <input type="text" class="form-control bg-light border-0 small"
                                placeholder="Search for..." aria-label="Search"
                                aria-describedby="basic-addon2">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="button">
                                    <i class="fas fa-search fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </li>

            <!-- Nav Item - Alerts>
            <li class="nav-item dropdown no-arrow mx-1">
                <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button"
                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-bell fa-fw"></i>
                    <!-- Counter - Alerts>
                    <span class="badge badge-danger badge-counter">3+</span>
                </a>
                <!-- Dropdown - Alerts>
                <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                    aria-labelledby="alertsDropdown">
                    <h6 class="dropdown-header">
                        Alerts Center
                    </h6>
                    <a class="dropdown-item d-flex align-items-center" href="#">
                        <div class="mr-3">
                            <div class="icon-circle bg-primary">
                                <i class="fas fa-file-alt text-white"></i>
                            </div>
                        </div>
                        <div>
                            <div class="small text-gray-500">December 12, 2019</div>
                            <span class="font-weight-bold">A new monthly report is ready to download!</span>
                        </div>
                    </a>
                    <a class="dropdown-item d-flex align-items-center" href="#">
                        <div class="mr-3">
                            <div class="icon-circle bg-success">
                                <i class="fas fa-donate text-white"></i>
                            </div>
                        </div>
                        <div>
                            <div class="small text-gray-500">December 7, 2019</div>
                            $290.29 has been deposited into your account!
                        </div>
                    </a>
                    <a class="dropdown-item d-flex align-items-center" href="#">
                        <div class="mr-3">
                            <div class="icon-circle bg-warning">
                                <i class="fas fa-exclamation-triangle text-white"></i>
                            </div>
                        </div>
                        <div>
                            <div class="small text-gray-500">December 2, 2019</div>
                            Spending Alert: We've noticed unusually high spending for your account.
                        </div>
                    </a>
                    <a class="dropdown-item text-center small text-gray-500" href="#">Show All Alerts</a>
                </div>
            </li-->

            <!-- Nav Item - Messages>
            <li class="nav-item dropdown no-arrow mx-1">
                <a class="nav-link dropdown-toggle" href="#" id="messagesDropdown" role="button"
                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-envelope fa-fw"></i>
                    <!-- Counter - Messages>
                    <span class="badge badge-danger badge-counter">7</span>
                </a>
                <!-- Dropdown - Messages>
                <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                    aria-labelledby="messagesDropdown">
                    <h6 class="dropdown-header">
                        Message Center
                    </h6>
                    <a class="dropdown-item d-flex align-items-center" href="#">
                        <div class="dropdown-list-image mr-3">
                            <img class="rounded-circle" src="img/undraw_profile_1.svg"
                                alt="...">
                            <div class="status-indicator bg-success"></div>
                        </div>
                        <div class="font-weight-bold">
                            <div class="text-truncate">Hi there! I am wondering if you can help me with a
                                problem I've been having.</div>
                            <div class="small text-gray-500">Emily Fowler · 58m</div>
                        </div>
                    </a>
                    <a class="dropdown-item d-flex align-items-center" href="#">
                        <div class="dropdown-list-image mr-3">
                            <img class="rounded-circle" src="img/undraw_profile_2.svg"
                                alt="...">
                            <div class="status-indicator"></div>
                        </div>
                        <div>
                            <div class="text-truncate">I have the photos that you ordered last month, how
                                would you like them sent to you?</div>
                            <div class="small text-gray-500">Jae Chun · 1d</div>
                        </div>
                    </a>
                    <a class="dropdown-item d-flex align-items-center" href="#">
                        <div class="dropdown-list-image mr-3">
                            <img class="rounded-circle" src="img/undraw_profile_3.svg"
                                alt="...">
                            <div class="status-indicator bg-warning"></div>
                        </div>
                        <div>
                            <div class="text-truncate">Last month's report looks great, I am very happy with
                                the progress so far, keep up the good work!</div>
                            <div class="small text-gray-500">Morgan Alvarez · 2d</div>
                        </div>
                    </a>
                    <a class="dropdown-item d-flex align-items-center" href="#">
                        <div class="dropdown-list-image mr-3">
                            <img class="rounded-circle" src="https://source.unsplash.com/Mv9hjnEUHR4/60x60"
                                alt="...">
                            <div class="status-indicator bg-success"></div>
                        </div>
                        <div>
                            <div class="text-truncate">Am I a good boy? The reason I ask is because someone
                                told me that people say this to all dogs, even if they aren't good...</div>
                            <div class="small text-gray-500">Chicken the Dog · 2w</div>
                        </div>
                    </a>
                    <a class="dropdown-item text-center small text-gray-500" href="#">Read More Messages</a>
                </div>
            </li-->

            <div class="topbar-divider d-none d-sm-block"></div>

            <!-- Nav Item - User Information -->
            <li class="nav-item dropdown no-arrow">
                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                        <?php echo htmlspecialchars($auth_user['username']); ?>
                    </span>

                    <img class="img-profile rounded-circle"
                        src="../../img/undraw_profile.svg">
                </a>
                <!-- Dropdown - User Information -->
                <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                    aria-labelledby="userDropdown">
                    <a class="dropdown-item" href="../../modules/usuario/view.php">
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
    <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <!-- Verifica si se está mostrando el formulario -->
                    <?php if (!isset($_GET['gestionar_compras']) || $_GET['form'] !== 'add'): ?>
                        <!-- Page Heading -->
                        <div class="d-sm-flex align-items-center justify-content-between mb-4">
                            <h1 class="h3 mb-0 text-gray-800">Gestionar compras</h1>
                            <a href="?gestionar_compras=add&form=add" class="btn btn-primary btn-sm shadow-sm">
                                <i class="fas fa-plus fa-sm text-white-50"></i> Nueva Factura
                            </a>
                        </div>

                        <!-- Alert Messages -->
                        <?php 
                    if (!empty($_GET['alert'])) {
                        $alertMessage = '';
                        $alertClass = 'alert-success'; // Clase por defecto

                        if ($_GET['alert'] == 1) {
                            $alertMessage = "Datos registrados correctamente.";
                        } elseif ($_GET['alert'] == 2) {
                            $alertMessage = "Datos modificados correctamente.";
                        } elseif ($_GET['alert'] == 3) {
                            $alertMessage = "Registro anulado correctamente.";
                        } elseif ($_GET['alert'] == 4) {
                            $alertMessage = "No se pudo realizar la operación.";
                            $alertClass = 'alert-danger'; 
                        }

                        echo "<div id='alert-message' class='alert $alertClass alert-dismissible fade show' role='alert'>";
                        echo $alertMessage;
                        echo "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>";
                        echo "<span aria-hidden='true'>&times;</span>";
                        echo "</button>";
                        echo "</div>";
                    }
                    ?>
                    <script>
                        // Ocultar el mensaje de alerta automáticamente después de 3 segundos
                        setTimeout(function() {
                            var alertMessage = document.getElementById('alert-message');
                            if (alertMessage) {
                                alertMessage.style.display = 'none';
                            }
                        }, 3000);
                    </script>

                        <!-- DataTable -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Lista de Gestiones de compras </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Estado</th>
                                        <th>Proveedor</th>
                                        <th>Monto total</th>
                                        <th>Fecha Factura</th>
                                        <th>Detalle</th>  <!-- Nueva columna -->
                                        <th>Usuario</th>
                                        <th>Nombre</th>
                                        <th>Apellido</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    try {
                                        // Crear conexión con PostgreSQL usando PDO
                                        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                                        $pdo = new PDO($dsn, $user, $pass);
                                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                                        // Consultar datos
                                        $query = $pdo->query("SELECT 
                                                                fc.fact_id, 
                                                                fc.fact_estado,
                                                                pv.razon_social,
                                                                fc.fact_total,
                                                                fc.fact_fecha,
                                                                u.email,
                                                                pe.personal_nombre,
                                                                pe.personal_apellido
                                                            FROM facturas_compra fc
                                                            JOIN orden_compras oc ON fc.orden_id = oc.orden_id
                                                            JOIN usuarios u ON oc.id_usuario = u.id_usuario
                                                            JOIN personal pe ON u.personal_id = pe.personal_id
                                                            JOIN proveedor pv ON oc.cod_proveedor = pv.cod_proveedor
                                                            ORDER BY fc.fact_id DESC");

                                        // Generar filas
                                        while ($data = $query->fetch(PDO::FETCH_ASSOC)) {
                                            echo '<tr>
                                                    <td>' . htmlspecialchars($data['fact_id']) . '</td>
                                                    <td>' . htmlspecialchars($data['fact_estado']) . '</td>
                                                    <td>' . htmlspecialchars($data['razon_social']) . '</td>
                                                    <td>' . htmlspecialchars($data['fact_total']) . '</td>
                                                    <td>' . htmlspecialchars($data['fact_fecha']) . '</td>
                                                    <td>
                                                        <button type="button" class="btn btn-info btn-sm btn-detalle" data-id="' . $data['fact_id'] . '">
                                                            Ver Detalle
                                                        </button>
                                                    </td>
                                                    <td>' . htmlspecialchars($data['email']) . '</td>
                                                    <td>' . htmlspecialchars($data['personal_nombre']) . '</td>
                                                    <td>' . htmlspecialchars($data['personal_apellido']) . '</td>
                                                    <td>
                                                        <a href="proses.php?act=anular&fact_id=' . htmlspecialchars($data['fact_id']) . '" 
                                                            class="btn btn-danger btn-sm" 
                                                            onclick="return confirm(\'¿Seguro que deseas anular este pedido?\')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                        <a href="reporte.php?fact_id=' . htmlspecialchars($data['fact_id']) . '" 
                                                            target="_blank" 
                                                            class="btn btn-warning btn-sm">
                                                            <i class="fas fa-print"></i>
                                                        </a>
                                                    </td>
                                                </tr>';
                                        }
                                    } catch (PDOException $e) {
                                        die("Error al consultar los datos: " . $e->getMessage());
                                    }
                                    ?>
                                </tbody>

                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Incluir el formulario cuando se selecciona "Nuevo Pedido" -->
                        <?php include "form.php"; ?>
                    <?php endif; ?>
                </div>

                <!-- /.container-fluid -->
            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; web - Nicolas Dominguez - 2024</span>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->
        </div>
        <!-- End of Content Wrapper -->
    </div>
    
        <div class="modal fade" id="detalleModal" tabindex="-1" aria-labelledby="detalleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detalleModalLabel">Detalle de la Factura</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Cerrar"> <span aria-hidden="true">&times;</span> </button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio</th>
                                <th>Subtotal</th>
                                <th>Iva</th>
                            </tr>
                        </thead>
                        <tbody id="detallePedidoBody">
                            <!-- Los detalles se llenarán aquí con JavaScript -->
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                
                </div>
            </div>
        </div>
    </div>

    <!-- End of Page Wrapper -->

    <!-- Scripts -->
    <script src="../../vendor/jquery/jquery.min.js"></script>
    <script src="../../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../../js/sb-admin-2.min.js"></script>
    <script src="../../vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="../../vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script>
    $(document).ready(function () {
        $('#dataTable').DataTable({
            language: {
                "decimal": "",
                "emptyTable": "No hay datos disponibles en la tabla",
                "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
                "infoEmpty": "Mostrando 0 a 0 de 0 registros",
                "infoFiltered": "(filtrado de _MAX_ registros totales)",
                "infoPostFix": "",
                "thousands": ",",
                "lengthMenu": "Mostrar _MENU_ registros",
                "loadingRecords": "Cargando...",
                "processing": "Procesando...",
                "search": "Buscar:",
                "zeroRecords": "No se encontraron registros coincidentes",
                "paginate": {
                    "first": "Primero",
                    "last": "Último",
                    "next": "Siguiente",
                    "previous": "Anterior"
                },
                "aria": {
                    "sortAscending": ": activar para ordenar de manera ascendente",
                    "sortDescending": ": activar para ordenar de manera descendente"
                }
            }
        });
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const detalleButtons = document.querySelectorAll('.btn-detalle');
        
        detalleButtons.forEach(button => {
            button.addEventListener('click', function () {
                const pedidoId = this.getAttribute('data-id');
                
                // Realiza una solicitud AJAX para obtener los detalles
                fetch(`get_detalle.php?fact_id=${pedidoId}`)
                    .then(response => response.json())
                    .then(data => {
                        const detalleBody = document.getElementById('detallePedidoBody');
                        detalleBody.innerHTML = '';

                        // Generar filas para cada detalle
                        data.forEach(detalle => {
                            const row = `<tr>
                                <td>${detalle.producto}</td>
                                <td>${detalle.cantidad}</td>
                                <td>${detalle.precio}</td>
                                <td>${detalle.subtotal}</td>
                                <td>${detalle.iva} %</td>
                            </tr>`;
                            detalleBody.innerHTML += row;
                        });

                        // Mostrar el modal
                        new bootstrap.Modal(document.getElementById('detalleModal')).show();
                    })
                    .catch(error => {
                        console.error('Error al obtener el detalle:', error);
                    });
            });
        });
    });
</script>

</body>

</html>
