<?php
// seleccionar_empresa.php
require_once 'includes/functions.php';
proteger_pagina(); // Asegura que el usuario esté logueado
require_once 'templates/layouts/header.php';
?>

<!-- Estilos para las tarjetas de selección -->
<style>
    .company-card {
        background-color: var(--bs-gray-800);
        border: 1px solid var(--bs-gray-700);
        border-radius: 1rem;
        padding: 2.5rem;
        text-align: center;
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        display: flex;
        flex-direction: column;
        align-items: center;
        height: 100%;
    }
    .company-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    }
    .card-icon-wrapper {
        font-size: 3rem;
        color: #fff;
        background: linear-gradient(145deg, var(--bs-primary), var(--bs-info));
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 123, 255, 0.4);
    }
    .company-card h3 {
        color: #fff;
        font-weight: 600;
        margin-bottom: 1rem;
    }
    .company-card .btn {
        width: 100%;
        font-weight: bold;
        padding: 0.75rem;
    }
</style>

<!-- Contenido Principal -->
<div class="container-fluid">
    <!-- Encabezado -->
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold text-white">Seleccionar Empresa</h1>
        <p class="lead text-muted">Elige la base de datos de la empresa en la que deseas trabajar.</p>
    </div>

    <!-- Tarjetas de Selección -->
    <div class="row justify-content-center g-4">
        
        <!-- Tarjeta: UNHESA -->
        <div class="col-lg-4 col-md-6">
            <div class="company-card">
                <div class="card-icon-wrapper"><i class="bi bi-building"></i></div>
                <h3>UNHESA</h3>
                <p class="text-muted">Base de Datos: TEST_UNHESA_ZZZ</p>
                <a href="crear_pago_sap.php?empresa=TEST_UNHESA_ZZZ" class="btn btn-primary mt-auto">
                    Seleccionar <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>

        <!-- Tarjeta: PROQUIMA -->
        <div class="col-lg-4 col-md-6">
            <div class="company-card">
                <div class="card-icon-wrapper"><i class="bi bi-briefcase-fill"></i></div>
                <h3>PROQUIMA</h3>
                <p class="text-muted">Base de Datos: TEST_PROQUIMA_ZZZ</p>
                <a href="crear_pago_sap.php?empresa=TEST_PROQUIMA_ZZZ" class="btn btn-info mt-auto">
                    Seleccionar <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>

    </div>
</div>

<?php require_once 'templates/layouts/footer.php'; ?>