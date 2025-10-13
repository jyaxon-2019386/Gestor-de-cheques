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

<!-- =============================================================== -->
<!-- ============= CSS PARA LAS NOTIFICACIONES (TOAST) ============= -->
<!-- =============================================================== -->
<style>
#notification-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.toast-notification {
    min-width: 300px;
    max-width: 400px;
    padding: 15px 20px;
    border-radius: 8px;
    color: #fff;
    background-color: #333; /* Color por defecto */
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 15px;
    opacity: 0;
    transform: translateX(100%);
    animation: slideIn 0.5s forwards;
    word-break: break-word;
}

.toast-notification.fade-out {
    animation: slideOut 0.5s forwards;
}

/* Tipos de notificación */
.toast-notification.success { background-color: #28a745; }
.toast-notification.error { background-color: #dc3545; }
.toast-notification.info { background-color: #17a2b8; }

.toast-notification i {
    font-size: 1.5rem;
    line-height: 1;
}

@keyframes slideIn {
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideOut {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(100%);
    }
}
</style>


<!-- INICIO DEL FORMULARIO PARA PAGO SAP -->
<form id="form-pago-sap" method="POST" class="needs-validation" novalidate> <!-- Se elimina 'action' para manejarlo con JS -->
    
    <!-- Contenedor para las notificaciones -->
    <div id="notification-container"></div>
    
    <!-- Encabezado de la página -->
    <div class="mb-5">
        <?php generar_breadcrumbs(); // Asumiendo que esta función existe y está configurada ?>
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
                    <button type="button" class="btn btn-sm btn-success" id="btn-agregar-cuenta" disabled>
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
                <div class="form-card-header">
                     <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="toggle-cheque">
                        <label class="form-check-label" for="toggle-cheque"><i class="bi bi-bank me-2"></i>Registrar Pago con Cheque</label>
                    </div>
                </div>
                <div id="cheque-details-container" style="display: none;">
                    <div class="row g-3 p-3">
                        <div class="col-12">
                            <label for="CheckNumber" class="form-label">Número de Cheque <span class="text-danger cheque-required-indicator" style="display: none;">*</span></label>
                            <input type="number" class="form-control" id="CheckNumber" name="PaymentChecks[CheckNumber]">
                        </div>
                        <div class="col-12">
                            <label for="BankCode" class="form-label">Banco <span class="text-danger cheque-required-indicator" style="display: none;">*</span></label>
                            <select class="form-select" id="BankCode" name="PaymentChecks[BankCode]">
                                <option value="">Seleccionar...</option>
                                <option value="BI">Banco Industrial</option>
                                <option value="BAM">BAM</option>
                                <option value="G&T">G&T Continental</option>
                                <option value="BAC">BAC Credomatic</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="AccounttNum" class="form-label">Número de Cuenta <span class="text-danger cheque-required-indicator" style="display: none;">*</span></label>
                            <input type="text" class="form-control" id="AccounttNum" name="PaymentChecks[AccounttNum]">
                        </div>
                        <div class="col-12">
                            <label for="CheckAccount" class="form-label">Cuenta de Cheque (SAP) <span class="text-danger cheque-required-indicator" style="display: none;">*</span></label>
                            <input type="text" class="form-control" id="CheckAccount" name="PaymentChecks[CheckAccount]" placeholder="Ej: _SYS00000000007">
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
    </div>
    
    <!-- Barra de acciones flotante -->
    <div class="form-action-bar">
        <button class="btn btn-secondary" type="reset">Limpiar</button>
        <button class="btn btn-primary btn-lg" type="submit" id="submit-btn">
            <i class="bi bi-send-fill me-2"></i>
            <span id="submit-btn-text">Enviar a SAP</span>
        </button>
    </div>
</form>

<!-- Plantilla para la fila de cuenta (estará oculta) -->
<template id="cuenta-template">
    <div class="cuenta-row row g-2 mb-3 align-items-center border-bottom pb-3">
        <div class="col-md-4">
            <label class="form-label small">Cuenta Contable <span class="text-danger">*</span></label>
            <select class="form-select form-select-sm account-code-select" name="cuentas[AccountCode][]" required></select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Monto (Q) <span class="text-danger">*</span></label>
            <input type="number" class="form-control form-control-sm monto-cuenta" name="cuentas[SumPaid][]" step="0.01" min="0.01" required>
        </div>
        <div class="col-md-4">
            <label class="form-label small">Descripción <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm account-description-input" name="cuentas[Decription][]" required readonly>
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button type="button" class="btn btn-sm btn-outline-danger btn-remover-cuenta w-100"><i class="bi bi-trash"></i></button>
        </div>
    </div>
</template>


<!-- JAVASCRIPT ESPECÍFICO PARA ESTA PÁGINA -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Selección de Elementos del DOM ---
    const form = document.getElementById('form-pago-sap');
    const submitBtn = document.getElementById('submit-btn');
    const submitBtnText = document.getElementById('submit-btn-text');
    const container = document.getElementById('cuentas-container');
    const template = document.getElementById('cuenta-template');
    const btnAgregar = document.getElementById('btn-agregar-cuenta');
    const totalPagarEl = document.getElementById('total-pagar');
    const checkSumInput = document.getElementById('CheckSum');
    const toggleCheque = document.getElementById('toggle-cheque');
    const chequeContainer = document.getElementById('cheque-details-container');
    
    let sapAccountsList = [];

    // --- SISTEMA DE NOTIFICACIONES ---
    function showNotification(message, type = 'info') {
        const container = document.getElementById('notification-container');
        const notif = document.createElement('div');
        notif.className = `toast-notification ${type}`;
        
        const iconClass = type === 'success' ? 'bi-check-circle-fill' : (type === 'error' ? 'bi-x-circle-fill' : 'bi-info-circle-fill');
        notif.innerHTML = `<i class="bi ${iconClass}"></i><div>${message}</div>`;
        
        container.appendChild(notif);

        setTimeout(() => {
            notif.classList.add('fade-out');
            notif.addEventListener('animationend', () => notif.remove());
        }, 5000);
    }

    // --- LÓGICA DE CARGA DE CUENTAS ---
    async function cargarCuentasSAP() {
        try {
            const response = await fetch('ajax/get_predefined_accounts.php');
            if (!response.ok) throw new Error(`Error ${response.status}: ${response.statusText}`);
            
            const result = await response.json();
            if (result.status === 'success') {
                sapAccountsList = result.data;
                agregarFila();
                btnAgregar.disabled = false;
            } else {
                showNotification(result.message, 'error');
            }
        } catch (error) {
            console.error('Fallo al cargar cuentas de SAP:', error);
            showNotification('Error de red al cargar las cuentas contables.', 'error');
        }
    }

    // --- FUNCIONES AUXILIARES DEL FORMULARIO ---
    function agregarFila() {
        const clone = template.content.cloneNode(true);
        const select = clone.querySelector('.account-code-select');
        select.appendChild(new Option('Seleccione una cuenta...', ''));
        if (sapAccountsList.length > 0) {
            sapAccountsList.forEach(account => {
                const optionText = `${account.Name} (${account.Code})`;
                select.appendChild(new Option(optionText, account.Code));
            });
        }
        container.appendChild(clone);
    }

    function calcularTotal() {
        let total = 0;
        const montos = container.querySelectorAll('.monto-cuenta');
        montos.forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        
        totalPagarEl.textContent = 'Q ' + total.toFixed(2);
        checkSumInput.value = total.toFixed(2);
    }

    // --- MANEJO DE EVENTOS ---
    
    // Envío del formulario con AJAX
    form.addEventListener('submit', async function(event) {
        event.preventDefault();
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            showNotification('Por favor, completa todos los campos requeridos.', 'error');
            return;
        }

        submitBtn.disabled = true;
        submitBtnText.textContent = 'Enviando...';

        try {
            const formData = new FormData(form);
            const response = await fetch('scripts/handle_pago_sap.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (!response.ok) throw new Error(result.message || 'Error desconocido del servidor.');
            
            showNotification(result.message, 'success');
            form.reset();
            container.innerHTML = '';
            agregarFila();
            calcularTotal();
            form.classList.remove('was-validated');
            // Opcional: Si el toggle del cheque estaba activo, ocultarlo de nuevo
            if (toggleCheque.checked) {
                toggleCheque.dispatchEvent(new Event('change'));
            }

        } catch (error) {
            console.error('Error al enviar el pago:', error);
            showNotification(error.message, 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtnText.textContent = 'Enviar a SAP';
        }
    });

    // Añadir nueva fila de cuenta
    btnAgregar.addEventListener('click', agregarFila);
    
    // Delegación de eventos para filas dinámicas
    container.addEventListener('click', function(e) {
        if (e.target && e.target.closest('.btn-remover-cuenta')) {
            e.target.closest('.cuenta-row').remove();
            calcularTotal();
        }
    });
    
    container.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('account-code-select')) {
            const select = e.target;
            const fila = select.closest('.cuenta-row');
            const descriptionInput = fila.querySelector('.account-description-input');
            const selectedOption = select.options[select.selectedIndex];
            
            descriptionInput.value = select.value ? selectedOption.text.split(' (')[0] : '';
        }
    });

    container.addEventListener('input', function(e) {
        if (e.target && e.target.classList.contains('monto-cuenta')) {
            calcularTotal();
        }
    });

    // Toggle para la sección de cheque
    toggleCheque.addEventListener('change', function() {
        const isChecked = this.checked;
        const chequeInputs = chequeContainer.querySelectorAll('input, select');
        const chequeRequiredIndicators = chequeContainer.querySelectorAll('.cheque-required-indicator');

        chequeContainer.style.display = isChecked ? 'block' : 'none';
        chequeRequiredIndicators.forEach(span => span.style.display = isChecked ? 'inline' : 'none');
        chequeInputs.forEach(input => {
            if (!input.readOnly) input.required = isChecked;
        });
    });

    // --- INICIALIZACIÓN ---
    cargarCuentasSAP();
});
</script>

<?php require_once 'templates/layouts/footer.php'; ?>