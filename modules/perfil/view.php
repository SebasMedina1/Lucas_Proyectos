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

$username = $_SESSION['username'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->prepare("
        SELECT
            u.username,
            COALESCE(p.personal_nombre, '') AS nombre,
            COALESCE(p.personal_apellido, '') AS apellido,
            COALESCE(p.personal_ci, '') AS ci,
            COALESCE(p.personal_telefono, '') AS telefono,
            COALESCE(u.estado_usuario, '') AS estado,
            COALESCE(s.descripcion_sucursal, '') AS sucursal
        FROM usuarios u
        LEFT JOIN personal p ON p.id_personal = u.id_personal
        LEFT JOIN sucursales s ON s.id_sucursal = u.id_sucursal
        WHERE u.username = :username
        LIMIT 1
    ");
    $stmt->execute([':username' => $username]);
    $perfil = $stmt->fetch();

    if (!$perfil) {
        session_destroy();
        echo "<script>
                alert('Usuario no encontrado, serás redirigido al inicio de sesión');
                window.location.href = '../../login.html';
              </script>";
        exit();
    }

    $query = $pdo->prepare("SELECT id_cargo FROM usuarios WHERE username = :username LIMIT 1");
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->execute();
    $auth_user = $query->fetch(PDO::FETCH_ASSOC);
    $permisoAcceso = $auth_user['id_cargo'] ?? 0;
} catch (PDOException $e) {
    die("Error en la conexión a la base de datos: " . $e->getMessage());
}

// Configuración para el layout común
$BASE_PATH = '../../';
$page_title = 'Perfil de Usuario';

// Variables para el sidebar (necesarias para header.php)
$allowedCargos = [1,3,5];
$showCoreSidebar = in_array($permisoAcceso, $allowedCargos, true);
$showReportes = in_array($permisoAcceso, [3,5], true);
$showAdministracion = ($permisoAcceso === 5);

// Incluir header común
include '../../header.php';
?>

<!-- Contenido específico del módulo -->
<div class="container-fluid py-4">
    <h1 class="h3 mb-4 text-gray-800">Perfil del Usuario</h1>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Datos personales</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <strong>Nombre:</strong>
                    <p class="text-uppercase mb-0"><?php echo htmlspecialchars($perfil['nombre']); ?></p>
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Apellido:</strong>
                    <p class="text-uppercase mb-0"><?php echo htmlspecialchars($perfil['apellido']); ?></p>
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Usuario:</strong>
                    <p class="mb-0"><?php echo htmlspecialchars($perfil['username']); ?></p>
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Cédula:</strong>
                    <p class="mb-0"><?php echo htmlspecialchars($perfil['ci']); ?></p>
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Teléfono:</strong>
                    <p class="mb-0"><?php echo htmlspecialchars($perfil['telefono'] ?: '-'); ?></p>
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Estado:</strong>
                    <span class="badge badge-<?php echo $perfil['estado'] === 'ACTIVO' ? 'success' : 'secondary'; ?>">
                        <?php echo htmlspecialchars($perfil['estado']); ?>
                    </span>
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Sucursal:</strong>
                    <p class="mb-0"><?php echo htmlspecialchars($perfil['sucursal']); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include '../../footer.php';
?>
