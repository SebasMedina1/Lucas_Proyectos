<?php

// Configuración de la base de datos
$host = 'localhost';
$port = '5432'; // Puerto predeterminado de PostgreSQL
$user = 'postgres'; // Cambia este valor por el usuario de PostgreSQL
$pass = 'lucas456'; // Cambia este valor por la contraseña de PostgreSQL
$database = 'taller_cuarto';

try {
    // Crear la conexión usando PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database";
    $pdo = new PDO($dsn, $user, $pass);

    // Configurar PDO para manejar excepciones en caso de error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    //echo "CONECTADO CORRECTAMENTE A LA BD";
} catch (PDOException $e) {
    // Capturar errores y mostrar un mensaje
    die("Error en la conexión: " . $e->getMessage());
}

?>


