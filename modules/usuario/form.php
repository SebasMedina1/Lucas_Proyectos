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

$action = $_GET['action'] ?? 'add';
$idUsuario = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$usuario = [
    'username'     => '',
    'id_sucursal'  => '',
    'modulo_id'    => '',
    'id_cargo'     => '',
    'id_personal'  => '',
    'estado_usuario' => 'ACTIVO',
];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    if ($action === 'edit') {
        if ($idUsuario <= 0) {
            header("Location: view.php?alert=4");
            exit();
        }
        // Obtener datos del usuario y del personal asociado si existe
        $stmt = $pdo->prepare("
            SELECT 
                u.*,
                p.personal_nombre,
                p.personal_apellido,
                p.personal_ci,
                p.personal_telefono
            FROM usuarios u
            LEFT JOIN personal p ON p.id_personal = u.id_personal
            WHERE u.id_usuario = :id 
            LIMIT 1
        ");
        $stmt->execute([':id' => $idUsuario]);
        $data = $stmt->fetch();
        if (!$data) {
            header("Location: view.php?alert=4");
            exit();
        }
        $usuario = array_merge($usuario, $data);
        // Mapear datos de personal a variables para el formulario
        $usuario['personal_nombre'] = $data['personal_nombre'] ?? '';
        $usuario['personal_apellido'] = $data['personal_apellido'] ?? '';
        $usuario['personal_ci'] = $data['personal_ci'] ?? '';
        $usuario['personal_telefono'] = $data['personal_telefono'] ?? '';
    }

    $modulos = $pdo->query("SELECT modulo_id, modulo_descri FROM modulos ORDER BY modulo_descri ASC")->fetchAll();
    $sucursales = $pdo->query("SELECT id_sucursal, descripcion_sucursal FROM sucursales ORDER BY descripcion_sucursal ASC")->fetchAll();
    $cargos = $pdo->query("SELECT id_cargo, cargo_descripcion FROM cargos WHERE estado_cargo = 'ACTIVO' ORDER BY cargo_descripcion ASC")->fetchAll();
    $personalList = $pdo->query("
        SELECT 
            id_personal, 
            personal_nombre || ' ' || personal_apellido || ' (' || personal_ci || ')' AS nombre_completo
        FROM personal 
        WHERE personal_estado = 'ACTIVO' 
        ORDER BY personal_nombre, personal_apellido ASC
    ")->fetchAll();

} catch (PDOException $e) {
    die("Error en la conexión a la base de datos: " . $e->getMessage());
}

$formAction = $action === 'edit' ? "proses.php?act=update" : "proses.php?act=insert";
$titulo = $action === 'edit' ? 'Editar Usuario' : 'Nuevo Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $titulo; ?></title>
    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">
</head>
<body id="page-top">
    <div class="container py-5">
        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="m-0 font-weight-bold text-primary"><?php echo $titulo; ?></h5>
                <a href="view.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
            <div class="card-body">
                <form action="<?php echo $formAction; ?>" method="POST" autocomplete="off">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="id_usuario" value="<?php echo (int)$idUsuario; ?>">
                    <?php endif; ?>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="username">Usuario *</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($usuario['username'] ?? ''); ?>" required autocomplete="off" maxlength="30">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="password"><?php echo $action === 'edit' ? 'Nueva contraseña' : 'Contraseña *'; ?></label>
                            <input type="password" class="form-control" id="password" name="password" <?php echo $action === 'add' ? 'required' : ''; ?> placeholder="<?php echo $action === 'edit' ? 'Dejar en blanco para mantener' : ''; ?>" autocomplete="new-password" minlength="8" maxlength="15" pattern="^(?=.*\d).+$">
                            <small class="form-text text-muted">Mínimo 8 caracteres e incluir al menos un número.</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="modulo_id">Módulo *</label>
                            <select id="modulo_id" name="modulo_id" class="form-control" required>
                                <option value="" disabled <?php echo $usuario['modulo_id'] === '' ? 'selected' : ''; ?>>Seleccione...</option>
                                <?php foreach ($modulos as $modulo): ?>
                                    <option value="<?php echo (int)$modulo['modulo_id']; ?>" <?php echo $usuario['modulo_id'] == $modulo['modulo_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($modulo['modulo_descri']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="id_sucursal">Sucursal *</label>
                            <select id="id_sucursal" name="id_sucursal" class="form-control" required>
                                <option value="" disabled <?php echo $usuario['id_sucursal'] === '' ? 'selected' : ''; ?>>Seleccione...</option>
                                <?php foreach ($sucursales as $sucursal): ?>
                                    <option value="<?php echo (int)$sucursal['id_sucursal']; ?>" <?php echo $usuario['id_sucursal'] == $sucursal['id_sucursal'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sucursal['descripcion_sucursal']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="id_cargo">Cargo *</label>
                            <select id="id_cargo" name="id_cargo" class="form-control" required>
                                <option value="" disabled <?php echo $usuario['id_cargo'] === '' ? 'selected' : ''; ?>>Seleccione...</option>
                                <?php foreach ($cargos as $cargo): ?>
                                    <option value="<?php echo (int)$cargo['id_cargo']; ?>" <?php echo $usuario['id_cargo'] == $cargo['id_cargo'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cargo['cargo_descripcion']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-12">
                            <label for="id_personal">Personal (Opcional)</label>
                            <select id="id_personal" name="id_personal" class="form-control">
                                <option value="">-- Sin personal asociado --</option>
                                <?php foreach ($personalList as $personal): ?>
                                    <option value="<?php echo (int)$personal['id_personal']; ?>" <?php echo (isset($usuario['id_personal']) && $usuario['id_personal'] == $personal['id_personal']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($personal['nombre_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">
                                Seleccione un personal existente para asociarlo al usuario. 
                                <?php if ($action === 'add'): ?>
                                    Si no selecciona ninguno, el usuario se creará sin personal asociado.
                                <?php else: ?>
                                    Los datos personales (nombre, apellido, CI, teléfono) se obtienen del personal asociado.
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>

                    <?php if ($action === 'edit' && !empty($usuario['id_personal'])): ?>
                        <div class="alert alert-info">
                            <strong>Personal asociado:</strong><br>
                            <strong>Nombre:</strong> <?php echo htmlspecialchars($usuario['personal_nombre'] ?? ''); ?> <?php echo htmlspecialchars($usuario['personal_apellido'] ?? ''); ?><br>
                            <strong>Cédula:</strong> <?php echo htmlspecialchars($usuario['personal_ci'] ?? ''); ?><br>
                            <strong>Teléfono:</strong> <?php echo htmlspecialchars($usuario['personal_telefono'] ?? ''); ?>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                    <a href="view.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>

    <script src="../../vendor/jquery/jquery.min.js"></script>
    <script src="../../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../../js/sb-admin-2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            var $moduloSelect = $('#modulo_id');
            var $cargoSelect = $('#id_cargo');
            var cargoActual = <?php echo json_encode((int)$usuario['id_cargo']); ?>;
            
            // Función para cargar cargos según el módulo
            function cargarCargosPorModulo(moduloId) {
                if (!moduloId || moduloId === '') {
                    $cargoSelect.html('<option value="" disabled selected>Primero seleccione un módulo</option>');
                    $cargoSelect.prop('disabled', true);
                    return;
                }
                
                $cargoSelect.prop('disabled', true);
                $cargoSelect.html('<option value="">Cargando...</option>');
                
                $.ajax({
                    url: 'get_cargos_por_modulo.php',
                    method: 'GET',
                    data: { modulo_id: moduloId },
                    dataType: 'json',
                    success: function(response) {
                        $cargoSelect.html('<option value="" disabled>Seleccione un cargo</option>');
                        
                        if (response.cargos && response.cargos.length > 0) {
                            $.each(response.cargos, function(index, cargo) {
                                var selected = (cargo.id_cargo == cargoActual) ? 'selected' : '';
                                $cargoSelect.append(
                                    $('<option></option>')
                                        .attr('value', cargo.id_cargo)
                                        .attr('selected', selected)
                                        .text(cargo.cargo_descripcion)
                                );
                            });
                            
                            // Si había un cargo seleccionado y está en la lista, mantenerlo
                            if (cargoActual > 0) {
                                $cargoSelect.val(cargoActual);
                            }
                            
                            $cargoSelect.prop('disabled', false);
                        } else {
                            $cargoSelect.html('<option value="" disabled>No hay cargos disponibles para este módulo</option>');
                            $cargoSelect.prop('disabled', true);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error al cargar cargos:', error);
                        $cargoSelect.html('<option value="" disabled>Error al cargar cargos</option>');
                        $cargoSelect.prop('disabled', true);
                    }
                });
            }
            
            // Cargar cargos al cambiar el módulo
            $moduloSelect.on('change', function() {
                var moduloId = $(this).val();
                cargoActual = 0; // Reset cargo actual al cambiar módulo
                cargarCargosPorModulo(moduloId);
            });
            
            // Si ya hay un módulo seleccionado (modo edición), cargar sus cargos al inicio
            var moduloSeleccionado = $moduloSelect.val();
            if (moduloSeleccionado && moduloSeleccionado !== '') {
                cargarCargosPorModulo(moduloSeleccionado);
            } else {
                $cargoSelect.prop('disabled', true);
            }
        });
    </script>
</body>
</html>

