<?php
// nueva_solicitud.php - VERSIÓN FINAL CORREGIDA
require_once 'includes/functions.php';
proteger_pagina();
require_once 'templates/layouts/header.php';
?>

<!-- Contenido Principal -->
<div class="container-fluid">
    <!-- Encabezado y Stepper de Progreso -->
    <div class="text-center mb-5">
        
        <!-- Icono "+" ELIMINADO de esta versión -->

        <h1 class="display-5 fw-bold text-white">Nueva Solicitud de Cheque</h1>
        <p class="lead text-muted">Selecciona el tipo de soporte para iniciar tu proceso de solicitud.</p>

        <!-- Stepper de Progreso -->
        <div class="d-flex justify-content-center mt-4">
            <div class="step-item active">
                <div class="step-circle">1</div>
                <div class="step-label">Tipo</div>
            </div>
            <div class="step-connector"></div>
            <div class="step-item">
                <div class="step-circle">2</div>
                <div class="step-label">Datos</div>
            </div>
            <div class="step-connector"></div>
            <div class="step-item">
                <div class="step-circle">3</div>
                <div class="step-label">Revisión</div>
            </div>
        </div>
    </div>

    <!-- Título de la Sección de Tipos -->
    <h3 class="text-center mb-4 text-white">Tipos de Soporte Disponibles</h3>

    <!-- Tarjetas de Selección con ESTRUCTURA CORREGIDA -->
    <div class="row justify-content-center g-4">
        
        <!-- Tarjeta: Factura Comercial (envuelta en su columna) -->
        <div class="col-lg-5">
            <div class="support-card h-100">
                <div class="card-icon-wrapper"><i class="bi bi-receipt-cutoff"></i></div>
                <h3>Factura Comercial</h3>
                <a href="crear_solicitud.php?tipo=factura" class="btn btn-primary-gradient mt-auto">
                    Continuar con Factura <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>

        <!-- Tarjeta: Cotización Previa (envuelta en su columna) -->
        <div class="col-lg-5">
            <div class="support-card h-100">
                <div class="card-icon-wrapper"><i class="bi bi-file-earmark-text"></i></div>
                <h3>Cotización Previa</h3>
                <a href="crear_solicitud.php?tipo=cotizacion" class="btn btn-secondary-gradient mt-auto">
                    Continuar con Cotización <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>

    </div>
</div>

<?php require_once 'templates/layouts/footer.php'; ?>