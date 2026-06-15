<?php 
// ================== NUEVA ORDEN DE COMPRA ==================
if (isset($_GET['form_orden']) && $_GET['form'] === 'add'){ ?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800">
    <i class="fas fa-plus-circle"></i> Registrar Orden de Compra
  </h1>

  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
    <li class="breadcrumb-item"><a href="view.php">Orden de compra</a></li>
    <li class="breadcrumb-item active">Nueva Orden de Compra</li>
  </ol>

  <div class="card shadow mb-4">
    <div class="card-body">
      <form id="form-oc" action="proses.php?act=insert" method="POST">
        <?php
        try {
          require "../../config/database.php";
          $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
          $pdo = new PDO($dsn, $user, $pass);
          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

          
        // Mapear todos los presupuestos EMITIDO por proveedor (según especificación)
        $stPend = $pdo->query("
          SELECT id_presupuesto_compra AS id, id_proveedor AS prov
          FROM presupuesto_compra
          WHERE presu_estado = 'EMITIDO'
          ORDER BY id_proveedor, id_presupuesto_compra
        ");
        $rowsPend = $stPend->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rowsPend as $r) {
        $p = (int)$r['prov'];
        if (!isset($map[$p])) $map[$p] = [];
        $map[$p][] = ['id' => (int)$r['id']];
        }



          // Número siguiente de OC
          $q = $pdo->query("SELECT MAX(id_orden_compra) AS id FROM orden_de_compra");
          $row = $q->fetch(PDO::FETCH_ASSOC);
          $codigo = ($row && $row['id'] !== null) ? ((int)$row['id'] + 1) : 1;

          date_default_timezone_set('America/Asuncion');
          $fechaHoy = date("Y-m-d");
          $horaHoy  = date("H:i:s");

          // Usuario + Sucursal
          $userSesion = $_SESSION['username'];
          $sqlUser = "
            SELECT u.id_usuario, u.username, u.id_sucursal, s.descripcion_sucursal
            FROM usuarios u
            JOIN sucursales s ON s.id_sucursal = u.id_sucursal
            WHERE u.username = :u
            LIMIT 1";
          $stU = $pdo->prepare($sqlUser);
          $stU->execute([':u' => $userSesion]);
          $usr = $stU->fetch(PDO::FETCH_ASSOC);
          if (!$usr) throw new Exception("No se encontró el usuario logueado.");

          $usuarioId      = (int)$usr['id_usuario'];
          $usuarioNombre  = $usr['username'];
          $sucursalId     = (int)$usr['id_sucursal'];
          $sucursalNombre = $usr['descripcion_sucursal'];

        } catch (Throwable $e) {
          die("Error: " . $e->getMessage());
        }
        ?>

        <!-- Cabecera OC -->
        <div class="row mb-3">
          <div class="col-md-3">
            <label class="form-label">Orden N°</label>
            <input type="text" class="form-control" value="<?= $codigo ?>" readonly>
            <input type="hidden" name="oc_codigo" value="<?= $codigo ?>">
          </div>

          <div class="col-md-3">
            <label class="form-label">Fecha</label>
            <input type="text" class="form-control" value="<?= $fechaHoy ?>" readonly>
            <input type="hidden" name="oc_fecha" value="<?= $fechaHoy ?>">
          </div>

          <div class="col-md-3">
            <label class="form-label">Hora</label>
            <input type="text" class="form-control" value="<?= $horaHoy ?>" readonly>
            <input type="hidden" name="oc_hora" value="<?= $horaHoy ?>">
          </div>

          <div class="col-md-3">
            <label class="form-label">Usuario</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($usuarioNombre) ?>" readonly>
            <input type="hidden" name="oc_usuario" value="<?= (int)$usuarioId ?>">
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Sucursal</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($sucursalNombre) ?>" readonly>
            <input type="hidden" name="oc_sucursal" value="<?= (int)$sucursalId ?>">
          </div>

            <div class="col-md-4">
                <label for="proveedor" class="form-label">Proveedor</label>
                <select class="form-control" id="proveedor" name="proveedor" required>
                <option value="" selected>Seleccione un Proveedor</option>
                <?php
                $qp = $pdo->query("SELECT id_proveedor, razon_social FROM proveedor WHERE estado_proveedor = 'ACTIVO' ORDER BY razon_social ASC");
                while ($pr = $qp->fetch(PDO::FETCH_ASSOC)) {
                    echo '<option value="'.$pr['id_proveedor'].'">'.htmlspecialchars($pr['razon_social']).'</option>';
                }
                ?>
                </select>
            </div>

            <div class="col-md-4">
                <label for="presupuesto" class="form-label">Presupuesto</label>
                <select class="form-control" id="presupuesto" name="presupuesto" required disabled>
                <option value="" selected>Seleccione un Presupuesto</option>
                </select>
            </div>
        </div>

        <!-- Condición -->
        <div class="row mb-3">
        <div class="col-md-4">
            <label for="oc_condicion" class="form-label">Condición</label>
            <select class="form-control" id="orden_condicion" name="orden_condicion" required>
            <option value="CONTADO" selected>CONTADO</option>
            <option value="CREDITO">CRÉDITO</option>
            </select>
        </div>
        </div>


        <!-- Grilla Detalle -->
        <div class="table-responsive mb-4">
          <table class="table table-bordered table-striped" id="tabla-productos">
            <thead>
              <tr>
                <th>#</th>
                <th>Código</th>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio</th>
                <th>Tipo IVA</th>
                <th>Monto IVA</th>
                <th>Sub total</th>
                <th>Acción</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

        <input type="hidden" name="productos" id="productos">

        <!-- Totales -->
        <div class="row mb-3">
          <div class="col-md-3">
            <label for="total_importe" class="form-label">Total Importe</label>
            <input type="text" class="form-control" id="total_importe" name="total_importe" readonly>
          </div>
          <div class="col-md-3">
            <label for="total_iva" class="form-label">Total IVA</label>
            <input type="text" class="form-control" id="total_iva" name="total_iva" readonly>
          </div>
        </div>

        <!-- Observaciones -->
        <div class="row mb-3">
          <div class="col-md-12">
            <label for="oc_observaciones" class="form-label">Observaciones (Opcional)</label>
            <textarea class="form-control" id="oc_observaciones" name="oc_observaciones" rows="2" placeholder="Ingrese observaciones o condiciones adicionales"></textarea>
          </div>
        </div>

        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-success mx-2" name="Guardar">Guardar</button>
          <button type="button" id="btn-cancelar" class="btn btn-warning mx-2">Cancelar</button>
          <a href="view.php" class="btn btn-danger mx-2">Cerrar</a>
        </div>

      </form>
    </div>
  </div>
</div>
<?php } ?>
<!-- EDICION -->

