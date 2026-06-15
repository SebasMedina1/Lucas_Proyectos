<?php
if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}

require "../../config/database.php";

// Incluir sistema de permisos y verificar acceso
require_once realpath("../../config/permissions.php");
check_permission('REFERENCIALES'); // Materia prima es un módulo referencial

$isEdit = isset($_GET['form_materia_prima']) && $_GET['form'] == 'edit' && isset($_GET['id']);
$materiaPrimaData = null;

if ($isEdit) {
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        $query = $pdo->prepare("
            SELECT mp.*,
                   (SELECT deposito_id FROM stock_materia_prima 
                    WHERE id_materia_prima = mp.id_materia_prima 
                    ORDER BY cantidad_existente DESC 
                    LIMIT 1) AS deposito_predeterminado
            FROM materia_prima mp
            WHERE mp.id_materia_prima = :id
        ");
        $query->execute([':id' => (int)$_GET['id']]);
        $materiaPrimaData = $query->fetch();
        
        if (!$materiaPrimaData) {
            echo "<script>alert('Materia prima no encontrada'); window.location.href='view.php';</script>";
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
        <h1 class="h3 mb-0 text-gray-800"><?= $isEdit ? 'Editar Materia Prima' : 'Nueva Materia Prima' ?></h1>
        <a href="view.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?= $isEdit ? 'Editar' : 'Registrar' ?> Materia Prima</h6>
        </div>
        <div class="card-body">
            <form action="proses.php?act=<?= $isEdit ? 'update' : 'insert' ?>" method="POST" id="formMateriaPrima">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id_materia_prima" value="<?= htmlspecialchars($materiaPrimaData['id_materia_prima']) ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="materia_prima_descripcion">Descripción <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="materia_prima_descripcion" name="materia_prima_descripcion" 
                                   value="<?= $isEdit ? htmlspecialchars($materiaPrimaData['materia_prima_descripcion'] ?? '') : '' ?>" 
                                   placeholder="Ingrese descripción de la materia prima" required maxlength="30">
                            <small class="form-text text-muted">Descripción de la materia prima (máximo 30 caracteres)</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="id_unidad">Unidad de Medida <span class="text-danger">*</span></label>
                            <select name="id_unidad" id="id_unidad" class="form-control" required>
                                <option value="">Seleccione una unidad de medida</option>
                                <?php
                                try {
                                    $query_um = $pdo->query("SELECT id_unidad, unidad_descri FROM unidad_medida ORDER BY unidad_descri ASC");
                                    while ($um = $query_um->fetch()) {
                                        $selected = ($isEdit && ($materiaPrimaData['id_unidad'] ?? null) == $um['id_unidad']) ? 'selected' : '';
                                        echo "<option value=\"{$um['id_unidad']}\" {$selected}>{$um['unidad_descri']}</option>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<option value=''>Error al cargar unidades</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="iva_id">Tipo de IVA <span class="text-danger">*</span></label>
                            <select name="iva_id" id="iva_id" class="form-control" required>
                                <option value="">Seleccione el tipo de IVA</option>
                                <?php
                                try {
                                    $query_iva = $pdo->query("SELECT iva_id, iva_descri FROM tipo_iva ORDER BY iva_id ASC");
                                    while ($iva = $query_iva->fetch()) {
                                        $selected = ($isEdit && ($materiaPrimaData['iva_id'] ?? null) == $iva['iva_id']) ? 'selected' : '';
                                        echo "<option value=\"{$iva['iva_id']}\" {$selected}>{$iva['iva_descri']}</option>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<option value=''>Error al cargar tipos de IVA</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="deposito_predeterminado_id">Depósito Predeterminado (Opcional)</label>
                            <select name="deposito_predeterminado_id" id="deposito_predeterminado_id" class="form-control">
                                <option value="">-- Opcional --</option>
                                <?php
                                try {
                                    $query_dep = $pdo->query("SELECT deposito_id, deposito_descri FROM deposito ORDER BY deposito_descri ASC");
                                    while ($dep = $query_dep->fetch()) {
                                        $selected = '';
                                        if ($isEdit) {
                                            $depositoId = $materiaPrimaData['deposito_predeterminado'] ?? null;
                                            $selected = ($depositoId == $dep['deposito_id']) ? 'selected' : '';
                                        }
                                        echo "<option value=\"{$dep['deposito_id']}\" {$selected}>{$dep['deposito_descri']}</option>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<option value=''>Error al cargar depósitos</option>";
                                }
                                ?>
                            </select>
                            <small class="form-text text-muted">Depósito donde se almacenará la materia prima por defecto (opcional)</small>
                        </div>
                    </div>

                    <?php if ($isEdit): ?>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="materia_prima_estado">Estado</label>
                            <select name="materia_prima_estado" id="materia_prima_estado" class="form-control">
                                <option value="ACTIVO" <?= (($materiaPrimaData['materia_prima_estado'] ?? 'ACTIVO') === 'ACTIVO') ? 'selected' : '' ?>>ACTIVO</option>
                                <option value="INACTIVO" <?= (($materiaPrimaData['materia_prima_estado'] ?? '') === 'INACTIVO') ? 'selected' : '' ?>>INACTIVO</option>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

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

