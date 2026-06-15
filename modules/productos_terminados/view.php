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
check_permission('PRODUCTOS_TERMINADOS');

$username = $_SESSION['username'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $query = $pdo->prepare('SELECT * FROM usuarios WHERE username = :username');
    $query->execute([':username' => $username]);
    if (!$query->fetch()) {
        session_destroy();
        header('Location: ../../login.html');
        exit;
    }
} catch (PDOException $e) {
    die('Error en la conexión: ' . $e->getMessage());
}

$mostrarListado = !(
    (isset($_GET['form_pt'], $_GET['form']) && $_GET['form_pt'] === 'add' && $_GET['form'] === 'add') ||
    (isset($_GET['form_pt'], $_GET['form'], $_GET['terminado_id']) && $_GET['form_pt'] === 'edit' && $_GET['form'] === 'edit')
);

$BASE_PATH = '../../';
$page_title = 'Productos Terminados';
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
        <p><strong>¿Anular este registro de productos terminados?</strong></p>
        <p class="mb-0" id="anularPtInfo"></p>
        <p class="small text-muted mt-2">Se revertirá el stock de productos ingresado.</p>
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
    <h1 class="h3 mb-0 text-gray-800">Productos Terminados</h1>
    <a href="?form_pt=add&form=add" class="btn btn-primary btn-sm shadow-sm">
      <i class="fas fa-plus fa-sm text-white-50"></i> Nuevo registro
    </a>
  </div>

  <?php
  if (!empty($_GET['alert'])) {
      $msgCustom = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
      $alertMap = [
          1 => ['msg' => 'Productos terminados registrados correctamente.', 'class' => 'alert-success'],
          2 => ['msg' => 'Registro actualizado correctamente.', 'class' => 'alert-success'],
          3 => ['msg' => 'Registro anulado correctamente.', 'class' => 'alert-success'],
          4 => ['msg' => $msgCustom ?: 'No se pudo realizar la operación.', 'class' => 'alert-danger'],
          5 => ['msg' => $msgCustom ?: 'No se puede editar este registro.', 'class' => 'alert-danger'],
      ];
      if (isset($alertMap[(int)$_GET['alert']])) {
          $d = $alertMap[(int)$_GET['alert']];
          echo "<div id='alert-message' class='alert {$d['class']} alert-dismissible fade show'>{$d['msg']}
                <button type='button' class='close' data-dismiss='alert'><span>&times;</span></button></div>";
      }
  }
  ?>

  <div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Lista de ingresos a PT</h6></div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered" id="dataTable" width="100%">
          <thead>
            <tr>
              <th>N°</th>
              <th>Fecha</th>
              <th>OP</th>
              <th>Productos</th>
              <th>Cant. total</th>
              <th>Usuario</th>
              <th>Calidad</th>
              <th>Detalle</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php
            try {
                $sql = "
                    SELECT
                        pt.terminado_id,
                        pt.terminado_fecha,
                        pt.orden_id,
                        u.username,
                        STRING_AGG(DISTINCT p.producto_descri, ', ' ORDER BY p.producto_descri) AS productos,
                        COALESCE(SUM(ptd.terminado_cantidad), 0)::int AS cantidad_total,
                        (SELECT COUNT(*) FROM control_calidad_produccion cc
                         WHERE cc.terminado_id = pt.terminado_id) AS tiene_calidad
                    FROM producto_terminado pt
                    JOIN usuarios u ON u.id_usuario = pt.id_usuario
                    LEFT JOIN productos_terminados_detalle ptd ON ptd.terminado_id = pt.terminado_id
                    LEFT JOIN productos p ON p.producto_id = ptd.producto_id
                    GROUP BY pt.terminado_id, pt.terminado_fecha, pt.orden_id, u.username
                    ORDER BY pt.terminado_id DESC
                ";
                foreach ($pdo->query($sql) as $row) {
                    $id = (int)$row['terminado_id'];
                    $cal = (int)$row['tiene_calidad'] > 0;
                    $badgeCal = $cal
                        ? '<span class="badge badge-info">Con CC</span>'
                        : '<span class="badge badge-secondary">Pendiente CC</span>';
                    echo '<tr>
                      <td>' . $id . '</td>
                      <td>' . htmlspecialchars(substr((string)$row['terminado_fecha'], 0, 10)) . '</td>
                      <td>' . (int)$row['orden_id'] . '</td>
                      <td>' . htmlspecialchars($row['productos'] ?? '-') . '</td>
                      <td class="text-right">' . (int)$row['cantidad_total'] . '</td>
                      <td>' . htmlspecialchars($row['username']) . '</td>
                      <td>' . $badgeCal . '</td>
                      <td><button type="button" class="btn btn-info btn-sm btn-detalle" data-id="' . $id . '">Ver</button></td>
                      <td>';
                    echo '<a href="?form_pt=edit&form=edit&terminado_id=' . $id . '" class="btn btn-warning btn-sm" title="Editar fecha"><i class="fas fa-edit"></i></a> ';
                    if (!$cal) {
                        echo '<button type="button" class="btn btn-danger btn-sm btn-anular" data-id="' . $id . '"><i class="fas fa-times"></i></button>';
                    }
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
      <div class="modal-header"><h5 class="modal-title">Detalle — Productos terminados</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
      <div class="modal-body" id="detallePtBody"></div>
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
      const body = document.getElementById('detallePtBody');
      body.innerHTML = '<p class=\"text-center\"><i class=\"fas fa-spinner fa-spin\"></i></p>';
      fetch('get_detalle.php?terminado_id=' + btn.dataset.id)
        .then(r => r.json())
        .then(data => {
          if (!data.success) { body.innerHTML = '<p class=\"text-danger\">' + (data.error || '') + '</p>'; }
          else {
            const c = data.cabecera;
            let html = '<p><strong>N°:</strong> ' + c.terminado_id + ' | <strong>OP:</strong> ' + c.orden_id + ' (' + c.orden_prod_estado + ')</p>';
            html += '<p><strong>Fecha:</strong> ' + c.terminado_fecha + ' | <strong>Usuario:</strong> ' + c.username + '</p>';
            html += '<table class=\"table table-sm table-bordered\"><thead><tr><th>Producto</th><th>Cant.</th><th>Depósito</th><th>Elab.</th><th>Venc.</th></tr></thead><tbody>';
            (data.detalle || []).forEach(x => {
              html += '<tr><td>' + x.producto_descri + '</td><td class=\"text-right\">' + x.terminado_cantidad + '</td><td>' + x.deposito_descri + '</td><td>' + x.fecha_elab + '</td><td>' + x.fecha_venc + '</td></tr>';
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
      document.getElementById('anularPtInfo').textContent = 'Registro N° ' + idAnular;
      jQuery('#anularModal').modal('show');
    });
  });
  const btnA = document.getElementById('confirmarAnularBtn');
  if (btnA) {
    btnA.addEventListener('click', function() {
      if (!idAnular) return;
      this.disabled = true;
      fetch('anular_producto_terminado.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ terminado_id: idAnular })
      }).then(r => r.json()).then(data => {
        if (data.success) { jQuery('#anularModal').modal('hide'); location.href = 'view.php?alert=3'; }
        else { alert(data.message || 'Error'); this.disabled = false; }
      });
    });
  }
});
";
include '../../footer.php';
