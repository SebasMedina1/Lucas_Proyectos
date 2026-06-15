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
check_permission('PEDIDO_MATERIA_PRIMA');

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $query = $pdo->prepare('SELECT 1 FROM usuarios WHERE username = :username');
    $query->execute([':username' => $_SESSION['username']]);
    if (!$query->fetchColumn()) {
        session_destroy();
        header('Location: ../../login.html');
        exit;
    }
} catch (PDOException $e) {
    die('Error en la conexión: ' . $e->getMessage());
}

if (
    isset($_GET['form_ped_mp'], $_GET['form'], $_GET['id_pedido_mat_prod']) &&
    $_GET['form_ped_mp'] === 'edit' && $_GET['form'] === 'edit'
) {
    $stmt = $pdo->prepare('SELECT ped_mat_prod_estado FROM pedido_materia_produccion WHERE id_pedido_mat_prod = :id LIMIT 1');
    $stmt->execute([':id' => (int)$_GET['id_pedido_mat_prod']]);
    $estado = strtoupper(trim((string)$stmt->fetchColumn()));
    if ($estado !== 'PENDIENTE') {
        header('Location: view.php?alert=5');
        exit;
    }
}

$mostrarListado = !(
    (isset($_GET['form_ped_mp'], $_GET['form']) && $_GET['form_ped_mp'] === 'add' && $_GET['form'] === 'add') ||
    (isset($_GET['form_ped_mp'], $_GET['form'], $_GET['id_pedido_mat_prod']) && $_GET['form_ped_mp'] === 'edit' && $_GET['form'] === 'edit')
);

$BASE_PATH = '../../';
$page_title = 'Pedidos de Materia Prima';
$extra_css = ['vendor/datatables/dataTables.bootstrap4.min.css'];
$extra_js_plugins = [
    'vendor/datatables/jquery.dataTables.min.js',
    'vendor/datatables/dataTables.bootstrap4.min.js',
];

include '../../header.php';

function badgeEstadoPedMp(string $estado): string
{
    $e = strtoupper(trim($estado));
    $map = [
        'PENDIENTE' => 'badge-warning',
        'PARCIAL' => 'badge-info',
        'COMPLETADO' => 'badge-success',
        'ANULADO' => 'badge-danger',
    ];
    $cls = $map[$e] ?? 'badge-secondary';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($estado) . '</span>';
}
?>

