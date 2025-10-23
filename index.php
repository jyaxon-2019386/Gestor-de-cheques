<?php
// Nombre del archivo: index.php (Adaptado para Pagos SAP)
require_once 'includes/functions.php';
proteger_pagina();
require_once 'templates/layouts/header.php';
require_once 'config/database.php';

// --- LÓGICA DE DATOS DEL DASHBOARD ---

$usuario_id_actual = $_SESSION['usuario_id'];
$es_admin_o_finanzas = es_admin() || es_finanzas() ; // Simplificamos la comprobación de roles

// 1. Contadores para las tarjetas de estadísticas (KPI Cards)
// Agrupamos todos los estados 'Pendiente de...' en un solo contador.
$sql_counts = "SELECT 
    SUM(CASE WHEN estado LIKE 'Pendiente%' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'Aprobado' THEN 1 ELSE 0 END) as aprobados,
    SUM(CASE WHEN estado = 'ProcesadoSAP' THEN 1 ELSE 0 END) as procesados_sap,
    SUM(CASE WHEN estado = 'Rechazado' THEN 1 ELSE 0 END) as rechazados
FROM pagos_pendientes";

$params_counts = [];
$types_counts = "";
if (!$es_admin_o_finanzas) {
    $sql_counts .= " WHERE usuario_id = ?";
    $types_counts = "i";
    $params_counts[] = $usuario_id_actual;
}

$stmt_counts = $conexion->prepare($sql_counts);
if (!empty($params_counts)) {
    $stmt_counts->bind_param($types_counts, ...$params_counts);
}
$stmt_counts->execute();
$counts = $stmt_counts->get_result()->fetch_assoc();
$stmt_counts->close();

$pendientes = $counts['pendientes'] ?? 0;
$aprobados = $counts['aprobados'] ?? 0; // Estos son los que están en la bandeja de Finanzas
$procesados_sap = $counts['procesados_sap'] ?? 0;
$rechazados = $counts['rechazados'] ?? 0;


// 2. Actividad Reciente (Últimos 5 eventos)
$sql_actividad = "SELECT p.id, p.estado, p.fecha_creacion, p.fecha_aprobacion, u_sol.nombre_usuario as solicitante, p.aprobado_por_usuario as aprobador
                  FROM pagos_pendientes p
                  JOIN usuarios u_sol ON p.usuario_id = u_sol.id";

$params_actividad = [];
$types_actividad = "";
if (!$es_admin_o_finanzas) {
    $sql_actividad .= " WHERE p.usuario_id = ?";
    $types_actividad = "i";
    $params_actividad[] = $usuario_id_actual;
}
// Ordenamos por la fecha más reciente entre creación y aprobación
$sql_actividad .= " ORDER BY GREATEST(p.fecha_creacion, IFNULL(p.fecha_aprobacion, p.fecha_creacion)) DESC LIMIT 5";

$stmt_actividad = $conexion->prepare($sql_actividad);
if (!empty($params_actividad)) {
    $stmt_actividad->bind_param($types_actividad, ...$params_actividad);
}
$stmt_actividad->execute();
$actividad_reciente = $stmt_actividad->get_result();
$stmt_actividad->close();


// 3. Datos para el Gráfico (Solicitudes creadas en los últimos 6 meses)
$sql_chart = "SELECT DATE_FORMAT(fecha_creacion, '%b %Y') as mes, COUNT(id) as total 
              FROM pagos_pendientes 
              WHERE fecha_creacion >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";

$params_chart = [];
$types_chart = "";
if (!$es_admin_o_finanzas) {
    $sql_chart .= " AND usuario_id = ?";
    $types_chart = "i";
    $params_chart[] = $usuario_id_actual;
}
$sql_chart .= " GROUP BY DATE_FORMAT(fecha_creacion, '%Y-%m') ORDER BY fecha_creacion ASC";

$stmt_chart = $conexion->prepare($sql_chart);
if (!empty($params_chart)) {
    $stmt_chart->bind_param($types_chart, ...$params_chart);
}
$stmt_chart->execute();
$resultado_chart = $stmt_chart->get_result();
$chart_labels = [];
$chart_data = [];
while ($fila = $resultado_chart->fetch_assoc()) {
    $chart_labels[] = $fila['mes'];
    $chart_data[] = $fila['total'];
}
$stmt_chart->close();
?>

