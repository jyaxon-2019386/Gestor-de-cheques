<?php
// Constantes de conexión a la Base de Datos
define('DB_HOST', 'localhost:3307');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'solicitud_cheques'); 

// Crear la conexión
$conexion = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar si hubo un error en la conexión
if ($conexion->connect_error) {
    // Detener la ejecución y mostrar un error genérico y amigable
    die("Error de Conexión: No se pudo conectar a la base de datos. Por favor, contacte al administrador.");
}

// Asegurar que la conexión use el formato de caracteres UTF-8
$conexion->set_charset("utf8");
?>
