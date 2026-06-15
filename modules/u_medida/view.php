<?php
// Iniciar la sesión
session_start();

// Verificar si la sesión es válida
if (empty($_SESSION['username'])) {
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
    $permisoAcceso = $auth_user['id_cargo'] ?? 0;
    
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

// Configuración para el layout común
$BASE_PATH = '../../';
$page_title = 'Gestión de Unidades de Medida';
$extra_css = [
    'vendor/datatables/dataTables.bootstrap4.min.css'
];
$extra_js_plugins = [
    'vendor/datatables/jquery.dataTables.min.js',
    'vendor/datatables/dataTables.bootstrap4.min.js'
];

// Variables para el sidebar (necesarias para header.php)
$allowedCargos = [1,3,5];
$showCoreSidebar = in_array($permisoAcceso, $allowedCargos, true);
$showReportes = in_array($permisoAcceso, [3,5], true);
$showAdministracion = ($permisoAcceso === 5);

// Incluir header común
include '../../header.php';
?>

<!-- Contenido específico del módulo -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Gestión Unidad de Medida</h1>
        <a href="?form_umedida=add&form=add" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Agregar Unidad de Medida
        </a>
        <?php 
            // Verifica si los parámetros están presentes y, de ser así, incluye el archivo form.php 
            if (isset($_GET['form_umedida']) && $_GET['form'] == 'add') { 
                include "form.php"; 
            } 
        ?>
    </div>

    <?php if (!isset($_GET['form_umedida']) || ($_GET['form_umedida'] != 'add' && $_GET['form_umedida'] != 'edit')): ?>
        <a class="btn btn-warning btn-social pull-right" href="reporte.php" target="_blank">
            <i class="fa fa-print"></i> Imprimir Reporte
        </a>
    <?php endif; ?>
    <br><br>

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
            $alertMessage = "Datos eliminados correctamente.";
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

    <!-- DataTable -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Lista Unidad de Medida</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            // Consulta a la base de datos
                            $query = $pdo->query("SELECT * FROM u_medida ORDER BY id_u_medida ASC;");

                            // Iterar los resultados
                            while ($data = $query->fetch(PDO::FETCH_ASSOC)) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($data['id_u_medida']) . "</td>";
                                echo "<td>" . htmlspecialchars($data['u_descrip']) . "</td>";
                                echo "<td> 
                                    <a href='?form_umedida=edit&form=edit&id=" . htmlspecialchars($data['id_u_medida']) . "' class='btn btn-warning btn-sm'>
                                        <i class='fas fa-edit'></i> Editar 
                                    </a> 
                                    <a href='proses.php?act=delete&id=" . htmlspecialchars($data['id_u_medida']) . "' onclick='return confirm(\"¿Estás seguro/a de eliminar " . htmlspecialchars($data['u_descrip'], ENT_QUOTES) . "?\")' class='btn btn-danger btn-sm'>
                                        <i class='fas fa-trash'></i> Eliminar
                                    </a>
                                </td>";
                                echo "</tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='3' class='text-center text-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
if (isset($_GET['form_umedida']) && $_GET['form'] == 'edit') { 
    include "form.php"; 
}

$inline_js = "
// Ocultar el mensaje de alerta automáticamente después de 3 segundos
setTimeout(function() {
    var alertMessage = document.getElementById('alert-message');
    if (alertMessage) {
        alertMessage.style.display = 'none';
    }
}, 3000);

$(document).ready(function () {
    $('#dataTable').DataTable({
        language: {
            decimal: '',
            emptyTable: 'No hay datos disponibles en la tabla',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'Mostrando 0 a 0 de 0 registros',
            infoFiltered: '(filtrado de _MAX_ registros totales)',
            lengthMenu: 'Mostrar _MENU_ registros',
            loadingRecords: 'Cargando...',
            processing: 'Procesando...',
            search: 'Buscar:',
            zeroRecords: 'No se encontraron registros coincidentes',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            },
            aria: {
                sortAscending: ': activar para ordenar de manera ascendente',
                sortDescending: ': activar para ordenar de manera descendente'
            }
        }
    });
});
";
include '../../footer.php';
?>
