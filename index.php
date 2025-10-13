<?php
// index.php - VERSIÓN FINAL Y VERIFICADA
require_once 'includes/functions.php';
proteger_pagina();
require_once 'templates/layouts/header.php';
require_once 'config/database.php';

// --- LÓGICA DE DATOS DEL DASHBOARD ---

$usuario_id_actual = $_SESSION['usuario_id'];
$es_admin_actual = es_admin();

// 1. Contadores para las tarjetas de estadísticas (KPI Cards)
$sql_counts = "SELECT 
    SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'Aprobado' THEN 1 ELSE 0 END) as aprobados,
    SUM(CASE WHEN estado = 'Pagado' THEN 1 ELSE 0 END) as pagados,
    SUM(CASE WHEN estado = 'Rechazado' THEN 1 ELSE 0 END) as rechazados
FROM solicitud_cheques";
if (!$es_admin_actual && $_SESSION['rol'] !== 'finanzas') {
    $sql_counts .= " WHERE usuario_id = ?";
    $stmt = $conexion->prepare($sql_counts);
    $stmt->bind_param("i", $usuario_id_actual);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
} else {
    $counts = $conexion->query($sql_counts)->fetch_assoc();
}
$pendientes = $counts['pendientes'] ?? 0;
$aprobados = $counts['aprobados'] ?? 0;
$pagados = $counts['pagados'] ?? 0;
$rechazados = $counts['rechazados'] ?? 0;




// 3. Actividad Reciente
$sql_actividad = "SELECT s.id, s.estado, s.fecha_solicitud, s.fecha_gestion, u.nombre_usuario, g.nombre_usuario as gestor
                  FROM solicitud_cheques s
                  JOIN usuarios u ON s.usuario_id = u.id
                  LEFT JOIN usuarios g ON s.gestionado_por_id = g.id";
if (!$es_admin_actual && $_SESSION['rol'] !== 'finanzas') {
    $sql_actividad .= " WHERE s.usuario_id = ?";
}
$sql_actividad .= " ORDER BY GREATEST(s.fecha_solicitud, IFNULL(s.fecha_gestion, s.fecha_solicitud)) DESC LIMIT 5";
$stmt_actividad = $conexion->prepare($sql_actividad);
if (!$es_admin_actual && $_SESSION['rol'] !== 'finanzas') {
    $stmt_actividad->bind_param("i", $usuario_id_actual);
}
$stmt_actividad->execute();
$actividad_reciente = $stmt_actividad->get_result();


// 4. Datos para el Gráfico (VERSIÓN CORREGIDA Y FINAL)
$sql_chart = "SELECT DATE_FORMAT(fecha_solicitud, '%b %Y') as mes, COUNT(id) as total 
              FROM solicitud_cheques 
              WHERE fecha_solicitud >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND fecha_solicitud <= CURDATE()";

// Añadir filtro por usuario si no es admin
$params_chart = [];
$types_chart = "";
if (!$es_admin_actual && $_SESSION['rol'] !== 'finanzas') {
    $sql_chart .= " AND usuario_id = ?";
    $types_chart .= "i";
    $params_chart[] = $usuario_id_actual;
}

$sql_chart .= " GROUP BY DATE_FORMAT(fecha_solicitud, '%Y-%m') ORDER BY fecha_solicitud ASC";

$stmt_chart = $conexion->prepare($sql_chart);
if (!$es_admin_actual && $_SESSION['rol'] !== 'finanzas') {
    $stmt_chart->bind_param($types_chart, ...$params_chart);
}
$stmt_chart->execute();
$resultado_chart = $stmt_chart->get_result();

$chart_labels = [];
$chart_data = [];
if ($resultado_chart) {
    while ($fila = $resultado_chart->fetch_assoc()) {
        // Aseguramos que los nombres de los meses estén en español si es posible
        // (Esto depende de la configuración de MySQL, pero es una buena práctica)
        $chart_labels[] = $fila['mes'];
        $chart_data[] = $fila['total'];
    }
}

// DEBUG TEMPORAL: Verificar datos del gráfico
error_log("DEBUG CHART - Labels: " . json_encode($chart_labels));
error_log("DEBUG CHART - Data: " . json_encode($chart_data));
error_log("DEBUG CHART - SQL: " . $sql_chart);
?>

<!-- Encabezado de la Página -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <?php generar_breadcrumbs(); ?>
        <h1 class="fs-2 text-white mt-2">Dashboard General</h1>
    </div>
    <a href="nueva_solicitud.php" class="btn btn-lg btn-primary"><i class="bi bi-plus-circle-fill me-2"></i>Nueva Solicitud</a>
