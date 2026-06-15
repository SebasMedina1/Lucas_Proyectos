<?php
// Archivo temporal para ver los logs de debug
// Eliminar este archivo después de resolver el problema

header('Content-Type: text/plain; charset=utf-8');

$log_file = __DIR__ . '/debug_presupuesto.log';

if (file_exists($log_file)) {
    echo "=== ÚLTIMOS LOGS DE DEBUG ===\n\n";
    $lines = file($log_file);
    // Mostrar las últimas 100 líneas
    $lines = array_slice($lines, -100);
    echo implode('', $lines);
} else {
    echo "No hay logs disponibles aún. Intenta crear/editar un presupuesto primero.\n";
    echo "El archivo de log se creará en: " . $log_file;
}
?>

