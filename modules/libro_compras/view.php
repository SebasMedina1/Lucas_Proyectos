<?php
session_start();
if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}

$configPath = realpath("../../config/database.php");
if (!$configPath || !file_exists($configPath)) {
    die("Error: No se pudo encontrar el archivo de configuración.");
}
require_once $configPath;

$username = $_SESSION['username'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $query = $pdo->prepare("SELECT * FROM usuarios WHERE username = :username");
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->execute();
    $auth_user = $query->fetch();
    if (!$auth_user) {
        session_destroy();
        echo "<script>
                alert('Usuario no encontrado, serás redirigido al inicio de sesión');
                window.location.href = '../../login.html';
              </script>";
        exit();
    }
    $permisoAcceso = (int)($auth_user['id_cargo'] ?? 0);
} catch (PDOException $e) {
    die("Error en la conexión a la base de datos: " . $e->getMessage());
}

// Configuración para el layout común
$BASE_PATH = '../../';
$page_title = 'Libro de Compras';
$extra_css = [
    'vendor/datatables/dataTables.bootstrap4.min.css'
];
$extra_js_plugins = [
    'vendor/datatables/jquery.dataTables.min.js',
    'vendor/datatables/dataTables.bootstrap4.min.js'
];

// Variables para el sidebar (necesarias para header.php)
$allowedCargos = [1,3,5];
$showCoreSidebar = in_array($permisoAcceso, $allowedCargos, true);
$showReportes = in_array($permisoAcceso, [3,5], true);
$showAdministracion = ($permisoAcceso === 5);

// Incluir header común
include '../../header.php';

// Fechas por defecto (mes actual)
$hoy = date('Y-m-d');
$primerDiaMes = date('Y-m-01');
$ultimoDiaMes = date('Y-m-t');

$desde = $_GET['desde'] ?? $primerDiaMes;
$hasta = $_GET['hasta'] ?? $ultimoDiaMes;
$filtro_proveedor = (isset($_GET['proveedor']) && $_GET['proveedor'] !== '' && $_GET['proveedor'] !== '0') ? (int)$_GET['proveedor'] : null;
$filtro_tipo = (isset($_GET['tipo']) && $_GET['tipo'] !== '') ? trim($_GET['tipo']) : null;
$filtro_sucursal = (isset($_GET['sucursal']) && $_GET['sucursal'] !== '' && $_GET['sucursal'] !== '0') ? (int)$_GET['sucursal'] : null;
$ordenar_por = isset($_GET['ordenar']) ? trim($_GET['ordenar']) : 'fecha';
$orden = isset($_GET['orden']) && strtoupper($_GET['orden']) === 'DESC' ? 'DESC' : 'ASC';
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$por_pagina = 50;

// Obtener proveedores para el filtro
try {
    $proveedores = $pdo->query("
        SELECT id_proveedor, razon_social 
        FROM proveedor 
        WHERE estado_proveedor = 'ACTIVO'
        ORDER BY razon_social ASC
    ")->fetchAll();
} catch (PDOException $e) {
    $proveedores = [];
}

// Obtener sucursales para el filtro
try {
    $sucursales = $pdo->query("
        SELECT id_sucursal, descripcion_sucursal 
        FROM sucursales 
        ORDER BY descripcion_sucursal ASC
    ")->fetchAll();
} catch (PDOException $e) {
    $sucursales = [];
}
?>

<!-- Contenido específico del módulo -->
<div class="container-fluid py-3">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Libro de Compras</h1>
    </div>

    <!-- Card: Filtros -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filtros</h6>
        </div>
        <div class="card-body">
            <form id="form-filtros" method="GET" action="view.php">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="desde" class="form-label">Desde</label>
                        <input type="date" class="form-control" id="desde" name="desde" value="<?= htmlspecialchars($desde) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="hasta" class="form-label">Hasta</label>
                        <input type="date" class="form-control" id="hasta" name="hasta" value="<?= htmlspecialchars($hasta) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="proveedor" class="form-label">Proveedor (Opcional)</label>
                        <select class="form-control" id="proveedor" name="proveedor">
                            <option value="0">— Todos los proveedores —</option>
                            <?php
                            $proveedorFiltro = $_GET['proveedor'] ?? '0';
                            foreach ($proveedores as $prov) {
                                $selected = ($proveedorFiltro == $prov['id_proveedor']) ? 'selected' : '';
                                echo "<option value='{$prov['id_proveedor']}' {$selected}>" . htmlspecialchars($prov['razon_social']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="tipo" class="form-label">Tipo Documento (Opcional)</label>
                        <select class="form-control" id="tipo" name="tipo">
                            <option value="">— Todos —</option>
                            <?php
                            $tipoFiltro = $_GET['tipo'] ?? '';
                            $tipos = ['FACTURA' => 'Factura', 'CREDITO' => 'Nota de Crédito', 'DEBITO' => 'Nota de Débito'];
                            foreach ($tipos as $valor => $label) {
                                $selected = ($tipoFiltro == $valor) ? 'selected' : '';
                                echo "<option value='{$valor}' {$selected}>{$label}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="sucursal" class="form-label">Sucursal (Opcional)</label>
                        <select class="form-control" id="sucursal" name="sucursal">
                            <option value="0">— Todas las sucursales —</option>
                            <?php
                            $sucursalFiltro = $_GET['sucursal'] ?? '0';
                            foreach ($sucursales as $suc) {
                                $selected = ($sucursalFiltro == $suc['id_sucursal']) ? 'selected' : '';
                                echo "<option value='{$suc['id_sucursal']}' {$selected}>" . htmlspecialchars($suc['descripcion_sucursal']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-9 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary mr-2">
                            <i class="fas fa-search"></i> Generar/Actualizar
                        </button>
                        <button type="button" class="btn btn-secondary mr-2" onclick="document.getElementById('desde').value='<?= $primerDiaMes ?>'; document.getElementById('hasta').value='<?= $ultimoDiaMes ?>'; document.getElementById('proveedor').value='0'; document.getElementById('tipo').value=''; document.getElementById('sucursal').value='0';">
                            <i class="fas fa-calendar"></i> Mes Actual
                        </button>
                        <a href="view.php" class="btn btn-warning">
                            <i class="fas fa-redo"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php
    // Generar libro si hay fechas
    if ($desde && $hasta) {
        include 'generar_libro.php';
    }
    ?>
</div>

<?php
$inline_js = "";
include '../../footer.php';
?>
