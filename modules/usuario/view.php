<?php
session_start();

if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}

$file = realpath("../../config/database.php");
if (!$file || !file_exists($file)) {
    die("Error: No se pudo encontrar el archivo en la ruta $file");
}

require_once $file;

// Incluir sistema de permisos y verificar acceso
require_once realpath("../../config/permissions.php");
check_permission('ADMINISTRACION'); // Solo ADMIN puede gestionar usuarios

$alertCode = $_GET['alert'] ?? '';
$alertMessage = '';
$alertClass = 'success';
switch ($alertCode) {
    case '1':
        $alertMessage = 'Usuario registrado correctamente.';
        break;
    case '2':
        $alertMessage = 'Usuario actualizado correctamente.';
        break;
    case '3':
        $alertMessage = 'Estado del usuario actualizado.';
        break;
    case '4':
        $alertClass = 'danger';
        // Si hay un mensaje personalizado en la URL, usarlo; sino mostrar el genérico
        $alertMessage = !empty($_GET['msg']) ? htmlspecialchars(urldecode($_GET['msg'])) : 'Ocurrió un error al procesar la solicitud.';
        break;
}

// Si hay un mensaje en la URL y aún no se estableció, usarlo
if (empty($alertMessage) && !empty($_GET['msg'])) {
    $alertClass = 'danger';
    $alertMessage = htmlspecialchars(urldecode($_GET['msg']));
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Ya validado por check_permission arriba

    $stmt = $pdo->query("
        SELECT 
            u.id_usuario,
            u.username,
            COALESCE(p.personal_nombre, '') AS usu_nombre,
            COALESCE(p.personal_apellido, '') AS usu_apellido,
            COALESCE(p.personal_ci, '') AS usuario_ci,
            COALESCE(p.personal_telefono, '') AS usuario_telefono,
            COALESCE(u.estado_usuario, 'ACTIVO') AS estado_usuario,
            COALESCE(s.descripcion_sucursal, '-') AS sucursal,
            COALESCE(m.modulo_descri, '-') AS modulo,
            COALESCE(c.cargo_descripcion, '-') AS cargo,
            u.id_personal
        FROM usuarios u
        LEFT JOIN sucursales s ON s.id_sucursal = u.id_sucursal
        LEFT JOIN modulos m ON m.modulo_id = u.modulo_id
        LEFT JOIN personal p ON p.id_personal = u.id_personal
        LEFT JOIN cargos c ON c.id_cargo = COALESCE(u.id_cargo, p.id_cargo, 0)
        ORDER BY u.id_usuario ASC
    ");
    $usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error en la conexión a la base de datos: " . $e->getMessage());
}

// Configuración para el layout común
$BASE_PATH = '../../';
$page_title = 'Mantenimiento de Usuarios';
$extra_css = [
    'vendor/datatables/dataTables.bootstrap4.min.css'
];
$extra_js_plugins = [
    'vendor/datatables/jquery.dataTables.min.js',
    'vendor/datatables/dataTables.bootstrap4.min.js'
];

// El header.php ya maneja los permisos automáticamente

// Incluir header común
include '../../header.php';
?>

<!-- Contenido específico del módulo -->
<div class="container-fluid py-4">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Mantenimiento de Usuarios</h1>
        <a href="form.php?action=add" class="btn btn-primary btn-sm">
            <i class="fas fa-user-plus"></i> Nuevo Usuario
        </a>
    </div>

    <?php if ($alertMessage): ?>
        <div id="alert-message" class="alert alert-<?php echo $alertClass; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($alertMessage); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Usuarios registrados</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Apellido</th>
                            <th>Usuario</th>
                            <th>Cédula</th>
                            <th>Módulo</th>
                            <th>Sucursal</th>
                            <th>Cargo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td><?php echo (int)$usuario['id_usuario']; ?></td>
                                <td><?php echo htmlspecialchars($usuario['usu_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['usu_apellido']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['username']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['usuario_ci']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['modulo']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['sucursal']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['cargo']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $usuario['estado_usuario'] === 'ACTIVO' ? 'success' : 'secondary'; ?>">
                                        <?php echo htmlspecialchars($usuario['estado_usuario']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="form.php?action=edit&id=<?php echo (int)$usuario['id_usuario']; ?>" class="btn btn-info btn-sm mb-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form class="d-inline confirm-toggle" action="proses.php?act=toggle" method="POST">
                                        <input type="hidden" name="id_usuario" value="<?php echo (int)$usuario['id_usuario']; ?>">
                                        <input type="hidden" name="estado" value="<?php echo $usuario['estado_usuario'] === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO'; ?>">
                                        <button type="submit" class="btn btn-<?php echo $usuario['estado_usuario'] === 'ACTIVO' ? 'warning' : 'success'; ?> btn-sm">
                                            <?php echo $usuario['estado_usuario'] === 'ACTIVO' ? 'Inactivar' : 'Activar'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$inline_js = "
setTimeout(function() {
    var alertMessage = document.getElementById('alert-message');
    if (alertMessage) {
        alertMessage.style.display = 'none';
    }
}, 3000);

$('#dataTable').DataTable({
    language: {
        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
    }
});

document.querySelectorAll('.confirm-toggle').forEach(form => {
    form.addEventListener('submit', (event) => {
        const shouldSubmit = confirm('¿Confirma cambiar el estado del usuario?');
        if (!shouldSubmit) {
            event.preventDefault();
        }
    });
});
";
include '../../footer.php';
?>
