<?php
// Verificar si existe el parámetro 'form' en la URL
if (!isset($_SESSION['username'])) { die('Sesión expirada.'); }

if (isset($_GET['nueva_nota']) && ($_GET['form'] ?? '') === 'add') { ?>
<!-- Modal Único -->
<div class="modal fade" id="reglaModal" tabindex="-1" aria-labelledby="reglaModalLbl" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title" id="reglaModalLbl">Validación</h5>
      <button type="button" class="btn-close" data-dismiss="modal" aria-label="Cerrar"></button>
    </div>
    <div class="modal-body" id="reglaModalMsg">Mensaje dinámico…</div>
    <div class="modal-footer">
      <button type="button" class="btn btn-primary" data-dismiss="modal">Entendido</button>
    </div>
  </div></div>
</div>

<?php if (isset($_GET['err']) && $_GET['err'] === 'limite_credito'):
  $facId    = (int)($_GET['fac'] ?? 0);
  $facTotal = (int)($_GET['total_fac'] ?? 0);
  $ya       = (int)($_GET['ya'] ?? 0);
  $disp     = (int)($_GET['disp'] ?? 0);
?>
  <div class="alert alert-danger" role="alert">
    <strong>Tope excedido:</strong> las Notas de Crédito para la factura #<?= $facId ?>
    no pueden superar su total.
    <br> Total factura: <b><?= number_format($facTotal,0,',','.') ?></b> Gs
    — Ya acreditado: <b><?= number_format($ya,0,',','.') ?></b> Gs
    — Disponible: <b><?= number_format($disp,0,',','.') ?></b> Gs.
  </div>
<?php endif; ?>

<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800">
    <i class="fas fa-plus-circle"></i> Registrar Nota (Crédito / Débito)
  </h1>

  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
    <li class="breadcrumb-item"><a href="view.php">Notas</a></li>
    <li class="breadcrumb-item active">Nueva Nota</li>
  </ol>

  <div class="card shadow mb-4">
    <div class="card-body">
      <form id="form-nota" action="proses.php?act=insert_nota" method="POST">
        <?php
        try {
          require "../../config/database.php";
          $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
          $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
          ]);

          $qNext = $pdo->query("SELECT COALESCE(MAX(id_nota_compra),0)+1 AS next_id FROM nota_compra");
          $codigo = (int)$qNext->fetchColumn();

          date_default_timezone_set('America/Asuncion');
          $hoy   = date('Y-m-d');
          $hora  = date('H:i');

          $username = $_SESSION['username'];
          $sqlUser = "
            SELECT u.id_usuario, u.username, u.id_sucursal, s.descripcion_sucursal
            FROM usuarios u
            JOIN sucursales s ON s.id_sucursal = u.id_sucursal
            WHERE u.username = :u
            LIMIT 1
          ";
          $stU = $pdo->prepare($sqlUser);
          $stU->execute([':u' => $username]);
          $usr = $stU->fetch();
          if (!$usr) throw new Exception('No se encontró el usuario logueado.');

          $id_usuario       = (int)$usr['id_usuario'];
          $id_sucursal      = (int)$usr['id_sucursal'];
          $sucursal_nombre  = $usr['descripcion_sucursal'];

          $stFact = $pdo->query("
            SELECT 
              f.id_factura_compra AS id,
              f.numero_factura    AS numero,
              f.fact_fecha_compra,
              f.fac_estado,
              pr.razon_social     AS proveedor
            FROM factura_compra f
            JOIN orden_de_compra oc ON oc.id_orden_compra = f.id_orden_compra
            JOIN proveedor pr       ON pr.id_proveedor    = oc.id_proveedor
            WHERE f.fac_estado IN ('EMITIDA')
              AND f.fact_fecha_compra IS NOT NULL
            ORDER BY f.fact_fecha_compra DESC, f.id_factura_compra DESC
          ");
          $facturas = $stFact->fetchAll();

        } catch (Throwable $e) { die("Error: ".$e->getMessage()); }
        ?>

        <!-- Contexto -->
        <div class="row mb-3">
          <div class="col-md-2">
            <label class="form-label">Fecha</label>
            <input type="date" class="form-control" id="nota_fecha" name="nota_fecha"
                   value="<?= htmlspecialchars($hoy) ?>" readonly>
          </div>
          <div class="col-md-2">
            <label class="form-label">Hora</label>
            <input type="time" class="form-control" id="nota_hora" name="nota_hora"
                   value="<?= htmlspecialchars($hora) ?>" readonly>
          </div>
          <div class="col-md-2">
            <label class="form-label">Nota ID</label>
            <input class="form-control" value="<?= $codigo ?>" readonly>
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

        <!-- Elegir Factura / Tipo / Motivo -->
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Factura</label>
            <select id="fact_id" name="fact_id" class="form-control" required>
              <option value="">— Seleccione factura —</option>
              <?php foreach ($facturas as $f):
                $label = '#'.$f['id'].' | '.$f['numero'].' | '.$f['proveedor'].' | '.substr($f['fac_estado'],0,10);
              ?>
                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Estados válidos: EMITIDA.</small>
          </div>
          <div class="col-md-3">
            <label class="form-label">Tipo de Nota</label>
            <select id="nota_tipo" name="nota_tipo" class="form-control" required>
              <option value="">— Seleccione —</option>
              <option value="CREDITO">Crédito</option>
              <option value="DEBITO">Débito</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Motivo</label>
            <select id="motivo_id" name="motivo_id" class="form-control" required>
              <option value="">— Seleccione motivo —</option>
            </select>
          </div>
        </div>

        <!-- Autocompletados desde la Factura -->
        <div class="row mb-3">
          <div class="col-md-2">
            <label class="form-label">Proveedor</label>
            <input type="text" class="form-control" id="proveedor_txt" readonly>
            <input type="hidden" name="id_proveedor" id="proveedor_id">
          </div>
          <div class="col-md-1">
            <label class="form-label">RUC</label>
            <input type="text" class="form-control" id="proveedor_ruc" readonly>
          </div>
          <div class="col-md-2">
            <label class="form-label">Condición / Plazo / Saldo</label>
            <input type="text" class="form-control" id="fact_info" readonly>
          </div>
          <div class="col-md-2">
            <label class="form-label">Total Factura</label>
            <input type="text" class="form-control" id="fact_total_display" readonly style="font-weight: bold; color: #28a745;">
          </div>
          <div class="col-md-2">
            <label class="form-label">Costo adicional</label>
            <input type="numeric" class="form-control" id="costo_adicional" name="costo_adicional" readonly>
          </div>
          <div class="col-md-4">
            <label class="form-label">Descripción</label>
            <input type="text" class="form-control" id="descripcion" name="descripcion" readonly>
          </div>
        </div>

        <!-- guarda la emisión de la factura seleccionada -->
        <input type="hidden" id="fact_emision" name="fact_emision">

        <!-- Timbrado/Numero Nota y Vigencia -->
        <div class="row mb-3">
          <div class="col-md-3">
            <label class="form-label">N° Nota</label>
            <input type="text" class="form-control" id="nota_nro" name="nota_nro"
                   pattern="^\d{3}-\d{3}-\d{7}$" maxlength="15" placeholder="001-002-0001234" required>
            <small class="text-muted">Formato: EEE-PPP-NNNNNNN</small>
          </div>
          <div class="col-md-3">
            <label class="form-label">Timbrado</label>
            <input type="text" class="form-control" id="nota_timbrado" name="nota_timbrado"
                   pattern="^(?!0{8})\d{8}$" maxlength="8" placeholder="8 dígitos" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Fecha emisión de la Nota</label>
            <input type="date" class="form-control" id="nota_emision" name="nota_emision"
                   value="<?= htmlspecialchars($hoy) ?>" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Vencimiento del timbrado</label>
            <input type="date" class="form-control" id="nota_vto" name="nota_vto" disabled required>
          </div>
        </div>

        <!-- Detalle -->
        <div class="table-responsive mb-3">
          <table class="table table-bordered table-striped" id="tabla-factura-detalles">
            <thead>
              <tr>
                <th>Producto</th>
                <th style="width:120px">Cantidad</th>
                <th style="width:160px">Precio</th>
                <th>Subtotal</th>
                <th>IVA</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

        <input type="hidden" name="productos" id="productos">
        <input type="hidden" name="nota_total_num" id="nota_total_num">

        <div class="row mb-3">
          <div class="col-md-3 ms-auto">
            <label class="form-label">Total Nota</label>
            <input type="text" class="form-control" id="nota_total" readonly>
          </div>
        </div>

        <div class="d-flex justify-content-end">
          <button type="submit" id="btn-guardar" class="btn btn-success mx-2">Guardar</button>
          <input type="hidden" id="fac_total" name="fac_total" value="">
          <button type="button" id="btn-cancelar" class="btn btn-warning mx-2">Cancelar</button>
          <a href="view.php" class="btn btn-danger mx-2">Cerrar</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
