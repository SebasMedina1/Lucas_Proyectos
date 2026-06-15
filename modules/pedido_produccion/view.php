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
check_permission('PEDIDO_PRODUCCION');

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
    $permisoAcceso = (int)($auth_user['id_cargo'] ?? 0);
} catch (PDOException $e) {
    die('Error en la conexión: ' . $e->getMessage());
}

if (
    isset($_GET['form_pedido_prod'], $_GET['form'], $_GET['ped_id']) &&
    $_GET['form_pedido_prod'] === 'edit' &&
    $_GET['form'] === 'edit'
) {
    $stmt = $pdo->prepare('SELECT pedido_prod_estado FROM pedido_produccion WHERE id_pedido_produccion = :id LIMIT 1');
    $stmt->execute([':id' => (int)$_GET['ped_id']]);
    $estado = $stmt->fetchColumn();
    if ($estado === false || strtoupper(trim((string)$estado)) !== 'PENDIENTE') {
        header('Location: view.php?alert=5');
        exit;
    }
}

$mostrarListado = !(
    (isset($_GET['form_pedido_prod'], $_GET['form']) &&
     $_GET['form_pedido_prod'] === 'add' && $_GET['form'] === 'add') ||
    (isset($_GET['form_pedido_prod'], $_GET['form'], $_GET['ped_id']) &&
     $_GET['form_pedido_prod'] === 'edit' && $_GET['form'] === 'edit')
);

$BASE_PATH = '../../';
$page_title = 'Pedidos de Producción';
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
        <p><strong>¿Anular este pedido de producción?</strong></p>
        <p class="mb-0" id="anularPedidoInfo"></p>
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
    <h1 class="h3 mb-0 text-gray-800">Pedidos de Producción</h1>
    <a href="?form_pedido_prod=add&form=add" class="btn btn-primary btn-sm shadow-sm">
      <i class="fas fa-plus fa-sm text-white-50"></i> Nuevo pedido
    </a>
  </div>

  <?php
  if (!empty($_GET['alert'])) {
      $msgCustom = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
      $alertMap = [
          1 => ['msg' => 'Pedido registrado correctamente.', 'class' => 'alert-success'],
          2 => ['msg' => 'Pedido modificado correctamente.', 'class' => 'alert-success'],
          3 => ['msg' => 'Pedido anulado correctamente.', 'class' => 'alert-success'],
          4 => ['msg' => $msgCustom ?: 'No se pudo realizar la operación.', 'class' => 'alert-danger'],
          5 => ['msg' => $msgCustom ?: 'Solo se pueden editar pedidos en estado PENDIENTE.', 'class' => 'alert-danger'],
          6 => ['msg' => $msgCustom ?: 'El pedido fue modificado por otro usuario. Recargue la página.', 'class' => 'alert-warning'],
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
      <h6 class="m-0 font-weight-bold text-primary">Lista de pedidos</h6>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered" id="dataTable" width="100%">
          <thead>
            <tr>
              <th>Código</th>
              <th>Fecha</th>
              <th>Tipo</th>
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
                        pp.id_pedido_produccion,
                        pp.pedido_prod_fecha_emision AS pedido_fecha,
                        pp.pedido_prod_estado,
                        tp.tipo_pedido_descri,
                        u.username,
                        COALESCE((
                            SELECT SUM(cantidad_pedido)
                            FROM pedido_detalle_produccion
                            WHERE id_pedido_produccion = pp.id_pedido_produccion
                        ), 0) AS total_cantidad
                    FROM pedido_produccion pp
                    JOIN usuarios u ON u.id_usuario = pp.id_usuario
                    JOIN tipo_pedido tp ON tp.id_tipo_pedido = pp.id_tipo_pedido
                    ORDER BY pp.id_pedido_produccion DESC
                ";
                foreach ($pdo->query($sql) as $data) {
                    $estado = strtoupper(trim($data['pedido_prod_estado']));
                    if ($estado === 'PENDIENTE') {
                        $estadoClass = 'badge-warning';
                    } elseif ($estado === 'ANULADO') {
                        $estadoClass = 'badge-danger';
                    } else {
                        $estadoClass = 'badge-success';
                    }
                    $id = (int)$data['id_pedido_produccion'];
                    echo '<tr>
                        <td>' . $id . '</td>
                        <td>' . htmlspecialchars($data['pedido_fecha']) . '</td>
                        <td>' . htmlspecialchars($data['tipo_pedido_descri']) . '</td>
                        <td><span class="badge ' . $estadoClass . '">' . htmlspecialchars($data['pedido_prod_estado']) . '</span></td>
                        <td>' . (int)$data['total_cantidad'] . '</td>
                        <td>' . htmlspecialchars($data['username']) . '</td>
                        <td><button type="button" class="btn btn-info btn-sm btn-detalle" data-id="' . $id . '">Ver detalle</button></td>
                        <td>';
                    if ($estado === 'PENDIENTE') {
                        echo '<a href="?form_pedido_prod=edit&form=edit&ped_id=' . $id . '" class="btn btn-warning btn-sm" title="Editar"><i class="fas fa-edit"></i></a> ';
                        echo '<button type="button" class="btn btn-danger btn-sm btn-anular" data-id="' . $id . '" title="Anular"><i class="fas fa-times"></i></button> ';
                    }
                    echo '<a href="reporte.php?ped_id=' . $id . '" target="_blank" class="btn btn-secondary btn-sm" title="Imprimir"><i class="fas fa-print"></i></a>';
                    echo '</td></tr>';
                }
            } catch (PDOException $e) {
                echo '<tr><td colspan="8" class="text-danger text-center">' . htmlspecialchars($e->getMessage()) . '</td></tr>';
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
        <h5 class="modal-title">Detalle del pedido</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <table class="table table-bordered table-sm">
          <thead><tr><th>Código</th><th>Producto</th><th>Cantidad</th></tr></thead>
          <tbody id="detallePedidoBody"></tbody>
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
      const pedidoId = this.getAttribute('data-id');
      const tbody = document.getElementById('detallePedidoBody');
      tbody.innerHTML = '<tr><td colspan=\"3\" class=\"text-center\"><i class=\"fas fa-spinner fa-spin\"></i></td></tr>';
      fetch('get_detalle.php?ped_id=' + pedidoId)
        .then(r => r.json())
        .then(data => {
          tbody.innerHTML = '';
          if (data.success && data.detalle && data.detalle.length) {
            data.detalle.forEach(item => {
              const tr = document.createElement('tr');
              tr.innerHTML = '<td>' + (item.codigo || '') + '</td><td>' + (item.nombre_producto || '') + '</td><td>' + (item.cantidad || 0) + '</td>';
              tbody.appendChild(tr);
            });
          } else {
            tbody.innerHTML = '<tr><td colspan=\"3\" class=\"text-center text-muted\">Sin detalle</td></tr>';
          }
          jQuery('#detalleModal').modal('show');
        })
        .catch(err => {
          tbody.innerHTML = '<tr><td colspan=\"3\" class=\"text-danger text-center\">' + err.message + '</td></tr>';
          jQuery('#detalleModal').modal('show');
        });
    });
  });

  let pedidoIdAnular = null;
  document.querySelectorAll('.btn-anular').forEach(btn => {
    btn.addEventListener('click', function() {
      pedidoIdAnular = this.getAttribute('data-id');
      document.getElementById('anularPedidoInfo').textContent = 'Pedido N° ' + pedidoIdAnular;
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
      if (!pedidoIdAnular) return;
      this.disabled = true;
      this.textContent = 'Procesando...';
      fetch('anular_pedido.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ped_id: pedidoIdAnular })
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
