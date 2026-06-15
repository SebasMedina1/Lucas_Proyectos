<?php
require_once realpath(__DIR__ . '/../../config/database.php');
require_once __DIR__ . '/costos_helper.php';

$dsn = "pgsql:host=$host;port=$port;dbname=$database;";
$pdoForm = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

date_default_timezone_set('America/Asuncion');
$fechaHoy = date('Y-m-d');

$userSesion = $_SESSION['username'] ?? '';
$qUser = $pdoForm->prepare("
    SELECT u.username, s.descripcion_sucursal
    FROM usuarios u
    JOIN sucursales s ON s.id_sucursal = u.id_sucursal
    WHERE u.username = :user LIMIT 1
");
$qUser->execute([':user' => $userSesion]);
$usr = $qUser->fetch();
if (!$usr) {
    die('Usuario no encontrado.');
}

$trabajadores = $pdoForm->query("
    SELECT t.trabajadores_id,
           TRIM(per.personal_nombre || ' ' || per.personal_apellido) AS nombre,
           COALESCE(t.trabajador_costo_hora, 0)::int AS costo_hora,
           t.trabajador_rol
    FROM trabajadores t
    JOIN personal per ON per.id_personal = t.id_personal
    WHERE UPPER(TRIM(t.trabajador_estado)) = 'ACTIVO'
    ORDER BY per.personal_nombre
")->fetchAll();

$ordenes = $pdoForm->query("
    SELECT DISTINCT op.orden_id, op.orden_prod_estado, op.id_pedido_produccion
    FROM orden_produccion op
    WHERE EXISTS (
        SELECT 1 FROM control_produccion c
        WHERE c.orden_id = op.orden_id AND UPPER(TRIM(c.control_estado)) = 'REGISTRADO'
    )
    AND NOT EXISTS (
        SELECT 1 FROM costo_produccion cp
        WHERE cp.orden_id = op.orden_id
          AND UPPER(TRIM(cp.costo_estado)) IN ('PENDIENTE', 'CERRADO')
    )
    ORDER BY op.orden_id DESC
")->fetchAll();

$modoEdit = isset($_GET['form_costo'], $_GET['form'], $_GET['costo_id'])
    && $_GET['form_costo'] === 'edit' && $_GET['form'] === 'edit';
$costoEditId = $modoEdit ? (int)$_GET['costo_id'] : 0;
$costoData = null;
$lineasInit = ['mp' => [], 'mo' => [], 'cif' => []];

if ($modoEdit && $costoEditId > 0) {
    $st = $pdoForm->prepare("
        SELECT costo_id, costo_fecha, costo_estado, orden_id, costo_total
        FROM costo_produccion WHERE costo_id = :id
    ");
    $st->execute([':id' => $costoEditId]);
    $costoData = $st->fetch();
    if (!$costoData || strtoupper(trim((string)$costoData['costo_estado'])) !== 'PENDIENTE') {
        die('Solo se pueden editar costeos PENDIENTE.');
    }
    $lineasInit = detalleCostoParaJson($pdoForm, $costoEditId);
}

$ordenPre = isset($_GET['orden_id']) ? (int)$_GET['orden_id'] : 0;
$jsLineas = json_encode($lineasInit);
$jsTrab = json_encode(array_map(static function ($t) {
    return [
        'id' => (int)$t['trabajadores_id'],
        'nombre' => $t['nombre'],
        'costo_hora' => (int)$t['costo_hora'],
        'rol' => $t['trabajador_rol'],
    ];
}, $trabajadores));

if (!$modoEdit && isset($_GET['form_costo'], $_GET['form']) && $_GET['form_costo'] === 'add' && $_GET['form'] === 'add'):
?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-calculator"></i> Costear orden de producción</h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="view.php">Costos de producción</a></li>
    <li class="breadcrumb-item active">Nuevo</li>
  </ol>

  <div class="alert alert-info small">
    <strong>MP:</strong> se cargan desde consumos reales de la OP (precio unitario = última compra).
    Agregue líneas de <strong>mano de obra (MO)</strong> y <strong>CIF</strong> si corresponde.
  </div>

  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=insert" method="POST" id="form-costo">
      <div class="row mb-3">
        <div class="col-md-2"><label>Fecha</label>
          <input type="date" class="form-control" name="costo_fecha" value="<?= htmlspecialchars($fechaHoy) ?>" required></div>
        <div class="col-md-4"><label>Usuario</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['username']) ?>" readonly></div>
      </div>
      <div class="row mb-3">
        <div class="col-md-8">
          <label for="orden_id">Orden de producción <span class="text-danger">*</span></label>
          <select class="form-control" name="orden_id" id="orden_id" required>
            <option value="">Seleccione OP</option>
            <?php foreach ($ordenes as $o): ?>
            <option value="<?= (int)$o['orden_id'] ?>" <?= $ordenPre === (int)$o['orden_id'] ? ' selected' : '' ?>>
              OP #<?= (int)$o['orden_id'] ?> — Pedido #<?= (int)$o['id_pedido_produccion'] ?> (<?= htmlspecialchars($o['orden_prod_estado']) ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div id="panel-costos" class="d-none">
        <h6 class="font-weight-bold text-primary">Materia prima (MP)</h6>
        <div class="table-responsive mb-3">
          <table class="table table-sm table-bordered" id="tabla-mp">
            <thead class="thead-light">
              <tr><th>Materia prima</th><th class="text-right">Cant.</th><th class="text-right">Precio unit.</th><th class="text-right">Subtotal</th></tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

        <h6 class="font-weight-bold text-primary">Mano de obra (MO)</h6>
        <div class="row align-items-end mb-2">
          <div class="col-md-5"><label>Trabajador</label>
            <select class="form-control" id="sel-trab"><option value="">Seleccione</option></select></div>
          <div class="col-md-2"><label>Horas</label>
            <input type="number" class="form-control" id="mo-horas" min="1" step="1" value="1"></div>
          <div class="col-md-2"><label>Gs/hora</label>
            <input type="number" class="form-control" id="mo-precio" min="0" step="1"></div>
          <div class="col-md-3"><button type="button" class="btn btn-outline-primary btn-block" id="btn-add-mo">Agregar MO</button></div>
        </div>
        <div class="table-responsive mb-3">
          <table class="table table-sm table-bordered" id="tabla-mo">
            <thead class="thead-light"><tr><th>Trabajador</th><th class="text-right">Horas</th><th class="text-right">Gs/h</th><th class="text-right">Subtotal</th><th></th></tr></thead>
            <tbody></tbody>
          </table>
        </div>

        <h6 class="font-weight-bold text-primary">Costos indirectos (CIF)</h6>
        <div class="row align-items-end mb-2">
          <div class="col-md-5"><label>Concepto</label>
            <input type="text" class="form-control" id="cif-concepto" maxlength="150" placeholder="Ej. Energía, empaque extra"></div>
          <div class="col-md-2"><label>Monto (Gs)</label>
            <input type="number" class="form-control" id="cif-monto" min="1" step="1"></div>
          <div class="col-md-3"><button type="button" class="btn btn-outline-primary btn-block" id="btn-add-cif">Agregar CIF</button></div>
        </div>
        <div class="table-responsive mb-3">
          <table class="table table-sm table-bordered" id="tabla-cif">
            <thead class="thead-light"><tr><th>Concepto</th><th class="text-right">Monto</th><th></th></tr></thead>
            <tbody></tbody>
          </table>
        </div>

        <div class="alert alert-secondary text-right mb-3">
          <strong>Total costo: Gs. <span id="lbl-total">0</span></strong>
        </div>
      </div>

      <input type="hidden" name="lineas" id="lineas" value="{}">

      <div class="d-flex justify-content-end">
        <button type="submit" name="Guardar" class="btn btn-success mr-2" id="btn-guardar" disabled>Guardar costeo</button>
        <a href="view.php" class="btn btn-warning">Cancelar</a>
      </div>
    </form>
  </div></div>
</div>

<?php elseif ($modoEdit && $costoData): ?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-edit"></i> Editar costeo #<?= (int)$costoData['costo_id'] ?></h1>
  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=update" method="POST" id="form-costo">
      <input type="hidden" name="costo_id" value="<?= (int)$costoData['costo_id'] ?>">
      <div class="row mb-3">
        <div class="col-md-3"><label>OP</label>
          <input type="text" class="form-control" value="#<?= (int)$costoData['orden_id'] ?>" readonly></div>
        <div class="col-md-3"><label>Fecha</label>
          <input type="date" class="form-control" name="costo_fecha"
            value="<?= htmlspecialchars(substr((string)$costoData['costo_fecha'], 0, 10)) ?>" required></div>
      </div>
      <div id="panel-costos">
        <!-- same structure as add but always visible -->
        <h6 class="font-weight-bold text-primary">Materia prima (MP)</h6>
        <div class="table-responsive mb-3"><table class="table table-sm table-bordered" id="tabla-mp">
          <thead class="thead-light"><tr><th>Materia prima</th><th class="text-right">Cant.</th><th class="text-right">Precio unit.</th><th class="text-right">Subtotal</th></tr></thead>
          <tbody></tbody></table></div>
        <h6 class="font-weight-bold text-primary">Mano de obra (MO)</h6>
        <div class="row align-items-end mb-2">
          <div class="col-md-5"><select class="form-control" id="sel-trab"><option value="">Trabajador</option></select></div>
          <div class="col-md-2"><input type="number" class="form-control" id="mo-horas" min="1" value="1" placeholder="Horas"></div>
          <div class="col-md-2"><input type="number" class="form-control" id="mo-precio" min="0" placeholder="Gs/h"></div>
          <div class="col-md-3"><button type="button" class="btn btn-outline-primary btn-block" id="btn-add-mo">Agregar MO</button></div>
        </div>
        <div class="table-responsive mb-3"><table class="table table-sm table-bordered" id="tabla-mo">
          <thead class="thead-light"><tr><th>Trabajador</th><th class="text-right">Horas</th><th class="text-right">Gs/h</th><th class="text-right">Subtotal</th><th></th></tr></thead>
          <tbody></tbody></table></div>
        <h6 class="font-weight-bold text-primary">CIF</h6>
        <div class="row align-items-end mb-2">
          <div class="col-md-5"><input type="text" class="form-control" id="cif-concepto" maxlength="150" placeholder="Concepto"></div>
          <div class="col-md-2"><input type="number" class="form-control" id="cif-monto" min="1" placeholder="Gs"></div>
          <div class="col-md-3"><button type="button" class="btn btn-outline-primary btn-block" id="btn-add-cif">Agregar CIF</button></div>
        </div>
        <div class="table-responsive mb-3"><table class="table table-sm table-bordered" id="tabla-cif">
          <thead class="thead-light"><tr><th>Concepto</th><th class="text-right">Monto</th><th></th></tr></thead>
          <tbody></tbody></table></div>
        <div class="alert alert-secondary text-right"><strong>Total: Gs. <span id="lbl-total">0</span></strong></div>
      </div>
      <input type="hidden" name="lineas" id="lineas" value="{}">
      <div class="d-flex justify-content-end">
        <button type="submit" name="Guardar" class="btn btn-success mr-2">Guardar cambios</button>
        <a href="view.php" class="btn btn-warning">Cancelar</a>
      </div>
    </form>
  </div></div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('form-costo');
  if (!form) return;

  const TRAB = <?= $jsTrab ?>;
  const selOrden = document.getElementById('orden_id');
  const panel = document.getElementById('panel-costos');
  const hidLineas = document.getElementById('lineas');
  const lblTotal = document.getElementById('lbl-total');
  const btnGuardar = document.getElementById('btn-guardar');
  const tbodyMp = document.querySelector('#tabla-mp tbody');
  const tbodyMo = document.querySelector('#tabla-mo tbody');
  const tbodyCif = document.querySelector('#tabla-cif tbody');
  const selTrab = document.getElementById('sel-trab');

  let state = <?= $jsLineas ?>;

  const fmt = n => (parseInt(n, 10) || 0).toLocaleString('es-PY');

  const fillTrabSelect = () => {
    if (!selTrab) return;
    selTrab.innerHTML = '<option value="">Seleccione trabajador</option>';
    TRAB.forEach(t => {
      const opt = document.createElement('option');
      opt.value = t.id;
      opt.textContent = t.nombre + (t.rol ? ' (' + t.rol + ')' : '');
      opt.dataset.costo = t.costo_hora;
      selTrab.appendChild(opt);
    });
    selTrab.addEventListener('change', () => {
      const o = selTrab.selectedOptions[0];
      document.getElementById('mo-precio').value = o && o.dataset.costo ? o.dataset.costo : '';
    });
  };
  fillTrabSelect();

  const recalcTotal = () => {
    let total = 0;
    state.mp.forEach(x => { total += x.cantidad * x.precio; });
    state.mo.forEach(x => { total += x.cantidad * x.precio; });
    state.cif.forEach(x => { total += x.cantidad * x.precio; });
    lblTotal.textContent = fmt(total);
    hidLineas.value = JSON.stringify(state);
    if (btnGuardar) btnGuardar.disabled = (state.mp.length + state.mo.length + state.cif.length) === 0;
  };

  const renderMp = () => {
    tbodyMp.innerHTML = '';
    state.mp.forEach((x, idx) => {
      const tr = document.createElement('tr');
      const sub = x.cantidad * x.precio;
      tr.innerHTML = `
        <td>${x.nombre}</td>
        <td class="text-right">${x.cantidad}</td>
        <td class="text-right"><input type="number" class="form-control form-control-sm text-right inp-mp-precio" data-idx="${idx}" min="0" value="${x.precio}"></td>
        <td class="text-right sub-mp">${fmt(sub)}</td>`;
      tbodyMp.appendChild(tr);
    });
    tbodyMp.querySelectorAll('.inp-mp-precio').forEach(inp => {
      inp.addEventListener('input', () => {
        const i = parseInt(inp.dataset.idx, 10);
        state.mp[i].precio = parseInt(inp.value, 10) || 0;
        inp.closest('tr').querySelector('.sub-mp').textContent = fmt(state.mp[i].cantidad * state.mp[i].precio);
        recalcTotal();
      });
    });
    recalcTotal();
  };

  const renderMo = () => {
    tbodyMo.innerHTML = '';
    state.mo.forEach((x, idx) => {
      const sub = x.cantidad * x.precio;
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${x.nombre}</td>
        <td class="text-right">${x.cantidad}</td>
        <td class="text-right">${fmt(x.precio)}</td>
        <td class="text-right">${fmt(sub)}</td>
        <td><button type="button" class="btn btn-danger btn-sm" data-idx="${idx}">×</button></td>`;
      tbodyMo.appendChild(tr);
    });
    tbodyMo.querySelectorAll('button[data-idx]').forEach(b => {
      b.addEventListener('click', () => {
        state.mo.splice(parseInt(b.dataset.idx, 10), 1);
        renderMo();
      });
    });
    recalcTotal();
  };

  const renderCif = () => {
    tbodyCif.innerHTML = '';
    state.cif.forEach((x, idx) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${x.concepto}</td>
        <td class="text-right">${fmt(x.precio)}</td>
        <td><button type="button" class="btn btn-danger btn-sm" data-idx="${idx}">×</button></td>`;
      tbodyCif.appendChild(tr);
    });
    tbodyCif.querySelectorAll('button[data-idx]').forEach(b => {
      b.addEventListener('click', () => {
        state.cif.splice(parseInt(b.dataset.idx, 10), 1);
        renderCif();
      });
    });
    recalcTotal();
  };

  document.getElementById('btn-add-mo')?.addEventListener('click', () => {
    const opt = selTrab.selectedOptions[0];
    if (!opt || !opt.value) { alert('Seleccione trabajador.'); return; }
    const horas = parseInt(document.getElementById('mo-horas').value, 10) || 0;
    const precio = parseInt(document.getElementById('mo-precio').value, 10) || 0;
    const tid = parseInt(opt.value, 10);
    if (horas <= 0 || precio < 0) { alert('Horas y costo inválidos.'); return; }
    if (state.mo.some(x => x.trabajadores_id === tid)) { alert('Ese trabajador ya está en MO.'); return; }
    state.mo.push({ tipo: 'MO', trabajadores_id: tid, nombre: opt.textContent.split(' (')[0], cantidad: horas, precio });
    renderMo();
  });

  document.getElementById('btn-add-cif')?.addEventListener('click', () => {
    const concepto = document.getElementById('cif-concepto').value.trim();
    const monto = parseInt(document.getElementById('cif-monto').value, 10) || 0;
    if (!concepto || monto <= 0) { alert('Concepto y monto requeridos.'); return; }
    state.cif.push({ tipo: 'CIF', concepto, cantidad: 1, precio: monto });
    document.getElementById('cif-concepto').value = '';
    document.getElementById('cif-monto').value = '';
    renderCif();
  });

  const cargarOrden = async (id) => {
    if (panel && panel.classList.contains('d-none') === false && selOrden) {
      /* edit mode: skip fetch */
    }
    if (!id || !selOrden) {
      if (state.mp.length === 0 && panel) panel.classList.add('d-none');
      return;
    }
    try {
      const r = await fetch('get_orden_info.php?orden_id=' + id);
      const data = await r.json();
      if (!data.success) { alert(data.error || 'Error'); return; }
      state = data.lineas || { mp: [], mo: [], cif: [] };
      if (panel) panel.classList.remove('d-none');
      renderMp(); renderMo(); renderCif();
    } catch (e) { alert('Error de conexión'); }
  };

  if (selOrden) {
    selOrden.addEventListener('change', () => cargarOrden(selOrden.value));
    if (selOrden.value) cargarOrden(selOrden.value);
  } else {
    renderMp(); renderMo(); renderCif();
    if (btnGuardar) btnGuardar.disabled = false;
  }

  form.addEventListener('submit', () => recalcTotal());
});
</script>
