<?php
// scripts/handle_login.php - VERSIÓN CORREGIDA
require_once '../config/database.php';
require_once '../includes/functions.php'; // Esto ya inicia la sesión

$nombre_usuario = $_POST['nombre_usuario'];
$password = $_POST['password'];

// Preparamos la consulta para obtener todos los datos necesarios del usuario
$stmt = $conexion->prepare("SELECT id, nombre_usuario, password, rol, email FROM usuarios WHERE nombre_usuario = ?");
$stmt->bind_param("s", $nombre_usuario);
$stmt->execute();
$resultado = $stmt->get_result();

if ($usuario = $resultado->fetch_assoc()) {
    // Verificar que la contraseña coincida con el hash almacenado
    if (password_verify($password, $usuario['password'])) {
        // ¡Credenciales correctas! Iniciar la sesión del usuario.
        
        // Regenerar el ID de sesión por seguridad
        session_regenerate_id(true);

        // Guardar TODOS los datos necesarios en la sesión
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['nombre_usuario'] = $usuario['nombre_usuario'];
        $_SESSION['rol'] = $usuario['rol']; // <-- ¡LA LÍNEA CRÍTICA QUE FALTABA!
        $_SESSION['email'] = $usuario['email']; // <-- AÑADE ESTA LÍNEA
        
        // Redirigir al dashboard principal
        header('Location: ../index.php');
        exit();
    }
}

// Si el código llega hasta aquí, significa que el usuario o la contraseña son incorrectos.
header('Location: ../login.php?error=1');
$stmt->close();
$conexion->close();
exit();