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

$id_proveedor = (int)($_GET['id'] ?? 0);
if (!$id_proveedor) {
    header('Location: proveedores.php');
    exit;
}

// Obtener datos del proveedor
$sql = "SELECT * FROM PROVEEDOR WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_proveedor);
$stmt->execute();
$proveedor = $stmt->get_result()->fetch_assoc();

if (!$proveedor) {
    header('Location: proveedores.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $contacto_nombre = trim($_POST['contacto_nombre'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    $sql_update = "UPDATE PROVEEDOR SET 
                    nombre = ?, 
                    contacto_nombre = ?, 
                    telefono = ?, 
                    email = ?, 
                    direccion = ?, 
                    activo = ? 
                   WHERE id = ?";
    
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("sssssii", $nombre, $contacto_nombre, $telefono, $email, $direccion, $activo, $id_proveedor);
    
    if ($stmt_update->execute()) {
        $_SESSION['mensaje'] = "Proveedor actualizado correctamente";
        header('Location: proveedores.php');
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
    <title>Editar Proveedor - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/proveedor_editar.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Editar Proveedor</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="proveedores.php">🏭 Proveedores</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <h2>Editar Proveedor: <?php echo htmlspecialchars($proveedor['nombre']); ?></h2>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Nombre del proveedor *</label>
                <input type="text" name="nombre" value="<?php echo htmlspecialchars($proveedor['nombre']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Persona de contacto</label>
                <input type="text" name="contacto_nombre" value="<?php echo htmlspecialchars($proveedor['contacto_nombre'] ?? ''); ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="tel" name="telefono" value="<?php echo htmlspecialchars($proveedor['telefono'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($proveedor['email'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Dirección</label>
                <textarea name="direccion"><?php echo htmlspecialchars($proveedor['direccion'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group checkbox-group">
                <input type="checkbox" name="activo" id="activo" <?php echo $proveedor['activo'] ? 'checked' : ''; ?>>
                <label for="activo">Proveedor activo</label>
            </div>
            
            <div style="margin-top: 30px;">
                <button type="submit" class="btn-guardar">Guardar Cambios</button>
                <a href="proveedores.php" class="btn-cancelar">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>