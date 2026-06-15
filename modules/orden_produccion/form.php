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

$pedidosPendientes = $pdoForm->query("
    SELECT pp.id_pedido_produccion, pp.pedido_prod_fecha_emision, tp.tipo_pedido_descri
    FROM pedido_produccion pp
    JOIN tipo_pedido tp ON tp.id_tipo_pedido = pp.id_tipo_pedido
    WHERE pp.pedido_prod_estado = 'PENDIENTE'
      AND NOT EXISTS (
          SELECT 1 FROM orden_produccion op
          WHERE op.id_pedido_produccion = pp.id_pedido_produccion
            AND op.orden_prod_estado <> 'ANULADA'
      )
    ORDER BY pp.id_pedido_produccion DESC
")->fetchAll();

if (isset($_GET['form_orden_prod'], $_GET['form']) && $_GET['form'] === 'add' && $_GET['form_orden_prod'] === 'add'):
?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-plus-circle"></i> Generar orden de producción</h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="view.php">Órdenes de producción</a></li>
    <li class="breadcrumb-item active">Nueva</li>
  </ol>

  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=insert" method="POST" id="form-orden-add">
      <div class="row mb-3">
        <div class="col-md-3"><label>Fecha emisión</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($fechaHoy) ?>" readonly></div>
        <div class="col-md-3"><label>Usuario</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['username']) ?>" readonly></div>
        <div class="col-md-3"><label>Sucursal</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['descripcion_sucursal']) ?>" readonly></div>
        <div class="col-md-3">
          <label for="orden_prod_fecha_entrega">Fecha entrega prevista <span class="text-danger">*</span></label>
          <input type="date" class="form-control" name="orden_prod_fecha_entrega" id="orden_prod_fecha_entrega"
                 value="<?= htmlspecialchars($fechaHoy) ?>" min="<?= htmlspecialchars($fechaHoy) ?>" required>
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-6">
          <label for="id_pedido_produccion">Pedido de producción <span class="text-danger">*</span></label>
          <select class="form-control" name="id_pedido_produccion" id="id_pedido_produccion" required>
            <option value="">Seleccione pedido PENDIENTE</option>
            <?php foreach ($pedidosPendientes as $p): ?>
            <option value="<?= (int)$p['id_pedido_produccion'] ?>">
              #<?= (int)$p['id_pedido_produccion'] ?> — <?= htmlspecialchars($p['tipo_pedido_descri']) ?>
              (<?= htmlspecialchars($p['pedido_prod_fecha_emision']) ?>)
            </option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($pedidosPendientes)): ?>
          <small class="text-muted">No hay pedidos PENDIENTE sin orden asignada.</small>
          <?php endif; ?>
        </div>
      </div>

      <div id="info-pedido" class="mb-3" style="display:none;">
        <div class="alert alert-info py-2 mb-2" id="resumen-pedido"></div>
      </div>

      <div class="table-responsive mb-3">
        <table class="table table-bordered" id="tabla-detalle-pedido">
          <thead class="table-dark"><tr><th>#</th><th>Código</th><th>Producto</th><th>Cantidad</th></tr></thead>
          <tbody id="tbody-detalle-pedido">
            <tr><td colspan="4" class="text-center text-muted">Seleccione un pedido para ver el detalle.</td></tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-end">
        <button type="submit" name="Guardar" class="btn btn-success me-2" id="btn-guardar-orden" disabled>Guardar orden</button>
        <a href="view.php" class="btn btn-warning">Cancelar</a>
      </div>
    </form>
  </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const sel = document.getElementById('id_pedido_produccion');
  const tbody = document.getElementById('tbody-detalle-pedido');
  const info = document.getElementById('info-pedido');
  const resumen = document.getElementById('resumen-pedido');
  const btnGuardar = document.getElementById('btn-guardar-orden');

  sel.addEventListener('change', () => {
    const pedId = parseInt(sel.value, 10);
    btnGuardar.disabled = true;
    if (!pedId) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Seleccione un pedido para ver el detalle.</td></tr>';
      info.style.display = 'none';
      return;
    }
    tbody.innerHTML = '<tr><td colspan="4" class="text-center"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    fetch('get_pedido_info.php?ped_id=' + pedId)
      .then(r => r.json())
      .then(data => {
        if (!data.success) {
          tbody.innerHTML = '<tr><td colspan="4" class="text-danger text-center">' + (data.error || 'Error') + '</td></tr>';
          info.style.display = 'none';
          return;
        }
        const p = data.pedido;
        resumen.textContent = 'Pedido #' + p.id_pedido_produccion + ' — ' + p.tipo_pedido_descri
          + ' | Fecha: ' + p.fecha_emision + ' | ' + p.username + ' / ' + p.descripcion_sucursal;
        info.style.display = 'block';
        tbody.innerHTML = '';
        data.detalle.forEach((item, i) => {
          const tr = document.createElement('tr');
          tr.innerHTML = '<td>' + (i + 1) + '</td><td>' + item.codigo + '</td><td>' + item.nombre_producto + '</td><td>' + item.cantidad + '</td>';
          tbody.appendChild(tr);
        });
        btnGuardar.disabled = false;
      })
      .catch(() => {
        tbody.innerHTML = '<tr><td colspan="4" class="text-danger text-center">Error al cargar el pedido</td></tr>';
      });
  });

  document.getElementById('form-orden-add').addEventListener('submit', e => {
    if (!parseInt(sel.value, 10)) {
      e.preventDefault();
      alert('Seleccione un pedido de producción.');
    }
  });
});
</script>

