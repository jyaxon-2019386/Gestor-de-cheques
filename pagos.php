<?php
// pagos.php - VERSIÓN FINAL CON RECHAZO Y MODAL
require_once 'includes/functions.php';
proteger_pagina();

// Verificar que el usuario tiene rol de finanzas
if (!in_array($_SESSION['rol'], ['finanzas', 'admin'])) {
    die("No tienes permiso para acceder a esta página.");
}

require_once 'config/database.php';

// --- LÓGICA DE FILTRADO ---
$filtro_q = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtro_fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$filtro_fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';

// --- LÓGICA DE CONSULTA A LA BASE DE DATOS ---
$sql_pagos = "SELECT s.*, u.nombre_usuario 
              FROM solicitud_cheques s
              JOIN usuarios u ON s.usuario_id = u.id
              WHERE s.estado = 'Aprobado'";

$param_types = "";
$param_values = [];

if (!empty($filtro_q)) {
    $sql_pagos .= " AND (s.id LIKE ? OR s.nombre_cheque LIKE ?)";
    $param_types .= "ss";
    $param_values[] = "%$filtro_q%";
    $param_values[] = "%$filtro_q%";
}

if (!empty($filtro_fecha_inicio)) {
    $sql_pagos .= " AND s.fecha_programada_pago >= ?";
    $param_types .= "s";
    $param_values[] = $filtro_fecha_inicio;
}

if (!empty($filtro_fecha_fin)) {
    $sql_pagos .= " AND s.fecha_programada_pago <= ?";
    $param_types .= "s";
    $param_values[] = $filtro_fecha_fin;
}

$sql_pagos .= " ORDER BY s.fecha_programada_pago ASC"; // Ordenar por fecha de pago

$stmt_pagos = $conexion->prepare($sql_pagos);
if (!empty($param_values)) {
    $stmt_pagos->bind_param($param_types, ...$param_values);
}
$stmt_pagos->execute();
$pagos_pendientes = $stmt_pagos->get_result();

require_once 'templates/layouts/header.php';
?>

<!-- Encabezado de la Página -->
<div class="mb-5">
    <?php generar_breadcrumbs(); ?>
    <h1 class="fs-2 text-white mt-2">Gestión de Pagos</h1>
    <p class="text-muted">Procesa los pagos de solicitudes aprobadas</p>
</div>

<!-- BARRA DE FILTROS MEJORADA -->
<div class="filter-bar mb-4">
    <form action="pagos.php" method="GET" class="row g-3 align-items-end">
        <div class="col-lg-4">
            <label for="q" class="form-label">Buscar por ID o beneficiario...</label>
            <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($filtro_q); ?>">
        </div>
        <div class="col-lg-3">
            <label for="fecha_inicio" class="form-label">Desde Fecha Programada</label>
            <input type="date" class="form-control" name="fecha_inicio" value="<?php echo htmlspecialchars($filtro_fecha_inicio); ?>">
        </div>
        <div class="col-lg-3">
            <label for="fecha_fin" class="form-label">Hasta Fecha Programada</label>
            <input type="date" class="form-control" name="fecha_fin" value="<?php echo htmlspecialchars($filtro_fecha_fin); ?>">
        </div>
        <div class="col-lg-2">
            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-2"></i>Filtrar</button>
        </div>
    </form>
</div>

<!-- VISTA DE TARJETAS DE PAGO MEJORADA -->
<div class="payment-list">
    <?php if ($pagos_pendientes->num_rows > 0): ?>
        <?php while($solicitud = $pagos_pendientes->fetch_assoc()): ?>
            <div class="card approval-card">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-3">
                        <div class="fw-bold">Solicitud #<?php echo $solicitud['id']; ?></div>
                        <small class="text-muted">Solicitado por: <?php echo htmlspecialchars($solicitud['nombre_usuario']); ?></small>
                    </div>
                    <div class="col-lg-4">
                        <div class="fw-bold"><?php echo htmlspecialchars($solicitud['nombre_cheque']); ?></div>
                        <small class="text-muted"><?php echo htmlspecialchars($solicitud['descripcion']); ?></small>
                    </div>
                    <div class="col-lg-2 text-lg-center">
                        <div class="fw-bold fs-5">Q <?php echo number_format($solicitud['valor_quetzales'], 2); ?></div>
                        <!-- MOSTRAR FECHA PROGRAMADA -->
                        <small class="text-muted">Pagar el: <strong class="text-white"><?php echo date("d/m/Y", strtotime($solicitud['fecha_programada_pago'])); ?></strong></small>
                    </div>
                    <!-- Columna de Acciones Actualizada -->
                    <div class="col-lg-3 text-lg-end approval-actions">
                        <button class="btn btn-sm btn-success flex-grow-1 btn-marcar-pagado" data-id="<?php echo $solicitud['id']; ?>">
                            <i class="bi bi-check-circle-fill"></i> Pagar
                        </button>
                        <!-- NUEVO BOTÓN DE RECHAZAR PARA FINANZAS -->
                        <button class="btn btn-sm btn-danger btn-accion-estado" data-id="<?php echo $solicitud['id']; ?>" data-estado="Rechazado">
                            <i class="bi bi-x-circle-fill"></i> Rechazar
                        </button>
                        <button class="btn btn-sm btn-outline-secondary btn-ver-detalles" data-id="<?php echo $solicitud['id']; ?>" title="Ver Detalles">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            No hay solicitudes aprobadas pendientes de pago.
        </div>
    <?php endif; ?>
</div>

<!-- ===================================================================
     MODAL PARA VER DETALLES (EL CÓDIGO QUE FALTABA)
     =================================================================== -->
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