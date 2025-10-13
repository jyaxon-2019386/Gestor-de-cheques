<?php
// scripts/obtener_detalles.php - VERSIÓN FINAL CON PERMISOS Y CC DE SAP
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../classes/SapClient.php'; // Incluimos el cliente de SAP

// Iniciar sesión y proteger la página
proteger_pagina();

// Verificar que se ha proporcionado un ID válido
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $solicitud_id = intval($_GET['id']);
    $usuario_id_actual = $_SESSION['usuario_id'];
    $rol_actual = $_SESSION['rol'];

    // 1. Obtener los datos base de la solicitud, incluyendo el creador y el gestor
    $sql = "SELECT s.*, u.nombre_usuario, g.nombre_usuario as gestor_nombre 
            FROM solicitud_cheques s
            JOIN usuarios u ON s.usuario_id = u.id
            LEFT JOIN usuarios g ON s.gestionado_por_id = g.id
            WHERE s.id = ?";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $solicitud_id);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $solicitud = $resultado->fetch_assoc();

        // 2. LÓGICA DE PERMISOS DE VISUALIZACIÓN
        $puede_ver = false;

        // Condición 1: Eres un rol con visión global (admin, finanzas)
        if (in_array($rol_actual, ['admin', 'finanzas'])) {
            $puede_ver = true;
        }
        // Condición 2: Eres el creador de la solicitud
        elseif ($solicitud['usuario_id'] == $usuario_id_actual) {
            $puede_ver = true;
        }
        // Condición 3: Eres el aprobador actual asignado a esta solicitud
        elseif ($solicitud['aprobador_actual_id'] == $usuario_id_actual) {
            $puede_ver = true;
        }
        
        if ($puede_ver) {
            // --- Si tiene permiso, procedemos a obtener los datos enriquecidos ---

            // --- LÓGICA OPTIMIZADA PARA OBTENER NOMBRES DE CENTROS DE COSTO ---
            $nombres_cc = ['Nivel 1' => 'N/A', 'Nivel 2' => 'N/A', 'Nivel 3' => 'N/A', 'Nivel 4' => 'N/A'];
            if (!empty($solicitud['centro_costo'])) {
                try {
                    $empresa_sap = $solicitud['empresa_sap'] ?? 'PROQUIMA';
                    $sap_client = new SapClient($empresa_sap);
                    
                    $codigos_cc_bruto = explode('-', $solicitud['centro_costo']);
                    // Filtramos para quedarnos solo con los códigos que no están vacíos
                    $codigos_cc_validos = array_filter($codigos_cc_bruto);

                    if (!empty($codigos_cc_validos)) {
                        // Si hay códigos válidos, buscamos todos sus nombres EN UNA SOLA CONSULTA
                        $nombres_encontrados = $sap_client->getCostCenterNamesByCodes($codigos_cc_validos);

                        // Ahora mapeamos los resultados
                        for ($i = 1; $i <= 4; $i++) {
                            $codigo = $codigos_cc_bruto[$i-1] ?? null;
                            if (!empty($codigo)) {
                                // Buscamos el nombre en el array que ya obtuvimos
                                $nombre = $nombres_encontrados[$codigo] ?? 'No encontrado';
                                $nombres_cc['Nivel ' . $i] = $codigo . ' - ' . $nombre;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Si SAP falla, mostramos los códigos en bruto para no perder la información.
                    $codigos_cc = explode('-', $solicitud['centro_costo']);
                    $nombres_cc = [
                        'Nivel 1' => $codigos_cc[0] ?? 'N/A',
                        'Nivel 2' => $codigos_cc[1] ?? 'N/A',
                        'Nivel 3' => $codigos_cc[2] ?? 'N/A',
                        'Nivel 4' => $codigos_cc[3] ?? 'N/A',
                    ];
                }
            }
            // --- FIN DE LA LÓGICA OPTIMIZADA ---

            // 4. Lógica para el color del badge de estado
            $estado_class = 'text-bg-secondary';
            switch ($solicitud['estado']) {
                case 'Pendiente': case 'Pendiente de Jefe': case 'Pendiente de Gerente General':
                    $estado_class = 'text-bg-warning'; break;
                case 'Aprobado': $estado_class = 'text-bg-success'; break;
                case 'Pagado': $estado_class = 'text-bg-info'; break;
                case 'Rechazado': $estado_class = 'text-bg-danger'; break;
            }
            ?>
            <!-- INICIO DEL RENDERIZADO DEL HTML DEL MODAL -->
            
            <!-- Encabezado -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <h4 class="mb-1">A nombre de: <strong><?php echo htmlspecialchars($solicitud['nombre_cheque']); ?></strong></h4>
                    <small class="text-muted">Creado por <?php echo htmlspecialchars($solicitud['nombre_usuario']); ?> el <?php echo date("d/m/Y", strtotime($solicitud['fecha_solicitud'])); ?></small>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="badge fs-6 <?php echo $estado_class; ?>"><?php echo $solicitud['estado']; ?></span>
                </div>
            </div>

            <div class="row g-4">
                <!-- Columna Izquierda -->
                <div class="col-lg-6">
                    <div class="detail-section">
                        <h6 class="detail-title"><i class="bi bi-cash-coin me-2"></i>Detalles Financieros</h6>
                        <div class="detail-grid">
                            <span>Monto (Q):</span><strong>Q <?php echo number_format($solicitud['valor_quetzales'], 2); ?></strong>
                            <span>Monto ($):</span><strong>$ <?php echo number_format($solicitud['valor_dolares'], 2); ?></strong>
                            <span>Empresa:</span><strong><?php echo htmlspecialchars($solicitud['empresa']); ?></strong>
                            <span class="grid-col-span-2"><strong>Centro de Costo:</strong></span>
                            <?php foreach($nombres_cc as $nivel => $valor): ?>
                                <span><?php echo $nivel; ?>:</span><strong><?php echo htmlspecialchars($valor); ?></strong>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="detail-section mt-4">
                        <h6 class="detail-title"><i class="bi bi-file-earmark-text me-2"></i>Información Fiscal</h6>
                        <div class="detail-grid">
                             <span>NIT Proveedor:</span><strong><?php echo htmlspecialchars($solicitud['nit_proveedor']); ?></strong>
                             <span>Régimen ISR:</span><strong><?php echo htmlspecialchars($solicitud['regimen_isr']); ?></strong>
                             <span>Incluye Factura:</span><strong><?php echo htmlspecialchars($solicitud['incluye_factura']); ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha -->
                <div class="col-lg-6">
                     <div class="detail-section">
                        <h6 class="detail-title"><i class="bi bi-chat-left-text me-2"></i>Descripción y Observaciones</h6>
                        <p class="detail-text"><strong>Descripción:</strong><br><?php echo nl2br(htmlspecialchars($solicitud['descripcion'])); ?></p>
                        <p class="detail-text mt-3"><strong>Observaciones:</strong><br><?php echo nl2br(htmlspecialchars($solicitud['observaciones'])); ?></p>
                    </div>

                    <?php
                    // --- SECCIÓN DE AUDITORÍA MEJORADA ---
                    if ($solicitud['estado'] != 'Pendiente' && !empty($solicitud['gestor_nombre'])):
                        
                        // Determinar la acción realizada
                        $accion_realizada = "";
                        switch ($solicitud['estado']) {
                            case 'Pendiente de Gerente General':
                                $accion_realizada = "escaló para aprobación final";
                                break;
                            case 'Aprobado':
                                $accion_realizada = "dio la aprobación final";
                                break;
                            case 'Pagado':
                                $accion_realizada = "marcó como pagada";
                                break;
                            case 'Rechazado':
                                $accion_realizada = "rechazó";
                                break;
                        }
                    ?>
                    <div class="detail-section mt-4">
                        <h6 class="detail-title"><i class="bi bi-person-check me-2"></i>Auditoría</h6>
                        <p class="detail-text">
                            La solicitud fue gestionada por 
                            <strong><?php echo htmlspecialchars($solicitud['gestor_nombre']); ?></strong>, quien la <strong><?php echo $accion_realizada; ?></strong>.
                            <br>
                            <small class="text-muted">El <?php echo date("d/m/Y \a \l\a\s H:i", strtotime($solicitud['fecha_gestion'])); ?></small>
                        </p>
                    </div>
                    <?php endif; ?>

                    <!-- NUEVA SECCIÓN DE PROGRAMACIÓN -->
                    <?php if (!empty($solicitud['fecha_programada_pago'])): ?>
                    <div class="detail-section mt-4">
                        <h6 class="detail-title"><i class="bi bi-calendar-check me-2"></i>Programación de Pago</h6>
                        <div class="detail-grid">
                            <span>Fecha Estimada:</span><strong><?php echo date("d/m/Y", strtotime($solicitud['fecha_programada_pago'])); ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- FIN DEL RENDERIZADO DEL HTML DEL MODAL -->
            <?php
        } else {
            // Si ninguna condición de permiso se cumple, mostrar el error de permiso.
            echo '<div class="alert alert-danger">No tienes permiso para ver los detalles de esta solicitud.</div>';
        }

    } else {
        // Si no se encontró ninguna solicitud con ese ID.
        echo '<div class="alert alert-danger">No se encontró la solicitud especificada.</div>';
    }
} else {
    // Si el ID no fue proporcionado en la URL.
    echo '<div class="alert alert-danger">ID de solicitud no válido.</div>';
}
?>