<?php
require_once realpath(__DIR__ . '/../../config/database.php');

$dsn = "pgsql:host=$host;port=$port;dbname=$database;";
$pdoForm = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

date_default_timezone_set('America/Asuncion');
$fechaHoy = date('Y-m-d');
$horaHoy = date('H:i');

$userSesion = $_SESSION['username'] ?? '';
$qUser = $pdoForm->prepare("
    SELECT u.id_usuario, u.username, s.descripcion_sucursal
    FROM usuarios u
    JOIN sucursales s ON s.id_sucursal = u.id_sucursal
    WHERE u.username = :user LIMIT 1
");
$qUser->execute([':user' => $userSesion]);
$usr = $qUser->fetch();
if (!$usr) {
    die('Usuario de sesión no encontrado.');
}

$ordenes = $pdoForm->query("
    SELECT op.orden_id, op.id_pedido_produccion, op.orden_prod_estado
    FROM orden_produccion op
    WHERE op.orden_prod_estado IN ('PENDIENTE', 'EN_PROCESO', 'TERMINADA')
    ORDER BY op.orden_id DESC
")->fetchAll();

$depositos = $pdoForm->query("
    SELECT deposito_id, deposito_descri FROM deposito ORDER BY deposito_descri ASC
")->fetchAll();

$modoEdit = isset($_GET['form_pt'], $_GET['form'], $_GET['terminado_id'])
    && $_GET['form_pt'] === 'edit' && $_GET['form'] === 'edit';
$terminadoEdit = $modoEdit ? (int)$_GET['terminado_id'] : 0;
$ptData = null;
if ($modoEdit && $terminadoEdit > 0) {
    $st = $pdoForm->prepare("
        SELECT pt.terminado_id, pt.orden_id, pt.terminado_fecha,
               (SELECT COUNT(*) FROM control_calidad_produccion cc WHERE cc.terminado_id = pt.terminado_id) AS tiene_calidad
        FROM producto_terminado pt
        WHERE pt.terminado_id = :id
    ");
    $st->execute([':id' => $terminadoEdit]);
    $ptData = $st->fetch();
    if (!$ptData) {
        die('Registro no encontrado.');
    }
}

if (!$modoEdit && isset($_GET['form_pt'], $_GET['form']) && $_GET['form_pt'] === 'add' && $_GET['form'] === 'add'):
?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-box"></i> Registrar productos terminados</h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="view.php">Productos terminados</a></li>
    <li class="breadcrumb-item active">Nuevo</li>
  </ol>

  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=insert" method="POST" id="form-pt-add">
      <div class="row mb-3">
        <div class="col-md-2"><label>Fecha</label>
          <input type="date" class="form-control" name="terminado_fecha" value="<?= htmlspecialchars($fechaHoy) ?>" required></div>
        <div class="col-md-2"><label>Hora</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($horaHoy) ?>" readonly></div>
        <div class="col-md-4"><label>Usuario</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['username']) ?>" readonly></div>
        <div class="col-md-4"><label>Sucursal</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['descripcion_sucursal']) ?>" readonly></div>
      </div>

      <div class="row mb-3">
        <div class="col-md-6">
          <label for="orden_id">Orden de producción <span class="text-danger">*</span></label>
          <select class="form-control" name="orden_id" id="orden_id" required>
            <option value="">Seleccione OP</option>
            <?php foreach ($ordenes as $o): ?>
            <option value="<?= (int)$o['orden_id'] ?>">
              OP #<?= (int)$o['orden_id'] ?> — Pedido #<?= (int)$o['id_pedido_produccion'] ?> (<?= htmlspecialchars($o['orden_prod_estado']) ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div id="panel-op" class="d-none">
        <p class="small text-muted" id="hint-op"></p>
        <div class="row align-items-end mb-2 border rounded p-3 bg-light">
          <div class="col-md-4">
            <label for="producto_id">Producto</label>
            <select class="form-control" id="producto_id" disabled>
              <option value="">Seleccione orden</option>
            </select>
          </div>
          <div class="col-md-3">
            <label for="deposito_id">Depósito destino <span class="text-danger">*</span></label>
            <select class="form-control" id="deposito_id">
              <option value="">Seleccione</option>
              <?php foreach ($depositos as $d): ?>
              <option value="<?= (int)$d['deposito_id'] ?>"><?= htmlspecialchars($d['deposito_descri']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label for="cantidad_item">Cantidad</label>
            <input type="number" class="form-control" id="cantidad_item" min="1" step="1" disabled>
            <small class="text-muted" id="hint-finalizable"></small>
          </div>
          <div class="col-md-3">
            <label for="fecha_elab">F. elaboración</label>
            <input type="date" class="form-control" id="fecha_elab" value="<?= htmlspecialchars($fechaHoy) ?>">
          </div>
          <div class="col-md-3 mt-2">
            <label for="fecha_venc">F. vencimiento</label>
            <input type="date" class="form-control" id="fecha_venc">
          </div>
          <div class="col-md-2 mt-2">
            <button type="button" class="btn btn-primary w-100" id="btn-agregar-item" disabled>Agregar ítem</button>
          </div>
        </div>

        <div class="table-responsive mb-3">
          <table class="table table-bordered table-sm" id="tabla-items">
            <thead class="table-dark">
              <tr>
                <th>Producto</th>
                <th>Depósito</th>
                <th>Cantidad</th>
                <th>Elab.</th>
                <th>Venc.</th>
                <th>Acción</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>

      <input type="hidden" name="items" id="items" value="[]">

      <div class="d-flex justify-content-end">
        <button type="submit" name="Guardar" class="btn btn-success me-2" id="btn-guardar" disabled>Guardar</button>
        <a href="view.php" class="btn btn-warning">Cancelar</a>
      </div>
    </form>
  </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const selOrden = document.getElementById('orden_id');
  const panelOp = document.getElementById('panel-op');
  const selProd = document.getElementById('producto_id');
  const selDep = document.getElementById('deposito_id');
  const inpCant = document.getElementById('cantidad_item');
  const hintFin = document.getElementById('hint-finalizable');
  const hintOp = document.getElementById('hint-op');
  const btnAdd = document.getElementById('btn-agregar-item');
  const btnGuardar = document.getElementById('btn-guardar');
  const tbody = document.querySelector('#tabla-items tbody');
  const hidItems = document.getElementById('items');

  let productosOp = [];
  let items = [];

  const syncHidden = () => {
    hidItems.value = JSON.stringify(items);
    btnGuardar.disabled = items.length === 0;
  };

  const renderTabla = () => {
    tbody.innerHTML = '';
    items.forEach((it, idx) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${it.producto_nombre}</td>
        <td>${it.deposito_nombre}</td>
        <td class="text-right">${it.cantidad}</td>
        <td>${it.fecha_elab || '-'}</td>
        <td>${it.fecha_venc || '-'}</td>
        <td><button type="button" class="btn btn-danger btn-sm" data-idx="${idx}">Quitar</button></td>`;
      tbody.appendChild(tr);
    });
    tbody.querySelectorAll('button[data-idx]').forEach(b => {
      b.addEventListener('click', () => {
        items.splice(parseInt(b.dataset.idx, 10), 1);
        renderTabla();
        syncHidden();
        actualizarSelectProductos();
      });
    });
    syncHidden();
  };

  const cantidadEnItems = (productoId) =>
    items.filter(x => x.producto_id === productoId).reduce((s, x) => s + x.cantidad, 0);

  const actualizarSelectProductos = () => {
    selProd.innerHTML = '<option value="">Seleccione producto</option>';
    productosOp.forEach(p => {
      const usado = cantidadEnItems(p.producto_id);
      const disp = p.cantidad_finalizable - usado;
      if (disp > 0) {
        const opt = document.createElement('option');
        opt.value = p.producto_id;
        opt.textContent = `${p.producto_descri} (disp. ${disp})`;
        opt.dataset.max = disp;
        opt.dataset.nombre = p.producto_descri;
        selProd.appendChild(opt);
      }
    });
    selProd.disabled = productosOp.length === 0;
  };

  selProd.addEventListener('change', () => {
    const opt = selProd.selectedOptions[0];
    if (!opt || !opt.value) {
      inpCant.disabled = true;
      hintFin.textContent = '';
      btnAdd.disabled = true;
      return;
    }
    const max = parseInt(opt.dataset.max, 10) || 0;
    inpCant.max = max;
    inpCant.disabled = false;
    hintFin.textContent = `Máx. finalizable: ${max}`;
    btnAdd.disabled = false;
  });

  selOrden.addEventListener('change', async () => {
    items = [];
    productosOp = [];
    renderTabla();
    const id = selOrden.value;
    if (!id) {
      panelOp.classList.add('d-none');
      return;
    }
    panelOp.classList.remove('d-none');
    hintOp.textContent = 'Cargando...';
    try {
      const r = await fetch('get_orden_info.php?orden_id=' + id);
      const data = await r.json();
      if (!data.success) {
        alert(data.error || 'Error');
        panelOp.classList.add('d-none');
        return;
      }
      productosOp = data.productos || [];
      hintOp.textContent = `OP #${data.orden.orden_id} (${data.orden.orden_prod_estado}). Solo productos con control en etapa Empaque (última etapa), menos lo ya ingresado a PT.`;
      actualizarSelectProductos();
      inpCant.disabled = true;
      btnAdd.disabled = true;
    } catch (e) {
      alert('Error de conexión');
    }
  });

  btnAdd.addEventListener('click', () => {
    const opt = selProd.selectedOptions[0];
    const depOpt = selDep.selectedOptions[0];
    if (!opt || !opt.value || !depOpt || !depOpt.value) {
      alert('Seleccione producto y depósito.');
      return;
    }
    const cant = parseInt(inpCant.value, 10) || 0;
    const max = parseInt(opt.dataset.max, 10) || 0;
    if (cant <= 0 || cant > max) {
      alert(`Cantidad inválida. Máximo: ${max}`);
      return;
    }
    const pid = parseInt(opt.value, 10);
    if (items.some(x => x.producto_id === pid)) {
      alert('Ese producto ya está en el detalle. Quite la línea para cambiar depósito o cantidad.');
      return;
    }
    const elab = document.getElementById('fecha_elab').value;
    const venc = document.getElementById('fecha_venc').value;
    if (venc && elab && venc < elab) {
      alert('Vencimiento no puede ser anterior a elaboración.');
      return;
    }
    items.push({
      producto_id: pid,
      producto_nombre: opt.dataset.nombre,
      deposito_id: parseInt(depOpt.value, 10),
      deposito_nombre: depOpt.textContent.trim(),
      cantidad: cant,
      fecha_elab: elab || null,
      fecha_venc: venc || null,
    });
    renderTabla();
    actualizarSelectProductos();
    selProd.value = '';
    inpCant.value = '';
    inpCant.disabled = true;
    btnAdd.disabled = true;
    hintFin.textContent = '';
  });

  document.getElementById('form-pt-add').addEventListener('submit', e => {
    if (items.length === 0) {
      e.preventDefault();
      alert('Agregue al menos un ítem.');
    }
  });
});
</script>

