<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['admin', 'super_admin'])) {
    header('Location: login.php');
    exit;
}

// Si es super_admin, redirigir a dashboard normal
if ($_SESSION['rol'] === 'super_admin') {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Estadísticas rápidas
$stats = [];
$stats['citas_hoy'] = $conn->query("SELECT COUNT(*) FROM CITA WHERE fecha_cita = CURDATE()")->fetch_row()[0];
$stats['citas_pendientes'] = $conn->query("SELECT COUNT(*) FROM CITA WHERE estado = 'pendiente'")->fetch_row()[0];
$stats['productos_stock_bajo'] = $conn->query("SELECT COUNT(*) FROM PRODUCTO WHERE stock_actual <= stock_minimo AND activo = 1")->fetch_row()[0];
$stats['ingresos_mes'] = $conn->query("SELECT IFNULL(SUM(total), 0) FROM VENTA WHERE MONTH(fecha_venta) = MONTH(CURDATE()) AND estado = 'completada'")->fetch_row()[0];

// Últimas citas (CORREGIDO: usar LEFT JOIN para mostrar citas sin servicios)
$sql_citas = "SELECT 
                c.id, 
                c.fecha_cita, 
                c.hora_cita, 
                c.estado, 
                m.nombre_mascota, 
                cl.nombre AS dueno,
                GROUP_CONCAT(s.nombre_servicio SEPARATOR ', ') AS servicios
            FROM CITA c
            JOIN MASCOTA m ON c.id_mascota = m.id
            JOIN CLIENTE cl ON m.id_cliente = cl.id
            LEFT JOIN DETALLE_CITA dc ON c.id = dc.id_cita
            LEFT JOIN SERVICIO s ON dc.id_servicio = s.id
            GROUP BY c.id
            ORDER BY c.fecha_cita DESC, c.hora_cita DESC
            LIMIT 10";
$citas_recientes = $conn->query($sql_citas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Admin - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { background: var(--muted); }
        .admin-header { background: var(--primary); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        .admin-header a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: var(--radius-md); text-align: center; box-shadow: var(--shadow-soft); }
        .stat-card .numero { font-size: 2.5rem; font-weight: bold; color: var(--primary); }
        .stat-card .label { color: #666; margin-top: 10px; }
        .citas-table { width: 100%; background: white; border-radius: var(--radius-md); overflow: hidden; box-shadow: var(--shadow-soft); }
        .citas-table th, .citas-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .citas-table th { background: var(--black); color: white; }
        .btn-small { background: var(--primary); color: white; padding: 5px 10px; border-radius: var(--radius-sm); text-decoration: none; font-size: 12px; }
        .estado-pendiente { background: #ff9800; color: white; padding: 4px 8px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .estado-confirmada { background: #4caf50; color: white; padding: 4px 8px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .estado-cancelada { background: #f44336; color: white; padding: 4px 8px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .estado-completada { background: #2196f3; color: white; padding: 4px 8px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .btn-nuevo { background: #4caf50; color: white; padding: 10px 20px; border-radius: var(--radius-sm); text-decoration: none; display: inline-block; margin-bottom: 20px; }
        .sin-servicios { color: #999; font-style: italic; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Panel Administrativo</h1>
        <div>
            <a href="admin_dashboard.php">📊 Dashboard</a>
            <a href="citas.php">📋 Citas</a>
            <a href="calendario.php">📅 Calendario</a>
            <a href="reportes.php">📊 Reportes</a>
            <a href="productos.php">🛒 Productos</a>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="numero"><?php echo $stats['citas_hoy']; ?></div>
                <div class="label">Citas Hoy</div>
            </div>
            <div class="stat-card">
                <div class="numero"><?php echo $stats['citas_pendientes']; ?></div>
                <div class="label">Citas Pendientes</div>
            </div>
            <div class="stat-card">
                <div class="numero"><?php echo $stats['productos_stock_bajo']; ?></div>
                <div class="label">Productos Stock Bajo</div>
            </div>
            <div class="stat-card">
                <div class="numero">$<?php echo number_format($stats['ingresos_mes'], 2); ?></div>
                <div class="label">Ingresos del Mes</div>
            </div>
        </div>

        <a href="citas.php" class="btn-nuevo">+ Nueva Cita</a>

        <h3>📋 Últimas Citas</h3>
        <table class="citas-table">
            <thead>
                男生<th>ID</th><th>Fecha</th><th>Hora</th><th>Mascota</th><th>Dueño</th><th>Servicios</th><th>Estado</th><th>Acciones</th>\\
            </thead>
            <tbody>
                <?php while($cita = $citas_recientes->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $cita['id']; ?></td>
                    <td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
                    <td><?php echo $cita['hora_cita']; ?></td>
                    <td><?php echo htmlspecialchars($cita['nombre_mascota']); ?></td>
                    <td><?php echo htmlspecialchars($cita['dueno']); ?></td>
                    <td>
                        <?php if (!empty($cita['servicios'])): ?>
                            <?php echo $cita['servicios']; ?>
                        <?php else: ?>
                            <span class="sin-servicios">(Sin servicios asignados)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="estado-<?php echo $cita['estado']; ?>">
                            <?php echo ucfirst($cita['estado']); ?>
                        </span>
                    </td>
                    <td>
                        <a href="detalle_cita.php?id=<?php echo $cita['id']; ?>" class="btn-small">Ver</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>