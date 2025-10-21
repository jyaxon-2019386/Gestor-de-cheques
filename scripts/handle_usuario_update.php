<?php
// Nombre del archivo: scripts/handle_usuario_update.php
require_once '../includes/functions.php';
proteger_pagina();

if (!es_admin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Acceso no autorizado.']);
    exit();
}

$conexion = require_once '../config/database.php';
header('Content-Type: application/json');

try {
    // --- 1. VALIDACIÓN DE DATOS ---
    $usuario_id = filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT);
    $rol = $_POST['rol'] ?? '';
    $jefe_id = filter_input(INPUT_POST, 'jefe_id', FILTER_VALIDATE_INT) ?: NULL; // Convertir '' a NULL
    $departamento_id = filter_input(INPUT_POST, 'departamento_id', FILTER_VALIDATE_INT) ?: NULL;
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$usuario_id) {
        throw new Exception("ID de usuario no válido.");
    }

    // --- 2. CONSTRUCCIÓN DE LA CONSULTA SQL ---
    $sql_parts = [];
    $param_types = "";
    $param_values = [];

    // Actualizar rol, jefe y departamento
    $sql_parts[] = "rol = ?"; $param_types .= "s"; $param_values[] = $rol;
    $sql_parts[] = "jefe_id = ?"; $param_types .= "i"; $param_values[] = $jefe_id;
    $sql_parts[] = "departamento_id = ?"; $param_types .= "i"; $param_values[] = $departamento_id;

    // Lógica para actualizar la contraseña (solo si se proporcionó)
    if (!empty($password)) {
        if (strlen($password) < 6) {
            throw new Exception("La nueva contraseña debe tener al menos 6 caracteres.");
        }
        if ($password !== $confirm_password) {
            throw new Exception("Las contraseñas no coinciden.");
        }
        
        // Hashear la nueva contraseña
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql_parts[] = "password = ?";
        $param_types .= "s";
        $param_values[] = $hashed_password;
    }

    // Unir todo en la consulta final
    $sql = "UPDATE usuarios SET " . implode(", ", $sql_parts) . " WHERE id = ?";
    $param_types .= "i";
    $param_values[] = $usuario_id;
    
    // --- 3. EJECUTAR LA ACTUALIZACIÓN ---
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param($param_types, ...$param_values);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Usuario actualizado correctamente.']);
    } else {
        throw new Exception("Error al actualizar el usuario: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conexion->close();
?>