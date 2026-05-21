<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'grooming') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener citas de estética asignadas al groomer (con foto y notas)
$sql = "SELECT 
            c.id,
            c.fecha_cita,
            c.hora_cita,
            c.estado,
            c.notas,
            m.nombre_mascota,
            m.especie,
            m.raza,
            m.foto,
            cl.nombre AS nombre_dueno,
            cl.ape_pat,
            cl.ape_mat,
            cl.telefono,
            GROUP_CONCAT(s.nombre_servicio SEPARATOR ', ') AS servicios
        FROM CITA c
        JOIN ASIGNACION_CITA ac ON c.id = ac.id_cita
        JOIN MASCOTA m ON c.id_mascota = m.id
        JOIN CLIENTE cl ON m.id_cliente = cl.id
        LEFT JOIN DETALLE_CITA dc ON c.id = dc.id_cita
        LEFT JOIN SERVICIO s ON dc.id_servicio = s.id
        WHERE ac.id_empleado = ? 
          AND ac.rol_asignado = 'grooming'
          AND (s.nombre_servicio LIKE '%estetica%' 
               OR s.nombre_servicio LIKE '%baño%' 
               OR s.nombre_servicio LIKE '%corte%'
               OR s.nombre_servicio LIKE '%cepillado%'
               OR s.nombre_servicio LIKE '%uñas%'
               OR s.nombre_servicio LIKE '%grooming%')
        GROUP BY c.id
        ORDER BY c.fecha_cita ASC, c.hora_cita ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['empleado_id']);
$stmt->execute();
$citas = $stmt->get_result();

