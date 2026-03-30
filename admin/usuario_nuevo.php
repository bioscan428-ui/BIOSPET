<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener empleados sin usuario
$sql_empleados = "SELECT e.* FROM EMPLEADO e 
                  LEFT JOIN USUARIO u ON e.id = u.id_empleado 
                  WHERE u.id IS NULL AND e.activo = 1";
$empleados = $conn->query($sql_empleados);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_empleado = (int)$_POST['id_empleado'];
    $nombre_usuario = trim($_POST['nombre_usuario']);
    $contrasena = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);
    $rol = $_POST['rol'];
    
    $sql = "INSERT INTO USUARIO (id_empleado, nombre_usuario, contrasena, rol) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $id_empleado, $nombre_usuario, $contrasena, $rol);
    
    if ($stmt->execute()) {
        header('Location: usuarios.php?success=1');
        exit;
    } else {
        $error = "Error al crear usuario: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Usuario - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { background: var(--muted); }
        .admin-header { background: var(--primary); color: white; padding: 20px; }
        .admin-header a { color: white; margin-left: 20px; }
        .container { max-width: 600px; margin: 20px auto; background: white; padding: 30px; border-radius: var(--radius-md); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: var(--radius-sm); }
        .btn { background: var(--primary); color: white; padding: 12px 30px; border: none; border-radius: var(--radius-sm); cursor: pointer; }
        .error { color: red; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Nuevo Usuario</h1>
        <a href="usuarios.php">← Volver</a>
    </div>

    <div class="container">
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Empleado *</label>
                <select name="id_empleado" required>
                    <option value="">Seleccionar empleado...</option>
                    <?php while($emp = $empleados->fetch_assoc()): ?>
                        <option value="<?php echo $emp['id']; ?>">
                            <?php echo htmlspecialchars($emp['nombre'] . ' ' . $emp['ape_pat'] . ' - ' . $emp['puesto']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Nombre de usuario *</label>
                <input type="text" name="nombre_usuario" required>
            </div>
            
            <div class="form-group">
                <label>Contraseña *</label>
                <input type="password" name="contrasena" required>
            </div>
            
            <div class="form-group">
                <label>Rol *</label>
                <select name="rol" required>
                    <option value="admin">Administrador</option>
                    <option value="veterinario">Veterinario</option>
                    <option value="asistente">Asistente</option>
                    <option value="recepcionista">Recepcionista</option>
                </select>
                <small>Super Admin solo puede ser asignado por el sistema</small>
            </div>
            
            <button type="submit" class="btn">Crear Usuario</button>
        </form>
    </div>
</body>
</html>