/* ============================================================
   Utilidades
============================================================ */
const toInt  = v => parseInt(String(v).replace(/[^\d]/g,'')) || 0;
const fmtGs  = n => (Number(n)||0).toLocaleString('es-PY')+' Gs';
const todayYMD = () => new Date().toISOString().slice(0,10);
function q(id){ return document.getElementById(id); }
function setEnabled(id, on){
  const el=q(id); if(!el) return;
  el.disabled = !on;
  if (el.tagName !== 'SELECT') el.readOnly = !on && el.readOnly;
  el.tabIndex = on ? 0 : -1;
}
function focusId(id){ const el=q(id); if(el) el.focus(); }
function lockField(id){
  const el=q(id); if(!el) return;
  if (el.tagName==='SELECT'){
    if (el.name){
      let h=q(el.name+'__shadow');
      if(!h){ h=document.createElement('input'); h.type='hidden'; h.id=el.name+'__shadow'; h.name=el.name; el.insertAdjacentElement('afterend', h); }
      h.value = el.value;
    }
    el.disabled = true;
  }else{
    el.readOnly = true;
    el.classList.add('locked-input');
  }
}
function ensureHiddenForSelect(sel){
  if (!sel?.name) return;
  let h=q(sel.name+'__shadow');
  if(!h){ h=document.createElement('input'); h.type='hidden'; h.id=sel.name+'__shadow'; h.name=sel.name; sel.insertAdjacentElement('afterend',h); }
  h.value = sel.value;
}

/* ============================================================
   Flujo base y por caso
============================================================ */
const PRE_FLOW = ['fact_id','nota_tipo','motivo_id'];
function flowByCase(){
  const tipo   = q('nota_tipo')?.value || '';
  const motivo = q('motivo_id')?.value || '';
  let rest = ['descripcion','nota_nro','nota_timbrado','nota_emision','nota_vto'];
  if (tipo==='CREDITO' && motivo==='1'){
    rest = ['descripcion','nota_nro','nota_timbrado','nota_emision','nota_vto'];
  } else if (tipo==='CREDITO' && motivo==='2'){
    rest = ['descripcion','nota_nro','nota_timbrado','nota_emision','nota_vto','__habilitar_precio__'];
  } else if (tipo==='DEBITO' && motivo==='3'){
    rest = ['costo_adicional','descripcion','nota_nro','nota_timbrado','nota_emision','nota_vto','__deshabilitar_qty_precio__'];
  } else if (tipo==='DEBITO' && motivo==='4'){
    rest = ['descripcion','nota_nro','nota_timbrado','nota_emision','nota_vto','__habilitar_precio__'];
  }
  return PRE_FLOW.concat(rest);
}
function nextIdFrom(currentId){
  const flow = flowByCase();
  const i = flow.indexOf(currentId);
  if (i < 0) return null;
  for (let j = i + 1; j < flow.length; j++){
    const step = flow[j];
    if (step.startsWith('__')) continue;
    const el = q(step);
    if (el) return step;
  }
  return null;
}