<?php if (isset($_GET['form_orden']) && $_GET['form'] === 'edit' && isset($_GET['orden_id'])){ 
  try {
    require "../../config/database.php";
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $ocId = (int)$_GET['orden_id'];

    // Cabecera (debe estar EMITIDA según especificación)
    $sqlCab = "
      SELECT oc.id_orden_compra, oc.orden_fecha, oc.orden_estado, oc.orden_total,
             oc.id_presupuesto_compra, oc.id_proveedor, oc.id_sucursal, oc.id_usuario,
             COALESCE(oc.orden_condicion, 'CONTADO') AS orden_condicion,
             p.razon_social AS proveedor_nom,
             s.descripcion_sucursal AS sucursal_nom,
             u.username
      FROM orden_de_compra oc
      JOIN proveedor p  ON p.id_proveedor = oc.id_proveedor
      JOIN sucursales s ON s.id_sucursal = oc.id_sucursal
      JOIN usuarios  u  ON u.id_usuario  = oc.id_usuario
      WHERE oc.id_orden_compra = :id
      LIMIT 1";
    $st = $pdo->prepare($sqlCab);
    $st->execute([':id' => $ocId]);
    $cab = $st->fetch(PDO::FETCH_ASSOC);
    
    // Preparar valores
    $cond = isset($cab['orden_condicion']) ? $cab['orden_condicion'] : 'CONTADO';
    
    // Intentar leer orden_observaciones si existe
    $obs = '';
    try {
        $stObs = $pdo->prepare("SELECT COALESCE(orden_observaciones, '') AS orden_observaciones FROM orden_de_compra WHERE id_orden_compra = :id LIMIT 1");
        $stObs->execute([':id' => $ocId]);
        $obsRow = $stObs->fetch(PDO::FETCH_ASSOC);
        if ($obsRow && isset($obsRow['orden_observaciones'])) {
            $obs = $obsRow['orden_observaciones'];
        }
    } catch (PDOException $e) {
        // Campo no existe, usar valor por defecto
        error_log("Campo orden_observaciones no existe: " . $e->getMessage());
    }
    
    if (!$cab) throw new Exception("OC no encontrada.");
    // Validar estado: solo se pueden editar OCs en estado EMITIDA
    $estadoOC = strtoupper(trim((string)$cab['orden_estado']));
    if ($estadoOC !== 'EMITIDA') {
        throw new Exception("Solo se pueden editar OCs en estado EMITIDA. Estado actual: {$estadoOC}.");
    }

    // Detalle (solo lectura)
    $sqlDet = "
      SELECT d.id_materia_prima, mp.materia_prima_descripcion, d.oc_cantidad_compra, d.oc_precio_compra,
             ti.iva_descri AS iva
      FROM orden_detalle_compra d
      JOIN materia_prima mp ON mp.id_materia_prima = d.id_materia_prima
      LEFT JOIN tipo_iva ti ON ti.iva_id = mp.iva_id
      WHERE d.id_orden_compra = :id
      ORDER BY mp.materia_prima_descripcion ASC";
    $std = $pdo->prepare($sqlDet);
    $std->execute([':id' => $ocId]);
    $det = $std->fetchAll(PDO::FETCH_ASSOC);

    // Preparar valores
    $fecha   = substr($cab['orden_fecha'], 0, 10); // si timestamp, tomamos YYYY-MM-DD
    $totImp  = (int)$cab['orden_total'];
    // $obs ya se obtuvo arriba con el manejo de campos opcionales
  } catch (Throwable $e) {
    die("Error: ".$e->getMessage());
  }
?>
<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800">
    <i class="fas fa-edit"></i> Editar Orden de Compra
  </h1>

  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
    <li class="breadcrumb-item"><a href="view.php">Orden de compra</a></li>
    <li class="breadcrumb-item active">Editar</li>
  </ol>

  <div class="card shadow mb-4">
    <div class="card-body">
      <form id="form-oc" action="proses.php?act=update" method="POST">
        <!-- Cabecera (solo lectura) -->
        <div class="row mb-3">
          <div class="col-md-3">
            <label class="form-label">Orden N°</label>
            <input type="text" class="form-control" value="<?= (int)$cab['id_orden_compra'] ?>" readonly>
            <input type="hidden" name="oc_codigo" value="<?= (int)$cab['id_orden_compra'] ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Fecha</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($fecha) ?>" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label">Usuario</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($cab['username']) ?>" readonly>
            <input type="hidden" name="oc_usuario" value="<?= (int)$cab['id_usuario'] ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Sucursal</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($cab['sucursal_nom']) ?>" readonly>
            <input type="hidden" name="oc_sucursal" value="<?= (int)$cab['id_sucursal'] ?>">
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Proveedor</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($cab['proveedor_nom']) ?>" readonly>
            <input type="hidden" name="proveedor" value="<?= (int)$cab['id_proveedor'] ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Presupuesto</label>
            <input type="text" class="form-control" value="<?= (int)$cab['id_presupuesto_compra'] ?>" readonly>
            <input type="hidden" name="presupuesto" value="<?= (int)$cab['id_presupuesto_compra'] ?>">
          </div>
        </div>

        <!-- Solo editable: Condición y Observaciones -->
        <div class="row mb-3">
          <div class="col-md-4">
            <label for="orden_condicion" class="form-label">Condición</label>
            <select class="form-control" id="orden_condicion" name="orden_condicion" required>
              <option value="CONTADO" <?= $cond==='CONTADO'?'selected':''; ?>>CONTADO</option>
              <option value="CREDITO" <?= $cond==='CREDITO'?'selected':''; ?>>CRÉDITO</option>
            </select>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-12">
            <label for="oc_observaciones" class="form-label">Observaciones (Opcional)</label>
            <textarea class="form-control" id="oc_observaciones" name="oc_observaciones" rows="2" placeholder="Ingrese observaciones o condiciones adicionales"><?= htmlspecialchars($obs) ?></textarea>
          </div>
        </div>

        <!-- Detalle: SOLO LECTURA -->
        <div class="table-responsive mb-3">
          <table class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>#</th>
                <th>Código</th>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio</th>
                <th>IVA</th>
                <th>Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $i=1; $totalIva=0; $total=0;
              foreach ($det as $d):
                $sub = (int)$d['oc_cantidad_compra'] * (int)$d['oc_precio_compra'];
                $total += $sub;
              ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= (int)$d['id_materia_prima'] ?></td>
                <td><?= htmlspecialchars($d['materia_prima_descripcion']) ?></td>
                <td><?= (int)$d['oc_cantidad_compra'] ?></td>
                <td><?= (int)$d['oc_precio_compra'] ?></td>
                <td><?= htmlspecialchars($d['iva']) ?></td>
                <td><?= $sub ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Total (lectura) -->
        <div class="row mb-3">
          <div class="col-md-3">
            <label class="form-label">Total Importe</label>
            <input type="text" class="form-control" value="<?= (int)$totImp ?>" readonly>
          </div>
        </div>

        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-success mx-2" name="Guardar">Guardar cambios</button>
          <a href="view.php" class="btn btn-secondary mx-2">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php } ?>
