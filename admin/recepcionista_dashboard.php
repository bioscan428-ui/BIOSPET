<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1); 

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'recepcionista') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener citas de hoy (con foto y notas)
$sql_hoy = "SELECT c.id, c.hora_cita, c.estado, c.notas, m.nombre_mascota, m.foto, cl.nombre AS dueno, cl.telefono
            FROM CITA c
            JOIN MASCOTA m ON c.id_mascota = m.id
            JOIN CLIENTE cl ON m.id_cliente = cl.id
            WHERE c.fecha_cita = CURDATE()
            ORDER BY c.hora_cita";
$citas_hoy = $conn->query($sql_hoy);

// Obtener citas pendientes por confirmar (con foto y notas)
$sql_pendientes = "SELECT c.id, c.fecha_cita, c.hora_cita, c.notas, m.nombre_mascota, m.foto, cl.nombre AS dueno, cl.telefono
                   FROM CITA c
                   JOIN MASCOTA m ON c.id_mascota = m.id
                   JOIN CLIENTE cl ON m.id_cliente = cl.id
                   WHERE c.estado = 'pendiente'
                   ORDER BY c.fecha_cita ASC, c.hora_cita ASC
                   LIMIT 10";
$citas_pendientes = $conn->query($sql_pendientes);

// Obtener citas de mañana (con foto)
$sql_manana = "SELECT c.id, c.hora_cita, m.nombre_mascota, m.foto, cl.nombre AS dueno, cl.telefono
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
        .bienvenida { background: white; padding: 20px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .stats-mini { display: flex; gap: 20px; }
        .stat-mini { text-align: center; }
        .stat-mini .numero { font-size: 1.5rem; font-weight: bold; color: var(--primary); }
        .stat-mini .label { font-size: 0.8rem; color: #666; }
        .foto-miniatura {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .foto-miniatura:hover {
            transform: scale(3);
            z-index: 1000;
            position: relative;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
        }
        .sintomas-icono {
            cursor: pointer;
            font-size: 18px;
            color: var(--primary);
        }
        .sintomas-tooltip {
            position: relative;
            display: inline-block;
        }
        .sintomas-tooltip .tooltip-texto {
            visibility: hidden;
            background-color: #333;
            color: #fff;
            text-align: left;
            border-radius: 5px;
            padding: 8px 12px;
            position: absolute;
            z-index: 100;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            font-size: 12px;
            font-weight: normal;
            min-width: 200px;
            white-space: normal;
        }
        .sintomas-tooltip:hover .tooltip-texto {
            visibility: visible;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Recepción</h1>
        <div>
            <a href="recepcionista_dashboard.php">📋 Inicio</a>
            <a href="citas.php">➕ Nueva Cita</a>
            <!------
            <a href="clientes.php">👥 Clientes</a>
            ------->
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

        <!-- Citas Pendientes por Confirmar -->
        <h3>⏳ Citas Pendientes por Confirmar</h3>
        <table class="citas-table">
            <thead>
                男生<th>Foto</th><th>Fecha</th><th>Hora</th><th>Mascota</th><th>Dueño</th><th>Teléfono</th><th>Síntomas</th><th>Acciones</th>\\
            </thead>
            <tbody>
                <?php if ($citas_pendientes->num_rows > 0): ?>
                    <?php while($cita = $citas_pendientes->fetch_assoc()): ?>
                    牛
                        <td>
                            <?php if (!empty($cita['foto']) && file_exists('../' . $cita['foto'])): ?>
                                <img src="../<?php echo $cita['foto']; ?>" class="foto-miniatura">
                            <?php else: ?>
                                <span style="color:#999;">🐾</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
                        <td><?php echo $cita['hora_cita']; ?></td>
                        <td><?php echo htmlspecialchars($cita['nombre_mascota']); ?></td>
                        <td><?php echo htmlspecialchars($cita['dueno']); ?></td>
                        <td><?php echo $cita['telefono']; ?></td>
                        <td class="sintomas-tooltip">
                            <?php if (!empty($cita['notas'])): ?>
                                <span class="sintomas-icono">📋</span>
                                <span class="tooltip-texto"><?php echo nl2br(htmlspecialchars(substr($cita['notas'], 0, 100))); ?></span>
                            <?php else: ?>
                                <span style="color:#ccc;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="detalle_cita.php?id=<?php echo $cita['id']; ?>" class="btn-small">Ver</a>
                            <a href="actualizar_estado.php?id=<?php echo $cita['id']; ?>&estado=confirmada" class="btn-small" style="background:#4caf50;">Confirmar</a>
                        </td>
                    \\
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align: center;">No hay citas pendientes</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Citas de Hoy -->
        <h3>📋 Citas de Hoy (<?php echo date('d/m/Y'); ?>)</h3>
        <table class="citas-table">
            <thead>
                男生<th>Foto</th><th>Hora</th><th>Mascota</th><th>Dueño</th><th>Teléfono</th><th>Síntomas</th><th>Estado</th><th>Acciones</th>\\
            </thead>
            <tbody>
                <?php if ($citas_hoy->num_rows > 0): ?>
                    <?php while($cita = $citas_hoy->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php if (!empty($cita['foto']) && file_exists('../' . $cita['foto'])): ?>
                                <img src="../<?php echo $cita['foto']; ?>" class="foto-miniatura">
                            <?php else: ?>
                                <span style="color:#999;">🐾</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $cita['hora_cita']; ?></td>
                        <td><?php echo htmlspecialchars($cita['nombre_mascota']); ?></td>
                        <td><?php echo htmlspecialchars($cita['dueno']); ?></td>
                        <td><?php echo $cita['telefono']; ?></td>
                        <td class="sintomas-tooltip">
                            <?php if (!empty($cita['notas'])): ?>
                                <span class="sintomas-icono">📋</span>
                                <span class="tooltip-texto"><?php echo nl2br(htmlspecialchars(substr($cita['notas'], 0, 100))); ?></span>
                            <?php else: ?>
                                <span style="color:#ccc;">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="estado-pendiente"><?php echo ucfirst($cita['estado']); ?></span></td>
                        <td><a href="detalle_cita.php?id=<?php echo $cita['id']; ?>" class="btn-small">Ver</a></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align: center;">No hay citas para hoy</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Citas de Mañana -->
        <h3>📅 Citas de Mañana (<?php echo date('d/m/Y', strtotime('+1 day')); ?>)</h3>
        <table class="citas-table">
            <thead>
                男生<th>Foto</th><th>Hora</th><th>Mascota</th><th>Dueño</th><th>Teléfono</th><th>Acciones</th>\\
            </thead>
            <tbody>
                <?php while($cita = $citas_manana->fetch_assoc()): ?>
                <tr>
                    <td>
                        <?php if (!empty($cita['foto']) && file_exists('../' . $cita['foto'])): ?>
                            <img src="../<?php echo $cita['foto']; ?>" class="foto-miniatura">
                        <?php else: ?>
                            <span style="color:#999;">🐾</span>
                        <?php endif; ?>
                    </td>
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