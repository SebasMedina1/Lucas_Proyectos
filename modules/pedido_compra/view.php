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
check_permission('PEDIDO_COMPRAS');

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

if (
    isset($_GET['form_pedido_compra'], $_GET['form'], $_GET['ped_id']) &&
    $_GET['form_pedido_compra'] === 'edit' &&
    $_GET['form'] === 'edit'
) {
    $stmt = $pdo->prepare("
        SELECT pedido_estado
          FROM pedidos_compra
         WHERE id_pedido_compra = :id
         LIMIT 1
    ");
    $stmt->execute([':id' => (int)$_GET['ped_id']]);
    $estado = $stmt->fetchColumn();

    if ($estado === false || strtoupper(trim((string)$estado)) !== 'PENDIENTE') {
        header("Location: view.php?alert=5");
        exit;
    }
}

$mostrarListado = !(
    (isset($_GET['form_pedido_compra'], $_GET['form']) &&
     $_GET['form_pedido_compra'] === 'add' &&
     $_GET['form'] === 'add') ||
    (isset($_GET['form_pedido_compra'], $_GET['form'], $_GET['ped_id']) &&
     $_GET['form_pedido_compra'] === 'edit' &&
     $_GET['form'] === 'edit')
);

// Configuración para el layout común
$BASE_PATH = '../../';
$page_title = 'Pedidos de Compras';
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

<!-- Modal confirmación de cambio -->
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

<!-- Modal de anulación -->
<div class="modal fade" id="anularModal" tabindex="-1" role="dialog" aria-labelledby="anularModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="anularModalLabel">Confirmar Anulación</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p><strong>¿Está seguro que desea anular este pedido?</strong></p>
        <p class="mb-0" id="anularPedidoInfo"></p>
        <div id="anularVinculos" class="mt-3" style="display:none;">
          <div class="alert alert-warning">
            <strong>Advertencia:</strong> Este pedido está vinculado a:
            <ul id="listaVinculos" class="mb-0 mt-2"></ul>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="confirmarAnularBtn">Sí, anular</button>
      </div>
    </div>
  </div>
</div>

<!-- Contenido específico del módulo -->
<?php if ($mostrarListado): ?>
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Pedidos de Compras</h1>
    <div>
      <a href="?form_pedido_compra=add&form=add" class="btn btn-primary btn-sm shadow-sm">
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
          6 => ['msg'=>isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : 'El pedido fue modificado por otro usuario. Por favor, recargue la página.','class'=>'alert-warning'],
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
                      pc.id_pedido_compra,
                      pc.pedido_fecha_emision AS pedido_fecha,
                      pc.pedido_estado,
                      u.username,
                      COALESCE(
                          (SELECT psc.presu_total
                           FROM presupuesto_compra psc
                           WHERE psc.id_pedido_compra = pc.id_pedido_compra
                             AND psc.presu_estado != 'ANULADO'
                           ORDER BY psc.id_presupuesto_compra DESC
                           LIMIT 1)
                      ) AS total_presupuesto,
                      COALESCE(
                          (SELECT SUM(cantidad_pedido)
                           FROM pedido_detalle_compra
                           WHERE id_pedido_compra = pc.id_pedido_compra),
                          0
                      ) AS total_cantidad,
                      (SELECT COUNT(*) FROM pedido_detalle_compra WHERE id_pedido_compra = pc.id_pedido_compra) AS total_items
                  FROM pedidos_compra pc
                  JOIN usuarios u ON u.id_usuario = pc.id_usuario
                  ORDER BY pc.id_pedido_compra DESC
                ";
                foreach ($pdo->query($sql) as $data) {
                    $estadoClass = '';
                    $estado = strtoupper(trim($data['pedido_estado']));
                    if ($estado === 'PENDIENTE') $estadoClass = 'badge-warning';
                    elseif ($estado === 'ANULADO') $estadoClass = 'badge-danger';
                    else $estadoClass = 'badge-success';
                    
                    // Mostrar total: si hay presupuesto, mostrar monto; si no, mostrar cantidad total
                    $total_presupuesto = (float)$data['total_presupuesto'];
                    $total_cantidad = (int)$data['total_cantidad'];
                    
                    if ($total_presupuesto > 0) {
                        $total_display = number_format($total_presupuesto, 0, ',', '.') . ' Gs.';
                    } else {
                        $total_display = $total_cantidad . ' unidades';
                    }
                    
                    echo '<tr>
                            <td>' . htmlspecialchars($data['id_pedido_compra']) . '</td>
                            <td>' . htmlspecialchars($data['pedido_fecha']) . '</td>
                            <td><span class="badge ' . $estadoClass . '">' . htmlspecialchars($data['pedido_estado']) . '</span></td>
                            <td>' . $total_display . '</td>
                            <td>' . htmlspecialchars($data['username']) . '</td>
                            <td><button type="button" class="btn btn-info btn-sm btn-detalle" data-id="' . htmlspecialchars($data['id_pedido_compra']) . '">Ver Detalle</button></td>
                            <td>';
                    
                    // Solo mostrar editar si está PENDIENTE
                    if ($estado === 'PENDIENTE') {
                        echo '<a href="?form_pedido_compra=edit&form=edit&ped_id=' . htmlspecialchars($data['id_pedido_compra']) . '" class="btn btn-warning btn-sm" title="Editar"><i class="fas fa-edit"></i></a> ';
                    }
                    
                    // Botón anular
                    if ($estado === 'PENDIENTE') {
                        echo '<button type="button" class="btn btn-danger btn-sm btn-anular" data-id="' . htmlspecialchars($data['id_pedido_compra']) . '" title="Anular"><i class="fas fa-times"></i></button> ';
                    }
                    
                    // Botón reporte
                    echo '<a href="reporte.php?ped_id=' . htmlspecialchars($data['id_pedido_compra']) . '" target="_blank" class="btn btn-info btn-sm" title="Imprimir"><i class="fas fa-print"></i></a>';
                    
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
  <div class="modal-dialog">
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
      const tbody = document.getElementById('detallePedidoBody');
      tbody.innerHTML = '<tr><td colspan=\"2\" class=\"text-center\"><i class=\"fas fa-spinner fa-spin\"></i> Cargando...</td></tr>';
      
      fetch('get_detalle.php?ped_id=' + pedidoId)
        .then(response => {
          if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
          }
          return response.json();
        })
        .then(data => {
          tbody.innerHTML = '';
          if (data.success && data.detalle && data.detalle.length > 0) {
            data.detalle.forEach(item => {
              const row = document.createElement('tr');
              row.innerHTML = '<td>' + (item.nombre_producto || 'Sin nombre') + '</td><td>' + (item.cantidad || 0) + '</td>';
              tbody.appendChild(row);
            });
          } else {
            tbody.innerHTML = '<tr><td colspan=\"2\" class=\"text-center text-muted\">No hay detalles disponibles</td></tr>';
          }
          jQuery('#detalleModal').modal('show');
        })
        .catch(error => {
          console.error('Error:', error);
          tbody.innerHTML = '<tr><td colspan=\"2\" class=\"text-center text-danger\">Error al cargar el detalle: ' + error.message + '</td></tr>';
          jQuery('#detalleModal').modal('show');
        });
    });
  });

  // Anular pedido con modal mejorado
  let pedidoIdAnular = null;
  document.querySelectorAll('.btn-anular').forEach(btn => {
    btn.addEventListener('click', function() {
      pedidoIdAnular = this.getAttribute('data-id');
      const pedidoNum = this.closest('tr').querySelector('td:first-child').textContent;
      
      document.getElementById('anularPedidoInfo').textContent = 'Pedido N° ' + pedidoNum;
      document.getElementById('anularVinculos').style.display = 'none';
      document.getElementById('listaVinculos').innerHTML = '';
      
      jQuery('#anularModal').modal('show');
    });
  });

  // Confirmar anulación
  document.getElementById('confirmarAnularBtn').addEventListener('click', function() {
    if (!pedidoIdAnular) return;
    
    const btn = this;
    btn.disabled = true;
    btn.textContent = 'Procesando...';
    
    fetch('anular_pedido.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ped_id: pedidoIdAnular })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        jQuery('#anularModal').modal('hide');
        location.reload();
      } else {
        // Mostrar vínculos si existen
        if (data.vinculos && data.vinculos.length > 0) {
          const lista = document.getElementById('listaVinculos');
          lista.innerHTML = '';
          data.vinculos.forEach(v => {
            const li = document.createElement('li');
            li.textContent = v;
            lista.appendChild(li);
          });
          document.getElementById('anularVinculos').style.display = 'block';
          document.getElementById('confirmarAnularBtn').style.display = 'none';
        } else {
          alert(data.message || 'Error al anular el pedido');
          btn.disabled = false;
          btn.textContent = 'Sí, anular';
        }
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error al anular el pedido');
      btn.disabled = false;
      btn.textContent = 'Sí, anular';
    });
  });
});
";

// Incluir footer común
include '../../footer.php';
?>