<script>
// Campo de plazo de entrega eliminado según requerimiento
</script>


<!-- ================== SCRIPTS ================== -->
<script>
// ---------- Utilidades numéricas / formateo ----------
function toInt(s){ return parseInt(String(s||'').replace(/\D+/g,''),10) || 0; }
function formatGs(n){ n = Math.floor(n||0); return n.toString(); } // sin separadores

function isRowInput(el){
  return el && el.closest('#tabla-productos') &&
         (el.classList.contains('cantidad') || el.classList.contains('precio'));
}

// Bloqueo duro de símbolos y no-dígitos
document.addEventListener('beforeinput', (e)=>{
  const el = e.target;
  if (!isRowInput(el)) return;

  if (e.inputType === 'insertText' && e.data != null){
    if (!/^\d$/.test(e.data)) e.preventDefault();
  }
  if (e.inputType === 'insertFromPaste'){
    const txt = (e.clipboardData||window.clipboardData)?.getData('text') || '';
    if (!/^\d+$/.test(txt)) e.preventDefault();
  }
});
document.addEventListener('keydown', (e)=>{
  const el = e.target;
  if (!isRowInput(el)) return;
  if (['.',',','-','+','e','E'].includes(e.key)) e.preventDefault();
});
function normalizeLive(el){
  let v = String(el.value||'').replace(/\D+/g,'');
  if (v === '0') v = '';        // permite borrar todo si el usuario pone un 0
  v = v.replace(/^0+/, '');     // sin ceros a la izquierda
  el.value = v;
}
function normalizeFinal(el){
  let v = String(el.value||'').replace(/\D+/g,'').replace(/^0+/, '');
  if (v === '') v = '1';
  el.value = v;
}
document.addEventListener('input', (e)=>{
  const el = e.target;
  if (!isRowInput(el)) return;
  normalizeLive(el);
  if (el.classList.contains('cantidad') || el.classList.contains('precio')){
    const tr = el.closest('tr');
    recalcularFila(tr);
    actualizarTotales();
    actualizarEstadoGuardar();
  }
});
document.addEventListener('blur', (e)=>{
  const el = e.target;
  if (!isRowInput(el)) return;
  normalizeFinal(el);
}, true);

