<?php 

if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}
// Verificar si existe el parámetro 'form' en la URL
if (isset($_GET['gestionar_compras']) && $_GET['form'] == 'add') { ?>
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">
        <i class="fas fa-plus-circle"></i> Registrar Compra
    </h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="view.php">Gestionar compras</a></li>
        <li class="breadcrumb-item active">Nueva factura</li>
    </ol>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form id="form-presupuesto" action="proses.php?act=insert" method="POST">
                <?php
                try {
                    require "../../config/database.php";
                    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                    $pdo = new PDO($dsn, $user, $pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $query = $pdo->query("SELECT MAX(id_factura_compra) AS id FROM factura_compra");
                    $data = $query->fetch(PDO::FETCH_ASSOC);

                    // Genera el próximo código
                    $codigo = ($data['id'] !== null) ? $data['id'] + 1 : 1;

                    date_default_timezone_set('America/Asuncion');
                    $fecha = date("Y-m-d");
                    $hora  = date("H:i:s");
                    $fechaMaxFactura = $fecha;

                    $userSesion = $_SESSION['username']; // ajusta si guardás email u otro dato


                    $proveedores = $pdo->query("
                        SELECT id_proveedor, razon_social, ruc_proveedor
                        FROM proveedor
                        WHERE estado_proveedor='ACTIVO'
                        ORDER BY razon_social
                    ")->fetchAll(PDO::FETCH_ASSOC);

                        $sqlUser = "
                        SELECT 
                            u.id_usuario,
                            u.username,
                            u.id_sucursal,
                            s.descripcion_sucursal
                        FROM usuarios u
                        JOIN sucursales s ON s.id_sucursal = u.id_sucursal
                        WHERE u.username = :user
                        LIMIT 1;
                        ";
                        $q = $pdo->prepare($sqlUser);
                        $q->execute([':user' => $userSesion]);
                        $usr = $q->fetch(PDO::FETCH_ASSOC);

                        if (!$usr) {
                            throw new Exception('No se encontró el usuario logueado.');
                        }

                        $usuarioId      = (int)$usr['id_usuario'];
                        $usuarioNombre  = $usr['username'];
                        $sucursalId     = (int)$usr['id_sucursal'];
                        $sucursalNombre = $usr['descripcion_sucursal']; 

                } catch (PDOException $e) {
                    die("Error: " . $e->getMessage());
                }
                ?>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="fecha" class="form-label">Fecha</label>
                        <input type="text" class="form-control" id="fecha" name="fecha" value="<?php echo $fecha; ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="hora" class="form-label">Hora</label>
                        <input type="text" class="form-control" id="hora" name="hora" value="<?php echo $hora; ?>" readonly>
                    </div>

                    <div class="col-md-3">
                        <label for="usuario" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="usuario" name="username" value="<?php echo $usuarioNombre; ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sucursal</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($sucursalNombre); ?>" readonly>
                    </div>

                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="fecha_emision_factura" class="form-label">Fecha emisión factura</label>
                        <input
                            type="date"
                            class="form-control"
                            id="fecha_emision_factura"
                            name="fecha_emision_factura"
                            max="<?php echo $fechaMaxFactura; ?>"
                            value="<?php echo $fechaMaxFactura; ?>"
                            required
                        >
                        <small class="text-muted">Debe ser menor o igual al día de hoy.</small>
                    </div>
                </div>

                <!-- 5) Selección de proveedor -->
                <div class="row mb-3">

                    <div class="col-md-2">
                        <label for="codigo" class="form-label">Factura ID</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo $codigo; ?>" readonly>
                    </div>

                  <div class="col-md-2">
                      <label for="proveedor" class="form-label">Proveedor</label>
                      <select id="proveedor" name="proveedor" class="form-control" required>
                          <option value="">Seleccione proveedor…</option>
                          <?php foreach ($proveedores as $p): ?>
                              <option value="<?= (int)$p['id_proveedor'] ?>"
                                      data-ruc="<?= htmlspecialchars($p['ruc_proveedor'] ?? '') ?>">
                                  <?= htmlspecialchars($p['razon_social']) ?>
                              </option>
                          <?php endforeach; ?>
                      </select>
                      <small class="text-muted" id="ruc-info"></small>
                  </div>

                  <!-- 6) OC del proveedor -->
                  <div class="col-md-4">
                      <label for="orden_compra" class="form-label">Orden de Compra</label>
                      <select id="orden_compra" name="orden_compra" class="form-control" required disabled>
                          <option value="">Seleccione primero un proveedor…</option>
                      </select>
                      <small class="text-muted">Solo OC del proveedor con estado EMITIDA.</small>
                  </div>

                    <div class="col-md-4">
                      <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="recep_prev" name="recep_prev">
                        <label class="form-check-label" for="recep_prev">
                          Recepción previa con Nota de Remisión
                        </label>
                      </div>
                    </div>


                </div>

                <!-- 8) Datos de la factura del proveedor -->
                <div class="row mb-3">
                  <div class="col-md-3">
                      <label class="form-label">N° Factura</label>
                      <input type="text" class="form-control" id="numero_factura" name="numero_factura"
                             pattern="^(?!000)\d{3}-(?!000)\d{3}-(?!0{7})\d{7}$" maxlength="15" placeholder="001-002-0001234" required>
                      <small class="text-muted">Formato: EEE-PPP-NNNNNNN</small>
                  </div>

                  <div class="col-md-3">
                      <label class="form-label">Timbrado</label>
                      <input type="text" class="form-control" id="timbrado" name="timbrado"
                             pattern="^(?!0{8})\d{8}$" maxlength="8" placeholder="12345678" required>
                  </div>

                    <div class="form-group col-md-4 mb-0">
                      <label for="nota_remision_id" class="mb-1">Nota de Remisión</label>
                      <select id="nota_remision_id" name="nota_remision_id" class="form-control" disabled>
                        <option value="">— Seleccione una Nota de Remisión —</option>
                      </select>
                      <small class="form-text text-muted">
                        Se habilita si marcas la casilla de recepción previa.
                      </small>
                    </div>


                </div>

