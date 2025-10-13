<?php
// gestion_usuarios.php - VERSIÓN REDISEÑADA
require_once 'includes/functions.php';
proteger_pagina();

if (!es_admin()) {
    header('Location: index.php');
    exit();
}

require_once 'templates/layouts/header.php';
require_once 'config/database.php';

// Obtener todos los usuarios
$sql_usuarios = "SELECT u.id, u.nombre_usuario, u.email, u.rol, j.nombre_usuario as jefe_nombre, u.jefe_id
                 FROM usuarios u
                 LEFT JOIN usuarios j ON u.jefe_id = j.id
                 ORDER BY u.nombre_usuario ASC";
$lista_usuarios = $conexion->query($sql_usuarios);

// Obtener la lista de posibles jefes para el dropdown
$sql_jefes = "SELECT id, nombre_usuario FROM usuarios WHERE rol IN ('jefe_de_area', 'gerente', 'admin')";
$jefes_array = $conexion->query($sql_jefes)->fetch_all(MYSQLI_ASSOC);
?>

<!-- Encabezado de la Página con Botón "Crear" -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <?php generar_breadcrumbs(); ?>
        <h1 class="fs-2 text-white mt-2">Gestión de Usuarios</h1>
    </div>
    <button class="btn btn-lg btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario">
        <i class="bi bi-plus-circle-fill me-2"></i>Crear Usuario
    </button>
</div>

<!-- NUEVA BARRA DE BÚSQUEDA -->
<div class="card mb-4">
    <div class="card-body">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" id="filtro-usuarios" placeholder="Buscar usuario por nombre, email o rol...">
        </div>
    </div>
</div>

<!-- Nueva Lista de Usuarios -->
<div class="card">
    <div class="card-body p-0">
        <div class="user-list" id="lista-usuarios-container">
            <?php while($usuario = $lista_usuarios->fetch_assoc()): ?>
                <?php
                // Lógica para el color del badge del rol
                $rol_class = 'text-bg-secondary'; // Color por defecto
                switch ($usuario['rol']) {
                    case 'admin':
                        $rol_class = 'text-bg-danger'; break;
                    case 'gerente_general':
                        $rol_class = 'text-bg-warning'; break; // Amarillo/Dorado para el GM
                    case 'gerente':
                        $rol_class = 'text-bg-info'; break;
                    case 'jefe_de_area':
                        $rol_class = 'text-bg-primary'; break;
                    case 'finanzas':
                        $rol_class = 'text-bg-success'; break;
                    case 'proveeduria':
                        $rol_class = 'text-bg-dark'; break;
                    default: // Para 'usuario' y cualquier otro
                        $rol_class = 'text-bg-secondary';
                }

                // --- LA CORRECCIÓN CLAVE DEL TEXTO ---
                $rol_formateado = ucfirst(str_replace('_', ' de ', $usuario['rol']));
                ?>
                <div class="user-list-item user-card-wrapper" 
                     data-nombre="<?php echo htmlspecialchars($usuario['nombre_usuario']); ?>"
                     data-email="<?php echo htmlspecialchars($usuario['email']); ?>"
                     data-rol="<?php echo $usuario['rol']; ?>"
                     data-jefe="<?php echo htmlspecialchars($usuario['jefe_nombre'] ?? 'N/A'); ?>">
                    <div class="user-avatar">
                        <span class="avatar-initials"><?php echo strtoupper(substr($usuario['nombre_usuario'], 0, 2)); ?></span>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($usuario['nombre_usuario']); ?></div>
                        <div class="user-email"><?php echo htmlspecialchars($usuario['email']); ?></div>
                    </div>
                    <div class="user-role">
                        <!-- AÑADIMOS "rounded-pill" Y USAMOS EL TEXTO FORMATEADO -->
                        <span class="badge rounded-pill <?php echo $rol_class; ?>">
                            <?php echo $rol_formateado; ?>
                        </span>
                    </div>
                    <div class="user-manager">
                        <div class="text-muted small">Reporta a:</div>
                        <div><?php echo htmlspecialchars($usuario['jefe_nombre'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="user-actions">
                         <button class="btn btn-sm btn-primary btn-editar-usuario" 
                                data-id="<?php echo $usuario['id']; ?>"
                                data-nombre="<?php echo htmlspecialchars($usuario['nombre_usuario']); ?>"
                                data-email="<?php echo htmlspecialchars($usuario['email']); ?>"
                                data-rol="<?php echo $usuario['rol']; ?>"
                                data-jefe-id="<?php echo $usuario['jefe_id']; ?>">
                            <i class="bi bi-pencil-fill"></i> Editar
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>


<!-- MODAL PARA EDITAR USUARIO -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="form-editar-usuario">
        <div class="modal-header">
            <h5 class="modal-title">Editar Usuario</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="usuario_id" id="usuario_id">
            <div class="mb-3">
                <label for="nombre_usuario" class="form-label">Nombre de Usuario</label>
                <input type="text" class="form-control" name="nombre_usuario" id="nombre_usuario" readonly>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" name="email" id="email" readonly>
            </div>
            <div class="mb-3">
                <label for="rol" class="form-label">Rol</label>
                <select class="form-select" name="rol" id="rol" required>
                    <option value="usuario">Usuario Estándar</option>
                    <option value="jefe_de_area">Jefe de Área</option>
                    <option value="gerente">Gerente</option>
                    <option value="gerente_general">Gerente General</option>
                    <option value="finanzas">Finanzas</option>
                    <option value="proveeduria">Proveeduría</option>
                    <option value="admin">Administrador</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="jefe_id" class="form-label">Supervisor Directo</label>
                <select class="form-select" name="jefe_id" id="jefe_id">
                    <option value="">Sin supervisor</option>
                    <?php foreach($jefes_array as $jefe): ?>
                        <option value="<?php echo $jefe['id']; ?>"><?php echo htmlspecialchars($jefe['nombre_usuario']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- NUEVO MODAL PARA CREAR USUARIO -->
<div class="modal fade" id="modalCrearUsuario" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="form-crear-usuario">
        <div class="modal-header">
            <h5 class="modal-title">Crear Nuevo Usuario</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <!-- (Usaremos el mismo backend que el 'register' original) -->
            <div class="mb-3">
                <label for="create_nombre_usuario" class="form-label">Nombre de Usuario</label>
                <input type="text" class="form-control" name="nombre_usuario" id="create_nombre_usuario" required>
            </div>
            <div class="mb-3">
                <label for="create_email" class="form-label">Email</label>
                <input type="email" class="form-control" name="email" id="create_email" required>
            </div>
             <div class="mb-3">
                <label for="create_password" class="form-label">Contraseña</label>
                <div class="input-group">
                    <input type="password" class="form-control" name="password" id="create_password" required>
                    <button class="btn btn-outline-secondary" type="button" id="toggleCreatePassword">
                        <i class="bi bi-eye-slash"></i>
                    </button>
                </div>
            </div>
            <div class="mb-3">
                <label for="create_confirm_password" class="form-label">Confirmar Contraseña</label>
                <div class="input-group">
                    <input type="password" class="form-control" name="confirm_password" id="create_confirm_password" required>
                    <button class="btn btn-outline-secondary" type="button" id="toggleCreateConfirmPassword">
                        <i class="bi bi-eye-slash"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Crear Usuario</button>
        </div>
      </form>
    </div>
  </div>
</div>


<?php require_once 'templates/layouts/footer.php'; ?>