// ---------- Tabla helpers ----------
function reindex(){
  document.querySelectorAll('#tabla-productos tbody tr').forEach((tr,i)=>{
    const c0 = tr.children[0]; if (c0) c0.textContent = String(i+1);
  });
}
function recalcularFila(tr){
  const qty  = toInt(tr.querySelector('.cantidad')?.value);
  const prc  = toInt(tr.querySelector('.precio')?.value);
  const ivaT = (tr.querySelector('.iva')?.textContent || '').trim().toLowerCase();
  
  // Calcular IVA siempre desde cero basándose en el tipo de IVA (más confiable)
  // Normalizar el texto del IVA: eliminar espacios, %, guiones, guiones bajos
  const ivaTxtNormalized = ivaT.replace(/[\s%_\-]/g, '');
  
  let ivaUnit = 0;
  // Detectar IVA 10% en cualquier formato: "iva_10", "iva 10%", "iva10", "10", etc.
  if (ivaTxtNormalized.includes('10') || 
      ivaT === 'iva_10' || 
      ivaT === 'iva10' ||
      ivaT === '10' ||
      ivaT.includes('10%')) {
    // IVA 10%: precio / 11
    ivaUnit = Math.floor(prc / 11);
  } 
  // Detectar IVA 5% en cualquier formato: "iva_5", "iva 5%", "iva5", "5", etc.
  else if (ivaTxtNormalized.includes('5') || 
           ivaT === 'iva_5' || 
           ivaT === 'iva5' ||
           ivaT === '5' ||
           ivaT.includes('5%')) {
    // IVA 5%: precio / 21
    ivaUnit = Math.floor(prc / 21);
  }
  
  // IVA total = cantidad * IVA unitario
  const montoIva = Math.floor(qty * ivaUnit);
  const subtotal = Math.floor(qty * prc);
  
  const elSub = tr.querySelector('.subtotal');
  const elIva = tr.querySelector('.monto_iva');
  if (elSub) elSub.textContent = formatGs(subtotal);
  if (elIva) elIva.textContent = formatGs(montoIva);

  return { subtotal, montoIva };
}
function actualizarTotales(){
  let totImp=0, totIva=0;
  document.querySelectorAll('#tabla-productos tbody tr').forEach(tr=>{
    const {subtotal, montoIva} = recalcularFila(tr);
    totImp += subtotal;
    totIva += montoIva;
  });
  document.getElementById('total_importe').value = formatGs(totImp);
  document.getElementById('total_iva').value     = formatGs(totIva);
}

