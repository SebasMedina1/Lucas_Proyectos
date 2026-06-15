<?php
/**
 * Sistema de Control de Acceso por Cargo (Rol)
 * 
 * Este archivo centraliza toda la lógica de permisos del sistema.
 * Define los cargos, sus permisos y la función de validación.
 */

// ============================================
// CONSTANTES DE CARGOS (ROLES)
// ============================================

define('ROL_ADMIN', 'ADMIN');
define('ROL_JEFE_COMPRAS', 'JEFE_COMPRAS');
define('ROL_JEFE_PRODUCCION', 'JEFE_PRODUCCION');
define('ROL_ENC_COMPRAS', 'ENC_COMPRAS');
define('ROL_ENC_PRODUCCION', 'ENC_PRODUCCION');

/** @deprecated Alcance tesis: sin módulo de ventas */
define('ROL_JEFE_VENTAS', 'JEFE_VENTAS');
/** @deprecated Alcance tesis: sin módulo de ventas */
define('ROL_ENC_VENTAS', 'ENC_VENTAS');

/** Permisos asociados al módulo de ventas (legacy, deshabilitado en UI) */
define('PERMISOS_MODULO_VENTAS', [
    'VENTAS', 'COBRANZAS', 'REPORTES_VENTAS',
    'PEDIDO_VENTAS', 'PRESUPUESTO_VENTAS', 'FACTURA_VENTAS',
    'NOTAS_VENTAS', 'LIBRO_VENTAS', 'APERTURA_CIERRE_CAJA',
]);

// ============================================
// MAPEO ID_CARGO → NOMBRE DE CARGO
// ============================================

/**
 * Obtiene el nombre del cargo desde la base de datos según id_cargo
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param int $idCargo ID del cargo
 * @return string Nombre del cargo normalizado (ADMIN, JEFE_COMPRAS, etc.)
 */
function obtenerNombreCargo(PDO $pdo, int $idCargo): string {
    if ($idCargo <= 0) {
        return '';
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT UPPER(TRIM(cargo_descripcion)) AS cargo_descripcion
            FROM cargos
            WHERE id_cargo = :id_cargo
            AND estado_cargo = 'ACTIVO'
            LIMIT 1
        ");
        $stmt->execute([':id_cargo' => $idCargo]);
        $cargo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cargo) {
            return '';
        }
        
        $descripcion = trim($cargo['cargo_descripcion']);
        
        // Mapear descripciones a constantes normalizadas
        $descripcionUpper = strtoupper($descripcion);
        
        if (stripos($descripcionUpper, 'ADMIN') !== false) {
            return ROL_ADMIN;
        }
        if (stripos($descripcionUpper, 'JEFE') !== false && stripos($descripcionUpper, 'COMPRAS') !== false) {
            return ROL_JEFE_COMPRAS;
        }
        if (stripos($descripcionUpper, 'JEFE') !== false && stripos($descripcionUpper, 'PRODUCCION') !== false) {
            return ROL_JEFE_PRODUCCION;
        }
        if (stripos($descripcionUpper, 'ENCARGADO') !== false && stripos($descripcionUpper, 'COMPRAS') !== false) {
            return ROL_ENC_COMPRAS;
        }
        if (stripos($descripcionUpper, 'ENCARGADO') !== false && stripos($descripcionUpper, 'PRODUCCION') !== false) {
            return ROL_ENC_PRODUCCION;
        }
        
        return '';
    } catch (PDOException $e) {
        error_log("Error al obtener nombre de cargo: " . $e->getMessage());
        return '';
    }
}

/**
 * Obtiene los permisos permitidos para un cargo
 * 
 * @param string $cargo Nombre del cargo normalizado (ROL_ADMIN, etc.)
 * @return array Array de módulos permitidos
 */
function moduloVentasHabilitado(): bool {
    static $habilitado = null;
    if ($habilitado === null) {
        require_once __DIR__ . '/app_modules.php';
        $habilitado = defined('UI_MODULO_VENTAS') && UI_MODULO_VENTAS;
    }
    return $habilitado;
}

