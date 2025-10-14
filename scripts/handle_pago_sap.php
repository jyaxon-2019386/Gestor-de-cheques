<?php
// scripts/handle_pago_sap.php
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once "../includes/functions.php";
proteger_pagina();

// ===============================================================================
// INICIO: LÓGICA DE GESTIÓN DE EMPRESA SAP
// ===============================================================================
// Comprobar si se ha seleccionado una empresa en la sesión
if (empty($_SESSION['company_db'])) {
    http_response_code(403); // Forbidden
    header("Content-Type: application/json");
    echo json_encode(['status' => 'error', 'message' => 'No se ha seleccionado una empresa. Por favor, vuelva a iniciar el proceso desde la página de selección.']);
    exit();
}
// ===============================================================================
// FIN: LÓGICA DE GESTIÓN DE EMPRESA SAP
// ===============================================================================

require_once "../includes/sap_service_layer.php";
header("Content-Type: application/json");

$response = ["status" => "error", "message" => "Petición no válida."];

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !in_array($_SESSION["rol"], ["finanzas", "admin"])) {
    http_response_code(403);
    $response["message"] = "Acceso no autorizado o método incorrecto.";
    echo json_encode($response);
    exit();
}

$sap = new SapServiceLayer();

try {
    // --- 1. VALIDACIÓN Y CÁLCULO INICIAL ---
    if (empty($_POST["DocDate"]) || empty($_POST["CardName"]) || empty($_POST["cuentas"])) {
        throw new Exception("Faltan campos obligatorios: Fecha, Nombre de Beneficiario o Partidas.");
    }

    $payment_accounts_array = [];
    $total_pagar = 0;

    if (!empty($_POST["cuentas"]["AccountCode"])) {
        foreach ($_POST["cuentas"]["AccountCode"] as $index => $code) {
            if (empty($code) || !isset($_POST["cuentas"]["SumPaid"][$index])) {
                throw new Exception("Cada partida debe tener una cuenta contable y un monto.");
            }
            $monto = (float)$_POST["cuentas"]["SumPaid"][$index];
            if ($monto <= 0) {
                 throw new Exception("El monto de cada partida debe ser mayor a cero.");
            }
            
            $payment_accounts_array[] = [
                "AccountCode" => $code,
                "SumPaid" => $monto,
                "Decription" => $_POST["cuentas"]["Decription"][$index] ?? null,
            ];
            $total_pagar += $monto;
        }
    } else {
         throw new Exception("Debe agregar al menos una cuenta contable (partida).");
    }
    
    if ($total_pagar <= 0) {
        throw new Exception("El total a pagar debe ser mayor a cero.");
    }


    // --- 2. CONSTRUCCIÓN DEL PAYLOAD BASE ---
    $sap_data = [
        "DocType" => "rAccount",
        "DocDate" => $_POST["DocDate"],
        "TaxDate" => $_POST["DocDate"],
        "CardCode" => !empty($_POST["CardCode"]) ? $_POST["CardCode"] : null,
        "CardName" => $_POST["CardName"],
        "DocCurrency" => "QTZ",
        "Remarks" => $_POST["Remarks"] ?? null,
        "JournalRemarks" => $_POST["JournalRemarks"] ?? null,
        "U_Responsable" => $_SESSION["nombre_usuario"],
        "PaymentAccounts" => $payment_accounts_array
    ];

    // --- Lógica para balancear el monto total del pago ---
    if (isset($_POST["PaymentChecks"]["CheckAccount"]) && !empty($_POST["PaymentChecks"]["CheckAccount"])) {
        // --- CASO 1: PAGO BANCARIO (CHEQUE O TRANSFERENCIA) ---
        $check_details = $_POST["PaymentChecks"];

        if (empty($check_details["BankCode"]) || empty($check_details["AccounttNum"])) {
            throw new Exception("Para un pago bancario, los detalles del banco y número de cuenta son obligatorios.");
        }

        $sap_data["PaymentChecks"] = [[
            "DueDate" => $_POST["DocDate"],
            "CheckNumber" => 0,
            "BankCode" => $check_details["BankCode"],
            "Branch" => "01",
            "AccounttNum" => $check_details["AccounttNum"],
            "CheckSum" => $total_pagar,
            "CheckAccount" => $check_details["CheckAccount"],
            "Currency" => "QTZ",
            "CountryCode" => "GT"
        ]];

        $sap_data["CashSum"] = 0.0;
    } else {
        // --- CASO 2: PAGO EN EFECTIVO ---
        $sap_data["CashSum"] = $total_pagar;
    }


    // --- 3. LÓGICA DE ENVÍO A SAP ---
    $sap->login();
    $result = $sap->post("VendorPayments", $sap_data);
    $sap->logout();

    // --- 4. VERIFICAR RESPUESTA ---
    if (isset($result["http_code"]) && $result["http_code"] == 201) {
        $response["status"] = "success";
        $response["message"] = "¡Pago creado exitosamente en SAP!";
        $response["sap_response"] = $result["response_data"] ?? null;
        http_response_code(201);
    } else {
        $error_message = "Error desconocido desde SAP.";
        if (isset($result["response_data"]["error"]["message"]["value"])) {
            $error_message = $result["response_data"]["error"]["message"]["value"];
        }
        $http_status = $result["http_code"] ?? 400;
        http_response_code($http_status);
        throw new Exception($error_message);
    }

} catch (Exception $e) {
    $response["message"] = $e->getMessage();
    if ($sap && $sap->isLoggedIn()) {
        $sap->logout();
    }
    if (http_response_code() < 400) {
        http_response_code(500);
    }
}

echo json_encode($response);
exit();