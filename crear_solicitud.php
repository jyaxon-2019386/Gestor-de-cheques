<?php
// crear_solicitud.php - VERSIÓN REDISEÑO FINAL
require_once 'includes/functions.php';
proteger_pagina();

// Validar que el tipo viene en la URL
$tipo = isset($_GET['tipo']) ? htmlspecialchars($_GET['tipo']) : null;
if ($tipo !== 'factura' && $tipo !== 'cotizacion') {
    header('Location: nueva_solicitud.php');
    exit();
}

// Obtener departamentos para el dropdown
require_once 'config/database.php';
$departamentos = $conexion->query("SELECT id, nombre FROM departamentos ORDER BY nombre ASC");

// --- OBTENER LA LISTA DE APROBADORES MEJORADA ---
$sql_aprobadores = "SELECT u.id, u.nombre_usuario, u.rol, d.nombre as departamento_nombre
                    FROM usuarios u
                    LEFT JOIN departamentos d ON u.departamento_id = d.id
                    WHERE u.rol IN ('jefe_de_area', 'gerente', 'admin')
                    ORDER BY u.nombre_usuario ASC";
$lista_aprobadores = $conexion->query($sql_aprobadores);

// Títulos dinámicos
$titulos = [
    'factura' => 'Solicitud de Cheque - Factura',
    'cotizacion' => 'Solicitud de Cheque - Cotización'
];

$titulo = $titulos[$tipo] ?? 'Solicitud de Cheque';

// Obtener el rol del usuario actual para la condición
$rol_usuario_actual = $_SESSION['rol'];

require_once 'templates/layouts/header.php';
?>

