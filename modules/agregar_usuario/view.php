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
$page_title = 'Agregar Usuario';
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
    <!-- Verifica si se está mostrando el formulario -->
    <?php if (!isset($_GET['gestionar_compras']) || $_GET['form'] !== 'add'): ?>
        <!-- Page Heading -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Agregar usuario</h1>
            <a href="?gestionar_compras=add&form=add" class="btn btn-primary btn-sm shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> Nuevo Usuario
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
            } elseif ($_GET['alert'] == 5) {
                $alertMessage = "La factura seleccionada ya está anulado.";
                $alertClass = 'alert-danger'; 
            }

            echo "<div id='alert-message' class='alert $alertClass alert-dismissible fade show' role='alert'>";
            echo htmlspecialchars($alertMessage);
            echo "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>";
            echo "<span aria-hidden='true'>&times;</span>";
            echo "</button>";
            echo "</div>";
        }
        ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Lista Usuarios</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID Usuario</th>
                                <th>Nombre de Usuario</th>
                                <th>Email</th>
                                <th>Permisos de Acceso</th>
                                <th>Estado</th>
                                <th>Switch Estado</th>
                                <th>Sucursal</th>
                                <th>ID Personal</th>
                                <th>Cargo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                // Consultar datos de usuarios y personal
                                $query = $pdo->query("SELECT u.id_usuario, u.username, u.email, u.permisos_acceso, u.estado, u.sucursal_id, pe.personal_id, c.cargo_descripcion 
                                                      FROM usuarios u 
                                                      JOIN personal pe ON u.personal_id = pe.personal_id 
                                                      JOIN cargo c ON u.cargo_id = c.cargo_id 
                                                      ORDER BY u.id_usuario ASC");

                                // Generar filas de la tabla
                                while ($data = $query->fetch(PDO::FETCH_ASSOC)) {
                                    $checked = $data['estado'] === 'ACTIVO' ? 'checked' : '';
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($data['id_usuario']) . "</td>";
                                    echo "<td>" . htmlspecialchars($data['username']) . "</td>";
                                    echo "<td>" . htmlspecialchars($data['email']) . "</td>";
                                    echo "<td>" . htmlspecialchars($data['permisos_acceso']) . "</td>";
                                    echo "<td>" . htmlspecialchars($data['estado']) . "</td>";
                                    echo "<td><input type='checkbox' class='toggle-status' data-id='" . htmlspecialchars($data['id_usuario']) . "' $checked></td>";
                                    echo "<td>" . htmlspecialchars($data['sucursal_id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($data['personal_id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($data['cargo_descripcion']) . "</td>";
                                    echo "<td><a href='form2.php?gestionar_compras=edit&id=" . htmlspecialchars($data['id_usuario']) . "' class='btn btn-info btn-sm shadow-sm'><i class='fas fa-edit fa-sm text-white-50'></i> Editar Usuario</a></td>";
                                    echo "</tr>";
                                }
                            } catch (PDOException $e) {
                                echo "<tr><td colspan='10' class='text-center text-danger'>Error al consultar los datos: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Incluir el formulario cuando se selecciona "Nuevo Usuario" -->
        <?php include "form.php"; ?>
    <?php endif; ?>
</div>

<?php
$inline_js = "
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

    // Script para manejar el switch y hacer la solicitud AJAX
    $(document).on('change', '.toggle-status', function() {
        var userId = $(this).data('id');
        var newState = $(this).is(':checked') ? 'ACTIVO' : 'INACTIVO';

        $.ajax({
            url: 'update_user_status.php',
            type: 'POST',
            data: {
                id_usuario: userId,
                estado: newState
            },
            success: function(response) {
                alert(response);
                location.reload();
            },
            error: function() {
                alert('Error al actualizar el estado del usuario.');
            }
        });
    });
});
";
include '../../footer.php';
?>
