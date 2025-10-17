<?php
// Nombre del archivo: scripts/handle_aprobacion_sap.php
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once "../includes/functions.php";
$conexion = require_once "../config/database.php";
proteger_pagina();
header("Content-Type: application/json");

// --- CONFIGURACIÓN ---
$TASA_CAMBIO_USD_EVALUACION = 7.8;
$MONTO_LIMITE_GG = 20000; // Límite para requerir aprobación de Gerente General

// --- VALIDACIÓN DE ENTRADA Y PERMISOS INICIALES ---
if (!puede_aprobar() && !es_finanzas()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No tienes permiso para realizar esta acción.']);
    exit();
}

$solicitud_id = filter_input(INPUT_POST, 'solicitud_id', FILTER_VALIDATE_INT);
$accion = $_POST['accion'] ?? '';
$motivo_rechazo = trim($_POST['motivo'] ?? '');

if (!$solicitud_id || !in_array($accion, ['Aprobado', 'Rechazado'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Acción o ID de solicitud inválido.']);
    exit();
}

try {
    // 1. OBTENER DATOS DE LA SOLICITUD Y VALIDAR PERMISOS
    $stmt_check = $conexion->prepare("SELECT * FROM pagos_pendientes WHERE id = ?");
    $stmt_check->bind_param("i", $solicitud_id);
    $stmt_check->execute();
    $solicitud = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if (!$solicitud) throw new Exception("La solicitud #{$solicitud_id} no existe.");

    $puede_actuar = (es_admin() || 
                    (es_finanzas() && $solicitud['estado'] === 'Aprobado' && $accion === 'Rechazado') || 
                    (puede_aprobar() && $solicitud['aprobador_actual_id'] == $_SESSION['usuario_id']));

    if (!$puede_actuar) throw new Exception("No tienes permiso para actuar sobre esta solicitud en su estado actual.");

    $conexion->begin_transaction();

    // --- LÓGICA DE RECHAZO ---
    if ($accion === 'Rechazado') {
        if (empty($motivo_rechazo)) throw new Exception("El motivo del rechazo es obligatorio.");
        $sql = "UPDATE pagos_pendientes SET estado = 'Rechazado', aprobador_actual_id = NULL, motivo_rechazo = ? WHERE id = ?";
        $stmt_update = $conexion->prepare($sql);
        $stmt_update->bind_param("si", $motivo_rechazo, $solicitud_id);
        $message = "Solicitud rechazada correctamente.";
    } 
    // --- LÓGICA DE APROBACIÓN ---
    elseif ($accion === 'Aprobado') {
        $message = "Solicitud Aprobada."; // Mensaje por defecto

        // --- MOTOR DE FLUJO DE TRABAJO ---
        $departamento = $solicitud['departamento_solicitante'];
        $estado_actual = $solicitud['estado'];

        // --- PASO 1: ENCONTRAR AL SIGUIENTE APROBADOR EN LA CADENA ---
        // Buscamos al jefe del aprobador actual.
        $stmt_siguiente = $conexion->prepare("SELECT jefe_id FROM usuarios WHERE id = ?");
        $stmt_siguiente->bind_param("i", $solicitud['aprobador_actual_id']);
        $stmt_siguiente->execute();
        $siguiente_aprobador_id = $stmt_siguiente->get_result()->fetch_assoc()['jefe_id'] ?? null;
        $stmt_siguiente->close();

        // --- PASO 2: APLICAR LÓGICAS CONDICIONALES ---
        $monto_evaluacion = ($solicitud['DocCurrency'] === 'USD') ? $solicitud['total_pagar'] * $TASA_CAMBIO_USD_EVALUACION : $solicitud['total_pagar'];
        $necesita_aprobacion_gg = ($monto_evaluacion >= $MONTO_LIMITE_GG);

        // CONDICIÓN DE ESCALAR A GERENTE GENERAL (SOLO PARA LOGÍSTICA)
        if ($departamento === 'Logistica' && $estado_actual === 'Pendiente Gerente Bodega' && $necesita_aprobacion_gg) {
            $gg_result = $conexion->query("SELECT id FROM usuarios WHERE rol = 'gerente_general' LIMIT 1");
            $gerente_general_id = $gg_result->fetch_assoc()['id'] ?? null;
            if (!$gerente_general_id) throw new Exception("No se encontró un Gerente General para la aprobación final.");
            
            // Reasignamos al Gerente General
            $siguiente_aprobador_id = $gerente_general_id;
            $nuevo_estado = 'Pendiente Gerente General';
            $message = "Aprobado por Gerente. Pasa a Gerente General por el monto.";
        }

        // --- PASO 3: DECIDIR EL ESTADO FINAL ---
        if (isset($nuevo_estado)) {
            // Si ya definimos un nuevo estado (como escalar a GG), lo usamos.
            $sql = "UPDATE pagos_pendientes SET estado = ?, aprobador_actual_id = ? WHERE id = ?";
            $stmt_update = $conexion->prepare($sql);
            $stmt_update->bind_param("sii", $nuevo_estado, $siguiente_aprobador_id, $solicitud_id);
        } elseif ($siguiente_aprobador_id) {
            // Si hay un siguiente jefe en la cadena, simplemente reasignamos.
            // Esto funciona tanto para el flujo normal como para el de Logística (Jefe -> Gerente)
            $nuevo_estado = ($departamento === 'Logistica' && $estado_actual === 'Pendiente Jefe Bodega') ? 'Pendiente Gerente Bodega' : 'Pendiente de Jefe'; // Asigna el siguiente estado lógico
            
            $sql = "UPDATE pagos_pendientes SET estado = ?, aprobador_actual_id = ? WHERE id = ?";
            $stmt_update = $conexion->prepare($sql);
            $stmt_update->bind_param("sii", $nuevo_estado, $siguiente_aprobador_id, $solicitud_id);
            $message = "Aprobado. La solicitud ha pasado al siguiente nivel.";
        } else {
            // Si no hay más jefes, esta es la aprobación final. Pasa a Finanzas.
            $sql = "UPDATE pagos_pendientes SET estado = 'Aprobado', aprobador_actual_id = NULL, aprobado_por_usuario = ?, fecha_aprobacion = NOW() WHERE id = ?";
            $stmt_update = $conexion->prepare($sql);
            $stmt_update->bind_param("si", $_SESSION['nombre_usuario'], $solicitud_id);
            $message = "¡Aprobación final completada! La solicitud está ahora en Finanzas.";
        }
    } else {
        throw new Exception("Acción no válida.");
    }

    if (isset($stmt_update)) {
        $stmt_update->execute();
        $stmt_update->close();
        $conexion->commit();
        echo json_encode(['status' => 'success', 'message' => $message]);
    } else {
        throw new Exception("No se definió ninguna acción de actualización.");
    }

} catch (Exception $e) {
    if ($conexion) $conexion->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

if ($conexion) $conexion->close();
?>