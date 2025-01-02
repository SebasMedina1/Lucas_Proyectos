<?php
session_start();

$response = array();

if (isset($_SESSION['message'])) {
    // Agregar los mensajes a la respuesta
    $response['message'] = $_SESSION['message'];
    $response['type'] = $_SESSION['message_type'];
    
    // Limpiar los mensajes de sesión para evitar que persistan entre recargas
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
} else {
    $response['message'] = ''; // No hay mensajes
    $response['type'] = '';
}

// Enviar la respuesta como JSON
echo json_encode($response);
?>
