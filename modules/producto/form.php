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
check_permission('REFERENCIALES'); // Productos es un módulo referencial

$isEdit = isset($_GET['form_producto']) && $_GET['form'] == 'edit' && isset($_GET['id']);
$productoData = null;

if ($isEdit) {
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        $query = $pdo->prepare("
            SELECT p.*,
                   (SELECT deposito_id FROM stock_producto 
                    WHERE producto_id = p.producto_id 
                    ORDER BY stock_prod_existente DESC 
                    LIMIT 1) AS deposito_predeterminado
            FROM productos p
            WHERE p.producto_id = :id
        ");
        $query->execute([':id' => (int)$_GET['id']]);
        $productoData = $query->fetch();
        
        if (!$productoData) {
            echo "<script>alert('Producto no encontrado'); window.location.href='view.php';</script>";
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
        <h1 class="h3 mb-0 text-gray-800"><?= $isEdit ? 'Editar Producto' : 'Nuevo Producto' ?></h1>
        <a href="view.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?= $isEdit ? 'Editar' : 'Registrar' ?> Producto</h6>
        </div>
        <div class="card-body">
            <form action="proses.php?act=<?= $isEdit ? 'update' : 'insert' ?>" method="POST" id="formProducto">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="producto_id" value="<?= htmlspecialchars($productoData['producto_id']) ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="producto_descri">Descripción del Producto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="producto_descri" name="producto_descri" 
                                   value="<?= $isEdit ? htmlspecialchars($productoData['producto_descri']) : '' ?>" 
                                   placeholder="Ingrese descripción del producto" required maxlength="30">
                            <small class="form-text text-muted">Máximo 30 caracteres</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="producto_precio">Precio de Referencia <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="producto_precio" name="producto_precio" 
                                   value="<?= $isEdit ? number_format($productoData['producto_precio'], 0, '', '') : '' ?>" 
                                   placeholder="Ingrese el precio del producto" required
                                   inputmode="numeric" pattern="^[0-9]+$"
                                   oninput="this.value = this.value.replace(/[^0-9]/g,''); this.setCustomValidity('');"
                                   oninvalid="this.setCustomValidity('Solo números enteros positivos')">
                            <small class="form-text text-muted">Precio en guaraníes (solo números)</small>
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
                                        $selected = ($isEdit && $productoData['id_unidad'] == $um['id_unidad']) ? 'selected' : '';
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
                                        $selected = ($isEdit && $productoData['iva_id'] == $iva['iva_id']) ? 'selected' : '';
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
                            <label for="id_tipo_producto">Tipo de Producto (Opcional)</label>
                            <select name="id_tipo_producto" id="id_tipo_producto" class="form-control">
                                <option value="">Seleccione el tipo de producto</option>
                                <?php
                                try {
                                    // Verificar si existe la tabla tipo_producto y qué columnas tiene
                                    $checkTable = $pdo->query("
                                        SELECT column_name 
                                        FROM information_schema.columns 
                                        WHERE table_name = 'tipo_producto' 
                                        LIMIT 1
                                    ");
                                    if ($checkTable->rowCount() > 0) {
                                        // Intentar obtener columnas
                                        $cols = $pdo->query("
                                            SELECT column_name 
                                            FROM information_schema.columns 
                                            WHERE table_name = 'tipo_producto'
                                        ")->fetchAll(PDO::FETCH_COLUMN);
                                        
                                        if (in_array('cod_tipo_prod', $cols) && in_array('t_p_descrip', $cols)) {
                                            $query_tipo = $pdo->query("SELECT cod_tipo_prod, t_p_descrip FROM tipo_producto ORDER BY t_p_descrip ASC");
                                            while ($tipo = $query_tipo->fetch()) {
                                                $selected = ($isEdit && isset($productoData['id_tipo_producto']) && $productoData['id_tipo_producto'] == $tipo['cod_tipo_prod']) ? 'selected' : '';
                                                echo "<option value=\"{$tipo['cod_tipo_prod']}\" {$selected}>{$tipo['t_p_descrip']}</option>";
                                            }
                                        }
                                    }
                                } catch (PDOException $e) {
                                    // Silenciar error si la tabla no existe
                                }
                                ?>
                            </select>
                            <small class="form-text text-muted">Clasificación del producto (opcional)</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="deposito_predeterminado_id">Depósito Predeterminado</label>
                            <select name="deposito_predeterminado_id" id="deposito_predeterminado_id" class="form-control">
                                <option value="">-- Opcional --</option>
                                <option value="">Seleccione el depósito</option>
                                <?php
                                try {
                                    $query_dep = $pdo->query("SELECT deposito_id, deposito_descri FROM deposito ORDER BY deposito_descri ASC");
                                    while ($dep = $query_dep->fetch()) {
                                        $selected = '';
                                        if ($isEdit) {
                                            // Obtener desde stock_producto
                                            $depositoId = $productoData['deposito_predeterminado'] ?? null;
                                            $selected = ($depositoId == $dep['deposito_id']) ? 'selected' : '';
                                        }
                                        echo "<option value=\"{$dep['deposito_id']}\" {$selected}>{$dep['deposito_descri']}</option>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<option value=''>Error al cargar depósitos</option>";
                                }
                                ?>
                            </select>
                            <small class="form-text text-muted">Depósito donde se almacenará el producto por defecto (opcional)</small>
                        </div>
                    </div>

                    <?php if ($isEdit): ?>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="producto_estado">Estado</label>
                            <select name="producto_estado" id="producto_estado" class="form-control">
                                <option value="ACTIVO" <?= ($productoData['producto_estado'] === 'ACTIVO') ? 'selected' : '' ?>>ACTIVO</option>
                                <option value="INACTIVO" <?= ($productoData['producto_estado'] === 'INACTIVO') ? 'selected' : '' ?>>INACTIVO</option>
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
