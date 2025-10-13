<?php
// scripts/marcar_pagado.php
require_once '../config/database.php';
require_once '../includes/functions.php';

proteger_pagina();
$response = ['status' => 'error', 'message' => 'Acción no permitida.'];

// Solo Finanzas o Admins pueden marcar como pagado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SESSION['rol'] === 'finanzas' || es_admin())) {
    $solicitud_id = $_POST['solicitud_id'] ?? null;
    $usuario_gestion_id = $_SESSION['usuario_id'];

    if ($solicitud_id) {
        // Actualizamos el estado a 'Pagado' y registramos quién y cuándo lo hizo.
        $sql = "UPDATE solicitud_cheques 
                SET estado = 'Pagado', gestionado_por_id = ?, fecha_gestion = NOW() 
                WHERE id = ? AND estado = 'Aprobado'"; // Seguridad: solo se pueden pagar las aprobadas
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ii", $usuario_gestion_id, $solicitud_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response['status'] = 'success';
                $response['message'] = 'Solicitud marcada como "Pagado" exitosamente.';
                // (Aquí podrías añadir una entrada al historial de auditoría)
            } else {
                 $response['message'] = 'La solicitud no se pudo actualizar (quizás ya no estaba en estado "Aprobado").';
            }
        } else {
            $response['message'] = 'Error al actualizar la base de datos.';
        }
        $stmt->close();
    } else {
        $response['message'] = 'Faltas datos requeridos.';
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit();
