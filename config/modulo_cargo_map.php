<?php
/**
 * Mapeo de Módulos a Cargos Permitidos
 * 
 * Define qué cargos están permitidos para cada módulo del sistema.
 * Este mapeo se usa para validar y filtrar cargos en formularios de usuario.
 */

/**
 * Obtiene los cargos permitidos para un módulo específico
 * 
 * @param string $moduloDescripcion Descripción del módulo (ej: 'ADMIN', 'COMPRAS', 'VENTAS')
 * @return array Array con las descripciones de cargos permitidos (en mayúsculas)
 */
function obtenerCargosPorModulo(string $moduloDescripcion): array {
    $moduloDescripcion = strtoupper(trim($moduloDescripcion));
    
    $mapeo = [
        'ADMIN' => [
            'ADMIN'
        ],
        'COMPRAS' => [
            'JEFE DE COMPRAS',
            'ENCARGADO DE COMPRAS'
        ],
        'PRODUCCION' => [
            'JEFE DE PRODUCCION',
            'ENCARGADO DE PRODUCCION'
        ],
        'VENTAS' => [
            'JEFE DE VENTAS',
            'ENCARGADO DE VENTAS'
        ],
    ];
    
    return $mapeo[$moduloDescripcion] ?? [];
}

/**
 * Obtiene los IDs de cargos permitidos para un módulo
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param int $moduloId ID del módulo
 * @return array Array con los IDs de cargos permitidos
 */
function obtenerCargosIdsPorModulo(PDO $pdo, int $moduloId): array {
    if ($moduloId <= 0) {
        return [];
    }
    
    try {
        // Obtener descripción del módulo
        $stmtModulo = $pdo->prepare("
            SELECT UPPER(TRIM(modulo_descri)) AS modulo_descri
            FROM modulos
            WHERE modulo_id = :modulo_id
            LIMIT 1
        ");
        $stmtModulo->execute([':modulo_id' => $moduloId]);
        $modulo = $stmtModulo->fetch(PDO::FETCH_ASSOC);
        
        if (!$modulo) {
            return [];
        }
        
        $moduloDescripcion = trim($modulo['modulo_descri']);
        $cargosPermitidos = obtenerCargosPorModulo($moduloDescripcion);
        
        // ADMIN solo está permitido si el módulo es ADMIN
        // No agregar ADMIN a otros módulos
        
        if (empty($cargosPermitidos)) {
            return [];
        }
        
        // Construir la consulta para obtener los IDs
        $placeholders = [];
        $params = [];
        foreach ($cargosPermitidos as $index => $cargoDesc) {
            $key = ':cargo' . $index;
            $placeholders[] = $key;
            $params[$key] = $cargoDesc;
        }
        
        $sql = "
            SELECT id_cargo
            FROM cargos
            WHERE UPPER(TRIM(cargo_descripcion)) IN (" . implode(',', $placeholders) . ")
            AND estado_cargo = 'ACTIVO'
            ORDER BY cargo_descripcion ASC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $cargos = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return array_map('intval', $cargos);
        
    } catch (PDOException $e) {
        error_log("Error al obtener cargos por módulo: " . $e->getMessage());
        return [];
    }
}

/**
 * Valida si un cargo está permitido para un módulo
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param int $moduloId ID del módulo
 * @param int $cargoId ID del cargo
 * @return bool True si el cargo está permitido para el módulo
 */
function validarCargoParaModulo(PDO $pdo, int $moduloId, int $cargoId): bool {
    if ($moduloId <= 0 || $cargoId <= 0) {
        return false;
    }
    
    // Obtener la descripción del módulo y del cargo para validar
    try {
        // Obtener módulo
        $stmtModulo = $pdo->prepare("
            SELECT UPPER(TRIM(modulo_descri)) AS modulo_descri
            FROM modulos
            WHERE modulo_id = :modulo_id
            LIMIT 1
        ");
        $stmtModulo->execute([':modulo_id' => $moduloId]);
        $modulo = $stmtModulo->fetch(PDO::FETCH_ASSOC);
        
        if (!$modulo) {
            return false;
        }
        
        // Obtener cargo
        $stmtCargo = $pdo->prepare("
            SELECT UPPER(TRIM(cargo_descripcion)) AS cargo_descripcion
            FROM cargos
            WHERE id_cargo = :cargo_id
            AND estado_cargo = 'ACTIVO'
            LIMIT 1
        ");
        $stmtCargo->execute([':cargo_id' => $cargoId]);
        $cargo = $stmtCargo->fetch(PDO::FETCH_ASSOC);
        
        if (!$cargo) {
            return false;
        }
        
        $moduloDesc = strtoupper(trim($modulo['modulo_descri']));
        $cargoDesc = strtoupper(trim($cargo['cargo_descripcion']));
        
        // ADMIN solo está permitido si el módulo es ADMIN
        if ($cargoDesc === 'ADMIN') {
            return $moduloDesc === 'ADMIN';
        }
        
    } catch (PDOException $e) {
        error_log("Error al validar cargo: " . $e->getMessage());
        return false;
    }
    
    $cargosPermitidos = obtenerCargosIdsPorModulo($pdo, $moduloId);
    
    return in_array($cargoId, $cargosPermitidos, true);
}

