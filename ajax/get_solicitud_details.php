<?php
require_once '../includes/functions.php';
require_once '../includes/sap_service_layer.php';
proteger_pagina();

// ---------------------------------------------------------------------------------
// PASO 1: VALIDAR LA ENTRADA
// ---------------------------------------------------------------------------------
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo "<div class='alert alert-danger'>ID de solicitud no válido.</div>";
    exit();
}
$solicitud_id = intval($_GET['id']);
$conexion = require_once '../config/database.php';

$sql_main = "SELECT p.*, creador.nombre_usuario AS creador_nombre, creador.email AS creador_email, aprobador.nombre_usuario AS aprobador_actual_nombre FROM pagos_pendientes p JOIN usuarios creador ON p.usuario_id = creador.id LEFT JOIN usuarios aprobador ON p.aprobador_actual_id = aprobador.id WHERE p.id = ?";
$stmt_main = $conexion->prepare($sql_main);
$stmt_main->bind_param("i", $solicitud_id);
$stmt_main->execute();
$solicitud = $stmt_main->get_result()->fetch_assoc();
$stmt_main->close();

if (!$solicitud) {
    http_response_code(404);
    echo "<div class='alert alert-warning'>No se encontró la solicitud con ID #{$solicitud_id}.</div>";
    exit();
}

$company_for_this_request = $solicitud['empresa_db'];

$bancos_map = [];
$cache_key_bancos = 'sap_bank_accounts_' . $company_for_this_request;
if (isset($_SESSION[$cache_key_bancos]) && (time() - ($_SESSION[$cache_key_bancos . '_timestamp'] ?? 0) < 3600)) {
    $bancos_map = array_column($_SESSION[$cache_key_bancos], 'AccountName', 'GLAccount');
} else {
    try {
        $sap_bancos = new SapServiceLayer($company_for_this_request);
        $sap_bancos->login();
        $query_params = "?\$select=GLAccount,AccNo,AccountName";
        $bank_data = $sap_bancos->get("HouseBankAccounts" . $query_params);
        $sap_bancos->logout();
        if (isset($bank_data['value'])) {
            $formatted_accounts = [];
            foreach ($bank_data['value'] as $account) {
                $display_name = $account['AccountName'] . ' # ' . $account['AccNo'];
                $bancos_map[$account['GLAccount']] = $display_name;
                $formatted_accounts[] = ["GLAccount" => $account['GLAccount'], "AccountName" => $display_name];
            }
            $_SESSION[$cache_key_bancos] = $formatted_accounts;
            $_SESSION[$cache_key_bancos . '_timestamp'] = time();
        }
    } catch (Exception $e) { error_log("Error al obtener cuentas bancarias de SAP: " . $e->getMessage()); }
}

$cuentas_map = [];
$cache_key_cuentas = 'sap_predefined_accounts_' . $company_for_this_request;
if (isset($_SESSION[$cache_key_cuentas]) && (time() - ($_SESSION[$cache_key_cuentas . '_timestamp'] ?? 0) < 3600)) {
    $cuentas_map = array_column($_SESSION[$cache_key_cuentas], 'Name', 'Code');
} else {
    try {
        $sap_cuentas = new SapServiceLayer($company_for_this_request);
        $allowed_codes_by_company = [
            'TEST_PROQUIMA_ZZZ' => ['_SYS00000000026', '_SYS00000000113', '_SYS00000000139', '_SYS00000000144', '_SYS00000000148'],
            'TEST_UNHESA_ZZZ'   => ['_SYS00000000070', '_SYS00000000134', '_SYS00000000137', '_SYS00000000153', '_SYS00000002104']
        ];
        $allowed_account_codes = $allowed_codes_by_company[$company_for_this_request] ?? [];
        if (!empty($allowed_account_codes)) {
            $sap_cuentas->login();
            $filter_parts = array_map(fn($code) => "Code eq '$code'", $allowed_account_codes);
            $filter_string = implode(' or ', $filter_parts);
            $query_params = "?\$select=Code,Name&\$filter=" . urlencode($filter_string);
            $accounts_data = $sap_cuentas->get("ChartOfAccounts" . $query_params);
            $sap_cuentas->logout();
            if (isset($accounts_data['value'])) {
                $cuentas_map = array_column($accounts_data['value'], 'Name', 'Code');
                $_SESSION[$cache_key_cuentas] = $accounts_data['value'];
                $_SESSION[$cache_key_cuentas . '_timestamp'] = time();
            }
        }
    } catch (Exception $e) { error_log("Error al obtener cuentas contables de SAP: " . $e->getMessage()); }
}

$sql_cuentas = "SELECT * FROM pagos_pendientes_cuentas WHERE pago_id = ?";
$stmt_cuentas = $conexion->prepare($sql_cuentas);
$stmt_cuentas->bind_param("i", $solicitud_id);
$stmt_cuentas->execute();
$cuentas = $stmt_cuentas->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_cuentas->close();

