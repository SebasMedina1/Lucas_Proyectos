<?php
if (!function_exists('inferTipoDesdeDescripcion')) {
  function inferTipoDesdeDescripcion(string $descripcion): string {
    $texto = strtoupper(trim($descripcion));
    // Entradas: Sobrante, Regularización (puede ser +)
    $mapEntradas = ['SOBRANTE'];
    // Salidas: Faltante, Merma, Regularización (puede ser -)
    $mapSalidas  = ['FALTANTE', 'MERMA', 'PRODUCTO DAÑADO', 'PRODUCTO DANADO'];
    
    // Regularización puede ser entrada o salida según el contexto
    // Por defecto, si contiene "REGULARIZACION" y no hay indicador claro, asumimos salida
    if (strpos($texto, 'REGULARIZACION') !== false || strpos($texto, 'REGULARIZACIÓN') !== false) {
      // Si no hay indicador claro, se determinará por el signo del ajuste o se asume salida
      return 'SALIDA'; // Por defecto, pero puede ajustarse según el factor
    }
    
    foreach ($mapEntradas as $needle) {
      if (strpos($texto, $needle) !== false) return 'ENTRADA';
    }
    foreach ($mapSalidas as $needle) {
      if (strpos($texto, $needle) !== false) return 'SALIDA';
    }
    return 'SALIDA';
  }
}

// Detectar modo edición
$modoEdicion = isset($_GET['ajustes']) && $_GET['form'] === 'edit' && isset($_GET['id']);
$idAjusteEditar = $modoEdicion ? (int)$_GET['id'] : 0;