<?php
elseif (isset($_GET['form_orden_prod'], $_GET['form'], $_GET['orden_id']) && $_GET['form'] === 'edit'):
    $ordenId = (int)$_GET['orden_id'];
    $stCab = $pdoForm->prepare("
        SELECT op.*, pp.id_pedido_produccion, tp.tipo_pedido_descri,
               u.username, to_char(pp.pedido_prod_fecha_emision, 'YYYY-MM-DD') AS pedido_fecha
        FROM orden_produccion op
        JOIN pedido_produccion pp ON pp.id_pedido_produccion = op.id_pedido_produccion
        JOIN tipo_pedido tp ON tp.id_tipo_pedido = pp.id_tipo_pedido
        JOIN usuarios u ON u.id_usuario = op.id_usuario
        WHERE op.orden_id = :id
    ");
    $stCab->execute([':id' => $ordenId]);
    $cabecera = $stCab->fetch();
    if (!$cabecera || strtoupper($cabecera['orden_prod_estado']) !== 'PENDIENTE') {
        header('Location: view.php?alert=5');
        exit;
    }
    $stDet = $pdoForm->prepare("
        SELECT d.producto_id, d.orden_prod_cantidad, d.cantidad_pendiente, p.producto_descri
        FROM orden_detalle_produccion d
        JOIN productos p ON p.producto_id = d.producto_id
        WHERE d.orden_id = :id
        ORDER BY p.producto_descri
    ");
    $stDet->execute([':id' => $ordenId]);
    $detalles = $stDet->fetchAll();
    $fechaEntrega = $cabecera['orden_prod_fecha_entrega'] ?? '';
    if ($fechaEntrega && strpos($fechaEntrega, ' ') !== false) {
        $fechaEntrega = substr($fechaEntrega, 0, 10);
    }
?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-edit"></i> Editar fecha de entrega — Orden #<?= $ordenId ?></h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="view.php">Órdenes de producción</a></li>
    <li class="breadcrumb-item active">Editar fecha de entrega</li>
  </ol>

  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=update" method="POST" id="form-orden-edit">
      <input type="hidden" name="orden_id" value="<?= $ordenId ?>">

      <div class="row mb-3">
        <div class="col-md-2"><label>Fecha emisión</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($cabecera['orden_prod_fecha']) ?>" readonly></div>
        <div class="col-md-2"><label>Estado</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($cabecera['orden_prod_estado']) ?>" readonly></div>
        <div class="col-md-2"><label>Pedido N°</label>
          <input type="text" class="form-control" value="<?= (int)$cabecera['id_pedido_produccion'] ?>" readonly></div>
        <div class="col-md-3"><label>Usuario</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($cabecera['username']) ?>" readonly></div>
        <div class="col-md-3">
          <label for="orden_prod_fecha_entrega">Fecha entrega prevista <span class="text-danger">*</span></label>
          <input type="date" class="form-control" name="orden_prod_fecha_entrega" id="orden_prod_fecha_entrega"
                 value="<?= htmlspecialchars($fechaEntrega) ?>" min="<?= htmlspecialchars($fechaHoy) ?>" required>
        </div>
      </div>

      <div class="alert alert-info py-2">
        Pedido #<?= (int)$cabecera['id_pedido_produccion'] ?> — <?= htmlspecialchars($cabecera['tipo_pedido_descri']) ?>
        (<?= htmlspecialchars($cabecera['pedido_fecha']) ?>)
      </div>

      <p class="text-muted small mb-2">El detalle de productos no se modifica en esta pantalla; solo la fecha de entrega prevista.</p>

      <div class="table-responsive mb-3">
        <table class="table table-bordered">
          <thead class="table-dark"><tr><th>#</th><th>Código</th><th>Producto</th><th>Cantidad</th><th>Pendiente</th></tr></thead>
          <tbody>
            <?php if ($detalles): $i = 1; foreach ($detalles as $d): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><?= (int)$d['producto_id'] ?></td>
              <td><?= htmlspecialchars($d['producto_descri']) ?></td>
              <td><?= (int)$d['orden_prod_cantidad'] ?></td>
              <td><?= (int)$d['cantidad_pendiente'] ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5" class="text-center">Sin detalle.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-end">
        <button type="submit" name="Guardar" class="btn btn-success me-2">Guardar fecha</button>
        <a href="view.php" class="btn btn-warning">Cancelar</a>
      </div>
    </form>
  </div></div>
</div>
<?php else: ?>
<script>window.location.href = 'view.php';</script>
<?php endif; ?>
