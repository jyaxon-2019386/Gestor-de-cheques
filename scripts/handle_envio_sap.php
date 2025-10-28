<?php
// scripts/handle_envio_sap.php (Versión Final con Corrección de AccounttNum)
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once "../includes/functions.php";
require_once "../includes/sap_service_layer.php";
$conexion = require_once "../config/database.php";
proteger_pagina();
header("Content-Type: application/json");

if (!in_array($_SESSION['rol'], ['finanzas', 'admin'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No tienes permiso.']);
    exit();
}

$pago_id = $_POST['pago_id'] ?? null;
if (!$pago_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID de pago no proporcionado.']);
    exit();
}

$sap = null;

try {
    // 1. OBTENER DATOS DEL PAGO DESDE LA BASE DE DATOS LOCAL
    $stmt_pago = $conexion->prepare("SELECT * FROM pagos_pendientes WHERE id = ? AND estado = 'Aprobado'");
    $stmt_pago->bind_param("i", $pago_id);
    $stmt_pago->execute();
    $pago = $stmt_pago->get_result()->fetch_assoc();
    $stmt_pago->close();

    if (!$pago) {
        throw new Exception("El pago no existe o no está en estado 'Aprobado'.");
    }
    
    $empresa_del_pago = $pago['empresa_db'];
    $sap = new SapServiceLayer($empresa_del_pago);

    $stmt_cuentas = $conexion->prepare("SELECT * FROM pagos_pendientes_cuentas WHERE pago_id = ?");
    $stmt_cuentas->bind_param("i", $pago_id);
    $stmt_cuentas->execute();
    $cuentas = $stmt_cuentas->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_cuentas->close();
    
    // 2. CONSTRUIR EL PAYLOAD PARA SAP
    $payment_accounts_array = [];
    foreach ($cuentas as $cuenta) {
        $payment_accounts_array[] = [
            "AccountCode" => $cuenta['AccountCode'],
            "SumPaid" => (float)$cuenta['SumPaid'],
            "Decription" => $cuenta['Decription'],
        ];
    }
    
    $sap_data = [
        "DocType" => "rAccount", "DocDate" => $pago['DocDate'], "TaxDate" => $pago['DocDate'],
        "CardName" => $pago['CardName'], "DocCurrency" => $pago['DocCurrency'],
        "Remarks" => $pago['Remarks'], "JournalRemarks" => $pago['JournalRemarks'],
        "U_Responsable" => $pago['creado_por_usuario'],
        "PaymentAccounts" => $payment_accounts_array, "CashSum" => (float)$pago['total_pagar']
    ];

    $sap->login();

    if (!empty($pago['CheckAccount'])) {
      
        $all_bank_accounts_raw = $sap->get("HouseBankAccounts?\$select=GLAccount,BankCode,AccNo");
        
        $bank_details = null;
        if (isset($all_bank_accounts_raw['value']) && is_array($all_bank_accounts_raw['value'])) {
            foreach ($all_bank_accounts_raw['value'] as $account) {
                if ($account['GLAccount'] === $pago['CheckAccount']) {
                    $bank_details = $account;
                    break;
                }
            }
        }

        if ($bank_details !== null) {
            $sap_data["PaymentChecks"] = [[
                "DueDate" => $pago['DocDate'],
                "CheckNumber" => 0,
                "BankCode" => $bank_details['BankCode'],
                "Branch" => "01",
                "AccounttNum" => $bank_details['AccNo'], 
                "CheckSum" => (float)$pago['total_pagar'],
                "CheckAccount" => $pago['CheckAccount'],
                "Currency" => $pago['DocCurrency'],
                "CountryCode" => "GT"
            ]];
            $sap_data["CashSum"] = 0.0;
        } else {
             throw new Exception("No se encontraron los detalles de la cuenta bancaria '{$pago['CheckAccount']}' en SAP.");
        }
    
    }

    // 3. ENVIAR A SAP
    $result = $sap->post("VendorPayments", $sap_data);

    // 4. MANEJAR RESPUESTA DE SAP Y ACTUALIZAR DB LOCAL
    if (isset($result["http_code"]) && $result["http_code"] == 201) {
        $doc_entry = $result["response_data"]["DocEntry"] ?? null;
        
        $stmt_update = $conexion->prepare("UPDATE pagos_pendientes SET estado = 'ProcesadoSAP', NumeroDocumentoSAP = ? WHERE id = ?");
        $stmt_update->bind_param("ii", $doc_entry, $pago_id);
        $stmt_update->execute();
        $stmt_update->close();

        echo json_encode(['status' => 'success', 'message' => "¡Pago enviado a SAP exitosamente! DocEntry: " . $doc_entry]);
    } else {
        $error_message = $result["response_data"]["error"]["message"]["value"] ?? "Error desconocido desde SAP.";
        $stmt_update = $conexion->prepare("UPDATE pagos_pendientes SET MensajeErrorSAP = ? WHERE id = ?");
        $stmt_update->bind_param("si", $error_message, $pago_id);
        $stmt_update->execute();
        $stmt_update->close();
        throw new Exception($error_message);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    if ($sap && $sap->isLoggedIn()) $sap->logout();
    if ($conexion) $conexion->close();
}
?>  