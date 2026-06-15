<?php 
// Iniciar la sesión
session_start();

// Verificar si la sesión es válida
if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}

// Conexión a la base de datos
$file = realpath("../../config/database.php");

if (!$file || !file_exists($file)) {
    die("Error: No se pudo encontrar el archivo en la ruta $file");
}

require_once $file;
require_once realpath("../../config/app_modules.php");

// Obtener el nombre de usuario de la sesión
$username = $_SESSION['username'];

try {
    // Crear conexión con PostgreSQL usando PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);

    // Configurar excepciones para errores
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Preparar consulta para obtener datos del usuario autenticado
    $query = $pdo->prepare("SELECT * FROM usuarios WHERE username = :username");
    $query->bindParam(':username', $username, PDO::PARAM_STR);

    // Ejecutar consulta
    $query->execute();

    // Obtener los datos del usuario autenticado
    $auth_user = $query->fetch(PDO::FETCH_ASSOC);
    $permisoAcceso = $auth_user ? (int)$auth_user['id_cargo'] : null;

    // Verificar si se encontraron datos del usuario
    if (!$auth_user) {
        session_destroy();
        echo "<script>
                alert('Usuario no encontrado, serás redirigido al inicio de sesión');
                window.location.href = '../../login.html';
              </script>";
        exit();
    }
} catch (PDOException $e) {
    die("Error en la conexión a la base de datos: " . $e->getMessage());
}

$today = date('Y-m-d');

// Configuración para el layout común
$BASE_PATH = '../../';
$page_title = 'Generador de Informes';

// Variables para el sidebar (necesarias para header.php)
$allowedCargos = [1,3,5];
$showCoreSidebar = in_array($permisoAcceso, $allowedCargos, true);
$showReportes = in_array($permisoAcceso, [3,5], true);
$showAdministracion = ($permisoAcceso === 5);

// Incluir header común
include '../../header.php';
?>

<!-- Contenido específico del módulo -->
<div class="container-fluid py-3">
    <!-- Encabezado -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Generar Informes</h1>
    </div>

    <!-- Card: Filtros de Informe -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filtros</h6>
        </div>
        <div class="card-body">
            <form id="form-informe" action="router_informes.php" method="GET" target="_blank" novalidate>

                <div class="form-row">
                    <!-- Movimiento -->
                    <div class="form-group col-md-4">
                        <label for="movimiento">Movimiento</label>
                        <select id="movimiento" name="movimiento" class="form-control" required>
                            <option value="" selected disabled>Seleccione...</option>
                            <optgroup label="Compras">
                                <option value="PEDIDO">Pedido de Compra</option>
                                <option value="PRESUPUESTO">Presupuesto de Compra</option>
                                <option value="ORDEN_COMPRA">Orden de Compra</option>
                                <option value="FACTURA">Factura de Compra</option>
                                <option value="NOTA_CREDITO">Nota de Crédito Compra</option>
                                <option value="NOTA_DEBITO">Nota de Débito Compra</option>
                                <option value="NOTA_REMISION">Nota de Remisión</option>
                                <option value="AJUSTES">Ajustes</option>
                                <option value="LIBRO_COMPRAS">Libro Compras</option>
                            </optgroup>
                            <?php if (UI_MODULO_VENTAS): ?>
                            <optgroup label="Ventas">
                                <option value="PEDIDO_VENTA">Pedido de Venta</option>
                                <option value="PRESUPUESTO_VENTA">Presupuesto de Venta</option>
                                <option value="FACTURA_VENTA">Factura de Venta</option>
                                <option value="NOTA_CREDITO_VENTA">Nota de Crédito Venta</option>
                                <option value="LIBRO_VENTAS">Libro de Ventas</option>
                            </optgroup>
                            <?php endif; ?>
                        </select>
                        <div class="invalid-feedback">Seleccione un movimiento.</div>
                    </div>

                    <!-- Estado -->
                    <div class="form-group col-md-4" id="estado-group">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado" class="form-control" required>
                            <option value="" selected disabled>Seleccione...</option>
                        </select>
                        <div class="invalid-feedback">Seleccione un estado.</div>
                    </div>

                    <!-- Desde -->
                    <div class="form-group col-md-2">
                        <label for="desde">Desde</label>
                        <input type="date" id="desde" name="desde" class="form-control" required max="<?php echo $today; ?>">
                        <div class="invalid-feedback">Ingrese la fecha inicial.</div>
                    </div>

                    <!-- Hasta -->
                    <div class="form-group col-md-2">
                        <label for="hasta">Hasta</label>
                        <input type="date" id="hasta" name="hasta" class="form-control" required max="<?php echo $today; ?>">
                        <div class="invalid-feedback">Ingrese la fecha final.</div>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-file-alt"></i> Generar informe
                    </button>
                </div>
            </form>
            <small class="text-muted d-block mt-3">
                El informe se abrirá en una nueva pestaña con los filtros seleccionados.
            </small>
        </div>
    </div>

    <!-- (Opcional) Mensajes de alerta -->
    <?php 
    if (!empty($_GET['alert'])) {
        $alertMessage = '';
        $alertClass = 'alert-success';
        switch ($_GET['alert']) {
            case '4':
                $alertMessage = "No se pudo realizar la operación.";
                $alertClass = 'alert-danger';
                break;
            case '6':
                $alertMessage = "No se encontraron registros en el rango seleccionado.";
                $alertClass = 'alert-warning';
                break;
        }
        if ($alertMessage !== '') {
            echo "<div id='alert-message' class='alert $alertClass alert-dismissible fade show' role='alert'>
                    $alertMessage
                    <button type='button' class='close' data-dismiss='alert' aria-label='Close'>
                        <span aria-hidden='true'>&times;</span>
                    </button>
                  </div>";
        }
    }
    ?>