/* ============================================================
   Estado inicial y reset
============================================================ */
function resetGuided(){
  ['fact_id','nota_tipo','motivo_id','descripcion','costo_adicional','nota_nro','nota_timbrado','nota_emision','nota_vto']
    .forEach((id,idx)=>{
      const el=q(id); if(!el) return;
      el.value = '';
      el.readOnly = false;
      el.disabled = true;
      el.classList.remove('locked-input');
      if (id==='fact_id') el.disabled = false;
    });

  ['proveedor_id','proveedor_txt','proveedor_ruc','fact_info','fact_total_display','nota_total','nota_total_num','fact_emision','fac_total']
    .forEach(id=>{ const el=q(id); if(el) el.value=''; });

  const tb = document.querySelector('#tabla-factura-detalles tbody');
  if (tb) tb.innerHTML='';

  const nEmi=q('nota_emision'), nVto=q('nota_vto');
  if (nEmi){ nEmi.disabled=true; nEmi.removeAttribute('min'); nEmi.removeAttribute('max'); }
  if (nVto){ nVto.disabled=true; nVto.removeAttribute('min'); nVto.removeAttribute('max'); }

  const ca = q('costo_adicional');
  if (ca){ ca.value='0'; ca.readOnly=true; ca.disabled=true; }

  updateGuardarState(true);
}
document.addEventListener('DOMContentLoaded', ()=>{ resetGuided(); focusId('fact_id'); });

/* ============================================================
   Enforcers numéricos y helpers
============================================================ */
function blockInvalidNumberKeys(e){ const invalid=['-','+','e','E','.',',',' ']; if (invalid.includes(e.key)) e.preventDefault(); }
function sanitizeOnInput(e){ e.target.value = e.target.value.replace(/[^\d]/g,''); }
function enforceMinZero(e){ const v = toInt(e.target.value); e.target.value = v<0 ? '0' : String(v); }
function setQtyEnabled(enable){
  document.querySelectorAll('#tabla-factura-detalles input.cantidad').forEach(inp=>{
    inp.disabled = !enable;
    if (!enable) inp.value = inp.dataset.origCant || inp.getAttribute('data-orig-cant') || inp.value;
  });
}
function setPriceEnabled(enable){
  document.querySelectorAll('#tabla-factura-detalles input.precio').forEach(inp=>{
    inp.disabled = !enable;
  });
  // Actualizar estado del botón guardar cuando se habilitan/deshabilitan los precios
  if (enable) {
    setTimeout(() => {
      recalcular();
      updateGuardarState();
    }, 100);
  }
}
function validarCantidadContraOriginal(inp){
  const orig = toInt(inp.dataset.origCant || inp.getAttribute('data-orig-cant') || '0');
  let val = toInt(inp.value);
  if (val <= 0) val = 1;
  const tipo = q('nota_tipo')?.value || '';
  const motivo = q('motivo_id')?.value || '';
  const requiereMenor = (tipo === 'CREDITO' && motivo === '2');

  if (orig > 0) {
    let maxPermit = orig;
    if (requiereMenor) {
      maxPermit = orig - 1;
    }
    if (maxPermit < 1 && requiereMenor) {
      val = 1; // se validará como violación posteriormente
    } else if (val > maxPermit) {
      val = maxPermit;
    }
  }
  inp.value = String(Math.max(val, 1));
}
function attachNumericEnforcers(){
  document.querySelectorAll('#tabla-factura-detalles input.cantidad, #tabla-factura-detalles input.precio')
    .forEach(inp=>{
      inp.addEventListener('keydown', blockInvalidNumberKeys);
      inp.addEventListener('input', sanitizeOnInput);
      inp.addEventListener('blur', enforceMinZero);
    });
}

/* ============================================================
   Reglas por caso (habilitación en grilla)
============================================================ */
function aplicarReglasCamposPorTipoMotivo(){
  const tipo   = q('nota_tipo')?.value || '';
  const motivo = q('motivo_id')?.value || '';
  setQtyEnabled(false);
  setPriceEnabled(false);

  if (tipo==='CREDITO' && motivo==='2'){ setQtyEnabled(true); setPriceEnabled(true); }
  else if (tipo==='DEBITO' && motivo==='3'){ setQtyEnabled(false); setPriceEnabled(false); }
  else if (tipo==='DEBITO' && motivo==='4'){ setPriceEnabled(true); }
  else { setQtyEnabled(false); setPriceEnabled(false); }

  updateGuardarState();
}