<!-- ====== BLOQUE HTML (tal como usas) ====== -->
<!-- Reemplaza "Tipo de Factura" por este bloque -->
<div class="form-row">
  <div class="form-group col-md-3">
    <label for="orden_condicion">Orden Condición</label>
    <input type="text" class="form-control" id="orden_condicion" name="orden_condicion" readonly>
    <small class="form-text text-muted">Proviene de la OC seleccionada.</small>
  </div>

  <div class="form-group col-md-2">
    <label for="cuotas">Cuotas</label>
    <input type="text" class="form-control" id="cuotas" name="cuotas" value="0" inputmode="numeric" autocomplete="off" required>
    <small class="form-text text-muted">0 si es CONTADO, máx. 12.</small>
  </div>

  <div class="form-group col-md-2">
    <label for="interes_pct">% Interés</label>
    <input type="text" class="form-control" id="interes_pct" name="interes_pct" value="0" inputmode="decimal" autocomplete="off" required>
  </div>

  <div class="form-group col-md-2">
    <label for="cuota_base">Cuota base</label>
    <input type="text" class="form-control" id="cuota_base" name="cuota_base" readonly>
  </div>

  <div class="form-group col-md-2">
    <label for="interes_cuota">Interés por cuota</label>
    <input type="text" class="form-control" id="interes_cuota" name="interes_cuota" readonly>
  </div>

  <div class="form-group col-md-2">
    <label for="cuota_final">Cuota final</label>
    <input type="text" class="form-control" id="cuota_final" name="cuota_final" readonly>
  </div>
</div> 

  <!-- 9) Recepción previa (opcional) -->
<!-- Fila nueva SOLO para: Recepción previa + Nota de Remisión -->
<div class="form-row align-items-end mb-3">



</div>

<!-- 7) Detalle precargado desde OC -->
<div id="nr-alert" class="alert alert-warning d-none mb-3" role="alert"></div>
<div class="table-responsive mb-4">
  <table class="table table-bordered table-striped" id="tabla-productos">
    <thead>
      <tr>
        <th>Código</th>
        <th>Producto</th>
        <th>Cantidad</th>
        <th>Precio</th>
        <th>IVA</th>
        <th>IVA (Gs)</th>
        <th>Sub total</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
</div>

<input type="hidden" name="productos" id="productos">

<div class="row mb-3">
  <div class="col-md-2">
    <label for="total_importe">Total Importe</label>
    <input type="text" class="form-control" id="total_importe" name="total_importe" readonly>
  </div>

  <div class="col-md-2">
    <label for="iva_total">Total IVA</label>
    <input type="text" class="form-control" id="iva_total" name="iva_total" readonly>
  </div>
</div>