<?php elseif ($modoEdit && $ptData): ?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-edit"></i> Editar productos terminados #<?= (int)$ptData['terminado_id'] ?></h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="view.php">Productos terminados</a></li>
    <li class="breadcrumb-item active">Editar</li>
  </ol>

  <?php if ((int)$ptData['tiene_calidad'] > 0): ?>
  <div class="alert alert-warning">Este registro tiene control de calidad. No se pueden modificar cantidades ni depósitos.</div>
  <?php endif; ?>

  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=update" method="POST">
      <input type="hidden" name="terminado_id" value="<?= (int)$ptData['terminado_id'] ?>">
      <div class="row mb-3">
        <div class="col-md-4">
          <label>Orden de producción</label>
          <input type="text" class="form-control" value="OP #<?= (int)$ptData['orden_id'] ?>" readonly>
        </div>
        <div class="col-md-4">
          <label for="terminado_fecha">Fecha del registro</label>
          <input type="date" class="form-control" name="terminado_fecha" id="terminado_fecha"
            value="<?= htmlspecialchars(substr((string)$ptData['terminado_fecha'], 0, 10)) ?>" required>
        </div>
      </div>
      <p class="text-muted small">Para cambiar cantidades o depósitos debe anular el registro (si no tiene control de calidad) y crear uno nuevo.</p>
      <div class="d-flex justify-content-end">
        <button type="submit" name="Guardar" class="btn btn-success">Guardar</button>
        <a href="view.php" class="btn btn-warning ml-2">Cancelar</a>
      </div>
    </form>
  </div></div>
</div>
<?php endif; ?>
