<?php
// includes/SapServiceLayer.php

class SapServiceLayer {
    private $config;
    private $cookie_file;
    private $company_db; 
    private $is_logged_in = false;
    private $base_uri;

    // ==========================================================
    // INICIO DE LA MODIFICACIÓN: Constructor flexible
    // ==========================================================
    public function __construct($company_db_name = null) {
        // Cargar todas las configuraciones de SAP
        $all_configs = require(__DIR__ . '/../config/sap_config.php');

        // Determinar qué empresa usar: la proporcionada o la de la sesión
        if ($company_db_name === null) {
            $company_db_name = $_SESSION['company_db'] ?? null;
        }

        // Si después de todo, no hay empresa, lanzamos el error
        if (empty($company_db_name)) {
            throw new Exception("Error: No se ha seleccionado una empresa en SAP.");
        }

        // Verificar si existe la configuración para la empresa seleccionada
        if (!isset($all_configs[$company_db_name])) {
            throw new Exception("Configuración no encontrada para la empresa: " . htmlspecialchars($company_db_name));
        }

        // Asignar la configuración específica de la empresa a la clase
        $this->config = $all_configs[$company_db_name];
        $this->company_db = $company_db_name;
        $this->base_uri = $this->config['base_uri'];

        // Usar un archivo de cookie único por sesión para evitar conflictos
        $this->cookie_file = sys_get_temp_dir() . '/sap_cookie_' . session_id() . '.txt';
    }
    // ==========================================================
    // FIN DE LA MODIFICACIÓN
    // ==========================================================

    public function login() {
        // La validación de $this->company_db ya se hizo en el constructor
        
        $login_data = [
            'UserName' => $this->config['username'],
            'Password' => $this->config['password'],
            'CompanyDB' => $this->company_db // Se usa la empresa determinada en el constructor
        ];

        $ch = curl_init($this->base_uri . 'Login');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($login_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_file);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            $this->is_logged_in = true;
            return json_decode($response, true);
        } else {
            $this->is_logged_in = false;
            // Intenta decodificar el error para un mensaje más claro
            $error_details = json_decode($response, true);
            $error_message = $error_details['error']['message']['value'] ?? $response;
            throw new Exception("Fallo en el login de SAP: " . $error_message);
        }
    }

    public function post($endpoint, $data) {
        $ch = curl_init($this->base_uri . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response_data = json_decode(curl_exec($ch), true);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['http_code' => $http_code, 'response_data' => $response_data];
    }

    public function get($endpoint) {
        $ch = curl_init($this->base_uri . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response_data = json_decode(curl_exec($ch), true);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $response_data;
    }

    public function logout() {
        if (!$this->is_logged_in) return;

        $ch = curl_init($this->base_uri . 'Logout');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_exec($ch);
        curl_close($ch);

        if (file_exists($this->cookie_file)) {
            @unlink($this->cookie_file);
        }
        $this->is_logged_in = false;
    }

    public function isLoggedIn() {
        return $this->is_logged_in;
    }
    
    public function __destruct() {
        // Es una buena práctica asegurarse de que la sesión se cierre.
        $this->logout(); 
    }
}