/* ============================================================
   Cálculo de totales
============================================================ */
function recalcular(){
  // Calcular el monto de la nota (diferencia entre valores actuales y originales)
  const tipoNota = q('nota_tipo')?.value || '';
  let montoItemsNota = 0;
  document.querySelectorAll('#tabla-factura-detalles tbody tr').forEach(tr=>{
    const cantInput = tr.querySelector('.cantidad');
    const precInput = tr.querySelector('.precio');
    // Leer valores actuales incluso si están disabled
    const c = cantInput ? toInt(cantInput.value || cantInput.getAttribute('value') || cantInput.dataset.origCant || '0') : 0;
    const p = precInput ? toInt(precInput.value || precInput.getAttribute('value') || precInput.dataset.origPrecio || '0') : 0;
    const subActual = c * p;
    
    // Leer valores originales
    const cOrig = toInt(cantInput?.dataset.origCant || cantInput?.getAttribute('data-orig-cant') || '0');
    const pOrig = toInt(precInput?.dataset.origPrecio || precInput?.getAttribute('data-orig-precio') || '0');
    const subOrig = cOrig * pOrig;
    
    // Calcular la diferencia (monto de la nota para este item)
    // Solo calcular diferencia si hay tipo de nota seleccionado
    if (tipoNota) {
      let diferencia;
      if (tipoNota === 'CREDITO') {
        // Para crédito, el monto es lo que se devuelve/acredita (positivo)
        diferencia = subOrig - subActual;
      } else if (tipoNota === 'DEBITO') {
        // Para débito, el monto es lo que se aumenta (positivo)
        diferencia = subActual - subOrig;
      } else {
        // Si no hay tipo, no hay diferencia
        diferencia = 0;
      }
      montoItemsNota += diferencia;
    }
    
    // Mostrar el subtotal actual
    const tdSub = tr.querySelector('.subtotal');
    if (tdSub) tdSub.textContent = fmtGs(subActual);
  });

  const costoHdr = toInt(q('costo_adicional')?.value || '0');
  // Solo calcular montoNota si hay tipo de nota seleccionado
  let montoNota = 0;
  if (tipoNota) {
    montoNota = montoItemsNota + Math.max(0, costoHdr);
  }

  // Obtener el total actual de la factura
  const facTotalActual = toInt(q('fac_total')?.value || '0');
  
  // Obtener el motivo (tipoNota ya está declarado arriba)
  const motivoId = q('motivo_id')?.value || '';
  
  // Calcular el total final para MOSTRAR: fac_total actual + cambios según tipo de nota
  let totalFinal = facTotalActual; // Por defecto, mostrar el total de la factura
  
  if (tipoNota === 'DEBITO') {
    // Débito: suma la diferencia al total de la factura
    // Para motivo 3 (COSTO ADICIONAL), solo se suma el costo adicional
    if (motivoId === '3') {
      totalFinal = facTotalActual + costoHdr;
    } else {
      totalFinal = facTotalActual + montoNota;
    }
  } else if (tipoNota === 'CREDITO') {
    // Crédito: resta la diferencia del total de la factura
    totalFinal = Math.max(0, facTotalActual - montoNota);
  }
  // Si no hay tipo seleccionado, mantener el total de la factura (facTotalActual)

  // Mostrar el total final en el campo visible
  if (q('nota_total')) q('nota_total').value = fmtGs(totalFinal);
  
  // IMPORTANTE: nota_total_num debe contener el MONTO DE LA NOTA (diferencia), no el total final
  // El backend calcula el total sumando los items enviados, así que necesitamos enviar
  // el monto que el backend va a calcular
  let montoParaBackend = montoNota;
  
  // Para DEBITO con motivo 3 (COSTO ADICIONAL), el backend solo suma el costo adicional
  // Para DEBITO con motivo 4 (DIFERENCIA), el backend calcula la diferencia internamente
  // pero necesita validar contra el total de los items nuevos que enviamos
  // Para DIFERENCIA, enviar la suma de los subtotales nuevos para validación
  // (el backend calculará la diferencia internamente)
  if (tipoNota === 'DEBITO' && motivoId === '4') {
    // Para DIFERENCIA, el backend calcula la diferencia internamente
    // Pero para validación, necesita el total de los items nuevos
    // Calcular la suma de los subtotales actuales
    let sumaSubtotalesNuevos = 0;
    document.querySelectorAll('#tabla-factura-detalles tbody tr').forEach(tr=>{
      const cantInput = tr.querySelector('.cantidad');
      const precInput = tr.querySelector('.precio');
      const c = cantInput ? toInt(cantInput.value || cantInput.getAttribute('value') || cantInput.dataset.origCant || '0') : 0;
      const p = precInput ? toInt(precInput.value || precInput.getAttribute('value') || precInput.dataset.origPrecio || '0') : 0;
      sumaSubtotalesNuevos += c * p;
    });
    // El backend calculará la diferencia, pero para validación enviamos el total de items nuevos
    montoParaBackend = sumaSubtotalesNuevos;
  } else if (tipoNota === 'DEBITO' && motivoId === '3') {
    // Para COSTO ADICIONAL, solo el costo adicional
    montoParaBackend = costoHdr;
  } else if (tipoNota === 'CREDITO') {
    // Para CREDITO con motivo 2 (AJUSTE PARCIAL), el backend calcula la diferencia internamente
    // igual que para DEBITO DIFERENCIA, así que necesitamos enviar el total de items nuevos
    // para que el backend pueda calcular la diferencia y validar
    if (motivoId === '2') {
      // Para AJUSTE PARCIAL, el backend calcula la diferencia (subOrig - subActual)
      // pero necesita el total de items nuevos para validar
      let sumaSubtotalesNuevos = 0;
      document.querySelectorAll('#tabla-factura-detalles tbody tr').forEach(tr=>{
        const cantInput = tr.querySelector('.cantidad');
        const precInput = tr.querySelector('.precio');
        const c = cantInput ? toInt(cantInput.value || cantInput.getAttribute('value') || cantInput.dataset.origCant || '0') : 0;
        const p = precInput ? toInt(precInput.value || precInput.getAttribute('value') || precInput.dataset.origPrecio || '0') : 0;
        sumaSubtotalesNuevos += c * p;
      });
      // El backend calculará la diferencia, pero para validación enviamos el total de items nuevos
      montoParaBackend = sumaSubtotalesNuevos;
    } else {
      // Para otros motivos de CREDITO (como motivo 1 - Anulación Total), usar montoNota
      montoParaBackend = montoNota;
    }
  }
  
  if (q('nota_total_num')) q('nota_total_num').value = montoParaBackend;
}

/* ============================================================
   ENTER para avanzar
============================================================ */
document.addEventListener('keydown', (e)=>{
  if (e.key!=='Enter') return;
  const t = e.target;
  if (!t.id) return;
  const flow = flowByCase();
  if (!flow.includes(t.id)) return;
  if (t.tagName==='SELECT' && !t.value) return;
  if (t.checkValidity && !t.checkValidity()) return;

  e.preventDefault();
  lockField(t.id);
  if (t.id==='nota_nro' || t.id==='nota_timbrado' || t.id==='costo_adicional' || t.id==='descripcion'){ recalcular(); }
  const markers = flow.filter(s=>s.startsWith('__'));
  if (markers.includes('__habilitar_precio__')){ setPriceEnabled(true); }
  if (markers.includes('__deshabilitar_qty_precio__')){ setQtyEnabled(false); setPriceEnabled(false); }
  const nxt = nextIdFrom(t.id);
  if (nxt){ setEnabled(nxt, true); focusId(nxt); }
});

