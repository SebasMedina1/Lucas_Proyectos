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
if (isset($_GET['gestionar_ventas']) && $_GET['form'] == 'add') { 
    require "../../config/database.php";
?>
 <div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">
        <i class="fas fa-plus-circle"></i> Registrar Factura de Venta
    </h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="view.php">Gestionar Ventas</a></li>
        <li class="breadcrumb-item active">Nueva Factura</li>
    </ol>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="proses.php?act=insert" method="POST" id="formFactura">
                <?php
                try {
                    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                    $pdo = new PDO($dsn, $user, $pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $query = $pdo->query("SELECT MAX(id_factura_venta) AS id FROM factura_ventas");
                    $data = $query->fetch(PDO::FETCH_ASSOC);
                    $codigo = ($data['id'] !== null) ? $data['id'] + 1 : 1;

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
                                <strong>Error:</strong> No hay caja abierta en la sucursal. Debe abrir una caja antes de emitir facturas.
                                <br><a href='../apertura_cierre_caja/view.php' class='btn btn-primary btn-sm mt-2'>Abrir Caja</a>
                              </div>";
                        exit;
                    }

                    // Obtener timbrado vigente para la caja
                    // La tabla caja_timbrado tiene clave primaria compuesta (id_timbrado, id_caja)
                    // Necesitamos obtener id_timbrado e id_caja para identificar el registro
                    $qTimbrado = $pdo->prepare("
                        SELECT ct.id_timbrado, ct.id_caja, t.timbrado_numero AS timbrado, 
                               ct.punto_expedicion, ct.numero_inicial, ct.numero_final, 
                               ct.numero_actual, ct.fecha_vencimiento, ct.estado
                        FROM caja_timbrado ct
                        JOIN timbrado t ON t.id_timbrado = ct.id_timbrado
                        WHERE ct.id_caja = :caja_id
                          AND ct.estado = 'ACTIVO'
                          AND ct.fecha_vencimiento >= CURRENT_DATE
                          AND (ct.numero_actual IS NULL OR ct.numero_actual < ct.numero_final)
                        ORDER BY ct.fecha_vencimiento ASC
                        LIMIT 1
                    ");
                    $qTimbrado->execute([':caja_id' => $cajaAbierta['id_caja']]);
                    $timbrado = $qTimbrado->fetch(PDO::FETCH_ASSOC);

                    if (!$timbrado) {
                        echo "<div class='alert alert-danger'>
                                <strong>Error:</strong> No hay timbrado vigente disponible para esta caja.
                                <br><small>Verifique que exista un timbrado con estado ACTIVO, fecha de vencimiento vigente y números disponibles.</small>
                              </div>";
                        exit;
                    }

                    // Calcular próximo número de factura
                    $numeroActual = $timbrado['numero_actual'] ?? ($timbrado['numero_inicial'] - 1);
                    $proximoNumero = $numeroActual + 1;
                    $numeroFactura = $timbrado['punto_expedicion'] . '-' . str_pad($proximoNumero, 7, '0', STR_PAD_LEFT);

                } catch (PDOException $e) {
                    die("Error al obtener los datos: " . $e->getMessage());
                }
                ?>
                
                <input type="hidden" name="usuario_id" value="<?= $usuarioId ?>">
                <input type="hidden" name="sucursal_id" value="<?= $sucursalId ?>">
                <input type="hidden" name="apertura_cierre_id" value="<?= $cajaAbierta['id_apertura'] ?>">
                <input type="hidden" name="id_timbrado" value="<?= $timbrado['id_timbrado'] ?>">
                <input type="hidden" name="id_caja_timbrado" value="<?= $timbrado['id_caja'] ?>">
                
                <div class="row mb-3">
                    <div class="col-md-2">
                        <label for="fecha" class="form-label">Fecha</label>
                        <input type="text" class="form-control" id="fecha" name="fecha" value="<?= $fecha ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label for="hora" class="form-label">Hora</label>
                        <input type="text" class="form-control" id="hora" name="hora" value="<?= $hora ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label for="codigo" class="form-label">Factura ID</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?= $codigo ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label for="numero_factura" class="form-label">N° Factura</label>
                        <input type="text" class="form-control" id="numero_factura" name="numero_factura" value="<?= $numeroFactura ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label for="timbrado" class="form-label">Timbrado</label>
                        <input type="text" class="form-control" id="timbrado" name="timbrado" value="<?= $timbrado['timbrado'] ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label for="usuario" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="usuario" value="<?= htmlspecialchars($usuarioNombre) ?>" readonly>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="sucursal" class="form-label">Sucursal</label>
                        <input type="text" class="form-control" id="sucursal" value="<?= htmlspecialchars($sucursalNombre) ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="caja" class="form-label">Caja</label>
                        <input type="text" class="form-control" id="caja" value="<?= htmlspecialchars($cajaAbierta['descripcion_caja']) ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="fecha_emision" class="form-label">Fecha Emisión <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="fecha_emision" name="fecha_emision" max="<?= $fecha ?>" value="<?= $fecha ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="tipo_factura" class="form-label">Tipo de Factura <span class="text-danger">*</span></label>
                        <select class="form-control" id="tipo_factura" name="tipo_factura" required>
                            <option value="CONTADO" selected>Contado</option>
                            <option value="CREDITO">Crédito</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="cliente" class="form-label">Cliente <span class="text-danger">*</span></label>
                        <select class="form-control" id="cliente" name="cliente_id" required>
                            <option value="" selected>Seleccione un Cliente</option>
                            <option value="0">Consumidor Final</option>
                            <?php
                            try {
                                $query_cli = $pdo->query("
                                    SELECT id_cliente, cliente_nombre || ' ' || cliente_apellido AS nombre_completo, cliente_ruc
                                    FROM clientes 
                                    WHERE cliente_estado = 'ACTIVO'
                                      AND cliente_ruc != '9999999'
                                    ORDER BY cliente_nombre ASC
                                ");
                                while ($cli = $query_cli->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"{$cli['id_cliente']}\">{$cli['nombre_completo']} - RUC: {$cli['cliente_ruc']}</option>";
                                }
                            } catch (PDOException $e) {
                                die("Error al cargar clientes: " . $e->getMessage());
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="pedido_venta" class="form-label">Cargar desde Pedido (Opcional)</label>
                        <select class="form-control" id="pedido_venta" name="pedido_venta_id">
                            <option value="">Sin pedido</option>
                            <!-- Los pedidos se cargarán dinámicamente según el cliente seleccionado -->
                        </select>
                    </div>
                </div>

                <!-- Campos para crédito -->
                <div class="row mb-3" id="campos_credito" style="display: none;">
                    <div class="col-md-3">
                        <label for="cuotas" class="form-label">Cuotas</label>
                        <input type="number" class="form-control" id="cuotas" name="cuotas" min="1" max="12" value="1">
                    </div>
                    <div class="col-md-3">
                        <label for="interes_pct" class="form-label">% Interés</label>
                        <input type="number" class="form-control" id="interes_pct" name="interes_pct" step="0.01" min="0" value="0">
                    </div>
                    <div class="col-md-3">
                        <label for="plazo" class="form-label">Plazo</label>
                        <input type="text" class="form-control" id="plazo" name="plazo" placeholder="Se calcula automáticamente" readonly>
                    </div>
                </div>

                <!-- Campo para tipo de pago -->
                <div class="row mb-3" id="campos_contado">
                    <div class="col-md-3">
                        <label for="tipo_pago" class="form-label">Tipo de Pago <span class="text-danger">*</span></label>
                        <select class="form-control" id="tipo_pago" name="tipo_pago" required>
                            <option value="EFECTIVO" selected>Efectivo</option>
                            <option value="TARJETA">Tarjeta</option>
                            <option value="TRANSFERENCIA">Transferencia</option>
                            <option value="CHEQUE">Cheque</option>
                            <option value="BILLETERA">Billetera</option>
                        </select>
                    </div>
                </div>

                <hr>
                <h5>Agregar Productos</h5>
                
                <div class="row align-items-end mb-3">
                    <div class="col-md-5">
                        <label for="producto" class="form-label">Producto</label>
                        <select class="form-control" id="producto" name="producto">
                            <option value="" selected>Seleccione un Producto</option>
                            <?php
                            try {
                                $query_prod = $pdo->query("
                                    SELECT 
                                        p.producto_id, 
                                        p.producto_descri, 
                                        p.producto_precio, 
                                        um.unidad_descri,
                                        COALESCE(ti.iva_descri, 'N/A') AS iva_descri,
                                        COALESCE(p.iva_id, 0) AS iva_id
                                    FROM productos p
                                    JOIN unidad_medida um ON p.id_unidad = um.id_unidad
                                    LEFT JOIN tipo_iva ti ON p.iva_id = ti.iva_id
                                    WHERE p.producto_estado = 'ACTIVO'
                                    ORDER BY p.producto_descri ASC
                                ");
                                while ($prod = $query_prod->fetch(PDO::FETCH_ASSOC)) {
                                    $precio = number_format($prod['producto_precio'], 0, ',', '.');
                                    $iva = $prod['iva_descri'];
                                    $ivaId = (int)$prod['iva_id'];
                                    echo "<option value=\"{$prod['producto_id']}\" 
                                            data-precio=\"{$prod['producto_precio']}\" 
                                            data-iva=\"{$iva}\"
                                            data-iva-id=\"{$ivaId}\"
                                            data-unidad=\"{$prod['unidad_descri']}\">
                                            {$prod['producto_descri']} - Precio: {$precio} - IVA: {$iva} - Unidad: {$prod['unidad_descri']}
                                          </option>";
                                }
                            } catch (PDOException $e) {
                                die("Error al cargar productos: " . $e->getMessage());
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="cantidad_producto" class="form-label">Cantidad</label>
                        <input type="number" class="form-control" id="cantidad_producto" min="1" step="1" placeholder="Ingrese cantidad">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Stock Disponible</label>
                        <input type="text" class="form-control" id="stock_disponible" readonly placeholder="Seleccione producto">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-primary w-100" id="btn-agregar">Agregar</button>
                    </div>
                </div>

                <!-- Tabla de productos -->
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-striped" id="tabla-productos">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio Unit.</th>
                                <th>IVA</th>
                                <th>Subtotal</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                        <tfoot>
                            <tr class="table-info">
                                <td colspan="6" class="text-right font-weight-bold">TOTAL:</td>
                                <td class="font-weight-bold" id="total-factura">0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Subtotal (sin IVA):</label>
                        <input type="text" class="form-control" id="subtotal-sin-iva" readonly value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">IVA 5%:</label>
                        <input type="text" class="form-control" id="iva-5" readonly value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">IVA 10%:</label>
                        <input type="text" class="form-control" id="iva-10" readonly value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Total IVA:</label>
                        <input type="text" class="form-control" id="total-iva" readonly value="0">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="2" placeholder="Observaciones adicionales"></textarea>
                    </div>
                </div>

                <input type="hidden" name="productos" id="productos">
                <input type="hidden" name="subtotal" id="subtotal">
                <input type="hidden" name="iva_5" id="iva_5">
                <input type="hidden" name="iva_10" id="iva_10">
                <input type="hidden" name="iva_exento" id="iva_exento">
                <input type="hidden" name="total_general" id="total_general">

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-success me-2" name="Guardar" id="btn-guardar">Emitir Factura</button>
                    <a href="view.php" class="btn btn-warning me-2">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const productos = [];
    window.productos = productos;
    const tablaProductos = document.getElementById('tabla-productos');
    const tbody = tablaProductos ? tablaProductos.querySelector('tbody') : null;
    const form = document.getElementById('formFactura');
    const tipoFactura = document.getElementById('tipo_factura');
    const camposCredito = document.getElementById('campos_credito');
    const pedidoSelect = document.getElementById('pedido_venta');
    const clienteSelect = document.getElementById('cliente');

    if (!form || !tbody) return;

    const camposContado = document.getElementById('campos_contado');
    const tipoPago = document.getElementById('tipo_pago');
    
    // Función para actualizar opciones de tipo de pago según tipo de factura
    function actualizarTipoPago(esCredito) {
        if (!tipoPago) return;
        
        // Guardar el valor actual antes de limpiar
        const valorActual = tipoPago.value;
        
        // Limpiar todas las opciones
        tipoPago.innerHTML = '';
        
        if (esCredito) {
            // Para CRÉDITO: solo mostrar Tarjeta
            const opcionTarjeta = document.createElement('option');
            opcionTarjeta.value = 'TARJETA';
            opcionTarjeta.textContent = 'Tarjeta';
            opcionTarjeta.selected = true;
            tipoPago.appendChild(opcionTarjeta);
        } else {
            // Para CONTADO: mostrar todas las opciones
            const opciones = [
                { value: 'EFECTIVO', text: 'Efectivo' },
                { value: 'TARJETA', text: 'Tarjeta' },
                { value: 'TRANSFERENCIA', text: 'Transferencia' },
                { value: 'CHEQUE', text: 'Cheque' },
                { value: 'BILLETERA', text: 'Billetera' }
            ];
            
            opciones.forEach(opcion => {
                const option = document.createElement('option');
                option.value = opcion.value;
                option.textContent = opcion.text;
                if (opcion.value === valorActual || (valorActual === 'TARJETA' && opcion.value === 'EFECTIVO')) {
                    option.selected = true;
                }
                tipoPago.appendChild(option);
            });
        }
    }
    
    // Función para actualizar el plazo según las cuotas
    function actualizarPlazo() {
        const plazoInput = document.getElementById('plazo');
        const cuotasInput = document.getElementById('cuotas');
        
        if (!plazoInput || !cuotasInput) return;
        
        // Solo actualizar si es crédito
        if (tipoFactura.value === 'CREDITO') {
            const cuotas = parseInt(cuotasInput.value) || 0;
            if (cuotas > 0) {
                plazoInput.value = `${cuotas} ${cuotas === 1 ? 'mes' : 'meses'}`;
            } else {
                plazoInput.value = '';
            }
        } else {
            plazoInput.value = '';
        }
    }
    
    // Escuchar cambios en las cuotas
    const cuotasInput = document.getElementById('cuotas');
    if (cuotasInput) {
        cuotasInput.addEventListener('input', actualizarPlazo);
        cuotasInput.addEventListener('change', actualizarPlazo);
    }
    
    // Mostrar/ocultar campos de crédito y contado
    tipoFactura.addEventListener('change', function() {
        if (this.value === 'CREDITO') {
            camposCredito.style.display = 'block';
            if (camposContado) camposContado.style.display = 'block';
            document.getElementById('cuotas').required = true;
            if (tipoPago) {
                tipoPago.required = true;
                actualizarTipoPago(true); // Solo Tarjeta para crédito
            }
            // Actualizar plazo al cambiar a crédito
            actualizarPlazo();
        } else {
            camposCredito.style.display = 'none';
            if (camposContado) camposContado.style.display = 'block';
            document.getElementById('cuotas').required = false;
            if (tipoPago) {
                tipoPago.required = true;
                actualizarTipoPago(false); // Todas las opciones para contado
            }
            // Limpiar plazo al cambiar a contado
            const plazoInput = document.getElementById('plazo');
            if (plazoInput) plazoInput.value = '';
        }
    });
    
    // Inicializar visibilidad y opciones según el tipo de factura seleccionado
    if (camposContado) camposContado.style.display = 'block';
    if (tipoPago) {
        tipoPago.required = true;
        // Inicializar opciones según el tipo de factura inicial
        const tipoInicial = tipoFactura.value;
        actualizarTipoPago(tipoInicial === 'CREDITO');
    }

    // Cargar pedidos según cliente seleccionado
    async function cargarPedidosPorCliente(clienteId) {
        if (!pedidoSelect) return;
        
        // Limpiar opciones actuales (excepto "Sin pedido")
        pedidoSelect.innerHTML = '<option value="">Sin pedido</option>';
        
        // Si no hay cliente seleccionado o es Consumidor Final (0), no cargar pedidos
        if (!clienteId || clienteId === '0' || clienteId === '') {
            return;
        }
        
        try {
            const response = await fetch(`get_pedidos_cliente.php?cliente_id=${clienteId}`);
            const data = await response.json();
            
            if (data.success && data.pedidos && data.pedidos.length > 0) {
                data.pedidos.forEach(pedido => {
                    const option = document.createElement('option');
                    option.value = pedido.id_pedido_venta;
                    option.textContent = `Pedido #${pedido.id_pedido_venta} - ${pedido.cliente_nombre} (${pedido.pedido_fecha})`;
                    pedidoSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error al cargar pedidos:', error);
        }
    }
    
    // Escuchar cambios en el cliente
    if (clienteSelect) {
        clienteSelect.addEventListener('change', function() {
            const clienteId = this.value;
            cargarPedidosPorCliente(clienteId);
            
            // Limpiar el pedido seleccionado y la tabla de productos cuando cambia el cliente
            if (pedidoSelect) {
                pedidoSelect.value = '';
            }
            if (tbody) {
                tbody.innerHTML = '';
                productos.length = 0;
                calcularTotales();
            }
        });
    }

    // Cargar desde pedido
    if (pedidoSelect) {
        pedidoSelect.addEventListener('change', async function() {
            const pedidoId = this.value;
            if (!pedidoId) {
                tbody.innerHTML = '';
                productos.length = 0;
                calcularTotales();
                return;
            }

            try {
                const response = await fetch(`get_pedido_detalle.php?pedido_id=${pedidoId}`);
                const data = await response.json();
                
                if (data.success && data.detalle) {
                    productos.length = 0;
                    tbody.innerHTML = '';
                    
                    data.detalle.forEach(item => {
                        productos.push({
                            codigo: item.producto_id,
                            cantidad: item.cantidad_pedido,
                            precio: item.precio_unitario,
                            iva: item.iva_descri || 'N/A',
                            ivaId: item.iva_id || 0,
                            ivaPorcentaje: item.iva_porcentaje || 0
                        });
                        
                        const row = document.createElement('tr');
                        const subtotal = item.cantidad_pedido * item.precio_unitario;
                        const ivaMonto = subtotal * (item.iva_porcentaje || 0) / 100;
                        const total = subtotal + ivaMonto;
                        
                        row.innerHTML = `
                            <td>${productos.length}</td>
                            <td>${item.producto_id}</td>
                            <td>${item.nombre_producto}</td>
                            <td>${item.cantidad_pedido}</td>
                            <td>${new Intl.NumberFormat('es-PY').format(item.precio_unitario)}</td>
                            <td>${item.iva_descri || 'N/A'}</td>
                            <td>${new Intl.NumberFormat('es-PY').format(total)}</td>
                            <td><button type="button" class="btn btn-danger btn-sm btn-eliminar">Quitar</button></td>
                        `;
                        tbody.appendChild(row);
                    });
                    
                    calcularTotales();
                } else {
                    alert('No se pudo cargar el detalle del pedido');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al cargar el detalle del pedido');
            }
        });
    }

    // Validar que haya productos antes de enviar
    form.addEventListener('submit', function (e) {
        if (productos.length === 0) {
            e.preventDefault();
            alert('Por favor, agregue como mínimo un producto');
            return false;
        }
        
        // Validar que se haya seleccionado un cliente
        const cliente = document.getElementById('cliente');
        if (!cliente || !cliente.value) {
            e.preventDefault();
            alert('Por favor, seleccione un cliente');
            cliente.focus();
            return false;
        }

        // Validar crédito
        if (tipoFactura.value === 'CREDITO') {
            const cuotas = parseInt(document.getElementById('cuotas').value) || 0;
            if (cuotas < 1) {
                e.preventDefault();
                alert('Para facturas a crédito, debe especificar al menos 1 cuota');
                return false;
            }
        }
    });

    // Función para calcular totales
    function calcularTotales() {
        let subtotal = 0;
        let iva5 = 0;
        let iva10 = 0;
        let ivaExento = 0;
        let total = 0;
        
        productos.forEach(p => {
            const sub = p.cantidad * p.precio;
            subtotal += sub;
            
            const ivaPorcentaje = p.ivaPorcentaje || 0;
            if (ivaPorcentaje === 5) {
                iva5 += sub * 0.05;
            } else if (ivaPorcentaje === 10) {
                iva10 += sub * 0.10;
            } else {
                ivaExento += sub;
            }
            
            total += sub * (1 + ivaPorcentaje / 100);
        });
        
        // Actualizar totales
        document.getElementById('subtotal-sin-iva').value = new Intl.NumberFormat('es-PY').format(subtotal);
        document.getElementById('iva-5').value = new Intl.NumberFormat('es-PY').format(iva5);
        document.getElementById('iva-10').value = new Intl.NumberFormat('es-PY').format(iva10);
        document.getElementById('total-iva').value = new Intl.NumberFormat('es-PY').format(iva5 + iva10);
        document.getElementById('total-factura').textContent = new Intl.NumberFormat('es-PY').format(total);
        
        // Hidden inputs
        document.getElementById('subtotal').value = subtotal;
        document.getElementById('iva_5').value = iva5;
        document.getElementById('iva_10').value = iva10;
        document.getElementById('iva_exento').value = ivaExento;
        document.getElementById('total_general').value = total;
        document.getElementById('productos').value = JSON.stringify(productos);
    }

    // Eliminar producto
    if (tbody) {
        tbody.addEventListener('click', function (event) {
            if (event.target.classList.contains('btn-eliminar')) {
                const row = event.target.closest('tr');
                const index = Array.from(tbody.children).indexOf(row);
                productos.splice(index, 1);
                row.remove();
                
                // Actualizar índices
                Array.from(tbody.children).forEach((row, idx) => {
                    row.children[0].innerText = idx + 1;
                });
                
                calcularTotales();
            }
        });
    }

    // Agregar producto
    const btnAgregar = document.getElementById('btn-agregar');
    const cantidadInput = document.getElementById('cantidad_producto');
    const productoSelect = document.getElementById('producto');
    const stockInput = document.getElementById('stock_disponible');

    // Cargar stock cuando se selecciona un producto
    if (productoSelect && stockInput) {
        productoSelect.addEventListener('change', function() {
            const productoId = this.value;
            if (productoId) {
                fetch(`get_stock.php?producto_id=${productoId}`)
                    .then(resp => resp.json())
                    .then(data => {
                        if (data.stock_existencia !== undefined) {
                            stockInput.value = data.stock_existencia;
                            stockInput.style.backgroundColor = data.stock_existencia > 0 ? '#d4edda' : '#f8d7da';
                        } else {
                            stockInput.value = 'N/A';
                            stockInput.style.backgroundColor = '#fff';
                        }
                    })
                    .catch(() => {
                        stockInput.value = 'N/A';
                        stockInput.style.backgroundColor = '#fff';
                    });
            } else {
                stockInput.value = '';
                stockInput.style.backgroundColor = '#fff';
            }
        });
    }

    if (btnAgregar && cantidadInput && productoSelect) {
        btnAgregar.addEventListener('click', function (e) {
            const productoId = productoSelect.value;
            const cantidad = parseInt(cantidadInput.value, 10);
            const option = productoSelect.options[productoSelect.selectedIndex];

            if (!productoId || isNaN(cantidad) || cantidad < 1) {
                alert('Por favor, seleccione un producto y especifique una cantidad válida.');
                return;
            }

            // Validar stock disponible
            if (stockInput && stockInput.value !== '' && stockInput.value !== 'N/A') {
                const stockDisponible = parseInt(stockInput.value, 10);
                if (!isNaN(stockDisponible) && cantidad > stockDisponible) {
                    if (!confirm(`La cantidad solicitada (${cantidad}) excede el stock disponible (${stockDisponible}). ¿Desea continuar de todas formas?`)) {
                        return;
                    }
                }
            }

            // Evitar duplicados
            const yaExiste = productos.some(p => String(p.codigo) === String(productoId));
            if (yaExiste) {
                const nombre = option.text.split(' - ')[0];
                alert(`El producto "${nombre}" ya fue agregado al detalle.`);
                return;
            }

            const precio = parseFloat(option.getAttribute('data-precio')) || 0;
            const iva = option.getAttribute('data-iva') || 'N/A';
            const ivaId = parseInt(option.getAttribute('data-iva-id')) || 0;
            
            // Calcular subtotal e IVA
            const subtotal = precio * cantidad;
            let ivaPorcentaje = 0;
            let ivaMonto = 0;
            let total = subtotal;
            
            // Calcular IVA según tasa
            if (ivaId > 0 && iva !== 'N/A') {
                const ivaMatch = iva.match(/(\d+)/);
                if (ivaMatch) {
                    ivaPorcentaje = parseFloat(ivaMatch[1]);
                    ivaMonto = subtotal * (ivaPorcentaje / 100);
                    total = subtotal + ivaMonto;
                }
            }

            // Agregar al array
            productos.push({
                codigo: productoId,
                cantidad: cantidad,
                precio: precio,
                iva: iva,
                ivaId: ivaId,
                ivaPorcentaje: ivaPorcentaje,
                ivaMonto: ivaMonto,
                subtotal: subtotal,
                total: total
            });

            // Agregar fila
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${productos.length}</td>
                <td>${productoId}</td>
                <td>${option.text.split(' - ')[0]}</td>
                <td>${cantidad}</td>
                <td>${new Intl.NumberFormat('es-PY').format(precio)}</td>
                <td>${iva}</td>
                <td>${new Intl.NumberFormat('es-PY').format(total)}</td>
                <td><button type="button" class="btn btn-danger btn-sm btn-eliminar">Quitar</button></td>
            `;
            tbody.appendChild(row);

            // Limpiar campos
            productoSelect.value = '';
            cantidadInput.value = '';
            if (stockInput) {
                stockInput.value = '';
                stockInput.style.backgroundColor = '#fff';
            }

            calcularTotales();
        });
    }

    // Exponer función para uso externo
    window.calcularTotales = calcularTotales;
});
</script>

<?php } ?>