function get_status_badge($estado) { 
    $color_map = [
        'Aprobado' => 'primary',
        'Rechazado' => 'danger',
        'ProcesadoSAP' => 'success',
    ];
    // Por defecto, usar 'warning' para cualquier estado que contenga 'Pendiente'
    $color = $color_map[$estado] ?? 'warning';
    $text_color = ($color === 'warning') ? 'dark' : 'white';
    return "<span class='badge text-bg-{$color} fs-6 px-3 py-2'>{$estado}</span>";
}
?>

<div class="p-4">
    <!-- SECCIÓN 1: CABECERA -->
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h5 class="mb-0">Solicitud de Pago #<?php echo $solicitud['id']; ?></h5>
            <small class="text-muted">Del departamento de <?php echo htmlspecialchars($solicitud['departamento_solicitante']); ?></small>
        </div>
        <?php echo get_status_badge($solicitud['estado']); ?>
    </div>

    <!-- SECCIÓN 2: DETALLES PRINCIPALES -->
    <div class="row mb-4">
        <div class="col-6 col-md-3 mb-3">
            <small class="text-muted d-block">Monto Total</small>
            <span class="fw-bold fs-5"><?php echo ($solicitud['DocCurrency'] === 'USD' ? '$' : 'Q'); ?> <?php echo number_format($solicitud['total_pagar'], 2); ?></span>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <small class="text-muted d-block">Empresa (DB)</small>
            <span class="fw-bold"><?php echo htmlspecialchars($solicitud['empresa_db']); ?></span>
        </div>
        <div class="col-12 col-md-6 mb-3">
            <small class="text-muted d-block">Beneficiario</small>
            <span class="fw-bold"><?php echo htmlspecialchars($solicitud['CardName']); ?></span>
        </div>
        <div class="col-12 col-md-6">
            <small class="text-muted d-block">Cuenta de Cheques</small>
            <span class="fw-bold"><?php echo htmlspecialchars($bancos_map[$solicitud['CheckAccount']] ?? $solicitud['CheckAccount']); ?></span>
        </div>
    </div>

    <!-- SECCIÓN 3: JUSTIFICACIÓN Y PARTIDAS -->
    <h6 class="mb-3">Justificación y Partidas Contables</h6>
    <div class="mb-3">
        <small class="text-muted d-block">Concepto / Observaciones Generales</small>
        <blockquote class="mt-1 mb-0 ps-2" style="font-size: 0.9em;"><?php echo nl2br(htmlspecialchars($solicitud['Remarks'])); ?></blockquote>
    </div>
    <div class="mb-4">
        <small class="text-muted d-block">Justificación para Asiento Contable (Finanzas)</small>
        <p class="ps-2 mt-1 mb-0" style="font-size: 0.9em;"><?php echo nl2br(htmlspecialchars($solicitud['JournalRemarks'] ?: 'N/A')); ?></p>
    </div>
    
    <div class="mb-4">
        <small class="text-muted d-block mb-2">Partidas Contables</small>
        <table class="table">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="fw-semibold">Cuenta Contable</th>
                    <th scope="col" class="fw-semibold">Descripción</th>
                    <th scope="col" class="fw-semibold text-end">Monto</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cuentas as $cuenta): ?>
                    <tr style="border-bottom: 1px solid #f0f0f0;">
                        <td><?php echo htmlspecialchars($cuenta['Decription']); ?></td>
                        <td><?php echo htmlspecialchars($cuentas_map[$cuenta['AccountCode']] ?? $cuenta['Decription']); ?></td>
                        <td class="text-end"><?php echo number_format($cuenta['SumPaid'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- SECCIÓN 4: TRAZABILIDAD Y APROBACIÓN -->
    <h6 class="mb-3">Trazabilidad</h6>
    <div class="row">
        <div class="col-md-6 mb-3">
            <small class="text-muted d-block">Creado por</small>
            <span><?php echo htmlspecialchars($solicitud['creador_nombre']); ?> (<?php echo htmlspecialchars($solicitud['creador_email']); ?>)</span>
        </div>
        <div class="col-md-6 mb-3">
            <small class="text-muted d-block">Fecha de Creación</small>
            <span><?php echo date("d/m/Y H:i", strtotime($solicitud['fecha_creacion'])); ?></span>
        </div>
        <?php if (!empty($solicitud['aprobador_actual_nombre'])): ?>
            <div class="col-12 mb-3">
                <small class="text-muted d-block">Pendiente de Aprobación por</small>
                <span class="fw-bold"><?php echo htmlspecialchars($solicitud['aprobador_actual_nombre']); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($solicitud['estado'] === 'Aprobado' && !empty($solicitud['fecha_aprobacion'])): ?>
            <div class="col-12 mb-3">
                <small class="text-muted d-block">Fecha de Aprobación Final</small>
                <span><?php echo date("d/m/Y H:i", strtotime($solicitud['fecha_aprobacion'])); ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- SECCIÓN 5: MOTIVO DE RECHAZO (SI APLICA) -->
    <?php if ($solicitud['estado'] === 'Rechazado' && !empty($solicitud['motivo_rechazo'])): ?>
        <div class="alert alert-danger mt-3">
            <h6 class="alert-heading">Motivo del Rechazo</h6>
            <p class="mb-0"><?php echo nl2br(htmlspecialchars($solicitud['motivo_rechazo'])); ?></p>
        </div>
    <?php endif; ?>
</div>