/* ============================================================
   Descripción: habilitar por tipo
============================================================ */
function habilitarDescripcionPorTipo(){
  const tipo  = q('nota_tipo')?.value || '';
  const desc  = q('descripcion');
  if (!desc) return;
  desc.readOnly = false;
  desc.required = (tipo==='CREDITO');
}

/* ============================================================
   Rango de fechas por factura y encadenamiento
============================================================ */
(function(){
  const nEmi=q('nota_emision'), nVto=q('nota_vto'), facHid=q('fact_emision');
  if (!nEmi || !nVto) return;
  const HOY = todayYMD();
  const setRange = (el,min,max)=>{ if(min) el.min=min; else el.removeAttribute('min'); if(max) el.max=max; else el.removeAttribute('max'); };

  function aplicarRangosPorFactura(){
    const facEmi = facHid?.value || '';
    if (facEmi){
      setRange(nEmi, facEmi, HOY);
      if (nEmi.value && (nEmi.value<facEmi || nEmi.value>HOY)) nEmi.value='';
    }else{
      setRange(nEmi, null, HOY);
      nEmi.value='';
    }
    aplicarRangosPorEmision();
  }
  function aplicarRangosPorEmision(){
    const e = nEmi.value || '';
    if (e){
      setRange(nVto, e, null);
      if (nVto.value && nVto.value<e) nVto.value='';
    }else{
      nVto.value=''; setRange(nVto, null, null); nVto.disabled=true;
    }
  }

  nEmi.addEventListener('change', ()=>{
    const facEmi = facHid?.value || '';
    if (!nEmi.value || !facEmi) return;
    nEmi.setCustomValidity('');
    if (nEmi.value<facEmi || nEmi.value>HOY){
      nEmi.setCustomValidity(`Emisión de la nota: entre ${facEmi} y ${HOY}.`);
      nEmi.reportValidity(); nEmi.value=''; aplicarRangosPorEmision(); return;
    }
    aplicarRangosPorEmision();
    nVto.disabled=false;
    lockField('nota_emision');
    const nxt = nextIdFrom('nota_emision');
    if (nxt){ setEnabled(nxt,true); focusId(nxt); }
  });

  nVto.addEventListener('change', ()=>{
    if (!nVto.value || !nEmi.value) return;
    nVto.setCustomValidity('');
    if (nVto.value < nEmi.value){
      nVto.setCustomValidity(`Vencimiento: debe ser ≥ ${nEmi.value}.`);
      nVto.reportValidity(); nVto.value=''; return;
    }
    lockField('nota_vto');
    const nxt = nextIdFrom('nota_vto');
    if (nxt){ setEnabled(nxt,true); focusId(nxt); }
  });

  window.__aplicarRangosFechaPorFactura = aplicarRangosPorFactura;
})();

/* ============================================================
   Motivos y handlers
============================================================ */
const MOTIVOS_BY_TIPO = {
  CREDITO: [{ id: 1, txt: 'DEVOLUCION TOTAL' }, { id: 2, txt: 'AJUSTE PARCIAL' }],
  DEBITO:  [{ id: 3, txt: 'COSTO ADICIONAL' }, { id: 4, txt: 'DIFERENCIA' }]
};

function cargarMotivosSegunTipo(tipo){
  const sel = q('motivo_id');
  if (!sel) {
    console.error('No se encontró el elemento motivo_id');
    return;
  }
  if (!tipo) {
    console.warn('No se proporcionó tipo de nota');
    sel.innerHTML = '<option value="">— Seleccione motivo —</option>';
    sel.disabled = true;
    return;
  }
  sel.innerHTML = '<option value="">— Seleccione motivo —</option>';
  const motivos = MOTIVOS_BY_TIPO[tipo] || [];
  if (motivos.length === 0) {
    console.warn(`No se encontraron motivos para el tipo: ${tipo}`);
  }
  motivos.forEach(m=>{
    const opt=document.createElement('option');
    opt.value=String(m.id); opt.textContent=m.txt;
    sel.appendChild(opt);
  });
  sel.disabled = false;
}

