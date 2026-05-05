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

// ========== ESTADÍSTICAS RÁPIDAS ==========
$stats = [];
$stats['citas_hoy'] = $conn->query("SELECT COUNT(*) FROM CITA WHERE fecha_cita = CURDATE()")->fetch_row()[0];
$stats['citas_pendientes'] = $conn->query("SELECT COUNT(*) FROM CITA WHERE estado = 'pendiente'")->fetch_row()[0];
$stats['productos_stock_bajo'] = $conn->query("SELECT COUNT(*) FROM PRODUCTO WHERE stock_actual <= stock_minimo AND activo = 1")->fetch_row()[0];
$stats['ingresos_mes'] = $conn->query("SELECT IFNULL(SUM(total), 0) FROM VENTA WHERE MONTH(fecha_venta) = MONTH(CURDATE()) AND estado = 'completada'")->fetch_row()[0];

// ========== USANDO VISTA ==========
// Últimas citas usando vista_citas_completas
$sql_citas = "SELECT 
                cita_id as id,
                fecha_cita,
                hora_cita,
                estado,
                notas,
                nombre_mascota,
                foto,
                nombre_dueno as dueno,
                servicios
            FROM vista_citas_completas 
            ORDER BY fecha_cita DESC, hora_cita DESC 
            LIMIT 10";
$citas_recientes = $conn->query($sql_citas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Admin - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
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
            <a href="punto_venta.php" style="background: #4caf50; padding: 5px 12px; border-radius: 5px;">💰 Punto de Venta</a>
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
                男生
                    <th>ID</th>
                    <th>Foto</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Mascota</th>
                    <th>Dueño</th>
                    <th>Motivo / Síntomas</th>
                    <th>Servicios</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </
            </thead>
            <tbody>
                <?php while($cita = $citas_recientes->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $cita['id']; ?></td>
                    <td>
                        <?php if (!empty($cita['foto']) && file_exists('../' . $cita['foto'])): ?>
                            <img src="../<?php echo $cita['foto']; ?>" alt="Foto de <?php echo $cita['nombre_mascota']; ?>" class="foto-miniatura">
                        <?php else: ?>
                            <span style="color:#999; font-size:20px;">🐾</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
                    <td><?php echo $cita['hora_cita']; ?></td>
                    <td><?php echo htmlspecialchars($cita['nombre_mascota']); ?></td>
                    <td><?php echo htmlspecialchars($cita['dueno']); ?></td>
                    <td class="sintomas-tooltip">
                        <?php if (!empty($cita['notas'])): ?>
                            <span class="sintomas-icono">📋</span>
                            <span class="tooltip-texto">
                                <strong>Motivo de consulta:</strong><br>
                                <?php echo nl2br(htmlspecialchars(substr($cita['notas'], 0, 150))); ?>
                                <?php if (strlen($cita['notas']) > 150): ?>...<?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#ccc;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($cita['servicios'])): ?>
                            <?php echo $cita['servicios']; ?>
                        <?php else: ?>
                            <span class="sin-servicios">(Sin servicios)</span>
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