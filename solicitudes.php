<?php
// solicitudes.php - VERSIÓN FINAL CON PAGINACIÓN PERSONALIZADA
require_once 'includes/functions.php';
proteger_pagina();
require_once 'config/database.php';

// --- LÓGICA DE PAGINACIÓN ---
$resultados_por_pagina = 10; // ¿Cuántos items mostrar por página?
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $resultados_por_pagina;

// --- LÓGICA DE FILTRADO ---
$filtro_q = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_depto = isset($_GET['departamento_id']) ? $_GET['departamento_id'] : '';

// --- CONSTRUCCIÓN DE CONSULTA SQL CON PAGINACIÓN ---
// 1. Construir la consulta para CONTAR el total de resultados
$sql_count = "SELECT COUNT(s.id) as total FROM solicitud_cheques s JOIN usuarios u ON s.usuario_id = u.id";
// 2. Construir la consulta para OBTENER los resultados de la página actual
$sql = "SELECT s.*, u.nombre_usuario FROM solicitud_cheques s JOIN usuarios u ON s.usuario_id = u.id";

$where_conditions = [];
$param_types = "";
$param_values = [];

// Filtro por rol (el más importante)
if (!es_admin() && $_SESSION['rol'] !== 'finanzas') {
    $where_conditions[] = "s.usuario_id = ?";
    $param_types .= "i";
    $param_values[] = $_SESSION['usuario_id'];
}

// Filtro por búsqueda
if (!empty($filtro_q)) {
    $where_conditions[] = "(s.id LIKE ? OR s.nombre_cheque LIKE ?)";
    $param_types .= "ss";
    $param_values[] = "%$filtro_q%";
    $param_values[] = "%$filtro_q%";
}

// Filtro por estado
if (!empty($filtro_estado)) {
    $where_conditions[] = "s.estado = ?";
    $param_types .= "s";
    $param_values[] = $filtro_estado;
}

// Filtro por departamento
if (!empty($filtro_depto)) {
    $where_conditions[] = "s.departamento_id = ?";
    $param_types .= "i";
    $param_values[] = $filtro_depto;
}

if (!empty($where_conditions)) {
    $where_clause = " WHERE " . implode(" AND ", $where_conditions);
    $sql_count .= $where_clause;
    $sql .= $where_clause;
}

// 3. Ejecutar la consulta de conteo
$stmt_count = $conexion->prepare($sql_count);
if (!empty($param_values)) { $stmt_count->bind_param($param_types, ...$param_values); }
$stmt_count->execute();
$total_resultados = $stmt_count->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total_resultados / $resultados_por_pagina);

// 4. Añadir LIMIT y OFFSET a la consulta principal y ejecutarla
$sql .= " ORDER BY s.id DESC LIMIT ? OFFSET ?";
$param_types .= "ii";
$param_values[] = $resultados_por_pagina;
$param_values[] = $offset;

$stmt = $conexion->prepare($sql);
if (!empty($param_values)) { $stmt->bind_param($param_types, ...$param_values); }
$stmt->execute();
$resultado = $stmt->get_result();

// Obtener lista de departamentos para el filtro
$departamentos = $conexion->query("SELECT id, nombre FROM departamentos ORDER BY nombre ASC");

require_once 'templates/layouts/header.php';
?>

<!-- Encabezado de la Página -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <?php generar_breadcrumbs(); ?>
        <h1 class="fs-2 text-white mt-2">Gestión de Solicitudes</h1>
    </div>
    <a href="nueva_solicitud.php" class="btn btn-lg btn-primary">
        <i class="bi bi-plus-circle-fill me-2"></i>Nueva Solicitud
    </a>
</div>

