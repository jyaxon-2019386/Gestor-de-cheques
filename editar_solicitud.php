<?php
// editar_solicitud.php
require_once 'includes/functions.php';
proteger_pagina(); // Asegura que el usuario esté logueado
require_once 'config/database.php';

// --- 1. VALIDACIÓN Y OBTENCIÓN DE DATOS DE LA SOLICITUD ---

// Verificar que se proporcionó un ID y es numérico
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Si no es válido, detenemos la ejecución con un mensaje de error.
    die("Error: ID de solicitud no válido o no proporcionado.");
}
$solicitud_id = intval($_GET['id']);
$usuario_id_actual = $_SESSION['usuario_id'];

// Obtener todos los datos de la solicitud que se va a editar
$sql_solicitud = "SELECT * FROM solicitud_cheques WHERE id = ?";
$stmt = $conexion->prepare($sql_solicitud);
$stmt->bind_param("i", $solicitud_id);
$stmt->execute();
$resultado_solicitud = $stmt->get_result();
$solicitud = $resultado_solicitud->fetch_assoc();

// --- 2. LÓGICA DE PERMISOS DE EDICIÓN ---

// Si no se encontró la solicitud o el usuario no tiene permiso, detenemos la ejecución.
if (!$solicitud) {
    die("Error: No se encontró ninguna solicitud con el ID proporcionado.");
}
// Un usuario solo puede editar su propia solicitud, a menos que sea admin.
if ($solicitud['usuario_id'] != $usuario_id_actual && !es_admin()) {
    die("Acceso denegado: No tienes permiso para editar esta solicitud.");
}
// Opcional: Impedir la edición si la solicitud no está en estado 'Rechazado'
// if ($solicitud['estado'] !== 'Rechazado' && !es_admin()) {
//     die("Esta solicitud no puede ser editada porque no ha sido rechazada.");
// }


// --- 3. OBTENER DATOS PARA LOS DROPDOWNS DEL FORMULARIO ---

// Obtener la lista de departamentos
$departamentos_res = $conexion->query("SELECT id, nombre FROM departamentos ORDER BY nombre ASC");
$departamentos = $departamentos_res->fetch_all(MYSQLI_ASSOC);

// Obtener la lista de posibles aprobadores (para el rol de Proveeduría/Admin)
$sql_aprobadores = "SELECT u.id, u.nombre_usuario, u.rol, d.nombre as departamento_nombre
                    FROM usuarios u
                    LEFT JOIN departamentos d ON u.departamento_id = d.id
                    WHERE u.rol IN ('jefe_de_area', 'gerente', 'admin', 'gerente_general')
                    ORDER BY u.nombre_usuario ASC";
$aprobadores_res = $conexion->query($sql_aprobadores);
$lista_aprobadores = $aprobadores_res->fetch_all(MYSQLI_ASSOC);

// Obtener el rol del usuario actual
$rol_usuario_actual = $_SESSION['rol'];


// Cargar el encabezado de la página
require_once 'templates/layouts/header.php';
?>

