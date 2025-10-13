<?php
// config/sap.php
return [
    'server' => '122.8.179.122',
    'username' => 'sa',
    'password' => 'r4nD8q8GLaMk$',
    'port' => 1433,
    'companies' => [
        // Las claves aquí (PROQUIMA, UNHESA) deben coincidir con los 'value' del dropdown en crear_solicitud.php
        'PROQUIMA' => ['database' => 'SBOPROQUIMA'],
        'UNHESA'   => ['database' => 'UNHESA'],
    ],
    // El valor por defecto aquí debe ser una de las claves de arriba
    'default_company' => 'PROQUIMA'
];
