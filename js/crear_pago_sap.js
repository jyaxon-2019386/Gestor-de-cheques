// js/crear_pago_sap.js

document.addEventListener('DOMContentLoaded', function() {
    // --- REFERENCIAS A ELEMENTOS DEL DOM ---
    const form = document.getElementById('form-pago-sap');
    const submitBtn = document.getElementById('submit-btn');
    const submitBtnText = document.getElementById('submit-btn-text');
    const container = document.getElementById('cuentas-container');
    const template = document.getElementById('cuenta-template');
    const btnAgregar = document.getElementById('btn-agregar-cuenta');
    const totalPagarEl = document.getElementById('total-pagar');
    const checkSumInput = document.getElementById('CheckSum');
    const checkAccountSelect = document.getElementById('CheckAccount');
    const bankNameDisplay = document.getElementById('BankNameDisplay');
    const bankCodeInput = document.getElementById('BankCode');
    const accountNumInput = document.getElementById('AccounttNum');
    const currencySelect = document.getElementById('DocCurrency');
    const cardNameInput = document.getElementById('CardName');
    const journalRemarksInput = document.getElementById('JournalRemarks');
    
    // --- VARIABLES GLOBALES ---
    let sapAccountsList = [];
    
    // --- SINCRONIZACIÓN DE CAMPO DE BENEFICIARIO ---
    cardNameInput.addEventListener('input', () => {
        journalRemarksInput.value = cardNameInput.value;
    });
    
    // --- FUNCIONES AUXILIARES ---

    /**
     * Muestra una notificación toast en la esquina de la pantalla.
     * @param {string} message El mensaje a mostrar.
     * @param {string} type 'info', 'success', o 'error'.
     */
    function showNotification(message, type = 'info') {
        const container = document.getElementById('notification-container');
        if (!container) return;
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

    /**
     * Carga las cuentas contables predefinidas desde el servidor.
     */
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

    /**
     * Carga las cuentas bancarias desde el servidor y las añade al select.
     */
    async function cargarCuentasBancarias() {
        try {
            const response = await fetch('ajax/get_bank_accounts.php');
            if (!response.ok) throw new Error(`Error ${response.status}: ${response.statusText}`);
            const result = await response.json();
            if (result.status === 'success') {
                checkAccountSelect.innerHTML = '<option value="">Seleccionar cuenta bancaria...</option>';
                result.data.forEach(account => {
                    const optionText = account.AccountName || `Cuenta sin nombre (${account.GLAccount})`;
                    const option = new Option(optionText, account.GLAccount);
                    option.dataset.bankCode = account.BankCode || '';
                    option.dataset.accountNum = account.AccNo || '';
                    option.dataset.currency = account.Currency || 'QTZ';
                    option.dataset.bankName = account.AccountName ? account.AccountName.split('#')[0].trim() : 'N/A';
                    checkAccountSelect.appendChild(option);
                });
            } else {
                showNotification(result.message, 'error');
            }
        } catch (error) {
            console.error('Fallo al cargar cuentas bancarias de SAP:', error);
            showNotification('Error de red al cargar las cuentas bancarias.', 'error');
        }
    }

    /**
     * Añade una nueva fila para una cuenta contable al formulario.
     */
    function agregarFila() {
        const clone = template.content.cloneNode(true);
        const select = clone.querySelector('.account-code-select');
        select.appendChild(new Option('Seleccione una cuenta...', ''));
        sapAccountsList.forEach(account => select.appendChild(new Option(account.Name, account.Code)));
        container.appendChild(clone);
    }

    /**
     * Calcula el total de los montos y actualiza la UI.
     */
    function calcularTotal() {
        let total = 0;
        container.querySelectorAll('.monto-cuenta').forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        const currencySymbol = currencySelect.value === 'USD' ? '$' : 'Q';
        const formattedTotal = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        totalPagarEl.textContent = `${currencySymbol} ${formattedTotal}`;
        checkSumInput.value = total.toFixed(2);
    }

    // --- EVENT LISTENERS ---

    // Envío del formulario
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form.checkValidity()) {
            event.stopPropagation();
            form.classList.add('was-validated');
            showNotification('Por favor, completa todos los campos requeridos.', 'error');
            return;
        }
        submitBtn.disabled = true;
        submitBtnText.textContent = 'Guardando...';
        try {
            const formData = new FormData(form);
            const response = await fetch('scripts/handle_pago_sap.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || `Error del servidor: ${response.status}`);
            showNotification(result.message, 'success');
            form.reset();
            form.classList.remove('was-validated');
            container.innerHTML = '';
            journalRemarksInput.value = '';
            currencySelect.value = 'QTZ';
            agregarFila();
            calcularTotal();
        } catch (error) {
            console.error('Error al guardar la solicitud:', error);
            showNotification(error.message, 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtnText.textContent = 'Guardar para Aprobación';
        }
    });

    // Botón para añadir nueva cuenta
    btnAgregar.addEventListener('click', agregarFila);
    
    // Delegación de eventos para el contenedor de cuentas
    container.addEventListener('click', (e) => {
        if (e.target && e.target.closest('.btn-remover-cuenta')) {
            e.target.closest('.cuenta-row').remove();
            calcularTotal();
        }
    });
    
    container.addEventListener('change', (e) => {
        if (e.target.classList.contains('account-code-select')) {
            const select = e.target;
            const fila = select.closest('.cuenta-row');
            const descriptionInput = fila.querySelector('.account-description-input');
            const selectedOption = select.options[select.selectedIndex];
            descriptionInput.value = select.value ? selectedOption.text.split(' (')[0] : '';
        }
    });

    container.addEventListener('input', (e) => {
        if (e.target.classList.contains('monto-cuenta')) {
            calcularTotal();
        }
    });

    // Cambio en la selección de cuenta bancaria
    checkAccountSelect.addEventListener('change', (e) => {
        const selectedOption = e.target.options[e.target.selectedIndex];
        if (e.target.value) {
            bankNameDisplay.value = selectedOption.dataset.bankName;
            bankCodeInput.value = selectedOption.dataset.bankCode;
            accountNumInput.value = selectedOption.dataset.accountNum;
            currencySelect.value = selectedOption.dataset.currency;
        } else {
            bankNameDisplay.value = '';
            bankCodeInput.value = '';
            accountNumInput.value = '';
            currencySelect.value = 'QTZ'; // Volver a moneda por defecto
        }
        calcularTotal(); // Recalcular para actualizar el símbolo (Q o $)
    });

    // --- INICIALIZACIÓN ---
    cargarCuentasSAP();
    cargarCuentasBancarias();
});