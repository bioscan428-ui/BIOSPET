<?php
session_start();

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol para acceso (solo admin y super_admin)
if (!in_array($_SESSION['rol'], ['super_admin', 'admin'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Procesar eliminación de horario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar') {
    $id_horario = (int)$_POST['id_horario'];
    $conn->query("DELETE FROM HORARIO_EMPLEADO WHERE id = $id_horario");
    $_SESSION['mensaje'] = "Horario eliminado correctamente";
    header('Location: horarios_empleado.php');
    exit;
}

// Obtener todos los empleados (primero)
$sql_empleados = "SELECT id, nombre, ape_pat, puesto FROM EMPLEADO WHERE activo = 1 ORDER BY puesto, nombre";
$empleados = $conn->query($sql_empleados);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horarios de Empleados - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/horarios_empleado.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Horarios de Empleados</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="usuarios.php">👥 Usuarios</a>
            <a href="horarios_empleado.php">🕐 Horarios</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn-volver">← Volver al Dashboard</a>
        
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="alert-success"><?php echo $_SESSION['mensaje']; ?></div>
            <?php unset($_SESSION['mensaje']); ?>
        <?php endif; ?>

        <table class="horarios-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Empleado</th>
                    <th>Puesto</th>
                    <th>Horarios</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while($emp = $empleados->fetch_assoc()): 
                    // Obtener horarios de este empleado
                    $sql_horarios = "SELECT dia_semana, DATE_FORMAT(hora_entrada, '%H:%i') as hora_entrada, DATE_FORMAT(hora_salida, '%H:%i') as hora_salida 
                                    FROM HORARIO_EMPLEADO 
                                    WHERE id_empleado = ? AND activo = 1 
                                    ORDER BY FIELD(dia_semana, 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo')";
                    $stmt_h = $conn->prepare($sql_horarios);
                    $stmt_h->bind_param("i", $emp['id']);
                    $stmt_h->execute();
                    $horarios = $stmt_h->get_result();
                ?>
                <tr>
                    <td><?php echo $emp['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($emp['nombre'] . ' ' . $emp['ape_pat']); ?></strong></td>
                    <td><?php echo $emp['puesto']; ?></td>
                    <td>
                        <?php if ($horarios->num_rows > 0): ?>
                            <?php while($h = $horarios->fetch_assoc()): ?>
                                <span class="horario-badge">
                                    <?php echo ucfirst($h['dia_semana']); ?>: <?php echo $h['hora_entrada']; ?> - <?php echo $h['hora_salida']; ?>
                                </span>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <span class="sin-horario">Sin horario asignado</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="horario_asignar.php?empleado_id=<?php echo $emp['id']; ?>" class="btn-asignar">✏️ Asignar Horario</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>