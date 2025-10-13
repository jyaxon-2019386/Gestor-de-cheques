<?php
// ajax/get_predefined_accounts.php
header('Content-Type: application/json');

require_once '../includes/functions.php';
require_once '../includes/sap_service_layer.php'; // Incluir la nueva clase
proteger_pagina();

if (isset($_SESSION['sap_predefined_accounts']) && (time() - $_SESSION['sap_predefined_accounts_timestamp'] < 3600)) {
    echo json_encode(['status' => 'success', 'data' => $_SESSION['sap_predefined_accounts']]);
    exit();
}

$allowed_account_codes = array_unique([
    '_SYS00000000026', '_SYS00000000070', '_SYS00000000113', '_SYS00000000134',
    '_SYS00000000139', '_SYS00000000137', '_SYS00000000144', '_SYS00000000153',
    '_SYS00000000148', '_SYS00000002104'
]);

$response = ['status' => 'error', 'message' => 'No se pudieron obtener las cuentas de SAP.'];
$sap = new SapServiceLayer(); // Crear una instancia de nuestra clase

try {
    $sap->login(); // Iniciar sesión

    // Construir el filtro
    $filter_parts = array_map(fn($code) => "Code eq '$code'", $allowed_account_codes);
    $filter_string = implode(' or ', $filter_parts);
    $query_params = "?\$select=Code,Name&\$filter=" . urlencode($filter_string);

    // Realizar la petición GET usando el método de la clase
    $accounts_data = $sap->get("ChartOfAccounts" . $query_params);
    
    // El logout se hace al final, incluso si hay un error, usando un bloque finally
    $sap->logout();

    if (isset($accounts_data['value'])) {
        $_SESSION['sap_predefined_accounts'] = $accounts_data['value'];
        $_SESSION['sap_predefined_accounts_timestamp'] = time();
        $response = ['status' => 'success', 'data' => $accounts_data['value']];
    } else {
        throw new Exception("La respuesta de SAP no contenía un valor de cuentas válido.");
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    // Asegurarse de cerrar sesión si la conexión se estableció pero luego falló
    if ($sap) $sap->logout(); 
}

echo json_encode($response);
exit();