<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_mascota = (int)($_GET['id'] ?? 0);
if (!$id_mascota) {
    header('Location: dashboard.php');
    exit;
}

// ========== USANDO vista_mascotas_completas (RESUMEN) ==========
$sql_mascota = "SELECT * FROM vista_mascotas_completas WHERE id = ?";
$stmt = $conn->prepare($sql_mascota);
$stmt->bind_param("i", $id_mascota);
$stmt->execute();
$mascota = $stmt->get_result()->fetch_assoc();

if (!$mascota) {
    die('Mascota no encontrada');
}

// ========== USANDO vista_historial_mascota (DETALLE DE SERVICIOS) ==========
// Esta vista muestra CADA SERVICIO por separado, no solo el resumen de la cita
$sql_historial = "SELECT * FROM vista_historial_mascota WHERE mascota_id = ? ORDER BY fecha_cita DESC";
$stmt_historial = $conn->prepare($sql_historial);
$stmt_historial->bind_param("i", $id_mascota);
$stmt_historial->execute();
$historial = $stmt_historial->get_result();

// ========== TAMBIÉN USAMOS vista_citas_completas para el resumen por cita ==========
$sql_citas = "SELECT * FROM vista_citas_completas WHERE mascota_id = ? ORDER BY fecha_cita DESC, hora_cita DESC";
$stmt_citas = $conn->prepare($sql_citas);
$stmt_citas->bind_param("i", $id_mascota);
$stmt_citas->execute();
$citas = $stmt_citas->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de <?php echo htmlspecialchars($mascota['nombre_mascota']); ?> - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/historial_mascota.css">
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="btn-volver">← Volver al Dashboard</a>
        
        <!-- HEADER CON DATOS DE LA MASCOTA (usando vista_mascotas_completas) -->
        <div class="header-mascota">
            <h1>🐾 Historial de <?php echo htmlspecialchars($mascota['nombre_mascota']); ?></h1>
            
            <div class="info-grid">
                <div class="info-card">
                    <div class="label">Especie</div>
                    <div class="value"><?php echo $mascota['especie']; ?></div>
                </div>
                <div class="info-card">
                    <div class="label">Raza</div>
                    <div class="value"><?php echo $mascota['raza'] ?: '—'; ?></div>
                </div>
                <div class="info-card">
                    <div class="label">Género</div>
                    <div class="value"><?php echo $mascota['genero'] == 'MACHO' ? '♂️ Macho' : '♀️ Hembra'; ?></div>
                </div>
                <div class="info-card">
                    <div class="label">Edad</div>
                    <div class="value"><?php echo $mascota['edad_anios'] ? $mascota['edad_anios'] . ' años' : '—'; ?></div>
                </div>
                <div class="info-card">
                    <div class="label">Dueño</div>
                    <div class="value"><?php echo htmlspecialchars($mascota['nombre_dueno']); ?></div>
                </div>
                <div class="info-card">
                    <div class="label">Teléfono</div>
                    <div class="value"><?php echo $mascota['telefono'] ?: '—'; ?></div>
                </div>
                <div class="info-card">
                    <div class="label">Total Visitas</div>
                    <div class="value">📋 <?php echo $mascota['total_citas']; ?></div>
                </div>
                <div class="info-card">
                    <div class="label">Última Visita</div>
                    <div class="value"><?php echo $mascota['ultima_cita'] ? date('d/m/Y', strtotime($mascota['ultima_cita'])) : 'Sin visitas'; ?></div>
                </div>
            </div>
        </div>
        
        <!-- SECCIÓN 1: RESUMEN POR CITA (usando vista_citas_completas) -->
        <div class="seccion">
            <h2>📋 Historial de Citas</h2>
            <table class="citas-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Servicios</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($citas->num_rows > 0): ?>
                        <?php while($cita = $citas->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
                            <td><?php echo substr($cita['hora_cita'], 0, 5); ?></td>
                            <td>
                                <?php 
                                $servicios_lista = explode(', ', $cita['servicios']);
                                foreach($servicios_lista as $servicio):
                                    if(trim($servicio)):
                                ?>
                                    <span class="badge-servicio"><?php echo htmlspecialchars($servicio); ?></span>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </td>
                            <td>$<?php echo number_format($cita['total_cobrado'], 2); ?></td>
                            <td><span class="estado-<?php echo $cita['estado']; ?>"><?php echo ucfirst($cita['estado']); ?></span></td>
                            <td><a href="detalle_cita.php?id=<?php echo $cita['cita_id']; ?>" class="btn-small">Ver detalle</a></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #999;">No hay citas registradas</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- SECCIÓN 2: DETALLE DE SERVICIOS (usando vista_historial_mascota) -->
        <div class="seccion">
            <h2>🩺 Detalle de Servicios por Cita</h2>
            <p style="color: #666; margin-bottom: 15px;">Desglose de cada servicio realizado en cada cita</p>
            
            <table class="servicios-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Servicio</th>
                        <th>Precio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($historial->num_rows > 0): ?>
                        <?php 
                        $current_cita = null;
                        while($detalle = $historial->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?php echo $detalle['fecha_cita'] ? date('d/m/Y', strtotime($detalle['fecha_cita'])) : 'Fecha no disponible'; ?></td>
                            <td>
                                <?php if($detalle['estado']): ?>
                                    <span class="estado-<?php echo $detalle['estado']; ?>"><?php echo ucfirst($detalle['estado']); ?></span>
                                <?php else: ?>
                                    <span style="color:#999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $detalle['nombre_servicio'] ?: '<span style="color:#999;">Sin servicio</span>'; ?></td>
                            <td><?php echo $detalle['precio_fijado'] ? '$' . number_format($detalle['precio_fijado'], 2) : '—'; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: #999;">No hay servicios registrados</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Totales -->
        <hr>
        <div class="total-general">
            💰 Gasto total acumulado: 
            <span>$<?php 
                // Calcular total de todas las citas
                $citas->data_seek(0);
                $total_general = 0;
                while($cita = $citas->fetch_assoc()) {
                    $total_general += $cita['total_cobrado'];
                }
                echo number_format($total_general, 2);
            ?></span>
        </div>
    </div>
</body>
</html>