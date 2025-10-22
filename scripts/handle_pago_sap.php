<?php
// Nombre del archivo: scripts/handle_pago_sap.php (VERIFICADO - SIN CAMBIOS)
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
        $parts = explode('.', $standard_str);
        if (count($parts) > 1) {
            $decimal_part = array_pop($parts);
            $integer_part = implode('', $parts);
            return (float)($integer_part . '.' . $decimal_part);
        } else {
            return (float)$parts[0];
        }
    }

    // --- VALIDACIONES DE ENTRADA ---
    $docDate = $_POST['DocDate'] ?? '';
    $cardName = trim($_POST['CardName'] ?? '');
    $docCurrency = $_POST['DocCurrency'] ?? '';
    $cuentas = $_POST['cuentas'] ?? [];

    if (empty($docDate) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $docDate)) throw new Exception("La fecha es inválida.");
    if (empty($cardName)) throw new Exception("El nombre del beneficiario es obligatorio.");
    if (!in_array($docCurrency, ['QTZ', 'USD'])) throw new Exception("La moneda no es válida.");
    if (empty($cuentas['AccountCode']) || !is_array($cuentas['AccountCode'])) throw new Exception("Debe agregar al menos una partida de cuenta contable.");
    
    $conexion->begin_transaction();

    // 1. OBTENER DATOS CLAVE DEL USUARIO LOGUEADO
    $stmt_usuario = $conexion->prepare("SELECT u.departamento_id, d.nombre AS departamento_nombre, u.jefe_id FROM usuarios u LEFT JOIN departamentos d ON u.departamento_id = d.id WHERE u.id = ?");
    $stmt_usuario->bind_param("i", $_SESSION['usuario_id']);
    $stmt_usuario->execute();
    $usuario_data = $stmt_usuario->get_result()->fetch_assoc();
    $stmt_usuario->close();

    $departamento_id = $usuario_data['departamento_id'] ?? null;
    $departamento_nombre = $usuario_data['departamento_nombre'] ?? null;
    $primer_aprobador_id = $usuario_data['jefe_id'] ?? null;
    
    if (!$departamento_id) throw new Exception("Tu usuario no tiene un departamento válido asignado.");
    
    // 2. CALCULAR MONTO TOTAL
    $total_pagar = 0.0;
    $cuentas_procesadas = [];
    foreach ($cuentas["SumPaid"] as $index => $monto_str) {
        if (empty($cuentas['AccountCode'][$index])) continue;
        $monto_numerico = parse_final_amount($monto_str);
        if ($monto_numerico < 0) throw new Exception("El monto '{$monto_str}' no es válido.");
        $total_pagar += $monto_numerico;
        $cuentas_procesadas[$index] = $monto_numerico;
    }

    // LÓGICA DE INICIO DE FLUJO DE APROBACIÓN
    $estado_final = '';
    $aprobador_final_id = null;
    $fecha_aprobacion = null;

    if ($departamento_id == 13) {
        $estado_final = 'Pendiente de Jefe';
        if (!$primer_aprobador_id) {
            throw new Exception("No tienes un jefe asignado para iniciar el flujo de Logística.");
        }
        $aprobador_final_id = $primer_aprobador_id;
    
    } else {
        if ($total_pagar < 5000) {
            $estado_final = 'Aprobado';
            $aprobador_final_id = null;
            $fecha_aprobacion = date('Y-m-d H:i:s');
        } else {
            $estado_final = 'Pendiente de Jefe';
            if (!$primer_aprobador_id) {
                throw new Exception("No tienes un jefe asignado para iniciar el flujo de aprobación.");
            }
            $aprobador_final_id = $primer_aprobador_id;
        }
    }

    // 4. INSERTAR EL PAGO PENDIENTE
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