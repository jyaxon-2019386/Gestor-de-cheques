<?php
// ajax/get_solicitud_details.php (VERSIÓN FINAL CON MAPEO DE CUENTAS DE CHEQUES)

require_once '../includes/functions.php';
proteger_pagina();

// ---------------------------------------------------------------------------------
// PASO 1: VALIDAR LA ENTRADA (ID DE SOLICITUD)
// ---------------------------------------------------------------------------------
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo "<div class='alert alert-danger'>ID de solicitud no válido.</div>";
    exit();
}
$solicitud_id = intval($_GET['id']);
$conexion = require_once '../config/database.php';

// ---------------------------------------------------------------------------------
// PASO 2: OBTENER LOS DATOS PRINCIPALES DE LA SOLICITUD
// ---------------------------------------------------------------------------------
// Se obtiene la solicitud primero para saber a qué empresa ('empresa_db') pertenece.
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

// ---------------------------------------------------------------------------------
// PASO 3: CARGAR MAPA DE BANCOS BASADO EN LA EMPRESA DE LA SOLICITUD
// ---------------------------------------------------------------------------------
$company_for_this_request = $solicitud['empresa_db'];
$bancos_map = [];

// Usar la empresa de la solicitud para construir la clave de caché correcta
$cache_key_bancos = 'sap_bank_accounts_' . $company_for_this_request;

// Si existe la caché en la sesión, crear un mapa [GLAccount => AccountName] para una búsqueda rápida
if (isset($_SESSION[$cache_key_bancos])) {
    $bancos_map = array_column($_SESSION[$cache_key_bancos], 'AccountName', 'GLAccount');
}

// ---------------------------------------------------------------------------------
// PASO 4: OBTENER LOS DETALLES SECUNDARIOS (LÍNEAS DE CUENTA)
// ---------------------------------------------------------------------------------
$sql_cuentas = "SELECT * FROM pagos_pendientes_cuentas WHERE pago_id = ?";
$stmt_cuentas = $conexion->prepare($sql_cuentas);
$stmt_cuentas->bind_param("i", $solicitud_id);
$stmt_cuentas->execute();
$cuentas = $stmt_cuentas->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_cuentas->close();

function get_status_badge($estado) { 
    $color = 'secondary';
    if (strpos($estado, 'Pendiente') !== false) $color = 'warning text-dark';
    if ($estado === 'Aprobado') $color = 'success';
    if ($estado === 'Rechazado') $color = 'danger';
    return "<span class='badge bg-{$color}'>{$estado}</span>";
}
?>

