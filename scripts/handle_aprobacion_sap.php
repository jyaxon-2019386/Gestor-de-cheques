<?php
// scripts/handle_aprobacion_sap.php (Versión Final con Lógica Corregida)
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once "../includes/functions.php";
$conexion = require_once "../config/database.php";
proteger_pagina();
header("Content-Type: application/json");

// --- CONFIGURACIÓN ---
$TASA_CAMBIO_USD_EVALUACION = 7.8;
$MONTO_LIMITE_ESCALAR = 25000; // Límite en QTZ para requerir aprobación de Gerente

if (!puede_aprobar()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No tienes permiso.']);
    exit();
}

$solicitud_id = $_POST['solicitud_id'] ?? null;
$accion = $_POST['accion'] ?? null;

try {
    // 1. OBTENER DATOS DE LA SOLICITUD
    $stmt_check = $conexion->prepare("SELECT * FROM pagos_pendientes WHERE id = ?");
    $stmt_check->bind_param("i", $solicitud_id);
    $stmt_check->execute();
    $solicitud = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if (!$solicitud) throw new Exception("La solicitud #{$solicitud_id} no existe.");
    if ($solicitud['aprobador_actual_id'] != $_SESSION['usuario_id'] && !es_admin()) {
        throw new Exception("No eres el aprobador asignado para esta solicitud.");
    }

    $conexion->begin_transaction();

    if ($accion === 'Rechazado') {
        $sql = "UPDATE pagos_pendientes SET estado = 'Rechazado', aprobador_actual_id = NULL, MensajeErrorSAP = 'Rechazado por aprobador' WHERE id = ?";
        $stmt_update = $conexion->prepare($sql);
        $stmt_update->bind_param("i", $solicitud_id);
        $message = "Solicitud rechazada correctamente.";
    } else {
        // --- LÓGICA DE APROBACIÓN MEJORADA ---
        $monto_evaluacion = ($solicitud['DocCurrency'] === 'USD') ? $solicitud['total_pagar'] * $TASA_CAMBIO_USD_EVALUACION : $solicitud['total_pagar'];
        
        $stmt_rol = $conexion->prepare("SELECT rol FROM usuarios WHERE id = ?");
        $stmt_rol->bind_param("i", $_SESSION['usuario_id']);
        $stmt_rol->execute();
        $rol_aprobador = $stmt_rol->get_result()->fetch_assoc()['rol'] ?? 'usuario';
        $stmt_rol->close();
        
        $roles_autoridad_final = ['admin', 'gerente_general'];

        // Si el monto es alto Y el aprobador NO es la autoridad final, entonces se escala.
        if ($monto_evaluacion >= $MONTO_LIMITE_ESCALAR && !in_array($rol_aprobador, $roles_autoridad_final)) {
            // --- Caso 1: Escalar a Gerente General ---
            $gerente_id_result = $conexion->query("SELECT id FROM usuarios WHERE rol IN ('admin', 'gerente_general') LIMIT 1");
            $gerente_general_id = $gerente_id_result->fetch_assoc()['id'] ?? null;

            if (!$gerente_general_id) throw new Exception("No se encontró un Gerente para escalar la aprobación.");

            $sql = "UPDATE pagos_pendientes SET estado = 'Pendiente de Gerente General', aprobador_actual_id = ? WHERE id = ?";
            $stmt_update = $conexion->prepare($sql);
            $stmt_update->bind_param("ii", $gerente_general_id, $solicitud_id);
            $message = "Aprobado. La solicitud ahora requiere la aprobación final del Gerente General.";
        } else {
            // --- Caso 2: Aprobación Final (¡AQUÍ ESTÁ LA CLAVE!) ---
            // Esto se ejecuta si el monto es BAJO, o si el que aprueba YA ES un gerente/admin.
            $sql = "UPDATE pagos_pendientes SET estado = 'Aprobado', aprobador_actual_id = NULL, aprobado_por_usuario = ?, fecha_aprobacion = NOW() WHERE id = ?";
            $stmt_update = $conexion->prepare($sql);
            $stmt_update->bind_param("si", $_SESSION['nombre_usuario'], $solicitud_id);
            $message = "¡Solicitud Aprobada! Ahora está visible para Finanzas.";
        }
    }

    $stmt_update->execute();
    $stmt_update->close();
    $conexion->commit();

    echo json_encode(['status' => 'success', 'message' => $message]);

} catch (Exception $e) {
    if ($conexion) $conexion->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

if ($conexion) $conexion->close();
?>