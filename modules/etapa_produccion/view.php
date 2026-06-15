<?php
session_start();
if (empty($_SESSION['username'])) {
    echo "<script>alert('Token de sesión inválido'); window.location.href='../../login.html';</script>";
    exit;
}

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');
check_permission('ETAPAS_PRODUCCION');

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
    (isset($_GET['form_etapa'], $_GET['form']) && $_GET['form_etapa'] === 'add' && $_GET['form'] === 'add') ||
    (isset($_GET['form_etapa'], $_GET['form'], $_GET['producto_id']) && $_GET['form_etapa'] === 'edit' && $_GET['form'] === 'edit')
);

$BASE_PATH = '../../';
$page_title = 'Etapas de Producción';
$extra_css = ['vendor/datatables/dataTables.bootstrap4.min.css'];
$extra_js_plugins = [
    'vendor/datatables/jquery.dataTables.min.js',
    'vendor/datatables/dataTables.bootstrap4.min.js',
];

include '../../header.php';
?>

<div class="modal fade" id="detalleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Ruta de producción</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body" id="detalleBody">Cargando...</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="anularModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Anular ruta</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <p><strong>¿Anular toda la ruta de etapas de este producto?</strong></p>
        <p class="mb-0" id="anularInfo"></p>
        <p class="small text-muted mt-2">Solo es posible si ninguna etapa fue usada en control de producción.</p>
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
    <h1 class="h3 mb-0 text-gray-800">Etapas de Producción</h1>
    <a href="?form_etapa=add&form=add" class="btn btn-primary btn-sm shadow-sm">
      <i class="fas fa-plus fa-sm text-white-50"></i> Nueva ruta
    </a>
  </div>

  <div class="alert alert-info small">
    Defina la secuencia de pasos por producto (Preparación → Cocción → Armado → Empaque).
    Estas etapas se usan en <strong>Control de producción</strong> y determinan cuándo un ítem está listo para empaque.
  </div>

  <?php
  if (!empty($_GET['alert'])) {
      $msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
      $map = [
          1 => ['msg' => 'Ruta de etapas registrada.', 'class' => 'alert-success'],
          2 => ['msg' => 'Ruta actualizada.', 'class' => 'alert-success'],
          3 => ['msg' => 'Ruta anulada.', 'class' => 'alert-success'],
          4 => ['msg' => $msg ?: 'No se pudo completar la operación.', 'class' => 'alert-danger'],
      ];
      if (isset($map[(int)$_GET['alert']])) {
          $d = $map[(int)$_GET['alert']];
          echo "<div class='alert {$d['class']} alert-dismissible fade show'>{$d['msg']}
                <button type='button' class='close' data-dismiss='alert'><span>&times;</span></button></div>";
      }
  }

  $rows = $pdo->query("
      SELECT
          p.producto_id,
          p.producto_descripcion,
          COUNT(ep.etapa_id)::int AS num_etapas,
          MIN(ep.etapa_fecha) AS ruta_fecha,
          string_agg(ed.etapa_nombre, ' → ' ORDER BY ed.etapa_secuencia) AS ruta_resumen
      FROM productos p
      JOIN etapa_produccion ep ON ep.producto_id = p.producto_id
          AND UPPER(TRIM(ep.etapa_estado)) = 'ACTIVA'
      JOIN etapa_detalle_produccion ed ON ed.etapa_id = ep.etapa_id AND ed.producto_id = p.producto_id
      GROUP BY p.producto_id, p.producto_descripcion
      ORDER BY p.producto_descripcion
  ")->fetchAll();
  ?>

  <div class="card shadow mb-4">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered table-hover" id="tablaEtapas" width="100%">
          <thead class="table-dark">
            <tr>
              <th>Producto</th>
              <th class="text-center">Etapas</th>
              <th>Secuencia</th>
              <th>Fecha alta</th>
              <th style="width:140px">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['producto_descripcion']) ?></td>
              <td class="text-center"><span class="badge badge-primary"><?= (int)$r['num_etapas'] ?></span></td>
              <td><small><?= htmlspecialchars($r['ruta_resumen'] ?? '') ?></small></td>
              <td><?= htmlspecialchars($r['ruta_fecha'] ?? '') ?></td>
              <td>
                <button type="button" class="btn btn-info btn-sm btn-detalle" data-pid="<?= (int)$r['producto_id'] ?>" title="Ver"><i class="fas fa-eye"></i></button>
                <a href="?form_etapa=edit&form=edit&producto_id=<?= (int)$r['producto_id'] ?>" class="btn btn-warning btn-sm" title="Editar"><i class="fas fa-edit"></i></a>
                <button type="button" class="btn btn-danger btn-sm btn-anular" data-pid="<?= (int)$r['producto_id'] ?>" data-nom="<?= htmlspecialchars($r['producto_descripcion']) ?>" title="Anular"><i class="fas fa-times"></i></button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
      $('#tablaEtapas').DataTable({ language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' }, order: [[0, 'asc']] });
    }

    let anularPid = 0;
    document.querySelectorAll('.btn-detalle').forEach(btn => {
      btn.addEventListener('click', async () => {
        const pid = btn.dataset.pid;
        const body = document.getElementById('detalleBody');
        body.innerHTML = 'Cargando...';
        $('#detalleModal').modal('show');
        try {
          const r = await fetch('get_detalle.php?producto_id=' + pid);
          const data = await r.json();
          if (!data.success) { body.innerHTML = '<div class="alert alert-danger">' + (data.error || 'Error') + '</div>'; return; }
          let html = '<p><strong>' + data.producto.producto_descripcion + '</strong></p><ol class="mb-0">';
          (data.etapas || []).forEach(e => {
            html += '<li class="mb-2"><strong>' + e.etapa_nombre + '</strong> (sec. ' + e.etapa_secuencia + ')';
            if (e.etapa_tiempo_estimado) html += ' — ' + e.etapa_tiempo_estimado + ' min';
            html += '<br><span class="text-muted small">' + e.etapa_procedimiento + '</span>';
            if (e.en_uso) html += ' <span class="badge badge-warning">En uso</span>';
            html += '</li>';
          });
          html += '</ol>';
          body.innerHTML = html;
        } catch (e) { body.innerHTML = '<div class="alert alert-danger">Error de conexión</div>'; }
      });
    });

    document.querySelectorAll('.btn-anular').forEach(btn => {
      btn.addEventListener('click', () => {
        anularPid = parseInt(btn.dataset.pid, 10);
        document.getElementById('anularInfo').textContent = btn.dataset.nom;
        $('#anularModal').modal('show');
      });
    });

    document.getElementById('confirmarAnularBtn')?.addEventListener('click', async () => {
      if (!anularPid) return;
      const fd = new FormData();
      fd.append('producto_id', anularPid);
      try {
        const r = await fetch('anular_ruta.php', { method: 'POST', body: fd });
        const data = await r.json();
        if (data.success) { window.location.href = 'view.php?alert=3'; return; }
        alert(data.error || 'No se pudo anular');
      } catch (e) { alert('Error de conexión'); }
    });
  });
  </script>

<?php else: ?>
  <?php include 'form.php'; ?>
<?php endif; ?>

<?php include '../../footer.php'; ?>
