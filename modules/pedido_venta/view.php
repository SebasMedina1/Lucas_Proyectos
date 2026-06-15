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

// Validar que solo se pueda editar pedidos PENDIENTES y sin documentos posteriores
if (
    isset($_GET['form_pedido_venta'], $_GET['form'], $_GET['ped_id']) &&
    $_GET['form_pedido_venta'] === 'edit' &&
    $_GET['form'] === 'edit'
) {
    $ped_id = (int)$_GET['ped_id'];
    
    $stmt = $pdo->prepare("
        SELECT pedido_estado
          FROM pedido_venta
         WHERE id_pedido_venta = :id
         LIMIT 1
    ");
    $stmt->execute([':id' => $ped_id]);
    $estado = $stmt->fetchColumn();

    if ($estado === false || strtoupper(trim((string)$estado)) !== 'PENDIENTE') {
        header("Location: view.php?alert=5");
        exit;
    }
    
    // Verificar que no tenga documentos posteriores (presupuesto o factura)
    $qPresupuesto = $pdo->prepare("
        SELECT COUNT(*) 
        FROM presupuesto_venta 
        WHERE id_pedido_venta = :id
    ");
    $qPresupuesto->execute([':id' => $ped_id]);
    $tienePresupuesto = $qPresupuesto->fetchColumn() > 0;

    $qFactura = $pdo->prepare("
        SELECT COUNT(*) 
        FROM factura_ventas 
        WHERE id_pedido_venta = :id
    ");
    $qFactura->execute([':id' => $ped_id]);
    $tieneFactura = $qFactura->fetchColumn() > 0;

    if ($tienePresupuesto || $tieneFactura) {
        header("Location: view.php?alert=6");
        exit;
    }
}

$mostrarListado = !(
    isset($_GET['form_pedido_venta'], $_GET['form']) &&
    $_GET['form_pedido_venta'] === 'add' &&
    $_GET['form'] === 'add'
);

// Configuración para el layout común
$BASE_PATH = '../../';
$page_title = 'Pedidos de Ventas';
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
      <div class="modal-header">
        <h5 class="modal-title">Confirmar Anulación</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>¿Está seguro que desea anular este pedido?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="confirmAnularBtn">Anular</button>
      </div>
    </div>
  </div>
</div>

<!-- Contenido específico del módulo -->
<?php if ($mostrarListado): ?>
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Pedidos de Ventas</h1>
    <div>
      <a href="?form_pedido_venta=add&form=add" class="btn btn-primary btn-sm shadow-sm">
        <i class="fas fa-plus fa-sm text-white-50"></i> Nuevo Pedido
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
          5 => ['msg'=>'Solo se puede editar pedidos PENDIENTES.','class'=>'alert-danger'],
          6 => ['msg'=>'No se puede anular un pedido que ya tiene documentos asociados (presupuesto/factura).','class'=>'alert-danger'],
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
      <h6 class="m-0 font-weight-bold text-primary">Lista de Pedidos</h6>
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
                      pv.id_pedido_venta,
                      pv.pedido_fecha,
                      pv.pedido_estado,
                      c.cliente_nombre || ' ' || c.cliente_apellido AS cliente_nombre,
                      u.username,
                      COALESCE(SUM(d.pedido_precio_total), 0) AS total
                  FROM pedido_venta pv
                  JOIN clientes c ON c.id_cliente = pv.id_cliente
                  JOIN usuarios u ON u.id_usuario = pv.id_usuario
                  LEFT JOIN detalle_pedido_venta d ON d.id_pedido_venta = pv.id_pedido_venta
                  GROUP BY pv.id_pedido_venta, pv.pedido_fecha, pv.pedido_estado, c.cliente_nombre, c.cliente_apellido, u.username
                  ORDER BY pv.id_pedido_venta DESC
                ";
                foreach ($pdo->query($sql) as $data) {
                    $estadoClass = '';
                    $estado = strtoupper(trim($data['pedido_estado']));
                    if ($estado === 'PENDIENTE') $estadoClass = 'badge-warning';
                    elseif ($estado === 'ANULADO') $estadoClass = 'badge-danger';
                    else $estadoClass = 'badge-success';
                    
                    echo '<tr>
                            <td>' . htmlspecialchars($data['id_pedido_venta']) . '</td>
                            <td>' . htmlspecialchars($data['pedido_fecha']) . '</td>
                            <td>' . htmlspecialchars($data['cliente_nombre']) . '</td>
                            <td><span class="badge ' . $estadoClass . '">' . htmlspecialchars($data['pedido_estado']) . '</span></td>
                            <td>' . number_format($data['total'], 0, ',', '.') . '</td>
                            <td>' . htmlspecialchars($data['username']) . '</td>
                            <td><button type="button" class="btn btn-info btn-sm btn-detalle" data-id="' . htmlspecialchars($data['id_pedido_venta']) . '">Ver Detalle</button></td>
                            <td>';
                    
                    // Solo mostrar editar si está PENDIENTE
                    if ($estado === 'PENDIENTE') {
                        echo '<a href="?form_pedido_venta=edit&form=edit&ped_id=' . htmlspecialchars($data['id_pedido_venta']) . '" class="btn btn-warning btn-sm" title="Editar"><i class="fas fa-edit"></i></a> ';
                    }
                    
                    // Botón anular
                    if ($estado === 'PENDIENTE') {
                        echo '<button type="button" class="btn btn-danger btn-sm btn-anular" data-id="' . htmlspecialchars($data['id_pedido_venta']) . '" title="Anular"><i class="fas fa-times"></i></button> ';
                    }
                    
                    // Botón reporte
                    echo '<a href="reporte.php?ped_id=' . htmlspecialchars($data['id_pedido_venta']) . '" target="_blank" class="btn btn-info btn-sm" title="Imprimir"><i class="fas fa-print"></i></a>';
                    
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
        <h5 class="modal-title" id="detalleModalLabel">Detalle del Pedido</h5>
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
              <th>Subtotal</th>
              <th>IVA</th>
            </tr>
          </thead>
          <tbody id="detallePedidoBody"></tbody>
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
      const pedidoId = this.getAttribute('data-id');
      fetch('get_detalle.php?ped_id=' + pedidoId)
        .then(response => response.json())
        .then(data => {
          const tbody = document.getElementById('detallePedidoBody');
          tbody.innerHTML = '';
          if (data.success && data.detalle) {
            data.detalle.forEach(item => {
              const row = document.createElement('tr');
              row.innerHTML = '<td>' + item.nombre_producto + '</td><td>' + item.cantidad + '</td><td>' + item.precio_unitario + '</td><td>' + item.subtotal + '</td><td>' + item.iva_aplicado + '%</td>';
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

  // Anular pedido
  let pedidoIdAnular = null;
  document.querySelectorAll('.btn-anular').forEach(btn => {
    btn.addEventListener('click', function() {
      pedidoIdAnular = this.getAttribute('data-id');
      jQuery('#confirmAnularModal').modal('show');
    });
  });

  document.getElementById('confirmAnularBtn').addEventListener('click', function() {
    if (!pedidoIdAnular) return;
    
    const btn = this;
    btn.disabled = true;
    btn.textContent = 'Procesando...';
    
    const formData = new FormData();
    formData.append('ped_id', pedidoIdAnular);
    
    fetch('anular_pedido.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        window.location.href = 'view.php?alert=3';
      } else {
        alert(data.message || 'Error al anular el pedido');
        btn.disabled = false;
        btn.textContent = 'Anular';
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error al anular el pedido');
      btn.disabled = false;
      btn.textContent = 'Anular';
    });
  });
});
";

// Incluir footer común
include '../../footer.php';
?>
