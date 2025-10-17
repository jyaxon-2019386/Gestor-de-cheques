<?php
// Nombre del archivo: scripts/handle_pago_sap.php
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
    // --- VALIDACIONES DE ENTRADA ---
    $docDate = $_POST['DocDate'] ?? '';
    $cardName = trim($_POST['CardName'] ?? ''); // Capturamos el nombre del beneficiario
    $docCurrency = $_POST['DocCurrency'] ?? '';
    $cuentas = $_POST['cuentas'] ?? [];

    if (empty($docDate) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $docDate)) throw new Exception("La fecha es inválida.");
    if (empty($cardName)) throw new Exception("El nombre del beneficiario es obligatorio."); // La validación ya existía, pero el error estaba después
    if (!in_array($docCurrency, ['QTZ', 'USD'])) throw new Exception("La moneda no es válida.");
    if (empty($cuentas['AccountCode']) || !is_array($cuentas['AccountCode'])) throw new Exception("Debe agregar al menos una partida de cuenta contable.");
    
    $conexion->begin_transaction();

    // 1. OBTENER DATOS DEL USUARIO LOGUEADO (DEPARTAMENTO Y JEFE)
    $stmt_usuario = $conexion->prepare("SELECT d.nombre AS departamento_nombre, u.jefe_id FROM usuarios u LEFT JOIN departamentos d ON u.departamento_id = d.id WHERE u.id = ?");
    $stmt_usuario->bind_param("i", $_SESSION['usuario_id']);
    $stmt_usuario->execute();
    $usuario_data = $stmt_usuario->get_result()->fetch_assoc();
    $stmt_usuario->close();

    $departamento_nombre = $usuario_data['departamento_nombre'] ?? null;
    $primer_aprobador_id = $usuario_data['jefe_id'] ?? null;
    
    if (empty($departamento_nombre)) throw new Exception("Tu usuario no tiene un departamento válido asignado.");
    if (!$primer_aprobador_id) throw new Exception("No tienes un jefe directo asignado para iniciar el flujo.");

    // 2. DETERMINAR EL ESTADO INICIAL
    $estado_inicial = 'Pendiente de Jefe';
    if ($departamento_nombre === 'Logistica') {
        $estado_inicial = 'Pendiente Jefe Bodega';
    }

    // 3. INSERTAR EL PAGO PENDIENTE EN LA BASE DE DATOS
    $sql_pago = "INSERT INTO pagos_pendientes (usuario_id, empresa_db, departamento_solicitante, estado, aprobador_actual_id, DocDate, DocCurrency, total_pagar, CardName, Remarks, JournalRemarks, CheckAccount, creado_por_usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_pago = $conexion->prepare($sql_pago);
    
    $total_pagar = 0.0;
    foreach ($cuentas["SumPaid"] as $monto) {
        if (!is_numeric($monto) || (float)$monto <= 0) throw new Exception("Todos los montos deben ser números positivos.");
        $total_pagar += (float)$monto;
    }
    $checkAccount = $_POST['PaymentChecks']['CheckAccount'] ?? null;
    
    // ==========================================================
    // INICIO DE LA CORRECCIÓN FINAL (bind_param)
    // ==========================================================
    // Esta es la cadena de tipos correcta para tus 13 columnas.
    // i, s, s, s, i, s, s, d, s, s, s, s, s
    $stmt_pago->bind_param("isssissdsssss", 
        $_SESSION['usuario_id'],           // 1. usuario_id (i)
        $_SESSION['company_db'],           // 2. empresa_db (s)
        $departamento_nombre,              // 3. departamento_solicitante (s)
        $estado_inicial,                   // 4. estado (s)
        $primer_aprobador_id,              // 5. aprobador_actual_id (i)
        $_POST['DocDate'],                 // 6. DocDate (s)
        $_POST['DocCurrency'],             // 7. DocCurrency (s)
        $total_pagar,                      // 8. total_pagar (d)
        $cardName,                         // 9. CardName (s)  <-- AHORA ES CORRECTO
        $_POST['Remarks'],                 // 10. Remarks (s)
        $_POST['JournalRemarks'],          // 11. JournalRemarks (s)
        $checkAccount,                     // 12. CheckAccount (s)
        $_SESSION['nombre_usuario']        // 13. creado_por_usuario (s)
    );
    // ==========================================================
    // FIN DE LA CORRECCIÓN
    // ==========================================================
    
    $stmt_pago->execute();
    $pago_id = $conexion->insert_id;
    $stmt_pago->close();

    // 4. INSERTAR LAS LÍNEAS DE CUENTA
    $sql_cuenta = "INSERT INTO pagos_pendientes_cuentas (pago_id, AccountCode, SumPaid, Decription) VALUES (?, ?, ?, ?)";
    $stmt_cuenta = $conexion->prepare($sql_cuenta);
    foreach ($cuentas["AccountCode"] as $index => $code) {
        if (empty($code)) continue;
        $monto = (float)$cuentas["SumPaid"][$index];
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