<div class="d-flex justify-content-end">
  <button type="submit" class="btn btn-success mx-2" name="Guardar">Guardar</button>
  <button type="button" id="btn-cancelar" class="btn btn-warning mx-2">Cancelar</button>
  <a href="view.php" class="btn btn-danger mx-2">Cerrar</a>
</div>

<!-- ====== SCRIPTS ====== -->
<script>
  document.addEventListener('DOMContentLoaded', () => {
  updateGuardarState();
});

// ======================== Utilidades ========================
function toInt(v){ return parseInt(String(v).replace(/\./g,'').replace(/,/g,'').trim()) || 0; }
function toFloat(v){ return parseFloat(String(v).replace(/\./g,'').replace(/,/g,'').replace(',', '.').trim()) || 0; }
function fmtGs(n){ n = Number(n)||0; return n.toLocaleString('es-PY'); }

// ================== Máscaras/validaciones cabecera ==================
(function(){
  // N° factura EEE-PPP-NNNNNNN
  const fNro = document.getElementById('numero_factura');
  if (fNro){
    fNro.addEventListener('input', ()=> {
      let v = fNro.value.replace(/\D/g,'').slice(0,13);
      if (v.length >= 7) v = v.slice(0,3)+'-'+v.slice(3,6)+'-'+v.slice(6);
      else if (v.length >= 4) v = v.slice(0,3)+'-'+v.slice(3);
      fNro.value = v;
    });
  }
  // Timbrado 8 dígitos
  const fTim = document.getElementById('timbrado');
  if (fTim){
    fTim.addEventListener('input', ()=> fTim.value = fTim.value.replace(/\D/g,'').slice(0,8));
  }

})();

// ================== Referencias de UI ==================
const selProv   = document.getElementById('proveedor');
const selOC     = document.getElementById('orden_compra');
const selNota   = document.getElementById('nota_remision_id');
const chkRecep  = document.getElementById('recep_prev');
const nrAlertEl = document.getElementById('nr-alert');
const tbody     = document.querySelector('#tabla-productos tbody');
const inpTotal  = document.getElementById('total_importe');
const inpIvaTot = document.getElementById('iva_total');
const fechaEmisionInput = document.getElementById('fecha_emision_factura');
const btnCancelar = document.getElementById('btn-cancelar');
const rucInfoEl = document.getElementById('ruc-info');

// Campos financieros
const inpCond     = document.getElementById('orden_condicion');   // readonly
const inpCuotas   = document.getElementById('cuotas');
const inpInteres  = document.getElementById('interes_pct');
const inpCuotaB   = document.getElementById('cuota_base');
const inpIntCuota = document.getElementById('interes_cuota');
const inpCuotaFin = document.getElementById('cuota_final');
//const inpTotInt   = document.getElementById('total_con_interes');

// Guarda el total base (sin interés) para no “re-sumar” sobre lo ya mostrado
let baseTotal = 0;
let ocDetalle  = [];
let detalleActual = [];

// ================== Fecha emisión de factura ==================
(function enforceFechaEmision(){
  if (!fechaEmisionInput) return;
  const getIsoLocal = () => {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    return now.toISOString().split('T')[0];
  };
  const attrMax = fechaEmisionInput.getAttribute('max');
  const todayStr = (/^\d{4}-\d{2}-\d{2}$/.test(attrMax || '') ? attrMax : getIsoLocal());
  fechaEmisionInput.max = todayStr;
  if (!fechaEmisionInput.value || fechaEmisionInput.value > todayStr) {
    fechaEmisionInput.value = todayStr;
  }
  const validate = () => {
    if (!fechaEmisionInput.value || fechaEmisionInput.value > todayStr) {
      fechaEmisionInput.setCustomValidity('Seleccione una fecha de emisión menor o igual al día de hoy.');
    } else {
      fechaEmisionInput.setCustomValidity('');
    }
  };
  fechaEmisionInput.addEventListener('input', validate);
  fechaEmisionInput.addEventListener('change', validate);
  validate();
})();

if (btnCancelar && fechaEmisionInput){
  btnCancelar.addEventListener('click', () => {
    fechaEmisionInput.value = '';
    fechaEmisionInput.dispatchEvent(new Event('input'));
    fechaEmisionInput.dispatchEvent(new Event('change'));
  });
}

