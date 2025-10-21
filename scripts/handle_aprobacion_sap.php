<?php
// Nombre del archivo: scripts/handle_aprobacion_sap.php (VERSIÓN CORREGIDA Y FINAL)

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
    
    // Lógica de Rechazo (Sin cambios)
    if ($accion === 'Rechazado') {
        // ... (Tu lógica de rechazo es correcta y se mantiene)
        if (empty($motivo_rechazo)) throw new Exception("El motivo de rechazo es obligatorio.");
        $stmt = $conexion->prepare("UPDATE pagos_pendientes SET estado = 'Rechazado', motivo_rechazo = ? WHERE id = ?");
        $stmt->bind_param('si', $motivo_rechazo, $solicitud_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Solicitud rechazada correctamente.']);
        } else { throw new Exception("Error al actualizar el estado de la solicitud."); }
        $stmt->close(); $conexion->close(); exit();
    }
    
    if ($accion === 'Aprobado') {
        // 2. OBTENER DATOS CRÍTICOS DIRECTAMENTE DE LA SOLICITUD
        // CORREGIDO: Se elimina el JOIN y se lee de la propia tabla 'pagos_pendientes'
        $sql_info = "SELECT total_pagar, departamento_id 
                     FROM pagos_pendientes 
                     WHERE id = ?";
        $stmt_info = $conexion->prepare($sql_info);
        $stmt_info->bind_param('i', $solicitud_id);
        $stmt_info->execute();
        $resultado_info = $stmt_info->get_result()->fetch_assoc();
        $stmt_info->close();

        if (!$resultado_info) {
            throw new Exception("No se encontró la solicitud.");
        }

        $total_pagar = $resultado_info['total_pagar'];
        $departamento_id_solicitud = $resultado_info['departamento_id'];

        // 3. DETERMINAR EL SIGUIENTE ESTADO BASADO EN LAS REGLAS
        $rol_aprobador = $_SESSION['rol'];
        $next_estado = '';

        switch ($rol_aprobador) {
            case 'jefe_de_area':
            case 'gerente':
                // Regla para el departamento de Logística (ID 13)
                if ($departamento_id_solicitud == 13) {
                    $next_estado = 'Pendiente de Gerente';
                    if ($rol_aprobador == 'gerente') {
                        $next_estado = 'Pendiente de Gerente General';
                    }
                } else {
                    // Reglas para TODOS LOS DEMÁS departamentos
                    if ($total_pagar > 20000) {
                        $next_estado = 'Pendiente de Gerente General';
                    } else {
                        // Para montos de 20,000 o menos, va directo a Finanzas
                        $next_estado = 'Aprobado';
                    }
                }
                break;

            case 'gerente_general':
                // La aprobación del Gerente General siempre es el último paso antes de Finanzas.
                $next_estado = 'Aprobado';
                break;
            
            default:
                throw new Exception("Tu rol no está autorizado para aprobar solicitudes.");
        }

        if (empty($next_estado)) {
            throw new Exception("No se pudo determinar el siguiente estado de la solicitud.");
        }
        
        // 4. ACTUALIZAR LA SOLICITUD
        $stmt_update = $conexion->prepare("UPDATE pagos_pendientes SET estado = ? WHERE id = ?");
        $stmt_update->bind_param('si', $next_estado, $solicitud_id);
        
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