<?php
// scripts/handle_pago_sap.php (Versión MySQLi)
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once "../includes/functions.php";
// Usamos la conexión MySQLi que devuelve el archivo
$conexion = require_once "../config/database.php"; 
proteger_pagina();

header("Content-Type: application/json");

// --- CONFIGURACIÓN ---
$TASA_CAMBIO_USD_EVALUACION = 7.8;

// Validaciones de acceso...
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !in_array($_SESSION["rol"], ["finanzas", "admin", "usuario"])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Acceso no autorizado."]);
    exit();
}

try {
    // 1. VALIDACIÓN Y CÁLCULO DE TOTALES (sin cambios en la lógica)
    // ... (código de validación idéntico)

    // 2. LÓGICA DE APROBACIÓN (con MySQLi)
    $usuario_id = $_SESSION['usuario_id'];
    $total_pagar = 0.0; // Se recalcula aquí
    foreach ($_POST["cuentas"]["SumPaid"] as $monto) $total_pagar += (float)$monto;

    $monto_evaluacion_gtq = ($_POST['DocCurrency'] === 'USD') ? $total_pagar * $TASA_CAMBIO_USD_EVALUACION : $total_pagar;

    // Obtener jefe directo
    $stmt_user = $conexion->prepare("SELECT jefe_id FROM usuarios WHERE id = ?");
    $stmt_user->bind_param("i", $usuario_id);
    $stmt_user->execute();
    $jefe_directo_id = $stmt_user->get_result()->fetch_assoc()['jefe_id'] ?? null;
    $stmt_user->close();

    // Obtener Gerente General
    $gerente_general_id = $conexion->query("SELECT id FROM usuarios WHERE rol IN ('admin', 'gerente_general') LIMIT 1")->fetch_assoc()['id'] ?? null;
    $primer_aprobador_id = $jefe_directo_id ?: $gerente_general_id;

    // Reglas de aprobación... (lógica idéntica)
    $nuevo_estado = 'Pendiente de Jefe';
    $aprobador_actual_id = null;
    if ($monto_evaluacion_gtq < 5000) {
        $nuevo_estado = 'Aprobado';
    } else {
        if (!$primer_aprobador_id) throw new Exception("No se encontró un aprobador válido.");
        $aprobador_actual_id = $primer_aprobador_id;
        // ... (resto de la lógica de estados)
    }

    // 3. INSERCIÓN EN BASE DE DATOS (Transacción MySQLi)
    $conexion->begin_transaction();

    $sql_pago = "INSERT INTO pagos_pendientes (usuario_id, empresa_db, estado, aprobador_actual_id, DocDate, DocCurrency, total_pagar, CardName, Remarks, JournalRemarks, CheckAccount, creado_por_usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_pago = $conexion->prepare($sql_pago);
    
    $checkAccount = $_POST['PaymentChecks']['CheckAccount'] ?? null;
    $stmt_pago->bind_param("ississdsssss", 
        $usuario_id, $_SESSION['company_db'], $nuevo_estado, $aprobador_actual_id, 
        $_POST['DocDate'], $_POST['DocCurrency'], $total_pagar, $_POST['CardName'],
        $_POST['Remarks'], $_POST['JournalRemarks'], $checkAccount, $_SESSION['nombre_usuario']
    );
    $stmt_pago->execute();
    $pago_id = $conexion->insert_id;
    $stmt_pago->close();

    $sql_cuenta = "INSERT INTO pagos_pendientes_cuentas (pago_id, AccountCode, SumPaid, Decription) VALUES (?, ?, ?, ?)";
    $stmt_cuenta = $conexion->prepare($sql_cuenta);

    foreach ($_POST["cuentas"]["AccountCode"] as $index => $code) {
        $monto = (float)$_POST["cuentas"]["SumPaid"][$index];
        $decription = $_POST["cuentas"]["Decription"][$index] ?? null;
        $stmt_cuenta->bind_param("isds", $pago_id, $code, $monto, $decription);
        $stmt_cuenta->execute();
    }
    $stmt_cuenta->close();
    
    $conexion->commit();

    // RESPUESTA EXITOSA... (lógica idéntica)
    http_response_code(201);
    $mensaje_final = "Solicitud guardada. Estado: " . $nuevo_estado;
    echo json_encode(["status" => "success", "message" => $mensaje_final]);

} catch (Exception $e) {
    $conexion->rollback();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
$conexion->close();
exit();