</div>

<?php
$inline_js = "
setTimeout(function() {
    var alertMessage = document.getElementById('alert-message');
    if (alertMessage) {
        alertMessage.style.display = 'none';
    }
}, 3000);

document.addEventListener('DOMContentLoaded', () => {
    const movimientoSelect = document.getElementById('movimiento');
    const estadoSelect = document.getElementById('estado');
    const estadoGroup = document.getElementById('estado-group');
    if (!movimientoSelect || !estadoSelect) return;

    // Estados por movimiento según los módulos de compras y ventas
    const estadosPorMovimiento = {
        // === COMPRAS ===
        PEDIDO: ['REPORTE TOTAL','PENDIENTE','APROBADO','ANULADO','FINALIZADO'],
        PRESUPUESTO: ['REPORTE TOTAL','PENDIENTE','APROBADO','ANULADO','FINALIZADO'],
        ORDEN_COMPRA: ['REPORTE TOTAL','EMITIDA','FACTURADA','FINALIZADO','ANULADO'],
        FACTURA: ['REPORTE TOTAL','PENDIENTE','EMITIDA','APROBADO','ANULADO'],
        NOTA_CREDITO: ['REPORTE TOTAL','PENDIENTE','EMITIDA','APROBADO','ANULADO'],
        NOTA_DEBITO: ['REPORTE TOTAL','PENDIENTE','EMITIDA','APROBADO','ANULADO'],
        NOTA_REMISION: ['REPORTE TOTAL','PENDIENTE','EMITIDA','FINALIZADO','ANULADO'],
        AJUSTES: ['REPORTE TOTAL','APROBADO','ANULADO'],
        LIBRO_COMPRAS: [],
        // === VENTAS ===
        PEDIDO_VENTA: ['REPORTE TOTAL','PENDIENTE','FACTURADO','FINALIZADO','ANULADO'],
        PRESUPUESTO_VENTA: ['REPORTE TOTAL','BORRADOR','EMITIDO','ANULADO','APROBADO','PRESUPUESTADO'],
        FACTURA_VENTA: ['REPORTE TOTAL','PENDIENTE','EMITIDA','ANULADA','PAGADA'],
        NOTA_CREDITO_VENTA: ['REPORTE TOTAL','PENDIENTE','EMITIDA','ANULADA'],
        LIBRO_VENTAS: []
    };

    const valorEstado = (label) => label === 'REPORTE TOTAL' ? 'TOTAL' : label;

    function limpiarEstadoSelect(){
        estadoSelect.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Seleccione...';
        placeholder.disabled = true;
        placeholder.selected = true;
        estadoSelect.appendChild(placeholder);
    }

    function poblarEstados(claveMovimiento) {
        const opciones = estadosPorMovimiento[claveMovimiento] || [];
        limpiarEstadoSelect();

        opciones.forEach(label => {
            const opt = document.createElement('option');
            opt.value = valorEstado(label);
            opt.textContent = label;
            estadoSelect.appendChild(opt);
        });
    }

    function actualizarEstadoSegunMovimiento(){
        const movimiento = movimientoSelect.value;
        // Los libros no requieren estado
        const requiereEstado = movimiento && movimiento !== 'LIBRO_COMPRAS' && movimiento !== 'LIBRO_VENTAS';

        if (requiereEstado) {
            if (estadoGroup) estadoGroup.classList.remove('d-none');
            estadoSelect.disabled = false;
            estadoSelect.required = true;
            poblarEstados(movimiento);
        } else {
            limpiarEstadoSelect();
            estadoSelect.disabled = true;
            estadoSelect.required = false;
            if (estadoGroup) estadoGroup.classList.toggle('d-none', movimiento === 'LIBRO_COMPRAS' || movimiento === 'LIBRO_VENTAS');
        }
    }

    movimientoSelect.addEventListener('change', () => {
        actualizarEstadoSegunMovimiento();
    });

    // inicializar
    actualizarEstadoSegunMovimiento();
});

// Validaciones simples y coherencia de rango de fechas
(function(){
    const form = document.getElementById('form-informe');
    const desde = document.getElementById('desde');
    const hasta = document.getElementById('hasta');

    const todayLocal = (() => {
        const value = new Date(Date.now() - (new Date().getTimezoneOffset() * 60000));
        return value.toISOString().split('T')[0];
    })();

    const clampToToday = (input) => {
        if (input.value && input.value > todayLocal) {
            input.value = todayLocal;
        }
    };

    const syncHastaMin = () => {
        hasta.min = desde.value || '';
        if (hasta.value && desde.value && hasta.value < desde.value) {
            hasta.value = desde.value;
        }
    };

    [desde, hasta].forEach(input => {
        input.max = todayLocal;
    });

    // Si el usuario cambia \"desde\", ajustar min de \"hasta\"
    desde.addEventListener('change', () => {
        clampToToday(desde);
        syncHastaMin();
    });

    // Validar cambios en \"hasta\" para que no supere hoy ni quede antes que \"desde\"
    hasta.addEventListener('change', () => {
        clampToToday(hasta);
        if (desde.value && hasta.value && hasta.value < desde.value) {
            hasta.value = desde.value;
        }
    });

    // Validación HTML5 + check adicional de rango
    form.addEventListener('submit', (e) => {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
            form.classList.add('was-validated');
            return;
        }
        if (desde.value && hasta.value && hasta.value < desde.value) {
            e.preventDefault();
            e.stopPropagation();
            alert('La fecha \"Hasta\" no puede ser anterior a \"Desde\".');
        }
    });

    syncHastaMin();
})();
";
include '../../footer.php';
?>
