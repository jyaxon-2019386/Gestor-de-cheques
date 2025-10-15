<?php
// ajax/get_predefined_accounts.php
header('Content-Type: application/json');

require_once '../includes/functions.php';
require_once '../includes/sap_service_layer.php'; // Incluir la clase
proteger_pagina();

// Comprobar si se ha seleccionado una empresa
if (empty($_SESSION['company_db'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No se ha seleccionado una empresa.']);
    exit();
}

// Usar caché si está disponible y es reciente
$current_company = $_SESSION['company_db'];
$cache_key = 'sap_predefined_accounts_' . $current_company;
if (isset($_SESSION[$cache_key]) && (time() - $_SESSION[$cache_key . '_timestamp'] < 3600)) {
    echo json_encode(['status' => 'success', 'data' => $_SESSION[$cache_key]]);
    exit();
}

$allowed_codes_by_company = [
    'TEST_PROQUIMA_ZZZ' => [ 
        '_SYS00000000026',
        '_SYS00000000113',
        '_SYS00000000139',
        '_SYS00000000144',
        '_SYS00000000148'
    ],
    'TEST_UNHESA_ZZZ' => [ 
        '_SYS00000000070',
        '_SYS00000000134',
        '_SYS00000000137',
        '_SYS00000000153',
        '_SYS00000002104'
    ]
];

// Obtener los códigos de cuenta permitidos para la empresa activa
$allowed_account_codes = $allowed_codes_by_company[$current_company] ?? [];

// Si no hay cuentas definidas para esta empresa, devolver un arreglo vacío.
if (empty($allowed_account_codes)) {
    echo json_encode(['status' => 'success', 'data' => []]);
    exit();
}

$response = ['status' => 'error', 'message' => 'No se pudieron obtener las cuentas de SAP.'];
$sap = new SapServiceLayer(); // Crear una instancia

try {
    $sap->login(); // Iniciar sesión

    // Construir el filtro para la consulta a SAP usando los códigos permitidos
    $filter_parts = array_map(fn($code) => "Code eq '$code'", $allowed_account_codes);
    $filter_string = implode(' or ', $filter_parts);
    $query_params = "?\$select=Code,Name&\$filter=" . urlencode($filter_string);

    // Realizar la petición GET al endpoint ChartOfAccounts
    $accounts_data = $sap->get("ChartOfAccounts" . $query_params);
    
    $sap->logout();

    if (isset($accounts_data['value'])) {
        // El formato de la respuesta de SAP ($accounts_data['value']) ya es el que necesitamos:
        // un array de objetos, cada uno con 'Code' y 'Name'.
        // No es necesario procesarlo ni mapearlo. Se asigna directamente.
        $final_accounts = $accounts_data['value'];

        // Guardar en sesión para el caché
        $_SESSION[$cache_key] = $final_accounts;
        $_SESSION[$cache_key . '_timestamp'] = time();
        $response = ['status' => 'success', 'data' => $final_accounts];

    } else {
        throw new Exception("La respuesta de SAP no contenía un valor de cuentas válido.");
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    // Asegurarse de cerrar sesión si la conexión falló
    if ($sap && $sap->isLoggedIn()) {
        $sap->logout(); 
    }
}

echo json_encode($response);
exit();