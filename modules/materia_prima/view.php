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
check_permission('REFERENCIALES'); // Materia prima es un módulo referencial

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
$page_title = 'Mantener Materia Prima';
$extra_css = [
    'vendor/datatables/dataTables.bootstrap4.min.css'
];
$extra_js_plugins = [
    'vendor/datatables/jquery.dataTables.min.js',
    'vendor/datatables/dataTables.bootstrap4.min.js'
];

// Incluir header común (ya maneja permisos dinámicamente)
include '../../header.php';

$mostrarListado = !(isset($_GET['form_materia_prima']) && ($_GET['form'] === 'add' || $_GET['form'] === 'edit'));
?>

<!-- Contenido específico del módulo -->
<?php if ($mostrarListado): ?>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Mantener Materia Prima</h1>
        <div>
            <a href="?form_materia_prima=add&form=add" class="btn btn-primary btn-sm shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> Nueva Materia Prima
            </a>
            <button type="button" class="btn btn-warning btn-sm shadow-sm" data-toggle="modal" data-target="#modalReporte">
                <i class="fas fa-print fa-sm text-white-50"></i> Imprimir Reporte
        </button>
  </div>
</div>

    <?php
    if (!empty($_GET['alert'])) {
        $alertMap = [
            1 => ['msg'=>'Materia prima registrada correctamente.','class'=>'alert-success'],
            2 => ['msg'=>'Materia prima modificada correctamente.','class'=>'alert-success'],
            3 => ['msg'=>'Materia prima inactivada correctamente.','class'=>'alert-success'],
            4 => ['msg'=>'No se pudo realizar la operación.','class'=>'alert-danger'],
            8 => ['msg'=>'La materia prima ha sido marcada como INACTIVA.','class'=>'alert-warning'],
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
                            <h6 class="m-0 font-weight-bold text-primary">Lista de Materias Primas</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Descripción</th>
                            <th>Unidad de Medida</th>
                            <th>Tipo IVA</th>
                            <th>Depósito Predeterminado</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                        try {
                            $sql = "
                                SELECT 
                                    mp.id_materia_prima,
                                    mp.materia_prima_descripcion,
                                    mp.materia_prima_estado,
                                    um.unidad_descri,
                                    COALESCE(ti.iva_descri, 'Sin IVA') AS iva_descri,
                                    COALESCE(d.deposito_descri, 'Sin depósito') AS deposito_descri,
                                    COALESCE(SUM(smp.cantidad_existente), 0) AS stock_total
                                FROM materia_prima mp
                                LEFT JOIN unidad_medida um ON mp.id_unidad = um.id_unidad
                                LEFT JOIN tipo_iva ti ON mp.iva_id = ti.iva_id
                                LEFT JOIN stock_materia_prima smp ON smp.id_materia_prima = mp.id_materia_prima
                                LEFT JOIN deposito d ON d.deposito_id = (
                                    SELECT deposito_id FROM stock_materia_prima 
                                    WHERE id_materia_prima = mp.id_materia_prima 
                                    ORDER BY cantidad_existente DESC 
                                    LIMIT 1
                                )
                                GROUP BY mp.id_materia_prima, mp.materia_prima_descripcion,
                                         mp.materia_prima_estado, um.unidad_descri, 
                                         ti.iva_descri, d.deposito_descri
                                ORDER BY mp.id_materia_prima DESC
                            ";
                            foreach ($pdo->query($sql) as $data) {
                                $estadoClass = '';
                                $estado = strtoupper(trim($data['materia_prima_estado'] ?? 'ACTIVO'));
                                if ($estado === 'ACTIVO') $estadoClass = 'badge-success';
                                elseif ($estado === 'INACTIVO') $estadoClass = 'badge-warning';
                                else $estadoClass = 'badge-danger';

                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($data['id_materia_prima']) . "</td>";
                                echo "<td>" . htmlspecialchars($data['materia_prima_descripcion']) . "</td>";
                                echo "<td>" . htmlspecialchars($data['unidad_descri'] ?? 'Sin unidad') . "</td>";
                                echo "<td>" . htmlspecialchars($data['iva_descri']) . "</td>";
                                echo "<td>" . htmlspecialchars($data['deposito_descri']) . "</td>";
                                echo "<td><span class='badge {$estadoClass}'>" . htmlspecialchars($estado) . "</span></td>";
                                echo "<td>";
                                echo "<a href='?form_materia_prima=edit&form=edit&id={$data['id_materia_prima']}' class='btn btn-warning btn-sm' title='Editar'>";
                                echo "<i class='fas fa-edit'></i></a> ";
                                echo "<button type='button' class='btn btn-danger btn-sm btn-eliminar' data-id='{$data['id_materia_prima']}' data-descripcion='" . htmlspecialchars($data['materia_prima_descripcion'], ENT_QUOTES) . "' data-stock='{$data['stock_total']}' title='Inactivar'>";
                                echo "<i class='fas fa-ban'></i></button>";
                                echo "</td>";
                                echo "</tr>";
                            }
                                        } catch (PDOException $e) {
                            echo "<tr><td colspan='7'>Error al consultar los datos: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
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
                    <p>¿Está seguro de que desea <strong>inactivar</strong> la materia prima <strong id="eliminarMateriaPrimaDesc"></strong>?</p>
                    <div id="eliminarInfo" class="alert alert-info">
                        <small id="eliminarInfoText"></small>
                    </div>
                    <p class="text-warning"><strong>Nota:</strong> La materia prima se marcará como INACTIVA y no se podrá usar en nuevas operaciones, pero los registros se mantendrán en el sistema.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmEliminarBtn">Inactivar</button>
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
                                <option value="ACTIVO">Activas</option>
                                <option value="INACTIVO">Inactivas</option>
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
        var materiaPrimaId = $(this).data('id');
        var descripcion = $(this).data('descripcion');
        var stock = parseFloat($(this).data('stock')) || 0;
        
        $('#eliminarMateriaPrimaDesc').text(descripcion);
        
        var infoText = '';
        if (stock > 0) {
            infoText = 'La materia prima tiene stock de ' + stock.toLocaleString('es-PY') + ' unidades.';
        } else {
            infoText = 'La materia prima no tiene stock.';
        }
        $('#eliminarInfoText').text(infoText);
        
        $('#eliminarModal').modal('show');

        $('#confirmEliminarBtn').off('click').on('click', function() {
            $.ajax({
                url: 'proses.php?act=delete',
                type: 'POST',
                data: { id: materiaPrimaId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        window.location.reload();
                    } else {
                        alert('Error: ' + (response.message || 'No se pudo inactivar la materia prima'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error de comunicación: ' + error);
                }
            });
        });
    });
});
    </script>

