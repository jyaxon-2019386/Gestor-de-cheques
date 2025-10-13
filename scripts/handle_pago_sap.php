<?php
// scripts/handle_pago_sap.php
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once "../includes/functions.php";
require_once "../includes/sap_service_layer.php"; // Incluir la nueva clase
proteger_pagina();
header("Content-Type: application/json");

$response = ["status" => "error", "message" => "Petición no válida."];

if (
    $_SERVER["REQUEST_METHOD"] !== "POST" ||
    !in_array($_SESSION["rol"], ["finanzas", "admin"])
) {
    $response["message"] = "Acceso no autorizado o método incorrecto.";
    echo json_encode($response);
    exit();
}

$sap = new SapServiceLayer(); // Crear una instancia

try {
    // --- 1. VALIDACIÓN Y CONSTRUCCIÓN DE DATOS (sin cambios) ---
    if (
        empty($_POST["DocDate"]) ||
        empty($_POST["CardName"]) ||
        empty($_POST["cuentas"])
    ) {
        throw new Exception("Faltan campos obligatorios.");
    }

    // ... (toda la lógica para construir el array $sap_data es la misma)
    $sap_data = [
        "DocType" => "rAccount",
        "DocDate" => $_POST["DocDate"],
        "TaxDate" => $_POST["DocDate"],
        "CardName" => $_POST["CardName"],
        "DocCurrency" => "QTZ",
        "Remarks" => $_POST["Remarks"],
        "JournalRemarks" => $_POST["JournalRemarks"],
        "U_Responsable" => $_SESSION["nombre_usuario"],
        "U_Autoriza2" => "AUTORIZA",
        "PaymentAccounts" => [],
        "PaymentChecks" => [],
    ];
    if (!empty($_POST["CardCode"])) {
        $sap_data["CardCode"] = $_POST["CardCode"];
    }
    if (!empty($_POST["cuentas"]["AccountCode"])) {
        foreach ($_POST["cuentas"]["AccountCode"] as $index => $code) {
            if (!empty($code)) {
                $sap_data["PaymentAccounts"][] = [
                    "AccountCode" => $code,
                    "SumPaid" => (float) $_POST["cuentas"]["SumPaid"][$index],
                    "Decription" => $_POST["cuentas"]["Decription"][$index],
                ];
            }
        }
    }
    if (
        isset($_POST["PaymentChecks"]["CheckNumber"]) &&
        !empty($_POST["PaymentChecks"]["CheckNumber"])
    ) {
        $check_details = $_POST["PaymentChecks"];
        $sap_data["PaymentChecks"][] = [
            "DueDate" => $_POST["DocDate"],
            "CheckNumber" => (int) $check_details["CheckNumber"],
            "BankCode" => $check_details["BankCode"],
            "Branch" => "01",
            "AccounttNum" => $check_details["AccounttNum"],
            "CheckSum" => (float) $check_details["CheckSum"],
            "CheckAccount" => $check_details["CheckAccount"],
        ];
    }

    // --- 2. LÓGICA DE ENVÍO A SAP USANDO LA CLASE ---
    $sap->login();

    $result = $sap->post("VendorPayments", $sap_data);

    $sap->logout();

    // --- 3. VERIFICAR RESPUESTA ---
    if ($result["http_code"] == 201) {
        // 201 Created
        $response["status"] = "success";
        $response["message"] = "¡Pago creado exitosamente en SAP!";
        $response["sap_response"] = $result["response_data"];
    } else {
        $error_message =
            "Error desde SAP. Código HTTP: " . $result["http_code"];
        if (isset($result["response_data"]["error"]["message"]["value"])) {
            $error_message .=
                " - Mensaje: " .
                $result["response_data"]["error"]["message"]["value"];
        }
        throw new Exception($error_message);
    }
} catch (Exception $e) {
    $response["message"] = $e->getMessage();
    if ($sap) {
        $sap->logout();
    } // Intentar cerrar sesión si algo falló
}

echo json_encode($response);
exit();
