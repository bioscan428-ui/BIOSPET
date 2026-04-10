<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'super_admin') {
    http_response_code(403);
    die('Acceso denegado');
}

require_once __DIR__ . '/../includes/conexion.php';

$empleado_id = (int)($_GET['id'] ?? 0);
if (!$empleado_id) {
    die('ID de empleado no válido');
}

// Obtener información del empleado (YA usa vista)
$sql_empleado = "SELECT * FROM vista_empleados_activos WHERE id = ?";
$stmt = $conn->prepare($sql_empleado);
$stmt->bind_param("i", $empleado_id);
$stmt->execute();
$empleado = $stmt->get_result()->fetch_assoc();

if (!$empleado) {
    die('Empleado no encontrado');
}

// ========== AHORA USANDO vista_citas_por_empleado ==========
// Antes: 15 líneas con JOINs manuales
// Ahora: 1 línea
$sql_citas = "SELECT * FROM vista_citas_por_empleado WHERE empleado_id = ? ORDER BY fecha_cita DESC, hora_cita DESC LIMIT 20";
$stmt_citas = $conn->prepare($sql_citas);
$stmt_citas->bind_param("i", $empleado_id);
$stmt_citas->execute();
$citas = $stmt_citas->get_result();

// Obtener horario (sin cambios)
$sql_horario = "SELECT * FROM HORARIO_EMPLEADO 
                WHERE id_empleado = ? AND activo = 1
                ORDER BY FIELD(dia_semana, 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo')";
$stmt_horario = $conn->prepare($sql_horario);
$stmt_horario->bind_param("i", $empleado_id);
$stmt_horario->execute();
$horario = $stmt_horario->get_result();

// Contar citas por estado (usando la misma vista)
$sql_stats = "SELECT estado, COUNT(*) as total FROM vista_citas_por_empleado WHERE empleado_id = ? GROUP BY estado";
$stmt_stats = $conn->prepare($sql_stats);
$stmt_stats->bind_param("i", $empleado_id);
$stmt_stats->execute();
$stats_result = $stmt_stats->get_result();

$citas_por_estado = [
    'pendiente' => 0,
    'confirmada' => 0,
    'completada' => 0,
    'cancelada' => 0
];
while ($row = $stats_result->fetch_assoc()) {
    $citas_por_estado[$row['estado']] = $row['total'];
}
?>

<div class="historial-section">
    <h3>👤 Información Personal</h3>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Nombre:</span>
            <span class="info-value"><?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['ape_pat'] . ' ' . $empleado['ape_mat']); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Email:</span>
            <span class="info-value"><?php echo htmlspecialchars($empleado['email']); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Teléfono:</span>
            <span class="info-value"><?php echo $empleado['telefono'] ?: 'No registrado'; ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Puesto:</span>
            <span class="info-value"><?php echo ucfirst($empleado['puesto']); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Especialidad:</span>
            <span class="info-value"><?php echo $empleado['especialidad'] ?: 'N/A'; ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Fecha Contratación:</span>
            <span class="info-value"><?php echo date('d/m/Y', strtotime($empleado['fecha_contratacion'])); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Usuario sistema:</span>
            <span class="info-value"><?php echo $empleado['nombre_usuario'] ?: '<em>Sin acceso al sistema</em>'; ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Rol sistema:</span>
            <span class="info-value"><?php echo ucfirst($empleado['rol'] ?? 'No asignado'); ?></span>
        </div>
    </div>
</div>

<div class="historial-section">
    <h3>📊 Estadísticas</h3>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $empleado['citas_asignadas'] ?? 0; ?></div>
            <div class="stat-label">Total Citas</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $citas_por_estado['completada']; ?></div>
            <div class="stat-label">Completadas</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $citas_por_estado['pendiente']; ?></div>
            <div class="stat-label">Pendientes</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $citas_por_estado['cancelada']; ?></div>
            <div class="stat-label">Canceladas</div>
        </div>
    </div>
</div>

<div class="historial-section">
    <h3>🕐 Horario de Trabajo</h3>
    <?php if ($horario->num_rows > 0): ?>
        <table class="horario-table">
            <thead>
                <tr><th>Día</th><th>Entrada</th><th>Salida</th></tr>
            </thead>
            <tbody>
                <?php while($row = $horario->fetch_assoc()): ?>
                <tr>
                    <td><?php echo ucfirst($row['dia_semana']); ?></td>
                    <td><?php echo substr($row['hora_entrada'], 0, 5); ?></td>
                    <td><?php echo substr($row['hora_salida'], 0, 5); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No hay horario asignado.</p>
    <?php endif; ?>
</div>

<div class="historial-section">
    <h3>📋 Últimas Citas Asignadas</h3>
    <?php if ($citas->num_rows > 0): ?>
        <table class="citas-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Mascota</th>
                    <th>Dueño</th>
                    <th>Rol</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php while($cita = $citas->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
                    <td><?php echo substr($cita['hora_cita'], 0, 5); ?></td>
                    <td><?php echo htmlspecialchars($cita['nombre_mascota']); ?></td>
                    <td><?php echo htmlspecialchars($cita['dueno']); ?></td>
                    <td>
                        <span class="badge-rol">
                            <?php 
                            if ($cita['rol_asignado'] == 'veterinario') {
                                echo '🩺 Veterinario';
                            } else {
                                echo '🩹 Asistente';
                            }
                            ?>
                        </span>
                    </span>
                    <td><span class="badge badge-<?php echo $cita['estado']; ?>"><?php echo ucfirst($cita['estado']); ?></span></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No hay citas asignadas.</p>
    <?php endif; ?>
</div>

<style>
    .badge-rol {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        background: #e9ecef;
        color: #495057;
    }
</style>