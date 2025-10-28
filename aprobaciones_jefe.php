<?php
// Nombre del archivo: aprobaciones_jefe.php
require_once 'includes/functions.php';
proteger_pagina();

if (!puede_aprobar()) {
    header('Location: index.php');
    exit();
}

require_once 'templates/layouts/header.php';
$conexion = require_once 'config/database.php';

// --- LÓGICA DE FILTRADO Y CONSULTA (sin cambios) ---
$filtro_q = isset($_GET['q']) ? trim($_GET['q']) : '';
$sql_bandeja = "SELECT p.*, u.nombre_usuario 
                FROM pagos_pendientes p
                JOIN usuarios u ON p.usuario_id = u.id
                WHERE p.estado IN (
                    'Pendiente de Jefe', 
                    'Pendiente Jefe Bodega', 
                    'Pendiente Gerente Bodega', 
                    'Pendiente Gerente General'
                )";

$param_types = ""; $param_values = [];
if (!es_admin()) {
    $sql_bandeja .= " AND p.aprobador_actual_id = ?";
    $param_types .= "i";
    $param_values[] = $_SESSION['usuario_id'];
}
if (!empty($filtro_q)) {
    $sql_bandeja .= " AND (p.id = ? OR u.nombre_usuario LIKE ? OR p.CardName LIKE ?)";
    $param_types .= "iss";
    $id_query = is_numeric($filtro_q) ? intval($filtro_q) : 0;
    $like_query = '%' . $filtro_q . '%';
    $param_values[] = $id_query;
    $param_values[] = $like_query;
    $param_values[] = $like_query;
}
$sql_bandeja .= " ORDER BY p.fecha_creacion ASC";

$stmt_bandeja = $conexion->prepare($sql_bandeja);
if (!empty($param_values)) {
    $stmt_bandeja->bind_param($param_types, ...$param_values);
}
$stmt_bandeja->execute();
$result = $stmt_bandeja->get_result();
$bandeja_entrada = $result->fetch_all(MYSQLI_ASSOC);
$stmt_bandeja->close();
?>

<!-- ========================================================== -->
<!-- VISTA HTML DE LA PÁGINA (sin cambios) -->
<!-- ========================================================== -->

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <?php generar_breadcrumbs(); ?>
        <h1 class="fs-2 text-white mt-2">Bandeja de Aprobaciones</h1>
        <p class="text-muted">Solicitudes pendientes de tu revisión.</p>
    </div>
    <span class="badge rounded-pill text-bg-danger fs-6"><?php echo count($bandeja_entrada); ?> Pendientes</span>
</div>

<div class="filter-bar mb-4">
    <form action="aprobaciones_jefe.php" method="GET">
        <div class="input-group">
            <input type="text" class="form-control" name="q" placeholder="Buscar por ID, solicitante o beneficiario..." value="<?php echo htmlspecialchars($filtro_q); ?>">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search me-2"></i>Buscar</button>
        </div>
    </form>
</div>

<div class="approvals-list">
    <?php if (count($bandeja_entrada) > 0): ?>
        <?php foreach($bandeja_entrada as $solicitud): ?>
            <div class="card approval-card" id="solicitud-<?php echo $solicitud['id']; ?>">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-3 d-flex align-items-center gap-3"><div class="user-avatar"><span class="avatar-initials"><?php echo strtoupper(substr($solicitud['nombre_usuario'], 0, 2)); ?></span></div><div><div class="fw-bold"><?php echo htmlspecialchars($solicitud['nombre_usuario']); ?></div><small class="text-muted">Solicitante</small></div></div>
                    <div class="col-lg-4"><h5 class="card-title mb-1">Beneficiario: <?php echo htmlspecialchars($solicitud['CardName']); ?></h5><p class="mb-0 text-muted small">ID: #<?php echo $solicitud['id']; ?> | <span class="fw-bold"><?php echo htmlspecialchars($solicitud['estado']); ?></span></p></div>
                    <div class="col-lg-2 text-lg-center"><?php $currencySymbol = ($solicitud['DocCurrency'] === 'USD') ? '$' : 'Q'; ?><div class="fw-bold fs-5"><?php echo $currencySymbol; ?> <?php echo number_format($solicitud['total_pagar'], 2); ?></div><small class="text-muted">Solicitado: <?php echo date("d/m/Y", strtotime($solicitud['fecha_creacion'])); ?></small></div>
                    <div class="col-lg-3 text-lg-end approval-actions">
                        <button class="btn btn-sm btn-success flex-grow-1 btn-accion-aprobar" data-id="<?php echo $solicitud['id']; ?>"><i class="bi bi-check-lg"></i> Aprobar</button>
                        <button class="btn btn-sm btn-danger flex-grow-1 btn-accion-rechazar" data-id="<?php echo $solicitud['id']; ?>"><i class="bi bi-x-lg"></i> Rechazar</button>
                         <button class="btn btn-sm btn-outline-secondary btn-ver-detalles" data-id="<?php echo $solicitud['id']; ?>" title="Ver Detalles">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card"><div class="card-body text-center p-5"><i class="bi bi-check2-circle fs-1 text-muted"></i><h4 class="mt-3">¡Todo en orden!</h4><p class="text-muted">No tienes solicitudes pendientes en tu bandeja.</p></div></div>
    <?php endif; ?>
</div>

<?php
require_once 'templates/layouts/footer.php'; 
?>