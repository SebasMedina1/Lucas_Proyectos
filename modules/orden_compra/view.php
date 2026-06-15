<?php
// Iniciar la sesión
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

if (
    isset($_GET['form_orden'], $_GET['form'], $_GET['orden_id']) &&
    $_GET['form_orden'] === 'edit' &&
    $_GET['form'] === 'edit'
) {
    // Conectar si hace falta
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    $stmt = $pdo->prepare("
        SELECT orden_estado
          FROM orden_de_compra
         WHERE id_orden_compra = :id
         LIMIT 1
    ");
    $stmt->execute([':id' => (int)$_GET['orden_id']]);
    $estado = $stmt->fetchColumn();

    if ($estado === false) {
        header("Location: view.php?alert=4"); // no existe
        exit;
    }

    if (strtoupper(trim((string)$estado)) !== 'EMITIDA') {
        header("Location: view.php?alert=5"); // no editable
        exit;
    }
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
    $permisoAcceso = (int)($auth_user['id_cargo'] ?? 0);

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
$page_title = 'Ordenes de Compras';
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
<?php
if (isset($_GET['form_orden']) && in_array($_GET['form'] ?? '', ['add','edit'])):
?>
    <?php include "form.php"; ?>
<?php else: ?>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Orden de Compras</h1>
        <a href="?form_orden=add&form=add" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Nueva Orden
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
                $alertMessage = "La orden no se puede editar. Solo se pueden editar órdenes en estado EMITIDA.";
                $alertClass = 'alert-warning text-dark'; 
            } elseif ($_GET['alert'] == 6) {
                $alertMessage = "No se encontraron ordenes con ese estado.";
                $alertClass = 'alert-warning'; 
            } elseif ($_GET['alert'] == 7) {
                $alertMessage = "Ya esta vinculado ese presupuesto de compra para ese proveedor .";
                $alertClass = 'alert-warning'; 
            } elseif ($_GET['alert'] == 8) {
                // Si hay un mensaje personalizado, usarlo; sino, mensaje genérico
                $alertMessage = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : "La orden no se puede anular porque está vinculada a otros documentos.";
                $alertClass = 'alert-warning text-dark';
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
            <h6 class="m-0 font-weight-bold text-primary">Lista de Ordenes </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Estado</th>
                            <th>Condición</th>
                            <th>Proveedor</th>
                            <th>Monto total</th>
                            <th>Detalle</th>
                            <th>Usuario</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            // Consultar datos
                            $query = $pdo->query("SELECT 
                                                    oc.id_orden_compra, 
                                                    to_char(oc.orden_fecha,'YYYY-MM-DD') AS orden_fecha,
                                                    to_char(oc.orden_fecha,'HH24:MI:SS') AS orden_hora,
                                                    oc.orden_estado,
                                                    COALESCE(oc.orden_condicion, 'CONTADO') AS orden_condicion,
                                                    pv.razon_social,
                                                    oc.orden_total,
                                                    u.username
                                                FROM orden_de_compra oc
                                                JOIN presupuesto_compra pc ON oc.id_presupuesto_compra = pc.id_presupuesto_compra
                                                JOIN usuarios u ON oc.id_usuario = u.id_usuario
                                                JOIN proveedor pv ON oc.id_proveedor = pv.id_proveedor
                                                ORDER BY oc.id_orden_compra DESC");

                            // Generar filas
                            while ($data = $query->fetch(PDO::FETCH_ASSOC)) {
                                $condicion = htmlspecialchars($data['orden_condicion']);
                                echo '<tr>
                                        <td>' . htmlspecialchars($data['id_orden_compra']) . '</td>
                                        <td>' . htmlspecialchars($data['orden_fecha']) . '</td>
                                        <td>' . htmlspecialchars($data['orden_hora']) . '</td>
                                        <td>' . htmlspecialchars($data['orden_estado']) . '</td>
                                        <td>' . $condicion . '</td>
                                        <td>' . htmlspecialchars($data['razon_social']) . '</td>
                                        <td>' . htmlspecialchars($data['orden_total']) . '</td>
                                        <td>
                                            <button type="button" class="btn btn-info btn-sm btn-detalle" data-id="' . $data['id_orden_compra'] . '">
                                                Ver Detalle
                                            </button>
                                        </td>
                                        <td>' . htmlspecialchars($data['username']) . '</td>
                                        <td>
                                            <a href="?form_orden=edit&form=edit&orden_id=' . urlencode((int)$data['id_orden_compra']) . '"
                                            class="btn btn-primary btn-sm" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-danger btn-sm btn-anular-orden" 
                                                data-id="' . htmlspecialchars($data['id_orden_compra']) . '"
                                                data-num="' . htmlspecialchars($data['id_orden_compra']) . '"
                                                data-proveedor="' . htmlspecialchars($data['razon_social']) . '"
                                                title="Anular">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <a href="reporte.php?orden_id=' . htmlspecialchars($data['id_orden_compra']) . '" 
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
<?php endif; ?>

<!-- Modal de detalle -->
<div class="modal fade" id="detalleModal" tabindex="-1" aria-labelledby="detalleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detalleModalLabel">Detalle de la orden de compra</h5>
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
        </div>
    </div>
</div>

<!-- Modal de anulación -->
<div class="modal fade" id="anularOrdenModal" tabindex="-1" role="dialog" aria-labelledby="anularOrdenModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="anularOrdenModalLabel">Confirmar Anulación</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p><strong>¿Está seguro que desea anular esta orden de compra?</strong></p>
        <p class="mb-0" id="anularOrdenInfo"></p>
        <div id="anularOrdenVinculos" class="mt-3" style="display:none;">
          <div class="alert alert-warning">
            <strong>Advertencia:</strong> Esta orden está vinculada a:
            <ul id="listaVinculosOrden" class="mb-0 mt-2"></ul>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="confirmarAnularOrdenBtn">Sí, anular</button>
      </div>
    </div>
  </div>
</div>

<?php
// JavaScript específico del módulo
$inline_js = "
$(document).ready(function () {
    $('#dataTable').DataTable({
        language: {
            'decimal': '',
            'emptyTable': 'No hay datos disponibles en la tabla',
            'info': 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            'infoEmpty': 'Mostrando 0 a 0 de 0 registros',
            'infoFiltered': '(filtrado de _MAX_ registros totales)',
            'infoPostFix': '',
            'thousands': ',',
            'lengthMenu': 'Mostrar _MENU_ registros',
            'loadingRecords': 'Cargando...',
            'processing': 'Procesando...',
            'search': 'Buscar:',
            'zeroRecords': 'No se encontraron registros coincidentes',
            'paginate': {
                'first': 'Primero',
                'last': 'Último',
                'next': 'Siguiente',
                'previous': 'Anterior'
            },
            'aria': {
                'sortAscending': ': activar para ordenar de manera ascendente',
                'sortDescending': ': activar para ordenar de manera descendente'
            }
        }
    });
    
    // Ocultar el mensaje de alerta automáticamente después de 3 segundos
    setTimeout(function() {
        var alertMessage = document.getElementById('alert-message');
        if (alertMessage) {
            alertMessage.style.display = 'none';
        }
    }, 3000);
});

document.addEventListener('DOMContentLoaded', () => {
    const detalleButtons = document.querySelectorAll('.btn-detalle');
    
    detalleButtons.forEach(button => {
        button.addEventListener('click', function () {
            const pedidoId = this.getAttribute('data-id');
            
            // Realiza una solicitud AJAX para obtener los detalles
            fetch(`get_detalle.php?ped_id=\${pedidoId}`)
                .then(response => response.json())
                .then(data => {
                    const detalleBody = document.getElementById('detallePedidoBody');
                    detalleBody.innerHTML = '';

                    // Generar filas para cada detalle
                    data.forEach(detalle => {
                        const row = `<tr>
                            <td>\${detalle.producto}</td>
                            <td>\${detalle.precio}</td>
                            <td>\${detalle.cantidad}</td>
                            <td>\${detalle.subtotal}</td>
                            <td>\${detalle.iva} %</td>
                        </tr>`;
                        detalleBody.innerHTML += row;
                    });

                    // Mostrar el modal
                    $('#detalleModal').modal('show');
                })
                .catch(error => {
                    console.error('Error al obtener el detalle:', error);
                });
        });
    });

    // Anular orden con modal mejorado
    let ordenIdAnular = null;
    document.querySelectorAll('.btn-anular-orden').forEach(btn => {
        btn.addEventListener('click', function() {
            ordenIdAnular = this.getAttribute('data-id');
            const ordenNum = this.getAttribute('data-num');
            const ordenProveedor = this.getAttribute('data-proveedor');
            
            document.getElementById('anularOrdenInfo').textContent = 
                'Orden N° ' + ordenNum + ' - Proveedor: ' + ordenProveedor;
            document.getElementById('anularOrdenVinculos').style.display = 'none';
            document.getElementById('listaVinculosOrden').innerHTML = '';
            document.getElementById('confirmarAnularOrdenBtn').style.display = 'block';
            document.getElementById('confirmarAnularOrdenBtn').disabled = false;
            document.getElementById('confirmarAnularOrdenBtn').textContent = 'Sí, anular';
            
            jQuery('#anularOrdenModal').modal('show');
        });
    });

    // Confirmar anulación
    document.getElementById('confirmarAnularOrdenBtn').addEventListener('click', function() {
        if (!ordenIdAnular) return;
        
        const btn = this;
        btn.disabled = true;
        btn.textContent = 'Procesando...';
        
        // Redirigir directamente a proses.php como lo hace presupuesto
        // El servidor validará y redirigirá de vuelta con el mensaje apropiado
        window.location.href = 'proses.php?act=anular&orden_id=' + ordenIdAnular;
    });
});
";

// Incluir footer común
include '../../footer.php';
?>
