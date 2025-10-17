<?php
// Nombre del archivo: trazabilidad.php
require_once 'includes/functions.php';
proteger_pagina();
require_once 'templates/layouts/header.php';
require_once 'config/database.php';

// --- LÓGICA DE FILTRADO Y CONSULTA ---
$filtro_estado = isset($_GET['filtro']) ? $_GET['filtro'] : '';

// --- Contadores para los botones de filtro ---
// Esta consulta cuenta el total de solicitudes en cada estado, respetando los permisos de visibilidad.
$sql_counts_base = "SELECT estado, COUNT(id) as total FROM pagos_pendientes";
$where_counts = "";
$param_types_counts = "";
$param_values_counts = [];

if (!es_admin() && !in_array($_SESSION['rol'], ['finanzas', 'jefe_de_area', 'gerente_general'])) {
    $where_counts = " WHERE usuario_id = ?";
    $param_types_counts = "i";
    $param_values_counts[] = $_SESSION['usuario_id'];
}
$sql_counts_base .= $where_counts . " GROUP BY estado";

$stmt_counts = $conexion->prepare($sql_counts_base);
if (!empty($param_values_counts)) {
    $stmt_counts->bind_param($param_types_counts, ...$param_values_counts);
}
$stmt_counts->execute();
$result_counts = $stmt_counts->get_result();
$counts = [];
while ($row = $result_counts->fetch_assoc()) {
    $counts[$row['estado']] = $row['total'];
}
$stmt_counts->close();

// Inicializar contadores para evitar errores
$pendientes_jefe = $counts['Pendiente de Jefe'] ?? 0;
$pendientes_gerente = $counts['Pendiente de Gerente General'] ?? 0;
$aprobados = $counts['Aprobado'] ?? 0;
$procesados = $counts['ProcesadoSAP'] ?? 0;
$rechazados = $counts['Rechazado'] ?? 0;
$total_solicitudes = array_sum($counts);


// --- Consulta principal de solicitudes ---
$sql_solicitudes = "SELECT p.*, u.nombre_usuario FROM pagos_pendientes p JOIN usuarios u ON p.usuario_id = u.id";
$where_conditions = [];
$param_types = "";
$param_values = [];

// Aplicar filtro de visibilidad
if (!es_admin() && !in_array($_SESSION['rol'], ['finanzas', 'jefe_de_area', 'gerente_general'])) {
    $where_conditions[] = "p.usuario_id = ?";
    $param_types .= "i";
    $param_values[] = $_SESSION['usuario_id'];
}

// Aplicar filtro por estado
if (!empty($filtro_estado)) {
    $where_conditions[] = "p.estado = ?";
    $param_types .= "s";
    $param_values[] = $filtro_estado;
}

