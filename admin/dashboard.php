<?php
session_start();

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol para acceso
if (!in_array($_SESSION['rol'], ['super_admin', 'admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener todas las citas (incluyendo foto de mascota y notas)
$sql = "SELECT 
            c.id,
            c.fecha_cita,
            c.hora_cita,
            c.estado,
            c.notas,
            m.nombre_mascota,
            m.especie,
            m.foto,
            cl.nombre AS nombre_dueno,
            cl.telefono,
            cl.email,
            GROUP_CONCAT(s.nombre_servicio SEPARATOR ', ') AS servicios,
            IFNULL(SUM(dc.precio_fijado), 0) AS total
        FROM CITA c
        JOIN MASCOTA m ON c.id_mascota = m.id
        JOIN CLIENTE cl ON m.id_cliente = cl.id
        LEFT JOIN DETALLE_CITA dc ON c.id = dc.id_cita
        LEFT JOIN SERVICIO s ON dc.id_servicio = s.id
        GROUP BY c.id
        ORDER BY c.fecha_cita DESC, c.hora_cita DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Admin - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .sin-servicios { color: #999; font-style: italic; }
        .total-citas { background: white; padding: 15px; border-radius: var(--radius-md); margin-bottom: 20px; display: inline-block; }
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
        }
        .sintomas-tooltip:hover .tooltip-texto {
            visibility: visible;
        }
        .notas-resumen {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Panel de Administración</h1>
        <div>
            <a href="dashboard.php">📋 Citas</a>
            <a href="calendario.php">📅 Calendario</a>
            <a href="reportes.php">📊 Reportes</a>
            <a href="productos.php">🛒 Productos</a>
            <?php if ($_SESSION['rol'] === 'super_admin'): ?>
            <a href="usuarios.php">👥 Usuarios</a>
            <?php endif; ?>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <div class="total-citas">
            <strong>Total de citas:</strong> <?php echo $result->num_rows; ?>
        </div>

        <table class="citas-table">
            <thead>
                构建
                    <th>ID</th>
                    <th>Foto</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Dueño</th>
                    <th>Mascota</th>
                    <th>Motivo / Síntomas</th>
                    <th>Servicios</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                 <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td>
                        <?php if (!empty($row['foto']) && file_exists('../' . $row['foto'])): ?>
                            <img src="../<?php echo $row['foto']; ?>" alt="Foto de <?php echo $row['nombre_mascota']; ?>" class="foto-miniatura">
                        <?php else: ?>
                            <span style="color:#999; font-size:20px;">🐾</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('d/m/Y', strtotime($row['fecha_cita'])); ?></td>
                    <td><?php echo $row['hora_cita']; ?></td>
                    <td>
                        <?php echo htmlspecialchars($row['nombre_dueno']); ?><br>
                        <small><?php echo $row['telefono']; ?></small>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($row['nombre_mascota']); ?><br>
                        <small><?php echo $row['especie']; ?></small>
                    </td>
                    <td class="sintomas-tooltip">
                        <?php if (!empty($row['notas'])): ?>
                            <span class="sintomas-icono">📋</span>
                            <span class="tooltip-texto">
                                <strong>Motivo de consulta:</strong><br>
                                <?php echo nl2br(htmlspecialchars(substr($row['notas'], 0, 150))); ?>
                                <?php if (strlen($row['notas']) > 150): ?>...<?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#ccc;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($row['servicios'])): ?>
                            <?php echo $row['servicios']; ?>
                        <?php else: ?>
                            <span class="sin-servicios">(Sin servicios)</span>
                        <?php endif; ?>
                    </td>
                    <td>$<?php echo number_format($row['total'], 2); ?></td>
                    <td>
                        <span class="estado-<?php echo $row['estado']; ?>">
                            <?php echo ucfirst($row['estado']); ?>
                        </span>
                    </td>
                    <td>
                        <a href="detalle_cita.php?id=<?php echo $row['id']; ?>" class="btn-small">Ver</a>
                        <?php if ($row['estado'] == 'pendiente'): ?>
                            <a href="actualizar_estado.php?id=<?php echo $row['id']; ?>&estado=confirmada" class="btn-small">Confirmar</a>
                        <?php endif; ?>
                        <?php if ($row['estado'] != 'cancelada' && $row['estado'] != 'completada'): ?>
                            <a href="actualizar_estado.php?id=<?php echo $row['id']; ?>&estado=cancelada" class="btn-small" style="background:#f44336;">Cancelar</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
         </table>
    </div>
</body>
</html>