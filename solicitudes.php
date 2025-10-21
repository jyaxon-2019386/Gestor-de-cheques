<?php
// Nombre del archivo: historial_sap.php
require_once 'includes/functions.php';
proteger_pagina();
require_once 'config/database.php';

// --- LÓGICA DE PAGINACIÓN ---
$resultados_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $resultados_por_pagina;

// --- LÓGICA DE FILTRADO ---
$filtro_q = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';

// --- CONSTRUCCIÓN DE CONSULTA SQL ---
// 1. Consulta para CONTAR el total
$sql_count = "SELECT COUNT(p.id) as total FROM pagos_pendientes p JOIN usuarios u ON p.usuario_id = u.id";
// 2. Consulta para OBTENER los datos
$sql = "SELECT p.*, u.nombre_usuario FROM pagos_pendientes p JOIN usuarios u ON p.usuario_id = u.id";

$where_conditions = [];
$param_types = "";
$param_values = [];

// Filtro por rol: Un usuario normal solo ve sus propias solicitudes.
// Jefes, Finanzas y Admins pueden ver todo.
if (!es_admin() && !in_array($_SESSION['rol'], ['finanzas', 'jefe_de_area', 'gerente_general'])) {
    $where_conditions[] = "p.usuario_id = ?";
    $param_types .= "i";
    $param_values[] = $_SESSION['usuario_id'];
}

// Filtro por búsqueda de texto
if (!empty($filtro_q)) {
    $where_conditions[] = "(p.id = ? OR p.CardName LIKE ?)";
    $param_types .= "is";
    $param_values[] = is_numeric($filtro_q) ? intval($filtro_q) : 0;
    $param_values[] = "%$filtro_q%";
}

