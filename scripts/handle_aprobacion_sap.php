<?php
// Nombre del archivo: scripts/handle_aprobacion_sap.php (ACTUALIZADO CON EL NUEVO MONTO DE 25,000)

require_once '../includes/functions.php';
proteger_pagina();

$conexion = require_once '../config/database.php';
header('Content-Type: application/json');

try {
    // 1. Obtener datos de la solicitud AJAX
    $solicitud_id = filter_input(INPUT_POST, 'solicitud_id', FILTER_VALIDATE_INT);
    $accion = $_POST['accion'] ?? '';
    $motivo_rechazo = $_POST['motivo'] ?? NULL;

    if (!$solicitud_id || empty($accion)) {
        throw new Exception("Datos incompletos.");
    }
    
    // Lógica para el Rechazo (sin cambios)
    if ($accion === 'Rechazado') {
        if (empty($motivo_rechazo)) throw new Exception("El motivo de rechazo es obligatorio.");
        $stmt = $conexion->prepare("UPDATE pagos_pendientes SET estado = 'Rechazado', motivo_rechazo = ?, aprobador_actual_id = NULL WHERE id = ?");
        $stmt->bind_param('si', $motivo_rechazo, $solicitud_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Solicitud rechazada correctamente.']);
        } else { throw new Exception("Error al actualizar el estado de la solicitud."); }
        $stmt->close(); $conexion->close(); exit();
    }
    
    if ($accion === 'Aprobado') {
        // 2. OBTENER DATOS CRÍTICOS DE LA SOLICITUD
        $stmt_info = $conexion->prepare("SELECT departamento_id, total_pagar FROM pagos_pendientes WHERE id = ?");
        $stmt_info->bind_param('i', $solicitud_id);
        $stmt_info->execute();
        $resultado_info = $stmt_info->get_result()->fetch_assoc();
        $stmt_info->close();

        if (!$resultado_info) {
            throw new Exception("No se encontró la solicitud.");
        }

        $departamento_id_solicitud = $resultado_info['departamento_id'];
        $total_pagar = (float)$resultado_info['total_pagar'];

        // ==========================================================================================
        // INICIO: LÓGICA DE FLUJO ACTUALIZADA
        // ==========================================================================================
        $rol_aprobador = $_SESSION['rol'];
        $next_estado = '';
        $next_aprobador_id = null;
        $es_aprobacion_final = false;

        switch ($rol_aprobador) {
            case 'jefe_de_area':
                if ($departamento_id_solicitud == 13) {
                    // Flujo de Logística (sin cambios)
                    $next_estado = 'Pendiente Gerente Bodega';
                    $stmt_next = $conexion->prepare("SELECT id FROM usuarios WHERE rol = 'gerente_bodega' LIMIT 1");
                } else {
                    // ===== INICIO DEL CAMBIO SOLICITADO =====
                    // Para otros departamentos, el monto para ir a Gerente General ahora es 25,000
                    if ($total_pagar >= 25000) {
                        $next_estado = 'Pendiente Gerente General';
                        $stmt_next = $conexion->prepare("SELECT id FROM usuarios WHERE rol = 'gerente_general' LIMIT 1");
                    } else {
                        $next_estado = 'Aprobado';
                        $es_aprobacion_final = true;
                    }
                    // ===== FIN DEL CAMBIO SOLICITADO =====
                }
                
                if (!$es_aprobacion_final) {
                    $stmt_next->execute();
                    $next_user = $stmt_next->get_result()->fetch_assoc();
                    if (!$next_user) throw new Exception("No se encontró al siguiente aprobador en la cadena.");
                    $next_aprobador_id = $next_user['id'];
                    $stmt_next->close();
                }
                break;
            
            case 'gerente':
                throw new Exception("Tu rol de 'Gerente' no tiene un paso de aprobación definido en este flujo.");
                break;

            case 'gerente_bodega':
                // Flujo de Logística (sin cambios)
                if ($total_pagar >= 20000) {
                    $next_estado = 'Pendiente Gerente General';
                    $stmt_next = $conexion->prepare("SELECT id FROM usuarios WHERE rol = 'gerente_general' LIMIT 1");
                    $stmt_next->execute();
                    $next_user = $stmt_next->get_result()->fetch_assoc();
                    if (!$next_user) throw new Exception("No se encontró al Gerente General en el sistema.");
                    $next_aprobador_id = $next_user['id'];
                    $stmt_next->close();
                } else {
                    $next_estado = 'Aprobado';
                    $es_aprobacion_final = true;
                }
                break;

            case 'gerente_general':
                $next_estado = 'Aprobado';
                $es_aprobacion_final = true;
                break;
            
            default:
                throw new Exception("Tu rol no está autorizado para aprobar solicitudes.");
        }

        if (empty($next_estado)) throw new Exception("No se pudo determinar el siguiente estado de la solicitud.");
        
        // 4. ACTUALIZAR LA SOLICITUD EN LA BASE DE DATOS
        if ($es_aprobacion_final) {
            $stmt_update = $conexion->prepare("UPDATE pagos_pendientes SET estado = ?, aprobador_actual_id = NULL, fecha_aprobacion = NOW() WHERE id = ?");
            $stmt_update->bind_param('si', $next_estado, $solicitud_id);
        } else {
            $stmt_update = $conexion->prepare("UPDATE pagos_pendientes SET estado = ?, aprobador_actual_id = ? WHERE id = ?");
            $stmt_update->bind_param('sii', $next_estado, $next_aprobador_id, $solicitud_id);
        }
        
        if ($stmt_update->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Solicitud aprobada y enviada al siguiente nivel.']);
        } else {
            throw new Exception("Error al actualizar la solicitud.");
        }
        $stmt_update->close();
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conexion->close();
?>