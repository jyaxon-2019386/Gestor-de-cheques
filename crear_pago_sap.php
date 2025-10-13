<?php
// crear_pago_sap.php
require_once 'includes/functions.php';
proteger_pagina();

// Solo usuarios de finanzas o admins pueden acceder
if (!in_array($_SESSION['rol'], ['finanzas', 'admin'])) {
    die("Acceso denegado. No tienes permiso para acceder a esta página.");
}

require_once 'templates/layouts/header.php';
?>

<!-- INICIO DEL FORMULARIO PARA PAGO SAP -->
<form id="form-pago-sap" action="scripts/handle_pago_sap.php" method="POST" class="needs-validation" novalidate>
    
    <!-- Encabezado de la página -->
    <div class="mb-5">
        <?php generar_breadcrumbs(); // Asumiendo que añadirás esta página a la función ?>
        <h1 class="fs-2 text-white mt-2">Crear Pago Efectuado (SAP)</h1>
        <p class="text-muted">Completa todos los campos para registrar el pago en el sistema contable.</p>
    </div>

    <div class="row g-4">
        <!-- Columna Izquierda -->
        <div class="col-lg-8">
            <!-- Tarjeta de Información General -->
            <div class="form-card mb-4">
                <div class="form-card-header"><i class="bi bi-info-circle-fill me-2"></i>Información General</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="DocDate" class="form-label">Fecha del Documento <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="DocDate" name="DocDate" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="CardName" class="form-label">Nombre del Beneficiario <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="CardName" name="CardName" placeholder="Nombre completo del beneficiario" required>
                    </div>
                    <div class="col-12">
                        <label for="Remarks" class="form-label">Concepto del Pago (Observaciones) <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="Remarks" name="Remarks" rows="2" placeholder="Ej: Pago de factura #123 por servicios de consultoría" required></textarea>
                    </div>
                    <div class="col-12">
                        <label for="JournalRemarks" class="form-label">Descripción para Asiento Contable <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="JournalRemarks" name="JournalRemarks" rows="2" placeholder="Descripción que aparecerá en la partida contable" required></textarea>
                    </div>
                </div>
            </div>

            <!-- Tarjeta para Cuentas Contables (DINÁMICA) -->
            <div class="form-card mb-4">
                <div class="d-flex justify-content-between align-items-center form-card-header">
                    <span><i class="bi bi-journal-text me-2"></i>Cuentas Contables (Partidas)</span>
                    <button type="button" class="btn btn-sm btn-success" id="btn-agregar-cuenta">
                        <i class="bi bi-plus-circle me-1"></i>Añadir Cuenta
                    </button>
                </div>
                <div id="cuentas-container">
                    <!-- Las filas de cuentas se agregarán aquí dinámicamente -->
                </div>
                <div class="text-end mt-3 border-top pt-3">
                    <h5 class="text-white">Total a Pagar: <span id="total-pagar">Q 0.00</span></h5>
                </div>
            </div>
        </div>

        <!-- Columna Derecha -->
        <div class="col-lg-4">
            <!-- Tarjeta para Detalles del Cheque -->
            <div class="form-card">
                <div class="form-card-header"><i class="bi bi-bank me-2"></i>Detalles del Cheque</div>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="CheckNumber" class="form-label">Número de Cheque <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="CheckNumber" name="PaymentChecks[CheckNumber]" required>
                    </div>
                    <div class="col-12">
                        <label for="BankCode" class="form-label">Banco <span class="text-danger">*</span></label>
                        <select class="form-select" id="BankCode" name="PaymentChecks[BankCode]" required>
                            <option value="">Seleccionar...</option>
                            <option value="BI">Banco Industrial</option>
                            <option value="BAM">BAM</option>
                            <option value="G&T">G&T Continental</option>
                            <option value="BAC">BAC Credomatic</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="AccounttNum" class="form-label">Número de Cuenta <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="AccounttNum" name="PaymentChecks[AccounttNum]" required>
                    </div>
                    <div class="col-12">
                        <label for="CheckAccount" class="form-label">Cuenta de Cheque (SAP) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="CheckAccount" name="PaymentChecks[CheckAccount]" placeholder="Ej: _SYS00000000007" required>
                    </div>
                    <div class="col-12">
                        <label for="CheckSum" class="form-label">Monto del Cheque</label>
                        <input type="number" class="form-control" id="CheckSum" name="PaymentChecks[CheckSum]" step="0.01" readonly>
                        <small class="text-muted">Este valor se calcula automáticamente.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Barra de acciones flotante -->
    <div class="form-action-bar">
        <button class="btn btn-secondary" type="reset">Limpiar</button>
        <button class="btn btn-primary btn-lg" type="submit">
            <i class="bi bi-send-fill me-2"></i>Enviar a SAP
        </button>
    </div>
</form>

<!-- Plantilla para la fila de cuenta (estará oculta) -->
<template id="cuenta-template">
    <div class="cuenta-row row g-2 mb-3 align-items-center border-bottom pb-3">
        <div class="col-md-4">
            <label class="form-label small">Cód. Cuenta <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm" name="cuentas[AccountCode][]" placeholder="_SYS..." required>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Monto (Q) <span class="text-danger">*</span></label>
            <input type="number" class="form-control form-control-sm monto-cuenta" name="cuentas[SumPaid][]" step="0.01" min="0.01" required>
        </div>
        <div class="col-md-4">
            <label class="form-label small">Descripción <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm" name="cuentas[Decription][]" required>
        </div>
        <div class="col-md-1">
            <label class="form-label small d-block">&nbsp;</label>
            <button type="button" class="btn btn-sm btn-outline-danger btn-remover-cuenta">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </div>
</template>
<!-- FIN DEL FORMULARIO -->


<!-- JAVASCRIPT ESPECÍFICO PARA ESTA PÁGINA -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('cuentas-container');
    const template = document.getElementById('cuenta-template');
    const btnAgregar = document.getElementById('btn-agregar-cuenta');
    const totalPagarEl = document.getElementById('total-pagar');
    const checkSumInput = document.getElementById('CheckSum');

    // Función para añadir una nueva fila de cuenta
    function agregarFila() {
        const clone = template.content.cloneNode(true);
        container.appendChild(clone);
    }

    // Añadir una fila al cargar la página
    agregarFila();

    // Event listener para el botón de añadir
    btnAgregar.addEventListener('click', agregarFila);

    // Event listener para remover filas (usando delegación de eventos)
    container.addEventListener('click', function(e) {
        if (e.target && e.target.closest('.btn-remover-cuenta')) {
            e.target.closest('.cuenta-row').remove();
            calcularTotal();
        }
    });
    
    // Función para calcular y mostrar el total
    function calcularTotal() {
        let total = 0;
        const montos = container.querySelectorAll('.monto-cuenta');
        montos.forEach(input => {
            const valor = parseFloat(input.value) || 0;
            total += valor;
        });
        
        totalPagarEl.textContent = 'Q ' + total.toFixed(2);
        checkSumInput.value = total.toFixed(2); // Actualizar el monto del cheque
    }

    // Calcular el total cada vez que se modifica un campo de monto
    container.addEventListener('input', function(e) {
        if (e.target && e.target.classList.contains('monto-cuenta')) {
            calcularTotal();
        }
    });
});
</script>

<?php require_once 'templates/layouts/footer.php'; ?>