// Filtro por estado
if (!empty($filtro_estado)) {
    $where_conditions[] = "p.estado = ?";
    $param_types .= "s";
    $param_values[] = $filtro_estado;
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
$stmt_count->close();

// 4. Añadir paginación a la consulta principal y ejecutarla
$sql .= " ORDER BY p.id DESC LIMIT ? OFFSET ?";
$param_types .= "ii";
$param_values[] = $resultados_por_pagina;
$param_values[] = $offset;

$stmt = $conexion->prepare($sql);
if (!empty($param_values)) { $stmt->bind_param($param_types, ...$param_values); }
$stmt->execute();
$resultado = $stmt->get_result();
$stmt->close();

require_once 'templates/layouts/header.php';
?>

<!-- Encabezado de la Página -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <?php generar_breadcrumbs(); ?>
        <h1 class="fs-2 text-white mt-2">Historial de Pagos SAP</h1>
        <p class="text-muted">Consulta y seguimiento de todas las solicitudes de pago.</p>
    </div>
    <a href="crear_pago_sap.php" class="btn btn-lg btn-primary"><i class="bi bi-plus-circle-fill me-2"></i>Nuevo Pago SAP</a>
</div>

<!-- BARRA DE FILTROS ADAPTADA -->
<div class="filter-bar mb-4">
    <form action="solicitudes.php" method="GET" class="row g-3 align-items-end">
        <div class="col-lg-6">
            <label for="q" class="form-label">Buscar por ID o Beneficiario</label>
            <input type="text" class="form-control" name="q" id="q" value="<?php echo htmlspecialchars($filtro_q); ?>">
        </div>
        <div class="col-lg-4">
            <label for="estado" class="form-label">Filtrar por Estado</label>
            <select name="estado" id="estado" class="form-select">
                <option value="">Todos los estados</option>
                <option value="Pendiente de Jefe" <?php echo ($filtro_estado == 'Pendiente de Jefe') ? 'selected' : ''; ?>>Pendiente de Jefe</option>
                <option value="Pendiente de Gerente General" <?php echo ($filtro_estado == 'Pendiente de Gerente General') ? 'selected' : ''; ?>>Pendiente de Gerente</option>
                <option value="Aprobado" <?php echo ($filtro_estado == 'Aprobado') ? 'selected' : ''; ?>>Aprobado (Listo para Finanzas)</option>
                <option value="ProcesadoSAP" <?php echo ($filtro_estado == 'ProcesadoSAP') ? 'selected' : ''; ?>>Procesado en SAP</option>
                <option value="Rechazado" <?php echo ($filtro_estado == 'Rechazado') ? 'selected' : ''; ?>>Rechazado</option>
            </select>
        </div>
        <div class="col-lg-2">
            <button type="submit" class="btn btn-primary w-100">Filtrar</button>
        </div>
    </form>
</div>

<!-- VISTA DE TARJETAS ADAPTADA -->
<div class="solicitudes-list">
    <?php if ($resultado->num_rows > 0): ?>
        <?php while($solicitud = $resultado->fetch_assoc()): ?>
            <div class="card solicitud-card-v2 mb-3">
                <?php
                // Mapeo de estados a clases de Bootstrap para los badges
                $estado_info = [
                    'Pendiente de Jefe' => ['class' => 'text-bg-warning', 'icon' => 'bi-hourglass-split'],
                    'Pendiente de Gerente General' => ['class' => 'text-bg-warning', 'icon' => 'bi-hourglass-top'],
                    'Aprobado' => ['class' => 'text-bg-primary', 'icon' => 'bi-check-lg'],
                    'ProcesadoSAP' => ['class' => 'text-bg-success', 'icon' => 'bi-check-circle-fill'],
                    'Rechazado' => ['class' => 'text-bg-danger', 'icon' => 'bi-x-circle-fill'],
                ];
                $info_actual = $estado_info[$solicitud['estado']] ?? ['class' => 'text-bg-secondary', 'icon' => 'bi-question-circle'];
                ?>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-7">
                            <h5 class="card-title mb-1">Solicitud #<?php echo $solicitud['id']; ?> - <span class="text-primary"><?php echo htmlspecialchars($solicitud['CardName']); ?></span></h5>
                            <p class="card-text small text-muted">
                                Solicitado por: <?php echo htmlspecialchars($solicitud['nombre_usuario']); ?> 
                                el <?php echo date("d/m/Y", strtotime($solicitud['fecha_creacion'])); ?>
                            </p>
                            <?php if ($solicitud['estado'] === 'ProcesadoSAP' && !empty($solicitud['NumeroDocumentoSAP'])): ?>
                                <p class="card-text small text-success fw-bold">DocEntry SAP: <?php echo $solicitud['NumeroDocumentoSAP']; ?></p>
                            <?php endif; ?>
                            <?php if ($solicitud['estado'] === 'Rechazado' && !empty($solicitud['motivo_rechazo'])): ?>
                                <p class="card-text small text-danger"><strong class="me-1">Motivo:</strong><?php echo htmlspecialchars($solicitud['motivo_rechazo']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-2 text-center">
                            <span class="badge fs-6 <?php echo $info_actual['class']; ?>"><i class="<?php echo $info_actual['icon']; ?> me-1"></i><?php echo $solicitud['estado']; ?></span>
                            <div class="fw-bold fs-5 mt-1">
                                <?php echo ($solicitud['DocCurrency'] === 'USD' ? '$' : 'Q'); ?>
                                <?php echo number_format($solicitud['total_pagar'], 2); ?>
                            </div>
                        </div>
                        <div class="col-md-3 text-end">
                             <button class="btn btn-outline-info btn-sm btn-ver-detalles" data-id="<?php echo $solicitud['id']; ?>">
                                <i class="bi bi-eye-fill me-1"></i> Ver Detalles
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="text-center py-5"><i class="bi bi-inbox display-1 text-muted"></i><h3 class="text-muted mt-3">No se encontraron resultados</h3><p class="text-muted">Intenta ajustar los filtros de búsqueda.</p></div>
    <?php endif; ?>
</div>

<!-- BARRA DE PAGINACIÓN PERSONALIZADA -->
<?php if ($total_paginas > 1): ?>
<nav class="mt-4">
  <ul class="pagination justify-content-center">
    <?php 
    // Limpia 'pagina' para evitar duplicados en la URL
    $query_params = $_GET;
    unset($query_params['pagina']);
    $base_url = http_build_query($query_params);
    ?>
    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
        <li class="page-item <?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">
            <a class="page-link" href="?pagina=<?php echo $i . '&' . $base_url; ?>"><?php echo $i; ?></a>
        </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>



<?php require_once 'templates/layouts/footer.php'; ?>