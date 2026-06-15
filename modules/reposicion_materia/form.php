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
    SELECT u.username, s.descripcion_sucursal
    FROM usuarios u
    JOIN sucursales s ON s.id_sucursal = u.id_sucursal
    WHERE u.username = :user LIMIT 1
");
$qUser->execute([':user' => $userSesion]);
$usr = $qUser->fetch();
if (!$usr) {
    die('Usuario de sesión no encontrado.');
}

$pedidos = $pdoForm->query("
    SELECT p.id_pedido_mat_prod, p.ped_mat_prod_fecha, p.ped_mat_prod_estado,
           d.deposito_descri
    FROM pedido_materia_produccion p
    JOIN deposito d ON d.deposito_id = p.deposito_id
    WHERE UPPER(TRIM(p.ped_mat_prod_estado)) IN ('PENDIENTE', 'PARCIAL')
      AND EXISTS (
          SELECT 1 FROM pedido_materia_detalle_produccion pd
          WHERE pd.id_pedido_mat_prod = p.id_pedido_mat_prod
            AND pd.cantidad_repuesta < pd.ped_mat_prod_cantidad
      )
    ORDER BY p.id_pedido_mat_prod DESC
")->fetchAll();

$modoEdit = isset($_GET['form_rep'], $_GET['form'], $_GET['reposicion_id'])
    && $_GET['form_rep'] === 'edit' && $_GET['form'] === 'edit';
$repEditId = $modoEdit ? (int)$_GET['reposicion_id'] : 0;
$repData = null;

if ($modoEdit && $repEditId > 0) {
    $st = $pdoForm->prepare("
        SELECT reposicion_id, reposicion_fecha, reposicion_estado, id_pedido_mat_prod
        FROM reposicion_materia WHERE reposicion_id = :id
    ");
    $st->execute([':id' => $repEditId]);
    $repData = $st->fetch();
    if (!$repData || strtoupper(trim((string)$repData['reposicion_estado'])) !== 'REGISTRADO') {
        die('Reposición no editable.');
    }
}

$pedidoPre = isset($_GET['id_pedido_mat_prod']) ? (int)$_GET['id_pedido_mat_prod'] : 0;

if (!$modoEdit && isset($_GET['form_rep'], $_GET['form']) && $_GET['form_rep'] === 'add' && $_GET['form'] === 'add'):
?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-dolly"></i> Registrar reposición de MP</h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="view.php">Reposición de MP</a></li>
    <li class="breadcrumb-item active">Nuevo</li>
  </ol>

  <div class="alert alert-info small">
    Ingreso físico de materia prima al depósito del pedido. Actualiza <strong>stock</strong> y el avance del pedido.
    Puede reponer parcialmente (varias reposiciones por pedido).
  </div>

  <?php if (empty($pedidos)): ?>
  <div class="alert alert-warning">No hay pedidos de MP con cantidades pendientes. Cree un pedido primero.</div>
  <a href="../pedido_materia_produccion/view.php" class="btn btn-primary">Ir a pedidos de MP</a>
  <?php else: ?>

  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=insert" method="POST" id="form-rep-add">
      <div class="row mb-3">
        <div class="col-md-2"><label>Fecha</label>
          <input type="date" class="form-control" name="reposicion_fecha" value="<?= htmlspecialchars($fechaHoy) ?>" required></div>
        <div class="col-md-2"><label>Hora</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($horaHoy) ?>" readonly></div>
        <div class="col-md-4"><label>Usuario</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['username']) ?>" readonly></div>
        <div class="col-md-4"><label>Sucursal</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['descripcion_sucursal']) ?>" readonly></div>
      </div>

      <div class="row mb-3">
        <div class="col-md-8">
          <label for="id_pedido_mat_prod">Pedido de materia prima <span class="text-danger">*</span></label>
          <select class="form-control" name="id_pedido_mat_prod" id="id_pedido_mat_prod" required>
            <option value="">Seleccione pedido</option>
            <?php foreach ($pedidos as $p): ?>
            <option value="<?= (int)$p['id_pedido_mat_prod'] ?>"
              <?= $pedidoPre === (int)$p['id_pedido_mat_prod'] ? ' selected' : '' ?>>
              Pedido #<?= (int)$p['id_pedido_mat_prod'] ?> — <?= htmlspecialchars($p['deposito_descri']) ?>
              (<?= htmlspecialchars($p['ped_mat_prod_estado']) ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div id="panel-pedido" class="d-none">
        <p class="small text-muted" id="hint-pedido"></p>
        <div class="table-responsive mb-3">
          <table class="table table-bordered table-sm" id="tabla-pendientes">
            <thead class="table-dark">
              <tr>
                <th>Materia prima</th>
                <th class="text-right">Solicitada</th>
                <th class="text-right">Ya repuesta</th>
                <th class="text-right">Pendiente</th>
                <th style="width:120px">Reponer ahora</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>

      <input type="hidden" name="items" id="items" value="[]">

      <div class="d-flex justify-content-end">
        <button type="submit" name="Guardar" class="btn btn-success mr-2" id="btn-guardar" disabled>Registrar reposición</button>
        <a href="view.php" class="btn btn-warning">Cancelar</a>
      </div>
    </form>
  </div></div>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const selPed = document.getElementById('id_pedido_mat_prod');
  const panel = document.getElementById('panel-pedido');
  const tbody = document.querySelector('#tabla-pendientes tbody');
  const hint = document.getElementById('hint-pedido');
  const hidItems = document.getElementById('items');
  const btnGuardar = document.getElementById('btn-guardar');
  const form = document.getElementById('form-rep-add');
  if (!selPed || !form) return;

  let lineas = [];

  const syncItems = () => {
    const items = [];
    tbody.querySelectorAll('input[data-mp]').forEach(inp => {
      const cant = parseInt(inp.value, 10) || 0;
      if (cant > 0) {
        items.push({ id_materia_prima: parseInt(inp.dataset.mp, 10), cantidad: cant });
      }
    });
    hidItems.value = JSON.stringify(items);
    btnGuardar.disabled = items.length === 0;
  };

  const cargarPedido = async (id) => {
    panel.classList.add('d-none');
    tbody.innerHTML = '';
    btnGuardar.disabled = true;
    if (!id) return;
    hint.textContent = 'Cargando...';
    try {
      const r = await fetch('get_pedido_info.php?id_pedido_mat_prod=' + id);
      const data = await r.json();
      if (!data.success) {
        alert(data.error || 'Error');
        return;
      }
      lineas = data.lineas || [];
      hint.textContent = 'Pedido #' + data.pedido.id_pedido_mat_prod
        + ' → Depósito: ' + data.pedido.deposito_descri;
      lineas.forEach(ln => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${ln.materia_prima_descripcion}</td>
          <td class="text-right">${ln.ped_mat_prod_cantidad}</td>
          <td class="text-right">${ln.cantidad_repuesta}</td>
          <td class="text-right">${ln.cantidad_pendiente}</td>
          <td><input type="number" class="form-control form-control-sm text-right inp-reponer"
              data-mp="${ln.id_materia_prima}" data-max="${ln.cantidad_pendiente}"
              min="0" max="${ln.cantidad_pendiente}" step="1" value="0"></td>`;
        tbody.appendChild(tr);
      });
      tbody.querySelectorAll('.inp-reponer').forEach(inp => {
        inp.addEventListener('input', syncItems);
      });
      panel.classList.remove('d-none');
      syncItems();
    } catch (e) {
      alert('Error de conexión');
    }
  };

  selPed.addEventListener('change', () => cargarPedido(selPed.value));
  if (selPed.value) cargarPedido(selPed.value);

  form.addEventListener('submit', e => {
    syncItems();
    const items = JSON.parse(hidItems.value || '[]');
    if (!items.length) {
      e.preventDefault();
      alert('Ingrese al menos una cantidad a reponer.');
      return;
    }
    for (const inp of tbody.querySelectorAll('.inp-reponer')) {
      const cant = parseInt(inp.value, 10) || 0;
      const max = parseInt(inp.dataset.max, 10) || 0;
      if (cant > max) {
        e.preventDefault();
        alert('Cantidad supera lo pendiente.');
        return;
      }
    }
  });
});
</script>

<?php elseif ($modoEdit && $repData): ?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-edit"></i> Editar reposición #<?= (int)$repData['reposicion_id'] ?></h1>
  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=update" method="POST">
      <input type="hidden" name="reposicion_id" value="<?= (int)$repData['reposicion_id'] ?>">
      <div class="row mb-3">
        <div class="col-md-4">
          <label>Pedido MP</label>
          <input type="text" class="form-control" value="#<?= (int)$repData['id_pedido_mat_prod'] ?>" readonly>
        </div>
        <div class="col-md-4">
          <label for="reposicion_fecha">Fecha</label>
          <input type="date" class="form-control" name="reposicion_fecha" id="reposicion_fecha"
            value="<?= htmlspecialchars(substr((string)$repData['reposicion_fecha'], 0, 10)) ?>" required>
        </div>
      </div>
      <p class="text-muted small">Para cambiar cantidades debe anular y registrar una nueva reposición.</p>
      <div class="d-flex justify-content-end">
        <button type="submit" name="Guardar" class="btn btn-success mr-2">Guardar</button>
        <a href="view.php" class="btn btn-warning">Cancelar</a>
      </div>
    </form>
  </div></div>
</div>
<?php endif; ?>
