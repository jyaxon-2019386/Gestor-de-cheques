<?php
// classes/SapClient.php

class SapClient {
    private $conn;
    private $config;
    private $company;

    public function __construct($company) {
        $this->config = require __DIR__ . '/../config/sap.php';
        $this->company = $company;

        if (!isset($this->config['companies'][$this->company])) {
            throw new Exception("Configuración para la empresa '{$this->company}' no encontrada.");
        }

        $db_name = $this->config['companies'][$this->company]['database'];
        $server_name = $this->config['server'] . ',' . $this->config['port'];

        $connection_info = [
            "Database" => $db_name,
            "UID" => $this->config['username'],
            "PWD" => $this->config['password'],
            "CharacterSet" => "UTF-8",
            "LoginTimeout" => 10,
            "TrustServerCertificate" => "yes" // <-- ¡LA CORRECCIÓN CLAVE!
        ];

        $this->conn = sqlsrv_connect($server_name, $connection_info);

        if ($this->conn === false) {
            // Lanzar una excepción detallada si la conexión falla.
            throw new Exception("No se pudo conectar a la base de datos de SAP. Detalles: " . print_r(sqlsrv_errors(), true));
        }
    }

    public function searchCostCentersByDimension($dimension) {
        $sql = "SELECT OcrCode, OcrName FROM OOCR WHERE DimCode = ? ORDER BY OcrCode";
        $params = [$dimension];
        
        $stmt = sqlsrv_query($this->conn, $sql, $params);

        if ($stmt === false) {
            throw new Exception("Error en la consulta de Centros de Costo. Detalles: " . print_r(sqlsrv_errors(), true));
        }

        $results = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $results[] = $row;
        }

        sqlsrv_free_stmt($stmt);
        return $results;
    }

    /**
     * Obtiene el nombre de un Centro de Costo específico por su código.
     * @param string $ocrCode El código del centro de costo.
     * @return string El nombre del centro de costo o 'Desconocido'.
     * @throws Exception
     */
    public function getCostCenterNameByCode($ocrCode) {
        $sql = "SELECT OcrName FROM OOCR WHERE OcrCode = ?";
        $params = [$ocrCode];
        
        $stmt = sqlsrv_query($this->conn, $sql, $params);

        if ($stmt === false) {
            return 'Error en consulta';
        }

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);

        return $row['OcrName'] ?? 'Desconocido';
    }

    /**
     * Obtiene los nombres de MÚLTIPLES Centros de Costo en una sola consulta.
     * @param array $ocrCodes Un array de códigos de centro de costo.
     * @return array Un array asociativo [OcrCode => OcrName].
     * @throws Exception
     */
    public function getCostCenterNamesByCodes(array $ocrCodes) {
        if (empty($ocrCodes)) {
            return [];
        }

        // Crear los placeholders (?) para la cláusula IN
        $placeholders = implode(',', array_fill(0, count($ocrCodes), '?'));
        
        // La consulta ahora usa IN (...) para buscar todos los códigos a la vez
        $sql = "SELECT OcrCode, OcrName FROM OOCR WHERE OcrCode IN ($placeholders)";
        $params = $ocrCodes;
        
        $stmt = sqlsrv_query($this->conn, $sql, $params);

        if ($stmt === false) {
            return []; // Devolver array vacío en caso de error para no romper el frontend
        }

        $results = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $results[$row['OcrCode']] = $row['OcrName'];
        }
        sqlsrv_free_stmt($stmt);

        return $results;
    }

    // En el futuro, puedes añadir más funciones aquí, como:
    // public function searchProviders($query, $limit) { ... }

    public function __destruct() {
        if ($this->conn) {
            sqlsrv_close($this->conn);
        }
    }
}