// ================== Validación RUC ==================
function validarRUC(ruc) {
  if (!ruc || ruc.trim() === '') return { valido: false, mensaje: 'RUC no proporcionado' };
  
  // Formato paraguayo: NNNNNNN-N o NNNNNN-N (6-7 dígitos + guión + 1 dígito verificador)
  // También acepta sin guión: NNNNNNNN (8 dígitos)
  const rucLimpio = ruc.replace(/\s/g, '');
  const patronConGuion = /^(\d{6,7})-(\d{1})$/;
  const patronSinGuion = /^\d{8,9}$/;
  
  if (patronConGuion.test(rucLimpio) || patronSinGuion.test(rucLimpio)) {
    return { valido: true, mensaje: '' };
  }
  
  return { valido: false, mensaje: 'Formato RUC inválido. Debe ser: NNNNNNN-N o NNNNNN-N' };
}

// ================== Proveedor → OCs del proveedor ==================
if (selProv && selOC){
  selProv.addEventListener('change', async ()=>{
    const prov = selProv.value;
    selOC.innerHTML = '<option value="">Cargando OCs…</option>';
    selOC.disabled = true;

    // Validar RUC del proveedor seleccionado
    if (rucInfoEl) {
      const opcionSeleccionada = selProv.options[selProv.selectedIndex];
      const ruc = opcionSeleccionada ? opcionSeleccionada.getAttribute('data-ruc') : '';
      
      if (ruc) {
        const validacion = validarRUC(ruc);
        if (validacion.valido) {
          rucInfoEl.textContent = `RUC: ${ruc}`;
          rucInfoEl.className = 'text-muted';
        } else {
          rucInfoEl.textContent = `⚠ ${validacion.mensaje}`;
          rucInfoEl.className = 'text-warning';
        }
      } else {
        rucInfoEl.textContent = '';
      }
    }

    // reset de detalle, totales y condición
    if (tbody){ tbody.innerHTML=''; actualizarTotal(0,0); }
    ocDetalle = [];
    detalleActual = [];
    mostrarAlertaNR();
    if (selNota){
      selNota.innerHTML = '<option value="">Seleccione una Nota de Remisión…</option>';
      selNota.value = '';
    }
    if (inpCond){ inpCond.value=''; toggleCreditoUI(); }
    updateGuardarState();

    if (!prov){
      selOC.innerHTML = '<option value="">Seleccione un proveedor…</option>';
      return;
    }
    try{
      const r  = await fetch(`oc_por_proveedor.php?prov_id=${encodeURIComponent(prov)}`);
      const js = await r.json();
      selOC.innerHTML = '<option value="">Seleccione una orden de compra…</option>';
      (js.items || []).forEach(it=>{
        const op = document.createElement('option');
        op.value = it.id_orden_compra;
        op.textContent = `OC #${it.id_orden_compra}`;
        selOC.appendChild(op);
      });
      selOC.disabled = false;
    }catch(e){
      console.error(e);
      selOC.innerHTML = '<option value="">No se pudo cargar</option>';
    }
  });
}

// ================== Seleccionar OC → condición + detalle ==================
if (selOC){
  selOC.addEventListener('change', async function(){
    if (tbody){ tbody.innerHTML=''; actualizarTotal(0,0); }
    ocDetalle = [];
    detalleActual = [];
    const ocId = this.value;
    const prov = selProv ? selProv.value : '';
    if (!prov || !ocId) return;

    try{
      // 1) Traer condición de la OC (usa prov_id para validar pertenencia)
      try{
        const rInfo = await fetch(`ajax_oc_info.php?oc_id=${encodeURIComponent(ocId)}&prov_id=${encodeURIComponent(prov)}`);
        const info  = await rInfo.json();
        if (info.ok && info.condicion){
          inpCond.value = String(info.condicion).toUpperCase(); // CONTADO | CREDITO
        } else {
          inpCond.value = '';
        }
        toggleCreditoUI();
        updateGuardarState();
      }catch(eInfo){ console.error('OC info:', eInfo); }

      // 2) Traer detalle de ítems
      const r  = await fetch(`ajax_oc_detalle.php?prov_id=${encodeURIComponent(prov)}&oc_id=${encodeURIComponent(ocId)}`);
      const js = await r.json();
      if (!js.ok){ alert(js.msg || 'No se pudo cargar la OC'); return; }

      ocDetalle = (js.items || []).map(it => {
        const descuentoRaw = it.descuento;
        let descuento = 0;
        if (descuentoRaw !== null && descuentoRaw !== undefined && descuentoRaw !== '') {
          descuento = typeof descuentoRaw === 'string' 
            ? parseFloat(descuentoRaw.replace(',', '.')) 
            : parseFloat(descuentoRaw);
          if (isNaN(descuento)) descuento = 0;
        }
        
        return {
          codigo: toInt(it.codigo),
          producto: it.producto,
          cantidadOc: toInt(it.cant_oc),
          precio: toInt(it.precio),
          descuento: descuento,
          iva: it.iva
        };
      });
      
      console.log('ocDetalle mapeado:', ocDetalle);

      aplicarDetalleOC();

      if (selNota) selNota.value = '';
      mostrarAlertaNR();

      if (chkRecep?.checked) {
        aplicarNotaRemisionSeleccionada();
      }
    }catch(e){
      console.error('Error al cargar detalles:', e);
      alert('Ocurrió un error al intentar cargar los detalles de la OC.');
    }
  });
}