// ---------- Modal simple (Bootstrap 5) ----------
function showModal(titulo, mensaje, tipo = 'primary'){
  const id = 'modalMensaje';
  document.getElementById(id)?.remove();

  const html = `
  <div class="modal fade" id="${id}" tabindex="-1" aria-labelledby="${id}Label" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-${tipo}">
          <h5 class="modal-title text-white" id="${id}Label">${titulo}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">${mensaje}</div>
        <div class="modal-footer">
          <button type="button" class="btn btn-${tipo}" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>`;
  document.body.insertAdjacentHTML('beforeend', html);

  const el = document.getElementById(id);
  const modal = new bootstrap.Modal(el);

  // Fallback por si data-bs-dismiss no actúa
  el.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn=>{
    btn.addEventListener('click', ()=> modal.hide());
  });
  el.addEventListener('hidden.bs.modal', ()=> el.remove());

  modal.show();
}

// ---------- Estado Guardar y validaciones de cabecera ----------
const btnGuardar     = document.querySelector('button[type="submit"][name="Guardar"]');
const selProveedor   = document.getElementById('proveedor');
const selPresupuesto = document.getElementById('presupuesto');

// ¿Hay filas y todas las cantidades > 0?
function filasValidas(){
  const rows = document.querySelectorAll('#tabla-productos tbody tr');
  if (rows.length === 0) return false;
  for (const tr of rows){
    const cant = toInt(tr.querySelector('.cantidad')?.value);
    if (cant <= 0) return false;
  }
  return true;
}

