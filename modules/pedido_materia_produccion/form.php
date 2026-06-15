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

$depositos = $pdoForm->query("
    SELECT deposito_id, deposito_descri FROM deposito ORDER BY deposito_descri ASC
")->fetchAll();

$materias = $pdoForm->query("
    SELECT mp.id_materia_prima, mp.materia_prima_descripcion,
           COALESCE(um.u_medida_descri, '') AS u_medida
    FROM materia_prima mp
    LEFT JOIN u_medida um ON um.u_medida_id = mp.u_medida_id
    WHERE UPPER(TRIM(mp.materia_prima_estado)) = 'ACTIVO'
    ORDER BY mp.materia_prima_descripcion ASC
")->fetchAll();

$modoEdit = isset($_GET['form_ped_mp'], $_GET['form'], $_GET['id_pedido_mat_prod'])
    && $_GET['form_ped_mp'] === 'edit' && $_GET['form'] === 'edit';
$pedidoEditId = $modoEdit ? (int)$_GET['id_pedido_mat_prod'] : 0;
$pedData = null;
$detalleEdit = [];

if ($modoEdit && $pedidoEditId > 0) {
    $st = $pdoForm->prepare("
        SELECT id_pedido_mat_prod, ped_mat_prod_fecha, ped_mat_prod_estado, deposito_id
        FROM pedido_materia_produccion WHERE id_pedido_mat_prod = :id
    ");
    $st->execute([':id' => $pedidoEditId]);
    $pedData = $st->fetch();
    if (!$pedData) {
        die('Pedido no encontrado.');
    }
    if (strtoupper(trim((string)$pedData['ped_mat_prod_estado'])) !== 'PENDIENTE') {
        die('Solo se pueden editar pedidos PENDIENTE.');
    }
    $stDet = $pdoForm->prepare("
        SELECT pd.id_materia_prima, mp.materia_prima_descripcion, pd.ped_mat_prod_cantidad, pd.cantidad_repuesta
        FROM pedido_materia_detalle_produccion pd
        JOIN materia_prima mp ON mp.id_materia_prima = pd.id_materia_prima
        WHERE pd.id_pedido_mat_prod = :id
    ");
    $stDet->execute([':id' => $pedidoEditId]);
    $detalleEdit = $stDet->fetchAll();
    foreach ($detalleEdit as $d) {
        if ((int)$d['cantidad_repuesta'] > 0) {
            die('No se puede editar: ya hay cantidades repuestas.');
        }
    }
}

function optsDepositos(array $depositos, $sel = null): string
{
    $html = '<option value="">Seleccione depósito destino</option>';
    foreach ($depositos as $d) {
        $id = (int)$d['deposito_id'];
        $s = ($sel !== null && (int)$sel === $id) ? ' selected' : '';
        $html .= '<option value="' . $id . '"' . $s . '>' . htmlspecialchars($d['deposito_descri']) . '</option>';
    }
    return $html;
}

function optsMaterias(array $materias): string
{
    $html = '<option value="">Seleccione materia prima</option>';
    foreach ($materias as $m) {
        $html .= '<option value="' . (int)$m['id_materia_prima'] . '" data-nombre="'
            . htmlspecialchars($m['materia_prima_descripcion'], ENT_QUOTES) . '">'
            . htmlspecialchars($m['materia_prima_descripcion'])
            . ($m['u_medida'] ? ' (' . htmlspecialchars($m['u_medida']) . ')' : '')
            . '</option>';
    }
    return $html;
}

$optsMaterias = optsMaterias($materias);
$jsItemsInit = $modoEdit ? json_encode(array_map(static function ($d) {
    return [
        'id_materia_prima' => (int)$d['id_materia_prima'],
        'nombre' => $d['materia_prima_descripcion'],
        'cantidad' => (int)$d['ped_mat_prod_cantidad'],
    ];
}, $detalleEdit)) : '[]';

if (!$modoEdit && isset($_GET['form_ped_mp'], $_GET['form']) && $_GET['form_ped_mp'] === 'add' && $_GET['form'] === 'add'):
?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-boxes"></i> Nuevo pedido de materia prima</h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="view.php">Pedidos de MP</a></li>
    <li class="breadcrumb-item active">Nuevo</li>
  </ol>

  <div class="alert alert-info small">
    Solicitud interna de insumos hacia un depósito de producción.
    <strong>No mueve stock</strong> hasta registrar la reposición.
  </div>

  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=insert" method="POST" id="form-ped-mp">
      <div class="row mb-3">
        <div class="col-md-2"><label>Fecha</label>
          <input type="date" class="form-control" name="ped_mat_prod_fecha" value="<?= htmlspecialchars($fechaHoy) ?>" required></div>
        <div class="col-md-2"><label>Hora</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($horaHoy) ?>" readonly></div>
        <div class="col-md-4"><label>Usuario</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['username']) ?>" readonly></div>
        <div class="col-md-4"><label>Sucursal</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['descripcion_sucursal']) ?>" readonly></div>
      </div>
      <div class="row mb-3">
        <div class="col-md-6">
          <label for="deposito_id">Depósito destino <span class="text-danger">*</span></label>
          <select class="form-control" name="deposito_id" id="deposito_id" required>
            <?= optsDepositos($depositos) ?>
          </select>
        </div>
      </div>

      <div class="row align-items-end mb-2 border rounded p-3 bg-light">
        <div class="col-md-5">
          <label for="materia_id">Materia prima</label>
          <select class="form-control" id="materia_id"><?= $optsMaterias ?></select>
        </div>
        <div class="col-md-3">
          <label for="cantidad_item">Cantidad</label>
          <input type="number" class="form-control" id="cantidad_item" min="1" step="1">
        </div>
        <div class="col-md-2">
          <button type="button" class="btn btn-primary w-100" id="btn-agregar">Agregar</button>
        </div>
      </div>

      <div class="table-responsive mb-3">
        <table class="table table-bordered table-sm" id="tabla-items">
          <thead class="table-dark">
            <tr><th>Materia prima</th><th>Cantidad</th><th></th></tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
      <input type="hidden" name="items" id="items" value="[]">

      <div class="d-flex justify-content-end">
        <button type="submit" name="Guardar" class="btn btn-success mr-2">Guardar pedido</button>
        <a href="view.php" class="btn btn-warning">Cancelar</a>
      </div>
    </form>
  </div></div>
</div>

<?php elseif ($modoEdit && $pedData): ?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-edit"></i> Editar pedido MP #<?= (int)$pedData['id_pedido_mat_prod'] ?></h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="view.php">Pedidos de MP</a></li>
    <li class="breadcrumb-item active">Editar</li>
  </ol>

  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=update" method="POST" id="form-ped-mp">
      <input type="hidden" name="id_pedido_mat_prod" value="<?= (int)$pedData['id_pedido_mat_prod'] ?>">
      <div class="row mb-3">
        <div class="col-md-4">
          <label for="ped_mat_prod_fecha">Fecha</label>
          <input type="date" class="form-control" name="ped_mat_prod_fecha" id="ped_mat_prod_fecha"
            value="<?= htmlspecialchars(substr((string)$pedData['ped_mat_prod_fecha'], 0, 10)) ?>" required>
        </div>
        <div class="col-md-4">
          <label for="deposito_id">Depósito destino</label>
          <select class="form-control" name="deposito_id" id="deposito_id" required>
            <?= optsDepositos($depositos, $pedData['deposito_id']) ?>
          </select>
        </div>
      </div>

      <div class="row align-items-end mb-2 border rounded p-3 bg-light">
        <div class="col-md-5">
          <label for="materia_id">Materia prima</label>
          <select class="form-control" id="materia_id"><?= $optsMaterias ?></select>
        </div>
        <div class="col-md-3">
          <label for="cantidad_item">Cantidad</label>
          <input type="number" class="form-control" id="cantidad_item" min="1" step="1">
        </div>
        <div class="col-md-2">
          <button type="button" class="btn btn-primary w-100" id="btn-agregar">Agregar</button>
        </div>
      </div>

      <div class="table-responsive mb-3">
        <table class="table table-bordered table-sm" id="tabla-items">
          <thead class="table-dark">
            <tr><th>Materia prima</th><th>Cantidad</th><th></th></tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
      <input type="hidden" name="items" id="items" value="[]">

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
  const form = document.getElementById('form-ped-mp');
  if (!form) return;

  const selMp = document.getElementById('materia_id');
  const inpCant = document.getElementById('cantidad_item');
  const btnAdd = document.getElementById('btn-agregar');
  const tbody = document.querySelector('#tabla-items tbody');
  const hidItems = document.getElementById('items');

  let items = <?= $jsItemsInit ?>;

  const syncHidden = () => {
    hidItems.value = JSON.stringify(items.map(it => ({
      id_materia_prima: it.id_materia_prima,
      cantidad: it.cantidad,
    })));
  };

  const renderTabla = () => {
    tbody.innerHTML = '';
    items.forEach((it, idx) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${it.nombre}</td>
        <td class="text-right">${it.cantidad}</td>
        <td><button type="button" class="btn btn-danger btn-sm" data-idx="${idx}">Quitar</button></td>`;
      tbody.appendChild(tr);
    });
    tbody.querySelectorAll('button[data-idx]').forEach(b => {
      b.addEventListener('click', () => {
        items.splice(parseInt(b.dataset.idx, 10), 1);
        renderTabla();
        syncHidden();
      });
    });
    syncHidden();
  };

  btnAdd.addEventListener('click', () => {
    const opt = selMp.selectedOptions[0];
    if (!opt || !opt.value) {
      alert('Seleccione una materia prima.');
      return;
    }
    const cant = parseInt(inpCant.value, 10) || 0;
    if (cant <= 0) {
      alert('Cantidad inválida.');
      return;
    }
    const mpId = parseInt(opt.value, 10);
    if (items.some(x => x.id_materia_prima === mpId)) {
      alert('Esa materia prima ya está en el detalle.');
      return;
    }
    items.push({
      id_materia_prima: mpId,
      nombre: opt.dataset.nombre || opt.textContent.trim(),
      cantidad: cant,
    });
    renderTabla();
    selMp.value = '';
    inpCant.value = '';
  });

  form.addEventListener('submit', e => {
    if (items.length === 0) {
      e.preventDefault();
      alert('Agregue al menos un ítem.');
    }
  });

  renderTabla();
});
</script>
