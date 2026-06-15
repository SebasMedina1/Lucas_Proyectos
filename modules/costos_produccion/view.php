<?php
session_start();
if (empty($_SESSION['username'])) {
    echo "<script>alert('Token de sesión inválido'); window.location.href='../../login.html';</script>";
    exit;
}

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');
check_permission('COSTOS_PRODUCCION');

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

if (
    isset($_GET['form_costo'], $_GET['form'], $_GET['costo_id']) &&
    $_GET['form_costo'] === 'edit' && $_GET['form'] === 'edit'
) {
    $stmt = $pdo->prepare('SELECT costo_estado FROM costo_produccion WHERE costo_id = :id LIMIT 1');
    $stmt->execute([':id' => (int)$_GET['costo_id']]);
    if (strtoupper(trim((string)$stmt->fetchColumn())) !== 'PENDIENTE') {
        header('Location: view.php?alert=5');
        exit;
    }
}

$mostrarListado = !(
    (isset($_GET['form_costo'], $_GET['form']) && $_GET['form_costo'] === 'add' && $_GET['form'] === 'add') ||
    (isset($_GET['form_costo'], $_GET['form'], $_GET['costo_id']) && $_GET['form_costo'] === 'edit' && $_GET['form'] === 'edit')
);

$BASE_PATH = '../../';
$page_title = 'Costos de Producción';
$extra_css = ['vendor/datatables/dataTables.bootstrap4.min.css'];
$extra_js_plugins = [
    'vendor/datatables/jquery.dataTables.min.js',
    'vendor/datatables/dataTables.bootstrap4.min.js',
];

include '../../header.php';

function badgeEstadoCosto(string $estado): string
{
    $e = strtoupper(trim($estado));
    $map = ['PENDIENTE' => 'badge-warning', 'CERRADO' => 'badge-success', 'ANULADO' => 'badge-danger'];
    $cls = $map[$e] ?? 'badge-secondary';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($estado) . '</span>';
}
?>

