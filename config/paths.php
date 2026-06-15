<?php
/**
 * Configuración de rutas base del proyecto
 * Detecta automáticamente la ruta base desde cualquier ubicación
 */

// Detectar la ruta base del proyecto
function getBasePath() {
    // Obtener el directorio del script actual
    $currentDir = dirname($_SERVER['PHP_SELF']);
    
    // Si estamos en la raíz, retornar vacío o '/'
    if ($currentDir === '/' || $currentDir === '\\' || $currentDir === '') {
        return '';
    }
    
    // Contar niveles de profundidad desde la raíz
    $levels = substr_count($currentDir, '/');
    
    // Si estamos en un módulo (modules/xxx/), subir 2 niveles
    if (strpos($currentDir, '/modules/') !== false) {
        $levels = substr_count($currentDir, '/') - 2; // -2 porque modules/xxx/ son 2 niveles
    }
    
    // Construir la ruta relativa hacia la raíz
    if ($levels > 0) {
        return str_repeat('../', $levels);
    }
    
    return '';
}

// Función para obtener la ruta base absoluta (con / al inicio)
function getBaseUrl() {
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $scriptDir = dirname($scriptName);
    
    // Si el script está en la raíz del proyecto
    if ($scriptDir === '/' || $scriptDir === '\\') {
        return '';
    }
    
    // Extraer el nombre del proyecto de la ruta
    $parts = explode('/', trim($scriptDir, '/'));
    
    // Si estamos en modules/xxx/, el proyecto está 2 niveles arriba
    if (strpos($scriptDir, '/modules/') !== false) {
        $projectPath = '';
        $parts = explode('/', trim($scriptDir, '/'));
        $moduleIndex = array_search('modules', $parts);
        if ($moduleIndex !== false) {
            // Todo lo anterior a 'modules' es la ruta del proyecto
            $projectParts = array_slice($parts, 0, $moduleIndex);
            if (!empty($projectParts)) {
                $projectPath = '/' . implode('/', $projectParts);
            }
        }
        return $projectPath;
    }
    
    // Si estamos en la raíz, retornar el directorio del script
    return $scriptDir;
}

// Variable global para rutas relativas (sin / al inicio)
$BASE_PATH = getBasePath();

// Variable global para rutas absolutas (con / al inicio) - usar cuando sea necesario
$BASE_URL = getBaseUrl();

// Si BASE_URL está vacío, intentar detectar desde el nombre del proyecto
if (empty($BASE_URL)) {
    // Detectar desde el directorio actual
    $currentPath = $_SERVER['REQUEST_URI'];
    $scriptPath = $_SERVER['SCRIPT_NAME'];
    
    // Extraer el nombre del proyecto
    if (preg_match('#/([^/]+)/#', $scriptPath, $matches)) {
        $projectName = $matches[1];
        $BASE_URL = '/' . $projectName;
    } else {
        // Fallback: usar la ruta del script sin el nombre del archivo
        $BASE_URL = dirname($scriptPath);
        if ($BASE_URL === '/' || $BASE_URL === '\\') {
            $BASE_URL = '';
        }
    }
}

// Si aún está vacío, usar 'proyecto_taller' como fallback
if (empty($BASE_URL)) {
    $BASE_URL = '/proyecto_taller';
}

// Asegurar que BASE_URL siempre empiece con /
if (!empty($BASE_URL) && $BASE_URL[0] !== '/') {
    $BASE_URL = '/' . $BASE_URL;
}

?>

