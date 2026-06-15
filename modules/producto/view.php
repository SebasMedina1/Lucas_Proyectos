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

// Incluir sistema de permisos y verificar acceso
require_once realpath("../../config/permissions.php");
check_permission('REFERENCIALES'); // Productos es un módulo referencial

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
$page_title = 'Mantener Productos';
$extra_css = [
    'vendor/datatables/dataTables.bootstrap4.min.css'
];
$extra_js_plugins = [
    'vendor/datatables/jquery.dataTables.min.js',
    'vendor/datatables/dataTables.bootstrap4.min.js'
];

// Incluir header común (ya maneja permisos dinámicamente)
include '../../header.php';

$mostrarListado = !(isset($_GET['form_producto']) && ($_GET['form'] === 'add' || $_GET['form'] === 'edit'));
?>

<!-- Contenido específico del módulo -->
<?php if ($mostrarListado): ?>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Mantener Productos</h1>
        <div>
            <a href="?form_producto=add&form=add" class="btn btn-primary btn-sm shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> Nuevo Producto
            </a>
            <button type="button" class="btn btn-warning btn-sm shadow-sm" data-toggle="modal" data-target="#modalReporte">
                <i class="fas fa-print fa-sm text-white-50"></i> Imprimir Reporte
        </button>
  </div>
