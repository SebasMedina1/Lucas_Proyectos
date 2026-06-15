<?php
if (!isset($_SESSION['username'])) {
    die('Sesión expirada.');
}

if (isset($_GET['nueva_nota']) && ($_GET['form'] ?? '') === 'add') {
    require "../../config/database.php";
?>
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">
        <i class="fas fa-plus-circle"></i> Registrar Nota de Crédito Venta
    </h1>

    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="view.php">Notas de Crédito</a></li>
        <li class="breadcrumb-item active">Nueva Nota</li>
    </ol>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form id="form-nota" action="proses.php?act=insert_nota" method="POST">
                <?php
                try {
                    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                    $pdo = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]);

                    $qNext = $pdo->query("SELECT COALESCE(MAX(id_nota_venta),0)+1 AS next_id FROM nota_venta");
                    $codigo = (int)$qNext->fetchColumn();

                    date_default_timezone_set('America/Asuncion');
                    $hoy = date('Y-m-d');
                    $hora = date('H:i');

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

                    $id_usuario = (int)$usr['id_usuario'];
                    $id_sucursal = (int)$usr['id_sucursal'];
                    $sucursal_nombre = $usr['descripcion_sucursal'];

                    // Obtener motivos
                    // Filtrar motivos: solo los de categoría NOTA_CREDITO (excluir motivos de ajustes)
                    // Verificar si existe la columna categoria_motivo
                    $tieneCategoria = false;
                    try {
                      $checkCol = $pdo->query("
                        SELECT COUNT(*) 
                        FROM information_schema.columns 
                        WHERE table_schema = 'public' 
                          AND table_name = 'motivo' 
                          AND column_name = 'categoria_motivo'
                      ");
                      $tieneCategoria = ($checkCol->fetchColumn() > 0);
                    } catch (Exception $e) {
                      $tieneCategoria = false;
                    }
                    
                    if ($tieneCategoria) {
                      // Solo motivos específicos para Notas de Crédito VENTA (categoría exclusiva)
                      $stMotivos = $pdo->query("
                        SELECT id_motivo, motivo_descripcion 
                        FROM motivo 
                        WHERE categoria_motivo = 'NOTA_CREDITO_VENTA'
                        ORDER BY motivo_descripcion
                      ");
                    } else {
                      // Fallback: buscar por nombre si no existe la categoría
                      $stMotivos = $pdo->query("
                        SELECT id_motivo, motivo_descripcion 
                        FROM motivo 
                        WHERE (UPPER(TRIM(motivo_descripcion)) LIKE '%ANULACIÓN TOTAL VENTA%'
                           OR UPPER(TRIM(motivo_descripcion)) LIKE '%ANULACION TOTAL VENTA%'
                           OR UPPER(TRIM(motivo_descripcion)) LIKE '%DEVOLUCIÓN%MERCADER%VENTA%'
                           OR UPPER(TRIM(motivo_descripcion)) LIKE '%DEVOLUCION%MERCADER%VENTA%')
                        ORDER BY motivo_descripcion
                      ");
                    }
                    $motivos = $stMotivos->fetchAll();

                } catch (Throwable $e) {
                    die("Error: " . $e->getMessage());
                }
                ?>

                <input type="hidden" name="id_usuario" value="<?= $id_usuario ?>">
                <input type="hidden" name="id_sucursal" value="<?= $id_sucursal ?>">

                <!-- Contexto -->
                <div class="row mb-3">
                    <div class="col-md-2">
                        <label class="form-label">Fecha</label>
                        <input type="date" class="form-control" id="nota_fecha" name="nota_fecha" value="<?= $hoy ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Hora</label>
                        <input type="time" class="form-control" id="nota_hora" name="nota_hora" value="<?= $hora ?>" readonly>
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
                </div>

                <!-- Cliente y Factura -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Cliente <span class="text-danger">*</span></label>
                        <select id="cliente_id" name="cliente_id" class="form-control" required>
                            <option value="">— Seleccione cliente —</option>
                            <?php
                            try {
                                $stCli = $pdo->query("
                                    SELECT id_cliente, cliente_nombre || ' ' || cliente_apellido AS nombre_completo, cliente_ruc
                                    FROM clientes
                                    WHERE cliente_estado = 'ACTIVO'
                                    ORDER BY cliente_nombre
                                ");
                                while ($cli = $stCli->fetch()) {
                                    echo '<option value="' . $cli['id_cliente'] . '">' . htmlspecialchars($cli['nombre_completo']) . ' - RUC: ' . htmlspecialchars($cli['cliente_ruc']) . '</option>';
                                }
                            } catch (Throwable $e) {
                                // Ignorar
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Factura <span class="text-danger">*</span></label>
                        <select id="fact_id" name="fact_id" class="form-control" required disabled>
                            <option value="">— Seleccione primero un cliente —</option>
                        </select>
                        <small class="text-muted">Solo facturas EMITIDAS del cliente seleccionado.</small>
                    </div>
                </div>

                <!-- Tipo y Motivo -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Tipo de Nota <span class="text-danger">*</span></label>
                        <input type="text" id="nota_tipo" name="nota_tipo" class="form-control" value="CREDITO" readonly>
                        <input type="hidden" name="nota_tipo" value="CREDITO">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Motivo <span class="text-danger">*</span></label>
                        <select id="motivo_id" name="motivo_id" class="form-control" required>
                            <option value="">— Seleccione motivo —</option>
                            <?php foreach ($motivos as $m): ?>
                                <option value="<?= $m['id_motivo'] ?>"><?= htmlspecialchars($m['motivo_descripcion']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Medio de Devolución</label>
                        <select id="medio_devolucion" name="medio_devolucion" class="form-control">
                            <option value="EFECTIVO">Efectivo</option>
                            <option value="TARJETA">Tarjeta</option>
                            <option value="TRANSFERENCIA">Transferencia</option>
                            <option value="CHEQUE">Cheque</option>
                        </select>
                    </div>
                </div>

                <!-- Información de la Factura -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Cliente</label>
                        <input type="text" class="form-control" id="cliente_txt" readonly>
                        <input type="hidden" name="id_cliente" id="cliente_id_hidden">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">RUC</label>
                        <input type="text" class="form-control" id="cliente_ruc" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipo Factura</label>
                        <input type="text" class="form-control" id="fact_tipo" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Total Factura</label>
                        <input type="text" class="form-control" id="fact_total" readonly>
                        <input type="hidden" name="fac_total" id="fac_total_hidden">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Saldo Pendiente</label>
                        <input type="text" class="form-control" id="saldo_pendiente" readonly>
                    </div>
                </div>

                <!-- Timbrado y Número -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">N° Nota</label>
                        <input type="text" class="form-control" id="nota_nro" name="nota_nro" pattern="^\d{3}-\d{3}-\d{7}$" maxlength="15" placeholder="001-002-0001234" required>
                        <small class="text-muted">Formato: EEE-PPP-NNNNNNN</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Timbrado</label>
                        <input type="text" class="form-control" id="nota_timbrado" name="nota_timbrado" pattern="^(?!0{8})\d{8}$" maxlength="8" placeholder="8 dígitos" required>
                        <small id="timbrado_status" class="form-text"></small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha Emisión</label>
                        <input type="date" class="form-control" id="nota_emision" name="nota_emision" value="<?= $hoy ?>" required>
                    </div>
                </div>

                <!-- Descripción -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="2" placeholder="Descripción de la nota de crédito"></textarea>
                    </div>
                </div>

                <!-- Detalle -->
                <div class="table-responsive mb-3">
                    <table class="table table-bordered table-striped" id="tabla-factura-detalles">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th style="width:120px">Cantidad Factura</th>
                                <th style="width:120px">Cantidad a Devolver</th>
                                <th style="width:160px">Precio</th>
                                <th>Subtotal</th>
                                <th>IVA %</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <input type="hidden" name="productos" id="productos">
                <input type="hidden" name="nota_total_num" id="nota_total_num">
                <input type="hidden" name="subtotal" id="subtotal_hidden">
                <input type="hidden" name="iva_5" id="iva_5_hidden">
                <input type="hidden" name="iva_10" id="iva_10_hidden">
                <input type="hidden" name="iva_exento" id="iva_exento_hidden">

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Subtotal (sin IVA)</label>
                        <input type="text" class="form-control" id="subtotal_display" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">IVA 5%</label>
                        <input type="text" class="form-control" id="iva_5_display" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">IVA 10%</label>
                        <input type="text" class="form-control" id="iva_10_display" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Total Nota</label>
                        <input type="text" class="form-control" id="nota_total" readonly>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" id="btn-guardar" class="btn btn-success mx-2" disabled>Guardar</button>
                    <button type="button" id="btn-cancelar" class="btn btn-warning mx-2">Cancelar</button>
                    <a href="view.php" class="btn btn-danger mx-2">Cerrar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const toInt = v => parseInt(String(v).replace(/[^\d]/g,'')) || 0;
const toFloat = v => parseFloat(String(v).replace(/[^\d.,]/g,'').replace(',','.')) || 0;
const fmtGs = n => (Number(n)||0).toLocaleString('es-PY')+' Gs';
    
    // Función para mostrar mensajes de error usando la estructura visual estándar
    function mostrarMensaje(mensaje, tipo = 'danger') {
        // Remover mensaje anterior si existe
        const alertAnterior = document.getElementById('alert-dynamic');
        if (alertAnterior) {
            alertAnterior.remove();
        }
        
        // Crear nuevo mensaje
        const alertDiv = document.createElement('div');
        alertDiv.id = 'alert-dynamic';
        alertDiv.className = `alert alert-${tipo} alert-dismissible fade show`;
        alertDiv.setAttribute('role', 'alert');
        alertDiv.innerHTML = `
            ${mensaje}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        `;
        
        // Insertar al inicio del formulario
        const form = document.getElementById('form-nota');
        if (form && form.parentElement) {
            form.parentElement.insertBefore(alertDiv, form);
        } else {
            // Fallback: insertar al inicio del body
            document.body.insertBefore(alertDiv, document.body.firstChild);
        }
        
        // Auto-ocultar después de 5 segundos
        setTimeout(() => {
            if (alertDiv && alertDiv.parentElement) {
                alertDiv.remove();
            }
        }, 5000);
        
        // Scroll al mensaje
        alertDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

// Variable global para estado de timbrado
let timbradoValido = false;

document.addEventListener('DOMContentLoaded', () => {
    const clienteSelect = document.getElementById('cliente_id');
    const facturaSelect = document.getElementById('fact_id');
    const form = document.getElementById('form-nota');
    const tbody = document.querySelector('#tabla-factura-detalles tbody');
    let facturaDetalle = [];
    let facturaTotal = 0;
    let notasCreditoExistentes = 0;

    // Cargar facturas del cliente
    clienteSelect.addEventListener('change', async function() {
        const clienteId = this.value;
        facturaSelect.innerHTML = '<option value="">Cargando facturas...</option>';
        facturaSelect.disabled = true;
        tbody.innerHTML = '';
        resetTotales();

        if (!clienteId) {
            facturaSelect.innerHTML = '<option value="">— Seleccione primero un cliente —</option>';
            return;
        }

        try {
            const response = await fetch(`get_facturas_cliente.php?cliente_id=${clienteId}`);
            const data = await response.json();
            
            facturaSelect.innerHTML = '<option value="">— Seleccione factura —</option>';
            if (data.success && data.facturas && data.facturas.length > 0) {
                data.facturas.forEach(fact => {
                    const option = document.createElement('option');
                    option.value = fact.id_factura_venta;
                    option.textContent = `Factura #${fact.numero_factura} - ${fact.fecha_factura} - Total: ${fmtGs(fact.total_general)}`;
                    facturaSelect.appendChild(option);
                });
            } else if (data.success && (!data.facturas || data.facturas.length === 0)) {
                facturaSelect.innerHTML = '<option value="">Este cliente no tiene facturas EMITIDAS disponibles</option>';
            } else {
                mostrarMensaje('Error al cargar facturas: ' + (data.msg || 'Error desconocido'), 'danger');
                facturaSelect.innerHTML = '<option value="">Error al cargar facturas</option>';
            }
            facturaSelect.disabled = false;
        } catch (error) {
            console.error('Error:', error);
            mostrarMensaje('Error al cargar facturas: ' + error.message, 'danger');
            facturaSelect.innerHTML = '<option value="">Error al cargar facturas</option>';
            facturaSelect.disabled = false;
        }
    });

    // Cargar detalle de la factura
    facturaSelect.addEventListener('change', async function() {
        const factId = this.value;
        tbody.innerHTML = '';
        resetTotales();

        if (!factId) return;

        try {
            // Cargar header de factura
            const resHeader = await fetch(`get_factura_header.php?fact_id=${factId}`);
            const header = await resHeader.json();
            
            // El endpoint devuelve los datos directamente si es exitoso, o {ok: false, msg: ...} si hay error
            if (!header.ok && header.msg) {
                mostrarMensaje('Error al cargar la factura: ' + header.msg, 'danger');
                return;
            }
            
            // Si llegamos aquí, header contiene los datos de la factura
            if (header.id_factura_venta) {
                document.getElementById('cliente_txt').value = header.cliente || '';
                document.getElementById('cliente_id_hidden').value = header.id_cliente || '';
                document.getElementById('cliente_ruc').value = header.ruc || '';
                document.getElementById('fact_tipo').value = header.tipo_factura || '';
                document.getElementById('fact_total').value = fmtGs(header.total_general || 0);
                document.getElementById('fac_total_hidden').value = header.total_general || 0;
                document.getElementById('saldo_pendiente').value = fmtGs(header.saldo_pendiente || header.total_general || 0);
                facturaTotal = parseFloat(header.total_general || 0);
            }

            // Cargar detalle de factura
            const resDet = await fetch(`get_factura_detalle.php?fact_id=${factId}`);
            const detalle = await resDet.json();
            
            if (Array.isArray(detalle) && detalle.length > 0) {
                facturaDetalle = detalle;
                renderDetalle(detalle);
                calcularTotales();
            }

            // Verificar notas de crédito existentes para esta factura
            const resNC = await fetch(`get_notas_factura.php?fact_id=${factId}`);
            const ncData = await resNC.json();
            if (ncData.success) {
                notasCreditoExistentes = parseFloat(ncData.total_notas || 0);
            }

            updateGuardarState();
            } catch (error) {
                console.error('Error:', error);
                mostrarMensaje('Error al cargar el detalle de la factura', 'danger');
            }
    });

    function renderDetalle(detalle) {
        tbody.innerHTML = '';
        const motivoId = document.getElementById('motivo_id').value;
        const motivoSelect = document.getElementById('motivo_id');
        const motivoTexto = motivoSelect.options[motivoSelect.selectedIndex]?.text || '';
        const esAnulacionTotal = motivoTexto.toUpperCase().includes('ANULACIÓN TOTAL') || 
                                 motivoTexto.toUpperCase().includes('ANULACION TOTAL');
        const esDevolucion = motivoTexto.toUpperCase().includes('DEVOLUCIÓN') || 
                             motivoTexto.toUpperCase().includes('DEVOLUCION');
        
        detalle.forEach((item, idx) => {
            const tr = document.createElement('tr');
            const cantidadFactura = item.cantidad || 0;
            const precio = item.precio || 0;
            // Si es anulación total, usar cantidad total; si es devolución, permitir editar
            const cantidadInicial = esAnulacionTotal ? cantidadFactura : cantidadFactura;
            const subtotal = cantidadInicial * precio;
            const ivaPct = parseFloat(item.iva_porcentaje || 0);
            const ivaMonto = subtotal * (ivaPct / 100);
            const total = subtotal + ivaMonto;

            tr.innerHTML = `
                <td>${item.producto}</td>
                <td>
                    <input type="number" class="form-control cantidad-factura" 
                           value="${cantidadFactura}" 
                           readonly
                           style="width:100px; background-color: #e9ecef;">
                </td>
                <td>
                    <input type="number" class="form-control cantidad" 
                           data-producto-id="${item.producto_id}" 
                           data-orig-cant="${cantidadFactura}"
                           value="${cantidadInicial}" 
                           min="1" 
                           max="${cantidadFactura}" 
                           ${esAnulacionTotal ? 'readonly style="width:100px; background-color: #e9ecef;"' : 'style="width:100px"'}
                           >
                </td>
                <td>
                    <input type="number" class="form-control precio" 
                           data-producto-id="${item.producto_id}"
                           value="${precio}" min="0" 
                           style="width:140px" readonly>
                </td>
                <td class="subtotal">${fmtGs(subtotal)}</td>
                <td>${ivaPct}%</td>
                <td class="total-linea">${fmtGs(total)}</td>
            `;
            tbody.appendChild(tr);
        });

        // Agregar listeners para recalcular
        tbody.querySelectorAll('.cantidad').forEach(inp => {
            inp.addEventListener('input', function() {
                if (this.readOnly) return; // No procesar si está readonly
                const max = parseInt(this.getAttribute('max'));
                const val = parseInt(this.value) || 0;
                if (val > max) this.value = max;
                if (val < 1) this.value = 1;
                recalcularLinea(this.closest('tr'));
                calcularTotales();
                updateGuardarState();
            });
        });
    }

    function recalcularLinea(tr) {
        const cantidad = toInt(tr.querySelector('.cantidad')?.value || '0');
        const precio = toFloat(tr.querySelector('.precio')?.value || '0');
        const subtotal = cantidad * precio;
        const ivaPct = parseFloat(tr.querySelector('td:nth-child(6)')?.textContent.replace('%', '') || '0'); // Cambiado de 5 a 6 por nueva columna
        const ivaMonto = subtotal * (ivaPct / 100);
        const total = subtotal + ivaMonto;

        tr.querySelector('.subtotal').textContent = fmtGs(subtotal);
        tr.querySelector('.total-linea').textContent = fmtGs(total);
    }

    function calcularTotales() {
        let subtotal = 0;
        let iva5 = 0;
        let iva10 = 0;
        let ivaExento = 0;
        let total = 0;

        tbody.querySelectorAll('tr').forEach(tr => {
            const cantidad = toInt(tr.querySelector('.cantidad')?.value || '0');
            const precio = toFloat(tr.querySelector('.precio')?.value || '0');
            const sub = cantidad * precio;
            subtotal += sub;

            const ivaPct = parseFloat(tr.querySelector('td:nth-child(6)')?.textContent.replace('%', '') || '0'); // Cambiado de 5 a 6 por nueva columna
            const ivaMonto = sub * (ivaPct / 100);
            
            if (ivaPct === 5) {
                iva5 += ivaMonto;
            } else if (ivaPct === 10) {
                iva10 += ivaMonto;
            } else {
                ivaExento += sub;
            }

            total += sub + ivaMonto;
        });

        document.getElementById('subtotal_display').value = fmtGs(subtotal);
        document.getElementById('iva_5_display').value = fmtGs(iva5);
        document.getElementById('iva_10_display').value = fmtGs(iva10);
        document.getElementById('nota_total').value = fmtGs(total);

        document.getElementById('subtotal_hidden').value = subtotal;
        document.getElementById('iva_5_hidden').value = iva5;
        document.getElementById('iva_10_hidden').value = iva10;
        document.getElementById('iva_exento_hidden').value = ivaExento;
        document.getElementById('nota_total_num').value = total;

        // Preparar productos para envío
        const productos = [];
        tbody.querySelectorAll('tr').forEach(tr => {
            const productoId = tr.querySelector('.cantidad')?.getAttribute('data-producto-id');
            const cantidad = toInt(tr.querySelector('.cantidad')?.value || '0');
            const precio = toFloat(tr.querySelector('.precio')?.value || '0');
            const ivaPct = parseFloat(tr.querySelector('td:nth-child(6)')?.textContent.replace('%', '') || '0'); // Cambiado de 5 a 6 por nueva columna
            
            if (productoId && cantidad > 0) {
                productos.push({
                    producto_id: productoId,
                    cantidad: cantidad,
                    precio: precio,
                    iva_porcentaje: ivaPct
                });
            }
        });
        document.getElementById('productos').value = JSON.stringify(productos);
    }

    function resetTotales() {
        document.getElementById('subtotal_display').value = '';
        document.getElementById('iva_5_display').value = '';
        document.getElementById('iva_10_display').value = '';
        document.getElementById('nota_total').value = '';
        document.getElementById('nota_total_num').value = '0';
        document.getElementById('productos').value = '[]';
    }

    function updateGuardarState() {
        const factId = facturaSelect.value;
        const clienteId = clienteSelect.value;
        const notaTipo = 'CREDITO'; // Siempre es Crédito
        const motivoId = document.getElementById('motivo_id').value;
        const notaNro = document.getElementById('nota_nro').value;
        const timbrado = document.getElementById('nota_timbrado').value;
        const notaTotal = parseFloat(document.getElementById('nota_total_num').value || '0');
        const disponible = facturaTotal - notasCreditoExistentes;

        // Solo validar si hay productos agregados (notaTotal > 0)
        const isValid = factId && clienteId && motivoId && notaNro && timbrado && timbradoValido && notaTotal > 0;
        const noExcede = (notaTotal === 0) || (notaTotal <= disponible); // Permitir 0 (sin productos aún)

        document.getElementById('btn-guardar').disabled = !isValid || !noExcede;

        // Solo mostrar mensaje si hay productos y excede el disponible
        if (notaTotal > 0 && notaTotal > disponible && disponible > 0) {
            mostrarMensaje(`El monto de la nota (${fmtGs(notaTotal)}) excede el disponible (${fmtGs(disponible)}).`, 'warning');
        }
    }

    // Validar antes de enviar
    form.addEventListener('submit', function(e) {
        // Validar timbrado antes de enviar
        if (!timbradoValido) {
            e.preventDefault();
            mostrarMensaje('Por favor, ingrese un timbrado vigente válido', 'warning');
            document.getElementById('nota_timbrado').focus();
            return false;
        }
        
        const notaTotal = parseFloat(document.getElementById('nota_total_num').value || '0');
        const disponible = facturaTotal - notasCreditoExistentes;

        if (notaTotal <= 0) {
            e.preventDefault();
            mostrarMensaje('El total de la nota debe ser mayor a cero. Agregue al menos un producto.', 'warning');
            return false;
        }

        if (notaTotal > disponible) {
            e.preventDefault();
            mostrarMensaje(`El monto de la nota no puede exceder el disponible: ${fmtGs(disponible)}`, 'danger');
            return false;
        }
    });

    // Listener para motivo: cuando cambia, re-renderizar el detalle con las reglas correspondientes
    const motivoSelect = document.getElementById('motivo_id');
    if (motivoSelect) {
        motivoSelect.addEventListener('change', function() {
            // Si ya hay detalle cargado, re-renderizarlo con las nuevas reglas
            if (facturaDetalle && facturaDetalle.length > 0) {
                renderDetalle(facturaDetalle);
                calcularTotales();
            }
            updateGuardarState();
        });
    }

    // Listeners para validación (nota_tipo no se incluye porque siempre es CREDITO)
    ['motivo_id', 'nota_nro', 'nota_timbrado'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', updateGuardarState);
            el.addEventListener('input', updateGuardarState);
        }
    });

    // Máscara para número de nota
    const notaNroInput = document.getElementById('nota_nro');
    if (notaNroInput) {
        notaNroInput.addEventListener('input', function() {
            let v = this.value.replace(/\D/g,'').slice(0,13);
            if (v.length >= 7) v = v.slice(0,3)+'-'+v.slice(3,6)+'-'+v.slice(6);
            else if (v.length >= 4) v = v.slice(0,3)+'-'+v.slice(3);
            this.value = v;
        });
    }

    // Máscara y validación de timbrado
    const timbradoInput = document.getElementById('nota_timbrado');
    const timbradoStatus = document.getElementById('timbrado_status');
    let timbradoValido = false;
    
    if (timbradoInput) {
        // Máscara
        timbradoInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g,'').slice(0,8);
            timbradoValido = false;
            if (timbradoStatus) {
                timbradoStatus.textContent = '';
                timbradoStatus.className = 'form-text';
            }
        });
        
        // Validar timbrado cuando se completa (8 dígitos)
        timbradoInput.addEventListener('blur', async function() {
            const timbrado = this.value.trim();
            
            if (timbrado.length !== 8) {
                if (timbradoStatus) {
                    timbradoStatus.textContent = 'El timbrado debe tener 8 dígitos';
                    timbradoStatus.className = 'form-text text-danger';
                }
                timbradoValido = false;
                actualizarEstadoBoton();
                return;
            }
            
            try {
                const response = await fetch(`validar_timbrado.php?timbrado=${encodeURIComponent(timbrado)}`);
                const data = await response.json();
                
                if (data.success) {
                    timbradoValido = true;
                    if (timbradoStatus) {
                        const fechaVenc = new Date(data.fecha_vencimiento).toLocaleDateString('es-PY');
                        timbradoStatus.textContent = `✓ Timbrado vigente hasta ${fechaVenc}`;
                        timbradoStatus.className = 'form-text text-success';
                        
                        if (!data.hay_numeros) {
                            timbradoStatus.textContent += ' (Sin números disponibles)';
                            timbradoStatus.className = 'form-text text-warning';
                            timbradoValido = false;
                        }
                    }
                } else {
                    timbradoValido = false;
                    if (timbradoStatus) {
                        timbradoStatus.textContent = data.message || 'Timbrado inválido';
                        timbradoStatus.className = 'form-text text-danger';
                    }
                }
            } catch (error) {
                console.error('Error al validar timbrado:', error);
                timbradoValido = false;
                if (timbradoStatus) {
                    timbradoStatus.textContent = 'Error al validar timbrado';
                    timbradoStatus.className = 'form-text text-danger';
                }
            }
            
            updateGuardarState();
        });
    }
    
    // La función updateGuardarState ya maneja el estado del botón, incluyendo timbradoValido
});
</script>

<?php } ?>

