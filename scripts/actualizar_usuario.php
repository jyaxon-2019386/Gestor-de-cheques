<?php
// scripts/actualizar_usuario.php
require_once '../config/database.php';
require_once '../includes/functions.php';

proteger_pagina();
$response = ['status' => 'error', 'message' => 'Acción no permitida.'];

// Solo los admins pueden actualizar usuarios
if ($_SERVER['REQUEST_METHOD'] === 'POST' && es_admin()) {
    $usuario_id = $_POST['usuario_id'] ?? null;
    $rol = $_POST['rol'] ?? null;
    $jefe_id = !empty($_POST['jefe_id']) ? intval($_POST['jefe_id']) : null;

    if ($usuario_id && $rol) {
        $sql = "UPDATE usuarios SET rol = ?, jefe_id = ? WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("sii", $rol, $jefe_id, $usuario_id);

        if ($stmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'Usuario actualizado correctamente.';
        } else {
            $response['message'] = 'Error al actualizar la base de datos.';
        }
        $stmt->close();
    } else {
        $response['message'] = 'Faltan datos requeridos.';
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit();