<!-- ================================================================== -->
<!-- PASO 5: RENDERIZAR EL HTML DEL MODAL CON LA TRADUCCIÓN APLICADA    -->
<!-- ================================================================== -->
<div class="container-fluid">
    <!-- SECCIÓN 1: RESUMEN GENERAL -->
    <div class="row pb-2 mb-3 border-bottom">
        <div class="col-sm-6">
            <h5 class="mb-1">Solicitud de Pago #<?php echo $solicitud['id']; ?></h5>
            <span class="text-muted">De: <?php echo htmlspecialchars($solicitud['departamento_solicitante']); ?></span>
        </div>
        <div class="col-sm-6 text-sm-end">
            <h6 class="mb-1">Estado Actual</h6>
            <?php echo get_status_badge($solicitud['estado']); ?>
        </div>
    </div>

    <!-- SECCIÓN 2: DETALLES DEL PAGO -->
    <h6 class="text-primary"><i class="bi bi-wallet2 me-2"></i>Detalles del Pago</h6>
    <div class="row mb-3">
        <div class="col-md-6 mb-2"><strong><i class="bi bi-person-badge me-2 text-muted"></i>Beneficiario:</strong><br><?php echo htmlspecialchars($solicitud['CardName']); ?></div>
        <div class="col-md-6 mb-2"><strong><i class="bi bi-cash-coin me-2 text-muted"></i>Monto Total:</strong><br><span class="fw-bold fs-5 text-success"><?php echo ($solicitud['DocCurrency'] === 'USD' ? '$' : 'Q'); ?> <?php echo number_format($solicitud['total_pagar'], 2); ?></span> <small class="text-muted">(<?php echo $solicitud['DocCurrency']; ?>)</small></div>
        <div class="col-md-6 mb-2">
            <strong><i class="bi bi-bank2 me-2 text-muted"></i>Cuenta de Cheques:</strong><br>
            <?php 
            // PUNTO CLAVE: Se busca el código de la cuenta de cheques en el mapa.
            // Si se encuentra, se muestra el nombre legible. Si no, se muestra el código original como respaldo.
            echo htmlspecialchars($bancos_map[$solicitud['CheckAccount']] ?? $solicitud['CheckAccount']); 
            ?>
        </div>
        <div class="col-md-6 mb-2"><strong><i class="bi bi-building me-2 text-muted"></i>Empresa (DB):</strong><br><?php echo htmlspecialchars($solicitud['empresa_db']); ?></div>
    </div>
    <hr>

    <!-- SECCIÓN 3: JUSTIFICACIÓN Y PARTIDAS -->
    <h6 class="text-primary"><i class="bi bi-file-earmark-text me-2"></i>Justificación y Partidas Contables</h6>
    <div class="row mb-3">
        <div class="col-12 mb-2"><strong><i class="bi bi-body-text me-2 text-muted"></i>Concepto / Observaciones Generales:</strong><div class="p-2 bg-light rounded fst-italic mt-1">"<?php echo nl2br(htmlspecialchars($solicitud['Remarks'])); ?>"</div></div>
        <div class="col-12 mb-3"><strong><i class="bi bi-journal-bookmark-fill me-2 text-muted"></i>Justificación para Asiento Contable (Finanzas):</strong><div class="p-2 bg-light rounded fst-italic mt-1"><?php echo nl2br(htmlspecialchars($solicitud['JournalRemarks'] ?: 'Sin justificación contable específica.')); ?></div></div>
        <div class="col-12"><strong><i class="bi bi-journal-text me-2 text-muted"></i>Partidas Contables:</strong>
            <table class="table table-sm table-bordered mt-1">
                <thead class="table-light"><tr><th>Código de Cuenta</th><th>Descripción</th><th class="text-end">Monto</th></tr></thead>
                <tbody>
                    <?php foreach ($cuentas as $cuenta): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cuenta['AccountCode']); ?></td>
                            <td><?php echo htmlspecialchars($cuenta['Decription']); ?></td>
                            <td class="text-end"><?php echo number_format($cuenta['SumPaid'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <hr>

    <!-- SECCIÓN 4: TRAZABILIDAD Y APROBACIÓN -->
    <h6 class="text-primary"><i class="bi bi-signpost-split me-2"></i>Trazabilidad</h6>
    <div class="row">
        <div class="col-md-6"><strong><i class="bi bi-person-circle me-2 text-muted"></i>Creado por:</strong><br><?php echo htmlspecialchars($solicitud['creador_nombre']); ?> <small class="text-muted">(<?php echo htmlspecialchars($solicitud['creador_email']); ?>)</small></div>
        <div class="col-md-6"><strong><i class="bi bi-calendar-event me-2 text-muted"></i>Fecha de Creación:</strong><br><?php echo date("d/m/Y H:i", strtotime($solicitud['fecha_creacion'])); ?></div>
        
        <?php if (!empty($solicitud['aprobador_actual_nombre'])): ?>
            <div class="col-md-12 mt-2"><strong><i class="bi bi-person-check text-warning me-2"></i>Pendiente de Aprobación por:</strong><br><span class="fw-bold"><?php echo htmlspecialchars($solicitud['aprobador_actual_nombre']); ?></span></div>
        <?php endif; ?>

        <?php if ($solicitud['estado'] === 'Aprobado' && !empty($solicitud['fecha_aprobacion'])): ?>
            <div class="col-md-12 mt-2"><strong><i class="bi bi-check-circle-fill text-success me-2"></i>Fecha de Aprobación Final:</strong><br><?php echo date("d/m/Y H:i", strtotime($solicitud['fecha_aprobacion'])); ?></div>
        <?php endif; ?>
    </div>

    <!-- SECCIÓN 5: MOTIVO DE RECHAZO (SI APLICA) -->
    <?php if ($solicitud['estado'] === 'Rechazado' && !empty($solicitud['motivo_rechazo'])): ?>
        <div class="alert alert-danger mt-3"><h6 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Motivo del Rechazo</h6><p class="mb-0"><?php echo nl2br(htmlspecialchars($solicitud['motivo_rechazo'])); ?></p></div>
    <?php endif; ?>
</div>