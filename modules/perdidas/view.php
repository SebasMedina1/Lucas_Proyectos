<?php
session_start();
if (empty($_SESSION['username'])) {
    echo "<script>alert('Token de sesión inválido'); window.location.href='../../login.html';</script>";
    exit;
}

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');
check_permission('PERDIDAS');

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
    (isset($_GET['form_perdida'], $_GET['form']) && $_GET['form_perdida'] === 'add' && $_GET['form'] === 'add') ||
    (isset($_GET['form_perdida'], $_GET['form'], $_GET['perdidas_id']) && $_GET['form_perdida'] === 'edit' && $_GET['form'] === 'edit')
);

$BASE_PATH = '../../';
$page_title = 'Pérdidas y devoluciones';
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
        <h5 class="modal-title">Anular pérdida</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <p><strong>¿Anular este registro?</strong></p>
        <p class="mb-0" id="anularInfo"></p>
        <p class="small text-muted mt-2">Se repone el stock descontado.</p>
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
    <h1 class="h3 mb-0 text-gray-800">Pérdidas y devoluciones</h1>
    <a href="?form_perdida=add&form=add" class="btn btn-primary btn-sm shadow-sm">
      <i class="fas fa-plus fa-sm text-white-50"></i> Nueva pérdida
    </a>
  </div>

  <?php
  if (!empty($_GET['alert'])) {
      $msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
      $map = [
          1 => ['msg' => 'Pérdida registrada y stock actualizado.', 'class' => 'alert-success'],
          2 => ['msg' => 'Registro actualizado.', 'class' => 'alert-success'],
          3 => ['msg' => 'Pérdida anulada y stock repuesto.', 'class' => 'alert-success'],
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
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Registros de pérdida</h6></div>
    <div class="card-body table-responsive">
      <table class="table table-bordered" id="dataTable" width="100%">
        <thead>
          <tr>
            <th>N°</th>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>CC</th>
            <th>Lote PT</th>
            <th>Productos</th>
            <th>Cant. total</th>
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
                      pe.perdidas_id,
                      pe.perdida_fecha,
                      pe.perdida_estado,
                      pe.calidad_id,
                      tp.tipo_perdida_descri,
                      cc.terminado_id,
                      STRING_AGG(DISTINCT p.producto_descri, ', ' ORDER BY p.producto_descri) AS productos,
                      COALESCE(SUM(pd.perdida_cantidad), 0)::int AS cantidad_total
                  FROM perdidas pe
                  JOIN tipo_perdida tp ON tp.tipo_perdida_id = pe.tipo_perdida_id
                  LEFT JOIN control_calidad_produccion cc ON cc.calidad_id = pe.calidad_id
                  LEFT JOIN perdidas_detalle pd ON pd.perdidas_id = pe.perdidas_id
                  LEFT JOIN productos p ON p.producto_id = pd.producto_id
                  GROUP BY pe.perdidas_id, pe.perdida_fecha, pe.perdida_estado, pe.calidad_id,
                           tp.tipo_perdida_descri, cc.terminado_id
                  ORDER BY pe.perdidas_id DESC
              ";
              foreach ($pdo->query($sql) as $row) {
                  $id = (int)$row['perdidas_id'];
                  $est = strtoupper(trim($row['perdida_estado']));
                  $cls = $est === 'REGISTRADO' ? 'badge-warning' : ($est === 'ANULADO' ? 'badge-secondary' : 'badge-info');
                  echo '<tr>
                    <td>' . $id . '</td>
                    <td>' . htmlspecialchars(substr((string)$row['perdida_fecha'], 0, 10)) . '</td>
                    <td>' . htmlspecialchars($row['tipo_perdida_descri']) . '</td>
                    <td>' . ($row['calidad_id'] ? (int)$row['calidad_id'] : '-') . '</td>
                    <td>' . ($row['terminado_id'] ? (int)$row['terminado_id'] : '-') . '</td>
                    <td>' . htmlspecialchars($row['productos'] ?? '-') . '</td>
                    <td class="text-right">' . (int)$row['cantidad_total'] . '</td>
                    <td><span class="badge ' . $cls . '">' . htmlspecialchars($row['perdida_estado']) . '</span></td>
                    <td><button type="button" class="btn btn-info btn-sm btn-detalle" data-id="' . $id . '">Ver</button></td>
                    <td>';
                  if ($est === 'REGISTRADO') {
                      echo '<a href="?form_perdida=edit&form=edit&perdidas_id=' . $id . '" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i></a> ';
                      echo '<button type="button" class="btn btn-danger btn-sm btn-anular" data-id="' . $id . '"><i class="fas fa-times"></i></button>';
                  }
                  echo '</td></tr>';
              }
          } catch (PDOException $e) {
              echo '<tr><td colspan="10" class="text-danger">' . htmlspecialchars($e->getMessage()) . '</td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
<?php else: ?>
  <?php include 'form.php'; ?>
<?php endif; ?>

<div class="modal fade" id="detalleModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Detalle de pérdida</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
      <div class="modal-body" id="detalleBody"></div>
    </div>
  </div>
</div>

<?php
$inline_js = "
document.addEventListener('DOMContentLoaded', () => {
  const am = document.getElementById('alert-message');
  if (am) setTimeout(() => am.remove(), 6000);
  if (window.jQuery && jQuery().DataTable) {
    jQuery('#dataTable').DataTable({
      language: { url: '{$BASE_PATH}vendor/datatables/Spanish.json' },
      order: [[0, 'desc']]
    });
  }
  document.querySelectorAll('.btn-detalle').forEach(btn => {
    btn.addEventListener('click', () => {
      const body = document.getElementById('detalleBody');
      body.innerHTML = '<p class=\"text-center\"><i class=\"fas fa-spinner fa-spin\"></i></p>';
      fetch('get_detalle.php?perdidas_id=' + btn.dataset.id)
        .then(r => r.json()).then(data => {
          if (!data.success) { body.innerHTML = '<p class=\"text-danger\">' + (data.error || '') + '</p>'; }
          else {
            const c = data.cabecera;
            let html = '<p><strong>Tipo:</strong> ' + c.tipo_perdida_descri + ' | <strong>Estado:</strong> ' + c.perdida_estado + '</p>';
            html += '<p><strong>CC:</strong> ' + (c.calidad_id || '-') + ' | <strong>Lote PT:</strong> ' + (c.terminado_id || '-') + '</p>';
            html += '<table class=\"table table-sm table-bordered\"><thead><tr><th>Producto</th><th>Cant.</th><th>Motivo</th></tr></thead><tbody>';
            (data.detalle || []).forEach(x => {
              html += '<tr><td>' + x.producto_descri + '</td><td>' + x.perdida_cantidad + '</td><td>' + x.perdida_motivo + '</td></tr>';
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
      document.getElementById('anularInfo').textContent = 'Pérdida N° ' + idAnular;
      jQuery('#anularModal').modal('show');
    });
  });
  const btnA = document.getElementById('confirmarAnularBtn');
  if (btnA) {
    btnA.addEventListener('click', function() {
      if (!idAnular) return;
      this.disabled = true;
      fetch('anular_perdida.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ perdidas_id: idAnular })
      }).then(r => r.json()).then(data => {
        if (data.success) location.href = 'view.php?alert=3';
        else { alert(data.message || 'Error'); this.disabled = false; }
      });
    });
  }
});
";
include '../../footer.php';