function obtenerPermisosPorCargo(string $cargo): array {
    $permisos = [
        ROL_ADMIN => [
            'COMPRAS', 'PRODUCCION', 'REFERENCIALES', 'INVENTARIO',
            'REPORTES_COMPRAS', 'REPORTES_PRODUCCION', 'REPORTES_GENERALES',
            'ADMINISTRACION',
            'PEDIDO_COMPRAS', 'PRESUPUESTO_COMPRAS', 'ORDEN_COMPRA',
            'FACTURA_COMPRAS', 'NOTAS_COMPRAS', 'LIBRO_COMPRAS',
            'PEDIDO_PRODUCCION', 'ORDEN_PRODUCCION', 'CONTROL_PRODUCCION',
            'CONTROL_CALIDAD', 'PRODUCTOS_TERMINADOS', 'PERDIDAS',
            'PEDIDO_MATERIA_PRIMA', 'REPOSICION_MATERIA', 'COSTOS_PRODUCCION',
            'ETAPAS_PRODUCCION', 'EQUIPOS_PRODUCCION',
        ],

        ROL_JEFE_COMPRAS => [
            'COMPRAS',
            'REPORTES_COMPRAS',
            'PEDIDO_COMPRAS', 'PRESUPUESTO_COMPRAS', 'ORDEN_COMPRA',
            'FACTURA_COMPRAS', 'NOTAS_COMPRAS', 'LIBRO_COMPRAS',
        ],

        ROL_JEFE_PRODUCCION => [
            'PRODUCCION',
            'REPORTES_PRODUCCION',
            'REFERENCIALES',
            'PEDIDO_PRODUCCION', 'ORDEN_PRODUCCION', 'CONTROL_PRODUCCION',
            'CONTROL_CALIDAD', 'PRODUCTOS_TERMINADOS', 'PERDIDAS',
            'PEDIDO_MATERIA_PRIMA', 'REPOSICION_MATERIA', 'COSTOS_PRODUCCION',
            'ETAPAS_PRODUCCION', 'EQUIPOS_PRODUCCION',
        ],

        ROL_ENC_COMPRAS => [
            'PEDIDO_COMPRAS', 'PRESUPUESTO_COMPRAS', 'ORDEN_COMPRA',
            'FACTURA_COMPRAS', 'NOTAS_COMPRAS',
        ],

        ROL_ENC_PRODUCCION => [
            'PEDIDO_PRODUCCION', 'ORDEN_PRODUCCION', 'CONTROL_PRODUCCION',
            'CONTROL_CALIDAD', 'PRODUCTOS_TERMINADOS', 'PERDIDAS',
            'PEDIDO_MATERIA_PRIMA', 'REPOSICION_MATERIA', 'COSTOS_PRODUCCION',
            'ETAPAS_PRODUCCION', 'EQUIPOS_PRODUCCION',
        ],
    ];

    $lista = $permisos[$cargo] ?? [];

    if (moduloVentasHabilitado()) {
        $legacyVentas = [
            ROL_ADMIN => [
                'VENTAS', 'COBRANZAS', 'REPORTES_VENTAS',
                'PEDIDO_VENTAS', 'PRESUPUESTO_VENTAS', 'FACTURA_VENTAS',
                'NOTAS_VENTAS', 'LIBRO_VENTAS', 'APERTURA_CIERRE_CAJA',
            ],
            ROL_JEFE_VENTAS => [
                'VENTAS', 'COBRANZAS', 'REPORTES_VENTAS',
                'PEDIDO_VENTAS', 'PRESUPUESTO_VENTAS', 'FACTURA_VENTAS',
                'NOTAS_VENTAS', 'LIBRO_VENTAS', 'APERTURA_CIERRE_CAJA',
            ],
            ROL_ENC_VENTAS => [
                'PEDIDO_VENTAS', 'PRESUPUESTO_VENTAS', 'FACTURA_VENTAS',
                'NOTAS_VENTAS', 'COBRANZAS',
            ],
        ];
        if (isset($legacyVentas[$cargo])) {
            $lista = array_values(array_unique(array_merge($lista, $legacyVentas[$cargo])));
        }
    }

    return $lista;
}

/**
 * Verifica si el usuario actual tiene permiso para acceder a un módulo
 * 
 * @param string|array $requiredModules Módulo o array de módulos requeridos
 * @param bool $redirect Si es true, redirige automáticamente en caso de acceso denegado
 * @return bool true si tiene permiso, false si no
 */
