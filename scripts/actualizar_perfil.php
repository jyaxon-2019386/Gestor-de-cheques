<?php
// scripts/actualizar_perfil.php
require_once '../config/database.php';
require_once '../includes/functions.php';

proteger_pagina();
$response = ['status' => 'error', 'message' => 'Petición no válida.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['usuario_id'];
    $email = $_POST['email'] ?? '';
    
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    try {
        // Obtener datos actuales del usuario para comparar
        $stmt_user = $conexion->prepare("SELECT email, password FROM usuarios WHERE id = ?");
        $stmt_user->bind_param("i", $usuario_id);
        $stmt_user->execute();
        $usuario_actual = $stmt_user->get_result()->fetch_assoc();
        $stmt_user->close();

        // Lógica para actualizar el email (si ha cambiado)
        if (!empty($email) && $email !== $usuario_actual['email']) {
            $stmt_email = $conexion->prepare("UPDATE usuarios SET email = ? WHERE id = ?");
            $stmt_email->bind_param("si", $email, $usuario_id);
            $stmt_email->execute();
            $stmt_email->close();
            $_SESSION['email'] = $email; // Actualizar la sesión
        }

        // Lógica para actualizar la contraseña (solo si se llenaron los campos)
        if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception("Para cambiar la contraseña, debes rellenar los tres campos de contraseña.");
            }
            if (!password_verify($current_password, $usuario_actual['password'])) {
                throw new Exception("La contraseña actual es incorrecta.");
            }
            if (strlen($new_password) < 6) {
                throw new Exception("La nueva contraseña debe tener al menos 6 caracteres.");
            }
            if ($new_password !== $confirm_password) {
                throw new Exception("La nueva contraseña y su confirmación no coinciden.");
            }

            // Si todas las validaciones pasan, actualizamos la contraseña
            $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_pass = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmt_pass->bind_param("si", $new_password_hashed, $usuario_id);
            $stmt_pass->execute();
            $stmt_pass->close();
        }

        $response['status'] = 'success';
        $response['message'] = 'Perfil actualizado exitosamente.';

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit();
