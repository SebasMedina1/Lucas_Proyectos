<?php
session_start();
if (empty($_SESSION['username'])) {
    echo "<script>alert('Token de sesión inválido'); window.location.href='../../login.html';</script>";
    exit;
}

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');
check_permission('REPOSICION_MATERIA');

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $q = $pdo->prepare('SELECT 1 FROM usuarios WHERE username = :u');
    $q->execute([':u' => $_SESSION['username']]);
    if (!$q->fetchColumn()) {
        session_destroy();
        header('Location: ../../login.html');
        exit;
    }
} catch (PDOException $e) {
    die('Error: ' . $e->getMessage());
}

$mostrarListado = !(
    (isset($_GET['form_rep'], $_GET['form']) && $_GET['form_rep'] === 'add' && $_GET['form'] === 'add') ||
    (isset($_GET['form_rep'], $_GET['form'], $_GET['reposicion_id']) && $_GET['form_rep'] === 'edit' && $_GET['form'] === 'edit')
);

$BASE_PATH = '../../';
$page_title = 'Reposición de Materia Prima';
$extra_css = ['vendor/datatables/dataTables.bootstrap4.min.css'];
$extra_js_plugins = [
    'vendor/datatables/jquery.dataTables.min.js',
    'vendor/datatables/dataTables.bootstrap4.min.js',
];

include '../../header.php';
?>

