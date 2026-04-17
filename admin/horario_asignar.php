<?php
session_start();

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol para acceso
if (!in_array($_SESSION['rol'], ['super_admin', 'admin'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$empleado_id = (int)($_GET['empleado_id'] ?? 0);
if (!$empleado_id) {
    header('Location: horarios_empleado.php');
    exit;
}

// Obtener datos del empleado
$sql_emp = "SELECT id, nombre, ape_pat, puesto FROM EMPLEADO WHERE id = ? AND activo = 1";
$stmt_emp = $conn->prepare($sql_emp);
$stmt_emp->bind_param("i", $empleado_id);
$stmt_emp->execute();
$empleado = $stmt_emp->get_result()->fetch_assoc();

if (!$empleado) {
    header('Location: horarios_empleado.php');
    exit;
}

// Obtener horarios actuales del empleado
$sql_horarios = "SELECT * FROM HORARIO_EMPLEADO WHERE id_empleado = ? AND activo = 1 ORDER BY FIELD(dia_semana, 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo')";
$stmt_hor = $conn->prepare($sql_horarios);
$stmt_hor->bind_param("i", $empleado_id);
$stmt_hor->execute();
$horarios_existentes = $stmt_hor->get_result();

// Procesar asignación/actualización de horario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dia_semana = $_POST['dia_semana'];
    $hora_entrada = $_POST['hora_entrada'];
    $hora_salida = $_POST['hora_salida'];
    
    // Validar que hora_salida > hora_entrada
    if ($hora_salida <= $hora_entrada) {
        $error = "La hora de salida debe ser mayor a la hora de entrada";
    } else {
        // Usar el procedimiento asignar_horario
        $stmt = $conn->prepare("CALL asignar_horario(?, ?, ?, ?)");
        $stmt->bind_param("isss", $empleado_id, $dia_semana, $hora_entrada, $hora_salida);
        $stmt->execute();
        $stmt->close();
        $conn->next_result();
        
        $_SESSION['mensaje'] = "Horario asignado correctamente";
        header('Location: horarios_empleado.php');
        exit;
    }
}

// Días de la semana en orden
$dias_semana = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignar Horario - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/horario_asignar.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Asignar Horario</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="horarios_empleado.php">🕐 Horarios</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <h2>Asignar Horario a <?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['ape_pat']); ?></h2>
        <p><strong>Puesto:</strong> <?php echo ucfirst($empleado['puesto']); ?></p>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Horarios actuales -->
        <?php if ($horarios_existentes->num_rows > 0): ?>
            <div class="horarios-actuales">
                <strong>📅 Horarios actuales:</strong><br>
                <?php while($h = $horarios_existentes->fetch_assoc()): ?>
                    <span class="horario-item">
                        <?php echo ucfirst($h['dia_semana']); ?>: <?php echo substr($h['hora_entrada'], 0, 5); ?> - <?php echo substr($h['hora_salida'], 0, 5); ?>
                    </span>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
        
        <hr>
        
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Día de la semana *</label>
                    <select name="dia_semana" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach($dias_semana as $dia): ?>
                            <option value="<?php echo $dia; ?>"><?php echo ucfirst($dia); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Hora de entrada *</label>
                    <input type="time" name="hora_entrada" required>
                </div>
                
                <div class="form-group">
                    <label>Hora de salida *</label>
                    <input type="time" name="hora_salida" required>
                </div>
            </div>
            
            <div style="margin-top: 30px;">
                <button type="submit" class="btn-guardar">Guardar Horario</button>
                <a href="horarios_empleado.php" class="btn-cancelar">Cancelar</a>
            </div>
        </form>
        
        <hr>
        
        <p style="color: #666; font-size: 12px; margin-top: 20px;">
            ⚠️ Nota: Si ya existe un horario para el día seleccionado, será actualizado.
        </p>
    </div>
</body>
</html>