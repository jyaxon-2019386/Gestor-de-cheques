<?php
// includes/SapServiceLayer.php

class SapServiceLayer {
    private $config;
    private $cookie_file;
    // MODIFICACIÓN: Propiedad para almacenar la DB de la empresa de la sesión
    private $company_db; 
    private $is_logged_in = false;

    public function __construct() {
        $this->config = require(__DIR__ . '/../config/sap_config.php');
        // Usar un archivo de cookie único por sesión para evitar conflictos
        $this->cookie_file = sys_get_temp_dir() . '/sap_cookie_' . session_id() . '.txt';

        // MODIFICACIÓN: Obtener la empresa desde la sesión al crear el objeto
        if (isset($_SESSION['company_db'])) {
            $this->company_db = $_SESSION['company_db'];
        }
    }

    public function login() {
        // MODIFICACIÓN: Validar que se haya seleccionado una empresa antes de intentar el login
        if (empty($this->company_db)) {
            throw new Exception("Error: No se ha seleccionado una empresa en SAP para la sesión actual.");
        }

        $login_data = [
            'UserName' => $this->config['username'],
            'Password' => $this->config['password'],
            'CompanyDB' => $this->company_db // Se usa la empresa guardada en la sesión
        ];

        $ch = curl_init($this->config['base_uri'] . 'Login');
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
            throw new Exception("Fallo en el login de SAP: " . $response);
        }
    }

    public function post($endpoint, $data) {
        $ch = curl_init($this->config['base_uri'] . $endpoint);
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
        $ch = curl_init($this->config['base_uri'] . $endpoint);
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

        $ch = curl_init($this->config['base_uri'] . 'Logout');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_exec($ch);
        curl_close($ch);

        if (file_exists($this->cookie_file)) {
            unlink($this->cookie_file);
        }
        $this->is_logged_in = false;
    }

    public function isLoggedIn() {
        return $this->is_logged_in;
    }
    
    // Limpiar el cookie al destruir el objeto para cerrar sesión si se olvida
    public function __destruct() {
        // Opcional: Descomentar si se desea forzar el logout al final del script
        // $this->logout(); 
    }
}