<!-- Encabezado de la Página -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <?php generar_breadcrumbs(); ?>
        <h1 class="fs-2 text-white mt-2">Dashboard de Pagos SAP</h1>
    </div>
    <a href="seleccionar_empresa.php" class="btn btn-lg btn-primary"><i class="bi bi-plus-circle-fill me-2"></i>Nuevo Pago SAP</a>
</div>

<!-- Tarjetas de Estadísticas Adaptadas -->
<div class="row g-4">
    <div class="col-xl-3 col-md-6"><div class="stat-card-final card-pending"><div class="stat-icon"><i class="bi bi-hourglass-split"></i></div><div class="stat-info"><h6 class="text-muted text-uppercase">En Aprobación</h6><h2 class="display-5 fw-bold"><?php echo $pendientes; ?></h2></div></div></div>
    <div class="col-xl-3 col-md-6"><div class="stat-card-final card-approved"><div class="stat-icon"><i class="bi bi-inbox-fill"></i></div><div class="stat-info"><h6 class="text-muted text-uppercase">Para Finanzas</h6><h2 class="display-5 fw-bold"><?php echo $aprobados; ?></h2></div></div></div>
    <div class="col-xl-3 col-md-6"><div class="stat-card-final card-paid"><div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div><div class="stat-info"><h6 class="text-muted text-uppercase">Procesado en SAP</h6><h2 class="display-5 fw-bold"><?php echo $procesados_sap; ?></h2></div></div></div>
    <div class="col-xl-3 col-md-6"><div class="stat-card-final card-rejected"><div class="stat-icon"><i class="bi bi-x-octagon-fill"></i></div><div class="stat-info"><h6 class="text-muted text-uppercase">Rechazadas</h6><h2 class="display-5 fw-bold"><?php echo $rechazados; ?></h2></div></div></div>
</div>

<!-- Layout de Dos Columnas -->
<div class="row g-4 mt-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Solicitudes Creadas (Últimos 6 Meses)</h5></div>
            <div class="card-body">
                <div class="text-center p-4 no-data-message" style="<?php echo empty($chart_data) ? '' : 'display: none;'; ?>">
                    <i class="bi bi-graph-up fs-1 text-muted"></i>
                    <h5 class="mt-3 text-muted">No hay datos suficientes para mostrar</h5>
                </div>
                <canvas id="solicitudesChart" data-labels='<?php echo json_encode($chart_labels); ?>' data-values='<?php echo json_encode($chart_data); ?>' style="<?php echo empty($chart_data) ? 'display: none;' : ''; ?>"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Actividad Reciente</h5></div>
            <div class="card-body">
                <div class="activity-timeline">
                    <?php if ($actividad_reciente->num_rows > 0): while($actividad = $actividad_reciente->fetch_assoc()): ?>
                        <?php
                        $act_icon = 'bi-file-earmark-plus'; $act_color = 'primary';
                        $act_fecha = new DateTime($actividad['fecha_creacion']);
                        $act_mensaje = "<b>{$actividad['solicitante']}</b> creó la solicitud <b>#{$actividad['id']}</b>.";

                        if ($actividad['estado'] === 'Aprobado' && !empty($actividad['aprobador'])) {
                            $act_icon = 'bi-check-circle'; $act_color = 'success';
                            $act_fecha = new DateTime($actividad['fecha_aprobacion']);
                            $act_mensaje = "<b>{$actividad['aprobador']}</b> dio la aprobación final a la solicitud <b>#{$actividad['id']}</b>.";
                        } elseif ($actividad['estado'] === 'Rechazado') {
                            $act_icon = 'bi-x-circle'; $act_color = 'danger';
                            $act_mensaje = "La solicitud <b>#{$actividad['id']}</b> fue rechazada.";
                        } elseif ($actividad['estado'] === 'ProcesadoSAP') {
                            $act_icon = 'bi-send-check'; $act_color = 'info';
                            $act_mensaje = "Finanzas procesó en SAP la solicitud <b>#{$actividad['id']}</b>.";
                        }
                        ?>
                        <div class="timeline-item-sm">
                            <div class="timeline-icon-sm text-<?php echo $act_color; ?> bg-<?php echo $act_color; ?> bg-opacity-10"><i class="bi <?php echo $act_icon; ?>"></i></div>
                            <div class="timeline-content-sm">
                                <p class="mb-0"><?php echo $act_mensaje; ?></p>
                                <small class="text-muted"><?php echo $act_fecha->format('d/m/Y H:i'); ?></small>
                            </div>
                        </div>
                    <?php endwhile; else: ?>
                        <p class="text-center text-muted">No hay actividad reciente para mostrar.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'templates/layouts/footer.php'; ?>