<!-- Formulario de Edición -->
<form action="scripts/handle_update_cheque.php" method="POST" id="form-editar-cheque" class="needs-validation" novalidate>
    <!-- CAMPO OCULTO CON EL ID DE LA SOLICITUD A ACTUALIZAR -->
    <input type="hidden" name="solicitud_id" value="<?php echo $solicitud['id']; ?>">
    <!-- Campo oculto para el tipo de soporte, si lo usas -->
    <input type="hidden" name="tipo_soporte" value="<?php echo htmlspecialchars($solicitud['tipo_soporte']); ?>">

    <!-- Encabezado de la página de edición -->
    <div class="mb-5">
        <?php generar_breadcrumbs(); ?>
        <h1 class="fs-2 text-white mt-2">Editando Solicitud de Cheque #<?php echo $solicitud['id']; ?></h1>
        <?php if ($solicitud['estado'] === 'Rechazado' && !empty($solicitud['motivo_rechazo'])): ?>
            <div class="alert alert-warning mt-3">
                <strong>Motivo del Rechazo:</strong> <?php echo htmlspecialchars($solicitud['motivo_rechazo']); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Layout de Dos Columnas con datos pre-rellenados -->
    <div class="row g-4">
        <!-- Columna Principal -->
        <div class="col-lg-8">
            <div class="form-card mb-4">
                <div class="form-card-header"><i class="bi bi-person-vcard"></i>Beneficiario y Monto</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="empresa_sap" class="form-label">Empresa SAP</label>
                            <select class="form-select" id="empresa_sap" name="empresa_sap">
                                <option value="PROQUIMA" <?php echo ($solicitud['empresa_sap'] == 'PROQUIMA') ? 'selected' : ''; ?>>PROQUIMA</option>
                                <option value="UNHESA" <?php echo ($solicitud['empresa_sap'] == 'UNHESA') ? 'selected' : ''; ?>>UNHESA</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label for="nombre_cheque" class="form-label">Nombre del Beneficiario</label>
                            <input type="text" class="form-control form-control-lg" id="nombre_cheque" name="nombre_cheque" value="<?php echo htmlspecialchars($solicitud['nombre_cheque']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="empresa" class="form-label">Empresa</label>
                            <input type="text" class="form-control" id="empresa" name="empresa" value="<?php echo htmlspecialchars($solicitud['empresa']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="valor_quetzales" class="form-label">Monto en Quetzales (Q)</label>
                            <input type="number" step="0.01" class="form-control form-control-lg" id="valor_quetzales" name="valor_quetzales" value="<?php echo htmlspecialchars($solicitud['valor_quetzales']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="valor_dolares" class="form-label">Monto en Dólares ($)</label>
                            <input type="number" step="0.01" class="form-control" id="valor_dolares" name="valor_dolares" value="<?php echo htmlspecialchars($solicitud['valor_dolares']); ?>">
                        </div>
                        <!-- Campos de Centro de Costo (la lógica JS los llenará) -->
                        <div class="col-md-3"><label for="cc_nivel1" class="form-label">Centro de Costo Nivel 1</label><select class="form-select cc-selector" id="cc_nivel1" data-level="1"></select></div>
                        <div class="col-md-3"><label for="cc_nivel2" class="form-label">Nivel 2</label><select class="form-select cc-selector" id="cc_nivel2" data-level="2" disabled></select></div>
                        <div class="col-md-3"><label for="cc_nivel3" class="form-label">Nivel 3</label><select class="form-select cc-selector" id="cc_nivel3" data-level="3" disabled></select></div>
                        <div class="col-md-3"><label for="cc_nivel4" class="form-label">Nivel 4</label><select class="form-select cc-selector" id="cc_nivel4" data-level="4" disabled></select></div>
                        <input type="hidden" name="centro_costo" id="centro_costo_final" value="<?php echo htmlspecialchars($solicitud['centro_costo']); ?>">
                    </div>
                </div>
            </div>
            <div class="form-card">
                <div class="form-card-header"><i class="bi bi-file-earmark-text"></i>Detalles y Soporte Fiscal</div>
                <div class="card-body">
                    <div class="mb-3"><label for="descripcion" class="form-label">Descripción del Gasto</label><textarea class="form-control" id="descripcion" name="descripcion" rows="4" required><?php echo htmlspecialchars($solicitud['descripcion']); ?></textarea></div>
                    <div class="mb-3"><label for="observaciones" class="form-label">Observaciones Adicionales</label><textarea class="form-control" id="observaciones" name="observaciones" rows="2"><?php echo htmlspecialchars($solicitud['observaciones']); ?></textarea></div>
                    <div class="row g-3">
                        <div class="col-md-4"><label for="nit_proveedor" class="form-label">NIT del Proveedor</label><input type="text" class="form-control" id="nit_proveedor" name="nit_proveedor" value="<?php echo htmlspecialchars($solicitud['nit_proveedor']); ?>"></div>
                        <div class="col-md-4">
                            <label for="regimen_isr" class="form-label">Régimen ISR</label>
                            <select class="form-select" id="regimen_isr" name="regimen_isr">
                                <option value="">Seleccionar régimen</option>
                                <option value="Grande Contribuyente" <?php echo ($solicitud['regimen_isr'] == 'Grande Contribuyente') ? 'selected' : ''; ?>>Grande Contribuyente</option>
                                <option value="Mediano Contribuyente" <?php echo ($solicitud['regimen_isr'] == 'Mediano Contribuyente') ? 'selected' : ''; ?>>Mediano Contribuyente</option>
                                <option value="Pequeño Contribuyente" <?php echo ($solicitud['regimen_isr'] == 'Pequeño Contribuyente') ? 'selected' : ''; ?>>Pequeño Contribuyente</option>
                            </select>
                        </div>
                        <div class="col-md-4"><label for="correlativo" class="form-label">No. Correlativo</label><input type="text" class="form-control" id="correlativo" name="correlativo" value="<?php echo htmlspecialchars($solicitud['correlativo']); ?>"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna Lateral -->
        <div class="col-lg-4">
            <?php if (in_array($rol_usuario_actual, ['proveeduria', 'admin'])): ?>
                <!-- Tarjeta de Asignación de Aprobador (sin cambios) -->
            <?php endif; ?>
             <div class="form-card mb-4">
                <div class="form-card-header"><i class="bi bi-gear-fill"></i>Configuración</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="departamento_id" class="form-label">Departamento</label>
                        <select class="form-select" id="departamento_id" name="departamento_id" required>
                            <?php foreach ($departamentos as $depto): ?>
                                <option value="<?php echo $depto['id']; ?>" <?php echo ($solicitud['departamento_id'] == $depto['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($depto['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="prioridad" class="form-label">Prioridad</label>
                        <select class="form-select" id="prioridad" name="prioridad" required>
                            <option value="1" <?php echo ($solicitud['prioridad'] == 1) ? 'selected' : ''; ?>>Alta</option>
                            <option value="2" <?php echo ($solicitud['prioridad'] == 2) ? 'selected' : ''; ?>>Normal</option>
                            <option value="3" <?php echo ($solicitud['prioridad'] == 3) ? 'selected' : ''; ?>>Baja</option>
                        </select>
                    </div>
                    <div>
                        <label for="incluye_factura" class="form-label">Incluye Factura</label>
                        <select class="form-select" id="incluye_factura" name="incluye_factura" required>
                            <option value="SI" <?php echo ($solicitud['incluye_factura'] == 'SI') ? 'selected' : ''; ?>>Sí</option>
                            <option value="NO" <?php echo ($solicitud['incluye_factura'] == 'NO') ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-card">
                <div class="form-card-header"><i class="bi bi-info-circle-fill"></i>Contexto de la Solicitud</div>
                 <div class="card-body">
                    <div class="mb-3">
                        <label for="fecha_solicitud" class="form-label">Fecha de Solicitud</label>
                        <input type="date" class="form-control" id="fecha_solicitud" name="fecha_solicitud" value="<?php echo htmlspecialchars($solicitud['fecha_solicitud']); ?>" required>
                    </div>
                     <div class="mb-3">
                        <label for="fecha_utilizarse" class="form-label">Fecha a Utilizarse</label>
                        <input type="date" class="form-control" id="fecha_utilizarse" name="fecha_utilizarse" value="<?php echo htmlspecialchars($solicitud['fecha_utilizarse']); ?>">
                    </div>
                    <input type="hidden" name="solicita_fecha" value="<?php echo htmlspecialchars($solicitud['solicita_fecha']); ?>">
                    <input type="hidden" name="cargo" value="<?php echo htmlspecialchars($solicitud['cargo']); ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Acciones -->
    <div class="form-action-bar">
        <a href="solicitudes.php" class="btn btn-secondary">Cancelar</a>
        <button class="btn btn-primary btn-lg" type="submit">
            <i class="bi bi-arrow-clockwise me-2"></i>Actualizar y Reenviar
        </button>
    </div>
</form>

<?php require_once 'templates/layouts/footer.php'; ?>