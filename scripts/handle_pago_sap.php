<?php
// Nombre del archivo: scripts/handle_pago_sap.php (CORREGIDO - COMBINA LÓGICA DE JEFATURA Y SUPERVISOR)
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once "../includes/functions.php";
$conexion = require_once "../config/database.php"; 
proteger_pagina();
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Método no permitido."]);
    exit();
}

try {
    function parse_final_amount($amount_str) {
        $clean_str = preg_replace('/[^\d,.]/', '', $amount_str);
        $standard_str = str_replace(',', '.', $clean_str);
        return (float)$standard_str;
    }

    // --- VALIDACIONES DE ENTRADA (sin cambios) ---
    $docDate = $_POST['DocDate'] ?? '';
    $cardName = trim($_POST['CardName'] ?? '');
    $docCurrency = $_POST['DocCurrency'] ?? '';
    $cuentas = $_POST['cuentas'] ?? [];

    if (empty($docDate) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $docDate)) throw new Exception("La fecha es inválida.");
    if (empty($cardName)) throw new Exception("El nombre del beneficiario es obligatorio.");
    // ... (resto de validaciones)

    $conexion->begin_transaction();

    // 1. OBTENER DATOS CLAVE DEL USUARIO LOGUEADO
    $stmt_usuario = $conexion->prepare("SELECT u.departamento_id, d.nombre AS departamento_nombre, u.jefe_id FROM usuarios u LEFT JOIN departamentos d ON u.departamento_id = d.id WHERE u.id = ?");
    $stmt_usuario->bind_param("i", $_SESSION['usuario_id']);
    $stmt_usuario->execute();
    $usuario_data = $stmt_usuario->get_result()->fetch_assoc();
    $stmt_usuario->close();

    $departamento_id = $usuario_data['departamento_id'] ?? null;
    $departamento_nombre = $usuario_data['departamento_nombre'] ?? null;
    $rol_creador = $_SESSION['rol'];

    if (!$departamento_id) throw new Exception("Tu usuario no tiene un departamento válido asignado.");
    
    // 2. CALCULAR MONTO TOTAL
    $total_pagar = 0.0;
    foreach ($cuentas["SumPaid"] as $monto_str) {
        $total_pagar += parse_final_amount($monto_str);
    }

    // ==========================================================================================
    // INICIO: LÓGICA DE FLUJO CORREGIDA
    // ==========================================================================================
    $estado_final = '';
    $aprobador_final_id = null;
    $fecha_aprobacion = null;

    // REGLA 1: Verificar si una jefatura está creando una solicitud de bajo monto.
    if (in_array($rol_creador, ['jefe_de_area', 'gerente', 'gerente_bodega']) && $total_pagar < 25000) {
        // Si se cumple, es una auto-aprobación y va directo a Finanzas.
        $estado_final = 'Aprobado';
        $aprobador_final_id = null;
        $fecha_aprobacion = date('Y-m-d H:i:s');
    } else {
        // REGLA 2 (CASO GENERAL): Para todos los demás casos, usar la lógica del supervisor.
        // Esto aplica a usuarios normales, o a jefaturas con montos >= 25,000.
        
        $primer_aprobador_id = $usuario_data['jefe_id'] ?? null;
        if (!$primer_aprobador_id) {
            throw new Exception("No puedes crear esta solicitud porque no tienes un supervisor asignado para el siguiente nivel de aprobación.");
        }

        $stmt_jefe = $conexion->prepare("SELECT rol FROM usuarios WHERE id = ?");
        $stmt_jefe->bind_param("i", $primer_aprobador_id);
        $stmt_jefe->execute();
        $jefe_data = $stmt_jefe->get_result()->fetch_assoc();
        $stmt_jefe->close();

        if (!$jefe_data) {
            throw new Exception("El supervisor asignado (ID: {$primer_aprobador_id}) no fue encontrado.");
        }
        $rol_del_jefe = $jefe_data['rol'];

        switch ($rol_del_jefe) {
            case 'jefe_de_area':
                $estado_final = 'Pendiente de Jefe';
                break;
            case 'gerente_bodega':
                $estado_final = 'Pendiente Gerente Bodega';
                break;
            case 'gerente_general':
                $estado_final = 'Pendiente Gerente General';
                break;
            case 'gerente':
                $estado_final = 'Pendiente de Gerente';
                break;
            default:
                $estado_final = 'Pendiente de Aprobación';
        }
        
        $aprobador_final_id = $primer_aprobador_id;
    }
    // ========================================================================================
    // FIN: LÓGICA DE FLUJO
    // ========================================================================================

    // 4. INSERTAR EL PAGO PENDIENTE (el resto del script es igual)
    $sql_pago = "INSERT INTO pagos_pendientes (usuario_id, departamento_id, empresa_db, departamento_solicitante, estado, aprobador_actual_id, fecha_aprobacion, DocDate, DocCurrency, total_pagar, CardName, Remarks, JournalRemarks, CheckAccount, creado_por_usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_pago = $conexion->prepare($sql_pago);
    $checkAccount = $_POST['PaymentChecks']['CheckAccount'] ?? null;
    
    $stmt_pago->bind_param("iisssisssdsssss", 
        $_SESSION['usuario_id'], $departamento_id, $_SESSION['company_db'], $departamento_nombre,
        $estado_final, $aprobador_final_id, $fecha_aprobacion, $_POST['DocDate'], $_POST['DocCurrency'],
        $total_pagar, $cardName, $_POST['Remarks'], $_POST['JournalRemarks'],
        $checkAccount, $_SESSION['nombre_usuario']
    );
    
    $stmt_pago->execute();
    $pago_id = $conexion->insert_id;
    $stmt_pago->close();

    // 5. INSERTAR LAS LÍNEAS DE CUENTA
    $sql_cuenta = "INSERT INTO pagos_pendientes_cuentas (pago_id, AccountCode, SumPaid, Decription) VALUES (?, ?, ?, ?)";
    $stmt_cuenta = $conexion->prepare($sql_cuenta);
    $cuentas_procesadas = [];
     foreach ($cuentas["SumPaid"] as $index => $monto_str) {
        if (empty($cuentas['AccountCode'][$index])) continue;
        $cuentas_procesadas[$index] = parse_final_amount($monto_str);
    }
    foreach ($cuentas["AccountCode"] as $index => $code) {
        if (empty($code)) continue;
        $monto = $cuentas_procesadas[$index]; 
        $decription = $cuentas["Decription"][$index] ?? null;
        $stmt_cuenta->bind_param("isds", $pago_id, $code, $monto, $decription);
        $stmt_cuenta->execute();
    }
    $stmt_cuenta->close();
    
    $conexion->commit();
    echo json_encode(["status" => "success", "message" => "Solicitud creada y enviada para aprobación."]);

} catch (Exception $e) {
    if ($conexion) $conexion->rollback();
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

if ($conexion) $conexion->close();
?>