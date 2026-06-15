<?php
require_once realpath(__DIR__ . '/../../config/database.php');
require_once __DIR__ . '/equipos_helper.php';

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

$ordenes = $pdoForm->query("
    SELECT op.orden_id, op.orden_prod_estado, op.id_pedido_produccion
    FROM orden_produccion op
    WHERE op.orden_prod_estado IN ('PENDIENTE', 'EN_PROCESO')
    ORDER BY op.orden_id DESC
")->fetchAll();

$modoEdit = isset($_GET['form_equipo'], $_GET['form'], $_GET['equipo_id'])
    && $_GET['form_equipo'] === 'edit' && $_GET['form'] === 'edit';
$equipoEditId = $modoEdit ? (int)$_GET['equipo_id'] : 0;
$equipoData = null;
$miembrosInit = [];

if ($modoEdit && $equipoEditId > 0) {
    $st = $pdoForm->prepare("
        SELECT equipo_id, equipo_descri, equipo_estado, equipo_fecha, orden_id
        FROM equipos_produccion WHERE equipo_id = :id
    ");
    $st->execute([':id' => $equipoEditId]);
    $equipoData = $st->fetch();
    if (!$equipoData || !in_array(strtoupper(trim((string)$equipoData['equipo_estado'])), ['PENDIENTE', 'ACTIVO'], true)) {
        die('Equipo no editable.');
    }
    $miembrosInit = cargarDetalleEquipo($pdoForm, $equipoEditId);
}

$ordenPre = isset($_GET['orden_id']) ? (int)$_GET['orden_id'] : 0;
$jsMiembros = json_encode(array_map(static function ($m) {
    return [
        'trabajadores_id' => (int)$m['trabajadores_id'],
        'nombre' => $m['nombre'],
        'tarea_rol' => $m['tarea_rol'],
    ];
}, $miembrosInit));

$titulo = $modoEdit ? 'Editar equipo' : 'Nuevo equipo';
$action = $modoEdit ? 'update' : 'insert';

if (!$modoEdit && isset($_GET['form_equipo'], $_GET['form']) && $_GET['form_equipo'] === 'add' && $_GET['form'] === 'add'):
?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-users"></i> <?= htmlspecialchars($titulo) ?></h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="view.php">Equipos de trabajo</a></li>
    <li class="breadcrumb-item active">Nuevo</li>
  </ol>

  <?php if (empty($ordenes)): ?>
  <div class="alert alert-warning">No hay órdenes de producción PENDIENTE o EN_PROCESO.</div>
  <a href="view.php" class="btn btn-secondary">Volver</a>
  <?php else: ?>

  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=insert" method="POST" id="form-equipo">
      <div class="row mb-3">
        <div class="col-md-2"><label>Fecha</label>
          <input type="date" class="form-control" name="equipo_fecha" value="<?= htmlspecialchars($fechaHoy) ?>" required></div>
        <div class="col-md-3"><label>Usuario</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['username']) ?>" readonly></div>
        <div class="col-md-7"><label>Sucursal</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['descripcion_sucursal']) ?>" readonly></div>
      </div>

      <div class="row mb-3">
        <div class="col-md-4">
          <label for="orden_id">Orden de producción <span class="text-danger">*</span></label>
          <select class="form-control" name="orden_id" id="orden_id" required>
            <option value="">Seleccione OP</option>
            <?php foreach ($ordenes as $o): ?>
            <option value="<?= (int)$o['orden_id'] ?>"<?= $ordenPre === (int)$o['orden_id'] ? ' selected' : '' ?>>
              OP #<?= (int)$o['orden_id'] ?> — Pedido #<?= (int)$o['id_pedido_produccion'] ?> (<?= htmlspecialchars($o['orden_prod_estado']) ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label for="etapa_nombre">Etapa <span class="text-danger">*</span></label>
          <select class="form-control" name="etapa_nombre" id="etapa_nombre" required disabled>
            <option value="">Seleccione OP primero</option>
          </select>
        </div>
        <div class="col-md-4">
          <label for="equipo_descri">Nombre del equipo</label>
          <input type="text" class="form-control" name="equipo_descri" id="equipo_descri" maxlength="30" placeholder="Auto si vacío">
        </div>
      </div>

      <div id="panel-op" class="d-none mb-2">
        <p class="small text-muted mb-2" id="hint-op"></p>
      </div>

      <h6 class="font-weight-bold">Trabajadores del equipo</h6>
      <div class="row align-items-end mb-2">
        <div class="col-md-5">
          <label for="sel_trab">Trabajador</label>
          <select class="form-control" id="sel_trab" disabled><option value="">Seleccione OP</option></select>
        </div>
        <div class="col-md-4">
          <label for="inp_rol">Rol / tarea</label>
          <input type="text" class="form-control" id="inp_rol" maxlength="100" placeholder="Ej. Operario cocción" disabled>
        </div>
        <div class="col-md-3">
          <button type="button" class="btn btn-outline-primary btn-block" id="btn-add-trab" disabled><i class="fas fa-plus"></i> Agregar</button>
        </div>
      </div>

      <div class="table-responsive mb-3">
        <table class="table table-bordered table-sm" id="tabla-miembros">
          <thead class="table-dark"><tr><th>Trabajador</th><th>Rol / etapa</th><th style="width:50px"></th></tr></thead>
          <tbody></tbody>
        </table>
      </div>

      <input type="hidden" name="miembros" id="miembros-json" value="[]">

      <div class="d-flex justify-content-end">
        <button type="submit" name="Guardar" class="btn btn-success mr-2" id="btn-guardar" disabled>Guardar equipo</button>
        <a href="view.php" class="btn btn-warning">Cancelar</a>
      </div>
    </form>
  </div></div>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const selOp = document.getElementById('orden_id');
  const selEtapa = document.getElementById('etapa_nombre');
  const selTrab = document.getElementById('sel_trab');
  const inpRol = document.getElementById('inp_rol');
  const btnAdd = document.getElementById('btn-add-trab');
  const tbody = document.querySelector('#tabla-miembros tbody');
  const hid = document.getElementById('miembros-json');
  const btnGuardar = document.getElementById('btn-guardar');
  const form = document.getElementById('form-equipo');
  const hint = document.getElementById('hint-op');
  const panel = document.getElementById('panel-op');
  if (!selOp || !form) return;

  let miembros = [];
  let trabajadores = [];
  let etapaActual = '';

  const sync = () => {
    hid.value = JSON.stringify(miembros);
    btnGuardar.disabled = miembros.length === 0 || !selEtapa.value;
  };

  const render = () => {
    tbody.innerHTML = '';
    miembros.forEach((m, idx) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${m.nombre}</td><td>${m.tarea_rol}</td>
        <td><button type="button" class="btn btn-danger btn-sm btn-del" data-idx="${idx}"><i class="fas fa-trash"></i></button></td>`;
      tbody.appendChild(tr);
    });
    tbody.querySelectorAll('.btn-del').forEach(b => {
      b.addEventListener('click', () => { miembros.splice(parseInt(b.dataset.idx, 10), 1); render(); sync(); });
    });
    sync();
  };

  const cargarOp = async (oid) => {
    selEtapa.innerHTML = '<option value="">Cargando...</option>';
    selEtapa.disabled = true;
    selTrab.innerHTML = '<option value="">Cargando...</option>';
    selTrab.disabled = true;
    inpRol.disabled = true;
    btnAdd.disabled = true;
    panel.classList.add('d-none');
    miembros = [];
    render();

    if (!oid) return;
    try {
      const r = await fetch('get_orden_info.php?orden_id=' + oid);
      const data = await r.json();
      if (!data.success) { alert(data.error || 'Error'); return; }

      selEtapa.innerHTML = '<option value="">Seleccione etapa</option>';
      (data.etapas || []).forEach(e => {
        const opt = document.createElement('option');
        opt.value = e.etapa_nombre;
        opt.textContent = e.etapa_nombre + ' (sec. ' + e.etapa_secuencia + ') — ' + e.producto_descripcion;
        selEtapa.appendChild(opt);
      });
      selEtapa.disabled = (data.etapas || []).length === 0;

      trabajadores = data.trabajadores || [];
      selTrab.innerHTML = '<option value="">Seleccione trabajador</option>';
      trabajadores.forEach(t => {
        const opt = document.createElement('option');
        opt.value = t.trabajadores_id;
        opt.textContent = t.nombre + (t.trabajador_rol ? ' (' + t.trabajador_rol + ')' : '');
        selTrab.appendChild(opt);
      });
      selTrab.disabled = trabajadores.length === 0;

      hint.textContent = 'Productos en la OP: ' + (data.productos || []).map(p => p.producto_descripcion).join(', ') || '—';
      if (trabajadores.length === 0) {
        hint.textContent += ' — No hay trabajadores activos. Ejecute inserts_basicos_trabajadores.sql';
      }
      panel.classList.remove('d-none');
    } catch (e) { alert('Error de conexión'); }
  };

  selOp.addEventListener('change', () => cargarOp(selOp.value));
  selEtapa.addEventListener('change', () => {
    etapaActual = selEtapa.value;
    inpRol.value = etapaActual;
    inpRol.disabled = !etapaActual;
    btnAdd.disabled = !etapaActual;
    sync();
  });

  btnAdd.addEventListener('click', () => {
    const tid = parseInt(selTrab.value, 10);
    const rol = inpRol.value.trim() || etapaActual;
    if (!tid || !rol) return;
    if (miembros.some(m => m.trabajadores_id === tid)) { alert('Ese trabajador ya está en el equipo.'); return; }
    const t = trabajadores.find(x => parseInt(x.trabajadores_id, 10) === tid);
    miembros.push({ trabajadores_id: tid, nombre: t ? t.nombre : 'Trab. #' + tid, tarea_rol: rol });
    render();
  });

  form.addEventListener('submit', e => {
    if (miembros.length === 0) { e.preventDefault(); alert('Agregue al menos un trabajador.'); return; }
    sync();
  });

  if (selOp.value) cargarOp(selOp.value);
});
</script>

<?php
elseif ($modoEdit && $equipoData):
?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-edit"></i> Editar equipo</h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="view.php">Equipos de trabajo</a></li>
    <li class="breadcrumb-item active">Editar</li>
  </ol>

  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=update" method="POST" id="form-equipo-edit">
      <input type="hidden" name="equipo_id" value="<?= (int)$equipoEditId ?>">
      <div class="row mb-3">
        <div class="col-md-3"><label>Fecha</label>
          <input type="date" class="form-control" name="equipo_fecha" value="<?= htmlspecialchars($equipoData['equipo_fecha']) ?>" required></div>
        <div class="col-md-3"><label>OP</label>
          <input type="text" class="form-control" value="#<?= (int)$equipoData['orden_id'] ?>" readonly></div>
        <div class="col-md-6"><label>Nombre</label>
          <input type="text" class="form-control" name="equipo_descri" maxlength="30" value="<?= htmlspecialchars($equipoData['equipo_descri']) ?>" required></div>
      </div>

      <h6 class="font-weight-bold">Miembros</h6>
      <div class="table-responsive mb-3">
        <table class="table table-bordered table-sm" id="tabla-edit">
          <thead class="table-dark"><tr><th>Trabajador</th><th>Rol / etapa</th></tr></thead>
          <tbody>
            <?php foreach ($miembrosInit as $m): ?>
            <tr>
              <td><?= htmlspecialchars($m['nombre']) ?><input type="hidden" class="hid-tid" value="<?= (int)$m['trabajadores_id'] ?>"></td>
              <td><input type="text" class="form-control form-control-sm inp-rol" value="<?= htmlspecialchars($m['tarea_rol']) ?>" maxlength="100"></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <input type="hidden" name="miembros" id="miembros-json" value="[]">

      <div class="d-flex justify-content-end">
        <button type="submit" name="Guardar" class="btn btn-success mr-2">Guardar</button>
        <a href="view.php" class="btn btn-warning">Cancelar</a>
      </div>
    </form>
  </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('form-equipo-edit');
  const hid = document.getElementById('miembros-json');
  form?.addEventListener('submit', () => {
    const items = [];
    document.querySelectorAll('#tabla-edit tbody tr').forEach(tr => {
      items.push({
        trabajadores_id: parseInt(tr.querySelector('.hid-tid').value, 10),
        tarea_rol: tr.querySelector('.inp-rol').value.trim()
      });
    });
    hid.value = JSON.stringify(items);
  });
});
</script>
<?php endif; ?>
