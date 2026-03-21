<?php
session_start();
if (!isset($_SESSION['admin_logged'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener todas las citas con datos completos
$sql = "SELECT 
            c.id,
            c.fecha_cita,
            c.hora_cita,
            c.estado,
            c.notas,
            m.nombre_mascota,
            m.especie,
            cl.nombre AS nombre_dueno,
            cl.telefono,
            cl.email,
            GROUP_CONCAT(s.nombre_servicio SEPARATOR ', ') AS servicios,
            SUM(dc.precio_fijado) AS total
        FROM CITA c
        JOIN MASCOTA m ON c.id_mascota = m.id
        JOIN CLIENTE cl ON m.id_cliente = cl.id
        JOIN DETALLE_CITA dc ON c.id = dc.id_cita
        JOIN SERVICIO s ON dc.id_servicio = s.id
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
    <style>
        body { background: var(--muted); }
        .admin-header { background: var(--primary); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        .admin-header a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 1400px; margin: 20px auto; padding: 0 20px; }
        .citas-table { width: 100%; background: white; border-radius: var(--radius-md); overflow: hidden; box-shadow: var(--shadow-soft); }
        .citas-table th, .citas-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .citas-table th { background: var(--black); color: white; }
        .citas-table tr:hover { background: #f9f9f9; }
        .estado-pendiente { background: #ff9800; color: white; padding: 4px 8px; border-radius: 20px; font-size: 12px; }
        .estado-confirmada { background: #4caf50; color: white; padding: 4px 8px; border-radius: 20px; font-size: 12px; }
        .estado-cancelada { background: #f44336; color: white; padding: 4px 8px; border-radius: 20px; font-size: 12px; }
        .estado-completada { background: #2196f3; color: white; padding: 4px 8px; border-radius: 20px; font-size: 12px; }
        .btn-small { background: var(--primary); color: white; padding: 5px 10px; border-radius: var(--radius-sm); text-decoration: none; font-size: 12px; }
        .btn-small:hover { background: var(--primary-dark); }
        .total-citas { background: white; padding: 15px; border-radius: var(--radius-md); margin-bottom: 20px; display: inline-block; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Panel de Administración</h1>
        <div>
            <a href="dashboard.php">📋 Citas</a>
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
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Dueño</th>
                    <th>Mascota</th>
                    <th>Servicios</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo date('d/m/Y', strtotime($row['fecha_cita'])); ?></td>
                    <td><?php echo $row['hora_cita']; ?></td>
                    <td>
                        <?php echo htmlspecialchars($row['nombre_dueno']); ?><br>
                        <small><?php echo $row['telefono']; ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($row['nombre_mascota']); ?><br>
                        <small><?php echo $row['especie']; ?></small>
                    </td>
                    <td><?php echo $row['servicios']; ?></td>
                    <td>$<?php echo number_format($row['total'], 2); ?></td>
                    <td>
                        <span class="estado-<?php echo $row['estado']; ?>">
                            <?php echo ucfirst($row['estado']); ?>
                        </span>
                    </td>
                    <td>
                        <a href="detalle_cita.php?id=<?php echo $row['id']; ?>" class="btn-small">Ver</a>
                        <a href="actualizar_estado.php?id=<?php echo $row['id']; ?>&estado=confirmada" class="btn-small">Confirmar</a>
                        <a href="actualizar_estado.php?id=<?php echo $row['id']; ?>&estado=cancelada" class="btn-small" style="background:#f44336;">Cancelar</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>