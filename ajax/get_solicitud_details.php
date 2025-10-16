<?php
// ajax/get_solicitud_details.php
require_once '../includes/functions.php';
proteger_pagina();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo "<div class='alert alert-danger'>ID de solicitud no válido.</div>";
    exit();
}

$solicitud_id = intval($_GET['id']);
$conexion = require_once '../config/database.php';

// Obtener detalles principales de la solicitud
$sql_main = "SELECT p.*, u.nombre_usuario, u.email 
             FROM pagos_pendientes p 
             JOIN usuarios u ON p.usuario_id = u.id 
             WHERE p.id = ?";
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

// Obtener las líneas de cuenta de la solicitud
$sql_cuentas = "SELECT * FROM pagos_pendientes_cuentas WHERE pago_id = ?";
$stmt_cuentas = $conexion->prepare($sql_cuentas);
$stmt_cuentas->bind_param("i", $solicitud_id);
$stmt_cuentas->execute();
$cuentas = $stmt_cuentas->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_cuentas->close();

// Generar el HTML para el cuerpo del modal
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-6">
            <h6><i class="bi bi-person-circle me-2"></i>Solicitante</h6>
            <p class="text-muted"><?php echo htmlspecialchars($solicitud['nombre_usuario']); ?> (<?php echo htmlspecialchars($solicitud['email']); ?>)</p>
        </div>
        <div class="col-md-6">
            <h6><i class="bi bi-calendar-event me-2"></i>Fecha de Solicitud</h6>
            <p class="text-muted"><?php echo date("d/m/Y H:i", strtotime($solicitud['fecha_creacion'])); ?></p>
        </div>
    </div>
    <hr>
    <div class="row">
        <div class="col-md-6">
            <h6><i class="bi bi-person-badge me-2"></i>Beneficiario</h6>
            <p class="text-muted"><?php echo htmlspecialchars($solicitud['CardName']); ?></p>
        </div>
        <div class="col-md-6">
            <h6><i class="bi bi-cash-coin me-2"></i>Monto Total</h6>
            <p class="text-muted fw-bold fs-5">
                <?php echo ($solicitud['DocCurrency'] === 'USD' ? '$' : 'Q'); ?>
                <?php echo number_format($solicitud['total_pagar'], 2); ?>
            </p>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <h6><i class="bi bi-body-text me-2"></i>Concepto / Observaciones</h6>
            <p class="text-muted fst-italic">"<?php echo nl2br(htmlspecialchars($solicitud['Remarks'])); ?>"</p>
        </div>
    </div>
    <hr>
    <h6><i class="bi bi-journal-text me-2"></i>Partidas Contables</h6>
    <table class="table table-sm table-bordered">
        <thead class="table-light">
            <tr>
                <th>Código de Cuenta</th>
                <th>Descripción</th>
                <th class="text-end">Monto</th>
            </tr>
        </thead>
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

    <?php if ($solicitud['estado'] === 'Rechazado' && !empty($solicitud['motivo_rechazo'])): ?>
    <hr>
    <div class="alert alert-danger">
        <h6 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Motivo del Rechazo</h6>
        <p><?php echo nl2br(htmlspecialchars($solicitud['motivo_rechazo'])); ?></p>
    </div>
    <?php endif; ?>
</div>