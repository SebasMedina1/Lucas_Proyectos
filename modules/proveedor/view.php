<?php
session_start();
require "../../config/database.php";

// Verificar si la sesión es válida
if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}

// Obtener el nombre de usuario de la sesión
$username = $_SESSION['username'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $query = $pdo->prepare("SELECT * FROM usuarios WHERE username = :username");
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->execute();

    $auth_user = $query->fetch(PDO::FETCH_ASSOC);
    $permisoAcceso = $auth_user['id_cargo'];

    if (!$auth_user) {
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
$page_title = 'Gestión de Proveedores';
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

<!-- Modal confirmación de cambio de estado -->
<div class="modal fade" id="confirmEstadoModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title">Confirmar cambio</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p id="confirmEstadoText" class="mb-0"></p>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary btn-sm" id="confirmEstadoBtn">Sí, continuar</button>
      </div>
    </div>
  </div>
</div>

<!-- Contenido específico del módulo -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Gestión de Proveedores</h1>
        <a href="?form_proveedor=add&form=add" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Agregar Proveedor
        </a>
        <?php 
            // Verifica si los parámetros están presentes y, de ser así, incluye el archivo form.php 
            if (isset($_GET['form_proveedor']) && $_GET['form'] == 'add') { include "form.php"; } 
        ?>
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
            $alertMessage = "Datos eliminados correctamente.";
        } elseif ($_GET['alert'] == 4) {
            $alertMessage = "No se pudo realizar la operación.";
            $alertClass = 'alert-danger'; 
        } elseif ($_GET['alert'] == 5) {
            $alertMessage = "Ya existe un proveedor con esos datos.";
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
    
    <a class="btn btn-warning btn-social pull-right" href="reporte.php" target="_blank">
        <i class="fa fa-print"></i> Imprimir Reporte
    </a>
    <br>
    <br>
    
    <!-- DataTable -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Lista de Proveedores</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Razón Social</th>
                            <th>Ruc</th>
                            <th>Teléfono</th>
                            <th>Dirección</th>
                            <th>Correo Electronico</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            // Consulta
                            $query = $pdo->query("SELECT * FROM proveedor ORDER BY id_proveedor ASC;");

                            while ($data = $query->fetch(PDO::FETCH_ASSOC)) {
                                $checked    = ($data['estado_proveedor'] === 'ACTIVO') ? 'checked' : '';
                                $badgeClass = ($data['estado_proveedor'] === 'ACTIVO') ? 'badge-success' : 'badge-secondary';
                                ?>
                                <tr>
                                    <td><?= $data['id_proveedor'] ?></td>
                                    <td><?= htmlspecialchars($data['razon_social']) ?></td>
                                    <td><?= htmlspecialchars($data['ruc_proveedor'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($data['telefono_proveedor'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($data['direccion_proveedor'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($data['email_proveedor'] ?? '') ?></td>

                                    <!-- Estado (switch + badge) -->
                                    <td>
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox"
                                                class="custom-control-input estado-switch"
                                                id="sw<?= $data['id_proveedor'] ?>"
                                                data-id="<?= $data['id_proveedor'] ?>"
                                                <?= $checked ?>>
                                            <label class="custom-control-label" for="sw<?= $data['id_proveedor'] ?>"></label>
                                        </div>
                                        <span class="badge <?= $badgeClass ?> estado-label"
                                            data-id="<?= $data['id_proveedor'] ?>">
                                            <?= htmlspecialchars($data['estado_proveedor'] ?? 'ACTIVO') ?>
                                        </span>
                                    </td>

                                    <!-- Acciones -->
                                    <td>
                                        <a href="?form_proveedor=edit&form=edit&id=<?= $data['id_proveedor'] ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                    </td>
                                </tr>
                                <?php
                            }
                        } catch (PDOException $e) {
                            echo "Error: " . $e->getMessage();
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
if (isset($_GET['form_proveedor']) && $_GET['form'] == 'edit') { include "form.php"; }

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

$(function () {
    let pending = null; // { id, desired, \$sw }

    // Interceptar el cambio del switch
    $(document).on('change', '.estado-switch', function (e) {
        const \$sw = $(this);
        const id  = \$sw.data('id');

        // Estado que el usuario quiere dejar
        const desired = \$sw.is(':checked') ? 'ACTIVO' : 'ANULADO';

        // Revertimos visualmente de inmediato hasta confirmar
        \$sw.prop('checked', !\$sw.is(':checked'));

        // Guardamos lo pendiente y abrimos modal
        pending = { id, desired, \$sw };
        const verb = (desired === 'ACTIVO') ? 'activar' : 'anular';
        $('#confirmEstadoText').text(`¿Seguro que deseas \${verb} el proveedor #\${id}?`);
        $('#confirmEstadoModal').modal('show');
    });

    // Confirmar cambio
    $('#confirmEstadoBtn').on('click', function () {
        if (!pending) return;

        const { id, desired, \$sw } = pending;
        $('#confirmEstadoModal').modal('hide');

        $.ajax({
            url: 'proses.php?act=toggle_estado',
            method: 'POST',
            dataType: 'json',
            data: { id: id, estado: desired }
        })
        .done(function (resp) {
            if (resp && resp.ok) {
                // Aplicar visualmente
                const checked = (desired === 'ACTIVO');
                \$sw.prop('checked', checked);

                const \$badge = $(\`.estado-label[data-id=\"\${id}\"]\`);
                \$badge
                    .text(desired)
                    .removeClass('badge-success badge-secondary')
                    .addClass(checked ? 'badge-success' : 'badge-secondary');
            } else {
                alert(resp && resp.msg ? resp.msg : 'No se pudo actualizar el estado.');
            }
        })
        .fail(function () {
            alert('Error de red al actualizar el estado.');
        })
        .always(function () { pending = null; });
    });
});
";
include '../../footer.php';
?>
