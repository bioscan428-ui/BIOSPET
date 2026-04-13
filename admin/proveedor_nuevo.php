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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $contacto_nombre = trim($_POST['contacto_nombre'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    
    $sql = "INSERT INTO PROVEEDOR (nombre, contacto_nombre, telefono, email, direccion, activo) 
            VALUES (?, ?, ?, ?, ?, 1)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $nombre, $contacto_nombre, $telefono, $email, $direccion);
    
    if ($stmt->execute()) {
        $_SESSION['mensaje'] = "Proveedor registrado correctamente";
        header('Location: proveedores.php');
        exit;
    } else {
        $error = "Error al guardar: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Proveedor - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/proveedor_nuevo.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Nuevo Proveedor</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="proveedores.php">🏭 Proveedores</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <h2>Registrar Nuevo Proveedor</h2>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Nombre del proveedor *</label>
                <input type="text" name="nombre" required>
            </div>
            
            <div class="form-group">
                <label>Persona de contacto</label>
                <input type="text" name="contacto_nombre" placeholder="Ej: Juan Pérez">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="tel" name="telefono" placeholder="Ej: 555-1234567">
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="proveedor@ejemplo.com">
                </div>
            </div>
            
            <div class="form-group">
                <label>Dirección</label>
                <textarea name="direccion" placeholder="Dirección completa del proveedor"></textarea>
            </div>
            
            <div style="margin-top: 30px;">
                <button type="submit" class="btn-guardar">Guardar Proveedor</button>
                <a href="proveedores.php" class="btn-cancelar">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>