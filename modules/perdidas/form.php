<?php
require_once realpath(__DIR__ . '/../../config/database.php');

$dsn = "pgsql:host=$host;port=$port;dbname=$database;";
$pdoForm = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

date_default_timezone_set('America/Asuncion');
$fechaHoy = date('Y-m-d');

$userSesion = $_SESSION['username'] ?? '';
$qUser = $pdoForm->prepare("
    SELECT u.username, s.descripcion_sucursal FROM usuarios u
    JOIN sucursales s ON s.id_sucursal = u.id_sucursal
    WHERE u.username = :user LIMIT 1
");
$qUser->execute([':user' => $userSesion]);
$usr = $qUser->fetch();

$calidades = $pdoForm->query("
    SELECT cc.calidad_id, cc.terminado_id, pt.orden_id, cc.calidad_fecha
    FROM control_calidad_produccion cc
    JOIN producto_terminado pt ON pt.terminado_id = cc.terminado_id
    WHERE UPPER(TRIM(cc.calidad_estado)) = 'NO CONFORME'
      AND NOT EXISTS (
          SELECT 1 FROM perdidas pe
          WHERE pe.calidad_id = cc.calidad_id
            AND UPPER(TRIM(pe.perdida_estado)) <> 'ANULADO'
      )
    ORDER BY cc.calidad_id DESC
")->fetchAll();

$tiposPerdida = $pdoForm->query("
    SELECT tipo_perdida_id, tipo_perdida_descri FROM tipo_perdida ORDER BY tipo_perdida_id
")->fetchAll();

$calidadPreseleccion = isset($_GET['calidad_id']) ? (int)$_GET['calidad_id'] : 0;

$modoEdit = isset($_GET['form_perdida'], $_GET['form'], $_GET['perdidas_id'])
    && $_GET['form_perdida'] === 'edit' && $_GET['form'] === 'edit';
$perdidaEdit = $modoEdit ? (int)$_GET['perdidas_id'] : 0;
$perData = null;
if ($modoEdit && $perdidaEdit > 0) {
    $st = $pdoForm->prepare("
        SELECT perdidas_id, perdida_fecha, perdida_estado, tipo_perdida_id, calidad_id
        FROM perdidas WHERE perdidas_id = :id
    ");
    $st->execute([':id' => $perdidaEdit]);
    $perData = $st->fetch();
    if (!$perData || strtoupper(trim((string)$perData['perdida_estado'])) !== 'REGISTRADO') {
        die('Registro no disponible para edición.');
    }
}

if (!$modoEdit && isset($_GET['form_perdida'], $_GET['form']) && $_GET['form_perdida'] === 'add' && $_GET['form'] === 'add'):
?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-trash-alt"></i> Registrar pérdida</h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="view.php">Pérdidas</a></li>
    <li class="breadcrumb-item active">Nuevo</li>
  </ol>

  <div class="alert alert-warning small">
    Registre la baja de stock por un control de calidad <strong>NO CONFORME</strong>.
    Las devoluciones de cliente (si aplica) pueden usar otro tipo de pérdida.
  </div>

  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=insert" method="POST" id="form-perdida-add">
      <div class="row mb-3">
        <div class="col-md-3">
          <label>Fecha</label>
          <input type="date" class="form-control" name="perdida_fecha" value="<?= htmlspecialchars($fechaHoy) ?>" required>
        </div>
        <div class="col-md-4">
          <label>Usuario</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['username'] ?? '') ?>" readonly>
        </div>
        <div class="col-md-5">
          <label>Tipo de pérdida</label>
          <select class="form-control" name="tipo_perdida_id" required>
            <?php foreach ($tiposPerdida as $t): ?>
            <option value="<?= (int)$t['tipo_perdida_id'] ?>"
              <?= (int)$t['tipo_perdida_id'] === 1 ? 'selected' : '' ?>>
              <?= htmlspecialchars($t['tipo_perdida_descri']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-8">
          <label for="calidad_id">Control de calidad (NO CONFORME) <span class="text-danger">*</span></label>
          <select class="form-control" name="calidad_id" id="calidad_id" required>
            <option value="">Seleccione inspección</option>
            <?php foreach ($calidades as $c): ?>
            <option value="<?= (int)$c['calidad_id'] ?>"
              <?= $calidadPreseleccion === (int)$c['calidad_id'] ? 'selected' : '' ?>>
              CC #<?= (int)$c['calidad_id'] ?> — Lote PT #<?= (int)$c['terminado_id'] ?> / OP #<?= (int)$c['orden_id'] ?>
            </option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($calidades)): ?>
          <small class="text-danger">No hay controles NO CONFORME pendientes de registrar pérdida.</small>
          <?php endif; ?>
        </div>
      </div>

      <div id="panel-items" class="d-none">
        <p class="small text-muted" id="hint-calidad"></p>
        <div class="row align-items-end mb-2 bg-light border rounded p-3">
          <div class="col-md-4">
            <label for="producto_sel">Producto</label>
            <select class="form-control" id="producto_sel" disabled><option value="">—</option></select>
          </div>
          <div class="col-md-2">
            <label for="cantidad_per">Cantidad</label>
            <input type="number" class="form-control" id="cantidad_per" min="1" step="1" disabled>
          </div>
          <div class="col-md-4">
            <label for="motivo_per">Motivo (máx. 30)</label>
            <input type="text" class="form-control" id="motivo_per" maxlength="30" disabled
              placeholder="Ej. Presentación deficiente">
          </div>
          <div class="col-md-2">
            <button type="button" class="btn btn-primary w-100" id="btn-agregar" disabled>Agregar</button>
          </div>
        </div>
        <table class="table table-bordered table-sm" id="tabla-items">
          <thead class="table-dark"><tr><th>Producto</th><th>Cant.</th><th>Motivo</th><th></th></tr></thead>
          <tbody></tbody>
        </table>
      </div>

      <input type="hidden" name="items" id="items" value="[]">

      <div class="d-flex justify-content-end mt-3">
        <button type="submit" name="Guardar" class="btn btn-success" id="btn-guardar" disabled>Guardar pérdida</button>
        <a href="view.php" class="btn btn-warning ml-2">Cancelar</a>
      </div>
    </form>
  </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const selCal = document.getElementById('calidad_id');
  const panel = document.getElementById('panel-items');
  const hint = document.getElementById('hint-calidad');
  const selProd = document.getElementById('producto_sel');
  const inpCant = document.getElementById('cantidad_per');
  const inpMot = document.getElementById('motivo_per');
  const btnAdd = document.getElementById('btn-agregar');
  const btnGuardar = document.getElementById('btn-guardar');
  const tbody = document.querySelector('#tabla-items tbody');
  const hid = document.getElementById('items');

  let productos = [];
  let items = [];

  const sync = () => {
    hid.value = JSON.stringify(items.map(it => ({
      producto_id: it.producto_id,
      cantidad: it.cantidad,
      motivo: it.motivo,
      deposito_id: it.deposito_id
    })));
    btnGuardar.disabled = items.length === 0;
  };

  const renderTabla = () => {
    tbody.innerHTML = '';
    items.forEach((it, idx) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${it.nombre}</td><td class="text-right">${it.cantidad}</td><td>${it.motivo}</td>
        <td><button type="button" class="btn btn-danger btn-sm" data-i="${idx}">Quitar</button></td>`;
      tbody.appendChild(tr);
    });
    tbody.querySelectorAll('button[data-i]').forEach(b => {
      b.addEventListener('click', () => {
        items.splice(parseInt(b.dataset.i, 10), 1);
        renderTabla();
        actualizarProductos();
        sync();
      });
    });
    sync();
  };

  const actualizarProductos = () => {
    selProd.innerHTML = '<option value="">Seleccione</option>';
    productos.forEach(p => {
      if (items.some(x => x.producto_id === p.producto_id)) return;
      const opt = document.createElement('option');
      opt.value = p.producto_id;
      opt.textContent = `${p.producto_descri} (máx. ${p.cantidad_disponible})`;
      opt.dataset.max = p.cantidad_disponible;
      opt.dataset.nombre = p.producto_descri;
      opt.dataset.dep = p.deposito_id || '';
      selProd.appendChild(opt);
    });
    const hay = selProd.options.length > 1;
    selProd.disabled = !hay;
    inpCant.disabled = !hay;
    inpMot.disabled = !hay;
    btnAdd.disabled = !hay;
  };

  const cargarCalidad = async (id) => {
    items = [];
    productos = [];
    renderTabla();
    if (!id) { panel.classList.add('d-none'); return; }
    try {
      const r = await fetch('get_calidad_info.php?calidad_id=' + id);
      const data = await r.json();
      if (!data.success) { alert(data.error); panel.classList.add('d-none'); return; }
      productos = (data.productos || []).map(p => ({
        producto_id: parseInt(p.producto_id, 10),
        producto_descri: p.producto_descri,
        cantidad_disponible: parseInt(p.cantidad_disponible, 10),
        deposito_id: parseInt(p.deposito_id, 10) || 0,
        deposito_descri: p.deposito_descri || ''
      }));
      panel.classList.remove('d-none');
      hint.textContent = `Lote PT #${data.calidad.terminado_id} — OP #${data.calidad.orden_id}. Se descontará stock del depósito del lote.`;
      actualizarProductos();
    } catch (e) { alert('Error de conexión'); }
  };

  selCal.addEventListener('change', () => cargarCalidad(selCal.value));
  if (selCal.value) cargarCalidad(selCal.value);

  selProd.addEventListener('change', () => {
    const opt = selProd.selectedOptions[0];
    if (opt && opt.value) {
      inpCant.max = parseInt(opt.dataset.max, 10) || 1;
    }
  });

  btnAdd.addEventListener('click', () => {
    const opt = selProd.selectedOptions[0];
    if (!opt || !opt.value) return;
    const cant = parseInt(inpCant.value, 10) || 0;
    const max = parseInt(opt.dataset.max, 10) || 0;
    const motivo = inpMot.value.trim();
    if (cant <= 0 || cant > max) { alert('Cantidad inválida. Máx: ' + max); return; }
    if (!motivo) { alert('Indique el motivo.'); return; }
    items.push({
      producto_id: parseInt(opt.value, 10),
      nombre: opt.dataset.nombre,
      cantidad: cant,
      motivo: motivo,
      deposito_id: parseInt(opt.dataset.dep, 10) || 0
    });
    renderTabla();
    actualizarProductos();
    selProd.value = '';
    inpCant.value = '';
    inpMot.value = '';
  });

  document.getElementById('form-perdida-add').addEventListener('submit', e => {
    sync();
    if (JSON.parse(hid.value || '[]').length === 0) {
      e.preventDefault();
      alert('Agregue al menos un producto.');
    }
  });
});
</script>

<?php elseif ($modoEdit && $perData): ?>
<div class="container-fluid">
  <h1 class="h3 mb-4">Editar pérdida #<?= (int)$perData['perdidas_id'] ?></h1>
  <div class="card shadow"><div class="card-body">
    <p class="text-muted small">No se modifican cantidades ni productos. Para corregir, anule y registre de nuevo.</p>
    <form action="proses.php?act=update" method="POST">
      <input type="hidden" name="perdidas_id" value="<?= (int)$perData['perdidas_id'] ?>">
      <div class="row mb-3">
        <div class="col-md-4">
          <label>Control calidad</label>
          <input type="text" class="form-control" value="#<?= (int)$perData['calidad_id'] ?>" readonly>
        </div>
        <div class="col-md-4">
          <label>Fecha</label>
          <input type="date" class="form-control" name="perdida_fecha"
            value="<?= htmlspecialchars(substr((string)$perData['perdida_fecha'], 0, 10)) ?>" required>
        </div>
        <div class="col-md-4">
          <label>Tipo</label>
          <select class="form-control" name="tipo_perdida_id" required>
            <?php foreach ($tiposPerdida as $t): ?>
            <option value="<?= (int)$t['tipo_perdida_id'] ?>"
              <?= (int)$perData['tipo_perdida_id'] === (int)$t['tipo_perdida_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($t['tipo_perdida_descri']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <button type="submit" name="Guardar" class="btn btn-success">Guardar</button>
      <a href="view.php" class="btn btn-warning ml-2">Cancelar</a>
    </form>
  </div></div>
</div>
<?php endif; ?>
