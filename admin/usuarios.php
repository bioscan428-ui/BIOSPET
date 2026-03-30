<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar que sea super_admin
if ($_SESSION['rol'] !== 'super_admin') {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener todos los usuarios
$sql = "SELECT u.*, e.nombre, e.ape_pat, e.puesto, e.email, e.telefono 
        FROM USUARIO u
        JOIN EMPLEADO e ON u.id_empleado = e.id
        ORDER BY u.rol, e.nombre";
$result = $conn->query($sql);

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'activar':
                $id = (int)$_POST['id'];
                $conn->query("UPDATE USUARIO SET activo = 1 WHERE id = $id");
                break;
            case 'desactivar':
                $id = (int)$_POST['id'];
                $conn->query("UPDATE USUARIO SET activo = 0 WHERE id = $id");
                break;
            case 'cambiar_rol':
                $id = (int)$_POST['id'];
                $rol = $_POST['rol'];
                $conn->query("UPDATE USUARIO SET rol = '$rol' WHERE id = $id");
                break;
        }
        header('Location: usuarios.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { background: var(--muted); }
        .admin-header { background: var(--primary); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        .admin-header a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .btn-nuevo { background: #4caf50; color: white; padding: 10px 20px; border-radius: var(--radius-sm); text-decoration: none; display: inline-block; margin-bottom: 20px; }
        .usuarios-table { width: 100%; background: white; border-radius: var(--radius-md); overflow: hidden; box-shadow: var(--shadow-soft); }
        .usuarios-table th, .usuarios-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .usuarios-table th { background: var(--black); color: white; }
        .rol-super_admin { background: #ff9800; color: white; padding: 4px 8px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .rol-admin { background: #2196f3; color: white; padding: 4px 8px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .rol-veterinario { background: #4caf50; color: white; padding: 4px 8px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .rol-asistente { background: #9c27b0; color: white; padding: 4px 8px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .rol-recepcionista { background: #00bcd4; color: white; padding: 4px 8px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .activo { color: #4caf50; font-weight: bold; }
        .inactivo { color: #f44336; font-weight: bold; }
        .select-rol { padding: 5px; border-radius: var(--radius-sm); }
        .btn-accion { background: none; border: none; cursor: pointer; padding: 5px 10px; border-radius: var(--radius-sm); }
        .btn-activar { background: #4caf50; color: white; }
        .btn-desactivar { background: #f44336; color: white; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Gestión de Usuarios</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="usuarios.php">👥 Usuarios</a>
            <a href="productos.php">🛒 Productos</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <a href="usuario_nuevo.php" class="btn-nuevo">+ Nuevo Usuario</a>
        
        <table class="usuarios-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Email</th>
                    <th>Puesto</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Último Acceso</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while($user = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['nombre'] . ' ' . $user['ape_pat']); ?></td>
                    <td><?php echo htmlspecialchars($user['nombre_usuario']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo $user['puesto']; ?></td>
                    <td>
                        <span class="rol-<?php echo $user['rol']; ?>">
                            <?php 
                            $roles = [
                                'super_admin' => 'Super Admin',
                                'admin' => 'Admin',
                                'veterinario' => 'Veterinario',
                                'asistente' => 'Asistente',
                                'recepcionista' => 'Recepcionista'
                            ];
                            echo $roles[$user['rol']] ?? $user['rol'];
                            ?>
                        </span>
                    </td>
                    <td class="<?php echo $user['activo'] ? 'activo' : 'inactivo'; ?>">
                        <?php echo $user['activo'] ? '✅ Activo' : '❌ Inactivo'; ?>
                    </td>
                    <td><?php echo $user['ultimo_acceso'] ?: 'Nunca'; ?></td>
                    <td>
                        <form method="POST" style="display: inline-block;">
                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                            <?php if ($user['rol'] !== 'super_admin'): ?>
                                <select name="rol" class="select-rol" onchange="this.form.submit()">
                                    <option value="">Cambiar rol</option>
                                    <option value="admin">Admin</option>
                                    <option value="veterinario">Veterinario</option>
                                    <option value="asistente">Asistente</option>
                                    <option value="recepcionista">Recepcionista</option>
                                </select>
                                <input type="hidden" name="action" value="cambiar_rol">
                            <?php endif; ?>
                        </form>
                        
                        <form method="POST" style="display: inline-block;">
                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                            <?php if ($user['activo']): ?>
                                <button type="submit" name="action" value="desactivar" class="btn-accion btn-desactivar" onclick="return confirm('¿Desactivar este usuario?')">Desactivar</button>
                            <?php else: ?>
                                <button type="submit" name="action" value="activar" class="btn-accion btn-activar" onclick="return confirm('¿Activar este usuario?')">Activar</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>