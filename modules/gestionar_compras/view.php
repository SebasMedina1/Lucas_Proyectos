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
$page_title = 'Gestionar Compras';
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
                $alertMessage = isset($_GET['msg']) ? htmlspecialchars(urldecode($_GET['msg'])) : "No se pudo realizar la operación.";
                $alertClass = 'alert-danger'; 
            } elseif ($_GET['alert'] == 5) {
                $alertMessage = "Ya existe ese número de factura para el mismo proveedor";
                $alertClass = 'alert-warning'; 
            } elseif ($_GET['alert'] == 6) {
                $alertMessage = "La Factura ya se encuentra finalizada/anulada.";
                $alertClass = 'alert-warning'; 
            } elseif ($_GET['alert'] == 7) {
                $alertMessage = "La Factura ya se encuentra finalizada/anulada.";
                $alertClass = 'alert-warning'; 
            } elseif ($_GET['alert'] == 8) {
                $alertMessage = isset($_GET['msg']) ? htmlspecialchars(urldecode($_GET['msg'])) : "No se puede anular la factura.";
                $alertClass = 'alert-warning';
            } elseif ($_GET['alert'] == 9) {
                $alertMessage = "Factura aprobada correctamente.";
                $alertClass = 'alert-success';
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
            <h6 class="m-0 font-weight-bold text-primary">Lista de Gestiones de compras </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Proveedor</th>
                            <th>Monto total</th>
                            <th>Nro Factura</th>
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
                                                    fc.id_factura_compra, 
                                                    fc.fac_estado,
                                                    to_char(fc.fact_fecha_compra,'YYYY-MM-DD') AS fac_fecha,
                                                    to_char(fc.fact_fecha_compra,'HH24:MI:SS') AS fac_hora,
                                                    pv.razon_social,
                                                    fc.fac_total,
                                                    fc.numero_factura,
                                                    u.username
                                                FROM factura_compra fc
                                                JOIN orden_de_compra oc ON fc.id_orden_compra = oc.id_orden_compra
                                                JOIN usuarios u ON oc.id_usuario = u.id_usuario
                                                JOIN proveedor pv ON oc.id_proveedor = pv.id_proveedor
                                                ORDER BY fc.id_factura_compra DESC");

                            // Generar filas
                            while ($data = $query->fetch(PDO::FETCH_ASSOC)) {
                                echo '<tr>
                                        <td>' . htmlspecialchars($data['id_factura_compra']) . '</td>
                                        <td>' . htmlspecialchars($data['fac_estado']) . '</td>
                                        <td>' . htmlspecialchars($data['fac_fecha']) . '</td>
                                        <td>' . htmlspecialchars($data['fac_hora']) . '</td>
                                        <td>' . htmlspecialchars($data['razon_social']) . '</td>
                                        <td>' . htmlspecialchars($data['fac_total']) . '</td>
                                        <td>' . htmlspecialchars($data['numero_factura']) . '</td>
                                        <td>
                                            <button type="button" class="btn btn-info btn-sm btn-detalle" data-id="' . $data['id_factura_compra'] . '">
                                                Ver Detalle
                                            </button>
                                        </td>
                                        <td>' . htmlspecialchars($data['username']) . '</td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm btn-anular-factura" 
                                                data-id="' . htmlspecialchars($data['id_factura_compra']) . '"
                                                data-num="' . htmlspecialchars($data['id_factura_compra']) . '"
                                                data-proveedor="' . htmlspecialchars($data['razon_social']) . '"
                                                title="Anular">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <a href="reporte.php?fac_id=' . htmlspecialchars($data['id_factura_compra']) . '" 
                                                target="_blank" 
                                                class="btn btn-warning btn-sm" title="Reporte">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <button type="button" class="btn btn-success btn-sm btn-aprobar-factura" 
                                                data-id="' . htmlspecialchars($data['id_factura_compra']) . '"
                                                data-num="' . htmlspecialchars($data['id_factura_compra']) . '"
                                                data-proveedor="' . htmlspecialchars($data['razon_social']) . '"
                                                title="Aprobar factura">
                                                <i class="fas fa-check"></i>
                                            </button>
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

<!-- Modal de detalle -->
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
        </div>
    </div>
</div>

<!-- Modal de anulación -->
<div class="modal fade" id="anularFacturaModal" tabindex="-1" role="dialog" aria-labelledby="anularFacturaModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="anularFacturaModalLabel">Confirmar Anulación</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p><strong>¿Está seguro que desea anular esta factura?</strong></p>
        <p class="mb-0" id="anularFacturaInfo"></p>
        <div id="anularFacturaVinculos" class="mt-3" style="display:none;">
          <div class="alert alert-warning">
            <strong>Advertencia:</strong> Esta factura está vinculada a:
            <ul id="listaVinculosFactura" class="mb-0 mt-2"></ul>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="confirmarAnularFacturaBtn">Sí, anular</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal de aprobación -->
<div class="modal fade" id="aprobarFacturaModal" tabindex="-1" role="dialog" aria-labelledby="aprobarFacturaModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="aprobarFacturaModalLabel">Confirmar Aprobación</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p><strong>¿Está seguro que desea aprobar esta factura?</strong></p>
        <p class="mb-0" id="aprobarFacturaInfo"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success" id="confirmarAprobarFacturaBtn">Sí, aprobar</button>
      </div>
    </div>
  </div>
</div>

<style>
    /* Modal personalizado */
    #detalleModal .modal-dialog { max-width: 55vw; }
    #detalleModal .modal-body { overflow-x: auto; } /* por si la tabla se pasa */
</style>

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
            const factId = this.getAttribute('data-id');
            
            fetch(`get_detalle.php?fact_id=\${encodeURIComponent(factId)}`)
                .then(r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(data => {
                    const cuerpo = document.getElementById('detallePedidoBody');
                    cuerpo.innerHTML = '';
                    
                    if (!Array.isArray(data) || data.length === 0) {
                        cuerpo.innerHTML = `<tr><td colspan=\"5\" class=\"text-center\">Sin detalles</td></tr>`;
                    } else {
                        data.forEach(det => {
                            const fila = `<tr>
                                <td>\${det.producto}</td>
                                <td>\${det.cantidad}</td>
                                <td>\${det.precio}</td>
                                <td>\${det.subtotal}</td>
                                <td>\${det.iva}</td>
                            </tr>`;
                            cuerpo.insertAdjacentHTML('beforeend', fila);
                        });
                    }
                    
                    // Bootstrap 4:
                    $('#detalleModal').modal('show');
                })
                .catch(err => {
                    console.error('Error al obtener el detalle:', err);
                    alert('No se pudo cargar el detalle.');
                });
        });
    });

    // Anular factura con modal
    let facturaIdAnular = null;
    document.querySelectorAll('.btn-anular-factura').forEach(btn => {
        btn.addEventListener('click', function() {
            facturaIdAnular = this.getAttribute('data-id');
            const facturaNum = this.getAttribute('data-num');
            const facturaProveedor = this.getAttribute('data-proveedor');
            
            document.getElementById('anularFacturaInfo').textContent = 
                'Factura N° ' + facturaNum + ' - Proveedor: ' + facturaProveedor;
            document.getElementById('anularFacturaVinculos').style.display = 'none';
            document.getElementById('listaVinculosFactura').innerHTML = '';
            document.getElementById('confirmarAnularFacturaBtn').style.display = 'block';
            document.getElementById('confirmarAnularFacturaBtn').disabled = false;
            document.getElementById('confirmarAnularFacturaBtn').textContent = 'Sí, anular';
            
            jQuery('#anularFacturaModal').modal('show');
        });
    });

    // Confirmar anulación
    document.getElementById('confirmarAnularFacturaBtn').addEventListener('click', function() {
        if (!facturaIdAnular) return;
        
        this.disabled = true;
        this.textContent = 'Anulando...';
        
        fetch('anular_factura.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                factura_id: facturaIdAnular
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Redirigir con mensaje de éxito
                window.location.href = 'view.php?alert=3';
            } else {
                // Redirigir con mensaje de error
                const msg = encodeURIComponent(data.message || 'Error al anular la factura');
                window.location.href = 'view.php?alert=8&msg=' + msg;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const msg = encodeURIComponent('Error al anular la factura: ' + error.message);
            window.location.href = 'view.php?alert=8&msg=' + msg;
        });
    });

    // Aprobar factura con modal
    let facturaIdAprobar = null;
    document.querySelectorAll('.btn-aprobar-factura').forEach(btn => {
        btn.addEventListener('click', function() {
            facturaIdAprobar = this.getAttribute('data-id');
            const facturaNum = this.getAttribute('data-num');
            const facturaProveedor = this.getAttribute('data-proveedor');
            
            document.getElementById('aprobarFacturaInfo').textContent = 
                'Factura N° ' + facturaNum + ' - Proveedor: ' + facturaProveedor;
            document.getElementById('confirmarAprobarFacturaBtn').disabled = false;
            document.getElementById('confirmarAprobarFacturaBtn').textContent = 'Sí, aprobar';
            
            jQuery('#aprobarFacturaModal').modal('show');
        });
    });

    // Confirmar aprobación
    document.getElementById('confirmarAprobarFacturaBtn').addEventListener('click', function() {
        if (!facturaIdAprobar) return;
        
        this.disabled = true;
        this.textContent = 'Aprobando...';
        
        // Redirigir a proses.php para aprobar
        window.location.href = 'proses.php?act=aprobar&fact_id=' + facturaIdAprobar;
    });
});
";

// Incluir footer común
include '../../footer.php';
?>
