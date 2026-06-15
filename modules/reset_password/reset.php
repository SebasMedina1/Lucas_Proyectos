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

// Todos los usuarios autenticados pueden cambiar su propia contraseña
// No se requiere verificación de permisos especiales

$alertCode = $_GET['alert'] ?? '';
$alertMessage = '';
$alertClass = 'success';

switch ($alertCode) {
    case '1':
        $alertClass = 'danger';
        $alertMessage = 'La contraseña actual no coincide.';
        break;
    case '2':
        $alertClass = 'danger';
        $alertMessage = 'Las nuevas contraseñas no coinciden.';
        break;
    case '3':
        $alertClass = 'success';
        $alertMessage = 'Contraseña actualizada correctamente.';
        break;
    case '4':
        $alertClass = 'danger';
        $alertMessage = 'Los campos no pueden estar vacíos.';
        break;
}

// Configuración para el layout común
$BASE_PATH = '../../';
$page_title = 'Cambiar Contraseña';
$extra_css = [];

// Incluir header común
include '../../header.php';
?>

<!-- Contenido específico del módulo -->
<div class="container-fluid py-4">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Cambiar Contraseña</h1>
    </div>

    <?php if ($alertMessage): ?>
        <div id="alert-message" class="alert alert-<?php echo $alertClass; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($alertMessage); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Cambiar mi contraseña</h6>
                </div>
                <div class="card-body">
                    <form role="form" class="form-horizontal" action="proses.php" method="POST" id="form-cambiar-password">
                        
                        <!-- Campo: Contraseña actual -->
                        <div class="form-group">
                            <label for="old_pass">Ingrese su contraseña actual *</label>
                            <div class="input-group">
                                <input type="password" 
                                       class="form-control" 
                                       name="old_pass" 
                                       id="old_pass" 
                                       required 
                                       autocomplete="current-password">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#old_pass">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Campo: Nueva contraseña -->
                        <div class="form-group">
                            <label for="new_pass">Ingrese la nueva contraseña *</label>
                            <div class="input-group">
                                <input type="password" 
                                       class="form-control" 
                                       name="new_pass" 
                                       id="new_pass" 
                                       required 
                                       minlength="8" 
                                       maxlength="15" 
                                       pattern="^(?=.*\d).+$"
                                       autocomplete="new-password">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#new_pass">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">Mínimo 8 caracteres e incluir al menos un número.</small>
                        </div>

                        <!-- Campo: Confirmar nueva contraseña -->
                        <div class="form-group">
                            <label for="retype_pass">Confirme su nueva contraseña *</label>
                            <div class="input-group">
                                <input type="password" 
                                       class="form-control" 
                                       name="retype_pass" 
                                       id="retype_pass" 
                                       required 
                                       minlength="8" 
                                       maxlength="15" 
                                       pattern="^(?=.*\d).+$"
                                       autocomplete="new-password">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#retype_pass">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">Debe coincidir con la nueva contraseña.</small>
                        </div>

                        <!-- Botón de guardar -->
                        <button type="submit" name="Guardar" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$inline_js = "
// Ocultar automáticamente el mensaje de alerta después de 5 segundos
setTimeout(function() {
    var alertMessage = document.getElementById('alert-message');
    if (alertMessage) {
        alertMessage.style.display = 'none';
    }
}, 5000);

// JavaScript para alternar mostrar/ocultar contraseñas
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const targetInput = document.querySelector(targetId);
        const icon = this.querySelector('i');
        
        if (targetInput.type === 'password') {
            targetInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            targetInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
});

// Validación de contraseña en tiempo real
document.getElementById('new_pass').addEventListener('input', function() {
    var password = this.value;
    var pattern = /^(?=.*\d).+$/;
    
    if (password.length < 8 || password.length > 15) {
        this.setCustomValidity('La contraseña debe tener entre 8 y 15 caracteres.');
    } else if (!pattern.test(password)) {
        this.setCustomValidity('La contraseña debe incluir al menos un número.');
    } else {
        this.setCustomValidity('');
    }
});

// Validar que las contraseñas coincidan
document.getElementById('retype_pass').addEventListener('input', function() {
    var newPass = document.getElementById('new_pass').value;
    var retypePass = this.value;
    
    if (newPass !== retypePass) {
        this.setCustomValidity('Las contraseñas no coinciden.');
    } else {
        this.setCustomValidity('');
    }
});
";

include '../../footer.php';
?>
