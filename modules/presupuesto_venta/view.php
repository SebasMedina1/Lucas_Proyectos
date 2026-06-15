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

// Validar que solo se pueda editar presupuestos PENDIENTE/EMITIDO y que no estén convertidos
if (
    isset($_GET['form_presupuesto_venta'], $_GET['form'], $_GET['pre_id']) &&
    $_GET['form_presupuesto_venta'] === 'edit' &&
    $_GET['form'] === 'edit'
) {
    $pre_id = (int)$_GET['pre_id'];
    
    $stmt = $pdo->prepare("
        SELECT estado
          FROM presupuesto_venta
         WHERE id_presupuesto_venta = :id
         LIMIT 1
    ");
    $stmt->execute([':id' => $pre_id]);
    $estado = $stmt->fetchColumn();

    if ($estado === false || !in_array(strtoupper(trim((string)$estado)), ['PENDIENTE', 'EMITIDO'], true)) {
        header("Location: view.php?alert=5");
        exit;
    }
    
    // Verificar si fue convertido en Factura de Venta (conversión directa)
    $stFactura = $pdo->prepare("
        SELECT 1 
        FROM factura_ventas 
        WHERE id_presupuesto_venta = :id 
        LIMIT 1
    ");
    $stFactura->execute([':id' => $pre_id]);
    if ($stFactura->fetchColumn()) {
        header("Location: view.php?alert=6");
        exit;
    }
    
    // Verificar si fue convertido en Pedido de Venta (a través de presupuesto)
    // Nota: pedido_venta no tiene id_presupuesto_venta directo, pero puede estar vinculado
    // a través de factura_ventas o directamente. Por ahora verificamos solo factura_ventas.
}

$mostrarListado = !(
    isset($_GET['form_presupuesto_venta'], $_GET['form']) &&
    (
        ($_GET['form_presupuesto_venta'] === 'add' && $_GET['form'] === 'add') ||
        ($_GET['form_presupuesto_venta'] === 'edit' && $_GET['form'] === 'edit')
    )
);

// Configuración para el layout común
$BASE_PATH = '../../';
$page_title = 'Presupuestos de Ventas';
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

<!-- Modal confirmación de anulación -->
<div class="modal fade" id="confirmAnularModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Confirmar Anulación</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p><strong>¿Está seguro que desea anular este presupuesto?</strong></p>
        <p class="mb-0" id="anularPresupuestoInfo"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="confirmAnularBtn">Sí, anular</button>
      </div>
    </div>
  </div>
</div>

<!-- Contenido específico del módulo -->
<?php if ($mostrarListado): ?>
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Presupuestos de Ventas</h1>
    <div>
      <a href="?form_presupuesto_venta=add&form=add" class="btn btn-primary btn-sm shadow-sm">
        <i class="fas fa-plus fa-sm text-white-50"></i> Nuevo Presupuesto
      </a>
    </div>
  </div>
  <?php
  if (!empty($_GET['alert'])) {
      $alertMap = [
          1 => ['msg'=>'Datos registrados correctamente.','class'=>'alert-success'],
          2 => ['msg'=>'Datos modificados correctamente.','class'=>'alert-success'],
          3 => ['msg'=>'Registro anulado correctamente.','class'=>'alert-success'],
          4 => ['msg'=>'No se pudo realizar la operación.','class'=>'alert-danger'],
          5 => ['msg'=>'Solo se puede editar presupuestos en estado PENDIENTE o EMITIDO.','class'=>'alert-danger'],
          6 => ['msg'=>'No se puede anular un presupuesto que ya fue convertido en Orden.','class'=>'alert-danger'],
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
      <h6 class="m-0 font-weight-bold text-primary">Lista de Presupuestos</h6>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
          <thead>
            <tr>
              <th>Código</th>
              <th>Fecha</th>
              <th>Cliente</th>
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
                      pv.id_presupuesto_venta,
                      pv.fecha_presupuesto,
                      pv.estado,
                      c.cliente_nombre || ' ' || c.cliente_apellido AS cliente_nombre,
                      u.username,
                      COALESCE(pv.monto_total, 0) AS total
                  FROM presupuesto_venta pv
                  JOIN clientes c ON c.id_cliente = pv.id_cliente
                  JOIN usuarios u ON u.id_usuario = pv.id_usuario
                  ORDER BY pv.id_presupuesto_venta DESC
                ";
                foreach ($pdo->query($sql) as $data) {
                    $estadoClass = '';
                    $estado = strtoupper(trim($data['estado']));
                    if ($estado === 'PENDIENTE') $estadoClass = 'badge-secondary';
                    elseif ($estado === 'EMITIDO') $estadoClass = 'badge-info';
                    elseif ($estado === 'ANULADO') $estadoClass = 'badge-danger';
                    elseif (in_array($estado, ['APROBADO', 'PRESUPUESTADO'])) $estadoClass = 'badge-success';
                    else $estadoClass = 'badge-warning';
                    
                    echo '<tr>
                            <td>' . htmlspecialchars($data['id_presupuesto_venta']) . '</td>
                            <td>' . htmlspecialchars($data['fecha_presupuesto']) . '</td>
                            <td>' . htmlspecialchars($data['cliente_nombre']) . '</td>
                            <td><span class="badge ' . $estadoClass . '">' . htmlspecialchars($data['estado']) . '</span></td>
                            <td>' . number_format($data['total'], 0, ',', '.') . '</td>
                            <td>' . htmlspecialchars($data['username']) . '</td>
                            <td><button type="button" class="btn btn-info btn-sm btn-detalle" data-id="' . htmlspecialchars($data['id_presupuesto_venta']) . '">Ver Detalle</button></td>
                            <td>';
                    
                    // Solo mostrar editar si está PENDIENTE o EMITIDO
                    if (in_array($estado, ['PENDIENTE', 'EMITIDO'])) {
                        echo '<a href="?form_presupuesto_venta=edit&form=edit&pre_id=' . htmlspecialchars($data['id_presupuesto_venta']) . '" class="btn btn-warning btn-sm" title="Editar"><i class="fas fa-edit"></i></a> ';
                    }
                    
                    // Botón anular
                    if (!in_array($estado, ['ANULADO', 'APROBADO', 'PRESUPUESTADO'])) {
                        echo '<button type="button" class="btn btn-danger btn-sm btn-anular" data-id="' . htmlspecialchars($data['id_presupuesto_venta']) . '" data-num="' . htmlspecialchars($data['id_presupuesto_venta']) . '" data-cliente="' . htmlspecialchars($data['cliente_nombre']) . '" title="Anular"><i class="fas fa-times"></i></button> ';
                    }
                    
                    // Botón reporte
                    echo '<a href="reporte.php?pre_id=' . htmlspecialchars($data['id_presupuesto_venta']) . '" target="_blank" class="btn btn-info btn-sm" title="Imprimir"><i class="fas fa-print"></i></a>';
                    
                    echo '</td>
                          </tr>';
                }
            } catch (PDOException $e) {
                echo '<tr><td colspan="8" class="text-center text-danger">Error al consultar los datos: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
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
              <th>Precio Unitario</th>
              <th>IVA</th>
              <th>Subtotal</th>
            </tr>
          </thead>
          <tbody id="detallePresupuestoBody"></tbody>
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
  if (alertMessage) setTimeout(() => alertMessage.remove(), 3000);

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
      const presupuestoId = this.getAttribute('data-id');
      fetch('get_detalle.php?pre_id=' + presupuestoId)
        .then(response => response.json())
        .then(data => {
          const tbody = document.getElementById('detallePresupuestoBody');
          tbody.innerHTML = '';
          if (data.success && data.detalle) {
            data.detalle.forEach(item => {
              const row = document.createElement('tr');
              row.innerHTML = '<td>' + item.nombre_producto + '</td><td>' + item.cantidad + '</td><td>' + item.precio_unitario + '</td><td>' + item.iva_aplicado + '</td><td>' + item.subtotal + '</td>';
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

  // Anular presupuesto
  let presupuestoIdAnular = null;
  document.querySelectorAll('.btn-anular').forEach(btn => {
    btn.addEventListener('click', function() {
      presupuestoIdAnular = this.getAttribute('data-id');
      const preNum = this.getAttribute('data-num') || presupuestoIdAnular;
      const cliente = this.getAttribute('data-cliente') || '';
      
      document.getElementById('anularPresupuestoInfo').textContent = 
        'Presupuesto N° ' + preNum + (cliente ? ' - Cliente: ' + cliente : '');
      
      jQuery('#confirmAnularModal').modal('show');
    });
  });

  document.getElementById('confirmAnularBtn').addEventListener('click', function() {
    if (!presupuestoIdAnular) return;
    
    const btn = this;
    btn.disabled = true;
    btn.textContent = 'Procesando...';
    
    // Redirigir directamente a proses.php para anular (como otros módulos)
    window.location.href = 'proses.php?act=anular&pre_id=' + presupuestoIdAnular;
  });
});
";

// Incluir footer común
include '../../footer.php';
?>

