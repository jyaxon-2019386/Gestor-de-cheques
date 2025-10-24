<?php
// crear_pago_sap.php
require_once 'includes/functions.php';
proteger_pagina();

// ===============================================================================
// INICIO: LÓGICA DE GESTIÓN DE EMPRESA SAP
// ===============================================================================

// 1. Si se está seleccionando una nueva empresa desde la URL
if (isset($_GET['empresa'])) {
    $empresas_validas = ['TEST_UNHESA_ZZZ', 'TEST_PROQUIMA_ZZZ']; // ['UNHESA', 'SBOPROQUIMA'];
    if (in_array($_GET['empresa'], $empresas_validas)) {
        // Guardar la empresa en la sesión
        $_SESSION['company_db'] = $_GET['empresa'];
        
        unset($_SESSION['sap_bank_accounts_TEST_UNHESA_ZZZ']);
        unset($_SESSION['sap_bank_accounts_TEST_UNHESA_ZZZ_timestamp']);
        unset($_SESSION['sap_bank_accounts_TEST_PROQUIMA_ZZZ']);
        unset($_SESSION['sap_bank_accounts_TEST_PROQUIMA_ZZZ_timestamp']);
        
        // Limpiar caché de cuentas predefinidas
        unset($_SESSION['sap_predefined_accounts']);
        unset($_SESSION['sap_predefined_accounts_timestamp']);


        // Redirigir para limpiar la URL de parámetros GET
        header('Location: crear_pago_sap.php');
        exit();
    }
}

// 2. Si no hay ninguna empresa seleccionada en la sesión, redirigir
if (empty($_SESSION['company_db'])) {
    header('Location: seleccionar_empresa.php');
    exit();
}

require_once 'templates/layouts/header.php';
?>

<!-- INICIO DEL FORMULARIO PARA PAGO SAP -->
<form id="form-pago-sap" method="POST" class="needs-validation" novalidate>
    
    <div id="notification-container"></div>
    
    <div class="mb-5">
        <?php generar_breadcrumbs(); ?>
        <div class="d-flex justify-content-between align-items-center">
             <h1 class="fs-2 text-white mt-2">Crear Pago Efectuado (SAP)</h1>
             <div class="fs-5 text-info">
                Empresa Activa: <strong class="text-white"><?php echo htmlspecialchars($_SESSION['company_db']); ?></strong>
             </div>
        </div>
        <p class="text-muted">Completa todos los campos para registrar el pago en el sistema contable.</p>
    </div>

    <div class="row g-4">
        <!-- Columna Izquierda -->
        <div class="col-lg-8">
            <div class="form-card mb-4">
                <div class="form-card-header"><i class="bi bi-info-circle-fill me-2"></i>Información General</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="DocDate" class="form-label">Fecha del Documento <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="DocDate" name="DocDate" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="DocCurrency" class="form-label">Moneda <span class="text-danger">*</span></label>
                        <select class="form-select" id="DocCurrency" name="DocCurrency" required style="pointer-events: none; background-color: #e9ecef;">
                            <option value="QTZ" selected>Quetzales (QTZ)</option>
                            <option value="USD">Dólares (USD)</option>
                        </select>
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
                        <label for="JournalRemarks" class="form-label">Confirmar Nombre Beneficiario <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="JournalRemarks" name="JournalRemarks" rows="2" placeholder="Descripción que aparecerá en la partida contable" required readonly></textarea>
                    </div>
                </div>
            </div>
            <div class="form-card mb-4">
                <div class="d-flex justify-content-between align-items-center form-card-header">
                    <span><i class="bi bi-journal-text me-2"></i>Cuentas Contables (Partidas)</span>
                    <button type="button" class="btn btn-sm btn-success" id="btn-agregar-cuenta" disabled>
                        <i class="bi bi-plus-circle me-1"></i>Añadir Cuenta
                    </button>
                </div>
                <div id="cuentas-container"></div>
                <div class="text-end mt-3 border-top pt-3">
                    <h5 class="text-white">Total a Pagar: <span id="total-pagar">Q 0.00</span></h5>
                </div>
            </div>
        </div>
        <!-- Columna Derecha -->
        <div class="col-lg-4">
            <div class="form-card">
                <div class="form-card-header"><i class="bi bi-bank me-2"></i>Registrar Pago Bancario</div>
                <div id="cheque-details-container">
                    <div class="row g-3 p-3">
                        <div class="col-12">
                            <label for="CheckAccount" class="form-label">Cuenta Bancaria (SAP) <span class="text-danger">*</span></label>
                            <select class="form-select" id="CheckAccount" name="PaymentChecks[CheckAccount]" required>
                                <option value="">Seleccionar cuenta bancaria...</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="BankNameDisplay" class="form-label">Banco</label>
                            <input type="text" class="form-control" id="BankNameDisplay" readonly>
                            <input type="hidden" id="BankCode" name="PaymentChecks[BankCode]">
                        </div>
                        <div class="col-12">
                            <label for="AccounttNum" class="form-label">Número de Cuenta</label>
                            <input type="text" class="form-control" id="AccounttNum" name="PaymentChecks[AccounttNum]" readonly>
                        </div>
                        <div class="col-12">
                            <label for="CheckNumber" class="form-label">Número de Cheque</label>
                            <input disabled type="number" class="form-control" id="CheckNumber" name="PaymentChecks[CheckNumber]" value="0">
                            <div class="form-text text-info-emphasis mt-2"><i class="bi bi-info-circle-fill me-1"></i>Nota: Este pago se registrará en SAP con la referencia de <strong>0</strong>.</div>
                        </div>
                        <div class="col-12">
                            <label for="CheckSum" class="form-label">Monto</label>
                            <input type="number" class="form-control" id="CheckSum" name="PaymentChecks[CheckSum]" step="0.01" readonly>
                            <small class="text-muted">Este valor se calcula automáticamente.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="form-action-bar">
        <a href="seleccionar_empresa.php" class="btn btn-outline-secondary">Cambiar Empresa</a>
        <div>
            <button class="btn btn-secondary" type="reset">Limpiar</button>
            <button class="btn btn-primary btn-lg" type="submit" id="submit-btn">
                <i class="bi bi-floppy-fill me-2"></i>
                <span id="submit-btn-text">Guardar para Aprobación</span>
            </button>
        </div>
    </div>
</form>

<template id="cuenta-template">
    <div class="cuenta-row row g-2 mb-3 align-items-center border-bottom pb-3">
        <div class="col-md-4">
            <label class="form-label small">Cuenta Contable <span class="text-danger">*</span></label>
            <select class="form-select form-select-sm account-code-select" name="cuentas[AccountCode][]" required></select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Monto <span class="text-danger">*</span></label>
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


<script src="js/crear_pago_sap.js"></script>
<?php require_once 'templates/layouts/footer.php'; ?>