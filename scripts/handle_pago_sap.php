<?php
// scripts/handle_pago_sap.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../includes/functions.php';

proteger_pagina();
$response = ['status' => 'error', 'message' => 'Petición no válida.'];

// Solo usuarios de finanzas o admins pueden procesar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_SESSION['rol'], ['finanzas', 'admin'])) {
    try {
        // --- 1. VALIDACIÓN DE DATOS DEL SERVIDOR (Ejemplo) ---
        if (empty($_POST['DocDate']) || empty($_POST['CardName']) || empty($_POST['PaymentChecks']['CheckNumber'])) {
            throw new Exception("Faltan campos obligatorios.");
        }

        // --- 2. CONSTRUCCIÓN DEL ARRAY PARA EL JSON DE SAP ---
        $sap_data = [
            "DocDate" => $_POST['DocDate'],
            "TaxDate" => $_POST['DocDate'], // Usualmente es la misma fecha
            "DocType" => "rAccount",
            "CardCode" => null, // Opcional, puedes añadir un campo si lo necesitas
            "CardName" => $_POST['CardName'],
            "DocCurrency" => "QTZ",
            "CashSum" => 0.0,
            "TransferSum" => 0.0,
            "Remarks" => $_POST['Remarks'],
            "JournalRemarks" => $_POST['JournalRemarks'],
            "U_Responsable" => $_SESSION['nombre_usuario'], // Responsable es el usuario logueado
            "U_Autoriza2" => "AUTORIZA", // Este campo podría venir de otro lado o ser fijo
            "PaymentAccounts" => [], // Se llenará a continuación
            "PaymentChecks" => [] // Se llenará a continuación
        ];

        // Procesar las cuentas de pago (partidas)
        if (!empty($_POST['cuentas']['AccountCode'])) {
            foreach ($_POST['cuentas']['AccountCode'] as $index => $code) {
                if (!empty($code)) {
                    $sap_data['PaymentAccounts'][] = [
                        "AccountCode" => $code,
                        "SumPaid" => (float)$_POST['cuentas']['SumPaid'][$index],
                        "Decription" => $_POST['cuentas']['Decription'][$index]
                    ];
                }
            }
        }
        
        // Procesar los detalles del cheque
        $check_details = $_POST['PaymentChecks'];
        $sap_data['PaymentChecks'][] = [
            "DueDate" => $_POST['DocDate'], // Usualmente la misma fecha
            "CheckNumber" => (int)$check_details['CheckNumber'],
            "BankCode" => $check_details['BankCode'],
            "Branch" => "01", // Valor fijo según tu ejemplo
            "AccounttNum" => $check_details['AccounttNum'],
            "CheckSum" => (float)$check_details['CheckSum'],
            "Currency" => "QTZ",
            "CheckAccount" => $check_details['CheckAccount'],
            "CountryCode" => "GT"
        ];


        // --- 3. LÓGICA DE ENVÍO A SAP (FUTURO) ---
        // $json_to_send = json_encode($sap_data);
        //
        // AQUÍ IRÍA EL CÓDIGO cURL O GUZZLE PARA ENVIAR $json_to_send A LA API DE SAP
        //
        // Ejemplo:
        // $sap_api_url = 'https://tu-servidor-sap:puerto/b1s/v1/IncomingPayments';
        // $response_from_sap = enviar_a_sap($sap_api_url, $json_to_send);
        //
        // if ($response_from_sap['status'] == 'success') {
        //     // Guardar en la base de datos local que el pago fue enviado
        // } else {
        //     throw new Exception("Error desde SAP: " . $response_from_sap['message']);
        // }

        $response['status'] = 'success';
        $response['message'] = 'Datos formateados correctamente para SAP.';
        $response['data_enviada'] = $sap_data; // Devolvemos los datos para depuración

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
} else {
    $response['message'] = 'No tienes permiso para realizar esta acción.';
}

header('Content-Type: application/json');
echo json_encode($response);
exit();
