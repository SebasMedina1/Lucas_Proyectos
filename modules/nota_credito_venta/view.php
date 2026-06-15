<?php
session_start();
require "../../config/database.php";

if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}

$username = $_SESSION['username'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $query = $pdo->prepare("SELECT * FROM usuarios WHERE username = :username LIMIT 1");
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->execute();

    $auth_user = $query->fetch(PDO::FETCH_ASSOC);

    if (!$auth_user) {
        session_destroy();
        echo "<script>
                alert('Usuario no encontrado, serás redirigido al inicio de sesión');
                window.location.href = '../../login.html';
              </script>";
        exit();
    }

    $permisoAcceso = isset($auth_user['id_cargo']) ? (int)$auth_user['id_cargo'] : 0;

} catch (PDOException $e) {
    die("Error en la conexión a la base de datos: " . $e->getMessage());
}

// Configuración para el layout común
$BASE_PATH = '../../';
$page_title = 'Notas de Crédito Venta';
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
    <?php if (!isset($_GET['nueva_nota']) || ($_GET['form'] ?? '') !== 'add'): ?>
        <!-- LISTA -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Notas de Crédito Venta</h1>
            <a href="?nueva_nota=add&form=add" class="btn btn-primary btn-sm shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> Nueva Nota
            </a>
        </div>

        <?php 
        if (!empty($_GET['alert'])) {
            $alertMessage = '';
            $alertClass = 'alert-success';
            if ($_GET['alert'] == 1) {
                $alertMessage = "Nota registrada correctamente.";
            } elseif ($_GET['alert'] == 2) {
                $alertMessage = "Nota modificada correctamente.";
            } elseif ($_GET['alert'] == 3) {
                $alertMessage = "Nota anulada correctamente.";
            } elseif ($_GET['alert'] == 4) {
                $alertMessage = "No se pudo realizar la operación.";
                $alertClass = 'alert-danger';
            } elseif ($_GET['alert'] == 5) {
                $alertMessage = "La nota seleccionada ya está anulada.";
                $alertClass = 'alert-danger';
            } elseif ($_GET['alert'] == 6) {
                $alertMessage = "La suma de las Notas de Crédito excede el total de la factura.";
                $alertClass = 'alert-danger';
            }
            echo "<div id='alert-message' class='alert $alertClass alert-dismissible fade show' role='alert'>";
            echo $alertMessage;
            echo "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>";
            echo "<span aria-hidden='true'>&times;</span>";
            echo "</button></div>";
        }
        ?>

        <!-- Tabla -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Lista de Notas</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>N° Nota</th>
                                <th>Estado</th>
                                <th>Cliente</th>
                                <th>Factura</th>
                                <th>Tipo</th>
                                <th>Monto Nota</th>
                                <th>Fecha</th>
                                <th>Detalle</th>
                                <th>Usuario</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $sql = "
                                    SELECT 
                                        nv.id_nota_venta,
                                        nv.nota_nro,
                                        nv.nota_venta_estado,
                                        c.cliente_nombre || ' ' || c.cliente_apellido AS cliente_nombre,
                                        fv.numero_factura,
                                        nv.nota_venta_tipo,
                                        nv.nota_total,
                                        nv.nota_venta_fecha,
                                        u.username
                                    FROM nota_venta nv
                                    JOIN usuarios u ON nv.id_usuario = u.id_usuario
                                    JOIN clientes c ON nv.id_cliente = c.id_cliente
                                    JOIN factura_ventas fv ON nv.id_factura_venta = fv.id_factura_venta
                                    ORDER BY nv.id_nota_venta DESC
                                ";
                                $query = $pdo->query($sql);

                                while ($data = $query->fetch(PDO::FETCH_ASSOC)) {
                                    $estadoClass = '';
                                    $estado = strtoupper(trim($data['nota_venta_estado']));
                                    if ($estado === 'EMITIDA') $estadoClass = 'badge-success';
                                    elseif ($estado === 'ANULADA') $estadoClass = 'badge-danger';
                                    else $estadoClass = 'badge-warning';
                                    
                                    // Solo hay Notas de Crédito
                                    $tipoClass = 'badge-primary';
                                    
                                    echo '<tr>
                                            <td>' . htmlspecialchars($data['id_nota_venta']) . '</td>
                                            <td>' . htmlspecialchars($data['nota_nro'] ?? 'N/A') . '</td>
                                            <td><span class="badge ' . $estadoClass . '">' . htmlspecialchars($data['nota_venta_estado']) . '</span></td>
                                            <td>' . htmlspecialchars($data['cliente_nombre']) . '</td>
                                            <td>' . htmlspecialchars($data['numero_factura']) . '</td>
                                            <td><span class="badge ' . $tipoClass . '">Crédito</span></td>
                                            <td>' . number_format($data['nota_total'], 0, ',', '.') . '</td>
                                            <td>' . htmlspecialchars($data['nota_venta_fecha']) . '</td>
                                            <td>
                                                <button type="button" class="btn btn-info btn-sm btn-detalle" data-id="' . htmlspecialchars($data['id_nota_venta']) . '">
                                                    Ver Detalle
                                                </button>
                                            </td>
                                            <td>' . htmlspecialchars($data['username']) . '</td>
                                            <td>
                                                <a href="reporte.php?nota_id=' . htmlspecialchars($data['id_nota_venta']) . '" target="_blank" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                            </td>
                                          </tr>';
                                }
                            } catch (PDOException $e) {
                                echo '<tr><td colspan="11" class="text-center text-danger">Error al consultar los datos: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php else: ?>
        <?php include 'form.php'; ?>
    <?php endif; ?>
</div>

<!-- Modal de detalle -->
<div class="modal fade" id="detalleModal" tabindex="-1" aria-labelledby="detalleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detalleModalLabel">Detalle de la Nota</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>IVA %</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="detalleNotaBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// JavaScript específico del módulo
$inline_js = "
document.addEventListener('DOMContentLoaded', () => {
    const alertMessage = document.getElementById('alert-message');
    if (alertMessage) setTimeout(() => alertMessage.remove(), 5000);

    if (window.jQuery && jQuery().DataTable) {
        jQuery('#dataTable').DataTable({
            language: {
                url: '{$BASE_PATH}vendor/datatables/Spanish.json'
            },
            order: [[0, 'desc']],
            pageLength: 10
        });
    }

    // Ver detalle
    document.querySelectorAll('.btn-detalle').forEach(btn => {
        btn.addEventListener('click', function() {
            const notaId = this.getAttribute('data-id');
            fetch('get_detalle.php?nota_id=' + notaId)
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('detalleNotaBody');
                    tbody.innerHTML = '';
                    if (data.success && data.detalle) {
                        data.detalle.forEach(item => {
                            const row = document.createElement('tr');
                            row.innerHTML = '<td>' + item.nombre_producto + '</td><td>' + item.nota_cantidad + '</td><td>' + item.nota_precio + '</td><td>' + item.iva_porcentaje + '%</td><td>' + item.total_linea + '</td>';
                            tbody.appendChild(row);
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan=\"5\" class=\"text-center\">No hay detalles disponibles</td></tr>';
                    }
                    jQuery('#detalleModal').modal('show');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar el detalle');
                });
        });
    });
});
";

// Incluir footer común
include '../../footer.php';
?>