// Obtener estadísticas
$stats = [];
$stats['citas_hoy'] = $conn->query("SELECT COUNT(*) FROM CITA c 
                                    JOIN ASIGNACION_CITA ac ON c.id = ac.id_cita 
                                    WHERE ac.id_empleado = {$_SESSION['empleado_id']} 
                                      AND ac.rol_asignado = 'grooming' 
                                      AND c.fecha_cita = CURDATE()")->fetch_row()[0];
$stats['citas_pendientes'] = $conn->query("SELECT COUNT(*) FROM CITA c 
                                           JOIN ASIGNACION_CITA ac ON c.id = ac.id_cita 
                                           WHERE ac.id_empleado = {$_SESSION['empleado_id']} 
                                             AND ac.rol_asignado = 'grooming' 
                                             AND c.estado = 'pendiente'")->fetch_row()[0];
$stats['citas_semana'] = $conn->query("SELECT COUNT(*) FROM CITA c 
                                       JOIN ASIGNACION_CITA ac ON c.id = ac.id_cita 
                                       WHERE ac.id_empleado = {$_SESSION['empleado_id']} 
                                         AND ac.rol_asignado = 'grooming' 
                                         AND c.fecha_cita BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetch_row()[0];

// Obtener citas de hoy
$sql_hoy = "SELECT 
                c.id,
                c.hora_cita,
                c.estado,
                c.notas,
                m.nombre_mascota,
                m.raza,
                m.foto,
                cl.nombre AS nombre_dueno,
                cl.telefono,
                GROUP_CONCAT(s.nombre_servicio SEPARATOR ', ') AS servicios
            FROM CITA c
            JOIN ASIGNACION_CITA ac ON c.id = ac.id_cita
            JOIN MASCOTA m ON c.id_mascota = m.id
            JOIN CLIENTE cl ON m.id_cliente = cl.id
            LEFT JOIN DETALLE_CITA dc ON c.id = dc.id_cita
            LEFT JOIN SERVICIO s ON dc.id_servicio = s.id
            WHERE ac.id_empleado = ? 
              AND ac.rol_asignado = 'grooming'
              AND c.fecha_cita = CURDATE()
            GROUP BY c.id
            ORDER BY c.hora_cita";
$stmt_hoy = $conn->prepare($sql_hoy);
$stmt_hoy->bind_param("i", $_SESSION['empleado_id']);
$stmt_hoy->execute();
$citas_hoy = $stmt_hoy->get_result();

// Obtener citas de la semana (próximas)
$sql_semana = "SELECT 
                   c.id,
                   c.fecha_cita,
                   c.hora_cita,
                   c.estado,
                   m.nombre_mascota,
                   m.raza,
                   m.foto,
                   cl.nombre AS nombre_dueno,
                   cl.telefono
               FROM CITA c
               JOIN ASIGNACION_CITA ac ON c.id = ac.id_cita
               JOIN MASCOTA m ON c.id_mascota = m.id
               JOIN CLIENTE cl ON m.id_cliente = cl.id
               WHERE ac.id_empleado = ? 
                 AND ac.rol_asignado = 'grooming'
                 AND c.fecha_cita > CURDATE() 
                 AND c.fecha_cita <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
               ORDER BY c.fecha_cita ASC, c.hora_cita ASC";
$stmt_semana = $conn->prepare($sql_semana);
$stmt_semana->bind_param("i", $_SESSION['empleado_id']);
$stmt_semana->execute();
$citas_semana = $stmt_semana->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Estética - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { background: #f5f5f5; }
        .admin-header { background: #E68D0B; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .admin-header a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .bienvenida { background: white; padding: 20px; border-radius: 15px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 15px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-card .numero { font-size: 2rem; font-weight: bold; color: #E68D0B; }
        .stat-card .label { font-size: 0.8rem; color: #666; margin-top: 5px; }
        .citas-table { width: 100%; background: white; border-radius: 15px; overflow: hidden; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .citas-table th, .citas-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .citas-table th { background: #333; color: white; }
        .btn-small { background: #E68D0B; color: white; padding: 5px 12px; border-radius: 20px; text-decoration: none; font-size: 12px; display: inline-block; }
        .btn-small:hover { background: #d67f00; }
        .estado-pendiente { background: #ff9800; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .estado-confirmada { background: #4caf50; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .estado-cancelada { background: #f44336; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
        .estado-completada { background: #2196f3; color: white; padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
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
            color: #E68D0B;
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
            font-size: 12px;
            min-width: 200px;
            white-space: normal;
        }
        .sintomas-tooltip:hover .tooltip-texto {
            visibility: visible;
        }
        .servicios-badge {
            background: #f0f0f0;
            color: #666;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            display: inline-block;
            margin: 2px;
        }
        h3 { margin: 25px 0 15px 0; color: #333; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Panel de Estética (Grooming)</h1>
        <div>
            <a href="grooming_dashboard.php">📋 Mis Citas</a>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <div class="bienvenida">
            <div>
                <h2>Bienvenido/a <?php echo htmlspecialchars($_SESSION['nombre']); ?></h2>
                <p>Hoy es <?php echo date('d/m/Y'); ?></p>
            </div>
            <div class="stats-mini">
                <span>🕐 <?php echo date('h:i A'); ?></span>
            </div>
        </div>

        <!-- Estadísticas -->
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
                <div class="numero"><?php echo $stats['citas_semana']; ?></div>
                <div class="label">Citas Esta Semana</div>
            </div>
        </div>

        <!-- Citas de Hoy -->
        <h3>📋 Citas de Estética - Hoy (<?php echo date('d/m/Y'); ?>)</h3>
        <table class="citas-table">
            <thead>
                <tr>
                    <th>Hora</th>
                    <th>Foto</th>
                    <th>Mascota</th>
                    <th>Raza</th>
                    <th>Dueño</th>
                    <th>Teléfono</th>
                    <th>Servicios</th>
                    <th>Notas</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($citas_hoy->num_rows > 0): ?>
                    <?php while($cita = $citas_hoy->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo $cita['hora_cita']; ?></strong></td>
                        <td>
                            <?php if (!empty($cita['foto']) && file_exists('../' . $cita['foto'])): ?>
                                <img src="../<?php echo $cita['foto']; ?>" class="foto-miniatura" title="Foto de <?php echo $cita['nombre_mascota']; ?>">
                            <?php else: ?>
                                <span style="color:#999; font-size:20px;">🐾</span>
                            <?php endif; ?>
                         </span>
                        <td><strong><?php echo htmlspecialchars($cita['nombre_mascota']); ?></strong></td>
                        <td><?php echo $cita['raza'] ?: '—'; ?></td>
                        <td><?php echo htmlspecialchars($cita['nombre_dueno']); ?></td>
                        <td><?php echo $cita['telefono']; ?></td>
                        <td>
                            <?php 
                            $servicios_lista = explode(',', $cita['servicios']);
                            foreach($servicios_lista as $serv):
                                echo '<span class="servicios-badge">' . trim($serv) . '</span> ';
                            endforeach;
                            ?>
                         </span>
                        <td class="sintomas-tooltip">
                            <?php if (!empty($cita['notas'])): ?>
                                <span class="sintomas-icono">📋</span>
                                <span class="tooltip-texto">
                                    <strong>Notas:</strong><br>
                                    <?php echo nl2br(htmlspecialchars(substr($cita['notas'], 0, 150))); ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#ccc;">—</span>
                            <?php endif; ?>
                         </span>
                        <td>
                            <span class="estado-<?php echo $cita['estado']; ?>">
                                <?php echo ucfirst($cita['estado']); ?>
                            </span>
                         </span>
                        <td>
                            <a href="detalle_cita.php?id=<?php echo $cita['id']; ?>" class="btn-small">Ver</a>
                            <?php if ($cita['estado'] == 'confirmada'): ?>
                                <a href="actualizar_estado.php?id=<?php echo $cita['id']; ?>&estado=completada" class="btn-small" style="background:#2196f3;">Completar</a>
                            <?php endif; ?>
                         </span>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align: center;">No tienes citas de estética para hoy</span>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Próximas Citas (7 días) -->
        <h3>📅 Próximas Citas de Estética (7 días)</h3>
        <table class="citas-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Foto</th>
                    <th>Mascota</th>
                    <th>Raza</th>
                    <th>Dueño</th>
                    <th>Teléfono</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($citas_semana->num_rows > 0): ?>
                    <?php while($cita = $citas_semana->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
                        <td><?php echo $cita['hora_cita']; ?></td>
                        <td>
                            <?php if (!empty($cita['foto']) && file_exists('../' . $cita['foto'])): ?>
                                <img src="../<?php echo $cita['foto']; ?>" class="foto-miniatura">
                            <?php else: ?>
                                <span style="color:#999;">🐾</span>
                            <?php endif; ?>
                         </span>
                        <td><?php echo htmlspecialchars($cita['nombre_mascota']); ?></span>
                        <td><?php echo $cita['raza'] ?: '—'; ?></span>
                        <td><?php echo htmlspecialchars($cita['nombre_dueno']); ?></span>
                        <td><?php echo $cita['telefono']; ?></span>
                        <td>
                            <span class="estado-<?php echo $cita['estado']; ?>">
                                <?php echo ucfirst($cita['estado']); ?>
                            </span>
                         </span>
                        <td><a href="detalle_cita.php?id=<?php echo $cita['id']; ?>" class="btn-small">Ver</a></span>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center;">No hay citas de estética programadas para los próximos días</span>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>