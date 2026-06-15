<?php
session_start();
if (empty($_SESSION['username'])) {
    echo "<script>alert('Token de sesión inválido'); window.location.href='../../login.html';</script>";
    exit;
}

$configPath = realpath(__DIR__ . '/../../config/database.php');
if (!$configPath) {
    die('Error: No se encontró config/database.php');
}
require_once $configPath;
require_once realpath(__DIR__ . '/../../config/permissions.php');
check_permission('ORDEN_PRODUCCION');

$username = $_SESSION['username'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $query = $pdo->prepare('SELECT * FROM usuarios WHERE username = :username');
    $query->execute([':username' => $username]);
    $auth_user = $query->fetch();
    if (!$auth_user) {
        session_destroy();
        header('Location: ../../login.html');
        exit;
    }
} catch (PDOException $e) {
    die('Error en la conexión: ' . $e->getMessage());
}

if (
    isset($_GET['form_orden_prod'], $_GET['form'], $_GET['orden_id']) &&
    $_GET['form_orden_prod'] === 'edit' &&
    $_GET['form'] === 'edit'
) {
    $stmt = $pdo->prepare('SELECT orden_prod_estado FROM orden_produccion WHERE orden_id = :id LIMIT 1');
    $stmt->execute([':id' => (int)$_GET['orden_id']]);
    $estado = $stmt->fetchColumn();
    if ($estado === false || strtoupper(trim((string)$estado)) !== 'PENDIENTE') {
        header('Location: view.php?alert=5');
        exit;
    }
}

$mostrarListado = !(
    (isset($_GET['form_orden_prod'], $_GET['form']) &&
     $_GET['form_orden_prod'] === 'add' && $_GET['form'] === 'add') ||
    (isset($_GET['form_orden_prod'], $_GET['form'], $_GET['orden_id']) &&
     $_GET['form_orden_prod'] === 'edit' && $_GET['form'] === 'edit')
);

$BASE_PATH = '../../';
$page_title = 'Órdenes de Producción';
$extra_css = ['vendor/datatables/dataTables.bootstrap4.min.css'];
$extra_js_plugins = [
    'vendor/datatables/jquery.dataTables.min.js',
    'vendor/datatables/dataTables.bootstrap4.min.js',
];

include '../../header.php';
?>

<div class="modal fade" id="anularModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Confirmar anulación</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <p><strong>¿Anular esta orden de producción?</strong></p>
        <p class="mb-0" id="anularOrdenInfo"></p>
        <div id="anularVinculos" class="mt-3" style="display:none;">
          <div class="alert alert-warning mb-0">
            <strong>No se puede anular:</strong>
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