//
// botón Guardar
const btnGuardar = document.querySelector('button[type="submit"][name="Guardar"]');

// función que (des)habilita Guardar según la regla
function updateGuardarState() {
  const esCredito = (inpCond?.value || '').toUpperCase() === 'CREDITO';
  const cuotasVal = toInt(inpCuotas?.value || '0');
  const ocElegida = !!(selOC && selOC.value);

  // Si es CREDITO → cuotas debe ser >=2. Si es CONTADO → no exige cuotas.
  const reglaOk = ocElegida && (!esCredito || cuotasVal >= 2);

  // Si la recepción previa está tildada, debe haber una NR seleccionada (valor no vacío).
  const chk = document.getElementById('recep_prev');
  const combo = document.getElementById('nota_remision_id');
  const nrOk = !chk?.checked || !!(combo && combo.value);

  // Mensaje de validación visual en el input de cuotas
  if (inpCuotas) {
    if (esCredito && cuotasVal < 1) {
      inpCuotas.setCustomValidity('Para Órdenes en CREDITO, las cuotas deben ser al menos 1.');
    } else {
      inpCuotas.setCustomValidity('');
    }
  }

  if (btnGuardar) btnGuardar.disabled = !(reglaOk && nrOk);
}

// === ACTUALIZA el estado cada vez que cambian estos campos
if (inpCuotas) inpCuotas.addEventListener('input', () => { /* ya tenés lógica */ recalcFinanciero(); updateGuardarState(); });
if (selOC)     selOC.addEventListener('change', () => { updateGuardarState(); });
if (inpInteres)inpInteres.addEventListener('input', () => { updateGuardarState(); });

// Llamá a updateGuardarState() luego de setear la condición desde ajax_oc_info
// (en tu listener de selOC, después de: inpCond.value = ...; toggleCreditoUI();)

//

// ================== Totales ==================
function actualizarTotal(total, totalIva){
  baseTotal = Number(total) || 0;
  if (inpIvaTot) inpIvaTot.value = totalIva ? `${fmtGs(totalIva)} Gs` : '';
  recalcFinanciero(true);
}