/* Factura */
q('fact_id')?.addEventListener('change', async function(){
  const id = this.value; 
  if (!id) return;

  ensureHiddenForSelect(this);
  this.disabled = true;

  q('nota_tipo').value = '';
  setEnabled('nota_tipo', true);
  focusId('nota_tipo');

  q('motivo_id').innerHTML = '<option value="">— Seleccione motivo —</option>';
  setEnabled('motivo_id', false);
  q('descripcion').value = '';
  q('descripcion').readOnly = true;

  try{
    // Agregar timestamp para evitar caché del navegador
    const timestamp = new Date().getTime();
    const ch = await fetch('get_factura_header.php?fact_id='+encodeURIComponent(id)+'&_t='+timestamp, {
      cache: 'no-store',
      headers: {
        'Cache-Control': 'no-cache'
      }
    });
    if(!ch.ok) throw new Error('Error header HTTP');
    const h = await ch.json();

    q('proveedor_id').value = h.id_proveedor||'';
    q('proveedor_txt').value = h.proveedor||'';
    q('proveedor_ruc').value = h.ruc||'';

    const cond = h.tipo_compra||'';
    const plazo = h.fac_plazo ? (' / '+h.fac_plazo) : '';
    const saldo = (h.saldo_pendiente!=null) ? (' / Saldo: '+fmtGs(h.saldo_pendiente)) : '';
    q('fact_info').value = cond+plazo+saldo;

    const facEmi = h.factura_emision || '';
    q('fact_emision').value = facEmi;

    /* === NUEVO: guardar total de la factura para validar ND > importe de factura === */
    if (q('fac_total')) {
      // Asegurar que el valor sea numérico y se convierta correctamente
      const facTotalRaw = h.fac_total;
      const facTotalValue = facTotalRaw != null ? String(facTotalRaw) : '0';
      q('fac_total').value = facTotalValue;
      
      // Mostrar el total actualizado de la factura de forma visible
      if (q('fact_total_display')) {
        q('fact_total_display').value = fmtGs(toInt(facTotalValue));
      }
      
      console.log('fac_total asignado:', {
        raw: facTotalRaw,
        processed: facTotalValue,
        factura_id: id,
        tipo: typeof facTotalRaw,
        json_response: h
      });
    } else {
      console.error('Campo fac_total no encontrado en el DOM');
    }

    if (window.__aplicarRangosFechaPorFactura) window.__aplicarRangosFechaPorFactura();

    // Agregar timestamp para evitar caché del navegador
    const timestamp2 = new Date().getTime();
    const rd=await fetch('get_factura_detalle.php?fact_id='+encodeURIComponent(id)+'&_t='+timestamp2, {
      cache: 'no-store',
      headers: {
        'Cache-Control': 'no-cache'
      }
    });
    if(!rd.ok) throw new Error('Error detalle HTTP');
    const det=await rd.json();

    const tb=document.querySelector('#tabla-factura-detalles tbody'); tb.innerHTML='';
    det.forEach(d=>{
      const cant = toInt(d.cantidad);
      const prec = toInt(d.precio);
      const subO = cant * prec;
      const ivaRaw = (d.iva_descri || '').toString().toLowerCase();
      const tr=document.createElement('tr');
      // El backend retorna id_materia_prima, no id_producto
      const idMateriaPrima = d.id_materia_prima || d.id_producto || '';
      tr.innerHTML = `
        <td>${d.producto}</td>
        <td><input type="number" class="form-control cantidad" min="0" step="1"
                   value="${cant}" data-id="${idMateriaPrima}"
                   data-orig-cant="${cant}" data-orig-precio="${prec}"
                   data-orig-sub="${subO}" disabled></td>
        <td><input type="number" class="form-control precio" min="0" step="1"
                   value="${prec}" data-orig-precio="${prec}" disabled></td>
        <td class="subtotal">${fmtGs(subO)}</td>
        <td class="iva" data-iva="${ivaRaw}">${ivaRaw}</td>
      `;
      tb.appendChild(tr);
    });
    attachNumericEnforcers();
    aplicarReglasCamposPorTipoMotivo();
    // Recalcular el total de la nota basándose en los valores cargados
    // Asegurar que se recalcule después de que fac_total esté disponible
    setTimeout(() => {
      recalcular();
      updateGuardarState();
    }, 100);
  }catch(e){ console.error(e); alert('Error al cargar datos de la factura.'); }
});

/* Tipo de Nota */
q('nota_tipo')?.addEventListener('change', ()=>{
  const tipoSel = q('nota_tipo');
  if (!tipoSel || !tipoSel.value) return;
  
  try {
    const tipo = tipoSel.value;
    cargarMotivosSegunTipo(tipo);
    habilitarDescripcionPorTipo();
    ensureHiddenForSelect(tipoSel);
    tipoSel.disabled = true;
    setEnabled('motivo_id', true);
    focusId('motivo_id');

    // reset campos específicos
    const ca = q('costo_adicional');
    if (ca){ ca.value='0'; ca.readOnly=true; ca.disabled=true; }
    aplicarReglasCamposPorTipoMotivo();
    recalcular();
  } catch(e) {
    console.error('Error en cambio de tipo de nota:', e);
    alert('Error al cargar motivos. Por favor, recarga la página.');
  }
});

/* Motivo */
q('motivo_id')?.addEventListener('change', ()=>{
  const sel = q('motivo_id'); if(!sel.value) return;

  aplicarReglasCamposPorTipoMotivo();
  ensureHiddenForSelect(sel); sel.disabled=true;

  const ca = q('costo_adicional');
  if (sel.value==='3'){ // ND - Costo adicional
    if (ca){ ca.value='0'; ca.readOnly=false; ca.disabled=false; }
    setEnabled('costo_adicional', true);
    setEnabled('descripcion', true);
    setEnabled('nota_nro', false);
    setEnabled('nota_timbrado', false);
    focusId('costo_adicional');
  }else{
    if (ca){ ca.value='0'; ca.readOnly=true; ca.disabled=true; }
    setEnabled('descripcion', true);
    // Para motivo 4 (DIFERENCIA), habilitar nota_nro y nota_timbrado después de descripción
    if (sel.value==='4') {
      setEnabled('nota_nro', true);
      setEnabled('nota_timbrado', true);
    }
    focusId('descripcion');
  }
  recalcular();
  updateGuardarState(); // Actualizar estado del botón guardar
});

/* Recalcular en grilla */
document.querySelector('#tabla-factura-detalles')?.addEventListener('input', e=>{
  if (e.target.classList.contains('cantidad')){
    validarCantidadContraOriginal(e.target);
    recalcular(); updateGuardarState();
  }
  if (e.target.classList.contains('precio')){
    recalcular(); updateGuardarState();
  }
});

/* Cancelar */
q('btn-cancelar')?.addEventListener('click', (ev)=>{
  ev.preventDefault();
  q('form-nota').reset();
  resetGuided();
  focusId('fact_id');
});

