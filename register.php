<?php
// register.php - VERSIÓN CON CAMPOS DE NOMBRES Y APELLIDOS
require_once 'includes/functions.php'; 
if (isset($_SESSION['usuario_id'])) { header('Location: index.php'); exit(); }
require_once 'config/database.php';

$departamentos = $conexion->query("SELECT id, nombre FROM departamentos ORDER BY nombre ASC");
$error_message = $_SESSION['error_message'] ?? null;
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['error_message'], $_SESSION['form_data']);

require_once 'templates/layouts/header.php';
?>

<div class="auth-split-layout">
    <div class="auth-branding-side">
        <h1 class="app-logo-auth"><i class="bi bi-shield-check me-3"></i>ChequeGestor</h1>
        <p class="mt-3">Crea tu cuenta para empezar a gestionar tus solicitudes de forma eficiente.</p>
    </div>
    <div class="auth-form-side">
        <div class="auth-card">
            <h2 class="mb-4">Crear una Cuenta</h2>

            <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form action="scripts/handle_register.php" method="POST" class="needs-validation" novalidate>
                
                <!-- ============================================== -->
                <!-- INICIO DE LA MODIFICACIÓN: NUEVOS CAMPOS       -->
                <!-- ============================================== -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nombres" class="form-label">Nombres</label>
                        <input type="text" class="form-control form-control-lg" name="nombres" required 
                               value="<?php echo htmlspecialchars($form_data['nombres'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="apellidos" class="form-label">Apellidos</label>
                        <input type="text" class="form-control form-control-lg" name="apellidos" required 
                               value="<?php echo htmlspecialchars($form_data['apellidos'] ?? ''); ?>">
                    </div>
                </div>
                <!-- ============================================== -->
                <!-- FIN DE LA MODIFICACIÓN                         -->
                <!-- ============================================== -->

                <div class="mb-3">
                    <label for="nombre_usuario" class="form-label">Nombre de Usuario</label>
                    <input type="text" class="form-control form-control-lg" name="nombre_usuario" required 
                           value="<?php echo htmlspecialchars($form_data['nombre_usuario'] ?? ''); ?>"
                           pattern="\S*"
                           title="El nombre de usuario no puede contener espacios.">
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Correo Electrónico</label>
                    <input type="email" class="form-control form-control-lg" name="email" required value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label for="departamento_id" class="form-label">Departamento</label>
                    <select class="form-select form-select-lg" name="departamento_id" required>
                        <option value="" disabled selected>Selecciona tu departamento</option>
                        <?php while($depto = $departamentos->fetch_assoc()): ?>
                            <option value="<?php echo $depto['id']; ?>" <?php echo (($form_data['departamento_id'] ?? '') == $depto['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($depto['nombre']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <input type="password" class="form-control form-control-lg" name="password" id="password" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword"><i class="bi bi-eye-slash"></i></button>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirmar Contraseña</label>
                    <div class="input-group">
                        <input type="password" class="form-control form-control-lg" name="confirm_password" id="confirm_password" required>
                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword"><i class="bi bi-eye-slash"></i></button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100 mt-3">Registrarse</button>
            </form>
            <p class="mt-4 text-center text-muted">¿Ya tienes una cuenta? <a href="login.php">Inicia sesión</a></p>
        </div>
    </div>
</div>