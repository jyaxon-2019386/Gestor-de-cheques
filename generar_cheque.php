<?php
// generar_cheque.php - VERSIÓN FINAL CON TODOS LOS CAMPOS
require_once 'includes/functions.php';
proteger_pagina();
require_once 'config/database.php';

// Validar que se ha proporcionado un ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Error: No se ha especificado una solicitud válida.");
}
$solicitud_id = intval($_GET['id']);

// Consulta SQL completa para obtener todos los datos necesarios
$sql = "SELECT s.*, u.nombre_usuario, d.nombre as departamento_nombre 
        FROM solicitud_cheques s
        JOIN usuarios u ON s.usuario_id = u.id 
        LEFT JOIN departamentos d ON s.departamento_id = d.id
        WHERE s.id = ?";
        
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $solicitud_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    die("Error: La solicitud no fue encontrada.");
}
$cheque = $resultado->fetch_assoc();

// Función para renderizar un solo formulario de cheque
function render_cheque_form($cheque, $copy_text = "Original: Finanzas") {
?>
<div class="cheque-form">
    <header class="cheque-header">
        <h2>SOLICITUD DE CHEQUE</h2>
        <!-- LOGO OFICIAL INTEGRADO -->
        <img src="https://grupoeconsa.com/wp-content/uploads/2025/04/Logo-Econsa-Transparencia.png.webp" alt="Logo ECONSA" class="logo">
    </header>

    <div class="form-grid">
        <!-- Fila 1 -->
        <div class="field col-3"><label>Fecha:</label><span class="value"><?php echo date("d/m/Y", strtotime($cheque['fecha_solicitud'])); ?></span></div>
        <div class="field col-3"><label>Solicita:</label><span class="value"><?php echo $cheque['solicita_fecha'] ? date("d/m/Y", strtotime($cheque['solicita_fecha'])) : ''; ?></span></div>
        <div class="field col-3"><label>Cargo:</label><span class="value"><?php echo htmlspecialchars($cheque['cargo'] ?? ''); ?></span></div>
        <div class="field col-3"><label>Departamento:</label><span class="value"><?php echo htmlspecialchars($cheque['departamento_nombre'] ?? 'N/A'); ?></span></div>
        
        <!-- Fila 2 -->
        <div class="field col-12 large-label"><label>Cheque a Nombre de:</label><span class="value"><?php echo htmlspecialchars($cheque['nombre_cheque'] ?? ''); ?></span></div>

        <!-- Fila 3 -->
        <div class="field col-3"><label>Valor:</label><span class="value"></span></div>
        <div class="field col-3"><label>Quetzales:</label><span class="value"><?php echo number_format($cheque['valor_quetzales'] ?? 0, 2); ?></span></div>
        <div class="field col-2"><label>Dólares:</label><span class="value"><?php echo number_format($cheque['valor_dolares'] ?? 0, 2); ?></span></div>
        <div class="field col-4"><label>Emitir de Empresa:</label><span class="value"><?php echo htmlspecialchars($cheque['empresa'] ?? ''); ?></span></div>

        <!-- Fila 4 -->
        <div class="field col-6 shaded"><label>Centro de Costo (División):</label><span class="value-shaded"><?php echo htmlspecialchars($cheque['centro_costo'] ?? ''); ?></span></div>
        <div class="field col-3 no-label"><label>No. Correlativo</label><span class="value-box small"><?php echo htmlspecialchars($cheque['correlativo'] ?? ''); ?></span></div>
        <div class="field col-3 no-label"><label>Incluye factura</label><span class="value-box small"><?php echo htmlspecialchars($cheque['incluye_factura'] ?? ''); ?></span></div>

        <!-- Fila 5 -->
        <div class="field col-12 no-label"><label>Descripción Detallada:</label><div class="value-box large"><?php echo nl2br(htmlspecialchars($cheque['descripcion'] ?? '')); ?></div></div>

        <!-- Fila 6 -->
        <div class="field col-4"><label>Fecha a utilizarse:</label><span class="value"><?php echo $cheque['fecha_utilizarse'] ? date("d/m/Y", strtotime($cheque['fecha_utilizarse'])) : ''; ?></span></div>
        <div class="field col-3"><label>Régimen ISR:</label><span class="value"><?php echo htmlspecialchars($cheque['regimen_isr'] ?? ''); ?></span></div>
        <div class="field col-5"><label>NIT Proveedor:</label><span class="value"><?php echo htmlspecialchars($cheque['nit_proveedor'] ?? ''); ?></span></div>
    </div>

    <!-- Sección de Entrega y Firmas -->
    <div class="section-divider"></div>
    <div class="signatures-grid">
        <div class="signature-box"><div class="line"></div><label>Firma del Solicitante</label></div>
        <div class="signature-box"><div class="line"></div><label>Vo. Bo. Jefe de Área</label></div>
        <div class="signature-box"><div class="line"></div><label>Vo. Bo. Gerente de Área</label></div>
        <div class="finance-delivery">
            <label>Entregado a Finanzas:</label>
            <div class="delivery-fields">
                <div><div class="line-sm"></div><span>Fecha</span></div>
                <div><div class="line-sm"></div><span>Hora</span></div>
                <div><div class="line-sm"></div><span>Firma</span></div>
            </div>
        </div>
    </div>

    <!-- Sección de Finanzas -->
    <div class="finance-section">
        <div class="finance-title">Uso Exclusivo Departamento de Finanzas</div>
        <div class="finance-grid">
            <div class="field col-4"><label>Autorizado Por:</label><span class="value"><?php echo htmlspecialchars($cheque['autorizado_por'] ?? ''); ?></span></div>
            <div class="field col-4"><label>Banco:</label><span class="value"><?php echo htmlspecialchars($cheque['banco'] ?? ''); ?></span></div>
            <!-- NUEVO CAMPO -->
            <div class="field col-4"><label>Fecha Pago Programada:</label><span class="value"><?php echo $cheque['fecha_programada_pago'] ? date("d/m/Y", strtotime($cheque['fecha_programada_pago'])) : ''; ?></span></div>
            
            <div class="field col-3 no-label"><label>Prioridad:</label><span class="value-box priority"><?php echo $cheque['prioridad']; ?></span></div>
            <div class="col-12"><label style="font-size: 8pt; text-transform: none;">Prioridades: 1 = Emisión inmediata 2 = Emisión 24 horas 3 = Emisión con pro</label></div>
            <div class="field col-12 no-label"><label>Observaciones:</label><div class="value-box large obs"><?php echo nl2br(htmlspecialchars($cheque['observaciones'] ?? '')); ?></div></div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="cheque-footer">
        <div class="footer-line"></div>
        <span><?php echo $copy_text; ?></span>
    </footer>
</div>
<?php
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud de Cheque #<?php echo $cheque['id']; ?></title>
    <link rel="stylesheet" href="assets/css/cheque-print.css"> <!-- Archivo CSS dedicado -->
</head>
<body>
    <div class="page-container">
        <?php render_cheque_form($cheque, "Original: Finanzas"); ?>
        <?php render_cheque_form($cheque, "Copia: Contabilidad"); ?>
    </div>
    <div class="no-print action-buttons">
        <button onclick="window.print()">Imprimir</button>
        <a href="javascript:history.back()">Volver</a>
    </div>
</body>
</html>