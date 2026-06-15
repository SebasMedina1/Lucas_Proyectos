<?php
/**
 * Script de diagnóstico temporal para identificar errores
 * Eliminar después de solucionar el problema
 */

session_start();

if (empty($_SESSION['username'])) {
    die("No autenticado");
}

require_once realpath("../../config/database.php");
require_once realpath(__DIR__ . '/../../config/modulo_cargo_map.php');

header('Content-Type: text/plain; charset=utf-8');

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "=== DIAGNÓSTICO DE ERRORES ===\n\n";
    
    // 1. Verificar si existe la columna id_personal
    echo "1. Verificando columna id_personal:\n";
    $stmt = $pdo->query("
        SELECT column_name, data_type, is_nullable
        FROM information_schema.columns 
        WHERE table_schema = 'public' 
        AND table_name = 'usuarios' 
        AND column_name = 'id_personal'
    ");
    $col = $stmt->fetch();
    if ($col) {
        echo "   ✓ Columna existe: {$col['column_name']} ({$col['data_type']}, nullable: {$col['is_nullable']})\n";
    } else {
        echo "   ✗ Columna NO existe\n";
    }
    echo "\n";
    
    // 2. Verificar estructura completa de la tabla usuarios
    echo "2. Columnas de la tabla usuarios:\n";
    $stmt = $pdo->query("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns 
        WHERE table_schema = 'public' 
        AND table_name = 'usuarios'
        ORDER BY ordinal_position
    ");
    $cols = $stmt->fetchAll();
    foreach ($cols as $col) {
        echo "   - {$col['column_name']}: {$col['data_type']} (nullable: {$col['is_nullable']})\n";
    }
    echo "\n";
    
    // 3. Verificar módulos disponibles
    echo "3. Módulos disponibles:\n";
    $stmt = $pdo->query("SELECT modulo_id, modulo_descri FROM modulos ORDER BY modulo_id");
    $modulos = $stmt->fetchAll();
    foreach ($modulos as $mod) {
        echo "   - ID: {$mod['modulo_id']}, Descripción: {$mod['modulo_descri']}\n";
    }
    echo "\n";
    
    // 4. Verificar cargos disponibles
    echo "4. Cargos disponibles:\n";
    $stmt = $pdo->query("SELECT id_cargo, cargo_descripcion, estado_cargo FROM cargos ORDER BY id_cargo");
    $cargos = $stmt->fetchAll();
    foreach ($cargos as $cargo) {
        echo "   - ID: {$cargo['id_cargo']}, Descripción: {$cargo['cargo_descripcion']}, Estado: {$cargo['estado_cargo']}\n";
    }
    echo "\n";
    
    // 5. Probar función de validación
    echo "5. Probando validación de cargos por módulo:\n";
    foreach ($modulos as $mod) {
        $modId = (int)$mod['modulo_id'];
        $modDesc = $mod['modulo_descri'];
        echo "   Módulo: {$modDesc} (ID: {$modId})\n";
        $cargosPermitidos = obtenerCargosIdsPorModulo($pdo, $modId);
        if (empty($cargosPermitidos)) {
            echo "      - No hay cargos permitidos\n";
        } else {
            foreach ($cargosPermitidos as $cargoId) {
                $stmt = $pdo->prepare("SELECT cargo_descripcion FROM cargos WHERE id_cargo = ?");
                $stmt->execute([$cargoId]);
                $cargo = $stmt->fetch();
                echo "      - Cargo permitido: {$cargo['cargo_descripcion']} (ID: {$cargoId})\n";
            }
        }
    }
    echo "\n";
    
    // 6. Verificar datos POST simulados
    echo "6. Para probar, simular INSERT con datos de prueba:\n";
    echo "   (Esto es solo informativo, no ejecuta la inserción)\n";
    
    $testData = [
        'username' => 'test_user',
        'password' => 'test1234',
        'modulo_id' => 1,
        'id_sucursal' => 1,
        'id_cargo' => 1,
        'id_personal' => null
    ];
    
    foreach ($testData as $key => $value) {
        echo "   - {$key}: " . ($value ?? 'NULL') . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString();
}