</div>

<!-- Tarjetas de Estadísticas -->
<div class="row g-4">
    <div class="col-xl-3 col-md-6"><div class="stat-card-final card-pending"><div class="stat-icon"><i class="bi bi-hourglass-split"></i></div><div class="stat-info"><h6 class="text-muted text-uppercase">Pendientes</h6><h2 class="display-5 fw-bold"><?php echo $pendientes; ?></h2></div></div></div>
    <div class="col-xl-3 col-md-6"><div class="stat-card-final card-approved"><div class="stat-icon"><i class="bi bi-check2-circle"></i></div><div class="stat-info"><h6 class="text-muted text-uppercase">Aprobadas</h6><h2 class="display-5 fw-bold"><?php echo $aprobados; ?></h2></div></div></div>
    <div class="col-xl-3 col-md-6"><div class="stat-card-final card-paid"><div class="stat-icon"><i class="bi bi-archive-fill"></i></div><div class="stat-info"><h6 class="text-muted text-uppercase">Pagadas</h6><h2 class="display-5 fw-bold"><?php echo $pagados; ?></h2></div></div></div>
    <div class="col-xl-3 col-md-6"><div class="stat-card-final card-rejected"><div class="stat-icon"><i class="bi bi-x-octagon-fill"></i></div><div class="stat-info"><h6 class="text-muted text-uppercase">Rechazadas</h6><h2 class="display-5 fw-bold"><?php echo $rechazados; ?></h2></div></div></div>
</div>

<!-- Layout de Dos Columnas -->
<div class="row g-4 mt-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Solicitudes en los Últimos 6 Meses</h5></div>
            <div class="card-body">
                <!-- Contenedor para el mensaje de 'No hay datos' -->
                <div class="text-center p-4 no-data-message" style="display: none;">
                    <i class="bi bi-graph-up fs-1 text-muted"></i>
                    <h5 class="mt-3 text-muted">No hay datos suficientes para mostrar</h5>
                    <p class="text-muted">Crea más solicitudes para ver la tendencia aquí.</p>
                </div>

                <!-- El canvas del gráfico, que se mostrará si hay datos -->
                <canvas id="solicitudesChart" data-labels='<?php echo json_encode($chart_labels); ?>' data-values='<?php echo json_encode($chart_data); ?>'></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Actividad Reciente</h5></div>
            <div class="card-body">
                <div class="activity-timeline">
                    <?php if ($actividad_reciente && $actividad_reciente->num_rows > 0): while($actividad = $actividad_reciente->fetch_assoc()): ?>
                        <?php
                        $act_icon = 'bi-file-earmark-plus'; $act_color = 'primary';
                        $act_fecha = new DateTime($actividad['fecha_solicitud']);
                        $act_mensaje = "<b>{$actividad['nombre_usuario']}</b> creó la solicitud <b>#{$actividad['id']}</b>.";
                        if ($actividad['estado'] != 'Pendiente' && !empty($actividad['gestor'])) {
                            $act_fecha = new DateTime($actividad['fecha_gestion']);
                            if ($actividad['estado'] == 'Aprobado') {
                                $act_icon = 'bi-check-circle'; $act_color = 'success';
                                $act_mensaje = "<b>{$actividad['gestor']}</b> aprobó la solicitud <b>#{$actividad['id']}</b>.";
                            } elseif ($actividad['estado'] == 'Rechazado') {
                                $act_icon = 'bi-x-circle'; $act_color = 'danger';
                                $act_mensaje = "<b>{$actividad['gestor']}</b> rechazó la solicitud <b>#{$actividad['id']}</b>.";
                            }
                        }
                        ?>
                        <div class="timeline-item-sm">
                            <div class="timeline-icon-sm text-<?php echo $act_color; ?> bg-<?php echo $act_color; ?> bg-opacity-10">
                                <i class="bi <?php echo $act_icon; ?>"></i>
                            </div>
                            <div class="timeline-content-sm">
                                <p class="mb-0"><?php echo $act_mensaje; ?></p>
                                <small class="text-muted"><?php echo $act_fecha->format('d/m/Y H:i'); ?></small>
                            </div>
                        </div>
                    <?php endwhile; else: ?>
                        <p class="text-center text-muted">No hay actividad reciente.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>


<?php require_once 'templates/layouts/footer.php'; ?>