<?php
require_once realpath(__DIR__ . '/../../config/database.php');
require_once __DIR__ . '/etapas_helper.php';

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

$modoEdit = isset($_GET['form_etapa'], $_GET['form'], $_GET['producto_id'])
    && $_GET['form_etapa'] === 'edit' && $_GET['form'] === 'edit';
$productoEditId = $modoEdit ? (int)$_GET['producto_id'] : 0;
$etapasInit = [];
$productoEdit = null;

if ($modoEdit && $productoEditId > 0) {
    $st = $pdoForm->prepare('SELECT producto_id, producto_descripcion FROM productos WHERE producto_id = :id');
    $st->execute([':id' => $productoEditId]);
    $productoEdit = $st->fetch();
    if (!$productoEdit) {
        die('Producto no encontrado.');
    }
    $etapasInit = cargarEtapasProducto($pdoForm, $productoEditId, true);
    if (empty($etapasInit)) {
        die('No hay ruta activa para editar.');
    }
    foreach ($etapasInit as &$e) {
        $e['en_uso'] = etapaEnUso($pdoForm, (int)$e['etapa_id']);
    }
    unset($e);
}

if (!$modoEdit) {
    $productos = $pdoForm->query("
        SELECT p.producto_id, p.producto_descripcion
        FROM productos p
        WHERE UPPER(TRIM(p.producto_estado)) = 'ACTIVO'
          AND NOT EXISTS (
              SELECT 1 FROM etapa_produccion ep
              WHERE ep.producto_id = p.producto_id AND UPPER(TRIM(ep.etapa_estado)) = 'ACTIVA'
          )
        ORDER BY p.producto_descripcion
    ")->fetchAll();
}

$jsEtapas = json_encode($etapasInit);
$titulo = $modoEdit ? 'Editar ruta de etapas' : 'Nueva ruta de etapas';
$action = $modoEdit ? 'update' : 'insert';
?>

<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-route"></i> <?= htmlspecialchars($titulo) ?></h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="view.php">Etapas de producción</a></li>
    <li class="breadcrumb-item active"><?= $modoEdit ? 'Editar' : 'Nuevo' ?></li>
  </ol>

  <?php if (!$modoEdit && empty($productos)): ?>
  <div class="alert alert-warning">
    Todos los productos activos ya tienen ruta definida, o no hay productos activos.
  </div>
  <a href="view.php" class="btn btn-secondary">Volver</a>
  <?php else: ?>

  <div class="card shadow mb-4"><div class="card-body">
    <form action="proses.php?act=<?= $action ?>" method="POST" id="form-etapas">
      <div class="row mb-3">
        <div class="col-md-3"><label>Fecha</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($fechaHoy) ?>" readonly></div>
        <div class="col-md-3"><label>Usuario</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($usr['username']) ?>" readonly></div>
        <div class="col-md-6">
          <label>Producto <span class="text-danger">*</span></label>
          <?php if ($modoEdit): ?>
          <input type="hidden" name="producto_id" value="<?= (int)$productoEditId ?>">
          <input type="text" class="form-control" value="<?= htmlspecialchars($productoEdit['producto_descripcion']) ?>" readonly>
          <?php else: ?>
          <select class="form-control" name="producto_id" id="producto_id" required>
            <option value="">Seleccione producto</option>
            <?php foreach ($productos as $p): ?>
            <option value="<?= (int)$p['producto_id'] ?>"><?= htmlspecialchars($p['producto_descripcion']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php endif; ?>
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="font-weight-bold mb-0">Pasos de la ruta</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-fila"><i class="fas fa-plus"></i> Agregar paso</button>
      </div>

      <?php if ($modoEdit): ?>
      <p class="small text-muted">Etapas ya usadas en control de producción solo permiten editar observaciones.</p>
      <?php endif; ?>

      <div class="table-responsive mb-3">
        <table class="table table-bordered table-sm" id="tabla-etapas">
          <thead class="table-dark">
            <tr>
              <th style="width:50px">Sec.</th>
              <th style="width:120px">Código</th>
              <th>Nombre</th>
              <th>Procedimiento</th>
              <th style="width:80px">Min.</th>
              <th style="width:120px">Obs.</th>
              <th style="width:40px"></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <input type="hidden" name="etapas" id="etapas-json" value="[]">

      <div class="d-flex justify-content-end">
        <button type="submit" name="Guardar" class="btn btn-success mr-2">Guardar ruta</button>
        <a href="view.php" class="btn btn-warning">Cancelar</a>
      </div>
    </form>
  </div></div>
  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const tbody = document.querySelector('#tabla-etapas tbody');
  const hid = document.getElementById('etapas-json');
  const form = document.getElementById('form-etapas');
  if (!tbody || !form) return;

  let filas = <?= $jsEtapas ?>.map(e => ({
    etapa_id: e.etapa_id || 0,
    etapa_secuencia: e.etapa_secuencia,
    etapa_descri: e.etapa_descri || '',
    etapa_nombre: e.etapa_nombre,
    etapa_procedimiento: e.etapa_procedimiento,
    etapa_tiempo_estimado: e.etapa_tiempo_estimado || '',
    etapa_observaciones: e.etapa_observaciones || '',
    en_uso: !!e.en_uso
  }));

  if (filas.length === 0) {
    [
      { seq: 1, nom: 'Preparación', proc: 'Mise en place: porcionar insumos y preparar estación.' },
      { seq: 2, nom: 'Cocción', proc: 'Cocinar / freír según ficha técnica del producto.' },
      { seq: 3, nom: 'Armado', proc: 'Montar el producto según estándar de la hamburguesería.' },
      { seq: 4, nom: 'Empaque', proc: 'Envolver, etiquetar y dejar en zona de despacho.' }
    ].forEach(t => filas.push({
      etapa_id: 0, etapa_secuencia: t.seq, etapa_descri: '', etapa_nombre: t.nom,
      etapa_procedimiento: t.proc, etapa_tiempo_estimado: '', etapa_observaciones: '', en_uso: false
    }));
  }

  const render = () => {
    tbody.innerHTML = '';
    filas.forEach((f, idx) => {
      const tr = document.createElement('tr');
      const ro = f.en_uso;
      tr.innerHTML = `
        <td><input type="number" class="form-control form-control-sm inp-seq" data-idx="${idx}" value="${f.etapa_secuencia}" min="1" ${ro ? 'readonly' : ''}></td>
        <td><input type="text" class="form-control form-control-sm inp-cod" data-idx="${idx}" value="${f.etapa_descri || ''}" maxlength="30" ${ro ? 'readonly' : ''} placeholder="Auto"></td>
        <td><input type="text" class="form-control form-control-sm inp-nom" data-idx="${idx}" value="${f.etapa_nombre}" maxlength="30" ${ro ? 'readonly' : ''} required></td>
        <td><textarea class="form-control form-control-sm inp-proc" data-idx="${idx}" rows="2" ${ro ? 'readonly' : ''} required>${f.etapa_procedimiento}</textarea></td>
        <td><input type="number" class="form-control form-control-sm inp-min" data-idx="${idx}" value="${f.etapa_tiempo_estimado}" min="0" ${ro ? 'readonly' : ''}></td>
        <td><input type="text" class="form-control form-control-sm inp-obs" data-idx="${idx}" value="${f.etapa_observaciones || ''}" ${ro ? '' : ''}></td>
        <td class="text-center">${ro ? '<span class="badge badge-warning" title="En uso">🔒</span>' : `<button type="button" class="btn btn-danger btn-sm btn-del" data-idx="${idx}"><i class="fas fa-trash"></i></button>`}</td>`;
      tbody.appendChild(tr);
    });
    bindEvents();
  };

  const syncFromDom = () => {
    tbody.querySelectorAll('tr').forEach((tr, idx) => {
      if (!filas[idx]) return;
      filas[idx].etapa_secuencia = parseInt(tr.querySelector('.inp-seq')?.value, 10) || 1;
      filas[idx].etapa_descri = tr.querySelector('.inp-cod')?.value.trim() || '';
      filas[idx].etapa_nombre = tr.querySelector('.inp-nom')?.value.trim() || '';
      filas[idx].etapa_procedimiento = tr.querySelector('.inp-proc')?.value.trim() || '';
      const min = tr.querySelector('.inp-min')?.value;
      filas[idx].etapa_tiempo_estimado = min !== '' ? parseInt(min, 10) : '';
      filas[idx].etapa_observaciones = tr.querySelector('.inp-obs')?.value.trim() || '';
    });
  };

  const bindEvents = () => {
    tbody.querySelectorAll('.btn-del').forEach(btn => {
      btn.addEventListener('click', () => {
        const i = parseInt(btn.dataset.idx, 10);
        filas.splice(i, 1);
        render();
      });
    });
  };

  document.getElementById('btn-add-fila')?.addEventListener('click', () => {
    syncFromDom();
    const nextSeq = filas.length ? Math.max(...filas.map(f => f.etapa_secuencia)) + 1 : 1;
    filas.push({
      etapa_id: 0, etapa_secuencia: nextSeq, etapa_descri: '', etapa_nombre: '',
      etapa_procedimiento: '', etapa_tiempo_estimado: '', etapa_observaciones: '', en_uso: false
    });
    render();
  });

  form.addEventListener('submit', e => {
    syncFromDom();
    if (filas.length === 0) { e.preventDefault(); alert('Agregue al menos una etapa.'); return; }
    for (const f of filas) {
      if (!f.etapa_nombre || !f.etapa_procedimiento) {
        e.preventDefault(); alert('Complete nombre y procedimiento en cada paso.'); return;
      }
    }
    hid.value = JSON.stringify(filas);
  });

  render();
});
</script>