</div>

    <?php
    if (!empty($_GET['alert'])) {
        $alertMap = [
            1 => ['msg'=>'Producto registrado correctamente.','class'=>'alert-success'],
            2 => ['msg'=>'Producto modificado correctamente.','class'=>'alert-success'],
            3 => ['msg'=>'Producto inactivado correctamente.','class'=>'alert-success'],
            4 => ['msg'=>'No se pudo realizar la operación.','class'=>'alert-danger'],
            5 => ['msg'=>'Ya existe un producto con esa descripción.','class'=>'alert-danger'],
            8 => ['msg'=>'El producto ha sido marcado como INACTIVO.','class'=>'alert-warning'],
        ];
        $alertMessage = '';
        $alertClass = 'alert-danger';
        
        if (isset($alertMap[$_GET['alert']])) {
            $data = $alertMap[$_GET['alert']];
            $alertMessage = $data['msg'];
            $alertClass = $data['class'];
        }
        
        // Si hay un mensaje personalizado en la URL, usarlo
        if (!empty($_GET['msg'])) {
            $alertMessage = htmlspecialchars(urldecode($_GET['msg']));
            $alertClass = 'alert-danger';
        }
        
        if ($alertMessage !== '') {
            echo "<div id='alert-message' class='alert {$alertClass} alert-dismissible fade show' role='alert'>
                    {$alertMessage}
                    <button type='button' class='close' data-dismiss='alert' aria-label='Close'>
                      <span aria-hidden='true'>&times;</span>
                    </button>
                  </div>";
        }
    }
    ?>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Lista de Productos</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Descripción</th>
                            <th>Unidad de Medida</th>
                                            <th>Precio</th>
                            <th>Tipo IVA</th>
                            <th>Tipo Producto</th>
                            <th>Depósito Predeterminado</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                        try {
                            // Verificar si existe la tabla tipo_producto
                            $checkTipoProducto = $pdo->query("
                                SELECT 1 FROM information_schema.tables 
                                WHERE table_name = 'tipo_producto' 
                                LIMIT 1
                            ");
                            $existeTipoProducto = $checkTipoProducto->rowCount() > 0;
                            
                            // Verificar columnas de tipo_producto si existe
                            $tipoProductoCols = array();
                            if ($existeTipoProducto) {
                                $cols = $pdo->query("
                                    SELECT column_name 
                                    FROM information_schema.columns 
                                    WHERE table_name = 'tipo_producto'
                                ")->fetchAll(PDO::FETCH_COLUMN);
                                $tipoProductoCols = $cols;
                            }
                            
                            $joinTipoProducto = '';
                            $selectTipoProducto = "'Sin tipo' AS tipo_producto";
                            $groupByTipoProducto = '';
                            
                            if ($existeTipoProducto && in_array('cod_tipo_prod', $tipoProductoCols) && in_array('t_p_descrip', $tipoProductoCols)) {
                                $joinTipoProducto = 'LEFT JOIN tipo_producto tp ON tp.cod_tipo_prod = p.id_tipo_producto';
                                $selectTipoProducto = "COALESCE(tp.t_p_descrip, 'Sin tipo') AS tipo_producto";
                                $groupByTipoProducto = ', tp.t_p_descrip';
                            }
                            
                            $sql = "
                                SELECT 
                                    p.producto_id,
                                    p.producto_descri,
                                    p.producto_precio,
                                    p.producto_estado,
                                    um.unidad_descri,
                                    COALESCE(ti.iva_descri, 'Sin IVA') AS iva_descri,
                                    COALESCE(d.deposito_descri, 'Sin depósito') AS deposito_descri,
                                    {$selectTipoProducto},
                                    COALESCE(SUM(sp.stock_prod_existente), 0) AS stock_total
                                FROM productos p
                                JOIN unidad_medida um ON p.id_unidad = um.id_unidad
                                LEFT JOIN tipo_iva ti ON p.iva_id = ti.iva_id
                                {$joinTipoProducto}
                                LEFT JOIN stock_producto sp ON sp.producto_id = p.producto_id
                                LEFT JOIN deposito d ON d.deposito_id = (
                                    SELECT deposito_id FROM stock_producto 
                                    WHERE producto_id = p.producto_id 
                                    ORDER BY stock_prod_existente DESC 
                                    LIMIT 1
                                )
                                GROUP BY p.producto_id, p.producto_descri, p.producto_precio, p.producto_estado,
                                         um.unidad_descri, ti.iva_descri, d.deposito_descri{$groupByTipoProducto}
                                ORDER BY p.producto_id DESC
                            ";
                            foreach ($pdo->query($sql) as $data) {
                                $estadoClass = '';
                                $estado = strtoupper(trim($data['producto_estado']));
                                if ($estado === 'ACTIVO') $estadoClass = 'badge-success';
                                elseif ($estado === 'INACTIVO') $estadoClass = 'badge-warning';
                                else $estadoClass = 'badge-danger';

                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($data['producto_id']) . "</td>";
                                echo "<td>" . htmlspecialchars($data['producto_descri']) . "</td>";
                                echo "<td>" . htmlspecialchars($data['unidad_descri']) . "</td>";
                                echo "<td>" . number_format($data['producto_precio'], 0, ',', '.') . " Gs.</td>";
                                echo "<td>" . htmlspecialchars($data['iva_descri']) . "</td>";
                                echo "<td>" . htmlspecialchars($data['tipo_producto']) . "</td>";
                                echo "<td>" . htmlspecialchars($data['deposito_descri']) . "</td>";
                                echo "<td><span class='badge {$estadoClass}'>" . htmlspecialchars($estado) . "</span></td>";
                                echo "<td>";
                                echo "<a href='?form_producto=edit&form=edit&id={$data['producto_id']}' class='btn btn-warning btn-sm' title='Editar'>";
                                echo "<i class='fas fa-edit'></i></a> ";
                                echo "<button type='button' class='btn btn-info btn-sm btn-historial' data-id='{$data['producto_id']}' data-descripcion='" . htmlspecialchars($data['producto_descri'], ENT_QUOTES) . "' title='Ver Historial'>";
                                echo "<i class='fas fa-history'></i></button> ";
                                echo "<button type='button' class='btn btn-danger btn-sm btn-eliminar' data-id='{$data['producto_id']}' data-descripcion='" . htmlspecialchars($data['producto_descri'], ENT_QUOTES) . "' data-stock='{$data['stock_total']}' title='Inactivar'>";
                                echo "<i class='fas fa-ban'></i></button>";
                                echo "</td>";
                                echo "</tr>";
                            }
                                        } catch (PDOException $e) {
                            echo "<tr><td colspan='9'>Error al consultar los datos: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

    <!-- Modal de Inactivación -->
    <div class="modal fade" id="eliminarModal" tabindex="-1" role="dialog" aria-labelledby="eliminarModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eliminarModalLabel">Confirmar Inactivación</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro de que desea <strong>inactivar</strong> el producto <strong id="eliminarProductoDesc"></strong>?</p>
                    <div id="eliminarInfo" class="alert alert-info">
                        <small id="eliminarInfoText"></small>
                    </div>
                    <p class="text-warning"><strong>Nota:</strong> El producto se marcará como INACTIVO y no se podrá usar en nuevas operaciones, pero los registros se mantendrán en el sistema.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmEliminarBtn">Inactivar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Historial -->
    <div class="modal fade" id="historialModal" tabindex="-1" role="dialog" aria-labelledby="historialModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historialModalLabel">Historial de Cambios - <span id="historialProductoDesc"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="historialContent">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="sr-only">Cargando...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Reporte -->
    <div class="modal fade" id="modalReporte" tabindex="-1" role="dialog" aria-labelledby="modalReporteLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalReporteLabel">Opciones de Reporte</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="reporte.php" method="GET" target="_blank">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="filtro_estado_reporte">Estado</label>
                            <select name="estado" id="filtro_estado_reporte" class="form-control">
                                <option value="">Todos</option>
                                <option value="ACTIVO">Activos</option>
                                <option value="INACTIVO">Inactivos</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="filtro_iva_reporte">Tipo de IVA</label>
                            <select name="iva_id" id="filtro_iva_reporte" class="form-control">
                                <option value="">Todos</option>
                                <?php
                                try {
                                    $query_iva = $pdo->query("SELECT iva_id, iva_descri FROM tipo_iva ORDER BY iva_id ASC");
                                    while ($iva = $query_iva->fetch()) {
                                        echo "<option value=\"{$iva['iva_id']}\">{$iva['iva_descri']}</option>";
                                    }
                                } catch (PDOException $e) {
                                    // Silenciar error
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="filtro_tipo_reporte">Tipo de Producto</label>
                            <select name="tipo_producto" id="filtro_tipo_reporte" class="form-control">
                                <option value="">Todos</option>
                                <?php
                                try {
                                    $checkTable = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'tipo_producto' LIMIT 1");
                                    if ($checkTable->rowCount() > 0) {
                                        $cols = $pdo->query("
                                            SELECT column_name 
                                            FROM information_schema.columns 
                                            WHERE table_name = 'tipo_producto'
                                        ")->fetchAll(PDO::FETCH_COLUMN);
                                        
                                        if (in_array('cod_tipo_prod', $cols) && in_array('t_p_descrip', $cols)) {
                                            $query_tipo = $pdo->query("SELECT cod_tipo_prod, t_p_descrip FROM tipo_producto ORDER BY t_p_descrip ASC");
                                            while ($tipo = $query_tipo->fetch()) {
                                                echo "<option value=\"{$tipo['cod_tipo_prod']}\">{$tipo['t_p_descrip']}</option>";
                                            }
                                        }
                                    }
                                } catch (PDOException $e) {
                                    // Silenciar error
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Generar Reporte</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php else: ?>
    <?php include 'form.php'; ?>
<?php endif; ?>

<?php include '../../footer.php'; ?>

    <script>
$(document).ready(function() {
    $('#dataTable').DataTable({
        "language": {
            "url": "<?= $BASE_PATH ?>vendor/datatables/Spanish.json"
        },
        "order": [[0, "desc"]]
    });

    // Manejar clic en botón de eliminar
    $('#dataTable').on('click', '.btn-eliminar', function() {
        var productoId = $(this).data('id');
        var descripcion = $(this).data('descripcion');
        var stock = parseFloat($(this).data('stock')) || 0;
        
        $('#eliminarProductoDesc').text(descripcion);
        
        var infoText = '';
        if (stock > 0) {
            infoText = 'El producto tiene stock de ' + stock.toLocaleString('es-PY') + ' unidades.';
        } else {
            infoText = 'El producto no tiene stock.';
        }
        $('#eliminarInfoText').text(infoText);
        
        $('#eliminarModal').modal('show');

        $('#confirmEliminarBtn').off('click').on('click', function() {
            $.ajax({
                url: 'proses.php?act=delete',
                type: 'POST',
                data: { id: productoId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        window.location.reload();
                    } else {
                        alert('Error: ' + (response.message || 'No se pudo inactivar el producto'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error de comunicación: ' + error);
                }
            });
        });
    });

    // Manejar clic en botón de historial
    $('#dataTable').on('click', '.btn-historial', function() {
        var productoId = $(this).data('id');
        var descripcion = $(this).data('descripcion');
        
        $('#historialProductoDesc').text(descripcion);
        $('#historialContent').html('<div class="text-center"><div class="spinner-border" role="status"><span class="sr-only">Cargando...</span></div></div>');
        $('#historialModal').modal('show');

            $.ajax({
            url: 'get_historial.php',
            type: 'GET',
            data: { producto_id: productoId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var html = '<table class="table table-bordered table-sm">';
                    html += '<thead><tr><th>Fecha</th><th>Campo</th><th>Valor Anterior</th><th>Valor Nuevo</th><th>Acción</th><th>Usuario</th></tr></thead><tbody>';
                    
                    if (response.historial && response.historial.length > 0) {
                        response.historial.forEach(function(item) {
                            html += '<tr>';
                            html += '<td>' + item.fecha_modificacion + '</td>';
                            html += '<td>' + item.campo_modificado + '</td>';
                            html += '<td>' + (item.valor_anterior || '-') + '</td>';
                            html += '<td>' + (item.valor_nuevo || '-') + '</td>';
                            html += '<td><span class="badge badge-info">' + item.accion + '</span></td>';
                            html += '<td>' + item.username + '</td>';
                            html += '</tr>';
                        });
                    } else {
                        html += '<tr><td colspan="6" class="text-center text-muted">No hay historial disponible</td></tr>';
                    }
                    
                    html += '</tbody></table>';
                    $('#historialContent').html(html);
            } else {
                    $('#historialContent').html('<div class="alert alert-warning">' + (response.message || 'No se pudo cargar el historial') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#historialContent').html('<div class="alert alert-danger">Error al cargar el historial: ' + error + '</div>');
            }
        });
        });
        });
    </script>