<div class="modal fade" id="anularModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Confirmar anulación</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <p><strong>¿Anular este pedido de materia prima?</strong></p>
        <p class="mb-0" id="anularPedInfo"></p>
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
    <h1 class="h3 mb-0 text-gray-800">Pedidos de Materia Prima</h1>
    <a href="?form_ped_mp=add&form=add" class="btn btn-primary btn-sm shadow-sm">
      <i class="fas fa-plus fa-sm text-white-50"></i> Nuevo pedido
    </a>
  </div>

  <?php
  if (!empty($_GET['alert'])) {
      $msgCustom = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
      $alertMap = [
          1 => ['msg' => 'Pedido registrado correctamente.', 'class' => 'alert-success'],
          2 => ['msg' => 'Pedido actualizado correctamente.', 'class' => 'alert-success'],
          3 => ['msg' => 'Pedido anulado correctamente.', 'class' => 'alert-success'],
          4 => ['msg' => $msgCustom ?: 'No se pudo realizar la operación.', 'class' => 'alert-danger'],
          5 => ['msg' => $msgCustom ?: 'No se puede editar este pedido.', 'class' => 'alert-danger'],
      ];
      if (isset($alertMap[(int)$_GET['alert']])) {
          $d = $alertMap[(int)$_GET['alert']];
          echo "<div id='alert-message' class='alert {$d['class']} alert-dismissible fade show'>{$d['msg']}
                <button type='button' class='close' data-dismiss='alert'><span>&times;</span></button></div>";
      }
  }
  ?>

  <div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Solicitudes de MP a depósito</h6></div>
    <div class="card-body">
      <p class="small text-muted">Estos pedidos no mueven stock. Use <strong>Reposición de MP</strong> para ingresar físicamente los insumos.</p>
      <div class="table-responsive">
        <table class="table table-bordered" id="dataTable" width="100%">
          <thead>
            <tr>
              <th>N°</th>
              <th>Fecha</th>
              <th>Depósito</th>
              <th>Materias</th>
              <th>Cant. solicitada</th>
              <th>Repuesta</th>
              <th>Usuario</th>
              <th>Estado</th>
              <th>Detalle</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php
            try {
                $sql = "
                    SELECT
                        p.id_pedido_mat_prod,
                        p.ped_mat_prod_fecha,
                        p.ped_mat_prod_estado,
                        d.deposito_descri,
                        u.username,
                        STRING_AGG(DISTINCT mp.materia_prima_descripcion, ', ' ORDER BY mp.materia_prima_descripcion) AS materias,
                        COALESCE(SUM(pd.ped_mat_prod_cantidad), 0)::int AS cant_solicitada,
                        COALESCE(SUM(pd.cantidad_repuesta), 0)::int AS cant_repuesta
                    FROM pedido_materia_produccion p
                    JOIN deposito d ON d.deposito_id = p.deposito_id
                    JOIN usuarios u ON u.id_usuario = p.id_usuario
                    LEFT JOIN pedido_materia_detalle_produccion pd ON pd.id_pedido_mat_prod = p.id_pedido_mat_prod
                    LEFT JOIN materia_prima mp ON mp.id_materia_prima = pd.id_materia_prima
                    GROUP BY p.id_pedido_mat_prod, p.ped_mat_prod_fecha, p.ped_mat_prod_estado,
                             d.deposito_descri, u.username
                    ORDER BY p.id_pedido_mat_prod DESC
                ";
                foreach ($pdo->query($sql) as $row) {
                    $id = (int)$row['id_pedido_mat_prod'];
                    $est = strtoupper(trim($row['ped_mat_prod_estado']));
                    echo '<tr>
                      <td>' . $id . '</td>
                      <td>' . htmlspecialchars(substr((string)$row['ped_mat_prod_fecha'], 0, 10)) . '</td>
                      <td>' . htmlspecialchars($row['deposito_descri']) . '</td>
                      <td>' . htmlspecialchars($row['materias'] ?? '-') . '</td>
                      <td class="text-right">' . (int)$row['cant_solicitada'] . '</td>
                      <td class="text-right">' . (int)$row['cant_repuesta'] . '</td>
                      <td>' . htmlspecialchars($row['username']) . '</td>
                      <td>' . badgeEstadoPedMp($row['ped_mat_prod_estado']) . '</td>
                      <td><button type="button" class="btn btn-info btn-sm btn-detalle" data-id="' . $id . '">Ver</button></td>
                      <td class="text-nowrap">';
                    if ($est === 'PENDIENTE') {
                        echo '<a href="?form_ped_mp=edit&form=edit&id_pedido_mat_prod=' . $id . '" class="btn btn-warning btn-sm" title="Editar"><i class="fas fa-edit"></i></a> ';
                        echo '<button type="button" class="btn btn-danger btn-sm btn-anular" data-id="' . $id . '"><i class="fas fa-times"></i></button>';
                    }
                    if (in_array($est, ['PENDIENTE', 'PARCIAL'], true)) {
                        echo ' <a href="../reposicion_materia/view.php?form_rep=add&form=add&id_pedido_mat_prod=' . $id . '" class="btn btn-success btn-sm" title="Registrar reposición"><i class="fas fa-dolly"></i></a>';
                    }
                    echo '</td></tr>';
                }
            } catch (PDOException $e) {
                echo '<tr><td colspan="10" class="text-danger text-center">' . htmlspecialchars($e->getMessage()) . '</td></tr>';
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
      <div class="modal-header"><h5 class="modal-title">Detalle del pedido</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
      <div class="modal-body" id="detalleBody"></div>
    </div>
  </div>
</div>

<?php
$inline_js = "
document.addEventListener('DOMContentLoaded', () => {
  const am = document.getElementById('alert-message');
  if (am) setTimeout(() => am.remove(), 5000);
  if (window.jQuery && jQuery().DataTable) {
    jQuery('#dataTable').DataTable({
      language: { url: '{$BASE_PATH}vendor/datatables/Spanish.json' },
      order: [[0, 'desc']],
      pageLength: 10
    });
  }
  document.querySelectorAll('.btn-detalle').forEach(btn => {
    btn.addEventListener('click', () => {
      const body = document.getElementById('detalleBody');
      body.innerHTML = '<p class=\"text-center\"><i class=\"fas fa-spinner fa-spin\"></i></p>';
      fetch('get_detalle.php?id_pedido_mat_prod=' + btn.dataset.id)
        .then(r => r.json())
        .then(data => {
          if (!data.success) { body.innerHTML = '<p class=\"text-danger\">' + (data.error || '') + '</p>'; }
          else {
            const c = data.cabecera;
            let html = '<p><strong>N°:</strong> ' + c.id_pedido_mat_prod + ' | <strong>Estado:</strong> ' + c.ped_mat_prod_estado + '</p>';
            html += '<p><strong>Fecha:</strong> ' + c.ped_mat_prod_fecha + ' | <strong>Depósito:</strong> ' + c.deposito_descri + '</p>';
            html += '<p><strong>Usuario:</strong> ' + c.username + ' | <strong>Sucursal:</strong> ' + c.descripcion_sucursal + '</p>';
            html += '<table class=\"table table-sm table-bordered\"><thead><tr><th>Materia prima</th><th>Solicitada</th><th>Repuesta</th><th>Pendiente</th></tr></thead><tbody>';
            (data.detalle || []).forEach(x => {
              const pend = (parseInt(x.ped_mat_prod_cantidad, 10) || 0) - (parseInt(x.cantidad_repuesta, 10) || 0);
              html += '<tr><td>' + x.materia_prima_descripcion + '</td>';
              html += '<td class=\"text-right\">' + x.ped_mat_prod_cantidad + '</td>';
              html += '<td class=\"text-right\">' + x.cantidad_repuesta + '</td>';
              html += '<td class=\"text-right\">' + pend + '</td></tr>';
            });
            html += '</tbody></table>';
            body.innerHTML = html;
          }
          jQuery('#detalleModal').modal('show');
        });
    });
  });
  let idAnular = null;
  document.querySelectorAll('.btn-anular').forEach(btn => {
    btn.addEventListener('click', () => {
      idAnular = btn.dataset.id;
      document.getElementById('anularPedInfo').textContent = 'Pedido N° ' + idAnular;
      jQuery('#anularModal').modal('show');
    });
  });
  const btnA = document.getElementById('confirmarAnularBtn');
  if (btnA) {
    btnA.addEventListener('click', function() {
      if (!idAnular) return;
      this.disabled = true;
      fetch('anular_pedido.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_pedido_mat_prod: idAnular })
      }).then(r => r.json()).then(data => {
        if (data.success) { jQuery('#anularModal').modal('hide'); location.href = 'view.php?alert=3'; }
        else { alert(data.message || 'Error'); this.disabled = false; }
      });
    });
  }
});
";
include '../../footer.php';
