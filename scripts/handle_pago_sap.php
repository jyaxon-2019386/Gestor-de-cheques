<?php
// Nombre del archivo: scripts/handle_pago_sap.php (VERSIÓN CORREGIDA Y FINAL)
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
    $cardName = trim($_POST['CardName'] ?? '');
    $docCurrency = $_POST['DocCurrency'] ?? '';
    $cuentas = $_POST['cuentas'] ?? [];

    if (empty($docDate) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $docDate)) throw new Exception("La fecha es inválida.");
    if (empty($cardName)) throw new Exception("El nombre del beneficiario es obligatorio.");
    if (!in_array($docCurrency, ['QTZ', 'USD'])) throw new Exception("La moneda no es válida.");
    if (empty($cuentas['AccountCode']) || !is_array($cuentas['AccountCode'])) throw new Exception("Debe agregar al menos una partida de cuenta contable.");
    
    $conexion->begin_transaction();

    // 1. OBTENER DATOS CLAVE DEL USUARIO LOGUEADO (INCLUYENDO EL ID DEL DEPARTAMENTO)
    $stmt_usuario = $conexion->prepare(
        "SELECT u.departamento_id, d.nombre AS departamento_nombre, u.jefe_id 
         FROM usuarios u 
         LEFT JOIN departamentos d ON u.departamento_id = d.id 
         WHERE u.id = ?"
    );
    $stmt_usuario->bind_param("i", $_SESSION['usuario_id']);
    $stmt_usuario->execute();
    $usuario_data = $stmt_usuario->get_result()->fetch_assoc();
    $stmt_usuario->close();

    $departamento_id = $usuario_data['departamento_id'] ?? null; // <-- CORREGIDO: Obtenemos el ID numérico
    $departamento_nombre = $usuario_data['departamento_nombre'] ?? null;
    $primer_aprobador_id = $usuario_data['jefe_id'] ?? null;
    
    if (!$departamento_id) throw new Exception("Tu usuario no tiene un departamento válido asignado.");
    if (!$primer_aprobador_id) throw new Exception("No tienes un jefe directo asignado para iniciar el flujo.");

    // 2. ESTADO INICIAL SIMPLIFICADO
    // El primer paso siempre es el jefe directo del usuario.
    $estado_inicial = 'Pendiente de Jefe';

    // 3. CALCULAR MONTO TOTAL
    $total_pagar = 0.0;
    foreach ($cuentas["SumPaid"] as $monto) {
        if (!is_numeric($monto) || (float)$monto <= 0) throw new Exception("Todos los montos deben ser números positivos.");
        $total_pagar += (float)$monto;
    }
    
    // 4. INSERTAR EL PAGO PENDIENTE (AHORA INCLUYENDO departamento_id)
    $sql_pago = "INSERT INTO pagos_pendientes 
                (usuario_id, departamento_id, empresa_db, departamento_solicitante, estado, aprobador_actual_id, DocDate, DocCurrency, total_pagar, CardName, Remarks, JournalRemarks, CheckAccount, creado_por_usuario) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_pago = $conexion->prepare($sql_pago);
    
    $checkAccount = $_POST['PaymentChecks']['CheckAccount'] ?? null;
    
    // CORREGIDO: Se añade 'i' para departamento_id y se pasa la variable $departamento_id
    $stmt_pago->bind_param("iisssissdsssss", 
        $_SESSION['usuario_id'],           // 1. usuario_id (i)
        $departamento_id,                  // 2. departamento_id (i) <-- ¡AÑADIDO!
        $_SESSION['company_db'],           // 3. empresa_db (s)
        $departamento_nombre,              // 4. departamento_solicitante (s)
        $estado_inicial,                   // 5. estado (s)
        $primer_aprobador_id,              // 6. aprobador_actual_id (i)
        $_POST['DocDate'],                 // 7. DocDate (s)
        $_POST['DocCurrency'],             // 8. DocCurrency (s)
        $total_pagar,                      // 9. total_pagar (d)
        $cardName,                         // 10. CardName (s)
        $_POST['Remarks'],                 // 11. Remarks (s)
        $_POST['JournalRemarks'],          // 12. JournalRemarks (s)
        $checkAccount,                     // 13. CheckAccount (s)
        $_SESSION['nombre_usuario']        // 14. creado_por_usuario (s)
    );
    
    $stmt_pago->execute();
    $pago_id = $conexion->insert_id;
    $stmt_pago->close();

    // 5. INSERTAR LAS LÍNEAS DE CUENTA (Sin cambios)
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