<form id="form-cheque" class="needs-validation" novalidate>
    <input type="hidden" name="tipo_soporte" value="<?php echo htmlspecialchars($tipo); ?>">

    <!-- Stepper de Progreso y Título -->
    <div class="mb-5">
        <?php generar_breadcrumbs(); ?>
        <h1 class="fs-2 text-white mt-2"><?php echo $titulo; ?></h1>
    </div>
    
    <!-- Nuevo Layout de Dos Columnas con Tarjetas "Glass" -->
    <div class="row g-4">
        <!-- Columna Principal -->
        <div class="col-lg-8">
            <div class="form-card mb-4">
                <div class="form-card-header"><i class="bi bi-person-vcard"></i>Beneficiario y Monto</div>
                <div class="row g-3">
                    <!-- NUEVO CAMPO: EMPRESA SAP -->
                    <div class="col-12">
                        <label for="empresa_sap" class="form-label">Empresa SAP</label>
                        <select class="form-select" id="empresa_sap" name="empresa_sap">
                            <option value="PROQUIMA">PROQUIMA</option>
                            <option value="UNHESA">UNHESA</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="nombre_cheque" class="form-label">Nombre del Beneficiario <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre_cheque" name="nombre_cheque" required>
                    </div>
                    <div class="col-md-6">
                        <label for="empresa" class="form-label">Empresa</label>
                        <input type="text" class="form-control" id="empresa" name="empresa">
                    </div>
                    <div class="col-md-6">
                        <label for="valor_quetzales" class="form-label">Monto en Quetzales <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="valor_quetzales" name="valor_quetzales" step="0.01" min="0" required>
                    </div>
                    <div class="col-md-6">
                        <label for="valor_dolares" class="form-label">Monto en Dólares</label>
                        <input type="number" class="form-control" id="valor_dolares" name="valor_dolares" step="0.01" min="0">
                    </div>
                    
                    <!-- NUEVOS CAMPOS: CENTROS DE COSTO -->
                    <div class="col-md-3">
                        <label for="cc_nivel1" class="form-label">Centro de Costo Nivel 1</label>
                        <select class="form-select cc-selector" id="cc_nivel1" data-level="1"></select>
                    </div>
                     <div class="col-md-3">
                        <label for="cc_nivel2" class="form-label">Nivel 2</label>
                        <select class="form-select cc-selector" id="cc_nivel2" data-level="2" disabled></select>
                    </div>
                     <div class="col-md-3">
                        <label for="cc_nivel3" class="form-label">Nivel 3</label>
                        <select class="form-select cc-selector" id="cc_nivel3" data-level="3" disabled></select>
                    </div>
                     <div class="col-md-3">
                        <label for="cc_nivel4" class="form-label">Nivel 4</label>
                        <select class="form-select cc-selector" id="cc_nivel4" data-level="4" disabled></select>
                    </div>
                    <!-- Campo oculto para guardar el valor final -->
                    <input type="hidden" name="centro_costo" id="centro_costo_final">
                </div>
            </div>
            <div class="form-card">
                <div class="form-card-header"><i class="bi bi-file-earmark-text"></i>Detalles y Soporte Fiscal</div>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="descripcion" class="form-label">Descripción del Gasto <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3" required></textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="nit_proveedor" class="form-label">NIT del Proveedor</label>
                        <input type="text" class="form-control" id="nit_proveedor" name="nit_proveedor">
                    </div>
                    <div class="col-md-6">
                        <label for="regimen_isr" class="form-label">Régimen ISR</label>
                        <select class="form-select" id="regimen_isr" name="regimen_isr">
                            <option value="">Seleccionar régimen</option>
                            <option value="Grande Contribuyente">Grande Contribuyente</option>
                            <option value="Mediano Contribuyente">Mediano Contribuyente</option>
                            <option value="Pequeño Contribuyente">Pequeño Contribuyente</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="correlativo" class="form-label">Correlativo</label>
                        <input type="text" class="form-control" id="correlativo" name="correlativo">
                    </div>
                    <div class="col-md-6">
                        <label for="incluye_factura" class="form-label">Incluye Factura</label>
                        <select class="form-select" id="incluye_factura" name="incluye_factura" required>
                            <option value="">Seleccionar</option>
                            <option value="Sí">Sí</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna Lateral -->
        <div class="col-lg-4">
            <?php
            // --- LA CORRECCIÓN CLAVE: CONDICIÓN DE ROL ---
            // Define aquí los roles que pueden ver esta tarjeta
            $roles_permitidos_para_asignar = ['proveeduria', 'admin'];

            if (in_array($rol_usuario_actual, $roles_permitidos_para_asignar)): 
            ?>
                <div class="form-card mb-4">
                    <div class="form-card-header"><i class="bi bi-person-check-fill"></i>Asignación de Aprobador</div>
                    <div class="card-body">
                        <label for="aprobador_manual_id" class="form-label">Enviar aprobación a:</label>
                        <select class="form-select" id="aprobador_manual_id" name="aprobador_manual_id">
                            <option value="">Automático (según flujo)</option>
                            
                            <?php
                            // Reiniciar el puntero del resultado para poder recorrerlo de nuevo
                            $lista_aprobadores->data_seek(0);
                            while($aprobador = $lista_aprobadores->fetch_assoc()):
                            ?>
                                <?php
                                    $rol_formateado = ucfirst(str_replace('_', ' de ', $aprobador['rol']));
                                    $departamento = htmlspecialchars($aprobador['departamento_nombre'] ?? 'N/A');
                                ?>
                                <option value="<?php echo $aprobador['id']; ?>">
                                    <?php echo htmlspecialchars($aprobador['nombre_usuario']); ?> 
                                    (<?php echo $rol_formateado; ?> - <?php echo $departamento; ?>)
                                </option>
                            <?php endwhile; ?>

                        </select>
                        <small class="form-text text-muted">Selecciona un aprobador si esta solicitud es para otro departamento.</small>
                    </div>
                </div>
            <?php endif; // --- FIN DE LA CONDICIÓN DE ROL --- ?>
             <div class="form-card mb-4">
                <div class="form-card-header"><i class="bi bi-gear-fill"></i>Configuración</div>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="departamento_id" class="form-label">Departamento <span class="text-danger">*</span></label>
                        <select class="form-select" id="departamento_id" name="departamento_id" required>
                            <option value="">Seleccionar departamento</option>
                            <?php while($depto = $departamentos->fetch_assoc()): ?>
                                <option value="<?php echo $depto['id']; ?>">
                                    <?php echo htmlspecialchars($depto['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="prioridad" class="form-label">Prioridad</label>
                        <select class="form-select" id="prioridad" name="prioridad">
                            <option value="1">Baja</option>
                            <option value="2" selected>Normal</option>
                            <option value="3">Alta</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="fecha_utilizarse" class="form-label">Fecha de Utilización</label>
                        <!-- LA CORRECCIÓN CLAVE 2: Añadir el atributo 'min' -->
                        <input type="date" class="form-control" id="fecha_utilizarse" name="fecha_utilizarse"
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
            </div>
            <div class="form-card">
                <div class="form-card-header"><i class="bi bi-info-circle-fill"></i>Contexto de la Solicitud</div>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="fecha_solicitud" class="form-label">Fecha de Solicitud <span class="text-danger">*</span></label>
                        <!-- LA CORRECCIÓN CLAVE 1: Añadir el atributo 'min' -->
                        <input type="date" class="form-control" id="fecha_solicitud" name="fecha_solicitud" 
                               value="<?php echo date('Y-m-d'); ?>" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-12">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Campos ocultos que faltaban -->
    <input type="hidden" name="solicita_fecha" value="<?php echo date('Y-m-d'); ?>">
    <input type="hidden" name="cargo" value="Solicitante Web">

    <!-- Barra de Acciones Flotante -->
    <div class="form-action-bar">
        <a href="nueva_solicitud.php" class="btn btn-secondary">Cancelar</a>
        <button class="btn btn-primary btn-lg" type="submit">
            <i class="bi bi-check-circle-fill me-2"></i>Guardar Solicitud
        </button>
    </div>
</form>

<?php require_once 'templates/layouts/footer.php'; ?>
<?php require_once 'templates/layouts/footer.php'; ?>