/* Validaciones previas */
let _ultimaViolacion = false;
function lineaViolaCreditoAjusteParcial(tr){
  const qEl = tr.querySelector('.cantidad'), pEl=tr.querySelector('.precio');
  const qv = toInt(qEl?.value||'0'), pv = toInt(pEl?.value||'0');
  const qOrg = toInt(qEl?.dataset.origCant || qEl?.getAttribute('data-orig-cant') || '0');
  const subOrg = toInt(qEl?.dataset.origSub || (qOrg*toInt(pEl?.dataset.origPrecio||'0')));
  if (qv<=0) return true;
  if (qOrg<=0) return true;
  if (qv >= qOrg) return true;
  if ((qv*pv) > subOrg) return true;
  return false;
}
function lineaViolaDebitoDiferencia(tr){
  const qEl = tr.querySelector('.cantidad'), pEl=tr.querySelector('.precio');
  if (!qEl || !pEl) {
    console.log('lineaViolaDebitoDiferencia: No se encontraron elementos cantidad o precio');
    return true; // Si no hay elementos, viola
  }
  
  // Leer valores incluso si están disabled
  const pWasDisabled = pEl.disabled;
  if (pWasDisabled) pEl.disabled = false;
  
  const p = toInt(pEl.value || pEl.getAttribute('value') || '0');
  const qOrg = toInt(qEl.dataset?.origCant || qEl.getAttribute('data-orig-cant') || '0');
  const pOrg = toInt(pEl.dataset?.origPrecio || pEl.getAttribute('data-orig-precio') || '0');
  
  // Restaurar estado disabled
  if (pWasDisabled) pEl.disabled = true;
  
  // Calcular subtotal original
  const subOrg = toInt(qEl.dataset?.origSub || qEl.getAttribute('data-orig-sub') || (qOrg * pOrg));
  
  // Validación: precio debe ser mayor a 0 y (cantidad original × precio nuevo) debe ser mayor al subtotal original
  if (p <= 0) {
    console.log(`lineaViolaDebitoDiferencia: Precio inválido (${p})`);
    return true; // Precio debe ser mayor a 0
  }
  if (qOrg <= 0) {
    console.log(`lineaViolaDebitoDiferencia: Cantidad original inválida (${qOrg})`);
    return true; // Debe haber cantidad original
  }
  if (subOrg <= 0) {
    console.log(`lineaViolaDebitoDiferencia: Subtotal original inválido (${subOrg})`);
    return true; // Debe haber subtotal original
  }
  
  const nuevoSubtotal = qOrg * p;
  // Si el precio no ha cambiado (p === pOrg), no viola (permite valores originales)
  // Solo valida si el precio fue modificado
  if (p === pOrg) {
    console.log(`lineaViolaDebitoDiferencia: Precio no modificado (${p} === ${pOrg}), no viola`);
    return false; // No viola si el precio no cambió
  }
  
  // Si el precio cambió, debe ser mayor al original para que el nuevo subtotal sea mayor
  const viola = !(nuevoSubtotal > subOrg);
  
  console.log(`lineaViolaDebitoDiferencia: qOrg=${qOrg}, p=${p}, pOrg=${pOrg}, subOrg=${subOrg}, nuevoSubtotal=${nuevoSubtotal}, viola=${viola}`);
  
  return viola; // Retorna true si viola (nuevo subtotal NO es mayor al original)
}
function showModal(msg){
  const el = q('reglaModalMsg'); if (el) el.textContent = msg;
  try{ new bootstrap.Modal(q('reglaModal')).show(); }catch(_){}
}
function formularioTieneViolaciones(show=true){
  const tipo=q('nota_tipo')?.value||'', motivo=q('motivo_id')?.value||'';
  const filas = Array.from(document.querySelectorAll('#tabla-factura-detalles tbody tr'));
  if (filas.length===0) return true;

  if (tipo==='CREDITO' && motivo==='2'){
    const viola = filas.some(lineaViolaCreditoAjusteParcial);
    if (viola && show && !_ultimaViolacion){
      showModal('Crédito (Ajuste parcial): la cantidad debe ser mayor a 0 y menor a la facturada, y (cantidad × precio) no puede exceder el importe original de la línea.');
    }
    _ultimaViolacion = viola; return viola;
  }
  if (tipo==='DEBITO' && motivo==='4'){
    const viola = filas.some(lineaViolaDebitoDiferencia);
    if (viola && show && !_ultimaViolacion){
      showModal('Débito (Diferencia): (cantidad original × precio nuevo) debe ser mayor al total original de la línea.');
    }
    _ultimaViolacion = viola; return viola;
  }
  _ultimaViolacion = false; return false;
}
function updateGuardarState(force=false){
  const btn=q('btn-guardar');
  const bad = force ? true : formularioTieneViolaciones(false);
  if (btn) btn.disabled = bad;
}

