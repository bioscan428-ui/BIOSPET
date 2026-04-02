<?php
require_once __DIR__ . '/../includes/conexion.php';

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    // Buscar empleado por email
    $sql = "SELECT u.id, u.nombre_usuario, e.email, e.nombre 
            FROM USUARIO u
            JOIN EMPLEADO e ON u.id_empleado = e.id
            WHERE e.email = ? AND u.activo = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Generar token único
        $token = bin2hex(random_bytes(32));
        $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $update = "UPDATE USUARIO SET reset_token = ?, reset_expira = ? WHERE id = ?";
        $stmt_up = $conn->prepare($update);
        $stmt_up->bind_param("ssi", $token, $expira, $user['id']);
        $stmt_up->execute();
        
        // Enviar email con enlace (usando mail() simple)
        $enlace = "https://biospet.bioscan.services/admin/reestablecer_contrasena.php?token=" . $token;
        $asunto = "Restablecer contraseña - BIOSPET";
        $mensaje_email = "Hola " . $user['nombre'] . ",\n\n";
        $mensaje_email .= "Haz clic en el siguiente enlace para restablecer tu contraseña:\n";
        $mensaje_email .= $enlace . "\n\n";
        $mensaje_email .= "Este enlace expirará en 1 hora.\n\n";
        $mensaje_email .= "Si no solicitaste este cambio, ignora este mensaje.";
        
        $headers = "From: no-reply@biospet.bioscan.services";
        
        if (mail($email, $asunto, $mensaje_email, $headers)) {
            $mensaje = "Se ha enviado un enlace a tu correo electrónico.";
        } else {
            $error = "Error al enviar el correo. Contacta al administrador.";
        }
    } else {
        $error = "No se encontró un usuario con ese correo electrónico.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Restablecer Contraseña - BIOSPET</title>
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
        <h3 style="text-align: center;">Restablecer Contraseña</h3>
        
        <?php if ($mensaje): ?>
            <div class="mensaje"><?php echo $mensaje; ?></div>
            <div class="volver"><a href="login.php">← Volver al inicio de sesión</a></div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <p style="text-align: center; margin-bottom: 20px;">Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.</p>
            <form method="POST">
                <div class="form-group">
                    <input type="email" name="email" placeholder="Correo electrónico" required>
                </div>
                <button type="submit" class="btn">Enviar enlace</button>
            </form>
            <div class="volver"><a href="login.php">← Volver al inicio de sesión</a></div>
        <?php endif; ?>
    </div>
</body>
</html>