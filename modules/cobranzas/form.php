<?php 
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Verificar si la sesión es válida
if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}

// Verificar si existe el parámetro 'form' en la URL
if (isset($_GET['cobranzas']) && $_GET['form'] == 'add') { 
    require "../../config/database.php";
?>
 <div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">
        <i class="fas fa-plus-circle"></i> Registrar Cobro
    </h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="view.php">Gestionar Cobranzas</a></li>
        <li class="breadcrumb-item active">Nuevo Cobro</li>
    </ol>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="proses.php?act=insert_cobro" method="POST" id="formCobro">
                <?php
                try {
                    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                    $pdo = new PDO($dsn, $user, $pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Generar número de recibo (secuencia)
                    $query = $pdo->query("SELECT MAX(id_cobro) AS id FROM cobros");
                    $data = $query->fetch(PDO::FETCH_ASSOC);
                    $numeroRecibo = 'REC-' . str_pad(($data['id'] !== null ? $data['id'] + 1 : 1), 8, '0', STR_PAD_LEFT);

                    date_default_timezone_set('America/Asuncion');
                    $fecha = date("Y-m-d");
                    $hora = date("H:i:s");

                    $userSesion = $_SESSION['username'];
                    $sqlUser = "
                        SELECT 
                            u.id_usuario,
                            u.username,
                            u.id_sucursal,
                            s.descripcion_sucursal
                        FROM usuarios u
                        JOIN sucursales s ON s.id_sucursal = u.id_sucursal
                        WHERE u.username = :user
                        LIMIT 1
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

                    // Verificar caja abierta
                    $qCaja = $pdo->prepare("
                        SELECT acc.id_apertura, acc.id_caja, c.descripcion_caja
                        FROM apertura_cierre_caja acc
                        JOIN caja c ON c.id_caja = acc.id_caja
                        WHERE acc.id_sucursal = :sucursal_id
                          AND acc.apertura_estado = 'ABIERTA'
                        LIMIT 1
                    ");
                    $qCaja->execute([':sucursal_id' => $sucursalId]);
                    $cajaAbierta = $qCaja->fetch(PDO::FETCH_ASSOC);

                    if (!$cajaAbierta) {
                        echo "<div class='alert alert-danger'>
                                <strong>Error:</strong> No hay caja abierta en la sucursal. Debe abrir una caja antes de registrar cobros.
                                <br><a href='../apertura_cierre_caja/view.php' class='btn btn-primary btn-sm mt-2'>Abrir Caja</a>
                              </div>";
                        exit;
                    }

                } catch (PDOException $e) {
                    die("Error al obtener los datos: " . $e->getMessage());
                }
                ?>
                
                <input type="hidden" name="usuario_id" value="<?= $usuarioId ?>">
                <input type="hidden" name="sucursal_id" value="<?= $sucursalId ?>">
                <input type="hidden" name="apertura_cierre_id" value="<?= $cajaAbierta['id_apertura'] ?>">
                
                <div class="row mb-3">
                    <div class="col-md-2">
                        <label for="fecha" class="form-label">Fecha</label>
                        <input type="text" class="form-control" id="fecha" name="fecha" value="<?= $fecha ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label for="hora" class="form-label">Hora</label>
                        <input type="text" class="form-control" id="hora" name="hora" value="<?= $hora ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="numero_recibo" class="form-label">N° Recibo</label>
                        <input type="text" class="form-control" id="numero_recibo" name="numero_recibo" value="<?= $numeroRecibo ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="usuario" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="usuario" value="<?= htmlspecialchars($usuarioNombre) ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label for="sucursal" class="form-label">Sucursal</label>
                        <input type="text" class="form-control" id="sucursal" value="<?= htmlspecialchars($sucursalNombre) ?>" readonly>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="cliente_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                        <select class="form-control" id="cliente_id" name="cliente_id" required>
                            <option value="">— Seleccione cliente —</option>
                            <?php
                            try {
                                $qClientes = $pdo->query("
                                    SELECT id_cliente, cliente_nombre || ' ' || cliente_apellido AS nombre_completo
                                    FROM clientes
                                    WHERE cliente_estado = 'ACTIVO'
                                    ORDER BY cliente_nombre, cliente_apellido
                                ");
                                foreach ($qClientes as $cliente) {
                                    echo "<option value='{$cliente['id_cliente']}'>{$cliente['nombre_completo']}</option>";
                                }
                            } catch (PDOException $e) {
                                echo "<option value=''>Error al cargar clientes</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="2"></textarea>
                    </div>
                </div>

                <!-- Facturas a Cobrar -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">Facturas a Cobrar</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-md-12">
                                <button type="button" class="btn btn-sm btn-primary" id="btn-agregar-factura" data-toggle="modal" data-target="#modalAgregarFactura">
                                    <i class="fas fa-plus"></i> Agregar Factura
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="tabla-facturas">
                                <thead>
                                    <tr>
                                        <th>N° Factura</th>
                                        <th>Fecha</th>
                                        <th>Total Factura</th>
                                        <th>Saldo Pendiente</th>
                                        <th>Importe a Cobrar</th>
                                        <th>Pagos</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-facturas">
                                    <!-- Se llenará dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Medios de Pago por Factura -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">Medios de Pago por Factura</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="tabla-pagos-facturas">
                                <thead>
                                    <tr>
                                        <th>N° Factura</th>
                                        <th>Tipo de Pago</th>
                                        <th>Importe</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-pagos-facturas">
                                    <!-- Se llenará dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Resumen -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Total a Cobrar (Facturas)</label>
                        <input type="text" class="form-control" id="total_facturas" readonly value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Total Pagos</label>
                        <input type="text" class="form-control" id="total_pagos" readonly value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Vuelto</label>
                        <input type="text" class="form-control" id="vuelto" readonly value="0">
                    </div>
                </div>

                <input type="hidden" name="facturas_json" id="facturas_json">
                <input type="hidden" name="pagos_json" id="pagos_json">
                <input type="hidden" name="total_cobrado" id="total_cobrado_hidden">

                <div class="d-flex justify-content-end">
                    <button type="submit" id="btn-guardar" class="btn btn-success mx-2" disabled>Guardar Cobro</button>
                    <a href="view.php" class="btn btn-danger mx-2">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Agregar Factura -->
<div class="modal fade" id="modalAgregarFactura" tabindex="-1" role="dialog" aria-labelledby="modalAgregarFacturaLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAgregarFacturaLabel">Agregar Factura</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="select-factura-modal">Seleccione Factura</label>
                    <select class="form-control" id="select-factura-modal">
                        <option value="">— Seleccione factura —</option>
                    </select>
                    <small class="form-text text-muted" id="saldo-disponible"></small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-confirmar-factura">Agregar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Agregar Pago -->
<div class="modal fade" id="modalAgregarPago" tabindex="-1" role="dialog" aria-labelledby="modalAgregarPagoLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAgregarPagoLabel">Agregar Medio de Pago</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Factura</label>
                    <input type="text" class="form-control" id="factura-pago-display" readonly>
                    <input type="hidden" id="factura-pago-id">
                    <input type="hidden" id="factura-pago-disponible">
                </div>
                <div class="form-group">
                    <label for="select-tipo-pago-modal">Tipo de Pago <span class="text-danger">*</span></label>
                    <select class="form-control" id="select-tipo-pago-modal" required>
                        <option value="">— Seleccione tipo —</option>
                        <option value="EFECTIVO">EFECTIVO</option>
                        <option value="TARJETA">TARJETA</option>
                        <option value="TRANSFERENCIA">TRANSFERENCIA</option>
                        <option value="CHEQUE">CHEQUE</option>
                        <option value="BILLETERA">BILLETERA</option>
                    </select>
                </div>
                
                <!-- Campos específicos para CHEQUE -->
                <div id="campos-cheque" style="display: none;">
                    <div class="form-group">
                        <label for="cheque-banco-modal">Banco <span class="text-danger">*</span></label>
                        <select class="form-control" id="cheque-banco-modal">
                            <option value="">— Seleccione banco —</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="cheque-numero-modal">Número de Cheque <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="cheque-numero-modal" placeholder="Número de cheque">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="cheque-fecha-emision-modal">Fecha de Emisión <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="cheque-fecha-emision-modal">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="cheque-fecha-vencimiento-modal">Fecha de Vencimiento <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="cheque-fecha-vencimiento-modal">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="cheque-tipo-modal">Tipo de Cheque</label>
                        <select class="form-control" id="cheque-tipo-modal">
                            <option value="PROPIO">Propio</option>
                            <option value="TERCEROS">De Terceros</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="importe-pago-modal">Importe <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="importe-pago-modal" step="0.01" min="0.01" placeholder="0.00" required>
                    <small class="form-text text-muted" id="info-importe-efectivo" style="display:none;">Puede ingresar un monto mayor para calcular el vuelto</small>
                    <small class="form-text text-muted" id="info-importe-cheque" style="display:none;">El importe debe coincidir con el monto del cheque</small>
                    <small class="form-text text-danger" id="error-pago" style="display:none;"></small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-confirmar-pago">Agregar</button>
            </div>
        </div>
    </div>
</div>

<script>
const fmtGs = n => (Number(n)||0).toLocaleString('es-PY')+' Gs';
let facturasSeleccionadas = [];
let pagosFacturas = []; // Array de {id_factura_venta, numero_factura, tipo_pago, importe}
let facturasCliente = [];

document.addEventListener('DOMContentLoaded', () => {
    const clienteSelect = document.getElementById('cliente_id');
    const btnAgregarFactura = document.getElementById('btn-agregar-factura');
    const btnAgregarPago = document.getElementById('btn-agregar-pago-factura');
    const form = document.getElementById('formCobro');
    const tbodyFacturas = document.getElementById('tbody-facturas');
    const tbodyPagosFacturas = document.getElementById('tbody-pagos-facturas');
    const vuelto = document.getElementById('vuelto');
    
    // Elementos del modal de factura
    const selectFacturaModal = document.getElementById('select-factura-modal');
    const importeFacturaModal = document.getElementById('importe-factura-modal');
    const saldoDisponible = document.getElementById('saldo-disponible');
    const errorImporte = document.getElementById('error-importe');
    const btnConfirmarFactura = document.getElementById('btn-confirmar-factura');
    
    // Elementos del modal de pago
    const selectTipoPagoModal = document.getElementById('select-tipo-pago-modal');
    const importePagoModal = document.getElementById('importe-pago-modal');
    const errorPago = document.getElementById('error-pago');
    const btnConfirmarPago = document.getElementById('btn-confirmar-pago');
    const infoImporteEfectivo = document.getElementById('info-importe-efectivo');
    
    // Elementos de cheque
    const camposCheque = document.getElementById('campos-cheque');
    const chequeBancoModal = document.getElementById('cheque-banco-modal');
    const chequeNumeroModal = document.getElementById('cheque-numero-modal');
    const chequeFechaEmisionModal = document.getElementById('cheque-fecha-emision-modal');
    const chequeFechaVencimientoModal = document.getElementById('cheque-fecha-vencimiento-modal');
    const chequeTipoModal = document.getElementById('cheque-tipo-modal');
    const infoImporteCheque = document.getElementById('info-importe-cheque');
    
    // Elementos del modal de pago
    const facturaPagoId = document.getElementById('factura-pago-id');
    const facturaPagoDisponible = document.getElementById('factura-pago-disponible');
    const facturaPagoDisplay = document.getElementById('factura-pago-display');
    
    // Cargar bancos al abrir el modal
    $('#modalAgregarPago').on('show.bs.modal', function() {
        if (chequeBancoModal.options.length <= 1) {
            fetch('get_bancos.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.bancos) {
                        chequeBancoModal.innerHTML = '<option value="">— Seleccione banco —</option>';
                        data.bancos.forEach(banco => {
                            chequeBancoModal.innerHTML += `<option value="${banco.id_banco}">${banco.banco_descri}</option>`;
                        });
                    }
                })
                .catch(error => {
                    console.error('Error al cargar bancos:', error);
                });
        }
    });
    
    // Mostrar/ocultar campos de cheque y configurar importe según tipo de pago
    selectTipoPagoModal.addEventListener('change', function() {
        const tipoPago = this.value;
        const disponible = parseFloat(facturaPagoDisponible.value || 0);
        
        if (tipoPago === 'CHEQUE') {
            camposCheque.style.display = 'block';
            infoImporteCheque.style.display = 'block';
            infoImporteEfectivo.style.display = 'none';
            // Hacer campos requeridos
            chequeBancoModal.required = true;
            chequeNumeroModal.required = true;
            chequeFechaEmisionModal.required = true;
            chequeFechaVencimientoModal.required = true;
            // Importe editable, pero con límite al saldo pendiente
            importePagoModal.value = disponible.toFixed(2);
            importePagoModal.readOnly = false;
            importePagoModal.max = disponible; // Limitar al saldo pendiente
        } else if (tipoPago === 'EFECTIVO') {
            camposCheque.style.display = 'none';
            infoImporteCheque.style.display = 'none';
            infoImporteEfectivo.style.display = 'none'; // Ya no se permite exceder por factura
            // Quitar requeridos de cheque
            chequeBancoModal.required = false;
            chequeNumeroModal.required = false;
            chequeFechaEmisionModal.required = false;
            chequeFechaVencimientoModal.required = false;
            // Importe editable, pero no puede exceder el disponible de la factura
            importePagoModal.value = disponible.toFixed(2);
            importePagoModal.readOnly = false;
            importePagoModal.max = disponible; // Limitar al disponible
        } else {
            // TARJETA, TRANSFERENCIA, BILLETERA
            camposCheque.style.display = 'none';
            infoImporteCheque.style.display = 'none';
            infoImporteEfectivo.style.display = 'none';
            // Quitar requeridos de cheque
            chequeBancoModal.required = false;
            chequeNumeroModal.required = false;
            chequeFechaEmisionModal.required = false;
            chequeFechaVencimientoModal.required = false;
            // Importe editable, con límite al saldo pendiente
            importePagoModal.value = disponible.toFixed(2);
            importePagoModal.readOnly = false;
            importePagoModal.max = disponible; // Limitar al saldo pendiente
        }
        
        // Limpiar campos de cheque si no es CHEQUE
        if (tipoPago !== 'CHEQUE') {
            chequeBancoModal.value = '';
            chequeNumeroModal.value = '';
            chequeFechaEmisionModal.value = '';
            chequeFechaVencimientoModal.value = '';
            chequeTipoModal.value = 'PROPIO';
        }
        
        // Limpiar error
        errorPago.style.display = 'none';
    });

    // Cargar facturas del cliente
    clienteSelect.addEventListener('change', async function() {
        const clienteId = this.value;
        facturasSeleccionadas = [];
        pagosFacturas = [];
        facturasCliente = [];
        tbodyFacturas.innerHTML = '';
        tbodyPagosFacturas.innerHTML = '';
        actualizarTotales();

        if (!clienteId) {
            selectFacturaModal.innerHTML = '<option value="">— Seleccione factura —</option>';
            return;
        }

        try {
            const response = await fetch(`get_facturas_cliente.php?cliente_id=${clienteId}`);
            
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.facturas && data.facturas.length > 0) {
                facturasCliente = data.facturas;
                // Actualizar select del modal
                selectFacturaModal.innerHTML = '<option value="">— Seleccione factura —</option>';
                facturasCliente.forEach(fact => {
                    if (fact.saldo_pendiente > 0) {
                        selectFacturaModal.innerHTML += `<option value="${fact.id_factura_venta}" data-saldo="${fact.saldo_pendiente}" data-numero="${fact.numero_factura}">
                            ${fact.numero_factura} - ${fact.fecha_factura} - Saldo: ${fmtGs(fact.saldo_pendiente)}
                        </option>`;
                    }
                });
            } else {
                facturasCliente = [];
                selectFacturaModal.innerHTML = '<option value="">— No hay facturas disponibles —</option>';
                // No mostrar alert si simplemente no hay facturas, solo si hay un error
                if (data.success === false && data.message) {
                    alert('Error: ' + data.message);
                }
            }
        } catch (error) {
            console.error('Error al cargar facturas:', error);
            facturasCliente = [];
            selectFacturaModal.innerHTML = '<option value="">— Error al cargar facturas —</option>';
            alert('Error al cargar facturas del cliente. Por favor, intente nuevamente.');
        }
    });
    
    // Actualizar saldo disponible cuando se selecciona factura en modal
    selectFacturaModal.addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        if (option.value) {
            const saldo = parseFloat(option.dataset.saldo || 0);
            const total = parseFloat(option.dataset.total || 0);
            saldoDisponible.textContent = `Saldo pendiente: ${fmtGs(saldo)} | Total factura: ${fmtGs(total)}`;
            errorImporte.style.display = 'none';
        } else {
            saldoDisponible.textContent = '';
        }
    });
    
    // Confirmar agregar factura
    btnConfirmarFactura.addEventListener('click', function() {
        const facturaId = parseInt(selectFacturaModal.value);
        
        if (!facturaId) {
            alert('Seleccione una factura');
            return;
        }
        
        const factura = facturasCliente.find(f => f.id_factura_venta == facturaId);
        if (!factura) {
            alert('Factura no encontrada');
            return;
        }
        
        // Obtener datos del option seleccionado
        const option = selectFacturaModal.options[selectFacturaModal.selectedIndex];
        const saldoDisponible = parseFloat(option.dataset.saldo || factura.saldo_pendiente);
        const totalGeneral = parseFloat(option.dataset.total || factura.total_general || 0);
        // El saldo pendiente ORIGINAL es el que viene de la factura, no el disponible
        const saldoPendienteOriginal = parseFloat(factura.saldo_pendiente);
        
        // Validar que haya saldo pendiente
        if (saldoDisponible <= 0.01) {
            errorImporte.textContent = 'Esta factura ya ha sido cobrada completamente. No hay saldo pendiente.';
            errorImporte.style.display = 'block';
            return;
        }
        
        // Verificar si ya está agregada
        const yaSeleccionada = facturasSeleccionadas.some(f => f.id_factura_venta == facturaId);
        if (yaSeleccionada) {
            alert('Esta factura ya fue agregada');
            return;
        }
        
        // El importe a cobrar es siempre el total de la factura
        facturasSeleccionadas.push({
            id_factura_venta: factura.id_factura_venta,
            numero_factura: factura.numero_factura,
            fecha_factura: factura.fecha_factura,
            total_general: totalGeneral, // Total original de la factura (este es el Total Factura)
            saldo_pendiente_original: saldoPendienteOriginal, // Saldo pendiente ORIGINAL de la factura (antes de cualquier cobro)
            importe_aplicado: totalGeneral // El importe a cobrar es el total de la factura (siempre se cobra el total)
        });
        
        renderizarFacturas();
        actualizarTotales();
        
        // Cerrar modal y limpiar
        $('#modalAgregarFactura').modal('hide');
        selectFacturaModal.value = '';
        saldoDisponible.textContent = '';
        errorImporte.style.display = 'none';
    });
    
    // Validar importe de pago en tiempo real
    importePagoModal.addEventListener('input', function() {
        const facturaId = parseInt(facturaPagoId.value || 0);
        const disponible = parseFloat(facturaPagoDisponible.value || 0);
        const importe = parseFloat(this.value) || 0;
        
        if (!facturaId || disponible <= 0) return;
        
        // Todos los medios de pago (incluido EFECTIVO) no pueden exceder el disponible de la factura
        // Cada factura debe sumar exactamente su importe
        if (importe > disponible + 0.01) {
            errorPago.textContent = `El importe no puede exceder lo disponible (${fmtGs(disponible)}). La factura debe sumar exactamente su importe.`;
            errorPago.style.display = 'block';
        } else if (importe <= 0) {
            errorPago.textContent = 'El importe debe ser mayor a cero';
            errorPago.style.display = 'block';
        } else {
            errorPago.style.display = 'none';
        }
    });
    
    // Confirmar agregar pago
    btnConfirmarPago.addEventListener('click', function() {
        const facturaId = parseInt(facturaPagoId.value || 0);
        const tipoPago = selectTipoPagoModal.value;
        const disponible = parseFloat(facturaPagoDisponible.value || 0);
        const importe = parseFloat(importePagoModal.value) || 0;
        
        if (!facturaId) {
            alert('No hay factura seleccionada');
            return;
        }
        
        if (!tipoPago) {
            alert('Seleccione un tipo de pago');
            return;
        }
        
        // Validaciones específicas para CHEQUE
        if (tipoPago === 'CHEQUE') {
            if (!chequeBancoModal.value) {
                alert('Seleccione un banco');
                return;
            }
            if (!chequeNumeroModal.value.trim()) {
                alert('Ingrese el número de cheque');
                return;
            }
            if (!chequeFechaEmisionModal.value) {
                alert('Ingrese la fecha de emisión del cheque');
                return;
            }
            if (!chequeFechaVencimientoModal.value) {
                alert('Ingrese la fecha de vencimiento del cheque');
                return;
            }
            // Validar que fecha vencimiento >= fecha emisión
            if (new Date(chequeFechaVencimientoModal.value) < new Date(chequeFechaEmisionModal.value)) {
                alert('La fecha de vencimiento debe ser mayor o igual a la fecha de emisión');
                return;
            }
            // Para CHEQUE, el importe no puede exceder el saldo pendiente
            if (importe > disponible + 0.01) {
                errorPago.textContent = `El importe del cheque no puede exceder el saldo pendiente (${fmtGs(disponible)})`;
                errorPago.style.display = 'block';
                return;
            }
        }
        
        // Validaciones según tipo de pago
        if (importe <= 0) {
            errorPago.textContent = 'El importe debe ser mayor a cero';
            errorPago.style.display = 'block';
            return;
        }
        
        const factura = facturasSeleccionadas.find(f => f.id_factura_venta == facturaId);
        if (!factura) {
            alert('Factura no encontrada');
            return;
        }
        
        // Calcular total de pagos actuales para esta factura
        const totalFactura = factura.total_general || factura.importe_aplicado;
        const totalPagosFactura = pagosFacturas
            .filter(p => p.id_factura_venta == facturaId)
            .reduce((sum, p) => sum + p.importe, 0);
        
        // Saldo Pendiente = TotalFactura - SumaDeMediosDePagoAsociadosALaFactura
        const saldoPendiente = totalFactura - totalPagosFactura;
        
        // Validar que el nuevo pago no exceda el saldo pendiente
        // Si excede, se puede calcular vuelto o limitar al saldo pendiente
        if (importe > saldoPendiente + 0.01) {
            errorPago.textContent = `El importe excede el saldo pendiente. Saldo pendiente: ${fmtGs(saldoPendiente)}. Puede ingresar hasta ${fmtGs(saldoPendiente)}.`;
            errorPago.style.display = 'block';
            return;
        }
        
        // Construir objeto de pago
        const pagoData = {
            id_factura_venta: facturaId,
            numero_factura: factura.numero_factura,
            tipo_pago: tipoPago,
            importe: importe
        };
        
        // Si es CHEQUE, agregar datos del cheque
        if (tipoPago === 'CHEQUE') {
            pagoData.cheque = {
                id_banco: parseInt(chequeBancoModal.value),
                cheque_numero: chequeNumeroModal.value.trim(),
                cheque_fecha_emision: chequeFechaEmisionModal.value,
                cheque_fecha_vencimiento: chequeFechaVencimientoModal.value,
                cheque_tipo: chequeTipoModal.value,
                monto_cheque: importe // El importe debe coincidir con el monto del cheque
            };
        }
        
        pagosFacturas.push(pagoData);
        
        renderizarFacturas(); // Actualizar para mostrar estado de pagos
        renderizarPagosFacturas();
        actualizarTotales();
        
        // Cerrar modal y limpiar
        $('#modalAgregarPago').modal('hide');
        facturaPagoId.value = '';
        facturaPagoDisponible.value = '';
        facturaPagoDisplay.value = '';
        selectTipoPagoModal.value = '';
        importePagoModal.value = '';
        importePagoModal.readOnly = false;
        importePagoModal.removeAttribute('max');
        camposCheque.style.display = 'none';
        chequeBancoModal.value = '';
        chequeNumeroModal.value = '';
        chequeFechaEmisionModal.value = '';
        chequeFechaVencimientoModal.value = '';
        chequeTipoModal.value = 'PROPIO';
        errorPago.style.display = 'none';
        infoImporteEfectivo.style.display = 'none';
        infoImporteCheque.style.display = 'none';
    });

    // Abrir modal de factura
    btnAgregarFactura.addEventListener('click', function() {
        const clienteId = clienteSelect.value;
        
        // Verificar que haya un cliente seleccionado
        if (!clienteId) {
            alert('Por favor, seleccione un cliente primero');
            $('#modalAgregarFactura').modal('hide');
            return;
        }
        
        // Verificar si hay facturas disponibles
        if (facturasCliente.length === 0) {
            alert('Este cliente no tiene facturas con saldo pendiente disponibles para cobrar');
            $('#modalAgregarFactura').modal('hide');
            return;
        }
        
        // Filtrar facturas ya seleccionadas
        selectFacturaModal.innerHTML = '<option value="">— Seleccione factura —</option>';
        facturasCliente.forEach(fact => {
            const yaSeleccionada = facturasSeleccionadas.some(f => f.id_factura_venta == fact.id_factura_venta);
            if (!yaSeleccionada && fact.saldo_pendiente > 0) {
                selectFacturaModal.innerHTML += `<option value="${fact.id_factura_venta}" data-saldo="${fact.saldo_pendiente}" data-numero="${fact.numero_factura}">
                    ${fact.numero_factura} - ${fact.fecha_factura} - Saldo: ${fmtGs(fact.saldo_pendiente)}
                </option>`;
            }
        });
    });
    

    function renderizarFacturas() {
        tbodyFacturas.innerHTML = '';
        facturasSeleccionadas.forEach((fact, index) => {
            const tr = document.createElement('tr');
            // Total Factura: total_general (total original de la factura)
            const totalFactura = fact.total_general || fact.importe_aplicado;
            
            // Calcular total de pagos ya aplicados a esta factura
            const totalPagosFactura = pagosFacturas
                .filter(p => p.id_factura_venta == fact.id_factura_venta)
                .reduce((sum, p) => sum + p.importe, 0);
            
            // Saldo Pendiente = TotalFactura - SumaDeMediosDePagoAsociadosALaFactura
            const saldoPendienteFactura = Math.max(0, totalFactura - totalPagosFactura);
            
            // Disponible para agregar más pagos (igual al saldo pendiente)
            const disponibleParaPago = saldoPendienteFactura;
            
            // Determinar estado de pagos
            let estadoPagos = '';
            if (saldoPendienteFactura <= 0.01) {
                estadoPagos = '<span class="badge badge-success">Completo</span>';
            } else {
                estadoPagos = `<button type="button" class="btn btn-sm btn-success" onclick="abrirModalPago(${index})" title="Agregar pago - Saldo pendiente: ${fmtGs(disponibleParaPago)}">
                    <i class="fas fa-plus"></i> Agregar Pago
                </button>`;
            }
            
            tr.innerHTML = `
                <td>${fact.numero_factura}</td>
                <td>${fact.fecha_factura}</td>
                <td>${fmtGs(totalFactura)}</td>
                <td>${fmtGs(saldoPendienteFactura)}</td>
                <td>${fmtGs(totalFactura)}</td>
                <td>${estadoPagos}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger" onclick="quitarFactura(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            `;
            tbodyFacturas.appendChild(tr);
        });
    }
    
    // Función para abrir modal de pago con factura preseleccionada
    window.abrirModalPago = function(indexFactura) {
        const factura = facturasSeleccionadas[indexFactura];
        if (!factura) return;
        
        // Calcular disponible para pago
        // Saldo Pendiente = TotalFactura - SumaDeMediosDePagoAsociadosALaFactura
        const totalFactura = factura.total_general || factura.importe_aplicado;
        const totalPagosFactura = pagosFacturas
            .filter(p => p.id_factura_venta == factura.id_factura_venta)
            .reduce((sum, p) => sum + p.importe, 0);
        const disponible = Math.max(0, totalFactura - totalPagosFactura);
        
        if (disponible <= 0) {
            alert('Esta factura ya tiene todos sus pagos completos');
            return;
        }
        
        // Configurar modal con la factura seleccionada
        facturaPagoId.value = factura.id_factura_venta;
        facturaPagoDisponible.value = disponible;
        facturaPagoDisplay.value = `${factura.numero_factura} - Disponible: ${fmtGs(disponible)}`;
        
        // Limpiar campos
        selectTipoPagoModal.value = '';
        importePagoModal.value = disponible.toFixed(2);
        importePagoModal.readOnly = false;
        importePagoModal.max = disponible; // Limitar al disponible
        camposCheque.style.display = 'none';
        infoImporteEfectivo.style.display = 'none';
        infoImporteCheque.style.display = 'none';
        errorPago.style.display = 'none';
        
        // Limpiar campos de cheque
        chequeBancoModal.value = '';
        chequeNumeroModal.value = '';
        chequeFechaEmisionModal.value = '';
        chequeFechaVencimientoModal.value = '';
        chequeTipoModal.value = 'PROPIO';
        chequeBancoModal.required = false;
        chequeNumeroModal.required = false;
        chequeFechaEmisionModal.required = false;
        chequeFechaVencimientoModal.required = false;
        
        // Abrir modal
        $('#modalAgregarPago').modal('show');
    };

    function renderizarPagosFacturas() {
        tbodyPagosFacturas.innerHTML = '';
        pagosFacturas.forEach((pago, index) => {
            const tr = document.createElement('tr');
            let tipoPagoDisplay = pago.tipo_pago;
            if (pago.tipo_pago === 'CHEQUE' && pago.cheque) {
                tipoPagoDisplay = `CHEQUE - N° ${pago.cheque.cheque_numero}`;
            }
            tr.innerHTML = `
                <td>${pago.numero_factura}</td>
                <td>${tipoPagoDisplay}</td>
                <td>${fmtGs(pago.importe)}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger" onclick="quitarPagoFactura(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            `;
            tbodyPagosFacturas.appendChild(tr);
        });
    }

    window.quitarFactura = function(index) {
        const factura = facturasSeleccionadas[index];
        // Eliminar todos los pagos de esta factura
        pagosFacturas = pagosFacturas.filter(p => p.id_factura_venta != factura.id_factura_venta);
        facturasSeleccionadas.splice(index, 1);
        renderizarFacturas();
        renderizarPagosFacturas();
        actualizarTotales();
    };

    window.quitarPagoFactura = function(index) {
        pagosFacturas.splice(index, 1);
        renderizarFacturas(); // Actualizar para mostrar botón "Agregar Pago" si corresponde
        renderizarPagosFacturas();
        actualizarTotales();
    };

    function calcularVuelto() {
        const totalFacturas = facturasSeleccionadas.reduce((sum, f) => sum + f.importe_aplicado, 0);
        const totalEfectivo = pagosFacturas.reduce((sum, p) => sum + (p.tipo_pago === 'EFECTIVO' ? p.importe : 0), 0);
        // Vuelto = Total Efectivo - Total a Cobrar (si es positivo, sino 0)
        const vueltoCalc = Math.max(0, totalEfectivo - totalFacturas);
        vuelto.value = fmtGs(vueltoCalc);
    }

    function actualizarTotales() {
        // Total a Cobrar = Suma de TotalFactura de todas las facturas
        const totalFacturas = facturasSeleccionadas.reduce((sum, f) => {
            const totalFactura = f.total_general || f.importe_aplicado;
            return sum + totalFactura;
        }, 0);
        const totalPagos = pagosFacturas.reduce((sum, p) => sum + p.importe, 0);
        
        document.getElementById('total_facturas').value = fmtGs(totalFacturas);
        document.getElementById('total_pagos').value = fmtGs(totalPagos);
        // El total cobrado es el total de facturas (no incluye vuelto)
        document.getElementById('total_cobrado_hidden').value = totalFacturas;

        calcularVuelto();
        
        // Validar que todas las facturas tengan saldo pendiente = 0
        // El botón "Guardar Cobro" solo se habilita cuando TODAS las facturas tienen SaldoPendiente == 0
        let todasFacturasCompletas = true;
        let facturaIncompleta = null;
        facturasSeleccionadas.forEach(fact => {
            const totalFactura = fact.total_general || fact.importe_aplicado;
            const totalPagosFactura = pagosFacturas
                .filter(p => p.id_factura_venta == fact.id_factura_venta)
                .reduce((sum, p) => sum + p.importe, 0);
            // Saldo Pendiente = TotalFactura - SumaDeMediosDePagoAsociadosALaFactura
            const saldoPendiente = totalFactura - totalPagosFactura;
            if (saldoPendiente > 0.01) {
                todasFacturasCompletas = false;
                if (!facturaIncompleta) {
                    facturaIncompleta = fact;
                }
            }
        });

        // Habilitar botón "Guardar Cobro" solo si:
        // - Hay facturas y pagos
        // - TODAS las facturas tienen SaldoPendiente == 0
        const puedeGuardar = facturasSeleccionadas.length > 0 && 
                            pagosFacturas.length > 0 && 
                            todasFacturasCompletas;
        document.getElementById('btn-guardar').disabled = !puedeGuardar;
        
        // Mostrar advertencia si hay facturas incompletas
        if (facturasSeleccionadas.length > 0 && !todasFacturasCompletas && facturaIncompleta) {
            const btnGuardar = document.getElementById('btn-guardar');
            const totalFactura = facturaIncompleta.total_general || facturaIncompleta.importe_aplicado;
            const totalPagosFactura = pagosFacturas
                .filter(p => p.id_factura_venta == facturaIncompleta.id_factura_venta)
                .reduce((sum, p) => sum + p.importe, 0);
            const saldoPendiente = totalFactura - totalPagosFactura;
            btnGuardar.title = `La factura ${facturaIncompleta.numero_factura} tiene saldo pendiente: ${fmtGs(saldoPendiente)}. Debe completar todos los pagos antes de guardar.`;
        } else {
            document.getElementById('btn-guardar').title = '';
        }
    }

    form.addEventListener('submit', function(e) {
        if (facturasSeleccionadas.length === 0) {
            e.preventDefault();
            alert('Debe agregar al menos una factura');
            return false;
        }

        if (pagosFacturas.length === 0) {
            e.preventDefault();
            alert('Debe agregar al menos un medio de pago');
            return false;
        }

        // Total a Cobrar = Suma de TotalFactura de todas las facturas
        const totalFacturas = facturasSeleccionadas.reduce((sum, f) => {
            const totalFactura = f.total_general || f.importe_aplicado;
            return sum + totalFactura;
        }, 0);
        const totalPagos = pagosFacturas.reduce((sum, p) => sum + p.importe, 0);

        // Validar que todas las facturas tengan saldo pendiente = 0
        // GuardarCobro.enabled = (para TODAS las facturas seleccionadas, SaldoPendiente == 0)
        facturasSeleccionadas.forEach(fact => {
            const totalFactura = fact.total_general || fact.importe_aplicado;
            const totalPagosFactura = pagosFacturas
                .filter(p => p.id_factura_venta == fact.id_factura_venta)
                .reduce((sum, p) => sum + p.importe, 0);
            
            // Saldo Pendiente = TotalFactura - SumaDeMediosDePagoAsociadosALaFactura
            const saldoPendiente = totalFactura - totalPagosFactura;
            
            // Si hay saldo pendiente > 0, no se puede guardar
            if (saldoPendiente > 0.01) {
                e.preventDefault();
                alert(`La factura ${fact.numero_factura} tiene saldo pendiente: ${fmtGs(saldoPendiente)}. Debe completar todos los pagos antes de guardar.`);
                return false;
            }
        });
        
        // El vuelto se calcula automáticamente a nivel general (total efectivo - total facturas)
        // Solo aplica cuando el total de efectivo excede el total de facturas

        // Preparar datos para enviar (pagos ya están asociados a facturas)
        // El importe_aplicado es el total de la factura
        const facturasData = facturasSeleccionadas.map(f => {
            const totalFactura = f.total_general || f.importe_aplicado;
            return {
                id_factura_venta: f.id_factura_venta,
                importe_aplicado: totalFactura
            };
        });

        const detalleCobro = pagosFacturas.map(p => {
            const pagoData = {
                id_factura_venta: p.id_factura_venta,
                tipo_pago: p.tipo_pago,
                importe_aplicado: p.importe
            };
            // Si tiene datos de cheque, incluirlos
            if (p.cheque) {
                pagoData.cheque = p.cheque;
            }
            return pagoData;
        });

        document.getElementById('facturas_json').value = JSON.stringify(facturasData);
        document.getElementById('pagos_json').value = JSON.stringify(detalleCobro);
    });
});
</script>

<?php } ?>