/* Submit */
q('form-nota').addEventListener('submit', (e)=>{
  const facE=q('fact_emision').value;
  const nEmi=q('nota_emision').value;
  const nVto=q('nota_vto').value;

  if (facE && nEmi && (nEmi<facE || nEmi>todayYMD())){
    e.preventDefault(); showModal(`La emisión de la nota debe estar entre ${facE} y ${todayYMD()}.`); return;
  }
  if (nEmi && (!nVto || nVto < nEmi)){
    e.preventDefault(); showModal(`El vencimiento del timbrado debe ser mayor o igual a ${nEmi}.`); return;
  }

  if (formularioTieneViolaciones(true)){ e.preventDefault(); return; }

  const tipoSel=q('nota_tipo').value, motivoSel=q('motivo_id').value;
  const descVal=(q('descripcion').value||'').trim();

  // Regla: descripción obligatoria para NC (mantengo tu criterio)
  if (tipoSel==='CREDITO' && descVal.length===0){
    e.preventDefault(); showModal('La descripción es obligatoria para Notas de Crédito.'); return;
  }

  // Regla ND: costo adicional > 0 cuando motivo = 3
  if (tipoSel==='DEBITO' && motivoSel==='3'){
    const v = toInt(q('costo_adicional').value || '0');
    if (v<=0){ e.preventDefault(); showModal('Débito (Costo adicional): ingrese un costo adicional mayor a cero.'); return; }
  }

  // === NUEVO: Regla ND general (según especificación): total ND > importe de la factura ===
  if (tipoSel==='DEBITO'){
    const totalNota = toInt(q('nota_total_num').value || '0');
    const facTotal  = toInt(q('fac_total').value || '0');
    if (facTotal > 0 && !(totalNota > facTotal)){
      e.preventDefault(); showModal(`Para Nota de Débito, el total de la nota debe ser MAYOR al importe de la factura (${fmtGs(facTotal)}).`); return;
    }
  }

  // Serializa items
  const items=[];
  const filas = document.querySelectorAll('#tabla-factura-detalles tbody tr');
  
  // Si no hay filas en la tabla, mostrar error
  if (filas.length === 0) {
    e.preventDefault(); 
    showModal('Agregue al menos un ítem.'); 
    return;
  }
  
  // Para DEBITO con motivo 3 (COSTO ADICIONAL), los campos están disabled pero los items deben incluirse
  // porque el total se calcula desde costo_adicional pero los items se usan para el detalle
  const esDebitoCostoAdicional = (tipoSel === 'DEBITO' && motivoSel === '3');
  
  filas.forEach((tr, index)=>{
    const cantInput = tr.querySelector('.cantidad');
    const precInput = tr.querySelector('.precio');
    if (!cantInput || !precInput) {
      console.log(`Fila ${index}: No se encontraron inputs de cantidad o precio`);
      return;
    }
    
    // Obtener id_producto/id_materia_prima - probar múltiples métodos
    let idp = 0;
    // Intentar leer desde dataset
    if (cantInput.dataset && cantInput.dataset.id) {
      idp = toInt(cantInput.dataset.id);
    }
    // Si no funciona, intentar desde atributo
    if (idp <= 0 && cantInput.getAttribute('data-id')) {
      idp = toInt(cantInput.getAttribute('data-id'));
    }
    
    if (idp <= 0) {
      console.log(`Fila ${index}: No se encontró id_producto/id_materia_prima. Dataset:`, cantInput.dataset, 'Attr:', cantInput.getAttribute('data-id'));
      return; // Si no hay id_producto, saltar esta fila
    }
    
    // Leer valores: temporalmente habilitar campos si están disabled para poder leer el value
    let c = 0, p = 0;
    const cantWasDisabled = cantInput.disabled;
    const precWasDisabled = precInput.disabled;
    
    if (cantWasDisabled) cantInput.disabled = false;
    if (precWasDisabled) precInput.disabled = false;
    
    // Ahora leer los valores - probar múltiples métodos
    const cantValue = cantInput.value;
    const precValue = precInput.value;
    
    c = toInt(cantValue || cantInput.getAttribute('value') || cantInput.dataset?.origCant || '0');
    p = toInt(precValue || precInput.getAttribute('value') || precInput.dataset?.origPrecio || '0');
    
    // Restaurar estado disabled
    if (cantWasDisabled) cantInput.disabled = true;
    if (precWasDisabled) precInput.disabled = true;
    
    const ivaEl = tr.querySelector('td[data-iva]');
    const iva = (ivaEl?.dataset?.iva) || (ivaEl?.getAttribute('data-iva')) || '';
    
    // Debug log
    console.log(`Fila ${index}: idp=${idp}, c=${c}, p=${p}, iva=${iva}`);
    
    // Para DEBITO con motivo 3 (COSTO ADICIONAL), incluir items aunque cantidad sea 0
    // Para otros casos, validar que cantidad Y precio sean > 0
    if (esDebitoCostoAdicional) {
      // Para costo adicional, el total viene del campo costo_adicional, pero necesitamos los items para el detalle
      // Incluir el item con cantidad mínima 1 si tiene precio
      if (p > 0) {
        items.push({id_producto:idp,cantidad:Math.max(c, 1),precio:p,iva_descri:iva});
        console.log(`Fila ${index}: Item agregado (DEBITO COSTO ADICIONAL)`);
      } else {
        console.log(`Fila ${index}: Precio es 0, no se agrega item`);
      }
    } else {
      // Para otros casos, validar que ambos sean > 0
      if (c > 0 && p > 0) {
        items.push({id_producto:idp,cantidad:c,precio:p,iva_descri:iva});
        console.log(`Fila ${index}: Item agregado`);
      } else {
        console.log(`Fila ${index}: c=${c} o p=${p} es 0, no se agrega item`);
      }
    }
  });
  
  console.log(`Total items serializados: ${items.length}`);
  console.log('Items:', JSON.stringify(items));
  
  if (items.length===0){ 
    e.preventDefault(); 
    showModal('Agregue al menos un ítem.'); 
    return; 
  }

  // Asegurar que los campos de fecha viajen aunque estén disabled
  const emi = q('nota_emision'); if (emi && emi.disabled) emi.disabled = false;
  const vto = q('nota_vto'); if (vto && vto.disabled) vto.disabled = false;

  q('productos').value = JSON.stringify(items);
});

/* Máscara Nº Nota */
(function(){
  const f = q('nota_nro'); if (!f) return;
  function formatNota(digs){
    let v = String(digs).replace(/\D/g,'').slice(0,13);
    if (v.length>=7) v = v.slice(0,3)+'-'+v.slice(3,6)+'-'+v.slice(6);
    else if (v.length>=4) v = v.slice(0,3)+'-'+v.slice(3);
    return v;
  }
  function validar(){
    const re = /^(?!000)\d{3}-(?!000)\d{3}-(?!0{7})\d{7}$/;
    f.setCustomValidity(re.test(f.value) ? '' : 'Formato: EEE-PPP-NNNNNNN (EEE≠000, PPP≠000, NNNNNNN≠0000000).');
  }
  f.addEventListener('keydown', (e)=>{
    const allowed=['Backspace','Delete','ArrowLeft','ArrowRight','Home','End','Tab'];
    if (allowed.includes(e.key)) return;
    if (!/^\d$/.test(e.key)) e.preventDefault();
  });
  const onChangeLike=()=>{ f.value=formatNota(f.value); validar(); };
  f.addEventListener('input', onChangeLike);
  f.addEventListener('paste', (e)=>{
    e.preventDefault();
    const txt=(e.clipboardData||window.clipboardData).getData('text');
    f.value=formatNota(txt); validar();
  });
  f.addEventListener('blur', validar);
  f.value = formatNota(f.value); validar();
})();

/* Costo adicional: enforcers */
(function(){
  const campo=q('costo_adicional'); if (!campo) return;
  campo.addEventListener('keydown', blockInvalidNumberKeys);
  campo.addEventListener('input', sanitizeOnInput);
  campo.addEventListener('blur', enforceMinZero);
  campo.addEventListener('input', ()=>recalcular());
})();
</script>
<?php } ?>
