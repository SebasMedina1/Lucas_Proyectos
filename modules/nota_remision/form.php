<?php
// Verificar sesión
if (!isset($_SESSION['username'])) { die('Sesión expirada.'); }

$modoEdicion = (isset($_GET['nueva_nota']) && ($_GET['form'] ?? '') === 'edit');
$idNotaEditar = $modoEdicion ? (int)($_GET['id'] ?? 0) : 0;

if ((isset($_GET['nueva_nota']) && ($_GET['form'] ?? '') === 'add') || $modoEdicion) { ?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800">
    <i class="fas fa-truck-loading"></i> <?= $modoEdicion ? 'Editar' : 'Registrar' ?> Nota de Remisión (Compra)
  </h1>

  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
    <li class="breadcrumb-item"><a href="view.php">Notas de Remisión</a></li>
    <li class="breadcrumb-item active"><?= $modoEdicion ? 'Editar Nota de Remisión' : 'Nueva Nota de Remisión' ?></li>
  </ol>

  <?php
  try {
    require "../../config/database.php";
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    date_default_timezone_set('America/Asuncion');
    $hoy  = date('Y-m-d');
    $hora = date('H:i');

    // Usuario + sucursal
    $stU = $pdo->prepare("
      SELECT u.id_usuario, u.username, u.id_sucursal, s.descripcion_sucursal
      FROM usuarios u
      JOIN sucursales s ON s.id_sucursal = u.id_sucursal
      WHERE u.username = :u
      LIMIT 1
    ");
    $stU->execute([':u' => $_SESSION['username']]);
    $usr = $stU->fetch();
    if (!$usr) throw new Exception('No se encontró el usuario logueado.');

    $id_usuario       = (int)$usr['id_usuario'];
    $id_sucursal      = (int)$usr['id_sucursal'];
    $sucursal_nombre  = $usr['descripcion_sucursal'];
    $username         = $usr['username'];

    // Si es edición, cargar datos de la NR
    $nrData = null;
    $nrDetalle = [];
    if ($modoEdicion && $idNotaEditar > 0) {
      // Verificar si las columnas timbrado y vencimiento_timbrado existen
      $tieneTimbrado = false;
      try {
        $checkCol = $pdo->query("
          SELECT COUNT(*) 
          FROM information_schema.columns 
          WHERE table_schema = 'public' 
            AND table_name = 'nota_remision_compra' 
            AND column_name IN ('timbrado', 'vencimiento_timbrado')
        ");
        $tieneTimbrado = ($checkCol->fetchColumn() == 2);
      } catch (Exception $e) {
        // Si falla la verificación, asumimos que no existen
        $tieneTimbrado = false;
      }
      
      // Construir la consulta dinámicamente según las columnas disponibles
      if ($tieneTimbrado) {
        $sql = "
          SELECT 
            nr.id_nota_remision,
            nr.nota_fecha,
            nr.nota_remision_nro,
            nr.nota_estado,
            nr.id_orden_compra,
            nr.id_proveedor,
            nr.conductor_id,
            nr.vehiculo_id,
            nr.deposito_id,
            nr.timbrado,
            nr.vencimiento_timbrado,
            oc.id_orden_compra AS oc_id,
            oc.orden_estado AS oc_estado
          FROM nota_remision_compra nr
          LEFT JOIN orden_de_compra oc ON oc.id_orden_compra = nr.id_orden_compra
          WHERE nr.id_nota_remision = :id
          LIMIT 1
        ";
      } else {
        $sql = "
          SELECT 
            nr.id_nota_remision,
            nr.nota_fecha,
            nr.nota_remision_nro,
            nr.nota_estado,
            nr.id_orden_compra,
            nr.id_proveedor,
            nr.conductor_id,
            nr.vehiculo_id,
            nr.deposito_id,
            NULL AS timbrado,
            NULL AS vencimiento_timbrado,
            oc.id_orden_compra AS oc_id,
            oc.orden_estado AS oc_estado
          FROM nota_remision_compra nr
          LEFT JOIN orden_de_compra oc ON oc.id_orden_compra = nr.id_orden_compra
          WHERE nr.id_nota_remision = :id
          LIMIT 1
        ";
      }
      
      $stNr = $pdo->prepare($sql);
      $stNr->execute([':id' => $idNotaEditar]);
      $nrData = $stNr->fetch(PDO::FETCH_ASSOC);
      
      if (!$nrData) {
        die("Nota de Remisión no encontrada.");
      }
      
      // Validar que esté en estado EMITIDA y no conciliada
      $estadoNR = strtoupper(trim($nrData['nota_estado']));
      $idFacturaCompra = (int)($nrData['id_factura_compra'] ?? 0);
      
      if ($estadoNR !== 'EMITIDA') {
        die("Solo se pueden editar Notas de Remisión en estado EMITIDA.");
      }
      
      if ($idFacturaCompra > 0) {
        $stFac = $pdo->prepare("SELECT 1 FROM factura_compra WHERE id_factura_compra=:id LIMIT 1");
        $stFac->execute([':id'=>$idFacturaCompra]);
        if ($stFac->fetch()) {
          die("La Nota de Remisión ya está conciliada con una Factura de Compra; no se puede editar.");
        }
      }
      
      // Cargar detalle de la NR (consulta optimizada)
      $stDet = $pdo->prepare("
        SELECT 
          nrd.id_materia_prima,
          nrd.nota_cantidad AS cantidad,
          COALESCE(mp.materia_prima_descripcion, 'ID ' || nrd.id_materia_prima::text) AS producto_descripcion,
          COALESCE(smp.deposito_id, 0) AS deposito_id,
          COALESCE(d.deposito_descri, 'Sin depósito') AS deposito_descri
        FROM nota_remision_detalle_compra nrd
        LEFT JOIN materia_prima mp ON mp.id_materia_prima = nrd.id_materia_prima
        LEFT JOIN stock_materia_prima smp ON smp.id_materia_prima = nrd.id_materia_prima
        LEFT JOIN deposito d ON d.deposito_id = smp.deposito_id
        WHERE nrd.id_nota_remision = :id
        ORDER BY COALESCE(mp.materia_prima_descripcion, '')
        LIMIT 1000
      ");
      $stDet->execute([':id' => $idNotaEditar]);
      $nrDetalle = $stDet->fetchAll(PDO::FETCH_ASSOC);
    }

    // Siguiente ID (visual) - solo para modo add
    $nextId = $modoEdicion ? $idNotaEditar : (int)$pdo->query("SELECT COALESCE(MAX(id_nota_remision),0)+1 FROM nota_remision_compra")->fetchColumn();

    // Órdenes de compra (EMITIDA según especificación punto 7)
    // En modo edición, incluir la OC de la NR actual
    $ocIdFiltro = $modoEdicion && $nrData ? (int)$nrData['id_orden_compra'] : 0;
    $sqlOcs = "
      SELECT DISTINCT oc.id_orden_compra   AS id,
             oc.orden_estado      AS estado,
             oc.orden_fecha       AS fecha_oc,
             pr.razon_social      AS proveedor
      FROM orden_de_compra oc
      JOIN proveedor pr ON pr.id_proveedor = oc.id_proveedor
      WHERE oc.orden_estado = 'EMITIDA'
        AND (
          oc.id_orden_compra = :oc_actual
          OR NOT EXISTS (
            SELECT 1
            FROM factura_compra fc
            JOIN nota_remision_compra nr ON nr.id_factura_compra = fc.id_factura_compra
            WHERE fc.id_orden_compra = oc.id_orden_compra
              AND nr.nota_estado <> 'ANULADO'
              AND (:modo_edit = false OR nr.id_nota_remision != :nr_id)
          )
        )
      ORDER BY oc.id_orden_compra DESC
    ";
    $stOcs = $pdo->prepare($sqlOcs);
    $stOcs->execute([
      ':oc_actual' => $ocIdFiltro,
      ':modo_edit' => $modoEdicion,
      ':nr_id' => $idNotaEditar
    ]);
    $ocs = $stOcs->fetchAll(PDO::FETCH_ASSOC);

    // Conductores
    $conductores = $pdo->query("
      SELECT conductor_id, conductor_nombre, conductor_apellido
      FROM conductores
      ORDER BY conductor_nombre, conductor_apellido
    ")->fetchAll();

    // Vehículos (columnas reales según BD: vehiculo_id, vehiculo_marca, vehiculo_ano, vehiculo_color)
    $vehiculos = $pdo->query("
      SELECT vehiculo_id, vehiculo_marca, vehiculo_ano, vehiculo_color
      FROM vehiculos
      ORDER BY vehiculo_marca, vehiculo_ano
    ")->fetchAll();

    // Depósitos ya no se cargan aquí, se obtienen desde stock_materia_prima por producto

  } catch (Throwable $e) { die("Error: ".$e->getMessage()); }
  ?>

  <div class="card shadow mb-4">
    <div class="card-body">
      <form id="form-nr" action="proses.php?act=<?= $modoEdicion ? 'update_nr' : 'insert_nr' ?>" method="POST" autocomplete="off">
        <?php if ($modoEdicion): ?>
          <input type="hidden" name="id_nota_remision" value="<?= $idNotaEditar ?>">
        <?php endif; ?>
        <!-- Contexto -->
        <div class="row mb-3">

          <div class="col-md-2">
            <label class="form-label">Fecha</label>
            <input type="date" class="form-control" value="<?= htmlspecialchars($hoy) ?>" readonly>
          </div>

          <div class="col-md-2">
            <label class="form-label">Hora</label>
            <input type="time" class="form-control" value="<?= htmlspecialchars($hora) ?>" readonly>
          </div>
          <div class="col-md-2">
            <label class="form-label">NR ID</label>
            <input class="form-control" value="<?= $nextId ?>" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label">Usuario</label>
            <input class="form-control" value="<?= htmlspecialchars($username) ?>" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label">Sucursal</label>
            <input class="form-control" value="<?= htmlspecialchars($sucursal_nombre) ?>" readonly>
          </div>
          <input type="hidden" name="id_usuario"  value="<?= $id_usuario ?>">
          <input type="hidden" name="id_sucursal" value="<?= $id_sucursal ?>">
        </div>

        <!-- Orden/Proveedor/Logística -->
        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Orden de Compra</label>
            <select id="id_orden_compra" name="id_orden_compra" class="form-control" required <?= $modoEdicion ? 'disabled' : '' ?>>
              <option value="">— Seleccione una OC —</option>
              <?php foreach ($ocs as $oc): ?>
                <?php 
                  $fechaOc = htmlspecialchars(date('Y-m-d', strtotime($oc['fecha_oc'])));
                  $selected = ($modoEdicion && $nrData && (int)$oc['id'] === (int)$nrData['id_orden_compra']) ? 'selected' : '';
                ?>
                <option value="<?= $oc['id'] ?>" data-fecha="<?= $fechaOc ?>" <?= $selected ?>>
                  #<?= $oc['id'] ?> — <?= htmlspecialchars($oc['proveedor']) ?> — <?= htmlspecialchars($oc['estado']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if ($modoEdicion): ?>
              <input type="hidden" name="id_orden_compra" value="<?= htmlspecialchars($nrData['id_orden_compra'] ?? '') ?>">
            <?php endif; ?>
            <small class="text-muted">Solo OC en estado EMITIDA.<?= $modoEdicion ? ' (No editable)' : '' ?></small>
          </div>

          <div class="col-md-2">
            <label class="form-label">Fecha Nota Remisión</label>
            <input type="date" class="form-control" id="fecha_remision" name="fecha_remision"
                   value="<?= $modoEdicion && $nrData ? htmlspecialchars($nrData['nota_fecha']) : htmlspecialchars($hoy) ?>" 
                   required max="<?= htmlspecialchars($hoy) ?>" <?= $modoEdicion ? 'readonly' : 'disabled' ?>>
          </div>

          <div class="col-md-3">
            <label class="form-label">N° Remisión (Legal)</label>
            <!-- Acepta 13 dígitos o EEE-PPP-NNNNNNN -->
            <input
              type="text"
              class="form-control"
              id="remision_nro"
              name="nota_remision_nro"
              placeholder="EEE-PPP-NNNNNNN"
              pattern="^(\d{3}-\d{3}-\d{7}|\d{13})$"
              maxlength="15"
              value="<?= $modoEdicion && $nrData ? htmlspecialchars($nrData['nota_remision_nro'] ?? '') : '' ?>"
              required
              <?= $modoEdicion ? 'readonly' : '' ?>
            />
            <small class="text-muted">Formato: EEE-PPP-NNNNNNN (o 13 dígitos).<?= $modoEdicion ? ' (No editable)' : '' ?></small>
          </div>

        </div>

        <div class="row mb-3">
          <div class="col-md-3">
            <label class="form-label">Timbrado</label>
            <input
              type="text"
              class="form-control"
              id="timbrado"
              name="timbrado"
              maxlength="8"
              pattern="^(?!0{8})\d{8}$"
              placeholder="8 dígitos"
              value="<?= $modoEdicion && $nrData ? htmlspecialchars($nrData['timbrado'] ?? '') : '' ?>"
            >
            <small class="text-muted">8 dígitos — opcional.</small>
          </div>
          <div class="col-md-3">
            <label class="form-label">Vencimiento del timbrado</label>
            <input type="date" class="form-control" id="timbrado_vto" name="timbrado_vto" 
                   value="<?= $modoEdicion && $nrData && $nrData['vencimiento_timbrado'] ? htmlspecialchars($nrData['vencimiento_timbrado']) : '' ?>">
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-3">
            <label class="form-label">Conductor</label>
            <select id="conductor_id" name="conductor_id" class="form-control" required>
              <option value="">— Seleccione —</option>
              <?php foreach ($conductores as $c): 
                $selected = ($modoEdicion && $nrData && (int)$c['conductor_id'] === (int)$nrData['conductor_id']) ? 'selected' : '';
              ?>
                <option value="<?= $c['conductor_id'] ?>" <?= $selected ?>>
                  <?= htmlspecialchars(trim($c['conductor_nombre'].' '.$c['conductor_apellido'])) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Vehículo</label>
            <select id="vehiculo_id" name="vehiculo_id" class="form-control" required>
              <option value="">— Seleccione —</option>
              <?php foreach ($vehiculos as $v): 
                $selected = ($modoEdicion && $nrData && (int)$v['vehiculo_id'] === (int)$nrData['vehiculo_id']) ? 'selected' : '';
              ?>
                <option value="<?= $v['vehiculo_id'] ?>" <?= $selected ?>>
                  <?= htmlspecialchars($v['vehiculo_marca']) ?>
                  <?= htmlspecialchars($v['vehiculo_ano'] ? ' '.$v['vehiculo_ano'] : '') ?>
                  — <?= htmlspecialchars($v['vehiculo_color']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Proveedor</label>
            <input type="text" class="form-control" id="proveedor_txt" readonly>
            <input type="hidden" name="id_proveedor" id="id_proveedor">
          </div>
          <div class="col-md-4">
            <label class="form-label">RUC del Proveedor</label>
            <input type="text" class="form-control" id="proveedor_ruc" readonly>
          </div>
          <div class="col-md-4">
            <label class="form-label">Estado OC</label>
            <input type="text" class="form-control" id="oc_estado" readonly>
          </div>
        </div>

        <!-- Detalle -->
        <div class="table-responsive mb-3">
          <table class="table table-bordered table-striped" id="tabla-oc-detalles">
            <thead>
              <tr>
                <th>Producto</th>
                <th style="width:120px">Cant. OC</th>
                <th style="width:160px">Cant. a Remitir</th>
                <th style="width:160px">Precio OC</th>
                <th>Depósito</th>
                <th>Subtotal</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

        <input type="hidden" name="productos" id="productos">
        <div class="row mb-3">
          <div class="col-md-3 ms-auto">
            <label class="form-label">Total Remisión</label>
            <input type="text" class="form-control" id="nr_total" readonly>
          </div>
        </div>

        <div class="d-flex justify-content-end">
          <button type="submit" id="btn-guardar" class="btn btn-success mx-2">Guardar</button>
          <button type="button" id="btn-cancelar" class="btn btn-warning mx-2">Cancelar</button>
          <a href="view.php" class="btn btn-danger mx-2">Cerrar</a>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal simple para mensajes -->
<div class="modal fade" id="msgModal" tabindex="-1" aria-labelledby="msgModalLbl" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title" id="msgModalLbl">Validación</h5>
      <button type="button" class="btn-close" data-dismiss="modal" aria-label="Cerrar"></button>
    </div>
    <div class="modal-body" id="msgModalBody">Mensaje…</div>
    <div class="modal-footer">
      <button type="button" class="btn btn-primary" data-dismiss="modal">Entendido</button>
    </div>
  </div></div>
</div>

<style>
/* Estilos para asegurar que el precio no parezca editable */
#tabla-oc-detalles td.precio {
  background-color: #f8f9fa !important;
  font-weight: 500;
  color: #495057;
  pointer-events: none;
  user-select: none;
  cursor: default;
}
#tabla-oc-detalles td.precio:hover {
  background-color: #e9ecef !important;
}
</style>

<script>
const toInt = v => parseInt(String(v).replace(/[^\d]/g,'')) || 0;
const fmtGs = n => (Number(n)||0).toLocaleString('es-PY');
function q(id){ return document.getElementById(id); }

async function cargarOC(ocId, excluirNrId = 0){
  // 1) Header
  const ch = await fetch(`get_oc_header.php?oc_id=${encodeURIComponent(ocId)}`);
  if (!ch.ok) throw new Error('HTTP header');
  const h = await ch.json();
  if (h.error) throw new Error(h.error);

  q('id_proveedor').value   = h.id_proveedor || '';
  q('proveedor_txt').value  = h.proveedor    || '';
  q('proveedor_ruc').value  = h.ruc          || '';
  q('oc_estado').value      = (h.orden_estado || '').toUpperCase();

  // 2) Detalle - si estamos editando, excluir esta NR del cálculo del saldo
  const urlDetalle = excluirNrId > 0 
    ? `get_oc_detalle.php?oc_id=${encodeURIComponent(ocId)}&excluir_nr_id=${encodeURIComponent(excluirNrId)}`
    : `get_oc_detalle.php?oc_id=${encodeURIComponent(ocId)}`;
  const rd  = await fetch(urlDetalle);
  if (!rd.ok) throw new Error('HTTP detalle');
  const det = await rd.json();
  if (det.error) throw new Error(det.error);

  const tbody = document.querySelector('#tabla-oc-detalles tbody');
  tbody.innerHTML = '';

  det.forEach(d=>{
    const cantOC = toInt(d.cantidad || 0);
    // En modo edición, el max es la cantidad total de la OC (no el saldo)
    // En modo creación, el max es el saldo disponible
    const saldoDisponible = toInt(d.saldo_disponible ?? d.cantidad ?? 0);
    const maxPermitido = excluirNrId > 0 ? cantOC : saldoDisponible; // Si editamos, max = cantidad OC total
    const precio = toInt(d.precio   || 0);
    const cantidadInicial = excluirNrId > 0 ? 0 : Math.min(saldoDisponible, cantOC); // En edición, se llenará después
    const sub    = cantidadInicial * precio;

    const etiqueta = d.producto ? d.producto : ('ID ' + (d.id_materia_prima || ''));
    const ivaTxt   = d.iva_descri || '';
    const idMateriaPrima = d.id_materia_prima || 0;

    const depositoNombre = d.deposito_descri || 'Sin depósito';
    const depositoId = d.deposito_id || 0;

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td data-id="${idMateriaPrima}">${etiqueta}</td>
      <td class="text-right">${cantOC}</td>
      <td style="width:160px">
        <input type="number" class="form-control cantidad"
               min="1" max="${maxPermitido}" value="${cantidadInicial}"
               data-max="${maxPermitido}"
               data-cantidad-oc="${cantOC}"
               data-deposito-id="${depositoId}">
      </td>
      <td class="text-right precio bg-light" data-precio="${precio}" style="user-select: none; cursor: default;" title="Precio de la Orden de Compra (no editable)">${fmtGs(precio)}</td>
      <td class="deposito" data-deposito-id="${depositoId}">${depositoNombre}</td>
      <td class="subtotal text-right">${fmtGs(sub)}</td>
      <td class="iva">${ivaTxt}</td>
    `;
    tbody.appendChild(tr);
  });

  recalcularRemision();
}

function recalcularRemision(){
  let tot = 0;
  document.querySelectorAll('#tabla-oc-detalles tbody tr').forEach(tr=>{
    const cant   = toInt(tr.querySelector('.cantidad')?.value || 0);
    // Leer precio del atributo data-precio (más confiable que textContent)
    const precioEl = tr.querySelector('.precio');
    const precio = toInt(precioEl?.dataset.precio || precioEl?.textContent || 0);
    const sub    = cant * precio;
    tr.querySelector('.subtotal').textContent = fmtGs(sub);
    tot += sub;
  });
  if (q('nr_total')) q('nr_total').value = fmtGs(tot);
}

// limitar cantidad y recalcular al tipear
document.addEventListener('input', e=>{
  if (e.target && e.target.id === 'remision_nro'){
    // máscara: EEE-PPP-NNNNNNN
    let v = e.target.value.replace(/\D/g,'').slice(0,13);
    if (v.length >= 7) v = v.slice(0,3)+'-'+v.slice(3,6)+'-'+v.slice(6);
    else if (v.length >= 4) v = v.slice(0,3)+'-'+v.slice(3);
    e.target.value = v;
  }
  if (e.target && e.target.id === 'timbrado'){
    e.target.value = e.target.value.replace(/\D/g,'').slice(0,8);
  }
  if (!e.target.classList.contains('cantidad')) return;
  const max = toInt(e.target.getAttribute('max') || '0');
  let v = toInt(e.target.value);
  if (v < 1) v = 1;
  if (max && v > max) v = max;
  e.target.value = v;
  recalcularRemision();
});


const fechaRemisionInput = q('fecha_remision');
const timbradoVtoInput = q('timbrado_vto');
if (fechaRemisionInput) {
  const hoyStr = new Date().toISOString().slice(0,10);
  fechaRemisionInput.max = hoyStr;
  <?php if (!$modoEdicion): ?>
  fechaRemisionInput.disabled = true;
  <?php endif; ?>
}
if (fechaRemisionInput && timbradoVtoInput) {
  // Función para validar y actualizar el min del timbrado
  function actualizarMinTimbrado() {
    const ref = fechaRemisionInput.value || '';
    if (ref) {
      timbradoVtoInput.min = ref;
      // Si la fecha de vencimiento es anterior a la fecha de remisión, corregirla
      if (timbradoVtoInput.value && timbradoVtoInput.value < ref) {
        timbradoVtoInput.value = ref;
      }
    } else {
      timbradoVtoInput.removeAttribute('min');
    }
  }
  
  // Actualizar cuando cambia la fecha de remisión
  fechaRemisionInput.addEventListener('change', actualizarMinTimbrado);
  
  // Validar cuando cambia la fecha de vencimiento del timbrado
  timbradoVtoInput.addEventListener('change', function() {
    const fechaRemision = fechaRemisionInput.value || '';
    const fechaVto = this.value || '';
    
    if (fechaRemision && fechaVto && fechaVto < fechaRemision) {
      alert('La fecha de vencimiento del timbrado no puede ser anterior a la fecha de emisión de la remisión.');
      this.value = fechaRemision;
      this.focus();
    }
  });
  
  // Establecer el min inicial si hay una fecha de remisión
  actualizarMinTimbrado();
}

// Cargar datos de la NR en modo edición
<?php if ($modoEdicion && $nrData && !empty($nrDetalle)): ?>
let cargaEdicionCompletada = false;
document.addEventListener('DOMContentLoaded', function(){
  if (cargaEdicionCompletada) return;
  const ocId = <?= (int)($nrData['id_orden_compra'] ?? 0) ?>;
  const nrId = <?= (int)($idNotaEditar ?? 0) ?>;
  if (ocId > 0) {
    const ocSelect = q('id_orden_compra');
    if (ocSelect) {
      ocSelect.value = ocId;
      cargaEdicionCompletada = true;
      
      // Cargar datos de la OC (excluyendo esta NR del cálculo del saldo)
      setTimeout(async () => {
        try {
          await cargarOC(ocId, nrId);
          
          // Pre-llenar cantidades del detalle de la NR después de cargar la OC
          setTimeout(() => {
            const tbody = document.querySelector('#tabla-oc-detalles tbody');
            const detalleNR = <?= json_encode($nrDetalle, JSON_UNESCAPED_UNICODE) ?>;
            
            if (tbody && detalleNR.length > 0) {
              detalleNR.forEach(det => {
                const tr = Array.from(tbody.querySelectorAll('tr')).find(r => {
                  const idMp = toInt(r.children[0].dataset.id || 0);
                  return idMp === det.id_materia_prima;
                });
                
                if (tr) {
                  const cantInput = tr.querySelector('.cantidad');
                  if (cantInput) {
                    const cantidadNR = toInt(det.cantidad || 0);
                    // El max ahora es la cantidad total de la OC, no el saldo
                    const maxCant = toInt(cantInput.dataset.cantidadOc || cantInput.getAttribute('max') || 0);
                    const cantidadFinal = Math.min(cantidadNR, maxCant > 0 ? maxCant : cantidadNR);
                    cantInput.value = cantidadFinal;
                    recalcularRemision();
                  }
                }
              });
            }
          }, 500);
        } catch(e) {
          console.error('Error al cargar datos de edición:', e);
          cargaEdicionCompletada = false;
        }
      }, 300);
    }
  }
});
<?php endif; ?>

// cargar al cambiar la OC
const ocSelect = q('id_orden_compra');
if (ocSelect) {
  ocSelect.addEventListener('change', async function(){
    <?php if ($modoEdicion): ?>
    // En modo edición, no permitir cambiar la OC
    if (this.value != <?= (int)($nrData['id_orden_compra'] ?? 0) ?>) {
      this.value = <?= (int)($nrData['id_orden_compra'] ?? 0) ?>;
      return;
    }
    <?php endif; ?>
    
    if (fechaRemisionInput) {
      if (!this.value) {
        fechaRemisionInput.disabled = true;
        fechaRemisionInput.value = '';
        if (timbradoVtoInput){
          timbradoVtoInput.value = '';
          timbradoVtoInput.removeAttribute('min');
        }
        q('id_proveedor').value = '';
        q('proveedor_txt').value = '';
        if (q('proveedor_ruc')) q('proveedor_ruc').value = '';
        q('oc_estado').value = '';
        const tbody = document.querySelector('#tabla-oc-detalles tbody');
        if (tbody) tbody.innerHTML = '';
        if (q('nr_total')) q('nr_total').value = '';
      } else {
        const fechaOc = this.options[this.selectedIndex]?.dataset.fecha || '';
        const hoy = new Date().toISOString().slice(0,10);
        fechaRemisionInput.disabled = false;
        fechaRemisionInput.min = fechaOc || '';
        fechaRemisionInput.max = hoy;
        if (fechaOc && fechaRemisionInput.value < fechaOc) fechaRemisionInput.value = fechaOc;
        if (fechaRemisionInput.value > hoy) fechaRemisionInput.value = hoy;
        if (fechaRemisionInput.value && timbradoVtoInput){
          timbradoVtoInput.min = fechaRemisionInput.value;
          // Validar que la fecha de vencimiento no sea anterior a la fecha de remisión
          if (timbradoVtoInput.value && timbradoVtoInput.value < fechaRemisionInput.value){
            alert('La fecha de vencimiento del timbrado no puede ser anterior a la fecha de emisión de la remisión.');
            timbradoVtoInput.value = fechaRemisionInput.value;
          }
        }
      }
    }

    if (!this.value) return;
    try { await cargarOC(this.value); }
    catch(e){ console.error(e); alert('No se pudieron cargar datos de la Orden de Compra.'); }
  });
}

// armar JSON productos al guardar (incluye depósito de cada ítem)
q('form-nr').addEventListener('submit', (ev)=>{
  const items = [];
  document.querySelectorAll('#tabla-oc-detalles tbody tr').forEach(tr=>{
    const idMateriaPrima = toInt(tr.children[0].dataset.id || 0);
    const cant   = toInt(tr.querySelector('.cantidad')?.value || 0);
    const maxCant = toInt(tr.querySelector('.cantidad')?.dataset.max || 0);
    const depositoId = toInt(tr.querySelector('.cantidad')?.dataset.depositoId || 0);
    if (idMateriaPrima > 0 && cant > 0) {
      if (cant > maxCant) {
        ev.preventDefault();
        alert(`La cantidad excede el saldo disponible (${maxCant}) para este producto.`);
        return false;
      }
      if (depositoId <= 0) {
        ev.preventDefault();
        alert(`El producto "${tr.children[0].textContent.trim()}" no tiene depósito asignado. Verifique el stock.`);
        return false;
      }
      items.push({ 
        id_materia_prima: idMateriaPrima, 
        cantidad: cant,
        deposito_id: depositoId
      });
    }
  });
  if (items.length === 0) {
    ev.preventDefault();
    alert('Debe ingresar al menos un ítem con cantidad > 0.');
    return false;
  }
  q('productos').value = JSON.stringify(items);
});

// Cancelar: limpia grilla y resetea selects básicos
q('btn-cancelar').addEventListener('click', ()=>{
  q('form-nr').reset();
  const tb = document.querySelector('#tabla-oc-detalles tbody');
  if (tb) tb.innerHTML = '';
  q('proveedor_txt').value = '';
  q('id_proveedor').value  = '';
  if (q('proveedor_ruc')) q('proveedor_ruc').value = '';
  q('oc_estado').value     = '';
  q('nr_total').value      = '';
  if (timbradoVtoInput) timbradoVtoInput.removeAttribute('min');
});
</script>

<?php } ?>
