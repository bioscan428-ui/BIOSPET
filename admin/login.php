<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['usuario'] ?? '';
    $pass = $_POST['password'] ?? '';
    
    // CAMBIA ESTOS DATOS por los que quieras usar
    $usuario_valido = 'admin';
    $password_valido = 'biospet2025';
    
    if ($user === $usuario_valido && $pass === $password_valido) {
        $_SESSION['admin_logged'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Usuario o contraseña incorrectos";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Admin BIOSPET - Login</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { background: var(--muted); display: flex; justify-content: center; align-items: center; height: 100vh; }
        .login-box { background: var(--white); padding: 40px; border-radius: var(--radius-md); box-shadow: var(--shadow-soft); width: 350px; }
        .login-box h2 { color: var(--primary); margin-bottom: 20px; text-align: center; }
        .form-group { margin-bottom: 15px; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: var(--radius-sm); }
        .btn { width: 100%; background: var(--primary); color: white; border: none; padding: 10px; cursor: pointer; }
        .error { color: red; text-align: center; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>🔐 BIOSPET Admin</h2>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="POST">
            <div class="form-group">
                <input type="text" name="usuario" placeholder="Usuario" required>
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="Contraseña" required>
            </div>
            <button type="submit" class="btn">Ingresar</button>
        </form>
    </div>
</body>
</html>