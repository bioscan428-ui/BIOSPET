<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Usar la vista para empleados sin usuario
$sql = "SELECT * FROM vista_empleados_activos WHERE nombre_usuario IS NULL ORDER BY puesto, nombre";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Empleados sin usuario - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        .container { max-width: 1000px; margin: 20px auto; padding: 20px; background: white; border-radius: 10px; }
        .btn-crear { background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .btn-small { background: var(--primary); color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none; font-size: 12px; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Empleados sin acceso al sistema</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="usuarios.php">👥 Usuarios</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <a href="usuario_nuevo.php" class="btn-crear">+ Crear usuario para empleado</a>
        
        <table>
            <thead>
                <tr><th>ID</th><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Puesto</th><th>Especialidad</th><th>Citas</th><th>Acción</th></tr>
            </thead>
            <tbody>
                <?php while($emp = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $emp['id']; ?></td>
                    <td><?php echo htmlspecialchars($emp['nombre'] . ' ' . $emp['ape_pat']); ?></td>
                    <td><?php echo htmlspecialchars($emp['email']); ?></td>
                    <td><?php echo $emp['telefono'] ?: '-'; ?></td>
                    <td><?php echo $emp['puesto']; ?></td>
                    <td><?php echo $emp['especialidad'] ?: '-'; ?></td>
                    <td><?php echo $emp['citas_asignadas']; ?></td>
                    <td><a href="usuario_nuevo.php?empleado_id=<?php echo $emp['id']; ?>" class="btn-small">Crear Usuario</a></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>