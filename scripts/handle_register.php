<?php
// scripts/handle_register.php - VERSIÓN CON NOMBRES Y APELLIDOS
require_once '../config/database.php';
require_once '../includes/functions.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- INICIO DE LA MODIFICACIÓN: Capturar nuevos campos ---
$nombres = $_POST['nombres'] ?? null;
$apellidos = $_POST['apellidos'] ?? null;
// --- FIN DE LA MODIFICACIÓN ---

$nombre_usuario = $_POST['nombre_usuario'] ?? null;
$email = $_POST['email'] ?? null;
$departamento_id = $_POST['departamento_id'] ?? null;
$password = $_POST['password'] ?? null;
$confirm_password = $_POST['confirm_password'] ?? null;

$status = 'error';
$message = 'Ocurrió un error inesperado.';
$has_error = false;

// --- INICIO DE LA MODIFICACIÓN: Actualizar validaciones ---
if (empty($nombres) || empty($apellidos) || empty($nombre_usuario) || empty($email) || empty($password) || empty($confirm_password)) {
    $message = "Por favor, completa todos los campos obligatorios.";
    $has_error = true;
} 
// --- FIN DE LA MODIFICACIÓN ---
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

        // --- INICIO DE LA MODIFICACIÓN: Combinar nombres y actualizar consulta ---
        $nombre_completo = trim($nombres) . ' ' . trim($apellidos);

        // Se agrega la nueva columna 'nombres' a la consulta
        $stmt = $conexion->prepare("INSERT INTO usuarios (nombres, nombre_usuario, email, departamento_id, password) VALUES (?, ?, ?, ?, ?)");
        
        // Se actualiza el bind_param: se añade una 's' al principio y la variable $nombre_completo
        $stmt->bind_param("sssis", $nombre_completo, $nombre_usuario, $email, $departamento_id, $password_hashed);
        // --- FIN DE LA MODIFICACIÓN ---

        $stmt->execute();
        
        $status = 'success';
        $message = 'Usuario creado exitosamente.';
        
    } catch (mysqli_sql_exception $e) {
        $has_error = true;
        if ($e->getCode() == 1062) {
             $message = 'El nombre de usuario o el correo electrónico ya están registrados.';
        } else {
            $message = 'Error al crear el usuario en la base de datos.';
            // error_log($e->getMessage()); // Descomentar para depuración
        }
    }
}

// --- LÓGICA DE RESPUESTA  (SIN CAMBIOS) ---
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