<?php
// scripts/handle_pago_sap.php
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once "../includes/functions.php";
require_once "../config/database.php"; // <--- IMPORTANTE: Asegúrate de tener tu archivo de conexión a la DB
proteger_pagina();

header("Content-Type: application/json");

// Validaciones iniciales (Rol, Empresa, Método)
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !in_array($_SESSION["rol"], ["finanzas", "admin"])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Acceso no autorizado."]);
    exit();
}

if (empty($_SESSION['company_db'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No se ha seleccionado una empresa.']);
    exit();
}

// Conexión a la base de datos (asumiendo que db.php crea un objeto $pdo)
global $pdo; 

try {
    // --- 1. VALIDACIÓN DE DATOS (igual que antes) ---
    if (empty($_POST["DocDate"]) || empty($_POST["CardName"]) || empty($_POST["cuentas"]) || empty($_POST['DocCurrency'])) {
        throw new Exception("Faltan campos obligatorios: Fecha, Moneda, Nombre de Beneficiario o Partidas.");
    }
    
    if (empty($_POST["cuentas"]["AccountCode"])) {
        throw new Exception("Debe agregar al menos una cuenta contable (partida).");
    }

    // --- 2. PREPARACIÓN DE DATOS PARA LA BASE DE DATOS ---
    $pdo->beginTransaction();

    // Insertar en la tabla principal `pagos_pendientes`
    $sql_pago = "INSERT INTO pagos_pendientes 
                    (empresa_db, DocDate, DocCurrency, CardName, Remarks, JournalRemarks, CheckAccount, creado_por_usuario) 
                 VALUES 
                    (:empresa_db, :doc_date, :doc_currency, :card_name, :remarks, :journal_remarks, :check_account, :creado_por)";

    $stmt_pago = $pdo->prepare($sql_pago);
    
    $stmt_pago->execute([
        ':empresa_db'       => $_SESSION['company_db'],
        ':doc_date'         => $_POST['DocDate'],
        ':doc_currency'     => $_POST['DocCurrency'],
        ':card_name'        => $_POST['CardName'],
        ':remarks'          => $_POST['Remarks'] ?? null,
        ':journal_remarks'  => $_POST['JournalRemarks'] ?? null,
        ':check_account'    => $_POST['PaymentChecks']['CheckAccount'] ?? null,
        ':creado_por'       => $_SESSION['nombre_usuario']
    ]);

    // Obtener el ID del pago recién insertado
    $pago_id = $pdo->lastInsertId();

    // Insertar cada línea de cuenta en la tabla `pagos_pendientes_cuentas`
    $sql_cuenta = "INSERT INTO pagos_pendientes_cuentas 
                    (pago_id, AccountCode, SumPaid, Decription) 
                   VALUES 
                    (:pago_id, :account_code, :sum_paid, :decription)";
    $stmt_cuenta = $pdo->prepare($sql_cuenta);

    foreach ($_POST["cuentas"]["AccountCode"] as $index => $code) {
        $monto = (float)($_POST["cuentas"]["SumPaid"][$index] ?? 0);
        if ($monto <= 0) {
            throw new Exception("El monto de cada partida debe ser mayor a cero.");
        }

        $stmt_cuenta->execute([
            ':pago_id'      => $pago_id,
            ':account_code' => $code,
            ':sum_paid'     => $monto,
            ':decription'   => $_POST["cuentas"]["Decription"][$index] ?? null
        ]);
    }
    
    // Si todo salió bien, confirmar la transacción
    $pdo->commit();

    // --- 3. RESPUESTA DE ÉXITO ---
    http_response_code(201); // 201 Created
    echo json_encode([
        "status" => "success", 
        "message" => "Solicitud de pago guardada correctamente. Queda pendiente de aprobación."
    ]);

} catch (Exception $e) {
    // Si algo falla, revertir la transacción
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

exit();