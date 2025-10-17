<?php
/**
 * ARCHIVO DE FUNCIONES GLOBALES
 * Aquí definimos funciones que usaremos en varias partes de la aplicación.
 */

// 1. Iniciar la sesión. Es vital que este archivo inicie la sesión,
//    ya que las funciones de abajo dependen de la variable $_SESSION.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


/**
 * Función para proteger una página.
 * Verifica si el usuario ha iniciado sesión. Si no, lo redirige a login.php.
 */
function proteger_pagina() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit(); // Es crucial usar exit() para detener la ejecución.
    }
}


/**
 * Función para verificar si el usuario es Administrador.
 * Devuelve true si el rol es 'admin', de lo contrario devuelve false.
 */
function es_admin() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
}

/**
 * Comprueba si el usuario tiene un rol que le permite cambiar el estado de una solicitud
 * (Aprobar, Rechazar, Pagar).
 */
function puede_aprobar() {
    if (!isset($_SESSION['rol'])) {
        return false;
    }
    // Añadimos 'finanzas' a la lista de roles con poder de decisión.
    $roles_aprobadores = ['admin', 'gerente_general', 'gerente', 'jefe_de_area'];
    return in_array($_SESSION['rol'], $roles_aprobadores);
}

function puede_rechazar() {
    if (!isset($_SESSION['rol'])) {
        return false;
    }
    // Añadimos 'finanzas' a la lista de roles con poder de decisión.
    $roles_aprobadores = ['admin', 'gerente_general', 'gerente', 'jefe_de_area', 'finanzas'];
    return in_array($_SESSION['rol'], $roles_aprobadores);
}

function es_finanzas() {
    if (!isset($_SESSION['rol'])) {
        return false;
    }
    // Añadimos 'finanzas' a la lista de roles con poder de decisión.
    $roles_aprobadores = ['finanzas'];
    return in_array($_SESSION['rol'], $roles_aprobadores);
}

/**
 * Función para generar breadcrumbs dinámicos.
 * Crea una navegación de migas de pan basada en la página actual.
 */
function generar_breadcrumbs() {
    $pagina_actual = basename($_SERVER['PHP_SELF']);
    $breadcrumbs = [
        'index.php' => 'Dashboard',
        'nueva_solicitud.php' => 'Nueva Solicitud',
        'crear_solicitud.php' => 'Datos de Solicitud',
        'solicitudes.php' => 'Historial',
        'aprobaciones.php' => 'Aprobaciones',
        'trazabilidad.php' => 'Trazabilidad Global',
        'gestion_usuarios.php' => 'Usuarios',
        'perfil.php' => 'Mi Perfil', // <-- AÑADE ESTA LÍNEA
    ];
    $nombre_pagina = $breadcrumbs[$pagina_actual] ?? 'Página';

    echo '<nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-transparent p-0 m-0">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">' . $nombre_pagina . '</li>
            </ol>
          </nav>';
}