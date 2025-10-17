<?php
require_once(__DIR__ . '/../../includes/functions.php');
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChequeGestor | ECONSA</title>
    <!-- Tus enlaces a CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php if (isset($_SESSION['usuario_id'])): ?>
    <div id="wrapper">
        <!-- Barra Lateral Flotante Mejorada -->
        <div id="sidebar-wrapper">
            <div class="sidebar-header">
                <a href="index.php" class="app-logo" id="appLogo">
                    <i class="bi bi-shield-check"></i>
                    <span class="sidebar-link-text">ChequeGestor</span>
                </a>
            </div>
            
            <div class="list-group list-group-flush flex-grow-1">
                <div class="sidebar-heading-custom"><span class="sidebar-link-text">WORKSPACE</span></div>
                <!-- ENLACES CON TOOLTIPS AÑADIDOS -->
                <a href="index.php" class="list-group-item list-group-item-action <?php echo ($pagina_actual == 'index.php') ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <i class="bi bi-speedometer2"></i><span class="sidebar-link-text">Dashboard</span>
                </a>
                <!-- NUEVO ENLACE A LA BANDEJA DE ENTRADA -->
                <?php if (puede_aprobar()): ?>
                    <a href="aprobaciones_jefe.php" class="list-group-item list-group-item-action <?php echo ($pagina_actual == 'aprobaciones.php') ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Aprobaciones">
                        <i class="bi bi-inbox-fill"></i><span class="sidebar-link-text">Aprobaciones</span>
                    </a>
                <?php endif; ?>

                <?php if (es_finanzas()): ?>
                <a href="aprobaciones.php" class="list-group-item list-group-item-action <?php echo ($pagina_actual == 'aprobaciones.php') ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Aprobaciones">
                        <i class="bi bi-inbox-fill"></i><span class="sidebar-link-text">Aprobaciones</span>
                    </a>
                <?php endif; ?>


                <!-- ENLACE PARA PROCESAMIENTO DE PAGOS -->
                <?php if ($_SESSION['rol'] === 'usuario' || es_admin()): ?> 
                    <a href="pagos.php" class="list-group-item list-group-item-action <?php echo ($pagina_actual == 'pagos.php') ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Pagos Pendientes">
                        <i class="bi bi-cash-stack"></i><span class="sidebar-link-text">Pagos Pendientes</span>
                    </a>
                    <a href="crear_pago_sap.php" class="list-group-item list-group-item-action <?php echo ($pagina_actual == 'crear_pago_sap.php') ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Pago a SAP">
                    <i class="bi bi-send-check-fill"></i><span class="sidebar-link-text">Registrar Pago SAP</span>
                    </a>
                <?php endif; ?>
                <a href="nueva_solicitud.php" class="list-group-item list-group-item-action <?php echo in_array($pagina_actual, ['nueva_solicitud.php', 'crear_solicitud.php']) ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Nueva Solicitud">
                    <i class="bi bi-plus-circle"></i><span class="sidebar-link-text">Nueva Solicitud</span>
                </a>
                <a href="solicitudes.php" class="list-group-item list-group-item-action <?php echo ($pagina_actual == 'solicitudes.php') ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Historial">
                    <i class="bi bi-card-list"></i><span class="sidebar-link-text">Historial</span>
                </a>
                <a href="trazabilidad.php" class="list-group-item list-group-item-action <?php echo ($pagina_actual == 'trazabilidad.php') ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Trazabilidad Global">
                    <i class="bi bi-arrows-fullscreen"></i><span class="sidebar-link-text">Trazabilidad Global</span>
                </a>

                <?php if (es_admin()): ?>
                    <div class="sidebar-heading-custom"><span class="sidebar-link-text">ADMINISTRACIÓN</span></div>
                    <a href="gestion_usuarios.php" class="list-group-item list-group-item-action <?php echo ($pagina_actual == 'gestion_usuarios.php') ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Usuarios">
                        <i class="bi bi-people-fill"></i><span class="sidebar-link-text">Usuarios</span>
                    </a>
                <?php endif; ?>

            </div>

            <div class="sidebar-footer">
                <!-- Botón para el estado EXPANDIDO -->
                <a href="#" class="list-group-item list-group-item-action" id="sidebarToggleCollapse">
                    <i class="bi bi-list"></i>
                    <span class="sidebar-link-text">Contraer Menú</span>
                </a>
                <!-- Botón para el estado COLAPSADO -->
                <a href="#" class="list-group-item list-group-item-action d-none" id="sidebarToggleExpand" data-bs-toggle="tooltip" data-bs-placement="right" title="Expandir Menú">
                    <i class="bi bi-list"></i>
                </a>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->
        
        <!-- Contenido Principal de la Página -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-dark">
                <div class="container-fluid">
                    <div class="collapse navbar-collapse">
                        <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <div class="dropdown-header-custom">
                                        <strong><?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?></strong>
                                        <small class="text-muted d-block"><?php echo htmlspecialchars($_SESSION['email']); ?></small>
                                    </div>

                                    <a class="dropdown-item" href="perfil.php"><i class="bi bi-person-fill me-2"></i>Mi Perfil</a>
                                    
                                    <!-- ============================================= -->
                                    <!-- INICIO DE LA CORRECCIÓN DE USABILIDAD -->
                                    <!-- ============================================= -->
                                    <label class="dropdown-item dropdown-item-interactive d-flex justify-content-between align-items-center" for="themeSwitch">
                                        <div> <!-- Div para agrupar los elementos de la izquierda -->
                                            <i class="bi bi-moon-stars-fill me-2"></i>
                                            <span>Tema</span>
                                        </div>
                                        <!-- El switch ahora está dentro de la label -->
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="themeSwitch">
                                        </div>
                                    </label>
                                    <!-- ============================================= -->
                                    <!-- FIN DE LA CORRECCIÓN -->
                                    <!-- ============================================= -->

                                    <div class="dropdown-divider"></div>
                                    
                                    <a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
            <div class="container-fluid p-4">
                <main>
    <!-- El 'else' está ausente a propósito. Si no está logueado, no se genera NINGÚN contenedor. -->
<?php endif; ?>