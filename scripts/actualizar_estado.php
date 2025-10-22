<?php
// scripts/actualizar_estado.php - VERSIÓN FINAL Y ROBUSTA
ini_set('display_errors', 1); // Modo depuración
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../includes/functions.php';

proteger_pagina();
$response = ['status' => 'error', 'message' => 'Petición no válida.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && puede_aprobar()) {
    try {
        $solicitud_id = $_POST['solicitud_id'] ?? null;
        $nuevo_estado = $_POST['nuevo_estado'] ?? null;
        $motivo_rechazo = $_POST['motivo'] ?? null; // <-- NUEVA LÍNEA
        $usuario_gestion_id = $_SESSION['usuario_id'];

        if (!$solicitud_id || !in_array($nuevo_estado, ['Aprobado', 'Rechazado'])) {
            throw new Exception("Datos inválidos proporcionados.");
        }

        $stmt_sol = $conexion->prepare("SELECT valor_quetzales, estado FROM solicitud_cheques WHERE id = ?");
        $stmt_sol->bind_param("i", $solicitud_id);
        $stmt_sol->execute();
        $solicitud_actual = $stmt_sol->get_result()->fetch_assoc();

        if (!$solicitud_actual) {
            throw new Exception("La solicitud no fue encontrada.");
        }
        $monto = $solicitud_actual['valor_quetzales'];

        $siguiente_aprobador_id = NULL;
        $estado_final = $nuevo_estado;

        if ($nuevo_estado == 'Aprobado') {
            
            // --- LA CORRECCIÓN CLAVE: Identificar el nivel de aprobación actual ---
            
            // Si el monto es alto Y la aprobación viene de un 'Pendiente de Jefe'...
            if ($monto >= 25000 && $solicitud_actual['estado'] == 'Pendiente de Jefe') {
                // ...la solicitud debe escalar al Gerente General.
                
                // (La lógica de búsqueda jerárquica del Gerente General/Admin que ya tenías es correcta y va aquí)
                $siguiente_aprobador_id = null;

                // 1. Prioridad #1: Buscar un 'gerente_general'
                $gg_sql = "SELECT id FROM usuarios WHERE rol = 'gerente_general' LIMIT 1";
                $resultado_gg = $conexion->query($gg_sql);
                if ($resultado_gg && $resultado_gg->num_rows > 0) {
                    $siguiente_aprobador_id = $resultado_gg->fetch_assoc()['id'];
                }

                // 2. Prioridad #2 (Fallback): Si no hay Gerente General, buscar un 'admin'
                if (!$siguiente_aprobador_id) {
                    $admin_sql = "SELECT id FROM usuarios WHERE rol = 'admin' LIMIT 1";
                    $resultado_admin = $conexion->query($admin_sql);
                    if ($resultado_admin && $resultado_admin->num_rows > 0) {
                        $siguiente_aprobador_id = $resultado_admin->fetch_assoc()['id'];
                    }
                }
                
                if ($siguiente_aprobador_id) {
                    $estado_final = 'Pendiente Gerente General';
                } else {
                    throw new Exception("Flujo bloqueado: No se encontró un Gerente o Admin para la aprobación final.");
                }

            } 
            // Si la aprobación viene de un 'Pendiente Gerente General' o de cualquier otro caso...
            else {
                // ...esta es la APROBACIÓN FINAL. Pasa a Finanzas.
                $estado_final = 'Aprobado';
                $siguiente_aprobador_id = NULL;
            }
        }
        
        $sql_update = "UPDATE solicitud_cheques 
                       SET estado = ?, aprobador_actual_id = ?, gestionado_por_id = ?, fecha_gestion = NOW(), motivo_rechazo = ? 
                       WHERE id = ?";
        $stmt_update = $conexion->prepare($sql_update);
        // La cadena de tipos ahora necesita una 's' extra para el motivo
        $stmt_update->bind_param("siisi", $estado_final, $siguiente_aprobador_id, $usuario_gestion_id, $motivo_rechazo, $solicitud_id);
        
        if ($stmt_update->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'Estado actualizado correctamente a: ' . $estado_final;
        } else {
            throw new Exception("Error al actualizar la base de datos.");
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
} else {
    $response['message'] = 'No tienes permiso para realizar esta acción.';
}

ini_set('display_errors', 0);

header('Content-Type: application/json');
echo json_encode($response);
exit();