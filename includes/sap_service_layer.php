<?php
// includes/sap_service_layer.php

class SapServiceLayer {
    private $config;
    private $sessionCookie;

    public function __construct() {
        // Carga la configuración desde el archivo externo
        $this->config = require __DIR__ . '/../config/sap_config.php';
        $this->sessionCookie = null;
    }

    /**
     * Inicia sesión en el Service Layer y almacena la cookie de sesión.
     * @return bool true si el login fue exitoso, de lo contrario lanza una Exception.
     * @throws Exception Si el login falla por cualquier motivo.
     */
    public function login() {
        $login_payload = json_encode([
            'UserName' => $this->config['username'],
            'Password' => $this->config['password'],
            'CompanyDB' => $this->config['company_db']
        ]);

        $ch = curl_init($this->config['base_uri'] . 'Login');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $login_payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Expect:'], // 'Expect:' previene errores 100-Continue
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new Exception('Error cURL en Login: ' . curl_error($ch));
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header_str = substr($response, 0, $header_size);
        
        curl_close($ch);

        if ($http_code != 200) {
            $body = substr($response, $header_size);
            $error_data = json_decode($body, true);
            throw new Exception("Error de autenticación SAP: " . ($error_data['error']['message']['value'] ?? 'Respuesta inválida.'));
        }

        preg_match('/^Set-Cookie:\s*B1SESSION=([^;]*)/mi', $header_str, $matches);
        if (!isset($matches[1])) {
            throw new Exception("No se pudo obtener la cookie de sesión de SAP.");
        }
        
        $this->sessionCookie = 'B1SESSION=' . $matches[1];
        return true;
    }

    /**
     * Realiza una petición GET a un endpoint del Service Layer.
     * @param string $endpoint El endpoint a consultar (ej: "ChartOfAccounts?\$select=Code,Name").
     * @return array La respuesta decodificada de SAP.
     * @throws Exception Si la petición falla o no se ha iniciado sesión.
     */
    public function get($endpoint) {
        if (!$this->sessionCookie) {
            throw new Exception("Debe iniciar sesión en SAP antes de realizar una petición GET.");
        }

        $ch = curl_init($this->config['base_uri'] . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Cookie: ' . $this->sessionCookie],
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response_data = json_decode($response_body, true);

        if ($http_code != 200) {
             throw new Exception("Error en GET a SAP: " . ($response_data['error']['message']['value'] ?? 'Respuesta inválida.'));
        }

        return $response_data;
    }

    /**
     * Realiza una petición POST a un endpoint del Service Layer.
     * @param string $endpoint El endpoint donde postear (ej: "VendorPayments").
     * @param array $payload El cuerpo de la petición como un array asociativo.
     * @return array ['http_code' => int, 'response_data' => array]
     * @throws Exception Si la petición falla o no se ha iniciado sesión.
     */
    public function post($endpoint, $payload) {
        if (!$this->sessionCookie) {
            throw new Exception("Debe iniciar sesión en SAP antes de realizar una petición POST.");
        }
        
        $json_payload = json_encode($payload);

        $ch = curl_init($this->config['base_uri'] . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $json_payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Cookie: ' . $this->sessionCookie],
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'http_code' => $http_code,
            'response_data' => json_decode($response_body, true)
        ];
    }
    
    /**
     * Cierra la sesión en el Service Layer.
     */
    public function logout() {
        if ($this->sessionCookie) {
            $ch = curl_init($this->config['base_uri'] . 'Logout');
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Cookie: ' . $this->sessionCookie],
                CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false,
            ]);
            curl_exec($ch);
            curl_close($ch);
            $this->sessionCookie = null;
        }
    }
}