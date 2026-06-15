<?php
require_once realpath(__DIR__ . '/../../config/database.php');

$dsn = "pgsql:host=$host;port=$port;dbname=$database;";
$pdoForm = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

date_default_timezone_set('America/Asuncion');
$fecha = date('Y-m-d');
$hora = date('H:i');

$userSesion = $_SESSION['username'] ?? '';
$qUser = $pdoForm->prepare("
    SELECT u.id_usuario, u.username, u.id_sucursal, s.descripcion_sucursal
    FROM usuarios u
    JOIN sucursales s ON s.id_sucursal = u.id_sucursal
    WHERE u.username = :user LIMIT 1
");
$qUser->execute([':user' => $userSesion]);
$usr = $qUser->fetch();
if (!$usr) {
    die('Usuario de sesión no encontrado.');
}

$tiposPedido = $pdoForm->query("
    SELECT id_tipo_pedido, tipo_pedido_descri
    FROM tipo_pedido
    WHERE tipo_pedido_estado = 'ACTIVO'
    ORDER BY tipo_pedido_descri
")->fetchAll();

$productosActivos = $pdoForm->query("
    SELECT producto_id, producto_descri
    FROM productos
    WHERE producto_estado = 'ACTIVO'
    ORDER BY producto_descri ASC
")->fetchAll();

function optionsTipos(array $tipos, $selected = null): string
{
    $html = '<option value="">Seleccione tipo de pedido</option>';
    foreach ($tipos as $t) {
        $id = (int)$t['id_tipo_pedido'];
        $sel = ($selected !== null && (int)$selected === $id) ? ' selected' : '';
        $html .= '<option value="' . $id . '"' . $sel . '>' . htmlspecialchars($t['tipo_pedido_descri']) . '</option>';
    }
    return $html;
}

function optionsProductos(array $productos): string
{
    $html = '<option value="">Seleccione producto</option>';
    foreach ($productos as $p) {
        $html .= '<option value="' . (int)$p['producto_id'] . '">' . htmlspecialchars($p['producto_descri']) . '</option>';
    }
    return $html;
}

$optsProductos = optionsProductos($productosActivos);

if (isset($_GET['form_pedido_prod'], $_GET['form']) && $_GET['form'] === 'add' && $_GET['form_pedido_prod'] === 'add'):
    $codigo = (int)$pdoForm->query('SELECT COALESCE(MAX(id_pedido_produccion), 0) + 1 FROM pedido_produccion')->fetchColumn();
?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-plus-circle"></i> Registrar pedido de producción</h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
    <li class="breadcrumb-item"><a href="view.php">Pedidos de producción</a></li>
    <li class="breadcrumb-item active">Nuevo</li>
  </ol>

  <div class="modal fade" id="modalAviso" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content"><div class="modal-header"><h5 class="modal-title">Aviso</h5>
      <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
    <div class="modal-body"><p id="modalAvisoText"></p></div>
    <div class="modal-footer"><button type="button" class="btn btn-primary" data-dismiss="modal">Entendido</button></div>
  </div></div></div>

  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=insert" method="POST" id="form-pedido-add">
      <div class="row mb-3">
        <div class="col-md-2"><label class="form-label">Fecha</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($fecha) ?>" readonly></div>
        <div class="col-md-2"><label class="form-label">Hora</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($hora) ?>" readonly></div>
        <div class="col-md-2"><label class="form-label">Pedido N°</label>
          <input type="text" class="form-control" id="codigo" name="codigo" value="<?= $codigo ?>" readonly></div>
        <div class="col-md-3"><label class="form-label">Usuario</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['username']) ?>" readonly></div>
        <div class="col-md-3"><label class="form-label">Sucursal</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['descripcion_sucursal']) ?>" readonly></div>
      </div>
      <div class="row mb-3">
        <div class="col-md-4">
          <label for="id_tipo_pedido" class="form-label">Tipo de pedido <span class="text-danger">*</span></label>
          <select class="form-control" id="id_tipo_pedido" name="id_tipo_pedido" required>
            <?= optionsTipos($tiposPedido) ?>
          </select>
        </div>
        <div class="col-md-8">
          <label for="observaciones" class="form-label">Observaciones</label>
          <textarea class="form-control" id="observaciones" name="observaciones" rows="2" placeholder="Opcional"></textarea>
        </div>
      </div>

      <div class="row align-items-end mb-3">
        <div class="col-md-6"><label for="producto" class="form-label">Producto terminado</label>
          <select class="form-control" id="producto"><?= $optsProductos ?></select></div>
        <div class="col-md-4"><label for="cantidad_producto" class="form-label">Cantidad</label>
          <input type="number" class="form-control" id="cantidad_producto" min="1" placeholder="Cantidad"></div>
        <div class="col-md-2"><button type="button" class="btn btn-primary w-100" id="btn-agregar">Agregar</button></div>
      </div>

      <div class="table-responsive mb-3">
        <table class="table table-bordered table-striped" id="tabla-productos">
          <thead class="table-dark"><tr><th>#</th><th>Código</th><th>Producto</th><th>Cantidad</th><th>Acción</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
      <input type="hidden" name="productos" id="productos" value="[]">

      <div class="d-flex justify-content-end">
        <button type="submit" name="Guardar" class="btn btn-success me-2">Guardar</button>
        <a href="view.php" class="btn btn-warning">Cancelar</a>
      </div>
    </form>
  </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const productos = [];
  const tbody = document.querySelector('#tabla-productos tbody');
  const hidden = document.getElementById('productos');
  const sel = document.getElementById('producto');
  const cantInp = document.getElementById('cantidad_producto');
  const modalAviso = () => {
    const t = document.getElementById('modalAvisoText');
    return (msg) => { t.textContent = msg; jQuery('#modalAviso').modal('show'); };
  };
  const aviso = modalAviso();

  function renderTabla() {
    tbody.innerHTML = '';
    productos.forEach((p, i) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${i+1}</td><td>${p.codigo}</td><td>${p.nombre}</td><td>${p.cantidad}</td>
        <td><button type="button" class="btn btn-danger btn-sm btn-eliminar"><i class="fas fa-times"></i></button></td>`;
      tbody.appendChild(tr);
    });
    hidden.value = JSON.stringify(productos);
  }

  document.getElementById('btn-agregar').addEventListener('click', () => {
    const codigo = parseInt(sel.value, 10);
    const cantidad = parseInt(cantInp.value, 10);
    const nombre = sel.options[sel.selectedIndex]?.text || '';
    if (!codigo || !cantidad || cantidad < 1) {
      aviso('Seleccione un producto y una cantidad válida (mínimo 1).');
      return;
    }
    if (productos.some(p => p.codigo === codigo)) {
      aviso('El producto ya está en el detalle.');
      return;
    }
    productos.push({ codigo, nombre, cantidad });
    renderTabla();
    sel.value = '';
    cantInp.value = '';
  });

  tbody.addEventListener('click', e => {
    if (!e.target.closest('.btn-eliminar')) return;
    const row = e.target.closest('tr');
    const idx = Array.from(tbody.children).indexOf(row);
    productos.splice(idx, 1);
    renderTabla();
  });

  document.getElementById('form-pedido-add').addEventListener('submit', e => {
    if (productos.length === 0) {
      e.preventDefault();
      aviso('Agregue al menos un producto al pedido.');
    }
  });

  cantInp.addEventListener('keydown', e => {
    if (['e','E','+','-','.',','].includes(e.key)) e.preventDefault();
  });
});
</script>

<?php
elseif (isset($_GET['form_pedido_prod'], $_GET['form'], $_GET['ped_id']) && $_GET['form'] === 'edit'):
    $pedidoId = (int)$_GET['ped_id'];
    $stCab = $pdoForm->prepare("
        SELECT pp.*, tp.tipo_pedido_descri, u.username, s.descripcion_sucursal
        FROM pedido_produccion pp
        JOIN tipo_pedido tp ON tp.id_tipo_pedido = pp.id_tipo_pedido
        JOIN usuarios u ON u.id_usuario = pp.id_usuario
        JOIN sucursales s ON s.id_sucursal = pp.id_sucursal
        WHERE pp.id_pedido_produccion = :id
    ");
    $stCab->execute([':id' => $pedidoId]);
    $cabecera = $stCab->fetch();
    if (!$cabecera || strtoupper($cabecera['pedido_prod_estado']) !== 'PENDIENTE') {
        header('Location: view.php?alert=5');
        exit;
    }
    $stDet = $pdoForm->prepare("
        SELECT d.producto_id, d.cantidad_pedido, p.producto_descri
        FROM pedido_detalle_produccion d
        JOIN productos p ON p.producto_id = d.producto_id
        WHERE d.id_pedido_produccion = :id
        ORDER BY p.producto_descri
    ");
    $stDet->execute([':id' => $pedidoId]);
    $detalles = $stDet->fetchAll();
?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-edit"></i> Editar pedido #<?= $pedidoId ?></h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="view.php">Pedidos de producción</a></li>
    <li class="breadcrumb-item active">Editar</li>
  </ol>

  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=update" method="POST" id="form-pedido-edit">
      <input type="hidden" name="pedido_id" value="<?= $pedidoId ?>">
      <input type="hidden" name="pedido_prod_ultima_modificacion" value="<?= htmlspecialchars($cabecera['pedido_prod_ultima_modificacion'] ?? '') ?>">

      <div class="row mb-3">
        <div class="col-md-2"><label>Fecha emisión</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($cabecera['pedido_prod_fecha_emision']) ?>" readonly></div>
        <div class="col-md-2"><label>Estado</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($cabecera['pedido_prod_estado']) ?>" readonly></div>
        <div class="col-md-4"><label>Usuario</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($cabecera['username']) ?>" readonly></div>
        <div class="col-md-4"><label>Sucursal</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($cabecera['descripcion_sucursal']) ?>" readonly></div>
      </div>
      <div class="row mb-3">
        <div class="col-md-4">
          <label for="id_tipo_pedido">Tipo de pedido <span class="text-danger">*</span></label>
          <select class="form-control" name="id_tipo_pedido" id="id_tipo_pedido" required>
            <?= optionsTipos($tiposPedido, $cabecera['id_tipo_pedido']) ?>
          </select>
        </div>
        <div class="col-md-8">
          <label for="observaciones">Observaciones</label>
          <textarea class="form-control" name="observaciones" rows="2"><?= htmlspecialchars($cabecera['pedido_prod_observaciones'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="row align-items-end mb-3">
        <div class="col-md-6"><label>Agregar producto</label>
          <select class="form-control" id="producto_edit"><?= $optsProductos ?></select></div>
        <div class="col-md-4"><label>Cantidad</label>
          <input type="number" class="form-control" id="cantidad_edit" min="1"></div>
        <div class="col-md-2"><button type="button" class="btn btn-primary w-100" id="btn-agregar-edit">Agregar</button></div>
      </div>

      <div class="table-responsive mb-3">
        <table class="table table-bordered" id="tabla-detalle-edit">
          <thead class="table-dark"><tr><th>#</th><th>Código</th><th>Producto</th><th>Cantidad</th><th>Acción</th></tr></thead>
          <tbody id="tbody-detalle-edit">
            <?php if ($detalles): $i = 1; foreach ($detalles as $d): ?>
            <tr data-producto-id="<?= (int)$d['producto_id'] ?>">
              <td><?= $i++ ?></td>
              <td><?= (int)$d['producto_id'] ?></td>
              <td><?= htmlspecialchars($d['producto_descri']) ?></td>
              <td><input type="number" class="form-control cantidad-edit" name="cantidad[<?= (int)$d['producto_id'] ?>]" value="<?= (int)$d['cantidad_pedido'] ?>" min="1" required></td>
              <td><button type="button" class="btn btn-danger btn-sm btn-quitar-edit"><i class="fas fa-times"></i></button></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5" class="text-center">Sin detalles.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <input type="hidden" name="productos_eliminados" id="productos_eliminados" value="">
      <input type="hidden" name="productos_nuevos" id="productos_nuevos" value="">

      <div class="d-flex justify-content-end">
        <button type="submit" name="Guardar" class="btn btn-success me-2">Guardar cambios</button>
        <a href="view.php" class="btn btn-warning">Cancelar</a>
      </div>
    </form>
  </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const eliminados = [];
  const nuevos = [];
  const tbody = document.getElementById('tbody-detalle-edit');
  const hidElim = document.getElementById('productos_eliminados');
  const hidNuevos = document.getElementById('productos_nuevos');
  const sel = document.getElementById('producto_edit');
  const cantInp = document.getElementById('cantidad_edit');

  function reindex() {
    Array.from(tbody.querySelectorAll('tr[data-producto-id]')).forEach((tr, i) => {
      tr.children[0].textContent = i + 1;
    });
  }

  function syncHidden() {
    hidElim.value = JSON.stringify(eliminados);
    hidNuevos.value = JSON.stringify(nuevos);
  }

  document.getElementById('btn-agregar-edit').addEventListener('click', () => {
    const codigo = parseInt(sel.value, 10);
    const cantidad = parseInt(cantInp.value, 10);
    const nombre = sel.options[sel.selectedIndex]?.text || '';
    if (!codigo || !cantidad || cantidad < 1) {
      alert('Seleccione producto y cantidad válida.');
      return;
    }
    if (tbody.querySelector(`tr[data-producto-id="${codigo}"]`)) {
      alert('El producto ya está en el detalle.');
      return;
    }
    const tr = document.createElement('tr');
    tr.setAttribute('data-producto-id', codigo);
    tr.innerHTML = `<td></td><td>${codigo}</td><td>${nombre}</td>
      <td><input type="number" class="form-control cantidad-edit" name="cantidad[${codigo}]" value="${cantidad}" min="1" required></td>
      <td><button type="button" class="btn btn-danger btn-sm btn-quitar-edit"><i class="fas fa-times"></i></button></td>`;
    tbody.appendChild(tr);
    nuevos.push({ codigo, cantidad });
    syncHidden();
    reindex();
    sel.value = '';
    cantInp.value = '';
  });

  tbody.addEventListener('click', e => {
    const btn = e.target.closest('.btn-quitar-edit');
    if (!btn) return;
    const tr = btn.closest('tr');
    const id = parseInt(tr.getAttribute('data-producto-id'), 10);
    if (id) eliminados.push(id);
    const idxNuevo = nuevos.findIndex(n => n.codigo === id);
    if (idxNuevo >= 0) nuevos.splice(idxNuevo, 1);
    tr.remove();
    syncHidden();
    reindex();
  });

  document.getElementById('form-pedido-edit').addEventListener('submit', e => {
    const filas = tbody.querySelectorAll('tr[data-producto-id]');
    if (filas.length === 0) {
      e.preventDefault();
      alert('El pedido debe tener al menos un producto.');
      return;
    }
    let invalido = false;
    filas.forEach(tr => {
      const inp = tr.querySelector('.cantidad-edit');
      if (!inp || parseInt(inp.value, 10) < 1) invalido = true;
    });
    if (invalido) {
      e.preventDefault();
      alert('Todas las cantidades deben ser mayores a cero.');
    }
  });

  syncHidden();
});
</script>
<?php else: ?>
<script>window.location.href = 'view.php';</script>
<?php endif; ?>