<?php if ($mostrarListado): ?>
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Órdenes de Producción</h1>
    <a href="?form_orden_prod=add&form=add" class="btn btn-primary btn-sm shadow-sm">
      <i class="fas fa-plus fa-sm text-white-50"></i> Nueva orden
    </a>
  </div>

  <?php
  if (!empty($_GET['alert'])) {
      $msgCustom = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
      $alertMap = [
          1 => ['msg' => 'Orden registrada correctamente.', 'class' => 'alert-success'],
          2 => ['msg' => 'Fecha de entrega actualizada correctamente.', 'class' => 'alert-success'],
          3 => ['msg' => 'Orden anulada correctamente.', 'class' => 'alert-success'],
          4 => ['msg' => $msgCustom ?: 'No se pudo realizar la operación.', 'class' => 'alert-danger'],
          5 => ['msg' => $msgCustom ?: 'Solo se pueden editar órdenes en estado PENDIENTE.', 'class' => 'alert-danger'],
      ];
      if (isset($alertMap[(int)$_GET['alert']])) {
          $data = $alertMap[(int)$_GET['alert']];
          echo "<div id='alert-message' class='alert {$data['class']} alert-dismissible fade show' role='alert'>
                  {$data['msg']}
                  <button type='button' class='close' data-dismiss='alert'><span>&times;</span></button>
                </div>";
      }
  }
  ?>

  <div class="card shadow mb-4">
    <div class="card-header py-3">
      <h6 class="m-0 font-weight-bold text-primary">Lista de órdenes</h6>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered" id="dataTable" width="100%">
          <thead>
            <tr>
              <th>Orden N°</th>
              <th>Fecha</th>
              <th>Entrega prevista</th>
              <th>Pedido</th>
              <th>Estado</th>
              <th>Total unid.</th>
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
                        op.orden_id,
                        op.orden_prod_fecha,
                        op.orden_prod_fecha_entrega,
                        op.orden_prod_estado,
                        op.id_pedido_produccion,
                        u.username,
                        COALESCE((
                            SELECT SUM(orden_prod_cantidad)
                            FROM orden_detalle_produccion
                            WHERE orden_id = op.orden_id
                        ), 0) AS total_cantidad
                    FROM orden_produccion op
                    JOIN usuarios u ON u.id_usuario = op.id_usuario
                    ORDER BY op.orden_id DESC
                ";
                foreach ($pdo->query($sql) as $data) {
                    $estado = strtoupper(trim($data['orden_prod_estado']));
                    if ($estado === 'PENDIENTE') {
                        $estadoClass = 'badge-warning';
                    } elseif ($estado === 'EN_PROCESO') {
                        $estadoClass = 'badge-info';
                    } elseif ($estado === 'TERMINADA') {
                        $estadoClass = 'badge-success';
                    } else {
                        $estadoClass = 'badge-danger';
                    }
                    $id = (int)$data['orden_id'];
                    $fechaEntrega = $data['orden_prod_fecha_entrega'];
                    if ($fechaEntrega && strpos($fechaEntrega, ' ') !== false) {
                        $fechaEntrega = substr($fechaEntrega, 0, 10);
                    }
                    echo '<tr>
                        <td>' . $id . '</td>
                        <td>' . htmlspecialchars($data['orden_prod_fecha']) . '</td>
                        <td>' . htmlspecialchars($fechaEntrega ?? '') . '</td>
                        <td>' . (int)$data['id_pedido_produccion'] . '</td>
                        <td><span class="badge ' . $estadoClass . '">' . htmlspecialchars($data['orden_prod_estado']) . '</span></td>
                        <td>' . (int)$data['total_cantidad'] . '</td>
                        <td>' . htmlspecialchars($data['username']) . '</td>
                        <td><button type="button" class="btn btn-info btn-sm btn-detalle" data-id="' . $id . '">Ver detalle</button></td>
                        <td>';
                    if ($estado === 'PENDIENTE') {
                        echo '<a href="?form_orden_prod=edit&form=edit&orden_id=' . $id . '" class="btn btn-warning btn-sm" title="Editar fecha de entrega"><i class="fas fa-edit"></i></a> ';
                        echo '<button type="button" class="btn btn-danger btn-sm btn-anular" data-id="' . $id . '" title="Anular"><i class="fas fa-times"></i></button> ';
                    }
                    echo '<a href="reporte.php?orden_id=' . $id . '" target="_blank" class="btn btn-secondary btn-sm" title="Imprimir"><i class="fas fa-print"></i></a>';
                    echo '</td></tr>';
                }
            } catch (PDOException $e) {
                echo '<tr><td colspan="9" class="text-danger text-center">' . htmlspecialchars($e->getMessage()) . '</td></tr>';
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

<div class="modal fade" id="detalleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalle de la orden</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <table class="table table-bordered table-sm">
          <thead><tr><th>Código</th><th>Producto</th><th>Cantidad</th><th>Pendiente</th></tr></thead>
          <tbody id="detalleOrdenBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
$inline_js = "
document.addEventListener('DOMContentLoaded', () => {
  const alertMessage = document.getElementById('alert-message');
  if (alertMessage) setTimeout(() => alertMessage.remove(), 4000);

  if (window.jQuery && jQuery().DataTable) {
    jQuery('#dataTable').DataTable({
      language: { url: '{$BASE_PATH}vendor/datatables/Spanish.json' },
      order: [[0, 'desc']],
      pageLength: 10
    });
  }

  document.querySelectorAll('.btn-detalle').forEach(btn => {
    btn.addEventListener('click', function() {
      const ordenId = this.getAttribute('data-id');
      const tbody = document.getElementById('detalleOrdenBody');
      tbody.innerHTML = '<tr><td colspan=\"4\" class=\"text-center\"><i class=\"fas fa-spinner fa-spin\"></i></td></tr>';
      fetch('get_detalle.php?orden_id=' + ordenId)
        .then(r => r.json())
        .then(data => {
          tbody.innerHTML = '';
          if (data.success && data.detalle && data.detalle.length) {
            data.detalle.forEach(item => {
              const tr = document.createElement('tr');
              tr.innerHTML = '<td>' + (item.codigo || '') + '</td><td>' + (item.nombre_producto || '') + '</td><td>' + (item.cantidad || 0) + '</td><td>' + (item.cantidad_pendiente ?? '') + '</td>';
              tbody.appendChild(tr);
            });
          } else {
            tbody.innerHTML = '<tr><td colspan=\"4\" class=\"text-center text-muted\">Sin detalle</td></tr>';
          }
          jQuery('#detalleModal').modal('show');
        })
        .catch(err => {
          tbody.innerHTML = '<tr><td colspan=\"4\" class=\"text-danger text-center\">' + err.message + '</td></tr>';
          jQuery('#detalleModal').modal('show');
        });
    });
  });

  let ordenIdAnular = null;
  document.querySelectorAll('.btn-anular').forEach(btn => {
    btn.addEventListener('click', function() {
      ordenIdAnular = this.getAttribute('data-id');
      document.getElementById('anularOrdenInfo').textContent = 'Orden N° ' + ordenIdAnular;
      document.getElementById('anularVinculos').style.display = 'none';
      document.getElementById('listaVinculos').innerHTML = '';
      document.getElementById('confirmarAnularBtn').style.display = '';
      document.getElementById('confirmarAnularBtn').disabled = false;
      document.getElementById('confirmarAnularBtn').textContent = 'Sí, anular';
      jQuery('#anularModal').modal('show');
    });
  });

  const btnAnular = document.getElementById('confirmarAnularBtn');
  if (btnAnular) {
    btnAnular.addEventListener('click', function() {
      if (!ordenIdAnular) return;
      this.disabled = true;
      this.textContent = 'Procesando...';
      fetch('anular_orden.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ orden_id: ordenIdAnular })
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          jQuery('#anularModal').modal('hide');
          window.location.href = 'view.php?alert=3';
        } else if (data.vinculos && data.vinculos.length) {
          const lista = document.getElementById('listaVinculos');
          lista.innerHTML = '';
          data.vinculos.forEach(v => {
            const li = document.createElement('li');
            li.textContent = v;
            lista.appendChild(li);
          });
          document.getElementById('anularVinculos').style.display = 'block';
          this.style.display = 'none';
        } else {
          alert(data.message || 'No se pudo anular');
          this.disabled = false;
          this.textContent = 'Sí, anular';
        }
      })
      .catch(() => {
        alert('Error al anular');
        this.disabled = false;
        this.textContent = 'Sí, anular';
      });
    });
  }
});
";

include '../../footer.php';
