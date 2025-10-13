<?php
// trazabilidad.php - VERSIÓN REDISEÑO FINAL
require_once 'includes/functions.php';
proteger_pagina();
require_once 'templates/layouts/header.php';
require_once 'config/database.php';

// --- LÓGICA DE FILTRADO Y CONSULTA ---
$filtro_estado = isset($_GET['filtro']) ? $_GET['filtro'] : '';

// Contadores para los botones de filtro
$sql_counts = "SELECT 
    SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'Aprobado' THEN 1 ELSE 0 END) as aprobados,
    SUM(CASE WHEN estado = 'Pagado' THEN 1 ELSE 0 END) as pagados,
    SUM(CASE WHEN estado = 'Rechazado' THEN 1 ELSE 0 END) as rechazados
FROM solicitud_cheques";
if (!es_admin() && $_SESSION['rol'] !== 'finanzas') {
    $sql_counts .= " WHERE usuario_id = " . $_SESSION['usuario_id'];
}
$counts = $conexion->query($sql_counts)->fetch_assoc();
$pendientes = $counts['pendientes'] ?? 0;
$aprobados = $counts['aprobados'] ?? 0;
$pagados = $counts['pagados'] ?? 0;
$rechazados = $counts['rechazados'] ?? 0;

// Consulta principal de solicitudes
$sql_solicitudes = "SELECT s.*, u.nombre_usuario FROM solicitud_cheques s JOIN usuarios u ON s.usuario_id = u.id";
$where_conditions = [];
$param_types = "";
$param_values = [];

// Construir condiciones WHERE
if (!es_admin() && $_SESSION['rol'] !== 'finanzas') {
    $where_conditions[] = "s.usuario_id = ?";
    $param_types .= "i";
    $param_values[] = $_SESSION['usuario_id'];
}

// Filtro por estado
if (!empty($filtro_estado)) {
    $where_conditions[] = "s.estado = ?";
    $param_types .= "s";
    $param_values[] = $filtro_estado;
}

if (!empty($where_conditions)) {
    $sql_solicitudes .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql_solicitudes .= " ORDER BY s.fecha_solicitud DESC";

$stmt_solicitudes = $conexion->prepare($sql_solicitudes);
if (!empty($param_values)) {
    $stmt_solicitudes->bind_param($param_types, ...$param_values);
}
$stmt_solicitudes->execute();
$resultado_solicitudes = $stmt_solicitudes->get_result();
?>

<!-- Encabezado de la Página -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <?php generar_breadcrumbs(); ?>
        <h1 class="fs-2 text-white mt-2">Trazabilidad Global</h1>
    </div>
    <a href="#" class="btn btn-light"><i class="bi bi-download me-2"></i>Exportar</a>
</div>

<!-- NUEVOS BOTONES DE FILTRO DE ESTADO -->
<ul class="nav nav-pills mb-4 status-filter-nav">
    <li class="nav-item">
        <a class="nav-link <?php echo empty($filtro_estado) ? 'active filter-all' : ''; ?>" href="trazabilidad.php">Todos (<?php echo $pendientes+$aprobados+$pagados+$rechazados; ?>)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link filter-pending <?php echo ($filtro_estado == 'Pendiente') ? 'active' : ''; ?>" href="trazabilidad.php?filtro=Pendiente">Pendientes (<?php echo $pendientes; ?>)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link filter-approved <?php echo ($filtro_estado == 'Aprobado') ? 'active' : ''; ?>" href="trazabilidad.php?filtro=Aprobado">Aprobadas (<?php echo $aprobados; ?>)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link filter-paid <?php echo ($filtro_estado == 'Pagado') ? 'active' : ''; ?>" href="trazabilidad.php?filtro=Pagado">Pagadas (<?php echo $pagados; ?>)</a>
    </li>
    <li class="nav-item">
        <a class="nav-link filter-rejected <?php echo ($filtro_estado == 'Rechazado') ? 'active' : ''; ?>" href="trazabilidad.php?filtro=Rechazado">Rechazadas (<?php echo $rechazados; ?>)</a>
    </li>
</ul>

<!-- LÍNEA DE TIEMPO REDISEÑADA -->
<div class="timeline">
    <?php if ($resultado_solicitudes->num_rows > 0): ?>
        <?php while ($solicitud = $resultado_solicitudes->fetch_assoc()): ?>
            <?php
            // Lógica para determinar color e icono
            $icon_class = 'bi-hourglass-split'; 
            $bg_color_class = 'warning';
            
            switch ($solicitud['estado']) {
                case 'Aprobado':
                    $icon_class = 'bi-check2';
                    $bg_color_class = 'success';
                    break;
                case 'Pagado':
                    $icon_class = 'bi-archive';
                    $bg_color_class = 'info';
                    break;
                case 'Rechazado':
                    $icon_class = 'bi-x';
                    $bg_color_class = 'danger';
                    break;
            }
            ?>
            <div class="timeline-item">
                <div class="timeline-icon-wrapper">
                    <div class="timeline-icon text-<?php echo $bg_color_class; ?> bg-<?php echo $bg_color_class; ?> bg-opacity-10"><i class="bi <?php echo $icon_class; ?>"></i></div>
                </div>
                <div class="timeline-content">
                    <div class="card timeline-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title">Solicitud #<?php echo $solicitud['id']; ?> - <?php echo htmlspecialchars($solicitud['nombre_cheque']); ?></h5>
                                    <p class="card-text text-muted small">Solicitado por <?php echo htmlspecialchars($solicitud['nombre_usuario']); ?></p>
                                </div>
                                <div class="text-end">
                                    <span class="badge text-bg-primary fs-6">GTQ <?php echo number_format($solicitud['valor_quetzales'], 2); ?></span>
                                    <div class="text-muted small mt-1"><?php echo date("d/m/Y H:i", strtotime($solicitud['fecha_solicitud'])); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-end gap-2">
                             <?php if ($solicitud['aprobador_actual_id'] == $_SESSION['usuario_id'] && str_starts_with($solicitud['estado'], 'Pendiente')): ?>
                                <button class="btn btn-sm btn-success btn-accion-estado" data-id="<?php echo $solicitud['id']; ?>" data-estado="Aprobado">Aprobar</button>
                                <button class="btn btn-sm btn-danger btn-accion-estado" data-id="<?php echo $solicitud['id']; ?>" data-estado="Rechazado">Rechazar</button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-secondary btn-ver-detalles" data-id="<?php echo $solicitud['id']; ?>">Ver Detalles</button>
                            <a href="generar_cheque.php?id=<?php echo $solicitud['id']; ?>" target="_blank" class="btn btn-sm btn-outline-info">Imprimir</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="text-center p-5">
            <i class="bi bi-search fs-1 text-muted"></i>
            <h4 class="mt-3">No hay solicitudes para mostrar</h4>
            <p class="text-muted">Intenta seleccionar otro filtro de estado.</p>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL PARA VER DETALLES -->
<div class="modal fade" id="modalVerDetalles" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabel">Detalles de la Solicitud</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detalles-content">
                <div class="text-center p-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/layouts/footer.php'; ?>