<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'veterinario') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener pacientes (mascotas) que ha atendido el veterinario
$sql = "SELECT DISTINCT 
            m.id,
            m.nombre_mascota,
            m.especie,
            m.raza,
            cl.nombre AS dueno,
            cl.telefono,
            MAX(c.fecha_cita) AS ultima_cita
        FROM CITA c
        JOIN ASIGNACION_CITA ac ON c.id = ac.id_cita
        JOIN MASCOTA m ON c.id_mascota = m.id
        JOIN CLIENTE cl ON m.id_cliente = cl.id
        WHERE ac.id_empleado = ? AND ac.rol_asignado = 'veterinario'
        GROUP BY m.id
        ORDER BY ultima_cita DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['empleado_id']);
$stmt->execute();
$pacientes = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Pacientes - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { background: var(--muted); }
        .admin-header { background: var(--primary); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        .admin-header a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 1000px; margin: 20px auto; padding: 0 20px; }
        .pacientes-table { width: 100%; background: white; border-radius: var(--radius-md); overflow: hidden; box-shadow: var(--shadow-soft); }
        .pacientes-table th, .pacientes-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .pacientes-table th { background: var(--black); color: white; }
        .btn-small { background: var(--primary); color: white; padding: 5px 10px; border-radius: var(--radius-sm); text-decoration: none; font-size: 12px; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Mis Pacientes</h1>
        <div>
            <a href="veterinario_dashboard.php">📋 Mis Citas</a>
            <a href="veterinario_pacientes.php">🐕 Mis Pacientes</a>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <h3>🐕 Pacientes que he atendido</h3>
        <table class="pacientes-table">
            <thead>
                <tr><th>ID</th><th>Nombre</th><th>Especie</th><th>Raza</th><th>Dueño</th><th>Teléfono</th><th>Última cita</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php while($paciente = $pacientes->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $paciente['id']; ?></td>
                    <td><?php echo htmlspecialchars($paciente['nombre_mascota']); ?></td>
                    <td><?php echo $paciente['especie']; ?></td>
                    <td><?php echo $paciente['raza'] ?: 'N/A'; ?></td>
                    <td><?php echo htmlspecialchars($paciente['dueno']); ?></td>
                    <td><?php echo $paciente['telefono']; ?></td>
                    <td><?php echo date('d/m/Y', strtotime($paciente['ultima_cita'])); ?></td>
                    <td><a href="historial_mascota.php?id=<?php echo $paciente['id']; ?>" class="btn-small">Ver Historial</a></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>