<?php
// login.php - REDISEÑO FINAL
require_once 'includes/functions.php';
if (isset($_SESSION['usuario_id'])) { header('Location: index.php'); exit(); }
require_once 'templates/layouts/header.php'; // Carga el header (solo <head> y abre <body>)
?>

<div class="auth-split-layout">
    <div class="auth-branding-side">
        <h1 class="app-logo-auth"><i class="bi bi-shield-check me-3"></i>ChequeGestor</h1>
        <p class="mt-3">La solución moderna y segura para la gestión y trazabilidad de tus solicitudes de cheques.</p>
    </div>
    <div class="auth-form-side">
        <div class="auth-card">
            <h2 class="mb-4">Iniciar Sesión</h2>
            
            <?php
            // MENSAJE DE ÉXITO TRAS EL REGISTRO
            if (isset($_GET['status']) && $_GET['status'] === 'registered') {
                echo '<div class="alert alert-success" role="alert">¡Usuario creado exitosamente! Ahora puedes iniciar sesión.</div>';
            }
            // MENSAJE DE ERROR SI EL LOGIN FALLA
            if (isset($_GET['error'])) {
                 echo '<div class="alert alert-danger" role="alert">El usuario o la contraseña son incorrectos.</div>';
            }
            ?>

            <form action="scripts/handle_login.php" method="POST" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="nombre_usuario" class="form-label">Nombre de Usuario</label>
                    <input type="text" class="form-control form-control-lg" id="nombre_usuario" name="nombre_usuario" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <input type="password" class="form-control form-control-lg" id="password" name="password" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="bi bi-eye-slash"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100 mt-3">Acceder</button>
            </form>
            <p class="mt-4 text-center text-muted">¿No tienes una cuenta? <a href="register.php">Regístrate aquí</a></p>
        </div>
    </div>
</div>

<?php require_once 'templates/layouts/footer.php'; // Cierra <body> y <html> ?>