<div class="modal fade" id="anularModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Anular reposición</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <p><strong>¿Anular este ingreso de materia prima?</strong></p>
        <p class="mb-0" id="anularRepInfo"></p>
        <p class="small text-muted mt-2">Se revertirá el stock y el avance del pedido de MP.</p>
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
    <h1 class="h3 mb-0 text-gray-800">Reposición de Materia Prima</h1>
    <a href="?form_rep=add&form=add" class="btn btn-primary btn-sm shadow-sm">
      <i class="fas fa-plus fa-sm text-white-50"></i> Nueva reposición
    </a>
  </div>

  <?php
  if (!empty($_GET['alert'])) {
      $msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
      $map = [
          1 => ['msg' => 'Reposición registrada y stock actualizado.', 'class' => 'alert-success'],
          2 => ['msg' => 'Registro actualizado.', 'class' => 'alert-success'],
          3 => ['msg' => 'Reposición anulada. Stock y pedido revertidos.', 'class' => 'alert-success'],
          4 => ['msg' => $msg ?: 'No se pudo completar la operación.', 'class' => 'alert-danger'],
          5 => ['msg' => $msg ?: 'No se puede editar este registro.', 'class' => 'alert-danger'],
      ];
      if (isset($map[(int)$_GET['alert']])) {
          $d = $map[(int)$_GET['alert']];
          echo "<div id='alert-message' class='alert {$d['class']} alert-dismissible fade show'>{$d['msg']}
                <button type='button' class='close' data-dismiss='alert'><span>&times;</span></button></div>";
      }
  }
  ?>

  <div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Ingresos de MP a depósito</h6></div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered" id="dataTable" width="100%">
          <thead>
            <tr>
              <th>N°</th>
              <th>Fecha</th>
              <th>Pedido MP</th>
              <th>Depósito</th>
              <th>Materias</th>
              <th>Cant. total</th>
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
                        r.reposicion_id,
                        r.reposicion_fecha,
                        r.reposicion_estado,
                        r.id_pedido_mat_prod,
                        d.deposito_descri,
                        u.username,
                        STRING_AGG(DISTINCT mp.materia_prima_descripcion, ', ' ORDER BY mp.materia_prima_descripcion) AS materias,
                        COALESCE(SUM(rd.reposicion_cantidad), 0)::int AS cantidad_total
                    FROM reposicion_materia r
                    JOIN deposito d ON d.deposito_id = r.deposito_id
                    JOIN usuarios u ON u.id_usuario = r.id_usuario
                    LEFT JOIN reposicion_materia_detalle rd ON rd.reposicion_id = r.reposicion_id
                    LEFT JOIN materia_prima mp ON mp.id_materia_prima = rd.id_materia_prima
                    GROUP BY r.reposicion_id, r.reposicion_fecha, r.reposicion_estado,
                             r.id_pedido_mat_prod, d.deposito_descri, u.username
                    ORDER BY r.reposicion_id DESC
                ";
                foreach ($pdo->query($sql) as $row) {
                    $id = (int)$row['reposicion_id'];
                    $est = strtoupper(trim($row['reposicion_estado']));
                    $cls = $est === 'REGISTRADO' ? 'badge-success' : 'badge-secondary';
                    echo '<tr>
                      <td>' . $id . '</td>
                      <td>' . htmlspecialchars(substr((string)$row['reposicion_fecha'], 0, 10)) . '</td>
                      <td>#' . (int)$row['id_pedido_mat_prod'] . '</td>
                      <td>' . htmlspecialchars($row['deposito_descri']) . '</td>
                      <td>' . htmlspecialchars($row['materias'] ?? '-') . '</td>
                      <td class="text-right">' . (int)$row['cantidad_total'] . '</td>
                      <td>' . htmlspecialchars($row['username']) . '</td>
                      <td><span class="badge ' . $cls . '">' . htmlspecialchars($row['reposicion_estado']) . '</span></td>
                      <td><button type="button" class="btn btn-info btn-sm btn-detalle" data-id="' . $id . '">Ver</button></td>
                      <td class="text-nowrap">';
                    if ($est === 'REGISTRADO') {
                        echo '<a href="?form_rep=edit&form=edit&reposicion_id=' . $id . '" class="btn btn-warning btn-sm" title="Editar fecha"><i class="fas fa-edit"></i></a> ';
                        echo '<button type="button" class="btn btn-danger btn-sm btn-anular" data-id="' . $id . '"><i class="fas fa-times"></i></button>';
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
      <div class="modal-header"><h5 class="modal-title">Detalle de reposición</h5>
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
      fetch('get_detalle.php?reposicion_id=' + btn.dataset.id)
        .then(r => r.json())
        .then(data => {
          if (!data.success) { body.innerHTML = '<p class=\"text-danger\">' + (data.error || '') + '</p>'; }
          else {
            const c = data.cabecera;
            let html = '<p><strong>N°:</strong> ' + c.reposicion_id + ' | <strong>Estado:</strong> ' + c.reposicion_estado + '</p>';
            html += '<p><strong>Pedido MP:</strong> #' + c.id_pedido_mat_prod + ' | <strong>Depósito:</strong> ' + c.deposito_descri + '</p>';
            html += '<p><strong>Fecha:</strong> ' + c.reposicion_fecha + ' | <strong>Usuario:</strong> ' + c.username + '</p>';
            html += '<table class=\"table table-sm table-bordered\"><thead><tr><th>Materia prima</th><th>Cantidad</th></tr></thead><tbody>';
            (data.detalle || []).forEach(x => {
              html += '<tr><td>' + x.materia_prima_descripcion + '</td><td class=\"text-right\">' + x.reposicion_cantidad + '</td></tr>';
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
      document.getElementById('anularRepInfo').textContent = 'Reposición N° ' + idAnular;
      jQuery('#anularModal').modal('show');
    });
  });
  const btnA = document.getElementById('confirmarAnularBtn');
  if (btnA) {
    btnA.addEventListener('click', function() {
      if (!idAnular) return;
      this.disabled = true;
      fetch('anular_reposicion.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ reposicion_id: idAnular })
      }).then(r => r.json()).then(data => {
        if (data.success) { jQuery('#anularModal').modal('hide'); location.href = 'view.php?alert=3'; }
        else { alert(data.message || 'Error'); this.disabled = false; }
      });
    });
  }
});
";
include '../../footer.php';