if (isset($_GET['ajustes']) && (($_GET['form'] ?? '') === 'add' || $modoEdicion)) {
  require "../../config/database.php";
  date_default_timezone_set('America/Asuncion');

  try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Si es edición, cargar datos del ajuste
    $ajusteData = null;
    $ajusteDetalle = [];
    if ($modoEdicion && $idAjusteEditar > 0) {
      $stmtAjuste = $pdo->prepare("
        SELECT id_ajuste, ajuste_fecha, ajuste_estado, deposito_id
        FROM ajustes
        WHERE id_ajuste = :id
        LIMIT 1
      ");
      $stmtAjuste->execute([':id' => $idAjusteEditar]);
      $ajusteData = $stmtAjuste->fetch();
      
      if (!$ajusteData) {
        die("Ajuste no encontrado.");
      }
      
      $estadoAjuste = strtoupper(trim($ajusteData['ajuste_estado']));
      if ($estadoAjuste !== 'EMITIDO') {
        die("Solo se pueden editar ajustes en estado EMITIDO.");
      }
      
      // Cargar detalle del ajuste
      // Nota: En edición, el stock_base debe ser el stock actual (no el histórico del ajuste)
      $stmtDet = $pdo->prepare("
        SELECT 
          ad.id_materia_prima,
          ad.ajuste_cantidad AS cantidad,
          mp.materia_prima_descripcion AS producto_descripcion,
          COALESCE(smp.cantidad_existente, 0) AS stock_base,
          ad.id_motivo
        FROM ajustes_detalle ad
        LEFT JOIN materia_prima mp ON mp.id_materia_prima = ad.id_materia_prima
        LEFT JOIN stock_materia_prima smp ON smp.id_materia_prima = ad.id_materia_prima 
          AND smp.deposito_id = :deposito_id
        WHERE ad.id_ajuste = :id
        ORDER BY mp.materia_prima_descripcion
      ");
      $stmtDet->execute([':id' => $idAjusteEditar, ':deposito_id' => $ajusteData['deposito_id']]);
      $ajusteDetalle = $stmtDet->fetchAll();
      
      // Obtener motivo del ajuste (del primer detalle)
      $motivoIdEditar = !empty($ajusteDetalle) ? (int)($ajusteDetalle[0]['id_motivo'] ?? 0) : 0;
    }

    $stmtNext = $pdo->query("SELECT COALESCE(MAX(id_ajuste), 0) + 1 AS next_id FROM ajustes");
    $nextId = $modoEdicion ? $idAjusteEditar : (int)($stmtNext->fetchColumn() ?: 1);

    $username = $_SESSION['username'] ?? '';
    $stmtUser = $pdo->prepare("
      SELECT u.id_usuario, u.username, COALESCE(s.descripcion_sucursal, 'Sin sucursal asignada') AS sucursal
      FROM usuarios u
      LEFT JOIN sucursales s ON s.id_sucursal = u.id_sucursal
      WHERE u.username = :username
      LIMIT 1
    ");
    $stmtUser->execute([':username' => $username]);
    $datosUsuario = $stmtUser->fetch() ?: [];
    $nombreUsuario = $datosUsuario['username'] ?? 'N/D';
    $sucursalUsuario = $datosUsuario['sucursal'] ?? 'N/D';

    $depositos = $pdo->query("SELECT deposito_id, deposito_descri FROM deposito ORDER BY deposito_descri ASC")->fetchAll();
    $productos = $pdo->query("
      SELECT id_materia_prima, materia_prima_descripcion
      FROM materia_prima
      WHERE materia_prima_estado = 'ACTIVO'
      ORDER BY materia_prima_descripcion ASC
    ")->fetchAll();

    // Filtrar motivos: solo los de categoría AJUSTE
    // Primero verificar si existe la columna categoria_motivo
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
      // Usar el campo categoria_motivo si existe
      $motivosRaw = $pdo->prepare("
        SELECT id_motivo, motivo_descripcion
        FROM motivo
        WHERE categoria_motivo = 'AJUSTE'
        ORDER BY motivo_descripcion ASC
      ");
      $motivosRaw->execute();
      $motivosRaw = $motivosRaw->fetchAll();
    } else {
      // Fallback: buscar por nombre si no existe el campo categoria_motivo
      $motivosRaw = $pdo->prepare("
        SELECT id_motivo, motivo_descripcion
        FROM motivo
        WHERE UPPER(TRIM(motivo_descripcion)) IN (
          UPPER('Faltante'), 
          UPPER('Sobrante'), 
          UPPER('Merma'), 
          UPPER('Regularización'),
          UPPER('Regularizacion')
        )
        ORDER BY motivo_descripcion ASC
      ");
      $motivosRaw->execute();
      $motivosRaw = $motivosRaw->fetchAll();
    }
    
    $motivos = array_map(function ($row) {
      return [
        'id' => (int)$row['id_motivo'],
        'descripcion' => $row['motivo_descripcion'],
        'tipo' => inferTipoDesdeDescripcion($row['motivo_descripcion'])
      ];
    }, $motivosRaw);
  } catch (Throwable $e) {
    die("No se pudo preparar el formulario de ajustes: " . $e->getMessage());
  }

  $fechaHoy = $modoEdicion && $ajusteData ? $ajusteData['ajuste_fecha'] : date("Y-m-d");
  $horaActual = date("H:i");
  ?>

  <div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">
      <i class="fas fa-<?= $modoEdicion ? 'edit' : 'plus-circle' ?>"></i> <?= $modoEdicion ? 'Editar' : 'Registrar' ?> Ajuste
    </h1>
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
      <li class="breadcrumb-item"><a href="view.php">Ajustes</a></li>
      <li class="breadcrumb-item active"><?= $modoEdicion ? 'Editar Ajuste' : 'Nuevo Ajuste' ?></li>
    </ol>

    <div class="card shadow mb-4">
      <div class="card-body">
        <form id="form-ajuste" action="proses.php?act=<?= $modoEdicion ? 'update' : 'insert' ?>" method="POST">
          <?php if ($modoEdicion): ?>
            <input type="hidden" name="id_ajuste" value="<?= htmlspecialchars($idAjusteEditar) ?>">
          <?php endif; ?>
          <input type="hidden" name="detalle_json" id="detalle_json" value="[]">
          <div class="row mb-3">
            <div class="col-md-3">
              <label class="form-label">Fecha</label>
              <input type="text" class="form-control" name="ajuste_fecha" value="<?php echo htmlspecialchars($fechaHoy); ?>" readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label">Hora</label>
              <input type="text" class="form-control" value="<?php echo htmlspecialchars($horaActual); ?>" readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label">N.º interno</label>
              <input type="text" class="form-control" value="<?php echo htmlspecialchars($nextId); ?>" readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label">Usuario</label>
              <input type="text" class="form-control" value="<?php echo htmlspecialchars($nombreUsuario); ?>" readonly>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-3">
              <label class="form-label">Sucursal</label>
              <input type="text" class="form-control" value="<?php echo htmlspecialchars($sucursalUsuario); ?>" readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="deposito">Depósito destino</label>
              <select class="form-control" id="deposito" name="deposito_id" required <?= $modoEdicion ? 'disabled' : '' ?>>
                <option value="">Seleccione Depósito</option>
                <?php foreach ($depositos as $deposito): ?>
                  <option value="<?php echo (int)$deposito['deposito_id']; ?>" 
                    <?= ($modoEdicion && $ajusteData && (int)$ajusteData['deposito_id'] === (int)$deposito['deposito_id']) ? 'selected' : '' ?>>
                    <?php echo htmlspecialchars($deposito['deposito_descri']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if ($modoEdicion): ?>
                <input type="hidden" name="deposito_id" value="<?= htmlspecialchars($ajusteData['deposito_id']) ?>">
              <?php endif; ?>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="motivo">Motivo <span class="text-danger">*</span></label>
              <select class="form-control" id="motivo" name="ajuste_motivo_id" required>
                <option value="">Seleccione Motivo</option>
                <?php foreach ($motivos as $motivo): ?>
                  <option value="<?php echo $motivo['id']; ?>" data-tipo="<?php echo htmlspecialchars($motivo['tipo']); ?>"
                    <?= ($modoEdicion && isset($motivoIdEditar) && $motivo['id'] === $motivoIdEditar) ? 'selected' : '' ?>>
                    <?php echo htmlspecialchars($motivo['descripcion']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">Seleccione el motivo antes de agregar ítems</small>
            </div>
            <div class="col-md-2" id="tipo-regularizacion-container" style="display: none;">
              <label class="form-label" for="tipo_regularizacion">Tipo Regularización <span class="text-danger">*</span></label>
              <select class="form-control" id="tipo_regularizacion" name="tipo_regularizacion">
                <option value="ENTRADA">Entrada (+)</option>
                <option value="SALIDA">Salida (-)</option>
              </select>
            </div>
            <div class="col-md-<?= $modoEdicion ? '2' : '3' ?>">
              <label class="form-label">Tipo de ajuste</label>
              <input type="text" class="form-control" id="tipo_ajuste" value="SALIDA" readonly>
            </div>
          </div>

          <div class="alert alert-info mb-3">
            <i class="fas fa-info-circle"></i> Seleccione un depósito para ver los productos disponibles con stock.
          </div>

          <div class="table-responsive mb-3" id="tabla-productos-container" style="display: none;">
            <h5 class="mb-2">Productos disponibles en el depósito</h5>
            <table class="table table-sm table-bordered table-hover" id="tabla-productos">
              <thead class="table-secondary">
                <tr>
                  <th style="width: 60px;">#</th>
                  <th>Producto</th>
                  <th style="width: 120px;">Stock actual</th>
                  <th style="width: 120px;">Cantidad a ajustar</th>
                  <th style="width: 100px;">Acción</th>
                </tr>
              </thead>
              <tbody id="tbody-productos">
                <tr>
                  <td colspan="5" class="text-center text-muted">
                    <i class="fas fa-spinner fa-spin"></i> Cargando productos...
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="table-responsive">
            <h5 class="mb-2">Ítems seleccionados para ajuste</h5>
            <table class="table table-sm table-bordered" id="tabla-detalle">
              <thead class="table-secondary">
                <tr>
                  <th style="width: 60px;">#</th>
                  <th>Producto</th>
                  <th style="width: 120px;">Stock base</th>
                  <th style="width: 120px;">Cantidad</th>
                  <th style="width: 120px;">Tipo</th>
                  <th style="width: 80px;">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr id="detalle-vacio">
                  <td colspan="6" class="text-center text-muted">Sin ítems cargados.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-between align-items-center mt-3">
            <span class="text-muted">Total de ítems: <span id="total-items">0</span></span>
            <div>
              <a href="view.php" class="btn btn-outline-secondary">Cancelar</a>
              <button type="submit" class="btn btn-success" id="btn-guardar" disabled>Guardar</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalDuplicado" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Producto duplicado</h5>
          <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          El producto seleccionado ya fue agregado al detalle.
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" data-dismiss="modal" data-bs-dismiss="modal">Entendido</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    (function() {
      const depositoSelect = document.getElementById('deposito');
      const motivoSelect = document.getElementById('motivo');
      const tipoInput = document.getElementById('tipo_ajuste');
      const btnGuardar = document.getElementById('btn-guardar');
      const detalleInput = document.getElementById('detalle_json');
      const tbody = document.querySelector('#tabla-detalle tbody');
      const tbodyProductos = document.getElementById('tbody-productos');
      const tablaProductosContainer = document.getElementById('tabla-productos-container');
      const totalItemsLabel = document.getElementById('total-items');
      const form = document.getElementById('form-ajuste');
      const modalDuplicadoEl = document.getElementById('modalDuplicado');
      let modalDuplicadoInstance = null;
      if (modalDuplicadoEl) {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
          modalDuplicadoInstance = new bootstrap.Modal(modalDuplicadoEl);
        } else if (window.jQuery && typeof jQuery.fn.modal === 'function') {
          modalDuplicadoInstance = {
            show: () => window.jQuery(modalDuplicadoEl).modal('show')
          };
        }
      }

      function mostrarModalDuplicado(mensaje) {
        if (modalDuplicadoEl) {
          const body = modalDuplicadoEl.querySelector('.modal-body');
          if (body) body.textContent = mensaje;
        }
        if (modalDuplicadoInstance && typeof modalDuplicadoInstance.show === 'function') {
          modalDuplicadoInstance.show();
        } else {
          alert(mensaje);
        }
      }

      let detalle = [];
      let depositoFijado = null;
      let productosDisponibles = [];

      const tipoRegularizacionSelect = document.getElementById('tipo_regularizacion');
      const tipoRegularizacionContainer = document.getElementById('tipo-regularizacion-container');

      function setTipoDesdeMotivo() {
        const option = motivoSelect.options[motivoSelect.selectedIndex];
        const motivoDescripcion = option ? option.textContent.trim().toUpperCase() : '';
        const esRegularizacion = motivoDescripcion.includes('REGULARIZACI');
        
        // Si es Regularización, mostrar selector de tipo y usar ese valor
        if (esRegularizacion) {
          tipoRegularizacionContainer.style.display = 'block';
          tipoInput.value = tipoRegularizacionSelect.value || 'SALIDA';
        } else {
          tipoRegularizacionContainer.style.display = 'none';
          tipoInput.value = option ? (option.dataset.tipo || 'SALIDA') : 'SALIDA';
        }
      }
      
      // Actualizar tipo cuando cambia el selector de Regularización
      if (tipoRegularizacionSelect) {
        tipoRegularizacionSelect.addEventListener('change', () => {
          const option = motivoSelect.options[motivoSelect.selectedIndex];
          const motivoDescripcion = option ? option.textContent.trim().toUpperCase() : '';
          if (motivoDescripcion.includes('REGULARIZACI')) {
            tipoInput.value = tipoRegularizacionSelect.value;
            // Re-renderizar productos para actualizar max de inputs
            if (productosDisponibles.length > 0) {
              renderProductos();
            }
          }
        });
      }

      async function cargarProductosDeposito() {
        const depositoId = depositoSelect.value;
        if (!depositoId) {
          tablaProductosContainer.style.display = 'none';
          return;
        }

        tablaProductosContainer.style.display = 'block';
        tbodyProductos.innerHTML = '<tr><td colspan="5" class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Cargando productos...</td></tr>';

        try {
          const response = await fetch(`get_stock_productos.php?deposito_id=${depositoId}`);
          const data = await response.json();
          
          if (data.error) {
            tbodyProductos.innerHTML = `<tr><td colspan="5" class="text-center text-danger">${data.error}</td></tr>`;
            return;
          }

          productosDisponibles = data.productos || [];
          renderProductos();
        } catch (error) {
          console.error('Error al cargar productos:', error);
          tbodyProductos.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error al cargar productos del depósito.</td></tr>';
        }
      }

      function renderProductos() {
        tbodyProductos.innerHTML = '';
        if (productosDisponibles.length === 0) {
          tbodyProductos.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No hay productos con stock en este depósito.</td></tr>';
          return;
        }

        productosDisponibles.forEach((prod, index) => {
          const row = document.createElement('tr');
          const yaEnDetalle = detalle.some(item => item.id_materia_prima === prod.id_materia_prima);
          const tipoActual = tipoInput.value || 'SALIDA';
          const stockActual = parseInt(prod.stock_actual) || 0;
          
          row.innerHTML = `
            <td class="text-center">${index + 1}</td>
            <td>${prod.materia_prima_descripcion}</td>
            <td class="text-end ${stockActual === 0 ? 'text-muted' : ''}">${stockActual}</td>
            <td>
              <input type="number" 
                     class="form-control form-control-sm cantidad-producto" 
                     min="1" 
                     ${tipoActual === 'SALIDA' && stockActual > 0 ? `max="${stockActual}"` : ''}
                     value="1"
                     data-id-materia-prima="${prod.id_materia_prima}"
                     data-stock="${stockActual}"
                     ${yaEnDetalle ? 'disabled' : ''}>
            </td>
            <td class="text-center">
              <button type="button" 
                      class="btn btn-sm btn-primary btn-agregar-producto" 
                      data-id-materia-prima="${prod.id_materia_prima}"
                      data-producto="${prod.materia_prima_descripcion}"
                      data-stock="${stockActual}"
                      ${yaEnDetalle ? 'disabled' : ''}>
                <i class="fas fa-plus"></i> Agregar
              </button>
            </td>
          `;
          tbodyProductos.appendChild(row);
        });
      }

      function renderDetalle() {
        tbody.innerHTML = '';
        if (detalle.length === 0) {
          const row = document.createElement('tr');
          row.innerHTML = '<td colspan="6" class="text-center text-muted">Sin ítems cargados.</td>';
          tbody.appendChild(row);
        } else {
          detalle.forEach((item, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
              <td class="text-center">${index + 1}</td>
              <td>${item.producto}</td>
              <td class="text-end">${item.stock_base}</td>
              <td class="text-end">${item.cantidad}</td>
              <td class="text-center">${item.tipo}</td>
              <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger" data-remove="${item.id_materia_prima}">
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            `;
            tbody.appendChild(row);
          });
        }
        detalleInput.value = JSON.stringify(detalle);
        btnGuardar.disabled = detalle.length === 0;
        totalItemsLabel.textContent = detalle.length;
        
        // Re-renderizar productos para actualizar estados de botones
        if (productosDisponibles.length > 0) {
          renderProductos();
        }
      }

      function agregarProductoAlDetalle(idMateriaPrima, productoNombre, cantidad, stockBase) {
        const motivoId = motivoSelect.value;
        // Si es Regularización, usar el valor del selector de tipo
        const option = motivoSelect.options[motivoSelect.selectedIndex];
        const motivoDescripcion = option ? option.textContent.trim().toUpperCase() : '';
        const esRegularizacion = motivoDescripcion.includes('REGULARIZACI');
        const tipo = esRegularizacion && tipoRegularizacionSelect 
          ? tipoRegularizacionSelect.value 
          : (tipoInput.value || 'SALIDA');

        if (!depositoSelect.value) {
          alert('Seleccione un depósito antes de agregar ítems.');
          depositoSelect.focus();
          return;
        }
        if (!motivoId) {
          alert('Seleccione un motivo antes de agregar ítems.');
          motivoSelect.focus();
          return;
        }
        if (!cantidad || cantidad <= 0) {
          alert('Ingrese una cantidad válida (mayor a 0).');
          return;
        }
        if (tipo === 'SALIDA' && cantidad > stockBase) {
          alert(`La cantidad supera el stock disponible para este producto.\nStock disponible: ${stockBase}`);
          return;
        }

        const existente = detalle.find(item => item.id_materia_prima === idMateriaPrima);
        if (existente) {
          mostrarModalDuplicado('El producto ya fue agregado al detalle, no puede duplicarse.');
          return;
        }

        detalle.push({
          id_materia_prima: idMateriaPrima,
          producto: productoNombre,
          cantidad: cantidad,
          stock_base: stockBase,
          tipo: tipo
        });

        depositoFijado = depositoSelect.value;
        renderDetalle();
      }

      depositoSelect.addEventListener('change', () => {
        if (detalle.length > 0 && depositoFijado && depositoFijado !== depositoSelect.value) {
          if (!confirm('Ya existen ítems cargados. ¿Desea cambiar el depósito? Esto eliminará los ítems actuales.')) {
            depositoSelect.value = depositoFijado;
            return;
          }
          detalle = [];
          depositoFijado = null;
          renderDetalle();
        }
        depositoFijado = depositoSelect.value || null;
        cargarProductosDeposito();
      });

      motivoSelect.addEventListener('change', () => {
        setTipoDesdeMotivo();
        // Re-renderizar productos para actualizar max de inputs según el tipo
        if (productosDisponibles.length > 0) {
          renderProductos();
        }
      });

      // Event listener para botones "Agregar" en la tabla de productos
      tbodyProductos.addEventListener('click', (event) => {
        const btn = event.target.closest('.btn-agregar-producto');
        if (!btn || btn.disabled) return;

        const idMateriaPrima = parseInt(btn.dataset.idMateriaPrima, 10);
        const productoNombre = btn.dataset.producto;
        const stockBase = parseInt(btn.dataset.stock, 10);
        const inputCantidad = btn.closest('tr').querySelector('.cantidad-producto');
        const cantidad = parseInt(inputCantidad.value, 10);

        agregarProductoAlDetalle(idMateriaPrima, productoNombre, cantidad, stockBase);
      });

      // Event listener para eliminar del detalle
      tbody.addEventListener('click', (event) => {
        const btn = event.target.closest('button[data-remove]');
        if (!btn) return;
        const prodId = parseInt(btn.dataset.remove, 10);
        detalle = detalle.filter(item => item.id_materia_prima !== prodId);
        if (detalle.length === 0) {
          depositoFijado = null;
        }
        renderDetalle();
      });

      form.addEventListener('submit', (event) => {
        if (detalle.length === 0) {
          event.preventDefault();
          alert('Debe agregar al menos un ítem antes de guardar.');
          return;
        }
        if (!depositoSelect.value || !motivoSelect.value) {
          event.preventDefault();
          alert('Indique el depósito y el motivo del ajuste.');
          return;
        }
        detalleInput.value = JSON.stringify(detalle);
      });

      setTipoDesdeMotivo();
      renderDetalle();
      
      // Cargar productos si ya hay un depósito seleccionado (modo edición)
      if (depositoSelect.value) {
        cargarProductosDeposito();
      }
      
      // Cargar datos en modo edición
      <?php if ($modoEdicion && !empty($ajusteDetalle)): ?>
      (function() {
        const detalleEdicion = <?= json_encode($ajusteDetalle, JSON_UNESCAPED_UNICODE) ?>;
        
        function cargarDetalleEdicion() {
          if (detalleEdicion.length > 0 && motivoSelect.value) {
            const option = motivoSelect.options[motivoSelect.selectedIndex];
            const motivoDescripcion = option ? option.textContent.trim().toUpperCase() : '';
            const esRegularizacion = motivoDescripcion.includes('REGULARIZACI');
            
            // Determinar tipo: si es Regularización, usar el selector o inferir del stock
            let tipoMotivo = 'SALIDA';
            if (esRegularizacion) {
              // En edición, inferir el tipo desde el ajuste original
              // Si el stock aumentó, fue ENTRADA; si disminuyó, fue SALIDA
              // Por ahora, usar el tipo del selector si está disponible
              tipoMotivo = tipoRegularizacionSelect ? tipoRegularizacionSelect.value : 'SALIDA';
            } else {
              tipoMotivo = option ? (option.dataset.tipo || 'SALIDA') : 'SALIDA';
            }
            
            detalle = detalleEdicion.map(item => ({
              id_materia_prima: parseInt(item.id_materia_prima) || 0,
              producto: item.producto_descripcion || ('ID ' + item.id_materia_prima),
              cantidad: parseInt(item.cantidad) || 0,
              stock_base: parseInt(item.stock_base) || 0,
              tipo: tipoMotivo
            }));
            
            depositoFijado = depositoSelect.value || null;
            renderDetalle();
            
            // Si es Regularización, mostrar el selector de tipo
            if (esRegularizacion) {
              tipoRegularizacionContainer.style.display = 'block';
            }
            
            // Cargar productos del depósito después de cargar el detalle
            if (depositoSelect.value) {
              setTimeout(() => cargarProductosDeposito(), 300);
            }
          } else if (detalleEdicion.length > 0) {
            // Esperar a que se cargue el motivo
            setTimeout(cargarDetalleEdicion, 100);
          }
        }
        
        // Intentar cargar inmediatamente o después de que el DOM esté listo
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', cargarDetalleEdicion);
        } else {
          setTimeout(cargarDetalleEdicion, 200);
        }
      })();
      <?php endif; ?>
    })();
  </script>
<?php
}
?>