<div class="modal fade" id="anularModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Anular costeo</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <p><strong>¿Anular este registro de costos?</strong></p>
        <p class="mb-0" id="anularCostoInfo"></p>
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
    <h1 class="h3 mb-0 text-gray-800">Costos de Producción</h1>
    <a href="?form_costo=add&form=add" class="btn btn-primary btn-sm shadow-sm">
      <i class="fas fa-plus fa-sm text-white-50"></i> Nuevo costeo
    </a>
  </div>

  <?php
  if (!empty($_GET['alert'])) {
      $msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
      $map = [
          1 => ['msg' => 'Costeo registrado correctamente.', 'class' => 'alert-success'],
          2 => ['msg' => 'Costeo actualizado.', 'class' => 'alert-success'],
          3 => ['msg' => 'Costeo anulado.', 'class' => 'alert-success'],
          4 => ['msg' => $msg ?: 'No se pudo completar la operación.', 'class' => 'alert-danger'],
          5 => ['msg' => $msg ?: 'No se puede editar este registro.', 'class' => 'alert-danger'],
          6 => ['msg' => 'Costeo cerrado correctamente.', 'class' => 'alert-success'],
      ];
      if (isset($map[(int)$_GET['alert']])) {
          $d = $map[(int)$_GET['alert']];
          echo "<div id='alert-message' class='alert {$d['class']} alert-dismissible fade show'>{$d['msg']}
                <button type='button' class='close' data-dismiss='alert'><span>&times;</span></button></div>";
      }
  }
  ?>

  <div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Costeos por orden de producción</h6></div>
    <div class="card-body">
      <p class="small text-muted">Consolida MP (consumos), mano de obra y CIF. Una OP activa solo puede tener un costeo PENDIENTE o CERRADO.</p>
      <div class="table-responsive">
        <table class="table table-bordered" id="dataTable" width="100%">
          <thead>
            <tr>
              <th>N°</th>
              <th>Fecha</th>
              <th>OP</th>
              <th>Total (Gs)</th>
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
                    SELECT c.costo_id, c.costo_fecha, c.costo_estado, c.costo_total, c.orden_id, u.username
                    FROM costo_produccion c
                    JOIN usuarios u ON u.id_usuario = c.id_usuario
                    ORDER BY c.costo_id DESC
                ";
                foreach ($pdo->query($sql) as $row) {
                    $id = (int)$row['costo_id'];
                    $est = strtoupper(trim($row['costo_estado']));
                    echo '<tr>
                      <td>' . $id . '</td>
                      <td>' . htmlspecialchars(substr((string)$row['costo_fecha'], 0, 10)) . '</td>
                      <td>#' . (int)$row['orden_id'] . '</td>
                      <td class="text-right">' . number_format((int)$row['costo_total'], 0, ',', '.') . '</td>
                      <td>' . htmlspecialchars($row['username']) . '</td>
                      <td>' . badgeEstadoCosto($row['costo_estado']) . '</td>
                      <td><button type="button" class="btn btn-info btn-sm btn-detalle" data-id="' . $id . '">Ver</button></td>
                      <td class="text-nowrap">';
                    if ($est === 'PENDIENTE') {
                        echo '<a href="?form_costo=edit&form=edit&costo_id=' . $id . '" class="btn btn-warning btn-sm" title="Editar"><i class="fas fa-edit"></i></a> ';
                        echo '<form action="proses.php?act=cerrar" method="POST" class="d-inline" onsubmit="return confirm(\'¿Cerrar este costeo?\');">';
                        echo '<input type="hidden" name="costo_id" value="' . $id . '">';
                        echo '<button type="submit" class="btn btn-success btn-sm" title="Cerrar"><i class="fas fa-lock"></i></button></form> ';
                        echo '<button type="button" class="btn btn-danger btn-sm btn-anular" data-id="' . $id . '"><i class="fas fa-times"></i></button>';
                    } elseif ($est === 'CERRADO') {
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
      <div class="modal-header"><h5 class="modal-title">Detalle del costeo</h5>
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
  const fmt = n => (parseInt(n, 10) || 0).toLocaleString('es-PY');
  document.querySelectorAll('.btn-detalle').forEach(btn => {
    btn.addEventListener('click', () => {
      const body = document.getElementById('detalleBody');
      body.innerHTML = '<p class=\"text-center\"><i class=\"fas fa-spinner fa-spin\"></i></p>';
      fetch('get_detalle.php?costo_id=' + btn.dataset.id)
        .then(r => r.json())
        .then(data => {
          if (!data.success) { body.innerHTML = '<p class=\"text-danger\">' + (data.error || '') + '</p>'; }
          else {
            const c = data.cabecera;
            const r = data.resumen || {};
            let html = '<p><strong>N°:</strong> ' + c.costo_id + ' | <strong>OP:</strong> #' + c.orden_id + ' | <strong>Estado:</strong> ' + c.costo_estado + '</p>';
            html += '<p><strong>Fecha:</strong> ' + c.costo_fecha + ' | <strong>Usuario:</strong> ' + c.username + '</p>';
            html += '<p class=\"small\">MP: Gs. ' + fmt(r.mp) + ' | MO: Gs. ' + fmt(r.mo) + ' | CIF: Gs. ' + fmt(r.cif) + ' | <strong>Total: Gs. ' + fmt(c.costo_total) + '</strong></p>';
            html += '<table class=\"table table-sm table-bordered\"><thead><tr><th>Tipo</th><th>Descripción</th><th class=\"text-right\">Cant.</th><th class=\"text-right\">Precio</th><th class=\"text-right\">Subtotal</th></tr></thead><tbody>';
            (data.detalle || []).forEach(x => {
              html += '<tr><td>' + x.tipo + '</td><td>' + x.descripcion + '</td>';
              html += '<td class=\"text-right\">' + x.cantidad + '</td><td class=\"text-right\">' + fmt(x.precio) + '</td>';
              html += '<td class=\"text-right\">' + fmt(x.subtotal) + '</td></tr>';
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
      document.getElementById('anularCostoInfo').textContent = 'Costeo N° ' + idAnular;
      jQuery('#anularModal').modal('show');
    });
  });
  const btnA = document.getElementById('confirmarAnularBtn');
  if (btnA) {
    btnA.addEventListener('click', function() {
      if (!idAnular) return;
      this.disabled = true;
      fetch('anular_costo.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ costo_id: idAnular })
      }).then(r => r.json()).then(data => {
        if (data.success) { jQuery('#anularModal').modal('hide'); location.href = 'view.php?alert=3'; }
        else { alert(data.message || 'Error'); this.disabled = false; }
      });
    });
  }
});
";
include '../../footer.php';
