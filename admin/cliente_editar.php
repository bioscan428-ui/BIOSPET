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

$id_cliente = (int)($_GET['id'] ?? 0);
if (!$id_cliente) {
    header('Location: clientes.php');
    exit;
}

// Obtener datos del cliente
$sql_cliente = "SELECT * FROM CLIENTE WHERE id = ?";
$stmt = $conn->prepare($sql_cliente);
$stmt->bind_param("i", $id_cliente);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();

if (!$cliente) {
    header('Location: clientes.php');
    exit;
}

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $ape_pat = trim($_POST['ape_pat'] ?? '');
    $ape_mat = trim($_POST['ape_mat'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    $sql_update = "UPDATE CLIENTE SET 
                    nombre = ?, 
                    ape_pat = ?, 
                    ape_mat = ?, 
                    telefono = ?, 
                    email = ?, 
                    activo = ? 
                   WHERE id = ?";
    
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("sssssii", $nombre, $ape_pat, $ape_mat, $telefono, $email, $activo, $id_cliente);
    
    if ($stmt_update->execute()) {
        header('Location: clientes.php?success=1');
        exit;
    } else {
        $error = "Error al actualizar: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { background: var(--muted); }
        .admin-header { background: var(--primary); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        .admin-header a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 600px; margin: 20px auto; padding: 20px; background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-soft); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: var(--radius-sm); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .btn-guardar { background: var(--primary); color: white; padding: 12px 30px; border: none; border-radius: var(--radius-sm); cursor: pointer; }
        .btn-cancelar { background: #666; color: white; padding: 12px 30px; text-decoration: none; border-radius: var(--radius-sm); display: inline-block; margin-left: 10px; }
        .error { color: red; margin-bottom: 15px; padding: 10px; background: #ffebee; border-radius: var(--radius-sm); }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input { width: auto; }
        .checkbox-group label { margin: 0; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Editar Cliente</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="clientes.php">👥 Clientes</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <h2>Editar Cliente</h2>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Nombre(s) *</label>
                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($cliente['nombre']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Apellido Paterno</label>
                    <input type="text" name="ape_pat" value="<?php echo htmlspecialchars($cliente['ape_pat'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Apellido Materno</label>
                <input type="text" name="ape_mat" value="<?php echo htmlspecialchars($cliente['ape_mat'] ?? ''); ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="tel" name="telefono" value="<?php echo htmlspecialchars($cliente['telefono'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($cliente['email'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group checkbox-group">
                <input type="checkbox" name="activo" id="activo" <?php echo $cliente['activo'] ? 'checked' : ''; ?>>
                <label for="activo">Cliente activo</label>
            </div>
            
            <div style="margin-top: 30px;">
                <button type="submit" class="btn-guardar">Guardar Cambios</button>
                <a href="clientes.php" class="btn-cancelar">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>