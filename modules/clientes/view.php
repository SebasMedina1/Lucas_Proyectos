<?php
session_start();
if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}

$configPath = realpath("../../config/database.php");
if (!$configPath || !file_exists($configPath)) {
    die("Error: No se pudo encontrar el archivo de configuración.");
}
require_once $configPath;

// Validar permisos de acceso
require_once realpath("../../config/permissions.php");
check_permission('REFERENCIALES');

$username = $_SESSION['username'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $query = $pdo->prepare("SELECT * FROM usuarios WHERE username = :username");
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->execute();
    $auth_user = $query->fetch();
    if (!$auth_user) {
        session_destroy();
        echo "<script>
                alert('Usuario no encontrado, serás redirigido al inicio de sesión');
                window.location.href = '../../login.html';
              </script>";
        exit();
    }
    $permisoAcceso = (int)($auth_user['id_cargo'] ?? 0);
} catch (PDOException $e) {
    die("Error en la conexión a la base de datos: " . $e->getMessage());
}

// Configuración para el layout común
$BASE_PATH = '../../';
$page_title = 'Mantener Clientes';
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

$mostrarListado = !(isset($_GET['form_cliente']) && ($_GET['form'] === 'add' || $_GET['form'] === 'edit'));
?>

<!-- Contenido específico del módulo -->
<?php if ($mostrarListado): ?>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Mantener Clientes</h1>
        <div>
            <a href="?form_cliente=add&form=add" class="btn btn-primary btn-sm shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> Nuevo Cliente
            </a>
            <button type="button" class="btn btn-warning btn-sm shadow-sm" data-toggle="modal" data-target="#modalReporte">
                <i class="fas fa-print fa-sm text-white-50"></i> Imprimir Reporte
            </button>
        </div>
    </div>

    <?php
    if (!empty($_GET['alert'])) {
        $alertMap = [
            1 => ['msg'=>'Cliente registrado correctamente.','class'=>'alert-success'],
            2 => ['msg'=>'Cliente modificado correctamente.','class'=>'alert-success'],
            3 => ['msg'=>'Cliente eliminado correctamente.','class'=>'alert-success'],
            4 => ['msg'=>'No se pudo realizar la operación.','class'=>'alert-danger'],
            5 => ['msg'=>'Ya existe un cliente con ese RUC/CI.','class'=>'alert-danger'],
            6 => ['msg'=>'No se puede eliminar el cliente: tiene pedidos, ventas o cuentas por cobrar asociadas.','class'=>'alert-danger'],
            7 => ['msg'=>'El cliente ha sido marcado como INACTIVO.','class'=>'alert-warning'],
        ];
        if (isset($alertMap[$_GET['alert']])) {
            $data = $alertMap[$_GET['alert']];
            echo "<div id='alert-message' class='alert {$data['class']} alert-dismissible fade show' role='alert'>
                    {$data['msg']}
                    <button type='button' class='close' data-dismiss='alert' aria-label='Close'>
                      <span aria-hidden='true'>&times;</span>
                    </button>
                  </div>";
        }
    }
    ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Lista de Clientes</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre/Razón Social</th>
                            <th>Tipo</th>
                            <th>RUC/CI</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $sql = "
                                SELECT 
                                    c.id_cliente,
                                    c.cliente_nombre || ' ' || COALESCE(c.cliente_apellido, '') AS nombre_completo,
                                    c.cliente_nombre,
                                    c.cliente_apellido,
                                    COALESCE(c.tipo_cliente, 'PERSONA') AS tipo_cliente,
                                    c.cliente_ruc,
                                    COALESCE(c.cliente_ci, '') AS cliente_ci,
                                    COALESCE(c.cliente_telefono, '') AS cliente_telefono,
                                    COALESCE(c.cliente_email, '') AS cliente_email,
                                    COALESCE(c.cliente_direccion, '') AS cliente_direccion,
                                    c.cliente_estado
                                FROM clientes c
                                ORDER BY c.id_cliente DESC
                            ";
                            foreach ($pdo->query($sql) as $data) {
                                $estadoClass = '';
                                $estado = strtoupper(trim($data['cliente_estado']));
                                if ($estado === 'ACTIVO') $estadoClass = 'badge-success';
                                elseif ($estado === 'INACTIVO') $estadoClass = 'badge-warning';
                                else $estadoClass = 'badge-danger';

                                $rucCi = !empty($data['cliente_ci']) ? $data['cliente_ci'] : $data['cliente_ruc'];
                                $tipoCliente = strtoupper($data['tipo_cliente'] ?? 'PERSONA');

                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($data['id_cliente']) . "</td>";
                                echo "<td>" . htmlspecialchars($data['nombre_completo']) . "</td>";
                                echo "<td>" . htmlspecialchars($tipoCliente) . "</td>";
                                echo "<td>" . htmlspecialchars($rucCi) . "</td>";
                                echo "<td>" . htmlspecialchars($data['cliente_telefono']) . "</td>";
                                echo "<td>" . htmlspecialchars($data['cliente_email']) . "</td>";
                                echo "<td><span class='badge {$estadoClass}'>" . htmlspecialchars($estado) . "</span></td>";
                                echo "<td>";
                                echo "<a href='?form_cliente=edit&form=edit&id={$data['id_cliente']}' class='btn btn-warning btn-sm' title='Editar'>";
                                echo "<i class='fas fa-edit'></i></a> ";
                                echo "<button type='button' class='btn btn-info btn-sm btn-historial' data-id='{$data['id_cliente']}' data-nombre='" . htmlspecialchars($data['nombre_completo'], ENT_QUOTES) . "' title='Ver Historial'>";
                                echo "<i class='fas fa-history'></i></button> ";
                                echo "<button type='button' class='btn btn-danger btn-sm btn-eliminar' data-id='{$data['id_cliente']}' data-nombre='" . htmlspecialchars($data['nombre_completo'], ENT_QUOTES) . "' title='Eliminar/Inactivar'>";
                                echo "<i class='fas fa-trash'></i></button>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='8'>Error al consultar los datos: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal de Eliminación -->
    <div class="modal fade" id="eliminarModal" tabindex="-1" role="dialog" aria-labelledby="eliminarModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eliminarModalLabel">Confirmar Eliminación/Inactivación</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro de que desea eliminar el cliente <strong id="eliminarClienteNombre"></strong>?</p>
                    <div id="eliminarInfo" class="alert alert-info">
                        <small id="eliminarInfoText"></small>
                    </div>
                    <p class="text-danger"><strong>Nota:</strong> Si el cliente tiene pedidos, ventas o cuentas por cobrar asociadas, se marcará como INACTIVO en lugar de eliminarse.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmEliminarBtn">Eliminar/Inactivar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Historial -->
    <div class="modal fade" id="historialModal" tabindex="-1" role="dialog" aria-labelledby="historialModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historialModalLabel">Historial de Cambios - <span id="historialClienteNombre"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="historialContent">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="sr-only">Cargando...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Reporte -->
    <div class="modal fade" id="modalReporte" tabindex="-1" role="dialog" aria-labelledby="modalReporteLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalReporteLabel">Opciones de Reporte</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="reporte.php" method="GET" target="_blank">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="filtro_estado_reporte">Estado</label>
                            <select name="estado" id="filtro_estado_reporte" class="form-control">
                                <option value="">Todos</option>
                                <option value="ACTIVO">Activos</option>
                                <option value="INACTIVO">Inactivos</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="filtro_tipo_reporte">Tipo de Cliente</label>
                            <select name="tipo_cliente" id="filtro_tipo_reporte" class="form-control">
                                <option value="">Todos</option>
                                <option value="PERSONA">Persona</option>
                                <option value="EMPRESA">Empresa</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Generar Reporte</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php else: ?>
    <?php include 'form.php'; ?>
<?php endif; ?>

<?php include '../../footer.php'; ?>

<script>
$(document).ready(function() {
    $('#dataTable').DataTable({
        "language": {
            "url": "<?= $BASE_PATH ?>vendor/datatables/Spanish.json"
        },
        "order": [[0, "desc"]]
    });

    // Manejar clic en botón de eliminar
    $('#dataTable').on('click', '.btn-eliminar', function() {
        var clienteId = $(this).data('id');
        var nombre = $(this).data('nombre');
        
        $('#eliminarClienteNombre').text(nombre);
        $('#eliminarInfoText').text('Se verificará si el cliente tiene pedidos, ventas o cuentas por cobrar asociadas.');
        
        $('#eliminarModal').modal('show');

        $('#confirmEliminarBtn').off('click').on('click', function() {
            $.ajax({
                url: 'proses.php?act=delete',
                type: 'POST',
                data: { id: clienteId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        window.location.reload();
                    } else {
                        alert('Error: ' + (response.message || 'No se pudo eliminar el cliente'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error de comunicación: ' + error);
                }
            });
        });
    });

    // Manejar clic en botón de historial
    $('#dataTable').on('click', '.btn-historial', function() {
        var clienteId = $(this).data('id');
        var nombre = $(this).data('nombre');
        
        $('#historialClienteNombre').text(nombre);
        $('#historialContent').html('<div class="text-center"><div class="spinner-border" role="status"><span class="sr-only">Cargando...</span></div></div>');
        $('#historialModal').modal('show');

        $.ajax({
            url: 'get_historial.php',
            type: 'GET',
            data: { cliente_id: clienteId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var html = '<table class="table table-bordered table-sm">';
                    html += '<thead><tr><th>Fecha</th><th>Campo</th><th>Valor Anterior</th><th>Valor Nuevo</th><th>Acción</th><th>Usuario</th></tr></thead><tbody>';
                    
                    if (response.historial && response.historial.length > 0) {
                        response.historial.forEach(function(item) {
                            html += '<tr>';
                            html += '<td>' + item.fecha_modificacion + '</td>';
                            html += '<td>' + item.campo_modificado + '</td>';
                            html += '<td>' + (item.valor_anterior || '-') + '</td>';
                            html += '<td>' + (item.valor_nuevo || '-') + '</td>';
                            html += '<td><span class="badge badge-info">' + item.accion + '</span></td>';
                            html += '<td>' + item.username + '</td>';
                            html += '</tr>';
                        });
                    } else {
                        html += '<tr><td colspan="6" class="text-center text-muted">No hay historial disponible</td></tr>';
                    }
                    
                    html += '</tbody></table>';
                    $('#historialContent').html(html);
                } else {
                    $('#historialContent').html('<div class="alert alert-warning">' + (response.message || 'No se pudo cargar el historial') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#historialContent').html('<div class="alert alert-danger">Error al cargar el historial: ' + error + '</div>');
            }
        });
    });
});
</script>