function actualizarEstadoGuardar(){
  const okProveedor   = !!selProveedor.value;
  const okPresupuesto = !!selPresupuesto.value;
  const okFilas       = filasValidas();
  btnGuardar.disabled = !(okProveedor && okPresupuesto && okFilas);
}

// ---------- Cargar detalle por presupuesto ----------
document.getElementById('presupuesto').addEventListener('change', async function(){
  const preId = this.value;
  if (!preId) { actualizarEstadoGuardar(); return; }

  try{
    const resp = await fetch(`get_presupuesto_detalle.php?pre_id=${preId}`);
    const detalles = await resp.json(); // {codigo, descripcion, cantidad, precio, iva:'iva_10'|'iva_5'|''}

    const tbody = document.querySelector('#tabla-productos tbody');
    tbody.innerHTML = '';

    // Guardar el detalle original del presupuesto para validación
    window.presupuestoDetalleOriginal = detalles;
    
    detalles.forEach((d, idx)=>{
      const tr = document.createElement('tr');
      tr.setAttribute('data-codigo', d.codigo);
      tr.setAttribute('data-cantidad-original', d.cantidad);
      tr.setAttribute('data-precio-original', d.precio);
      const descuento = parseFloat(d.descuento || 0);
      tr.setAttribute('data-descuento-original', descuento);
      // Guardar el monto de IVA del presupuesto si existe
      const ivaMonto = toInt(d.iva_monto || 0);
      tr.setAttribute('data-iva-monto', ivaMonto);
      
      // Calcular precio neto considerando el descuento
      // El descuento es total por ítem, así que precio neto = precio - (descuento / cantidad)
      const cantidad = toInt(d.cantidad);
      const precioOriginal = toInt(d.precio);
      const precioNeto = cantidad > 0 ? Math.floor(precioOriginal - (descuento / cantidad)) : precioOriginal;
      
      // Formatear el texto del IVA para mostrar
      let ivaTexto = (d.iva || '').trim();
      // Si viene como "iva_5" o "iva_10", mostrar como "5%" o "10%"
      if (ivaTexto.toLowerCase() === 'iva_5') ivaTexto = '5%';
      else if (ivaTexto.toLowerCase() === 'iva_10') ivaTexto = '10%';
      
      tr.innerHTML = `
        <td>${idx+1}</td>
        <td>${d.codigo}</td>
        <td>${d.descripcion}</td>
        <td><input type="text" class="form-control cantidad" inputmode="numeric" pattern="\\d*" value="${cantidad}" required readonly></td>
        <td><input type="text" class="form-control precio"   inputmode="numeric" pattern="\\d*" value="${precioNeto}"   required readonly></td>
        <td class="iva">${ivaTexto}</td>
        <td class="monto_iva">0</td>
        <td class="subtotal">0</td>
        <td><span class="text-muted">—</span></td>
      `;
      tbody.appendChild(tr);
      recalcularFila(tr);
    });

    actualizarTotales();
    reindex();
    actualizarEstadoGuardar();
  }catch(err){
    console.error(err);
    showModal("Error","No se pudieron cargar los detalles del presupuesto.","danger");
  }
});

// NO permitir quitar filas según especificación (el detalle debe ser idéntico al presupuesto)
// Se eliminó el botón "Quitar" del HTML

