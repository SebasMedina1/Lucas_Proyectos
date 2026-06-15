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
$page_title = 'Libro de Ventas';
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
$clienteFiltro = (isset($_GET['cliente_id']) && $_GET['cliente_id'] !== '' && $_GET['cliente_id'] !== '0') ? (int)$_GET['cliente_id'] : null;
$tipoFiltro = (isset($_GET['tipo_documento']) && $_GET['tipo_documento'] !== '') ? trim($_GET['tipo_documento']) : null;
$busquedaFiltro = (isset($_GET['busqueda']) && $_GET['busqueda'] !== '') ? trim($_GET['busqueda']) : null;
$timbradoFiltro = (isset($_GET['timbrado_id']) && $_GET['timbrado_id'] !== '' && $_GET['timbrado_id'] !== '0') ? (int)$_GET['timbrado_id'] : null;
?>

<!-- Contenido específico del módulo -->
<div class="container-fluid py-3">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Libro de Ventas</h1>
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
                        <label for="cliente_id" class="form-label">Cliente (Opcional)</label>
                        <select class="form-control" id="cliente_id" name="cliente_id">
                            <option value="">— Todos los clientes —</option>
                            <?php
                            try {
                                $qClientes = $pdo->query("
                                    SELECT id_cliente, cliente_nombre || ' ' || cliente_apellido AS nombre_completo
                                    FROM clientes
                                    WHERE cliente_estado = 'ACTIVO'
                                    ORDER BY cliente_nombre, cliente_apellido
                                ");
                                $clienteFiltro = $_GET['cliente_id'] ?? '';
                                foreach ($qClientes as $cliente) {
                                    $selected = ($clienteFiltro == $cliente['id_cliente']) ? 'selected' : '';
                                    echo "<option value='{$cliente['id_cliente']}' {$selected}>{$cliente['nombre_completo']}</option>";
                                }
                            } catch (PDOException $e) {
                                echo "<option value=''>Error al cargar clientes</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="tipo_documento" class="form-label">Tipo Documento (Opcional)</label>
                        <select class="form-control" id="tipo_documento" name="tipo_documento">
                            <option value="">— Todos —</option>
                            <?php
                            $tipoFiltro = $_GET['tipo_documento'] ?? '';
                            $tipos = ['FACTURA' => 'Factura', 'NOTA_CREDITO' => 'Nota de Crédito', 'NOTA_DEBITO' => 'Nota de Débito'];
                            foreach ($tipos as $valor => $label) {
                                $selected = ($tipoFiltro == $valor) ? 'selected' : '';
                                echo "<option value='{$valor}' {$selected}>{$label}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="timbrado_id" class="form-label">Timbrado (Opcional)</label>
                        <select class="form-control" id="timbrado_id" name="timbrado_id">
                            <option value="">— Todos los timbrados —</option>
                            <?php
                            try {
                                $qTimbrados = $pdo->query("
                                    SELECT DISTINCT t.id_timbrado, t.timbrado_numero
                                    FROM timbrado t
                                    JOIN caja_timbrado ct ON ct.id_timbrado = t.id_timbrado
                                    WHERE ct.estado = 'ACTIVO'
                                    ORDER BY t.timbrado_numero
                                ");
                                $timbradoFiltro = $_GET['timbrado_id'] ?? '';
                                foreach ($qTimbrados as $timb) {
                                    $selected = ($timbradoFiltro == $timb['id_timbrado']) ? 'selected' : '';
                                    echo "<option value='{$timb['id_timbrado']}' {$selected}>{$timb['timbrado_numero']}</option>";
                                }
                            } catch (PDOException $e) {
                                echo "<option value=''>Error al cargar timbrados</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="busqueda" class="form-label">Búsqueda (N° Documento o Timbrado)</label>
                        <input type="text" class="form-control" id="busqueda" name="busqueda" value="<?= htmlspecialchars($_GET['busqueda'] ?? '') ?>" placeholder="Buscar por número o timbrado...">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary mr-2">
                            <i class="fas fa-search"></i> Generar/Actualizar
                        </button>
                        <button type="button" class="btn btn-secondary mr-2" onclick="document.getElementById('desde').value='<?= $primerDiaMes ?>'; document.getElementById('hasta').value='<?= $ultimoDiaMes ?>'; document.getElementById('cliente_id').value=''; document.getElementById('tipo_documento').value=''; document.getElementById('timbrado_id').value=''; document.getElementById('busqueda').value='';">
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
    
    <!-- Card: Historial de Libros -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Historial de Libros Generados</h6>
            <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#modalGuardarLibro">
                <i class="fas fa-save"></i> Guardar Libro Actual
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm" id="tablaHistorial">
                    <thead>
                        <tr>
                            <th>Período</th>
                            <th>Fecha Generación</th>
                            <th>Estado</th>
                            <th>Total General</th>
                            <th>Facturas</th>
                            <th>NC</th>
                            <th>ND</th>
                            <th>Usuario</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $sqlHistorial = "
                                SELECT 
                                    lv.id_libro,
                                    lv.fecha_desde,
                                    lv.fecha_hasta,
                                    lv.fecha_generacion,
                                    lv.estado,
                                    lv.total_general,
                                    lv.cantidad_facturas,
                                    lv.cantidad_notas_credito,
                                    lv.cantidad_notas_debito,
                                    u.username
                                FROM libro_ventas_historico lv
                                JOIN usuarios u ON u.id_usuario = lv.id_usuario
                                ORDER BY lv.fecha_generacion DESC
                                LIMIT 20
                            ";
                            foreach ($pdo->query($sqlHistorial) as $hist) {
                                $estadoClass = $hist['estado'] === 'CERRADO' ? 'badge-danger' : 'badge-success';
                                $fechaGen = date('d/m/Y H:i', strtotime($hist['fecha_generacion']));
                                $periodo = date('d/m/Y', strtotime($hist['fecha_desde'])) . ' - ' . date('d/m/Y', strtotime($hist['fecha_hasta']));
                                
                                echo "<tr>";
                                echo "<td>{$periodo}</td>";
                                echo "<td>{$fechaGen}</td>";
                                echo "<td><span class='badge {$estadoClass}'>{$hist['estado']}</span></td>";
                                echo "<td class='text-right'>" . number_format((float)$hist['total_general'], 0, ',', '.') . " Gs</td>";
                                echo "<td class='text-center'>{$hist['cantidad_facturas']}</td>";
                                echo "<td class='text-center'>{$hist['cantidad_notas_credito']}</td>";
                                echo "<td class='text-center'>{$hist['cantidad_notas_debito']}</td>";
                                echo "<td>{$hist['username']}</td>";
                                echo "<td>";
                                echo "<a href='view.php?desde={$hist['fecha_desde']}&hasta={$hist['fecha_hasta']}' class='btn btn-sm btn-info' title='Ver'>";
                                echo "<i class='fas fa-eye'></i></a> ";
                                if ($hist['estado'] === 'ABIERTO') {
                                    echo "<button type='button' class='btn btn-sm btn-warning' onclick='cerrarLibro({$hist['id_libro']})' title='Cerrar'>";
                                    echo "<i class='fas fa-lock'></i></button>";
                                }
                                echo "</td>";
                                echo "</tr>";
                            }
                        } catch (PDOException $e) {
                            // Si la tabla no existe, no mostrar error
                            if (strpos($e->getMessage(), 'does not exist') === false) {
                                echo "<tr><td colspan='9' class='text-center text-muted'>Error al cargar historial: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                            } else {
                                echo "<tr><td colspan='9' class='text-center text-muted'>Ejecute el script SQL para habilitar el historial de libros</td></tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal Guardar Libro -->
    <div class="modal fade" id="modalGuardarLibro" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Guardar Libro de Ventas</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="formGuardarLibro">
                    <div class="modal-body">
                        <input type="hidden" name="fecha_desde" id="guardar_desde" value="<?= htmlspecialchars($desde) ?>">
                        <input type="hidden" name="fecha_hasta" id="guardar_hasta" value="<?= htmlspecialchars($hasta) ?>">
                        <div class="form-group">
                            <label>Período:</label>
                            <p class="form-control-plaintext"><?= date('d/m/Y', strtotime($desde)) ?> al <?= date('d/m/Y', strtotime($hasta)) ?></p>
                        </div>
                        <div class="form-group">
                            <label for="observaciones_libro">Observaciones (Opcional)</label>
                            <textarea class="form-control" id="observaciones_libro" name="observaciones" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="btnGuardarLibro">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php
    // Validaciones de período
    $hoy = date('Y-m-d');
    $ultimoDiaMesActual = date('Y-m-t'); // Último día del mes actual
    $erroresValidacion = [];
    
    if (!empty($desde) && !empty($hasta)) {
        if ($desde > $hasta) {
            $erroresValidacion[] = 'La fecha "Desde" no puede ser posterior a "Hasta"';
        }
        // Permitir fechas hasta el último día del mes actual (puede haber documentos hasta ese día)
        if ($desde > $ultimoDiaMesActual) {
            $erroresValidacion[] = 'La fecha "Desde" no puede ser posterior al último día del mes actual';
        }
        if ($hasta > $ultimoDiaMesActual) {
            $erroresValidacion[] = 'La fecha "Hasta" no puede ser posterior al último día del mes actual';
        }
        // Validar rango máximo (1 año)
        $diff = (strtotime($hasta) - strtotime($desde)) / (60 * 60 * 24);
        if ($diff > 365) {
            $erroresValidacion[] = 'El rango de fechas no puede exceder 1 año';
        }
    }
    
    // Si hay fechas válidas, generar el libro
    if (!empty($desde) && !empty($hasta) && $desde <= $hasta && empty($erroresValidacion)) {
        try {
            require 'consolidar_documentos.php';
            // Asegurar que los parámetros opcionales sean null si están vacíos
            $clienteIdParam = ($clienteFiltro !== null && $clienteFiltro > 0) ? $clienteFiltro : null;
            $tipoDocParam = (!empty($tipoFiltro)) ? $tipoFiltro : null;
            $busquedaParam = (!empty($busquedaFiltro)) ? $busquedaFiltro : null;
            $timbradoIdParam = ($timbradoFiltro !== null && $timbradoFiltro > 0) ? $timbradoFiltro : null;
            $documentos = consolidarDocumentos($pdo, $desde, $hasta, $clienteIdParam, $tipoDocParam, $busquedaParam, $timbradoIdParam);
            
            // Validar consistencia de documentos
            $documentosInconsistentes = [];
            foreach ($documentos as $doc) {
                // Validar: subtotal + IVA = total (aproximado, con tolerancia de redondeo)
                $subtotalCalculado = $doc['exento'] + $doc['base_5'] + $doc['base_10'];
                $ivaCalculado = $doc['iva_5'] + $doc['iva_10'];
                $totalCalculado = $subtotalCalculado + $ivaCalculado;
                $diferencia = abs($doc['total'] - $totalCalculado);
                
                // Tolerancia de 100 Gs para redondeos
                if ($diferencia > 100) {
                    $documentosInconsistentes[] = [
                        'tipo' => $doc['tipo'],
                        'numero' => $doc['numero'],
                        'diferencia' => $diferencia
                    ];
                }
            }
            
            // Calcular totales
            $totalExento = 0;
            $totalBase5 = 0;
            $totalIva5 = 0;
            $totalBase10 = 0;
            $totalIva10 = 0;
            $totalGeneral = 0;
            
            foreach ($documentos as $doc) {
                $totalExento += $doc['exento'] * $doc['signo'];
                $totalBase5 += $doc['base_5'] * $doc['signo'];
                $totalIva5 += $doc['iva_5'] * $doc['signo'];
                $totalBase10 += $doc['base_10'] * $doc['signo'];
                $totalIva10 += $doc['iva_10'] * $doc['signo'];
                $totalGeneral += $doc['total'] * $doc['signo'];
            }
            
            // Mostrar advertencias si hay inconsistencias
            if (!empty($documentosInconsistentes)) {
                echo "<div class='alert alert-warning alert-dismissible fade show' role='alert'>";
                echo "<strong>Advertencia:</strong> Se encontraron " . count($documentosInconsistentes) . " documento(s) con inconsistencias en los cálculos. ";
                echo "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>";
                echo "<span aria-hidden='true'>&times;</span></button></div>";
            }
            ?>
            
            <!-- Card: Resumen -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Resumen del Período</h6>
                    <div>
                        <?php
                        $paramsExport = http_build_query([
                            'desde' => $desde,
                            'hasta' => $hasta,
                            'cliente_id' => $clienteFiltro,
                            'tipo_documento' => $tipoFiltro,
                            'busqueda' => $busquedaFiltro,
                            'timbrado_id' => $timbradoFiltro
                        ]);
                        ?>
                        <a href="exportar_pdf.php?<?= $paramsExport ?>" target="_blank" class="btn btn-sm btn-danger">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                        <a href="exportar_excel.php?<?= $paramsExport ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                        <a href="exportar_csv.php?<?= $paramsExport ?>" class="btn btn-sm btn-info">
                            <i class="fas fa-file-csv"></i> CSV
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <strong>Exentas:</strong><br>
                            <span class="h5"><?= number_format($totalExento, 0, ',', '.') ?> Gs</span>
                        </div>
                        <div class="col-md-2">
                            <strong>Base 5%:</strong><br>
                            <span class="h5"><?= number_format($totalBase5, 0, ',', '.') ?> Gs</span>
                        </div>
                        <div class="col-md-2">
                            <strong>IVA 5%:</strong><br>
                            <span class="h5"><?= number_format($totalIva5, 0, ',', '.') ?> Gs</span>
                        </div>
                        <div class="col-md-2">
                            <strong>Base 10%:</strong><br>
                            <span class="h5"><?= number_format($totalBase10, 0, ',', '.') ?> Gs</span>
                        </div>
                        <div class="col-md-2">
                            <strong>IVA 10%:</strong><br>
                            <span class="h5"><?= number_format($totalIva10, 0, ',', '.') ?> Gs</span>
                        </div>
                        <div class="col-md-2">
                            <strong>Total General:</strong><br>
                            <span class="h5 text-primary"><?= number_format($totalGeneral, 0, ',', '.') ?> Gs</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card: Detalle de Documentos -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Detalle de Documentos</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="tablaLibroVentas" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>N° Documento</th>
                                    <th>Timbrado</th>
                                    <th>Cliente</th>
                                    <th>RUC</th>
                                    <th>Exento</th>
                                    <th>Base 5%</th>
                                    <th>IVA 5%</th>
                                    <th>Base 10%</th>
                                    <th>IVA 10%</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($documentos as $doc) {
                                    $fechaFormateada = date('d/m/Y', strtotime($doc['fecha']));
                                    $tipoLabel = $doc['tipo'] === 'FACTURA' ? 'Factura' : 
                                                ($doc['tipo'] === 'NOTA_CREDITO' ? 'NC' : 'ND');
                                    $tipoClass = $doc['tipo'] === 'FACTURA' ? 'badge-success' : 
                                                ($doc['tipo'] === 'NOTA_CREDITO' ? 'badge-danger' : 'badge-warning');
                                    $signo = $doc['signo'] > 0 ? '' : '-';
                                    
                                    echo "<tr>";
                                    echo "<td>{$fechaFormateada}</td>";
                                    echo "<td><span class='badge {$tipoClass}'>{$tipoLabel}</span></td>";
                                    echo "<td>{$doc['numero']}</td>";
                                    echo "<td>{$doc['timbrado']}</td>";
                                    echo "<td>{$doc['cliente']}</td>";
                                    echo "<td>{$doc['ruc']}</td>";
                                    echo "<td class='text-right'>{$signo}" . number_format($doc['exento'] * $doc['signo'], 0, ',', '.') . "</td>";
                                    echo "<td class='text-right'>{$signo}" . number_format($doc['base_5'] * $doc['signo'], 0, ',', '.') . "</td>";
                                    echo "<td class='text-right'>{$signo}" . number_format($doc['iva_5'] * $doc['signo'], 0, ',', '.') . "</td>";
                                    echo "<td class='text-right'>{$signo}" . number_format($doc['base_10'] * $doc['signo'], 0, ',', '.') . "</td>";
                                    echo "<td class='text-right'>{$signo}" . number_format($doc['iva_10'] * $doc['signo'], 0, ',', '.') . "</td>";
                                    echo "<td class='text-right'><strong>{$signo}" . number_format($doc['total'] * $doc['signo'], 0, ',', '.') . "</strong></td>";
                                    
                                    // Estado del documento
                                    $estadoDoc = strtoupper(trim($doc['estado'] ?? 'EMITIDA'));
                                    $estadoClass = ($estadoDoc === 'ANULADA' || $estadoDoc === 'ANULADO') ? 'badge-danger' : 'badge-success';
                                    $estadoLabel = ($estadoDoc === 'ANULADA' || $estadoDoc === 'ANULADO') ? 'ANULADO' : 'VIGENTE';
                                    echo "<td><span class='badge {$estadoClass}'>{$estadoLabel}</span></td>";
                                    
                                    echo "<td>";
                                    
                                    if ($doc['tipo'] === 'FACTURA') {
                                        echo "<a href='../gestionar_ventas/reporte.php?id={$doc['id']}' target='_blank' class='btn btn-sm btn-info' title='Ver Factura'>";
                                        echo "<i class='fas fa-eye'></i></a>";
                                    } else {
                                        echo "<a href='../nota_credito_venta/reporte.php?id={$doc['id']}' target='_blank' class='btn btn-sm btn-info' title='Ver Nota'>";
                                        echo "<i class='fas fa-eye'></i></a>";
                                    }
                                    
                                    echo "</td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                            <tfoot>
                                <tr class="font-weight-bold">
                                    <td colspan="6" class="text-right">TOTALES:</td>
                                    <td class="text-right"><?= number_format($totalExento, 0, ',', '.') ?></td>
                                    <td class="text-right"><?= number_format($totalBase5, 0, ',', '.') ?></td>
                                    <td class="text-right"><?= number_format($totalIva5, 0, ',', '.') ?></td>
                                    <td class="text-right"><?= number_format($totalBase10, 0, ',', '.') ?></td>
                                    <td class="text-right"><?= number_format($totalIva10, 0, ',', '.') ?></td>
                                    <td class="text-right text-primary"><?= number_format($totalGeneral, 0, ',', '.') ?></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <script>
            function cerrarLibro(idLibro) {
                if (confirm('¿Está seguro que desea cerrar este libro? Una vez cerrado no podrá modificarse.')) {
                    const formData = new FormData();
                    formData.append('id_libro', idLibro);
                    formData.append('accion', 'cerrar');
                    
                    fetch('gestionar_libro.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Libro cerrado correctamente');
                            location.reload();
                        } else {
                            alert('Error: ' + (data.message || 'No se pudo cerrar el libro'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al cerrar el libro');
                    });
                }
            }
            
            // Manejar guardar libro
            $('#btnGuardarLibro').on('click', function() {
                const formData = new FormData();
                formData.append('fecha_desde', $('#guardar_desde').val());
                formData.append('fecha_hasta', $('#guardar_hasta').val());
                formData.append('observaciones', $('#observaciones_libro').val());
                
                fetch('guardar_libro.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Libro guardado correctamente');
                        $('#modalGuardarLibro').modal('hide');
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'No se pudo guardar el libro'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al guardar el libro');
                });
            });
            
            $(document).ready(function() {
                $('#tablaLibroVentas').DataTable({
                    "language": {
                        "url": "<?= $BASE_PATH ?>vendor/datatables/Spanish.json"
                    },
                    "order": [[0, "asc"]],
                    "pageLength": 25,
                    "footerCallback": function (row, data, start, end, display) {
                        // Los totales ya están en el tfoot HTML
                    }
                });
                
                $('#tablaHistorial').DataTable({
                    "language": {
                        "url": "<?= $BASE_PATH ?>vendor/datatables/Spanish.json"
                    },
                    "order": [[1, "desc"]],
                    "pageLength": 10
                });
            });
            </script>

        <?php
        } catch (PDOException $e) {
            echo "<div class='alert alert-danger'>Error al generar el libro: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        if (!empty($erroresValidacion)) {
            echo "<div class='alert alert-danger'>";
            echo "<strong>Errores de validación:</strong><ul>";
            foreach ($erroresValidacion as $error) {
                echo "<li>" . htmlspecialchars($error) . "</li>";
            }
            echo "</ul></div>";
        } elseif (!empty($desde) || !empty($hasta)) {
            echo "<div class='alert alert-warning'>Por favor, seleccione un rango de fechas válido.</div>";
        }
    }
    ?>

</div>

<?php include '../../footer.php'; ?>

