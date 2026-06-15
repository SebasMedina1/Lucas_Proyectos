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
    $permisoAcceso = $auth_user['id_cargo'] ?? 0;
    
    // Verificar si se encontraron datos del usuario
    if (!$auth_user) {
        // Si no se encuentra al usuario, destruir la sesión y redirigir al login
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

// Configuración para el layout común
$BASE_PATH = '../../';
$page_title = 'Gestión de Stock';
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

// Obtener filtros
$filtro_deposito = $_GET['filtro_deposito'] ?? '';
$filtro_producto = $_GET['filtro_producto'] ?? '';
?>

<!-- Contenido específico del módulo -->
<div class="container-fluid">
    <!-- Título de la página -->
    <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-warehouse"></i> Inventario por Depósitos</h1>

    <!-- Formulario de Filtrado -->
    <form method="GET" action="">
        <div class="row">
            <div class="col-md-4">
                <label for="filtro_deposito">Filtrar por Depósito</label>
                <select name="filtro_deposito" id="filtro_deposito" class="form-control">
                    <option value="">Todos los Depósitos</option>
                    <?php
                    try {
                        // Verificar si existe deposito_id o cod_deposito
                        $query_test = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'deposito' LIMIT 1");
                        $col_test = $query_test->fetch(PDO::FETCH_ASSOC);
                        $has_deposito_id = false;
                        if ($col_test) {
                            $query_cols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'deposito'");
                            while ($col = $query_cols->fetch(PDO::FETCH_ASSOC)) {
                                if ($col['column_name'] === 'deposito_id') {
                                    $has_deposito_id = true;
                                    break;
                                }
                            }
                        }
                        
                        if ($has_deposito_id) {
                            $query_depositos = $pdo->query("SELECT deposito_id, deposito_descri FROM deposito ORDER BY deposito_descri ASC");
                            while ($deposito = $query_depositos->fetch(PDO::FETCH_ASSOC)) {
                                $selected = ($filtro_deposito == $deposito['deposito_id']) ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($deposito['deposito_id']) . "\" $selected>" . htmlspecialchars($deposito['deposito_descri']) . "</option>";
                            }
                        } else {
                            $query_depositos = $pdo->query("SELECT cod_deposito, descrip FROM deposito ORDER BY descrip ASC");
                            while ($deposito = $query_depositos->fetch(PDO::FETCH_ASSOC)) {
                                $selected = ($filtro_deposito == $deposito['cod_deposito']) ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($deposito['cod_deposito']) . "\" $selected>" . htmlspecialchars($deposito['descrip']) . "</option>";
                            }
                        }
                    } catch (PDOException $e) {
                        echo "<option value=\"\">Error al cargar depósitos</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-4">
                <label for="filtro_producto">Filtrar por Producto</label>
                <select name="filtro_producto" id="filtro_producto" class="form-control">
                    <option value="">Todos los Productos</option>
                    <?php
                    try {
                        // Verificar estructura de tabla productos
                        $query_test = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_name IN ('productos', 'producto') LIMIT 1");
                        $table_test = $query_test->fetch(PDO::FETCH_ASSOC);
                        
                        if ($table_test && $table_test['table_name'] === 'productos') {
                            // Usar tabla productos con producto_id y producto_descri
                            $query_productos = $pdo->query("SELECT producto_id, producto_descri FROM productos WHERE producto_estado = 'ACTIVO' ORDER BY producto_descri ASC");
                            while ($producto = $query_productos->fetch(PDO::FETCH_ASSOC)) {
                                $selected = ($filtro_producto == $producto['producto_id']) ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($producto['producto_id']) . "\" $selected>" . htmlspecialchars($producto['producto_descri']) . "</option>";
                            }
                        } else {
                            // Fallback: usar tabla producto con cod_producto y p_descrip
                            $query_productos = $pdo->query("SELECT cod_producto, p_descrip FROM producto ORDER BY p_descrip ASC");
                            while ($producto = $query_productos->fetch(PDO::FETCH_ASSOC)) {
                                $selected = ($filtro_producto == $producto['cod_producto']) ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($producto['cod_producto']) . "\" $selected>" . htmlspecialchars($producto['p_descrip']) . "</option>";
                            }
                        }
                    } catch (PDOException $e) {
                        echo "<option value=\"\">Error al cargar productos</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-4 align-self-end">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="view.php" class="btn btn-secondary">Limpiar</a>
            </div>
        </div>
    </form>

    <!-- Tabla de Stock -->
    <div class="card shadow mt-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Stock Disponible</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Depósito</th>
                            <th>Producto</th>
                            <th>Precio Unitario</th>
                            <th>Cantidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            // Verificar qué tablas existen en la BD
                            $query_tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_name IN ('stock_producto', 'stock', 'productos', 'producto')");
                            $tables = [];
                            while ($t = $query_tables->fetch(PDO::FETCH_ASSOC)) {
                                $tables[] = $t['table_name'];
                            }
                            
                            // Determinar estructura de deposito
                            $query_dep_cols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'deposito'");
                            $dep_cols = [];
                            while ($col = $query_dep_cols->fetch(PDO::FETCH_ASSOC)) {
                                $dep_cols[] = $col['column_name'];
                            }
                            $has_deposito_id = in_array('deposito_id', $dep_cols);
                            $dep_id_col = $has_deposito_id ? 'deposito_id' : 'cod_deposito';
                            $dep_desc_col = $has_deposito_id ? 'deposito_descri' : 'descrip';
                            
                            // Usar stock_producto si existe, sino intentar con stock
                            if (in_array('stock_producto', $tables)) {
                                // Estructura moderna: stock_producto con productos
                                if (in_array('productos', $tables)) {
                                    $sql = "SELECT d.$dep_desc_col AS deposito, p.producto_descri AS producto, p.producto_precio AS precio_unitario, s.stock_prod_existente AS cantidad
                                            FROM stock_producto s
                                            JOIN deposito d ON d.$dep_id_col = s.deposito_id
                                            JOIN productos p ON p.producto_id = s.producto_id
                                            WHERE 1=1";
                                    
                                    if (!empty($filtro_deposito)) {
                                        $sql .= " AND s.deposito_id = :filtro_deposito";
                                    }
                                    if (!empty($filtro_producto)) {
                                        $sql .= " AND s.producto_id = :filtro_producto";
                                    }
                                    $sql .= " ORDER BY d.$dep_desc_col, p.producto_descri";
                                } else {
                                    // Fallback: stock_producto con producto
                                    $sql = "SELECT d.$dep_desc_col AS deposito, p.p_descrip AS producto, p.precio AS precio_unitario, s.stock_prod_existente AS cantidad
                                            FROM stock_producto s
                                            JOIN deposito d ON d.$dep_id_col = s.deposito_id
                                            JOIN producto p ON p.cod_producto = s.producto_id
                                            WHERE 1=1";
                                    
                                    if (!empty($filtro_deposito)) {
                                        $sql .= " AND s.deposito_id = :filtro_deposito";
                                    }
                                    if (!empty($filtro_producto)) {
                                        $sql .= " AND s.producto_id = :filtro_producto";
                                    }
                                    $sql .= " ORDER BY d.$dep_desc_col, p.p_descrip";
                                }
                            } else {
                                // Fallback: tabla stock antigua (si existe)
                                $sql = "SELECT d.$dep_desc_col AS deposito, p.p_descrip AS producto, p.precio AS precio_unitario, s.stock_existencia AS cantidad
                                        FROM stock s
                                        JOIN deposito d ON d.$dep_id_col = s.$dep_id_col
                                        JOIN producto p ON p.cod_producto = s.cod_producto
                                        WHERE 1=1";
                                
                                if (!empty($filtro_deposito)) {
                                    $sql .= " AND s.$dep_id_col = :filtro_deposito";
                                }
                                if (!empty($filtro_producto)) {
                                    $sql .= " AND s.cod_producto = :filtro_producto";
                                }
                                $sql .= " ORDER BY d.$dep_desc_col, p.p_descrip";
                            }

                            $stmt = $pdo->prepare($sql);

                            // Vincular parámetros si se aplican filtros
                            if (!empty($filtro_deposito)) {
                                $stmt->bindParam(':filtro_deposito', $filtro_deposito, PDO::PARAM_INT);
                            }
                            if (!empty($filtro_producto)) {
                                $stmt->bindParam(':filtro_producto', $filtro_producto, PDO::PARAM_INT);
                            }

                            $stmt->execute();
                            $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            // Mostrar los resultados
                            if (empty($stocks)) {
                                echo "<tr><td colspan='4' class='text-center'>No se encontraron registros con los filtros seleccionados.</td></tr>";
                            } else {
                                foreach ($stocks as $stock) {
                                    echo "<tr>
                                            <td>" . htmlspecialchars($stock['deposito']) . "</td>
                                            <td>" . htmlspecialchars($stock['producto']) . "</td>
                                            <td>" . number_format($stock['precio_unitario'], 2) . "</td>
                                            <td>" . htmlspecialchars($stock['cantidad']) . "</td>
                                          </tr>";
                                }
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='4' class='text-center text-danger'>Error al consultar stock: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$inline_js = "
$(document).ready(function () {
    $('#dataTable').DataTable({
        language: {
            decimal: '',
            emptyTable: 'No hay datos disponibles en la tabla',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'Mostrando 0 a 0 de 0 registros',
            infoFiltered: '(filtrado de _MAX_ registros totales)',
            lengthMenu: 'Mostrar _MENU_ registros',
            loadingRecords: 'Cargando...',
            processing: 'Procesando...',
            search: 'Buscar:',
            zeroRecords: 'No se encontraron registros coincidentes',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            },
            aria: {
                sortAscending: ': activar para ordenar de manera ascendente',
                sortDescending: ': activar para ordenar de manera descendente'
            }
        }
    });
});
";
include '../../footer.php';
?>
