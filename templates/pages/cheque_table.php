<?php // templates/pages/cheque_table.php
require_once 'config/database.php';
?>
<div class="card shadow-sm">
    <div class="card-header bg-secondary text-white">
        <h4 class="mb-0">Registro Histórico de Solicitudes</h4>
    </div>
    <div class="card-body p-4">
        <div class="table-responsive">
            <table id="tabla-cheques" class="table table-striped table-hover" style="width:100%">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Creado por</th>
                        <th>A Nombre de</th>
                        <th>Valor (Q)</th>
                        <th>Prioridad</th>
                        <th>Departamento</th>
                        <th>Fecha Sol.</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT s.id, u.nombre_usuario, s.nombre_cheque, s.valor_quetzales, s.prioridad, s.departamento, s.fecha_solicitud
                            FROM solicitud_cheques s
                            JOIN usuarios u ON s.usuario_id = u.id";
                    
                    // Si no es admin, solo mostrar sus propias solicitudes
                    if (!es_admin()) {
                        $sql .= " WHERE s.usuario_id = ?";
                    }
                    
                    $sql .= " ORDER BY s.id DESC";
                    
                    $stmt = $conexion->prepare($sql);
                    
                    if (!es_admin()) {
                        $stmt->bind_param("i", $_SESSION['usuario_id']);
                    }
                    
                    $stmt->execute();
                    $resultado = $stmt->get_result();
                    if ($resultado->num_rows > 0) {
                        while($fila = $resultado->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . $fila["id"] . "</td>";
                            echo "<td>" . htmlspecialchars($fila["nombre_usuario"]) . "</td>";
                            echo "<td>" . htmlspecialchars($fila["nombre_cheque"]) . "</td>";
                            echo "<td>Q " . number_format($fila["valor_quetzales"], 2) . "</td>";
                            echo "<td>" . $fila["prioridad"] . "</td>";
                            echo "<td>" . htmlspecialchars($fila["departamento"]) . "</td>";
                            echo "<td>" . date("d/m/Y", strtotime($fila["fecha_solicitud"])) . "</td>";
                            echo "<td><button class='btn btn-sm btn-info'>Ver</button></td>";
                            echo "</tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>