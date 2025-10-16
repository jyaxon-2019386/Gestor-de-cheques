<?php
// Nombre del archivo: finanzas_sap.php
require_once 'includes/functions.php';
proteger_pagina();

// Verificar que el usuario tiene rol de finanzas o es admin
if (!in_array($_SESSION['rol'], ['finanzas', 'admin'])) {
    die("No tienes permiso para acceder a esta página.");
}

require_once 'templates/layouts/header.php';
$conexion = require_once 'config/database.php';

// --- LÓGICA DE FILTRADO Y CONSULTA (sin cambios) ---
$filtro_q = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtro_fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$filtro_fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
$sql_pagos = "SELECT p.*, u.nombre_usuario 
              FROM pagos_pendientes p
              JOIN usuarios u ON p.usuario_id = u.id
              WHERE p.estado = 'Aprobado'";

$param_types = ""; $param_values = [];
if (!empty($filtro_q)) {
    $sql_pagos .= " AND (p.id = ? OR p.CardName LIKE ?)";
    $param_types .= "is";
    $param_values[] = is_numeric($filtro_q) ? intval($filtro_q) : 0;
    $param_values[] = "%$filtro_q%";
}
if (!empty($filtro_fecha_inicio)) {
    $sql_pagos .= " AND DATE(p.fecha_aprobacion) >= ?";
    $param_types .= "s";
    $param_values[] = $filtro_fecha_inicio;
}
if (!empty($filtro_fecha_fin)) {
    $sql_pagos .= " AND DATE(p.fecha_aprobacion) <= ?";
    $param_types .= "s";
    $param_values[] = $filtro_fecha_fin;
}
$sql_pagos .= " ORDER BY p.fecha_aprobacion ASC";

$stmt_pagos = $conexion->prepare($sql_pagos);
if (!empty($param_values)) { $stmt_pagos->bind_param($param_types, ...$param_values); }
$stmt_pagos->execute();
$result = $stmt_pagos->get_result();
$pagos_aprobados = $result->fetch_all(MYSQLI_ASSOC);
$stmt_pagos->close();
?>

<!-- VISTA HTML DE LA PÁGINA (sin cambios) -->
<div class="mb-5">
    <?php generar_breadcrumbs(); ?>
    <h1 class="fs-2 text-white mt-2">Gestión de Pagos a SAP</h1>
    <p class="text-muted">Procesa las solicitudes aprobadas para su registro final en SAP.</p>
</div>
<div class="filter-bar mb-4">
    <form action="finanzas_sap.php" method="GET" class="row g-3 align-items-end">
        <div class="col-lg-4"><label class="form-label">Buscar por ID o beneficiario</label><input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($filtro_q); ?>"></div>
        <div class="col-lg-3"><label class="form-label">Desde Fecha Aprobación</label><input type="date" class="form-control" name="fecha_inicio" value="<?php echo htmlspecialchars($filtro_fecha_inicio); ?>"></div>
        <div class="col-lg-3"><label class="form-label">Hasta Fecha Aprobación</label><input type="date" class="form-control" name="fecha_fin" value="<?php echo htmlspecialchars($filtro_fecha_fin); ?>"></div>
        <div class="col-lg-2"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-2"></i>Filtrar</button></div>
    </form>
</div>
<div class="payment-list">
    <?php if (count($pagos_aprobados) > 0): ?>
        <?php foreach($pagos_aprobados as $solicitud): ?>
            <div class="card approval-card" id="solicitud-finanzas-<?php echo $solicitud['id']; ?>">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-3"><div class="fw-bold">Solicitud #<?php echo $solicitud['id']; ?></div><small class="text-muted">Solicitante: <?php echo htmlspecialchars($solicitud['nombre_usuario']); ?></small></div>
                    <div class="col-lg-4"><div class="fw-bold"><?php echo htmlspecialchars($solicitud['CardName']); ?></div><small class="text-muted"><?php echo htmlspecialchars($solicitud['Remarks']); ?></small></div>
                    <div class="col-lg-2 text-lg-center"><?php $currencySymbol = ($solicitud['DocCurrency'] === 'USD') ? '$' : 'Q'; ?><div class="fw-bold fs-5"><?php echo $currencySymbol; ?> <?php echo number_format($solicitud['total_pagar'], 2); ?></div><small class="text-muted">Aprobado: <?php echo date("d/m/Y", strtotime($solicitud['fecha_aprobacion'])); ?></small></div>
                    <div class="col-lg-3 text-lg-end approval-actions">
                        <!-- Las clases .btn-enviar-sap y .btn-rechazar-finanzas son detectadas por el JS del footer -->
                        <button class="btn btn-sm btn-success flex-grow-1 btn-enviar-sap" data-id="<?php echo $solicitud['id']; ?>"><i class="bi bi-send-fill"></i> Enviar a SAP</button>
                        <button class="btn btn-sm btn-danger btn-rechazar-finanzas" data-id="<?php echo $solicitud['id']; ?>"><i class="bi bi-x-circle-fill"></i> Rechazar</button>
                        <button class="btn btn-sm btn-outline-secondary btn-ver-detalles" data-id="<?php echo $solicitud['id']; ?>" title="Ver Detalles">
        <i class="bi bi-eye"></i>
    </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card"><div class="card-body text-center p-5"><i class="bi bi-inbox-fill fs-1 text-muted"></i><h4 class="mt-3">Bandeja Vacía</h4><p class="text-muted">No hay solicitudes aprobadas pendientes de procesar.</p></div></div>
    <?php endif; ?>
</div>

<?php 
// El footer contiene toda la lógica JavaScript. Ya no se necesita un script local.
require_once 'templates/layouts/footer.php'; 
?>