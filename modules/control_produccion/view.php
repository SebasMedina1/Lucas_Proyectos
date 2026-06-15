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
require_once __DIR__ . '/etapas_helper.php';
check_permission('CONTROL_PRODUCCION');

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

if (
    isset($_GET['form_control'], $_GET['form'], $_GET['control_id']) &&
    $_GET['form_control'] === 'edit' && $_GET['form'] === 'edit'
) {
    $stmt = $pdo->prepare('SELECT control_estado FROM control_produccion WHERE control_id = :id LIMIT 1');
    $stmt->execute([':id' => (int)$_GET['control_id']]);
    $estado = $stmt->fetchColumn();
    if ($estado === false || strtoupper(trim((string)$estado)) !== 'REGISTRADO') {
        header('Location: view.php?alert=5');
        exit;
    }
}

$mostrarListado = !(
    (isset($_GET['form_control'], $_GET['form']) && $_GET['form_control'] === 'add' && $_GET['form'] === 'add') ||
    (isset($_GET['form_control'], $_GET['form'], $_GET['control_id']) && $_GET['form_control'] === 'edit' && $_GET['form'] === 'edit')
);

$lineasProduccion = [];
if ($mostrarListado) {
    try {
        $stLineas = $pdo->query("
            SELECT DISTINCT d.orden_id, d.producto_id, p.producto_descri, d.orden_prod_cantidad,
                   op.orden_prod_estado
            FROM orden_detalle_produccion d
            JOIN productos p ON p.producto_id = d.producto_id
            JOIN orden_produccion op ON op.orden_id = d.orden_id
            WHERE op.orden_prod_estado IN ('PENDIENTE', 'EN_PROCESO', 'TERMINADA')
               OR EXISTS (
                   SELECT 1 FROM control_produccion c
                   WHERE c.orden_id = d.orden_id AND c.producto_id = d.producto_id
               )
            ORDER BY d.orden_id DESC, p.producto_descri ASC
        ");
        foreach ($stLineas->fetchAll() as $row) {
            $ordenId = (int)$row['orden_id'];
            $productoId = (int)$row['producto_id'];
            $info = resolverSiguienteEtapa($pdo, $ordenId, $productoId);
            if (!$info['success']) {
                continue;
            }
            $estado = etiquetaEstadoLinea($info);
            $lineasProduccion[] = [
                'orden_id' => $ordenId,
                'producto_id' => $productoId,
                'producto_descri' => $row['producto_descri'],
                'orden_prod_cantidad' => (int)$row['orden_prod_cantidad'],
                'orden_prod_estado' => $row['orden_prod_estado'],
                'completado' => $info['completado'],
                'resumen' => $info['resumen'],
                'siguiente_etapa' => $info['siguiente_etapa'],
                'cantidad_max' => (int)$info['cantidad_maxima'],
                'estado_texto' => $estado['texto'],
                'estado_class' => $estado['class'],
                'progreso_html' => htmlProgresoEtapas($info['resumen']),
            ];
        }
    } catch (PDOException $e) {
        $lineasProduccion = [];
    }
}

$BASE_PATH = '../../';
$page_title = 'Control de Producción';
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
        <p><strong>¿Anular este control de producción?</strong></p>
        <p class="mb-0" id="anularControlInfo"></p>
        <p class="small text-muted mt-2">Se revertirá el stock de MP y el pendiente de la orden.</p>
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
    <h1 class="h3 mb-0 text-gray-800">Control de Producción</h1>
    <a href="?form_control=add&form=add" class="btn btn-primary btn-sm shadow-sm">
      <i class="fas fa-plus fa-sm text-white-50"></i> Nuevo control
    </a>
  </div>

  <?php
  if (!empty($_GET['alert'])) {
      $msgCustom = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
      $alertMap = [
          1 => ['msg' => 'Etapa registrada correctamente.', 'class' => 'alert-success'],
          2 => ['msg' => 'Control actualizado correctamente.', 'class' => 'alert-success'],
          3 => ['msg' => 'Control anulado correctamente.', 'class' => 'alert-success'],
          4 => ['msg' => $msgCustom ?: 'No se pudo realizar la operación.', 'class' => 'alert-danger'],
          5 => ['msg' => $msgCustom ?: 'Solo se pueden editar controles en estado REGISTRADO.', 'class' => 'alert-danger'],
      ];
      if (isset($alertMap[(int)$_GET['alert']])) {
          $d = $alertMap[(int)$_GET['alert']];
          echo "<div id='alert-message' class='alert {$d['class']} alert-dismissible fade show'>{$d['msg']}
                <button type='button' class='close' data-dismiss='alert'><span>&times;</span></button></div>";
      }
  }
  ?>

  <div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
      <h6 class="m-0 font-weight-bold text-primary">Producción en curso (una línea por OP + producto)</h6>
    </div>
    <div class="card-body">
      <p class="small text-muted mb-3">
        Cada orden y producto aparece <strong>una sola vez</strong> con su estado actual en la ruta.
        Los registros por etapa (consumos de MP) se consultan en <strong>Historial</strong>.
      </p>
      <div class="table-responsive">
        <table class="table table-bordered" id="dataTable" width="100%">
          <thead>
            <tr>
              <th>OP</th>
              <th>Producto</th>
              <th>Cant. OP</th>
              <th>Progreso por etapa</th>
              <th>Estado actual</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php
            if (empty($lineasProduccion)) {
                echo '<tr><td colspan="6" class="text-center text-muted">No hay producción en curso.</td></tr>';
            }
            foreach ($lineasProduccion as $ln):
            ?>
            <tr>
              <td>#<?= (int)$ln['orden_id'] ?></td>
              <td><?= htmlspecialchars($ln['producto_descri']) ?></td>
              <td class="text-right"><?= (int)$ln['orden_prod_cantidad'] ?></td>
              <td><?= $ln['progreso_html'] ?></td>
              <td><span class="badge <?= htmlspecialchars($ln['estado_class']) ?>"><?= htmlspecialchars($ln['estado_texto']) ?></span></td>
              <td class="text-nowrap">
                <?php if (!$ln['completado'] && !empty($ln['siguiente_etapa'])): ?>
                <a href="?form_control=add&amp;form=add&amp;orden_id=<?= (int)$ln['orden_id'] ?>&amp;producto_id=<?= (int)$ln['producto_id'] ?>"
                   class="btn btn-primary btn-sm mb-1" title="Registrar siguiente etapa">
                  <i class="fas fa-arrow-right"></i>
                  <?= htmlspecialchars($ln['siguiente_etapa']['etapa_nombre']) ?>
                </a>
                <?php endif; ?>
                <button type="button" class="btn btn-info btn-sm mb-1 btn-historial"
                  data-orden="<?= (int)$ln['orden_id'] ?>"
                  data-producto="<?= (int)$ln['producto_id'] ?>"
                  data-nombre="<?= htmlspecialchars($ln['producto_descri'], ENT_QUOTES) ?>">
                  <i class="fas fa-history"></i> Historial
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php else: ?>
  <?php include 'form.php'; ?>
<?php endif; ?>

<div class="modal fade" id="historialModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Historial de etapas</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body" id="historialBody"></div>
    </div>
  </div>
</div>

<div class="modal fade" id="detalleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Detalle del control</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
      <div class="modal-body" id="detalleControlBody"></div>
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
  document.querySelectorAll('.btn-historial').forEach(btn => {
    btn.addEventListener('click', () => {
      const body = document.getElementById('historialBody');
      const oid = btn.dataset.orden;
      const pid = btn.dataset.producto;
      const nombre = btn.dataset.nombre;
      body.innerHTML = '<p class=\"text-center\"><i class=\"fas fa-spinner fa-spin\"></i></p>';
      fetch('get_historial.php?orden_id=' + oid + '&producto_id=' + pid)
        .then(r => r.json())
        .then(data => {
          if (!data.success) {
            body.innerHTML = '<p class=\"text-danger\">' + (data.error || '') + '</p>';
          } else {
            let html = '<p><strong>OP #' + oid + '</strong> — ' + nombre + '</p>';
            html += '<p class=\"small text-muted\">Cada fila es un movimiento de etapa (consumo de MP).</p>';
            html += '<table class=\"table table-sm table-bordered\"><thead><tr><th>N°</th><th>Fecha</th><th>Etapa</th><th>Cant.</th><th>Inspector</th><th>Estado</th><th></th></tr></thead><tbody>';
            (data.movimientos || []).forEach(m => {
              const cls = m.control_estado === 'REGISTRADO' ? 'success' : 'secondary';
              html += '<tr><td>' + m.control_id + '</td><td>' + m.control_fecha + '</td><td>' + m.etapa_nombre + '</td>';
              html += '<td class=\"text-right\">' + m.control_cantidad + '</td><td>' + m.inspector + '</td>';
              html += '<td><span class=\"badge badge-' + cls + '\">' + m.control_estado + '</span></td><td>';
              html += '<button type=\"button\" class=\"btn btn-outline-info btn-sm btn-detalle\" data-id=\"' + m.control_id + '\">Ver</button>';
              if (m.control_estado === 'REGISTRADO') {
                html += ' <a href=\"?form_control=edit&amp;form=edit&amp;control_id=' + m.control_id + '\" class=\"btn btn-outline-warning btn-sm\">Editar</a>';
                html += ' <button type=\"button\" class=\"btn btn-outline-danger btn-sm btn-anular\" data-id=\"' + m.control_id + '\">Anular</button>';
              }
              html += '</td></tr>';
            });
            html += '</tbody></table>';
            if (!data.movimientos || !data.movimientos.length) {
              html += '<p class=\"text-muted\">Sin movimientos registrados.</p>';
            }
            body.innerHTML = html;
            body.querySelectorAll('.btn-detalle').forEach(b => {
              b.addEventListener('click', () => abrirDetalleControl(b.dataset.id));
            });
            body.querySelectorAll('.btn-anular').forEach(b => {
              b.addEventListener('click', () => {
                idAnular = b.dataset.id;
                document.getElementById('anularControlInfo').textContent = 'Control N° ' + idAnular;
                jQuery('#anularModal').modal('show');
              });
            });
          }
          jQuery('#historialModal').modal('show');
        });
    });
  });
  let idAnular = null;
  function abrirDetalleControl(controlId) {
    const body = document.getElementById('detalleControlBody');
    body.innerHTML = '<p class=\"text-center\"><i class=\"fas fa-spinner fa-spin\"></i></p>';
    fetch('get_detalle.php?control_id=' + controlId)
      .then(r => r.json())
      .then(data => {
        if (!data.success) { body.innerHTML = '<p class=\"text-danger\">' + (data.error || '') + '</p>'; }
        else {
          const c = data.cabecera;
          let html = '<p><strong>OP:</strong> ' + c.orden_id + ' | <strong>Estado:</strong> ' + c.control_estado + '</p>';
          html += '<p><strong>Producto:</strong> ' + c.producto_descri + ' | <strong>Etapa:</strong> ' + (c.etapa_nombre || '-') + '</p>';
          const fmtCant = n => parseInt(String(n), 10) || 0;
          html += '<p><strong>Cantidad:</strong> ' + fmtCant(c.cantidad_procesada) + ' | <strong>Inspector:</strong> ' + c.inspector + '</p>';
          if (c.control_observacion) html += '<p><strong>Obs.:</strong> ' + c.control_observacion + '</p>';
          html += '<table class=\"table table-sm table-bordered\"><thead><tr><th>Materia prima</th><th>Cantidad</th></tr></thead><tbody>';
          (data.consumos || []).forEach(x => {
            html += '<tr><td>' + x.materia_prima_descripcion + '</td><td class=\"text-right\">' + fmtCant(x.cantidad_consumida) + '</td></tr>';
          });
          html += '</tbody></table>';
          body.innerHTML = html;
        }
        jQuery('#detalleModal').modal('show');
      });
  }
  const btnA = document.getElementById('confirmarAnularBtn');
  if (btnA) {
    btnA.addEventListener('click', function() {
      if (!idAnular) return;
      this.disabled = true;
      fetch('anular_control.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ control_id: idAnular })
      }).then(r => r.json()).then(data => {
        if (data.success) { jQuery('#anularModal').modal('hide'); location.href = 'view.php?alert=3'; }
        else { alert(data.message || 'Error'); this.disabled = false; }
      });
    });
  }
});
";
include '../../footer.php';