if (!empty($where_conditions)) {
    $sql_solicitudes .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql_solicitudes .= " ORDER BY p.id DESC"; // Ordenar por ID descendente para ver lo más reciente primero

$stmt_solicitudes = $conexion->prepare($sql_solicitudes);
if (!empty($param_values)) {
    $stmt_solicitudes->bind_param($param_types, ...$param_values);
}
$stmt_solicitudes->execute();
$resultado_solicitudes = $stmt_solicitudes->get_result();
$stmt_solicitudes->close();
?>

<!-- Encabezado de la Página -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <?php generar_breadcrumbs(); ?>
        <h1 class="fs-2 text-white mt-2">Trazabilidad de Pagos SAP</h1>
        <p class="text-muted">Vista cronológica de todas las solicitudes y sus estados.</p>
    </div>
</div>

<!-- BOTONES DE FILTRO DE ESTADO ADAPTADOS -->
<ul class="nav nav-pills mb-4 status-filter-nav">
    <li class="nav-item"><a class="nav-link <?php echo empty($filtro_estado) ? 'active' : ''; ?>" href="trazabilidad.php">Todos (<?php echo $total_solicitudes; ?>)</a></li>
    <li class="nav-item"><a class="nav-link <?php echo ($filtro_estado == 'Pendiente de Jefe') ? 'active' : ''; ?>" href="?filtro=Pendiente de Jefe">Pend. Jefe (<?php echo $pendientes_jefe; ?>)</a></li>
    <li class="nav-item"><a class="nav-link <?php echo ($filtro_estado == 'Pendiente de Gerente General') ? 'active' : ''; ?>" href="?filtro=Pendiente de Gerente General">Pend. Gerente (<?php echo $pendientes_gerente; ?>)</a></li>
    <li class="nav-item"><a class="nav-link <?php echo ($filtro_estado == 'Aprobado') ? 'active' : ''; ?>" href="?filtro=Aprobado">Aprobadas (<?php echo $aprobados; ?>)</a></li>
    <li class="nav-item"><a class="nav-link <?php echo ($filtro_estado == 'ProcesadoSAP') ? 'active' : ''; ?>" href="?filtro=ProcesadoSAP">Procesado SAP (<?php echo $procesados; ?>)</a></li>
    <li class="nav-item"><a class="nav-link <?php echo ($filtro_estado == 'Rechazado') ? 'active' : ''; ?>" href="?filtro=Rechazado">Rechazadas (<?php echo $rechazados; ?>)</a></li>
</ul>

<!-- LÍNEA DE TIEMPO ADAPTADA -->
<div class="timeline">
    <?php if ($resultado_solicitudes->num_rows > 0): ?>
        <?php while ($solicitud = $resultado_solicitudes->fetch_assoc()): ?>
            <?php
            // Mapeo de estados a colores e iconos
            $estado_info = [
                'Pendiente de Jefe' => ['icon' => 'bi-person-check', 'color' => 'warning'],
                'Pendiente de Gerente General' => ['icon' => 'bi-person-video3', 'color' => 'warning'],
                'Aprobado' => ['icon' => 'bi-check-lg', 'color' => 'primary'],
                'ProcesadoSAP' => ['icon' => 'bi-check-circle-fill', 'color' => 'success'],
                'Rechazado' => ['icon' => 'bi-x-circle-fill', 'color' => 'danger'],
            ];
            $info_actual = $estado_info[$solicitud['estado']] ?? ['icon' => 'bi-question-circle', 'color' => 'secondary'];
            ?>
            <div class="timeline-item">
                <div class="timeline-icon-wrapper">
                    <div class="timeline-icon text-<?php echo $info_actual['color']; ?> bg-<?php echo $info_actual['color']; ?> bg-opacity-10"><i class="bi <?php echo $info_actual['icon']; ?>"></i></div>
                </div>
                <div class="timeline-content">
                    <div class="card timeline-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title">Solicitud #<?php echo $solicitud['id']; ?> - <?php echo htmlspecialchars($solicitud['CardName']); ?></h5>
                                    <p class="card-text text-muted small">
                                        Por <?php echo htmlspecialchars($solicitud['nombre_usuario']); ?> el <?php echo date("d/m/Y H:i", strtotime($solicitud['fecha_creacion'])); ?>
                                    </p>
                                    <?php if ($solicitud['estado'] === 'ProcesadoSAP' && !empty($solicitud['NumeroDocumentoSAP'])): ?>
                                        <p class="card-text small text-success fw-bold">DocEntry SAP: <?php echo $solicitud['NumeroDocumentoSAP']; ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <span class="badge text-bg-<?php echo $info_actual['color']; ?> fs-6 mb-1"><?php echo $solicitud['estado']; ?></span>
                                    <div class="fw-bold">
                                        <?php echo ($solicitud['DocCurrency'] === 'USD' ? '$' : 'Q'); ?>
                                        <?php echo number_format($solicitud['total_pagar'], 2); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-end gap-2">
                             <button class="btn btn-sm btn-outline-info btn-ver-detalles" data-id="<?php echo $solicitud['id']; ?>">Ver Detalles</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="text-center p-5"><i class="bi-search fs-1 text-muted"></i><h4 class="mt-3">No hay solicitudes para mostrar</h4><p class="text-muted">Intenta seleccionar otro filtro de estado.</p></div>
    <?php endif; ?>
</div>

<?php require_once 'templates/layouts/footer.php'; ?>