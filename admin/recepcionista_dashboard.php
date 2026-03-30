<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1); 
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'recepcionista') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener citas de hoy y mañana
$sql_hoy = "SELECT c.id, c.hora_cita, c.estado, m.nombre_mascota, cl.nombre AS dueno, cl.telefono
            FROM CITA c
            JOIN MASCOTA m ON c.id_mascota = m.id
            JOIN CLIENTE cl ON m.id_cliente = cl.id
            WHERE c.fecha_cita = CURDATE()
            ORDER BY c.hora_cita";
$citas_hoy = $conn->query($sql_hoy);

$sql_manana = "SELECT c.id, c.hora_cita, m.nombre_mascota, cl.nombre AS dueno, cl.telefono
               FROM CITA c
               JOIN MASCOTA m ON c.id_mascota = m.id
               JOIN CLIENTE cl ON m.id_cliente = cl.id
               WHERE c.fecha_cita = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
               ORDER BY c.hora_cita";
$citas_manana = $conn->query($sql_manana);

// Obtener clientes nuevos (últimos 7 días)
$sql_clientes_nuevos = "SELECT COUNT(*) FROM CLIENTE WHERE fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
$result = $conn->query($sql_clientes_nuevos);
$clientes_nuevos = $result->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recepción - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { background: var(--muted); }
        .admin-header { background: var(--primary); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        .admin-header a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .grid-acciones { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .accion-card { background: white; padding: 20px; border-radius: var(--radius-md); text-align: center; box-shadow: var(--shadow-soft); }
        .accion-card a { text-decoration: none; color: var(--primary); font-weight: bold; display: block; }
        .citas-table { width: 100%; background: white; border-radius: var(--radius-md); overflow: hidden; margin-bottom: 20px; box-shadow: var(--shadow-soft); }
        .citas-table th, .citas-table td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        .citas-table th { background: var(--black); color: white; }
        .btn-small { background: var(--primary); color: white; padding: 5px 10px; border-radius: var(--radius-sm); text-decoration: none; font-size: 12px; }
        .estado-pendiente { background: #ff9800; color: white; padding: 4px 8px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .bienvenida { background: white; padding: 20px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .stats-mini { display: flex; gap: 20px; }
        .stat-mini { text-align: center; }
        .stat-mini .numero { font-size: 1.5rem; font-weight: bold; color: var(--primary); }
        .stat-mini .label { font-size: 0.8rem; color: #666; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Recepción</h1>
        <div>
            <a href="recepcionista_dashboard.php">📋 Inicio</a>
            <a href="citas.php">➕ Nueva Cita</a>
            <a href="clientes.php">👥 Clientes</a>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <div class="bienvenida">
            <h2>Bienvenido/a <?php echo htmlspecialchars($_SESSION['nombre']); ?></h2>
            <div class="stats-mini">
                <div class="stat-mini">
                    <div class="numero"><?php echo $citas_hoy->num_rows; ?></div>
                    <div class="label">Citas Hoy</div>
                </div>
                <div class="stat-mini">
                    <div class="numero"><?php echo $clientes_nuevos; ?></div>
                    <div class="label">Nuevos Clientes (7d)</div>
                </div>
            </div>
        </div>

        <div class="grid-acciones">
            <div class="accion-card"><a href="citas.php">📅 Agendar Cita</a></div>
            <div class="accion-card"><a href="clientes.php">🐕 Buscar Cliente</a></div>
            <div class="accion-card"><a href="reportes.php">📊 Reporte Diario</a></div>
        </div>

        <h3>📋 Citas de Hoy (<?php echo date('d/m/Y'); ?>)</h3>
        <table class="citas-table">
            <thead>
                <tr><th>Hora</th><th>Mascota</th><th>Dueño</th><th>Teléfono</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php if ($citas_hoy->num_rows > 0): ?>
                    <?php while($cita = $citas_hoy->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $cita['hora_cita']; ?></td>
                        <td><?php echo htmlspecialchars($cita['nombre_mascota']); ?></td>
                        <td><?php echo htmlspecialchars($cita['dueno']); ?></td>
                        <td><?php echo $cita['telefono']; ?></td>
                        <td><span class="estado-pendiente"><?php echo ucfirst($cita['estado']); ?></span></td>
                        <td><a href="detalle_cita.php?id=<?php echo $cita['id']; ?>" class="btn-small">Ver</a></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center;">No hay citas para hoy</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h3>📅 Citas de Mañana (<?php echo date('d/m/Y', strtotime('+1 day')); ?>)</h3>
        <table class="citas-table">
            <thead>
                <tr><th>Hora</th><th>Mascota</th><th>Dueño</th><th>Teléfono</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php while($cita = $citas_manana->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $cita['hora_cita']; ?></td>
                    <td><?php echo htmlspecialchars($cita['nombre_mascota']); ?></td>
                    <td><?php echo htmlspecialchars($cita['dueno']); ?></td>
                    <td><?php echo $cita['telefono']; ?></td>
                    <td><a href="detalle_cita.php?id=<?php echo $cita['id']; ?>" class="btn-small">Ver</a></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>