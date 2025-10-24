<?php
// ajax/get_bank_accounts.php
header('Content-Type: application/json');

require_once '../includes/functions.php';
proteger_pagina();

// Comprobar si se ha seleccionado una empresa
if (empty($_SESSION['company_db'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No se ha seleccionado una empresa.']);
    exit();
}

require_once '../includes/sap_service_layer.php';

// Usar caché si está disponible y es reciente
$current_company = $_SESSION['company_db'];
$cache_key = 'sap_bank_accounts_' . $current_company;
if (isset($_SESSION[$cache_key]) && (time() - $_SESSION[$cache_key . '_timestamp'] < 3600)) {
    echo json_encode(['status' => 'success', 'data' => $_SESSION[$cache_key]]);
    exit();
}

$response = ['status' => 'error', 'message' => 'No se pudieron obtener las cuentas bancarias de SAP.'];
$sap = new SapServiceLayer();

try {
    // Mapa de nombres de bancos por empresa
    $bank_name_map = [
        'TEST_UNHESA_ZZZ' => [
            '_SYS00000000010' => 'Banco Agromercantil',
            '_SYS00000006397' => 'Banco G&T Continental',
            '_SYS00000000007' => 'Banco Industrial',
            '_SYS00000004994' => 'Banco Industrial',
            '_SYS00000000008' => 'Banco de Desarrollo Rural',
            '_SYS00000000009' => 'Banco de Desarrollo Rural',
            '_SYS00000000012' => 'Westrust Bank',
            '_SYS00000005130' => 'Banco Industrial (Dólares)',
            '_SYS00000006257' => 'BAC Credomatic',
        ],
        'TEST_PROQUIMA_ZZZ' => [
            '_SYS00000000014' => 'Banco G&T Continental',
            '_SYS00000000013' => 'Banco Industrial',
            '_SYS00000000015' => 'Banco Promerica',
            '_SYS00000000018' => 'Banco Promerica (Dólares)',
        ]
    ];

    // ===============================================================================
    // INICIO DE LA MODIFICACIÓN: Mapa de cuentas en USD
    // ===============================================================================
    $usd_accounts_map = [
        'TEST_PROQUIMA_ZZZ' => ['_SYS00000000018'],
        'TEST_UNHESA_ZZZ'   => ['_SYS00000005130']
    ];
    // ===============================================================================
    // FIN DEL MAPA DE USD
    // ===============================================================================

    $sap->login();

    // Se consulta el endpoint estándar de SAP para obtener todas las cuentas
    $query_params = "?\$select=GLAccount,AccNo,BankCode,AccountName";
    $bank_data = $sap->get("HouseBankAccounts" . $query_params);
    
    $sap->logout();

    if (isset($bank_data['value'])) {
        $formatted_accounts = [];
        
        // Se procesa cada cuenta devuelta por SAP
        foreach ($bank_data['value'] as $account) {
            $gl_account = $account['GLAccount'];
            $account_num = $account['AccNo'];
            
            // Se busca el nombre personalizado en el mapa usando la empresa activa y el código SAP
            $custom_bank_name = $bank_name_map[$current_company][$gl_account] ?? null;

            // Si se encuentra un nombre personalizado, se usa. Si no, se usa el de SAP como respaldo.
            $display_name = $custom_bank_name ? 
                $custom_bank_name . ' # ' . $account_num : 
                $account['AccountName'];

            // Determinar la moneda de la cuenta
            $currency = 'QTZ'; // Moneda por defecto
            if (isset($usd_accounts_map[$current_company]) && in_array($gl_account, $usd_accounts_map[$current_company])) {
                $currency = 'USD';
            }

            $formatted_accounts[] = [
                "GLAccount"   => $gl_account,
                "AccNo"       => $account_num,
                "BankCode"    => $account['BankCode'],
                "AccountName" => $display_name,
                "Currency"    => $currency // Se añade la moneda al resultado
            ];
        }

        // Guardar en sesión para el caché
        $_SESSION[$cache_key] = $formatted_accounts;
        $_SESSION[$cache_key . '_timestamp'] = time();
        $response = ['status' => 'success', 'data' => $formatted_accounts];

    } else {
        throw new Exception("La respuesta de SAP no contenía un valor de cuentas bancarias válido.");
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    if ($sap && $sap->isLoggedIn()) {
        $sap->logout(); 
    }
}

echo json_encode($response);
exit();