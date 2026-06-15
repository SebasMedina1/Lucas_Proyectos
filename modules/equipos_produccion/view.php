<?php
session_start();
if (empty($_SESSION['username'])) {
    echo "<script>alert('Token de sesión inválido'); window.location.href='../../login.html';</script>";
    exit;
}

require_once realpath(__DIR__ . '/../../config/database.php');
require_once realpath(__DIR__ . '/../../config/permissions.php');
check_permission('EQUIPOS_PRODUCCION');

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
    (isset($_GET['form_equipo'], $_GET['form']) && $_GET['form_equipo'] === 'add' && $_GET['form'] === 'add') ||
    (isset($_GET['form_equipo'], $_GET['form'], $_GET['equipo_id']) && $_GET['form_equipo'] === 'edit' && $_GET['form'] === 'edit')
);

$BASE_PATH = '../../';
$page_title = 'Equipos de Trabajo';
$extra_css = ['vendor/datatables/dataTables.bootstrap4.min.css'];
$extra_js_plugins = [
    'vendor/datatables/jquery.dataTables.min.js',
    'vendor/datatables/dataTables.bootstrap4.min.js',
];

include '../../header.php';

function badgeEstadoEquipo(string $estado): string
{
    $e = strtoupper(trim($estado));
    $map = ['PENDIENTE' => 'badge-warning', 'ACTIVO' => 'badge-success', 'ANULADO' => 'badge-danger'];
    $cls = $map[$e] ?? 'badge-secondary';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($estado) . '</span>';
}
?>

<div class="modal fade" id="detalleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Detalle del equipo</h5>
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
        <h5 class="modal-title">Anular equipo</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <p><strong>¿Anular este equipo de trabajo?</strong></p>
        <p class="mb-0" id="anularInfo"></p>
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
    <h1 class="h3 mb-0 text-gray-800">Equipos de Trabajo</h1>
    <a href="?form_equipo=add&form=add" class="btn btn-primary btn-sm shadow-sm">
      <i class="fas fa-users fa-sm text-white-50"></i> Nuevo equipo
    </a>
  </div>

  <div class="alert alert-info small">
    Asigne trabajadores a una <strong>orden de producción</strong> y etapa (Preparación, Cocción, Armado, Empaque).
    Cada miembro queda registrado con su rol/tarea en esa etapa.
  </div>

  <?php
  if (!empty($_GET['alert'])) {
      $msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
      $map = [
          1 => ['msg' => 'Equipo registrado.', 'class' => 'alert-success'],
          2 => ['msg' => 'Equipo actualizado.', 'class' => 'alert-success'],
          3 => ['msg' => 'Equipo anulado.', 'class' => 'alert-success'],
          4 => ['msg' => $msg ?: 'No se pudo completar la operación.', 'class' => 'alert-danger'],
          5 => ['msg' => $msg ?: 'No se puede editar.', 'class' => 'alert-danger'],
      ];
      if (isset($map[(int)$_GET['alert']])) {
          $d = $map[(int)$_GET['alert']];
          echo "<div class='alert {$d['class']} alert-dismissible fade show'>{$d['msg']}
                <button type='button' class='close' data-dismiss='alert'><span>&times;</span></button></div>";
      }
  }

  $rows = $pdo->query("
      SELECT e.equipo_id, e.equipo_descri, e.equipo_estado, e.equipo_fecha, e.orden_id,
             op.orden_prod_estado,
             (SELECT COUNT(*) FROM equipo_detalle ed WHERE ed.equipo_id = e.equipo_id)::int AS num_trab,
             (SELECT string_agg(DISTINCT ed.tarea_rol, ', ' ORDER BY ed.tarea_rol)
              FROM equipo_detalle ed WHERE ed.equipo_id = e.equipo_id) AS etapas
      FROM equipos_produccion e
      JOIN orden_produccion op ON op.orden_id = e.orden_id
      ORDER BY e.equipo_id DESC
  ")->fetchAll();
  ?>

  <div class="card shadow mb-4">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered table-hover" id="tablaEquipos" width="100%">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Descripción</th>
              <th>OP</th>
              <th>Etapa(s)</th>
              <th class="text-center">Trab.</th>
              <th>Fecha</th>
              <th>Estado</th>
              <th style="width:120px">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r):
                $est = strtoupper(trim((string)$r['equipo_estado']));
            ?>
            <tr>
              <td><?= (int)$r['equipo_id'] ?></td>
              <td><?= htmlspecialchars($r['equipo_descri']) ?></td>
              <td>#<?= (int)$r['orden_id'] ?> <small class="text-muted">(<?= htmlspecialchars($r['orden_prod_estado']) ?>)</small></td>
              <td><small><?= htmlspecialchars($r['etapas'] ?? '—') ?></small></td>
              <td class="text-center"><?= (int)$r['num_trab'] ?></td>
              <td><?= htmlspecialchars($r['equipo_fecha'] ?? '') ?></td>
              <td><?= badgeEstadoEquipo($est) ?></td>
              <td>
                <button type="button" class="btn btn-info btn-sm btn-detalle" data-id="<?= (int)$r['equipo_id'] ?>"><i class="fas fa-eye"></i></button>
                <?php if (in_array($est, ['PENDIENTE', 'ACTIVO'], true)): ?>
                <a href="?form_equipo=edit&form=edit&equipo_id=<?= (int)$r['equipo_id'] ?>" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i></a>
                <button type="button" class="btn btn-danger btn-sm btn-anular" data-id="<?= (int)$r['equipo_id'] ?>" data-nom="<?= htmlspecialchars($r['equipo_descri']) ?>"><i class="fas fa-times"></i></button>
                <?php endif; ?>
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
      $('#tablaEquipos').DataTable({ language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' }, order: [[0, 'desc']] });
    }

    let anularId = 0;
    document.querySelectorAll('.btn-detalle').forEach(btn => {
      btn.addEventListener('click', async () => {
        const body = document.getElementById('detalleBody');
        body.innerHTML = 'Cargando...';
        $('#detalleModal').modal('show');
        try {
          const r = await fetch('get_detalle.php?equipo_id=' + btn.dataset.id);
          const data = await r.json();
          if (!data.success) { body.innerHTML = '<div class="alert alert-danger">' + (data.error || '') + '</div>'; return; }
          const e = data.equipo;
          let html = '<p><strong>' + e.equipo_descri + '</strong> — OP #' + e.orden_id + ' (' + e.equipo_estado + ')</p>';
          html += '<table class="table table-sm table-bordered"><thead><tr><th>Trabajador</th><th>Rol / Etapa</th><th>Turno</th></tr></thead><tbody>';
          (data.miembros || []).forEach(m => {
            html += '<tr><td>' + m.nombre + '</td><td>' + m.tarea_rol + '</td><td>' + (m.trabajador_turno || '—') + '</td></tr>';
          });
          html += '</tbody></table>';
          body.innerHTML = html;
        } catch (err) { body.innerHTML = '<div class="alert alert-danger">Error de conexión</div>'; }
      });
    });

    document.querySelectorAll('.btn-anular').forEach(btn => {
      btn.addEventListener('click', () => {
        anularId = parseInt(btn.dataset.id, 10);
        document.getElementById('anularInfo').textContent = btn.dataset.nom;
        $('#anularModal').modal('show');
      });
    });

    document.getElementById('confirmarAnularBtn')?.addEventListener('click', async () => {
      if (!anularId) return;
      const fd = new FormData();
      fd.append('equipo_id', anularId);
      try {
        const r = await fetch('anular_equipo.php', { method: 'POST', body: fd });
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
