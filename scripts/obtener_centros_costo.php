<?php
// scripts/obtener_centros_costo.php - VERSIÓN CON LÓGICA DE PADRES
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../classes/SapClient.php';

try {
    $company_name = $_GET['company'] ?? null;
    $dimension = $_GET['dimension'] ?? 1;
    $parent_code = $_GET['parent_code'] ?? null; // Nuevo parámetro

    if (empty($company_name)) {
        $sap_config = require '../config/sap.php';
        $company_name = $sap_config['default_company'];
    }

    $sap = new SapClient($company_name);
    
    // Decidir qué función llamar
    if ($parent_code) {
        // Si nos dan un padre, buscamos sus hijos
        $centros = $sap->searchCostCentersByParent($dimension, $parent_code);
    } else {
        // Si no, buscamos por dimensión (como antes, para el Nivel 1)
        $centros = $sap->searchCostCentersByDimension($dimension);
    }

    echo json_encode($centros);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error Crítico', 'message' => $e->getMessage()]);
}
exit();