<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Si ya viene logueado del modal con las llaves correctas, mandarlo directo al panel
if (isset($_SESSION['logueado']) && $_SESSION['logueado'] === true && isset($_SESSION['rol'])) {
    switch ($_SESSION['rol']) {
        case 'super_admin':
            header('Location: dashboard.php'); exit;
        case 'admin':
            header('Location: admin_dashboard.php'); exit;
        case 'veterinario':
            header('Location: veterinario_dashboard.php'); exit;
        case 'asistente':
            header('Location: asistente_dashboard.php'); exit;
        case 'recepcionista':
            header('Location: recepcionista_dashboard.php'); exit;
        case 'grooming':
            header('Location: grooming_dashboard.php'); exit;
        case 'caja':
            header('Location: caja_dashboard.php'); exit;
        default:
            header('Location: dashboard.php'); exit;
    }
}

require_once __DIR__ . '/../includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    $sql = "SELECT u.*, e.nombre, e.ape_pat, e.puesto 
            FROM USUARIO u
            JOIN EMPLEADO e ON u.id_empleado = e.id
            WHERE u.nombre_usuario = ? AND u.activo = 1 AND e.activo = 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['contrasena'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['empleado_id'] = $user['id_empleado'];
            $_SESSION['nombre'] = $user['nombre'] . ' ' . $user['ape_pat'];
            $_SESSION['rol'] = $user['rol'];
            $_SESSION['usuario'] = $user['nombre_usuario'];
            $_SESSION['logueado'] = true;
            
            $update = "UPDATE USUARIO SET ultimo_acceso = NOW() WHERE id = ?";
            $stmt_up = $conn->prepare($update);
            $stmt_up->bind_param("i", $user['id']);
            $stmt_up->execute();
            $stmt_up->close();
            
            switch ($user['rol']) {
                case 'super_admin':
                    header('Location: dashboard.php'); break;
                case 'admin':
                    header('Location: admin_dashboard.php'); break;
                case 'veterinario':
                    header('Location: veterinario_dashboard.php'); break;
                case 'asistente':
                    header('Location: asistente_dashboard.php'); break;
                case 'recepcionista':
                    header('Location: recepcionista_dashboard.php'); break;
                case 'grooming':
                    header('Location: grooming_dashboard.php'); break;
                case 'caja':
                    header('Location: caja_dashboard.php'); break;
                default:
                    header('Location: dashboard.php');
            }
            $stmt->close();
            $conn->close();
            exit;
        } else {
            $error = "Contraseña incorrecta";
        }
    } else {
        $error = "Usuario no encontrado o inactivo";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>BIOSPET - Inicio de Sesión</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { background: var(--muted); display: flex; justify-content: center; align-items: center; height: 100vh; }
        .login-box { background: var(--white); padding: 40px; border-radius: var(--radius-md); box-shadow: var(--shadow-soft); width: 380px; }
        .login-box h2 { color: var(--primary); margin-bottom: 10px; text-align: center; }
        .login-box p.subtitle { text-align: center; color: #666; margin-bottom: 25px; font-size: 14px; }
        .form-group { margin-bottom: 15px; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: var(--radius-sm); font-size: 14px; }
        .btn { width: 100%; background: var(--primary); color: white; border: none; padding: 12px; cursor: pointer; border-radius: var(--radius-sm); font-size: 16px; font-weight: bold; }
        .btn:hover { background: var(--primary-dark); }
        .error { color: red; text-align: center; margin-bottom: 15px; padding: 8px; background: #ffe6e6; border-radius: var(--radius-sm); }
        .info { text-align: center; margin-top: 20px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>🐾 BIOSPET</h2>
        <p class="subtitle">Sistema de Gestión Veterinaria</p>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="POST">
            <div class="form-group">
                <input type="text" name="usuario" placeholder="Usuario" required autofocus>
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="Contraseña" required>
            </div>
            <button type="submit" class="btn">Ingresar</button>
            <div class="info" style="margin-top: 15px;">
                <a href="olvide_contrasena.php" style="color: var(--primary); text-decoration: none; font-size: 12px;">¿Olvidaste tu contraseña?</a>
            </div>
        </form>
    </div>
</body>
</html>