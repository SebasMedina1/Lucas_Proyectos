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
check_permission('CONTROL_CALIDAD');

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $query = $pdo->prepare('SELECT * FROM usuarios WHERE username = :username');
    $query->execute([':username' => $_SESSION['username']]);
    if (!$query->fetch()) {
        session_destroy();
        header('Location: ../../login.html');
        exit;
    }
} catch (PDOException $e) {
    die('Error en la conexión: ' . $e->getMessage());
}

$mostrarListado = !(
    (isset($_GET['form_calidad'], $_GET['form']) && $_GET['form_calidad'] === 'add' && $_GET['form'] === 'add') ||
    (isset($_GET['form_calidad'], $_GET['form'], $_GET['calidad_id']) && $_GET['form_calidad'] === 'edit' && $_GET['form'] === 'edit')
);

$BASE_PATH = '../../';
$page_title = 'Control de Calidad';
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
        <h5 class="modal-title">Confirmar anulación</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <p><strong>¿Anular este control de calidad?</strong></p>
        <p class="mb-0" id="anularCalidadInfo"></p>
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
    <h1 class="h3 mb-0 text-gray-800">Control de Calidad</h1>
    <a href="?form_calidad=add&form=add" class="btn btn-primary btn-sm shadow-sm">
      <i class="fas fa-plus fa-sm text-white-50"></i> Nueva inspección
    </a>
  </div>

  <?php
  if (!empty($_GET['alert'])) {
      $msgCustom = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
      $alertMap = [
          1 => ['msg' => 'Inspección registrada — veredicto: APROBADO.', 'class' => 'alert-success'],
          2 => ['msg' => 'Registro actualizado correctamente.', 'class' => 'alert-success'],
          3 => ['msg' => 'Control anulado correctamente.', 'class' => 'alert-success'],
          4 => ['msg' => $msgCustom ?: 'No se pudo realizar la operación.', 'class' => 'alert-danger'],
          5 => ['msg' => $msgCustom ?: 'No se puede editar este registro.', 'class' => 'alert-danger'],
          6 => ['msg' => 'Inspección registrada — veredicto: NO CONFORME. Registre pérdidas si corresponde.', 'class' => 'alert-warning'],
      ];
      if (isset($alertMap[(int)$_GET['alert']])) {
          $d = $alertMap[(int)$_GET['alert']];
          echo "<div id='alert-message' class='alert {$d['class']} alert-dismissible fade show'>{$d['msg']}
                <button type='button' class='close' data-dismiss='alert'><span>&times;</span></button></div>";
      }
  }
  ?>

  <div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Inspecciones de calidad</h6></div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered" id="dataTable" width="100%">
          <thead>
            <tr>
              <th>N°</th>
              <th>Fecha</th>
              <th>Lote PT</th>
              <th>OP</th>
              <th>Inspector</th>
              <th>Veredicto</th>
              <th>Detalle</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php
            try {
                $sql = "
                    SELECT
                        cc.calidad_id,
                        cc.calidad_fecha,
                        cc.calidad_estado,
                        cc.terminado_id,
                        pt.orden_id,
                        TRIM(per.personal_nombre || ' ' || per.personal_apellido) AS inspector,
                        (SELECT COUNT(*) FROM perdidas pe
                         WHERE pe.calidad_id = cc.calidad_id
                           AND UPPER(TRIM(pe.perdida_estado)) <> 'ANULADO') AS tiene_perdidas
                    FROM control_calidad_produccion cc
                    JOIN producto_terminado pt ON pt.terminado_id = cc.terminado_id
                    JOIN inspectores i ON i.id_inspectores = cc.id_inspectores
                    JOIN personal per ON per.id_personal = i.id_personal
                    WHERE UPPER(TRIM(cc.calidad_estado)) <> 'ANULADO'
                    ORDER BY cc.calidad_id DESC
                ";
                foreach ($pdo->query($sql) as $row) {
                    $id = (int)$row['calidad_id'];
                    $est = strtoupper(trim($row['calidad_estado']));
                    $cls = $est === 'APROBADO' ? 'badge-success' : 'badge-danger';
                    $perd = (int)$row['tiene_perdidas'] > 0;
                    echo '<tr>
                      <td>' . $id . '</td>
                      <td>' . htmlspecialchars(substr((string)$row['calidad_fecha'], 0, 10)) . '</td>
                      <td>' . (int)$row['terminado_id'] . '</td>
                      <td>' . (int)$row['orden_id'] . '</td>
                      <td>' . htmlspecialchars($row['inspector']) . '</td>
                      <td><span class="badge ' . $cls . '">' . htmlspecialchars($row['calidad_estado']) . '</span></td>
                      <td><button type="button" class="btn btn-info btn-sm btn-detalle" data-id="' . $id . '">Ver</button></td>
                      <td>';
                    echo '<a href="?form_calidad=edit&form=edit&calidad_id=' . $id . '" class="btn btn-warning btn-sm" title="Editar"><i class="fas fa-edit"></i></a> ';
                    if ($est === 'NO CONFORME' && !$perd && has_permission('PERDIDAS', false)) {
                        echo '<a href="../perdidas/view.php?form_perdida=add&form=add&calidad_id=' . $id . '" class="btn btn-dark btn-sm" title="Registrar pérdida"><i class="fas fa-trash-alt"></i></a> ';
                    }
                    if (!$perd) {
                        echo '<button type="button" class="btn btn-danger btn-sm btn-anular" data-id="' . $id . '"><i class="fas fa-times"></i></button>';
                    }
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
      <div class="modal-header"><h5 class="modal-title">Detalle inspección</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
      <div class="modal-body" id="detalleCalidadBody"></div>
    </div>
  </div>
</div>

<?php
$inline_js = "
document.addEventListener('DOMContentLoaded', () => {
  const am = document.getElementById('alert-message');
  if (am) setTimeout(() => am.remove(), 8000);
  if (window.jQuery && jQuery().DataTable) {
    jQuery('#dataTable').DataTable({
      language: { url: '{$BASE_PATH}vendor/datatables/Spanish.json' },
      order: [[0, 'desc']],
      pageLength: 10
    });
  }
  document.querySelectorAll('.btn-detalle').forEach(btn => {
    btn.addEventListener('click', () => {
      const body = document.getElementById('detalleCalidadBody');
      body.innerHTML = '<p class=\"text-center\"><i class=\"fas fa-spinner fa-spin\"></i></p>';
      fetch('get_detalle.php?calidad_id=' + btn.dataset.id)
        .then(r => r.json())
        .then(data => {
          if (!data.success) { body.innerHTML = '<p class=\"text-danger\">' + (data.error || '') + '</p>'; }
          else {
            const c = data.cabecera;
            let html = '<p><strong>Veredicto:</strong> ' + c.calidad_estado + ' | <strong>Lote PT:</strong> ' + c.terminado_id + ' | <strong>OP:</strong> ' + c.orden_id + '</p>';
            html += '<p><strong>Inspector:</strong> ' + c.inspector + '</p>';
            html += '<table class=\"table table-sm table-bordered\"><thead><tr><th>Producto</th><th>Parámetro</th><th>Valor</th><th>Cumple</th></tr></thead><tbody>';
            (data.detalle || []).forEach(x => {
              html += '<tr><td>' + x.producto_descri + '</td><td>' + x.parametro_descri + '</td><td>' + (x.valor_medido || '-') + '</td><td>' + (x.cumple_parametro ? 'Sí' : 'No') + '</td></tr>';
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
      document.getElementById('anularCalidadInfo').textContent = 'Control N° ' + idAnular;
      jQuery('#anularModal').modal('show');
    });
  });
  const btnA = document.getElementById('confirmarAnularBtn');
  if (btnA) {
    btnA.addEventListener('click', function() {
      if (!idAnular) return;
      this.disabled = true;
      fetch('anular_calidad.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ calidad_id: idAnular })
      }).then(r => r.json()).then(data => {
        if (data.success) { jQuery('#anularModal').modal('hide'); location.href = 'view.php?alert=3'; }
        else { alert(data.message || 'Error'); this.disabled = false; }
      });
    });
  }
});
";
include '../../footer.php';
