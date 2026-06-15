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
check_permission('FACTURA_VENTAS');

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

$mostrarListado = !(
    isset($_GET['gestionar_ventas']) &&
    $_GET['form'] === 'add'
);

// Configuración para el layout común
$BASE_PATH = '../../';
$page_title = 'Gestionar Ventas';
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
<?php if ($mostrarListado): ?>
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Gestionar Ventas</h1>
    <div>
      <a href="?gestionar_ventas=add&form=add" class="btn btn-primary btn-sm shadow-sm">
        <i class="fas fa-plus fa-sm text-white-50"></i> Nueva Factura
      </a>
    </div>
  </div>
  <?php
  if (!empty($_GET['alert'])) {
      $alertMap = [
          1 => ['msg'=>'Factura registrada correctamente.','class'=>'alert-success'],
          2 => ['msg'=>'Factura modificada correctamente.','class'=>'alert-success'],
          3 => ['msg'=>'Factura anulada correctamente.','class'=>'alert-success'],
          4 => ['msg'=>'No se pudo realizar la operación.','class'=>'alert-danger'],
          5 => ['msg'=>'No hay caja abierta en la sucursal.','class'=>'alert-danger'],
          6 => ['msg'=>'No hay timbrado vigente disponible.','class'=>'alert-danger'],
          7 => ['msg'=>'No hay números disponibles en el timbrado.','class'=>'alert-danger'],
          8 => ['msg'=>'No hay stock suficiente para uno o más productos.','class'=>'alert-danger'],
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
      <h6 class="m-0 font-weight-bold text-primary">Lista de Facturas</h6>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
          <thead>
            <tr>
              <th>N° Factura</th>
              <th>Fecha</th>
              <th>Cliente</th>
              <th>Tipo</th>
              <th>Estado</th>
              <th>Total</th>
              <th>Usuario</th>
              <th>Detalle</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php
            try {
                $sql = "
                  SELECT 
                      fv.id_factura_venta,
                      fv.numero_factura,
                      fv.fecha_factura,
                      fv.tipo_factura,
                      fv.estado,
                      fv.total_general,
                      c.cliente_nombre || ' ' || c.cliente_apellido AS cliente_nombre,
                      u.username
                  FROM factura_ventas fv
                  JOIN clientes c ON c.id_cliente = fv.id_cliente
                  JOIN usuarios u ON u.id_usuario = fv.id_usuario
                  ORDER BY fv.id_factura_venta DESC
                ";
                foreach ($pdo->query($sql) as $data) {
                    $estadoClass = '';
                    $estado = strtoupper(trim($data['estado']));
                    if ($estado === 'EMITIDA') $estadoClass = 'badge-success';
                    elseif ($estado === 'ANULADA') $estadoClass = 'badge-danger';
                    else $estadoClass = 'badge-warning';
                    
                    $tipoClass = '';
                    $tipo = strtoupper(trim($data['tipo_factura']));
                    if ($tipo === 'CONTADO') $tipoClass = 'badge-info';
                    else $tipoClass = 'badge-primary';
                    
                    echo '<tr>
                            <td>' . htmlspecialchars($data['numero_factura'] ?? 'N/A') . '</td>
                            <td>' . htmlspecialchars($data['fecha_factura']) . '</td>
                            <td>' . htmlspecialchars($data['cliente_nombre']) . '</td>
                            <td><span class="badge ' . $tipoClass . '">' . htmlspecialchars($data['tipo_factura']) . '</span></td>
                            <td><span class="badge ' . $estadoClass . '">' . htmlspecialchars($data['estado']) . '</span></td>
                            <td>' . number_format($data['total_general'], 0, ',', '.') . '</td>
                            <td>' . htmlspecialchars($data['username']) . '</td>
                            <td><button type="button" class="btn btn-info btn-sm btn-detalle" data-id="' . htmlspecialchars($data['id_factura_venta']) . '">Ver Detalle</button></td>
                            <td>';
                    
                    // Botón reporte
                    echo '<a href="reporte.php?fact_id=' . htmlspecialchars($data['id_factura_venta']) . '" target="_blank" class="btn btn-info btn-sm" title="Imprimir"><i class="fas fa-print"></i></a> ';
                    
                    // Botón Nota de Crédito (según especificación: reemplazar "Anular" por Nota de Crédito)
                    // Solo mostrar si la factura está EMITIDA
                    if ($estado === 'EMITIDA') {
                        echo '<a href="../nota_credito_venta/form.php?fact_id=' . htmlspecialchars($data['id_factura_venta']) . '" class="btn btn-warning btn-sm" title="Emitir Nota de Crédito"><i class="fas fa-file-invoice"></i> Nota de Crédito</a>';
                    }
                    
                    echo '</td>
                          </tr>';
                }
            } catch (PDOException $e) {
                echo '<tr><td colspan="9" class="text-center text-danger">Error al consultar los datos: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
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

<!-- Modal de detalle -->
<div class="modal fade" id="detalleModal" tabindex="-1" aria-labelledby="detalleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detalleModalLabel">Detalle de la Factura</h5>
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
          <tbody id="detalleFacturaBody"></tbody>
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
      const facturaId = this.getAttribute('data-id');
      fetch('get_detalle.php?fact_id=' + facturaId)
        .then(response => response.json())
        .then(data => {
          const tbody = document.getElementById('detalleFacturaBody');
          tbody.innerHTML = '';
          if (data.success && data.detalle) {
            data.detalle.forEach(item => {
              const row = document.createElement('tr');
              row.innerHTML = '<td>' + item.nombre_producto + '</td><td>' + item.cantidad + '</td><td>' + item.precio_unitario + '</td><td>' + item.iva_porcentaje + '%</td><td>' + item.total_linea + '</td>';
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

