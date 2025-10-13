<?php
// aprobaciones.php - VERSIÓN FINAL CON FILTROS
require_once 'includes/functions.php';
proteger_pagina();

if (!puede_aprobar()) {
    header('Location: index.php');
    exit();
}

require_once 'templates/layouts/header.php';
require_once 'config/database.php';

// --- LÓGICA DE FILTRADO ---
$filtro_q = isset($_GET['q']) ? trim($_GET['q']) : '';

// --- LÓGICA DE CONSULTA A LA BASE DE DATOS (VERSIÓN FINAL) ---
$sql_bandeja = "SELECT s.*, u.nombre_usuario 
                FROM solicitud_cheques s
                JOIN usuarios u ON s.usuario_id = u.id
                WHERE s.estado LIKE 'Pendiente%'"; // Empezamos buscando TODAS las pendientes

$param_types = "";
$param_values = [];

// --- LA CORRECCIÓN CLAVE: Lógica de visibilidad por rol ---
if (!es_admin()) {
    // Si NO eres admin, solo ves las que están asignadas a ti.
    $sql_bandeja .= " AND s.aprobador_actual_id = ?";
    $param_types .= "i";
    $param_values[] = $_SESSION['usuario_id'];
}
// Si ERES admin, no se añade ningún filtro extra, por lo que ves TODAS las pendientes.

// Añadir filtro por término de búsqueda (ID, solicitante o beneficiario)
if (!empty($filtro_q)) {
    $sql_bandeja .= " AND (s.id = ? OR u.nombre_usuario LIKE ? OR s.nombre_cheque LIKE ?)";
    $param_types .= "iss";
    $param_values[] = $filtro_q;
    $param_values[] = '%' . $filtro_q . '%';
    $param_values[] = '%' . $filtro_q . '%';
}

$sql_bandeja .= " ORDER BY s.fecha_solicitud ASC";

$stmt_bandeja = $conexion->prepare($sql_bandeja);
if (!empty($param_values)) {
    $stmt_bandeja->bind_param($param_types, ...$param_values);
}
$stmt_bandeja->execute();
$bandeja_entrada = $stmt_bandeja->get_result();
?>

<!-- Encabezado de la Página -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <?php generar_breadcrumbs(); ?>
        <h1 class="fs-2 text-white mt-2">Bandeja de Aprobaciones</h1>
    </div>
    <span class="badge rounded-pill text-bg-danger fs-6">
        <?php echo $bandeja_entrada->num_rows; ?> Pendientes
    </span>
</div>

<!-- NUEVA BARRA DE FILTROS -->
<div class="filter-bar mb-4">
    <form action="aprobaciones.php" method="GET">
        <div class="input-group">
            <input type="text" class="form-control" name="q" placeholder="Buscar por ID, solicitante o beneficiario..." value="<?php echo htmlspecialchars($filtro_q); ?>">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search me-2"></i>Buscar</button>
        </div>
    </form>
</div>


<!-- VISTA DE TARJETAS DE APROBACIÓN -->
<div class="approvals-list">
    <?php if ($bandeja_entrada->num_rows > 0): ?>
        <?php while($solicitud = $bandeja_entrada->fetch_assoc()): ?>
            <div class="card approval-card">
                <div class="row g-3 align-items-center">
                    <!-- Columna 1: Solicitante -->
                    <div class="col-lg-3 d-flex align-items-center gap-3">
                        <div class="user-avatar">
                            <span class="avatar-initials"><?php echo strtoupper(substr($solicitud['nombre_usuario'], 0, 2)); ?></span>
                        </div>
                        <div>
                            <div class="fw-bold"><?php echo htmlspecialchars($solicitud['nombre_usuario']); ?></div>
                            <small class="text-muted">Solicitante</small>
                        </div>
                    </div>
                    <!-- Columna 2: Beneficiario -->
                    <div class="col-lg-4">
                        <h5 class="card-title mb-1">A nombre de: <?php echo htmlspecialchars($solicitud['nombre_cheque']); ?></h5>
                        <p class="mb-0 text-muted small">ID de Solicitud: #<?php echo $solicitud['id']; ?></p>
                    </div>
                    <!-- Columna 3: Monto y Fecha -->
                    <div class="col-lg-2 text-lg-center">
                        <div class="fw-bold fs-5">Q <?php echo number_format($solicitud['valor_quetzales'], 2); ?></div>
                        <small class="text-muted"><?php echo date("d/m/Y", strtotime($solicitud['fecha_solicitud'])); ?></small>
                    </div>
                    <!-- Columna 4: Acciones -->
                    <div class="col-lg-3 text-lg-end approval-actions">
                        <button class="btn btn-sm btn-success flex-grow-1 btn-accion-estado" data-id="<?php echo $solicitud['id']; ?>" data-estado="Aprobado">
                            <i class="bi bi-check-lg"></i> Aprobar
                        </button>
                        <button class="btn btn-sm btn-danger flex-grow-1 btn-accion-estado" data-id="<?php echo $solicitud['id']; ?>" data-estado="Rechazado">
                            <i class="bi bi-x-lg"></i> Rechazar
                        </button>
                        <button class="btn btn-sm btn-outline-secondary btn-ver-detalles" data-id="<?php echo $solicitud['id']; ?>" title="Ver Detalles">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="card">
            <div class="card-body text-center p-5">
                <i class="bi bi-search fs-1 text-muted"></i>
                <h4 class="mt-3">No se encontraron resultados</h4>
                <p class="text-muted">No hay solicitudes pendientes que coincidan con tu búsqueda.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ===================================================================
     INICIO: MODAL PARA VER DETALLES (EL CÓDIGO QUE FALTABA)
     =================================================================== -->
<div class="modal fade" id="modalVerDetalles" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Detalles de la Solicitud</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="detalles-content">
        <!-- El contenido se cargará aquí con AJAX -->
        <div class="text-center p-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>
      </div>
       <div class="modal-footer">
            <a href="#" id="btn-imprimir-modal" target="_blank" class="btn btn-outline-info"><i class="bi bi-printer me-2"></i>Imprimir</a>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<!-- ===================================================================
     FIN: MODAL PARA VER DETALLES
     =================================================================== -->

<?php require_once 'templates/layouts/footer.php'; ?>
