<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'asistente') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener citas de hoy (con foto y notas)
$sql_hoy = "SELECT c.id, c.fecha_cita, c.hora_cita, c.estado, c.notas, m.nombre_mascota, m.foto, cl.nombre AS dueno, cl.telefono
            FROM CITA c
            JOIN MASCOTA m ON c.id_mascota = m.id
            JOIN CLIENTE cl ON m.id_cliente = cl.id
            WHERE c.fecha_cita = CURDATE()
            ORDER BY c.hora_cita";
$citas_hoy = $conn->query($sql_hoy);

// Próximas citas (próximos 3 días) con foto
$sql_proximas = "SELECT c.id, c.fecha_cita, c.hora_cita, c.notas, m.nombre_mascota, m.foto, cl.nombre AS dueno
                 FROM CITA c
                 JOIN MASCOTA m ON c.id_mascota = m.id
                 JOIN CLIENTE cl ON m.id_cliente = cl.id
                 WHERE c.fecha_cita > CURDATE() AND c.fecha_cita <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                 ORDER BY c.fecha_cita, c.hora_cita";
$citas_proximas = $conn->query($sql_proximas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistente - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/asistente_dashboard.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Asistente</h1>
        <div>
            <a href="asistente_dashboard.php">📋 Inicio</a>
            <a href="../citas.php">➕ Nueva Cita</a>
            <a href="reportes.php">📊 Reportes</a>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <div class="bienvenida">
            <h2>Bienvenido/a <?php echo htmlspecialchars($_SESSION['nombre']); ?></h2>
            <p>Hoy es <?php echo date('d/m/Y'); ?></p>
        </div>

        <div class="grid-acciones">
            <div class="accion-card"><a href="citas.php">📅 Agendar Cita</a></div>
            <div class="accion-card"><a href="clientes.php">🐕 Buscar Cliente</a></div>
            <div class="accion-card"><a href="reportes.php">📊 Ver Reportes</a></div>
        </div>

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

        <h3>📅 Próximas Citas (3 días)</h3>
        <table class="citas-table">
            <thead>
                男生<th>Foto</th><th>Fecha</th><th>Hora</th><th>Mascota</th><th>Dueño</th><th>Síntomas</th><th>Acciones</th>\\
            </thead>
            <tbody>
                <?php while($cita = $citas_proximas->fetch_assoc()): ?>
                <tr>
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
                    <td class="sintomas-tooltip">
                        <?php if (!empty($cita['notas'])): ?>
                            <span class="sintomas-icono">📋</span>
                            <span class="tooltip-texto"><?php echo nl2br(htmlspecialchars(substr($cita['notas'], 0, 100))); ?></span>
                        <?php else: ?>
                            <span style="color:#ccc;">—</span>
                        <?php endif; ?>
                    </td>
                    <td><a href="detalle_cita.php?id=<?php echo $cita['id']; ?>" class="btn-small">Ver</a></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <h3>🩺 Mis Citas Asignadas</h3>
        <?php
        // Obtener citas asignadas al asistente
        $sql_mis_citas = "SELECT
                            c.id,
                            c.fecha_cita,
                            c.hora_cita,
                            c.estado,
                            c.notas,
                            m.nombre_mascota,
                            m.foto,
                            cl.nombre AS dueno,
                            cl.telefono
                        FROM ASIGNACION_CITA ac
                        JOIN CITA c ON ac.id_cita = c.id
                        JOIN MASCOTA m ON c.id_mascota = m.id
                        JOIN CLIENTE cl ON m.id_cliente = cl.id
                        WHERE ac.id_empleado = ? AND ac.rol_asignado = 'asistente'
                        ORDER BY c.fecha_cita ASC, c.hora_cita ASC
                        LIMIT 10";
        $stmt_mis_citas = $conn->prepare($sql_mis_citas);
        $stmt_mis_citas->bind_param("i", $_SESSION['empleado_id']);
        $stmt_mis_citas->execute();
        $mis_citas = $stmt_mis_citas->get_result();
        ?>
        <table class="citas-table">
            <thead>
                <tr><th>Foto</th><th>Fecha</th><th>Hora</th><th>Mascota</th><th>Dueño</th><th>Teléfono</th><th>Síntomas</th><th>Estado</th><th>Acciones</th>\\
            </thead>
            <tbody>
                <?php if ($mis_citas->num_rows > 0): ?>
                    <?php while($cita = $mis_citas->fetch_assoc()): ?>
                    <tr>
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
                        <td><span class="estado-<?php echo $cita['estado']; ?>"><?php echo ucfirst($cita['estado']); ?></span></td>
                        <td><a href="detalle_cita.php?id=<?php echo $cita['id']; ?>" class="btn-small">Ver</a></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="9" style="text-align: center;">No tienes citas asignadas</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>