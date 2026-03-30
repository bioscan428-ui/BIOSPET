<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'veterinario') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener citas asignadas al veterinario
$sql = "SELECT 
            c.id,
            c.fecha_cita,
            c.hora_cita,
            c.estado,
            m.nombre_mascota,
            m.especie,
            cl.nombre AS nombre_dueno,
            cl.telefono,
            GROUP_CONCAT(s.nombre_servicio SEPARATOR ', ') AS servicios
        FROM CITA c
        JOIN ASIGNACION_CITA ac ON c.id = ac.id_cita
        JOIN MASCOTA m ON c.id_mascota = m.id
        JOIN CLIENTE cl ON m.id_cliente = cl.id
        JOIN DETALLE_CITA dc ON c.id = dc.id_cita
        JOIN SERVICIO s ON dc.id_servicio = s.id
        WHERE ac.id_empleado = ? AND ac.rol_asignado = 'veterinario'
        GROUP BY c.id
        ORDER BY c.fecha_cita DESC, c.hora_cita DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['empleado_id']);
$stmt->execute();
$citas = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Veterinario - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { background: var(--muted); }
        .admin-header { background: var(--primary); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        .admin-header a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .bienvenida { background: white; padding: 20px; border-radius: var(--radius-md); margin-bottom: 20px; box-shadow: var(--shadow-soft); }
        .citas-table { width: 100%; background: white; border-radius: var(--radius-md); overflow: hidden; box-shadow: var(--shadow-soft); }
        .citas-table th, .citas-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .citas-table th { background: var(--black); color: white; }
        .btn-small { background: var(--primary); color: white; padding: 5px 10px; border-radius: var(--radius-sm); text-decoration: none; font-size: 12px; display: inline-block; }
        .estado-pendiente { background: #ff9800; color: white; padding: 4px 8px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .estado-confirmada { background: #4caf50; color: white; padding: 4px 8px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .estado-cancelada { background: #f44336; color: white; padding: 4px 8px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .estado-completada { background: #2196f3; color: white; padding: 4px 8px; border-radius: 20px; font-size: 12px; display: inline-block; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Panel Veterinario</h1>
        <div>
            <a href="veterinario_dashboard.php">📋 Mis Citas</a>
            <a href="veterinario_pacientes.php">🐕 Mis Pacientes</a>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <div class="bienvenida">
            <h2>Bienvenido Dr/a. <?php echo htmlspecialchars($_SESSION['nombre']); ?></h2>
            <p>Hoy es <?php echo date('d/m/Y'); ?></p>
            <p>Tienes <?php echo $citas->num_rows; ?> citas asignadas</p>
        </div>

        <h3>📋 Mis Citas</h3>
        <table class="citas-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Mascota</th>
                    <th>Dueño</th>
                    <th>Servicios</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($citas->num_rows > 0): ?>
                    <?php while($cita = $citas->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $cita['id']; ?></td>
                        <td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
                        <td><?php echo $cita['hora_cita']; ?></td>
                        <td><?php echo htmlspecialchars($cita['nombre_mascota']); ?></td>
                        <td><?php echo htmlspecialchars($cita['nombre_dueno']); ?></td>
                        <td><?php echo $cita['servicios']; ?></td>
                        <td>
                            <span class="estado-<?php echo $cita['estado']; ?>">
                                <?php echo ucfirst($cita['estado']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="detalle_cita.php?id=<?php echo $cita['id']; ?>" class="btn-small">Ver Detalle</a>
                            <?php if ($cita['estado'] == 'confirmada'): ?>
                                <a href="actualizar_estado.php?id=<?php echo $cita['id']; ?>&estado=completada" class="btn-small" style="background:#2196f3;">Completar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">No tienes citas asignadas</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>