<!-- BARRA DE FILTROS REDISEÑADA -->
<div class="filter-bar mb-4">
    <form action="solicitudes.php" method="GET" class="row g-3 align-items-end">
        <div class="col-lg-4">
            <label for="q" class="form-label">Buscar por ID o Beneficiario</label>
            <input type="text" class="form-control" name="q" id="q" value="<?php echo htmlspecialchars($filtro_q); ?>">
        </div>
        <div class="col-lg-3">
            <label for="estado" class="form-label">Filtrar por Estado</label>
            <select name="estado" id="estado" class="form-select">
                <option value="">Todos los estados</option>
                <option value="Pendiente" <?php echo ($filtro_estado == 'Pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                <option value="Aprobado" <?php echo ($filtro_estado == 'Aprobado') ? 'selected' : ''; ?>>Aprobado</option>
                <option value="Pagado" <?php echo ($filtro_estado == 'Pagado') ? 'selected' : ''; ?>>Pagado</option>
                <option value="Rechazado" <?php echo ($filtro_estado == 'Rechazado') ? 'selected' : ''; ?>>Rechazado</option>
            </select>
        </div>
        <div class="col-lg-3">
            <label for="departamento_id" class="form-label">Departamento</label>
            <select name="departamento_id" id="departamento_id" class="form-select">
                <option value="">Todos</option>
                <?php while($depto = $departamentos->fetch_assoc()): ?>
                    <option value="<?php echo $depto['id']; ?>" <?php echo ($filtro_depto == $depto['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($depto['nombre']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-lg-2">
            <button type="submit" class="btn btn-primary w-100">Filtrar</button>
        </div>
    </form>
</div>

<!-- VISTA DE TARJETAS RESTAURADA -->
<div class="solicitudes-list">
    <?php if ($resultado->num_rows > 0): ?>
        <?php while($solicitud = $resultado->fetch_assoc()): ?>
            <div class="card solicitud-card-v2 mb-3">
                <?php
                $estado_class = 'text-bg-secondary';
                switch ($solicitud['estado']) {
                    case 'Pendiente': 
                        $estado_class = 'text-bg-warning'; 
                        break;
                    case 'Aprobado': 
                        $estado_class = 'text-bg-success'; 
                        break;
                    case 'Pagado': 
                        $estado_class = 'text-bg-info'; 
                        break;
                    case 'Rechazado': 
                        $estado_class = 'text-bg-danger'; 
                        break;
                }
                ?>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="card-title mb-1">Solicitud #<?php echo $solicitud['id']; ?></h5>
                            <p class="card-text text-muted mb-1">A nombre de: <?php echo htmlspecialchars($solicitud['nombre_cheque']); ?></p>
                            <p class="card-text small text-muted">Solicitado el: <?php echo date("d/m/Y", strtotime($solicitud['fecha_solicitud'])); ?></p>
                            
                            <!-- NUEVO: Mostrar motivo de rechazo -->
                            <?php if ($solicitud['estado'] === 'Rechazado' && !empty($solicitud['motivo_rechazo'])): ?>
                                <div class="alert alert-danger mt-2 p-2 small">
                                    <strong>Motivo:</strong> <?php echo htmlspecialchars($solicitud['motivo_rechazo']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-2 text-center">
                            <span class="badge fs-6 <?php echo $estado_class; ?>"><?php echo $solicitud['estado']; ?></span>
                            <div class="fw-bold fs-5 mt-1">Q <?php echo number_format($solicitud['valor_quetzales'], 2); ?></div>
                        </div>
                        <div class="col-md-2 text-end">
                            <!-- NUEVO: Lógica para mostrar "Editar" o "Ver Detalles" -->
                            <?php if ($solicitud['estado'] === 'Rechazado' && $solicitud['usuario_id'] == $_SESSION['usuario_id']): ?>
                                <a href="editar_solicitud.php?id=<?php echo $solicitud['id']; ?>" class="btn btn-warning btn-sm mb-1">
                                    <i class="bi bi-pencil-fill"></i> Editar
                                </a>
                            <?php else: ?>
                                <button class="btn btn-primary btn-sm btn-ver-detalles mb-1" data-id="<?php echo $solicitud['id']; ?>">Ver Detalles</button>
                                <!-- El botón de imprimir solo se muestra si NO está rechazada -->
                                <a href="generar_cheque.php?id=<?php echo $solicitud['id']; ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-printer"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-inbox display-1 text-muted"></i>
            <h3 class="text-muted mt-3">No se encontraron resultados</h3>
            <p class="text-muted">Intenta ajustar los filtros de búsqueda</p>
        </div>
    <?php endif; ?>
</div>

<!-- NUEVA BARRA DE PAGINACIÓN PERSONALIZADA -->
<?php if ($total_paginas > 1): ?>
<nav aria-label="Paginación de solicitudes" class="mt-4">
  <ul class="pagination justify-content-center">
    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
        <li class="page-item <?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">
            <a class="page-link" href="?pagina=<?php echo $i . '&' . http_build_query($_GET); ?>"><?php echo $i; ?></a>
        </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

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