// ---------- Envío ----------
document.getElementById('form-oc').addEventListener('submit', (e)=>{
  // Validar que el detalle sea idéntico al presupuesto (según especificación)
  const detalleOriginal = window.presupuestoDetalleOriginal || [];
  const productos = [];
  const filasActuales = [];
  
  document.querySelectorAll('#tabla-productos tbody tr').forEach(tr=>{
    const codigo   = (tr.children[1]?.textContent||'').trim();
    const cantidad = toInt(tr.querySelector('.cantidad')?.value);
    const precioMostrado = toInt(tr.querySelector('.precio')?.value); // Precio neto mostrado
    const precioOriginal = toInt(tr.getAttribute('data-precio-original') || 0); // Precio original del presupuesto
    const ivaTxt   = (tr.querySelector('.iva')?.textContent||'').trim().toLowerCase();
    const cantidadOriginal = toInt(tr.getAttribute('data-cantidad-original') || 0);

    if (codigo && cantidad>0 && precioMostrado>0){
      // Usar el precio original del presupuesto para validación y guardado
      // (el precio mostrado es neto con descuento, pero debemos guardar el original)
      productos.push({ codigo, cantidad, precio: precioOriginal, iva: ivaTxt });
      filasActuales.push({ codigo: parseInt(codigo), cantidad, precio: precioOriginal });
      
      // Validar que cantidad y precio original no hayan cambiado
      if (cantidad !== cantidadOriginal) {
        e.preventDefault();
        showModal("Error","No se pueden modificar las cantidades. El detalle debe ser idéntico al presupuesto.","danger");
        return;
      }
    }
  });

  // Validar que no se hayan agregado o quitado ítems
  if (productos.length !== detalleOriginal.length) {
    e.preventDefault();
    showModal("Error","No se pueden agregar o quitar ítems. El detalle debe ser idéntico al presupuesto.","danger");
    return;
  }

  // Validar que todos los códigos coincidan
  const codigosOriginales = detalleOriginal.map(d => parseInt(d.codigo)).sort();
  const codigosActuales = filasActuales.map(f => f.codigo).sort();
  if (JSON.stringify(codigosOriginales) !== JSON.stringify(codigosActuales)) {
    e.preventDefault();
    showModal("Error","El detalle debe contener exactamente los mismos productos que el presupuesto.","danger");
    return;
  }

  if (productos.length === 0){
    e.preventDefault();
    showModal("Atención","Debes agregar al menos un producto con cantidad válida.","warning");
  }else{
    document.getElementById('productos').value = JSON.stringify(productos);
  }
});

// Cancelar: limpiar proveedor, presupuesto, condición, plazo, grilla y totales
document.getElementById('btn-cancelar').addEventListener('click', ()=>{
  // Proveedor
  const prov = document.getElementById('proveedor');
  if (prov) prov.value = '';

  // Presupuesto
  const pre = document.getElementById('presupuesto');
  if (pre){
    pre.innerHTML = '<option value="" selected>Seleccione un Presupuesto</option>';
    pre.value = '';
    pre.disabled = true;
  }

  // Condición
  const cond = document.getElementById('oc_condicion');
  if (cond) cond.value = 'CONTADO';

  // Detalle + totales
  const tbody = document.querySelector('#tabla-productos tbody');
  if (tbody) tbody.innerHTML = '';
  const ti = document.getElementById('total_importe');
  const tv = document.getElementById('total_iva');
  if (ti) ti.value = '0';
  if (tv) tv.value = '0';

  actualizarEstadoGuardar();
  showModal("Listo","Se limpiaron los datos del formulario.","primary");
});

// Estado inicial
document.addEventListener('DOMContentLoaded', actualizarEstadoGuardar);
</script>

<script>
// ----- Proveedor -> llena combo de presupuestos PENDIENTE -----
const selProv = document.getElementById('proveedor');
const selPre  = document.getElementById('presupuesto');
const tbody   = document.querySelector('#tabla-productos tbody');

selProv.addEventListener('change', () => {
  const provId = selProv.value;

  // Limpieza
  tbody.innerHTML = '';
  document.getElementById('total_importe').value = '0';
  document.getElementById('total_iva').value     = '0';
  selPre.innerHTML = '<option value="" selected>Seleccione un Presupuesto</option>';
  selPre.disabled = true;

  if (!provId) { actualizarEstadoGuardar(); return; }

  const list = PRESUP_MAP[provId] || [];
  if (list.length === 0) { actualizarEstadoGuardar(); return; }

  list.forEach(it => {
    const opt = document.createElement('option');
    opt.value = it.id;
    opt.textContent = `Presupuesto N° ${it.id}`;
    selPre.appendChild(opt);
  });
  selPre.disabled = false;
  actualizarEstadoGuardar();
});
</script>

<script>
  // Mapa inyectado desde PHP: { provId: [ {id: xx}, ... ] }
  const PRESUP_MAP = <?= json_encode($map, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
</script>



