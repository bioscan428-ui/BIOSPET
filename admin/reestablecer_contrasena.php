<?php
session_start();
require_once __DIR__ . '/../includes/conexion.php';

$error = '';
$mensaje = '';
$token = $_GET['token'] ?? '';

// Verificar token
if (empty($token)) {
    die("Token no válido");
}

$sql = "SELECT id, nombre_usuario FROM USUARIO 
        WHERE reset_token = ? AND reset_expira > NOW() AND activo = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("Enlace inválido o expirado. Solicita un nuevo restablecimiento.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmar = $_POST['confirmar_password'] ?? '';
    
    if (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } elseif ($password !== $confirmar) {
        $error = "Las contraseñas no coinciden.";
    } else {
        $nuevo_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $update = "UPDATE USUARIO 
                   SET contrasena = ?, reset_token = NULL, reset_expira = NULL 
                   WHERE id = ?";
        $stmt_up = $conn->prepare($update);
        $stmt_up->bind_param("si", $nuevo_hash, $user['id']);
        $stmt_up->execute();
        
        $mensaje = "Contraseña actualizada correctamente.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva Contraseña - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { background: var(--muted); display: flex; justify-content: center; align-items: center; height: 100vh; }
        .reset-box { background: white; padding: 40px; border-radius: var(--radius-md); box-shadow: var(--shadow-soft); width: 400px; }
        .reset-box h2 { color: var(--primary); text-align: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: var(--radius-sm); }
        .btn { width: 100%; background: var(--primary); color: white; border: none; padding: 12px; cursor: pointer; border-radius: var(--radius-sm); }
        .mensaje { color: green; text-align: center; margin-bottom: 15px; }
        .error { color: red; text-align: center; margin-bottom: 15px; }
        .volver { text-align: center; margin-top: 20px; }
        .volver a { color: var(--primary); text-decoration: none; }
    </style>
</head>
<body>
    <div class="reset-box">
        <h2>🐾 BIOSPET</h2>
        <h3 style="text-align: center;">Nueva Contraseña</h3>
        
        <?php if ($mensaje): ?>
            <div class="mensaje"><?php echo $mensaje; ?></div>
            <div class="volver"><a href="login.php">← Ir al inicio de sesión</a></div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <p style="text-align: center; margin-bottom: 20px;">Usuario: <strong><?php echo htmlspecialchars($user['nombre_usuario']); ?></strong></p>
            <form method="POST">
                <div class="form-group">
                    <input type="password" name="password" placeholder="Nueva contraseña" required>
                </div>
                <div class="form-group">
                    <input type="password" name="confirmar_password" placeholder="Confirmar contraseña" required>
                </div>
                <button type="submit" class="btn">Actualizar contraseña</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>