function renderDetalleFactura(detalle = []){
  detalleActual = Array.isArray(detalle) ? detalle : [];
  if (!tbody) return;
  tbody.innerHTML = '';

  let total = 0;
  let totalIva = 0;

  detalleActual.forEach(it => {
    const cant   = Math.max(0, toInt(it.cantidad));
    const precio = Math.max(0, toInt(it.precio));
    if (!cant || !precio) return;

    const sub = cant * precio;
    const ivaKey = String(it.iva || '').toLowerCase();
    let ivaUnit = 0;
    if (ivaKey.includes('10'))      ivaUnit = precio / 11;
    else if (ivaKey.includes('5'))  ivaUnit = precio / 21;
    const ivaFila = Math.floor(cant * ivaUnit);

    total    += sub;
    totalIva += ivaFila;

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="cod">${it.codigo}</td>
      <td>${it.producto}</td>
      <td class="cant">${cant}</td>
      <td class="precio">${fmtGs(precio)}</td>
      <td class="iva">${it.iva}</td>
      <td class="iva_monto">${fmtGs(ivaFila)}</td>
      <td class="subtotal">${fmtGs(sub)}</td>
    `;
    tbody.appendChild(tr);
  });

  actualizarTotal(total, totalIva);
}

function aplicarDetalleOC(){
  const filas = ocDetalle.map(it => {
    // Calcular precio neto considerando el descuento
    // El descuento es total por ítem, así que precio neto = precio - (descuento / cantidad)
    const cantidad = toInt(it.cantidadOc || 0);
    const precioOriginal = toInt(it.precio || 0);
    // El descuento puede venir como string o number, asegurarse de parsearlo correctamente
    const descuentoRaw = it.descuento;
    const descuento = typeof descuentoRaw === 'string' ? parseFloat(descuentoRaw.replace(',', '.')) : parseFloat(descuentoRaw || 0);
    
    // Calcular precio neto: precio original menos el descuento por unidad
    const precioNeto = cantidad > 0 && descuento > 0 
      ? Math.max(0, Math.floor(precioOriginal - (descuento / cantidad))) 
      : precioOriginal;
    
    return {
      codigo: it.codigo,
      producto: it.producto,
      cantidad: cantidad,
      precio: precioNeto, // Precio con descuento aplicado
      iva: it.iva
    };
  });
  
  renderDetalleFactura(filas);
  mostrarAlertaNR();
}

function prepararDetalleDesdeNota(notaItems = []){
  const mapaNota = new Map();
  notaItems.forEach(it => {
    const idProd = toInt(it.id_producto ?? it.codigo);
    if (!idProd) return;
    mapaNota.set(idProd, {
      id: idProd,
      cantidad: toInt(it.cantidad),
      producto: it.producto || ''
    });
  });

  const filas = [];
  const sinRemision = [];
  const conDiferencia = [];

  ocDetalle.forEach(it => {
    const dataNota = mapaNota.get(it.codigo);
    if (dataNota) {
      filas.push({
        codigo: it.codigo,
        producto: it.producto,
        cantidad: dataNota.cantidad,
        precio: it.precio,
        iva: it.iva
      });
      if (dataNota.cantidad !== it.cantidadOc) {
        conDiferencia.push(`${it.producto}: OC ${it.cantidadOc} / NR ${dataNota.cantidad}`);
      }
      mapaNota.delete(it.codigo);
    } else {
      sinRemision.push(it.producto);
    }
  });

  const fueraOc = [];
  mapaNota.forEach(item => {
    fueraOc.push(item.producto || `ID ${item.id}`);
  });

  return { filas, sinRemision, conDiferencia, fueraOc };
}

function mostrarAlertaNR(mensajes){
  if (!nrAlertEl) return;
  if (!mensajes || (Array.isArray(mensajes) && mensajes.length === 0)) {
    nrAlertEl.classList.add('d-none');
    nrAlertEl.textContent = '';
    return;
  }
  const texto = Array.isArray(mensajes) ? mensajes.join(' ') : String(mensajes);
  nrAlertEl.innerHTML = '';
  const strong = document.createElement('strong');
  strong.textContent = 'Atención:';
  nrAlertEl.appendChild(strong);
  nrAlertEl.appendChild(document.createTextNode(` ${texto}`));
  nrAlertEl.classList.remove('d-none');
}

async function aplicarNotaRemisionSeleccionada(){
  if (!chkRecep?.checked) {
    aplicarDetalleOC();
    return;
  }
  if (!selNota?.value) {
    aplicarDetalleOC();
    return;
  }
  if (!selOC?.value) {
    mostrarAlertaNR('Seleccione primero la Orden de Compra.');
    return;
  }
  if (!ocDetalle.length) {
    return;
  }

  try {
    const url = `ajax_nr_detalle.php?nr_id=${encodeURIComponent(selNota.value)}&oc_id=${encodeURIComponent(selOC.value)}`;
    const resp = await fetch(url);
    const data = await resp.json();
    if (!resp.ok || !data.ok) {
      throw new Error(data?.msg || 'No se pudo obtener el detalle de la Nota de Remisión.');
    }

    const prep = prepararDetalleDesdeNota(data.items || []);
    if (!prep.filas.length) {
      renderDetalleFactura([]);
      mostrarAlertaNR('La Nota de Remisión seleccionada no posee productos para esta Orden de Compra.');
      return;
    }

    renderDetalleFactura(prep.filas);

    const avisos = [];
    if (prep.sinRemision.length) {
      avisos.push(`Productos sin remisión: ${prep.sinRemision.join(', ')}.`);
    }
    if (prep.conDiferencia.length) {
      avisos.push(`Diferencias de cantidad: ${prep.conDiferencia.join('; ')}.`);
    }
    if (prep.fueraOc.length) {
      avisos.push(`Se ignoraron ítems que no pertenecen a la OC: ${prep.fueraOc.join(', ')}.`);
    }
    if (avisos.length) {
      avisos.push('Factura únicamente lo recepcionado. Si existe diferencia, genera la Nota de Crédito correspondiente.');
    }
    mostrarAlertaNR(avisos);
  } catch (err) {
    console.error('Nota de remisión (detalle):', err);
    mostrarAlertaNR(err.message || 'No se pudo aplicar la Nota de Remisión.');
    aplicarDetalleOC();
  }
}

async function loadNotasRemision(){
  if (!selNota) return;

  const prov = selProv?.value || '';
  const oc   = selOC?.value || '';

  selNota.innerHTML = '<option value="">Seleccione una Nota de Remisión…</option>';
  selNota.value = '';

  if (!prov || !oc) {
    updateGuardarState();
    return;
  }

  try {
    const params = new URLSearchParams({ prov_id: prov, oc_id: oc });
    const rsp = await fetch(`get_notas_remision.php?${params.toString()}`, { headers: { 'Accept': 'application/json' }});
    const js  = await rsp.json();

    const rows = Array.isArray(js) ? js
               : (Array.isArray(js.items) ? js.items
               : (Array.isArray(js.rows) ? js.rows : []));

    if (!rows.length) {
      selNota.innerHTML = '<option value="">Sin notas de remisión pendientes para esta OC</option>';
      selNota.value = '';
      updateGuardarState();
      return;
    }

    rows.forEach(r => {
      const opt = document.createElement('option');
      opt.value = r.id;
      opt.textContent = `NR #${r.nro ?? '(s/n)'} — ${r.fecha ?? ''}`;
      selNota.appendChild(opt);
    });

    if (rows.length === 1) {
      selNota.value = rows[0].id;
    }

    updateGuardarState();
  } catch (err) {
    console.error('Error NR:', err);
    selNota.innerHTML = '<option value="">Error cargando notas de remisión</option>';
    selNota.value = '';
  }
}

