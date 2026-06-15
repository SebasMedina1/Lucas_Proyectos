<?php
// Iniciar la sesión
session_start();
require "../../config/database.php";

if (
    isset($_GET['form_presupuesto'], $_GET['form'], $_GET['pre_id']) &&
    $_GET['form_presupuesto'] === 'edit' &&
    $_GET['form'] === 'edit'
) {
    // Conectar si hace falta
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    $stmt = $pdo->prepare("
        SELECT presu_estado
          FROM presupuesto_compra
         WHERE id_presupuesto_compra = :id
         LIMIT 1
    ");
    $stmt->execute([':id' => (int)$_GET['pre_id']]);
    $estado = $stmt->fetchColumn();

    if ($estado === false) {
        header("Location: view.php?alert=4"); // no existe
        exit;
    }

    // Verificar estado: debe ser EMITIDO para editar
    $estadoUpper = strtoupper(trim((string)$estado));
    if ($estadoUpper !== 'EMITIDO') {
        header("Location: view.php?alert=5"); // no editable
        exit;
    }
    
    // Verificar vínculos con orden_compra
    $stmtVinculo = $pdo->prepare("
        SELECT 1
        FROM orden_de_compra
        WHERE id_presupuesto_compra = :id
        LIMIT 1
    ");
    $stmtVinculo->execute([':id' => (int)$_GET['pre_id']]);
    if ($stmtVinculo->fetchColumn()) {
        header("Location: view.php?alert=8&msg=" . urlencode("El presupuesto está vinculado a una Orden de Compra y no puede editarse."));
        exit;
    }
}

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
$page_title = 'Presupuesto Compras';
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
// Si hay form=add o form=edit, incluimos el formulario;
// si no, mostramos la tabla (lista).
if (isset($_GET['form_presupuesto']) && in_array($_GET['form'] ?? '', ['add','edit'])):
?>
    <?php include "form.php"; ?>
<?php else: ?>
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Presupuesto Compras</h1>
        <a href="?form_presupuesto=add&form=add" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Nuevo Presupuesto
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
                // Si hay un mensaje personalizado, usarlo; sino, mensaje genérico
                $alertMessage = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : "El presupuesto no se puede anular. Solo se pueden anular presupuestos en estado EMITIDO.";
                $alertClass = 'alert-warning text-dark'; 
            } elseif ($_GET['alert'] == 6) {
                $alertMessage = "No se encontraron presuepuestos con ese estado.";
                $alertClass = 'alert-warning'; 
            } elseif ($_GET['alert'] == 7) {
                $alertMessage = "Ya existe un presupuesto con ese mismo Pedido de Compra.";
                $alertClass = 'alert-warning'; 
            } elseif ($_GET['alert'] == 8) {
                $alertMessage = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : 'El presupuesto está vinculado a una Orden de Compra y no puede editarse/anularse.';
                $alertClass = 'alert-warning';
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
            <h6 class="m-0 font-weight-bold text-primary">Lista de Presupuestos</h6>
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
                                                    pr.id_presupuesto_compra, 
                                                    to_char(pr.presu_fecha,'YYYY-MM-DD') AS pre_fecha,
                                                    to_char(pr.presu_fecha,'HH24:MI:SS') AS pre_hora,
                                                    pr.presu_total,
                                                    pr.presu_estado,
                                                    pv.razon_social,
                                                    u.username,
                                                    pc.id_pedido_compra
                                                FROM presupuesto_compra pr
                                                JOIN pedidos_compra pc ON pr.id_pedido_compra = pc.id_pedido_compra
                                                JOIN usuarios u ON pc.id_usuario = u.id_usuario
                                                JOIN proveedor pv ON pr.id_proveedor = pv.id_proveedor
                                                ORDER BY pr.id_presupuesto_compra DESC");

                            // Generar filas
                            while ($data = $query->fetch(PDO::FETCH_ASSOC)) {
                                echo '<tr>
                                        <td>' . htmlspecialchars($data['id_presupuesto_compra']) . '</td>
                                        <td>' . htmlspecialchars($data['pre_fecha']) . '</td>
                                        <td>' . htmlspecialchars($data['pre_hora']) . '</td>
                                        <td>' . htmlspecialchars($data['presu_estado']) . '</td>
                                        <td>' . htmlspecialchars($data['razon_social']) . '</td>
                                        <td>' . htmlspecialchars($data['presu_total']) . '</td>
                                        <td>
                                            <button type="button" class="btn btn-info btn-sm btn-detalle" data-id="' . $data['id_presupuesto_compra'] . '">
                                                Ver Detalle
                                            </button>
                                        </td>
                                        <td>' . htmlspecialchars($data['username']) . '</td>
                                        <td class="d-flex" style="gap:.25rem;">
                                            <a href="?form_presupuesto=edit&form=edit&pre_id=' . urlencode((int)$data['id_presupuesto_compra']) . '"
                                            class="btn btn-primary btn-sm" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-danger btn-sm btn-anular-presupuesto" 
                                                data-id="' . htmlspecialchars($data['id_presupuesto_compra']) . '"
                                                data-num="' . htmlspecialchars($data['id_presupuesto_compra']) . '"
                                                data-proveedor="' . htmlspecialchars($data['razon_social']) . '"
                                                title="Anular">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <a href="reporte.php?pre_id=' . htmlspecialchars($data['id_presupuesto_compra']) . '" 
                                                target="_blank" 
                                                class="btn btn-warning btn-sm" title="Reporte">
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detalleModalLabel">Detalle del Presupuesto</h5>
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
                            <th>Precio</th>
                            <th>Descuento</th>
                            <th>IVA</th>
                            <th>Subtotal</th>
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
<div class="modal fade" id="anularPresupuestoModal" tabindex="-1" role="dialog" aria-labelledby="anularPresupuestoModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="anularPresupuestoModalLabel">Confirmar Anulación</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p><strong>¿Está seguro que desea anular este presupuesto?</strong></p>
        <p class="mb-0" id="anularPresupuestoInfo"></p>
        <div id="anularPresupuestoVinculos" class="mt-3" style="display:none;">
          <div class="alert alert-warning">
            <strong>Advertencia:</strong> Este presupuesto está vinculado a:
            <ul id="listaVinculosPresupuesto" class="mb-0 mt-2"></ul>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="confirmarAnularPresupuestoBtn">Sí, anular</button>
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
    // Ver detalle
    const detalleButtons = document.querySelectorAll('.btn-detalle');
    detalleButtons.forEach(button => {
        button.addEventListener('click', function () {
            const presupuestoId = this.getAttribute('data-id');
            const tbody = document.getElementById('detallePedidoBody');
            tbody.innerHTML = '<tr><td colspan=\"6\" class=\"text-center\"><i class=\"fas fa-spinner fa-spin\"></i> Cargando...</td></tr>';
            
            fetch('get_detalle.php?pre_id=' + presupuestoId)
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return response.json();
                })
                .then(function(data) {
                    tbody.innerHTML = '';
                    if (data.success && data.detalle && data.detalle.length > 0) {
                        data.detalle.forEach(function(item) {
                            const cantidad = parseFloat(item.cantidad) || 0;
                            const precio = parseFloat(item.precio) || 0;
                            const descuento = parseFloat(item.descuento) || 0;
                            const iva = parseFloat(item.iva) || 0;
                            const subtotal = (cantidad * precio) - descuento;
                            
                            // Formatear números con separadores de miles (solo enteros)
                            function formatNumber(num) {
                                const numInt = Math.floor(parseFloat(num) || 0);
                                return numInt.toString().replace(/\\B(?=(\\d{3})+(?!\\d))/g, '.');
                            }
                            
                            const row = document.createElement('tr');
                            row.innerHTML = 
                                '<td>' + (item.producto || 'Sin nombre') + '</td>' +
                                '<td class=\"text-right\">' + formatNumber(cantidad) + '</td>' +
                                '<td class=\"text-right\">' + formatNumber(precio) + '</td>' +
                                '<td class=\"text-right\">' + formatNumber(descuento) + '</td>' +
                                '<td class=\"text-right\">' + formatNumber(iva) + '</td>' +
                                '<td class=\"text-right\">' + formatNumber(subtotal) + '</td>';
                            tbody.appendChild(row);
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan=\"6\" class=\"text-center text-muted\">No hay detalles disponibles</td></tr>';
                    }
                    jQuery('#detalleModal').modal('show');
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    tbody.innerHTML = '<tr><td colspan=\"6\" class=\"text-center text-danger\">Error al cargar el detalle: ' + error.message + '</td></tr>';
                    jQuery('#detalleModal').modal('show');
                });
        });
    });

    // Anular presupuesto con modal mejorado
    let presupuestoIdAnular = null;
    document.querySelectorAll('.btn-anular-presupuesto').forEach(btn => {
        btn.addEventListener('click', function() {
            presupuestoIdAnular = this.getAttribute('data-id');
            const presupuestoNum = this.getAttribute('data-num');
            const presupuestoProveedor = this.getAttribute('data-proveedor');
            
            document.getElementById('anularPresupuestoInfo').textContent = 
                'Presupuesto N° ' + presupuestoNum + ' - Proveedor: ' + presupuestoProveedor;
            document.getElementById('anularPresupuestoVinculos').style.display = 'none';
            document.getElementById('listaVinculosPresupuesto').innerHTML = '';
            
            jQuery('#anularPresupuestoModal').modal('show');
        });
    });

    // Confirmar anulación
    document.getElementById('confirmarAnularPresupuestoBtn').addEventListener('click', function() {
        if (!presupuestoIdAnular) return;
        
        const btn = this;
        btn.disabled = true;
        btn.textContent = 'Procesando...';
        
        window.location.href = 'proses.php?act=anular&pre_id=' + presupuestoIdAnular;
    });
});
";

// Incluir footer común
include '../../footer.php';
?>
