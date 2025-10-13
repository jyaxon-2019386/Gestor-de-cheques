<?php
// scripts/handle_register.php - VERSIÓN FINAL CON MANEJO DE EXCEPCIONES
require_once '../config/database.php';
require_once '../includes/functions.php';

// Establecer mysqli para que lance excepciones en lugar de warnings
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$nombre_usuario = $_POST['nombre_usuario'] ?? null;
$email = $_POST['email'] ?? null;
$departamento_id = $_POST['departamento_id'] ?? null;
$password = $_POST['password'] ?? null;
$confirm_password = $_POST['confirm_password'] ?? null;

$status = 'error';
$message = 'Ocurrió un error inesperado.';
$has_error = false;

// Validaciones del servidor
if (empty($nombre_usuario) || empty($email) || empty($password) || empty($confirm_password)) {
    $message = "Por favor, completa todos los campos obligatorios.";
    $has_error = true;
} 
elseif (preg_match('/\s/', $nombre_usuario)) {
    $message = "El nombre de usuario no puede contener espacios.";
    $has_error = true;
}
elseif (strlen($password) < 6) {
    $message = "La contraseña debe tener al menos 6 caracteres.";
    $has_error = true;
} 
elseif ($password !== $confirm_password) {
    $message = "Las contraseñas no coinciden.";
    $has_error = true;
} else {
    try {
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conexion->prepare("INSERT INTO usuarios (nombre_usuario, email, departamento_id, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $nombre_usuario, $email, $departamento_id, $password_hashed);
        $stmt->execute();
        
        $status = 'success';
        $message = 'Usuario creado exitosamente.';
        
    } catch (mysqli_sql_exception $e) {
        $has_error = true;
        // --- LA CORRECCIÓN CLAVE: ATRAPAR LA EXCEPCIÓN DE DUPLICADO ---
        if ($e->getCode() == 1062) { // 1062 es el código de error para "Duplicate entry"
             $message = 'El nombre de usuario o el correo electrónico ya están registrados.';
        } else {
            // Para cualquier otro error de base de datos
            $message = 'Error al crear el usuario en la base de datos.';
            // Opcional: registrar el error real para depuración
            // error_log($e->getMessage());
        }
    }
}

// --- LÓGICA DE RESPUESTA INTELIGENTE (SIN CAMBIOS) ---
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($is_ajax) {
    // ... (respuesta JSON)
} else {
    if ($has_error) {
        $_SESSION['error_message'] = $message;
        $_SESSION['form_data'] = $_POST;
        unset($_SESSION['form_data']['password'], $_SESSION['form_data']['confirm_password']);
        header('Location: ../register.php');
    } else {
        unset($_SESSION['error_message'], $_SESSION['form_data']);
        header('Location: ../login.php?status=registered');
    }
}
exit();
?>