<?php
// scripts/handle_update_cheque.php - VERSIÓN FINAL Y CORREGIDA
ini_set('display_errors', 1); // Activar errores para depuración
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../includes/functions.php';

proteger_pagina();

$response = ['status' => 'error', 'message' => 'Ocurrió un error inesperado.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ======================================================
        // RECOLECCIÓN DE DATOS DEL FORMULARIO
        // ======================================================
        $id = isset($_POST['solicitud_id']) ? intval($_POST['solicitud_id']) : 0;
        if ($id <= 0) {
            throw new Exception("ID de solicitud no válido.");
        }

        $tipo_soporte     = $_POST['tipo_soporte'] ?? 'No especificado';
        $empresa_sap      = $_POST['empresa_sap'] ?? null;
        $fecha_solicitud  = $_POST['fecha_solicitud'] ?? date('Y-m-d');
        $departamento_id  = !empty($_POST['departamento_id']) ? intval($_POST['departamento_id']) : null;
        $nombre_cheque    = $_POST['nombre_cheque'] ?? null;
        $empresa          = $_POST['empresa'] ?? null;
        $valor_quetzales  = !empty($_POST['valor_quetzales']) ? floatval($_POST['valor_quetzales']) : 0.0;
        $valor_dolares    = !empty($_POST['valor_dolares']) ? floatval($_POST['valor_dolares']) : 0.0;
        $centro_costo     = $_POST['centro_costo'] ?? null;
        $descripcion      = $_POST['descripcion'] ?? null;
        $observaciones    = $_POST['observaciones'] ?? null;
        $nit_proveedor    = $_POST['nit_proveedor'] ?? null;
        $regimen_isr      = $_POST['regimen_isr'] ?? null;
        $correlativo      = $_POST['correlativo'] ?? null;
        $prioridad        = isset($_POST['prioridad']) ? intval($_POST['prioridad']) : 3;
        $incluye_factura  = $_POST['incluye_factura'] ?? null;
        $fecha_utilizarse = !empty($_POST['fecha_utilizarse']) ? $_POST['fecha_utilizarse'] : null;
        $solicita_fecha   = !empty($_POST['solicita_fecha']) ? $_POST['solicita_fecha'] : null;
        $cargo            = $_POST['cargo'] ?? null;

        // ======================================================
        // PREPARAR CONSULTA SQL (20 parámetros totales)
        // ======================================================
        $sql = "UPDATE solicitud_cheques SET
                    tipo_soporte = ?, empresa_sap = ?, fecha_solicitud = ?, departamento_id = ?,
                    nombre_cheque = ?, empresa = ?, valor_quetzales = ?, valor_dolares = ?, centro_costo = ?,
                    descripcion = ?, observaciones = ?, nit_proveedor = ?, regimen_isr = ?, correlativo = ?,
                    prioridad = ?, incluye_factura = ?, fecha_utilizarse = ?, solicita_fecha = ?, cargo = ?
                WHERE id = ?";

        $stmt = $conexion->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Error al preparar la consulta: " . $conexion->error);
        }

        // ======================================================
        // VINCULAR PARÁMETROS (20 tipos = 20 variables)
        // ======================================================
        $types = "sssisddssssssisssssi";

        $stmt->bind_param(
            $types,
            $tipo_soporte, $empresa_sap, $fecha_solicitud, $departamento_id,
            $nombre_cheque, $empresa, $valor_quetzales, $valor_dolares, $centro_costo,
            $descripcion, $observaciones, $nit_proveedor, $regimen_isr, $correlativo,
            $prioridad, $incluye_factura, $fecha_utilizarse, $solicita_fecha,
            $cargo, $id // último parámetro del WHERE
        );

        // ======================================================
        // EJECUTAR ACTUALIZACIÓN
        // ======================================================
        if (!$stmt->execute()) {
            throw new Exception("Error al ejecutar la actualización: " . $stmt->error);
        }

        // ===================================================================
        // INICIO: RE-EJECUTAR LÓGICA DEL WORKFLOW (CORREGIDA)
        // ===================================================================
        $monto = $valor_quetzales;
        $aprobador_manual_id = !empty($_POST['aprobador_manual_id']) ? intval($_POST['aprobador_manual_id']) : null;
        
        // Re-obtener datos del solicitante (el CREADOR original de la solicitud)
        // Necesitamos el ID del creador, que obtuvimos al principio para los permisos.
        
        // --- LA CORRECCIÓN CLAVE ---
        // Al principio del script, ya obtuvimos el creador original en $solicitud_original
        // Si no lo hicimos, lo obtenemos de nuevo aquí de forma segura.
        if (!isset($solicitud_original)) {
            $perm_sql = "SELECT usuario_id FROM solicitud_cheques WHERE id = ?";
            $stmt_perm = $conexion->prepare($perm_sql);
            $stmt_perm->bind_param("i", $id);
            $stmt_perm->execute();
            $solicitud_original = $stmt_perm->get_result()->fetch_assoc();
        }
        $creador_id = $solicitud_original['usuario_id'];
        
        $user_sql = "SELECT rol, jefe_id FROM usuarios WHERE id = ?";
        $stmt_user = $conexion->prepare($user_sql);
        $stmt_user->bind_param("i", $creador_id); // <-- Usamos el ID del creador, no el de la solicitud
        $stmt_user->execute();
        $solicitante = $stmt_user->get_result()->fetch_assoc();
        $jefe_directo_id = $solicitante['jefe_id'];

        $gm_sql = "SELECT id FROM usuarios WHERE rol IN ('admin', 'gerente_general', 'gerente') ORDER BY FIELD(rol, 'admin', 'gerente_general', 'gerente') LIMIT 1";
        $gerente_general_id = $conexion->query($gm_sql)->fetch_assoc()['id'] ?? null;
        
        $nuevo_estado = 'Pendiente';
        $proximo_aprobador_id = NULL;

        if ($monto < 5000) {
            $nuevo_estado = 'Aprobado';
            $proximo_aprobador_id = NULL; 
        } else {
            if ($aprobador_manual_id) {
                $primer_aprobador = $aprobador_manual_id;
            } elseif ($jefe_directo_id) {
                $primer_aprobador = $jefe_directo_id;
            } else {
                $primer_aprobador = $gerente_general_id;
            }

            if (!$primer_aprobador) {
                throw new Exception("No se pudo determinar un aprobador para el flujo de trabajo.");
            }

            if ($monto >= 25000) {
                $nuevo_estado = 'Pendiente de Jefe';
                $proximo_aprobador_id = $primer_aprobador;
            } else {
                $nuevo_estado = 'Pendiente de Jefe';
                $proximo_aprobador_id = $primer_aprobador;
            }
        }

        // Actualizar la solicitud con el nuevo estado y aprobador
        $update_workflow_sql = "UPDATE solicitud_cheques SET estado = ?, aprobador_actual_id = ? WHERE id = ?";
        $stmt_update_workflow = $conexion->prepare($update_workflow_sql);
        $stmt_update_workflow->bind_param("sii", $nuevo_estado, $proximo_aprobador_id, $id);
        $stmt_update_workflow->execute();
        // ===================================================================
        // FIN DEL WORKFLOW
        // ===================================================================

        // ======================================================
        // RESPUESTA FINAL
        // ======================================================
        $response = [
            'status'  => 'success',
            'message' => '¡Solicitud actualizada y reenviada para aprobación!'
        ];

        $stmt->close();
        $conexion->close();

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
} else {
    $response['message'] = 'Método de solicitud no válido.';
}

// ======================================================
// RESPUESTA JSON
// ======================================================
ini_set('display_errors', 0); // Ocultar errores en salida final
header('Content-Type: application/json');
echo json_encode($response);
exit;