function check_permission($requiredModules, bool $redirect = true): bool {
    // Validar sesión
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    if (empty($_SESSION['username'])) {
        if ($redirect) {
            $redirectPath = (strpos($_SERVER['PHP_SELF'], '/modules/') !== false) ? '../../login.html' : 'login.html';
            header("Location: $redirectPath");
            exit;
        }
        return false;
    }
    
    // Si el cargo es ADMIN, tiene acceso a todo
    $cargoNombre = $_SESSION['cargo_nombre'] ?? '';
    if ($cargoNombre === ROL_ADMIN) {
        return true;
    }
    
    // Si no hay cargo, denegar acceso
    if (empty($cargoNombre)) {
        if ($redirect) {
            accessDenied('No se pudo determinar el cargo del usuario.');
        }
        return false;
    }
    
    // Normalizar módulos requeridos a array
    $modules = is_array($requiredModules) ? $requiredModules : [$requiredModules];

    if (!moduloVentasHabilitado()) {
        foreach ($modules as $module) {
            if (in_array(strtoupper((string) $module), PERMISOS_MODULO_VENTAS, true)) {
                if ($redirect) {
                    registrarIntentoAccesoNoAutorizado($cargoNombre, $modules);
                    accessDenied('El módulo de ventas no está habilitado en esta versión del sistema.');
                }
                return false;
            }
        }
    }
    
    // Obtener permisos del cargo
    $permisos = obtenerPermisosPorCargo($cargoNombre);
    
    // Verificar si al menos uno de los módulos requeridos está permitido
    $tieneAcceso = false;
    foreach ($modules as $module) {
        if (in_array(strtoupper($module), $permisos, true)) {
            $tieneAcceso = true;
            break;
        }
    }
    
    if (!$tieneAcceso && $redirect) {
        // Registrar intento de acceso no autorizado en bitácora
        registrarIntentoAccesoNoAutorizado($cargoNombre, $modules);
        accessDenied('No tiene permisos para acceder a esta opción.');
    }
    
    return $tieneAcceso;
}

/**
 * Registra en bitácora un intento de acceso no autorizado
 */
function registrarIntentoAccesoNoAutorizado(string $cargo, array $modulosIntentados): void {
    if (empty($_SESSION['id_usuario'])) {
        return;
    }
    
    try {
        require_once __DIR__ . '/database.php';
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        $modulosStr = implode(', ', $modulosIntentados);
        $descripcion = "Intento de acceso no autorizado. Cargo: {$cargo}. Módulos intentados: {$modulosStr}";
        
        $stmt = $pdo->prepare("
            INSERT INTO bitacora (id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio)
            VALUES (:id_usuario, :entidad, NULL, 'ACCESO_DENEGADO', :descripcion)
        ");
        $stmt->execute([
            ':id_usuario' => (int)$_SESSION['id_usuario'],
            ':entidad' => 'Control de Acceso',
            ':descripcion' => $descripcion
        ]);
    } catch (Throwable $e) {
        error_log("Error al registrar intento de acceso no autorizado: " . $e->getMessage());
    }
}

/**
 * Muestra mensaje de acceso denegado y redirige
 */
function accessDenied(string $mensajeAdicional = ''): void {
    $mensaje = "Acceso denegado: su cargo no tiene permisos para acceder a esta opción.";
    if (!empty($mensajeAdicional)) {
        $mensaje .= " " . $mensajeAdicional;
    }
    $mensaje .= " Comuníquese con el administrador del sistema.";
    
    // Detectar ruta base para redirección
    $basePath = (strpos($_SERVER['PHP_SELF'], '/modules/') !== false) ? '../../' : '';
    $redirectPath = $basePath . 'index.php';
    
    // Usar mensaje estructurado si existe sistema de alertas
    if (strpos($_SERVER['PHP_SELF'], '/modules/') !== false) {
        header("Location: {$redirectPath}?alert=denied&msg=" . urlencode($mensaje));
    } else {
        echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Acceso Denegado</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
    <div class='container mt-5'>
        <div class='row justify-content-center'>
            <div class='col-md-6'>
                <div class='alert alert-danger' role='alert'>
                    <h4 class='alert-heading'>Acceso Denegado</h4>
                    <p>" . htmlspecialchars($mensaje) . "</p>
                    <hr>
                    <p class='mb-0'><a href='{$redirectPath}' class='alert-link'>Volver al inicio</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>";
    }
    exit;
}

/**
 * Verifica si el usuario tiene acceso a un módulo específico (sin redirección)
 * Útil para mostrar/ocultar elementos en la interfaz
 */
function has_permission(string $module): bool {
    return check_permission($module, false);
}

