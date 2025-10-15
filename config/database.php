<?php
// Archivo de conexión: ../includes/db.php

// Constantes de conexión
define('DB_HOST', 'localhost:3306');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'solicitud_cheques');
define('DB_CHARSET', 'utf8mb4');

// Opciones de configuración para PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Activa los errores como excepciones
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devuelve los resultados como arrays asociativos
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Usa preparaciones de sentencias reales
];

// DSN (Data Source Name)
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

try {
    // Crear la instancia de PDO
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // En un entorno de producción, nunca muestres el error detallado.
    // En su lugar, regístralo en un archivo de log y muestra un mensaje genérico.
    // Por ahora, para desarrollo, podemos mostrar el error.
    die("Error de Conexión: No se pudo conectar a la base de datos. Error: " . $e->getMessage());
}

// ESTA LÍNEA ES LA MÁS IMPORTANTE PARA LA SOLUCIÓN
// Devuelve el objeto de conexión para que pueda ser usado en otros scripts.
return $pdo;