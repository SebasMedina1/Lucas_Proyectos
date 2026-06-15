<?php
if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}

require "../../config/database.php";

$isEdit = isset($_GET['form_cliente']) && $_GET['form'] == 'edit' && isset($_GET['id']);
$clienteData = null;

if ($isEdit) {
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        $query = $pdo->prepare("
            SELECT * FROM clientes WHERE id_cliente = :id
        ");
        $query->execute([':id' => (int)$_GET['id']]);
        $clienteData = $query->fetch();
        
        if (!$clienteData) {
            echo "<script>alert('Cliente no encontrado'); window.location.href='view.php';</script>";
            exit();
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= $isEdit ? 'Editar Cliente' : 'Nuevo Cliente' ?></h1>
        <a href="view.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?= $isEdit ? 'Editar' : 'Registrar' ?> Cliente</h6>
        </div>
        <div class="card-body">
            <form action="proses.php?act=<?= $isEdit ? 'update' : 'insert' ?>" method="POST" id="formCliente">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id_cliente" value="<?= htmlspecialchars($clienteData['id_cliente']) ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="tipo_cliente">Tipo de Cliente <span class="text-danger">*</span></label>
                            <select name="tipo_cliente" id="tipo_cliente" class="form-control" required>
                                <option value="PERSONA" <?= ($isEdit && ($clienteData['tipo_cliente'] ?? 'PERSONA') === 'PERSONA') ? 'selected' : '' ?>>Persona Física</option>
                                <option value="EMPRESA" <?= ($isEdit && ($clienteData['tipo_cliente'] ?? '') === 'EMPRESA') ? 'selected' : '' ?>>Empresa</option>
                            </select>
                            <small class="form-text text-muted">Seleccione si es persona física o empresa</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="cliente_nombre" id="label_nombre">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="cliente_nombre" name="cliente_nombre" 
                                   value="<?= $isEdit ? htmlspecialchars($clienteData['cliente_nombre']) : '' ?>" 
                                   placeholder="Ingrese nombre o razón social" required maxlength="30">
                            <small class="form-text text-muted">Máximo 30 caracteres</small>
                        </div>
                    </div>

                    <div class="col-md-6" id="div_apellido">
                        <div class="form-group">
                            <label for="cliente_apellido">Apellido</label>
                            <input type="text" class="form-control" id="cliente_apellido" name="cliente_apellido" 
                                   value="<?= $isEdit ? htmlspecialchars($clienteData['cliente_apellido'] ?? '') : '' ?>" 
                                   placeholder="Ingrese apellido" maxlength="30">
                            <small class="form-text text-muted">Solo para personas físicas</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="cliente_ruc" id="label_ruc">RUC <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="cliente_ruc" name="cliente_ruc" 
                                   value="<?= $isEdit ? htmlspecialchars($clienteData['cliente_ruc']) : '' ?>" 
                                   placeholder="Ingrese RUC" required maxlength="30"
                                   pattern="[0-9-]+"
                                   oninput="this.value = this.value.replace(/[^0-9-]/g,'');">
                            <small class="form-text text-muted">Solo números y guiones</small>
                        </div>
                    </div>

                    <div class="col-md-6" id="div_ci">
                        <div class="form-group">
                            <label for="cliente_ci">Cédula de Identidad (CI)</label>
                            <input type="text" class="form-control" id="cliente_ci" name="cliente_ci" 
                                   value="<?= $isEdit ? htmlspecialchars($clienteData['cliente_ci'] ?? '') : '' ?>" 
                                   placeholder="Ingrese CI" maxlength="20"
                                   pattern="[0-9.]+"
                                   oninput="this.value = this.value.replace(/[^0-9.]/g,'');">
                            <small class="form-text text-muted">Solo para personas físicas</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="cliente_telefono">Teléfono <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="cliente_telefono" name="cliente_telefono" 
                                   value="<?= $isEdit ? htmlspecialchars($clienteData['cliente_telefono'] ?? '') : '' ?>" 
                                   placeholder="Ingrese teléfono" required maxlength="20"
                                   pattern="[0-9-()+ ]+"
                                   oninput="this.value = this.value.replace(/[^0-9-()+ ]/g,'');">
                            <small class="form-text text-muted">Formato: (021) 123-456</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="cliente_email">Correo Electrónico</label>
                            <input type="email" class="form-control" id="cliente_email" name="cliente_email" 
                                   value="<?= $isEdit ? htmlspecialchars($clienteData['cliente_email'] ?? '') : '' ?>" 
                                   placeholder="ejemplo@correo.com" maxlength="100">
                            <small class="form-text text-muted">Formato de email válido</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="cliente_direccion">Dirección</label>
                            <textarea class="form-control" id="cliente_direccion" name="cliente_direccion" 
                                      rows="3" placeholder="Ingrese dirección completa" maxlength="200"><?= $isEdit ? htmlspecialchars($clienteData['cliente_direccion'] ?? '') : '' ?></textarea>
                            <small class="form-text text-muted">Máximo 200 caracteres</small>
                        </div>
                    </div>
                </div>

                <?php if ($isEdit): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="cliente_estado">Estado</label>
                            <select name="cliente_estado" id="cliente_estado" class="form-control">
                                <option value="ACTIVO" <?= (($clienteData['cliente_estado'] ?? 'ACTIVO') === 'ACTIVO') ? 'selected' : '' ?>>ACTIVO</option>
                                <option value="INACTIVO" <?= (($clienteData['cliente_estado'] ?? '') === 'INACTIVO') ? 'selected' : '' ?>>INACTIVO</option>
                            </select>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-primary" name="Guardar">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                    <a href="view.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Función para actualizar campos según tipo de cliente
    function actualizarCampos() {
        var tipo = $('#tipo_cliente').val();
        if (tipo === 'EMPRESA') {
            $('#label_nombre').text('Razón Social *');
            $('#label_ruc').text('RUC *');
            $('#div_apellido').hide();
            $('#div_ci').hide();
            $('#cliente_apellido').val('');
            $('#cliente_ci').val('');
        } else {
            $('#label_nombre').text('Nombre *');
            $('#label_ruc').text('RUC *');
            $('#div_apellido').show();
            $('#div_ci').show();
        }
    }

    // Actualizar al cargar
    actualizarCampos();

    // Actualizar al cambiar tipo
    $('#tipo_cliente').on('change', function() {
        actualizarCampos();
    });

    // Validación de email
    $('#cliente_email').on('blur', function() {
        var email = $(this).val();
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            $(this).addClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
            $(this).after('<div class="invalid-feedback">Formato de email inválido</div>');
        } else {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        }
    });

    // Validación de RUC/CI
    $('#cliente_ruc').on('blur', function() {
        var ruc = $(this).val();
        if (ruc && ruc.length < 5) {
            $(this).addClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
            $(this).after('<div class="invalid-feedback">El RUC debe tener al menos 5 caracteres</div>');
        } else {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        }
    });
});
</script>

