<?php
// perfil.php
require_once 'includes/functions.php';
proteger_pagina(); // Asegura que el usuario esté logueado
require_once 'templates/layouts/header.php';
require_once 'config/database.php';

// Obtener la información completa del usuario actual para mostrarla
$usuario_id_actual = $_SESSION['usuario_id'];
$sql_usuario = "SELECT u.nombre_usuario, u.email, u.rol, d.nombre as departamento_nombre, j.nombre_usuario as jefe_nombre
                FROM usuarios u
                LEFT JOIN departamentos d ON u.departamento_id = d.id
                LEFT JOIN usuarios j ON u.jefe_id = j.id
                WHERE u.id = ?";
$stmt = $conexion->prepare($sql_usuario);
$stmt->bind_param("i", $usuario_id_actual);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
?>

<!-- Encabezado de la Página -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <?php generar_breadcrumbs(); ?>
        <h1 class="fs-2 text-white mt-2">Mi Perfil</h5>
    </div>
</div>

<form id="form-mi-perfil">
    <div class="row g-4">
        <!-- Columna Izquierda: Información General -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Información General</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre de Usuario</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($usuario['nombre_usuario']); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" name="email" id="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Departamento</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($usuario['departamento_nombre'] ?? 'N/A'); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol</label>
                        <input type="text" class="form-control" value="<?php echo ucfirst(str_replace('_', ' ', $usuario['rol'])); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Supervisor Directo</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($usuario['jefe_nombre'] ?? 'N/A'); ?>" readonly>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Cambio de Contraseña -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Seguridad</h5></div>
                <div class="card-body">
                    <p class="text-muted">Deja estos campos en blanco si no deseas cambiar tu contraseña.</p>
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Contraseña Actual</label>
                        <input type="password" class="form-control" name="current_password" id="current_password">
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nueva Contraseña</label>
                        <input type="password" class="form-control" name="new_password" id="new_password">
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña</label>
                        <input type="password" class="form-control" name="confirm_password" id="confirm_password">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Acciones -->
    <div class="form-action-bar mt-4">
        <button class="btn btn-primary btn-lg" type="submit">
            <i class="bi bi-check-circle-fill me-2"></i>Guardar Cambios
        </button>
    </div>
</form>

<?php require_once 'templates/layouts/footer.php'; ?>