// ================== Submit ==================
document.getElementById('form-presupuesto').addEventListener('submit', function (e) {
    // Regla adicional de cuotas cuando es CREDITO
  const esCredito = (inpCond?.value || '').toUpperCase() === 'CREDITO';
  const cuotasVal = toInt(inpCuotas?.value || '0');
  if (esCredito && cuotasVal < 1) {
    e.preventDefault();
    inpCuotas.reportValidity(); // muestra el mensaje de setCustomValidity
    return;
  }
  const productos = [];

  // Validar cabecera
  const nro = document.getElementById('numero_factura')?.value || '';
  const tim = document.getElementById('timbrado')?.value || '';
  const fechaEmision = document.getElementById('fecha_emision_factura')?.value || '';
  const hoyStr = new Date().toISOString().split('T')[0];

  if (!/^\d{3}-\d{3}-\d{7}$/.test(nro)){ e.preventDefault(); return alert('N° de factura inválido.'); }
  if (!/^\d{8}$/.test(tim)){ e.preventDefault(); return alert('Timbrado inválido.'); }
  if (!fechaEmision || fechaEmision > hoyStr){
    e.preventDefault();
    return alert('La fecha de emisión debe ser menor o igual al día de hoy.');
  }
  if (!selOC.value){ e.preventDefault(); return alert('Seleccione una Orden de Compra.'); }

  // Detalle
  document.querySelectorAll('#tabla-productos tbody tr').forEach(tr=>{
    const cod = toInt(tr.querySelector('.cod').textContent);
    const cant= toInt(tr.querySelector('.cant').textContent);
    const pre = toInt(tr.querySelector('.precio').textContent);
    if (cod && cant>0 && pre>0){
      productos.push({ codigo: cod, cantidad: cant, precio: pre });
    }
  });

  if (productos.length === 0){
    e.preventDefault();
    return showErrorModal('No hay detalle para registrar. Verifique la OC seleccionada.');
  }
  document.getElementById('productos').value = JSON.stringify(productos);
});

// ================== Modal de error ==================
function showErrorModal(message) {
  const modal = document.createElement('div');
  modal.className = 'modal fade';
  modal.setAttribute('role','dialog');
  modal.innerHTML = `
    <div class="modal-dialog"><div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-danger">Error</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body"><p>${message}</p></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-dismiss="modal">Cerrar</button>
      </div>
    </div></div>`;
  document.body.appendChild(modal);
  $(modal).modal('show');
  $(modal).on('hidden.bs.modal', ()=> modal.remove());
}

