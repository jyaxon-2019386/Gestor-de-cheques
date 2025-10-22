<?php
// scripts/handle_cheque.php - VERSIÓN FINAL FUNCIONAL
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../includes/functions.php';

proteger_pagina();

$response = ['status' => 'error', 'message' => 'Ocurrió un error inesperado.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ======================================================
        // RECOLECCIÓN DE DATOS (20 CAMPOS)
        // ======================================================
        $usuario_id        = $_SESSION['usuario_id'];
        $tipo_soporte      = $_POST['tipo_soporte'] ?? 'No especificado';
        $empresa_sap       = $_POST['empresa_sap'] ?? null;
        $fecha_solicitud   = $_POST['fecha_solicitud'] ?? date('Y-m-d');
        $departamento_id   = !empty($_POST['departamento_id']) ? intval($_POST['departamento_id']) : null;
        $nombre_cheque     = $_POST['nombre_cheque'] ?? null;
        $empresa           = $_POST['empresa'] ?? null; // <-- TEXTO EMPRESA CORRECTO
        $valor_quetzales   = !empty($_POST['valor_quetzales']) ? floatval($_POST['valor_quetzales']) : 0.0;
        $valor_dolares     = !empty($_POST['valor_dolares']) ? floatval($_POST['valor_dolares']) : 0.0;
        $centro_costo      = $_POST['centro_costo'] ?? null;
        $descripcion       = $_POST['descripcion'] ?? null;
        $observaciones     = $_POST['observaciones'] ?? null;
        $nit_proveedor     = $_POST['nit_proveedor'] ?? null;
        $regimen_isr       = $_POST['regimen_isr'] ?? null;
        $correlativo       = $_POST['correlativo'] ?? null;
        $prioridad         = isset($_POST['prioridad']) ? intval($_POST['prioridad']) : 3;
        $incluye_factura   = $_POST['incluye_factura'] ?? null;
        $fecha_utilizarse  = !empty($_POST['fecha_utilizarse']) ? $_POST['fecha_utilizarse'] : null;
        $solicita_fecha    = !empty($_POST['solicita_fecha']) ? $_POST['solicita_fecha'] : null;
        $cargo             = $_POST['cargo'] ?? null;

        // ======================================================
        // CONSULTA SQL (20 parámetros)
        // ======================================================
        $sql = "INSERT INTO solicitud_cheques (
                    usuario_id, tipo_soporte, empresa_sap, fecha_solicitud, departamento_id,
                    nombre_cheque, empresa, valor_quetzales, valor_dolares, centro_costo,
                    descripcion, observaciones, nit_proveedor, regimen_isr, correlativo,
                    prioridad, incluye_factura, fecha_utilizarse,
                    solicita_fecha, cargo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conexion->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Error al preparar la consulta: " . $conexion->error);
        }

        // ======================================================
        // TIPOS Y BINDEO (20 tipos = 20 variables)
        // ======================================================
        $types = "isssisddsssssssissss"; // ✅ 20 tipos exactos

        $stmt->bind_param(
            $types,
            $usuario_id,       // i
            $tipo_soporte,     // s
            $empresa_sap,      // s
            $fecha_solicitud,  // s
            $departamento_id,  // i
            $nombre_cheque,    // s
            $empresa,          // s
            $valor_quetzales,  // d
            $valor_dolares,    // d
            $centro_costo,     // s
            $descripcion,      // s
            $observaciones,    // s
            $nit_proveedor,    // s
            $regimen_isr,      // s
            $correlativo,      // s
            $prioridad,        // i
            $incluye_factura,  // s
            $fecha_utilizarse, // s
            $solicita_fecha,   // s
            $cargo             // s
        );

        // ======================================================
        // EJECUCIÓN Y FLUJO DE APROBACIÓN
        // ======================================================
        if ($stmt->execute()) {
            $solicitud_id = $conexion->insert_id;
            $monto = $valor_quetzales;

            // Obtener jefe directo y gerente general
            $user_sql = "SELECT rol, jefe_id FROM usuarios WHERE id = ?";
            $stmt_user = $conexion->prepare($user_sql);
            $stmt_user->bind_param("i", $usuario_id);
            $stmt_user->execute();
            $solicitante = $stmt_user->get_result()->fetch_assoc();
            $jefe_directo_id = $solicitante['jefe_id'] ?? null;

            $gm_sql = "SELECT id FROM usuarios WHERE rol IN ('admin', 'gerente_general') LIMIT 1";
            $gerente_general_id = $conexion->query($gm_sql)->fetch_assoc()['id'] ?? null;

            $nuevo_estado = 'Pendiente';
            $proximo_aprobador_id = null;

            // Reglas de aprobación
            if ($monto < 5000) {
                $nuevo_estado = 'Aprobado';
                $proximo_aprobador_id = null;
            } else {
                if (!empty($_POST['aprobador_manual_id'])) {
                    $primer_aprobador = intval($_POST['aprobador_manual_id']);
                } elseif ($jefe_directo_id) {
                    $primer_aprobador = $jefe_directo_id;
                } else {
                    $primer_aprobador = $gerente_general_id;
                }

                if ($monto >= 25000) {
                    $nuevo_estado = 'Pendiente de Jefe';
                    $proximo_aprobador_id = $primer_aprobador;
                } else {
                    $aprobador_rol_sql = "SELECT rol FROM usuarios WHERE id = ?";
                    $stmt_rol = $conexion->prepare($aprobador_rol_sql);
                    $stmt_rol->bind_param("i", $primer_aprobador);
                    $stmt_rol->execute();
                    $rol_aprobador_result = $stmt_rol->get_result();
                    $rol_aprobador = $rol_aprobador_result ? $rol_aprobador_result->fetch_assoc()['rol'] : null;

                    if ($rol_aprobador === 'admin' || $rol_aprobador === 'gerente_general') {
                        $nuevo_estado = 'Pendiente Gerente General';
                    } else {
                        $nuevo_estado = 'Pendiente de Jefe';
                    }
                    $proximo_aprobador_id = $primer_aprobador;
                }
            }

            // Actualizar estado
            $update_sql = "UPDATE solicitud_cheques SET estado = ?, aprobador_actual_id = ? WHERE id = ?";
            $stmt_update = $conexion->prepare($update_sql);
            $stmt_update->bind_param("sii", $nuevo_estado, $proximo_aprobador_id, $solicitud_id);
            $stmt_update->execute();

            $response = ['status' => 'success', 'message' => '¡Solicitud creada exitosamente!'];
        } else {
            throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
        }

        $stmt->close();
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    $conexion->close();
} else {
    $response['message'] = 'Método de solicitud no válido.';
}

// ======================================================
// RESPUESTA JSON FINAL
// ======================================================
ini_set('display_errors', 0);
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