// ================== Limpieza inicial ==================
(function limpiarFormulario(){
  if (tbody) tbody.innerHTML = '';
  if (inpTotal)  inpTotal.value = '';
  if (inpIvaTot) inpIvaTot.value = '';
  [inpCuotaB, inpIntCuota, inpCuotaFin].forEach(el=>{ if (el) el.value=''; });
})();

// ================== Reglas de cuotas e interés ==================
if (inpCuotas){
  inpCuotas.addEventListener('input', ()=>{
    inpCuotas.value = inpCuotas.value.replace(/\D/g,''); // solo dígitos
    let v = toInt(inpCuotas.value);
    if (v > 12) v = 12;
    if (v < 0)  v = 0;
    inpCuotas.value = String(v);
    recalcFinanciero();
  });
}
if (inpInteres){
  inpInteres.addEventListener('input', ()=>{
    let raw = inpInteres.value.replace(/[^0-9.,]/g,'').replace(',', '.');
    let val = Math.min(100, Math.max(0, parseFloat(raw || '0')));
    inpInteres.value = isNaN(val) ? '0' : String(val);
    recalcFinanciero();
  });
}

// ================== Cálculo financiero ==================
function recalcFinanciero(forceWriteTotal = false){
  const total   = baseTotal;
  const cuotas  = toInt(inpCuotas?.value || '0');
  const pct     = toFloat(inpInteres?.value || '0');

  const esCredito = (inpCond?.value || '').toUpperCase() === 'CREDITO';
  const activo    = esCredito && cuotas > 0 && total > 0;

  if (!activo){
    if (inpCuotaB)   inpCuotaB.value   = '';
    if (inpIntCuota) inpIntCuota.value = '';
    if (inpCuotaFin) inpCuotaFin.value = '';
    if (forceWriteTotal && inpTotal) inpTotal.value = total ? `${fmtGs(total)} Gs` : '';
    return;
  }

  const cuotaBase   = Math.round(total / cuotas);
  const interesCta  = Math.round(cuotaBase * (pct/100));
  const cuotaFinal  = cuotaBase + interesCta;
  const totalConInt = Math.round(cuotaFinal * cuotas);

  if (inpCuotaB)   inpCuotaB.value   = `${fmtGs(cuotaBase)} Gs`;
  if (inpIntCuota) inpIntCuota.value = `${fmtGs(interesCta)} Gs`;
  if (inpCuotaFin) inpCuotaFin.value = `${fmtGs(cuotaFinal)} Gs`;
  if (inpTotal)    inpTotal.value    = `${fmtGs(totalConInt)} Gs`;
}

// Habilitar/deshabilitar UI según condición
function toggleCreditoUI(){
  const esCredito = (inpCond?.value || '').toUpperCase() === 'CREDITO';
  [inpCuotas, inpInteres].forEach(el => { if (el) el.disabled = !esCredito; });
  if (!esCredito){
    if (inpCuotas)  inpCuotas.value = '0';
    if (inpInteres) inpInteres.value = '0';
  }
  recalcFinanciero();
}
</script>


<script>
document.addEventListener('DOMContentLoaded', () => {
  function resetCombo(){
    if (selNota) {
      selNota.innerHTML = '<option value="">Seleccione una Nota de Remisión…</option>';
      selNota.value = '';
    }
  }

  async function toggleCombo(){
    if (!selNota) return;

    if (chkRecep?.checked) {
      selNota.disabled = false;
      await loadNotasRemision();
      aplicarNotaRemisionSeleccionada();
    } else {
      selNota.disabled = true;
      resetCombo();
      mostrarAlertaNR();
      aplicarDetalleOC();
      updateGuardarState();
    }
  }

  chkRecep?.addEventListener('change', toggleCombo);

  selNota?.addEventListener('change', () => {
    updateGuardarState();
    aplicarNotaRemisionSeleccionada();
  });

  selProv?.addEventListener('change', () => {
    if (chkRecep?.checked) {
      loadNotasRemision().then(() => aplicarNotaRemisionSeleccionada());
    }
  });

  selOC?.addEventListener('change', () => {
    if (chkRecep?.checked) {
      loadNotasRemision().then(() => aplicarNotaRemisionSeleccionada());
    }
  });

  toggleCombo();
});
</script>








<?php } ?>
