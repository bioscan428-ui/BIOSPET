<?php
session_start();

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol para acceso (admin puede ver clientes)
if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'veterinario', 'asistente', 'recepcionista'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Procesar acciones (activar/desactivar cliente)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $id_cliente = (int)$_POST['id_cliente'];
        
        switch ($_POST['action']) {
            case 'activar':
                $conn->query("UPDATE CLIENTE SET activo = 1 WHERE id = $id_cliente");
                break;
            case 'desactivar':
                $conn->query("UPDATE CLIENTE SET activo = 0 WHERE id = $id_cliente");
                break;
        }
        header('Location: clientes.php');
        exit;
    }
}

// Obtener lista de clientes con sus puntos y nivel usando la vista de fidelidad
$sql = "SELECT * FROM vista_cliente_fidelidad ORDER BY puntos_actuales DESC, nombre ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/clientes.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Gestión de Clientes</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="clientes.php">👥 Clientes</a>
            <a href="reportes.php">📊 Reportes</a>
            <a href="productos.php">🛒 Productos</a>
            <?php if ($_SESSION['rol'] === 'super_admin'): ?>
            <a href="usuarios.php">👥 Usuarios</a>
            <?php endif; ?>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <div class="header-actions">
            <a href="cliente_nuevo.php" class="btn-nuevo">+ Nuevo Cliente</a>
            <div class="stats-badge">
                📊 Total clientes: <?php echo $result->num_rows; ?>
            </div>
        </div>

        <table class="clientes-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>🎯 Nivel</th>
                    <th>⭐ Puntos</th>
                    <th>💰 Total Gastado</th>
                    <th>Mascotas</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while($cliente = $result->fetch_assoc()): ?>
                <tr class="nivel-<?php echo $cliente['nivel']; ?>">
                    <td><?php echo $cliente['cliente_id']; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['ape_pat']); ?></strong>
                     </td>
                    <td><?php echo $cliente['telefono'] ?: '—'; ?></td>
                    <td><?php echo $cliente['email'] ?: '—'; ?></td>
                    <td class="nivel-badge">
                        <?php
                        $nivel_icono = [
                            'bronce' => '🥉',
                            'plata' => '🥈',
                            'oro' => '🥇',
                            'platino' => '💎'
                        ];
                        echo $nivel_icono[$cliente['nivel']] . ' ' . ucfirst($cliente['nivel']);
                        ?>
                    </td>
                    <td class="puntos">
                        <span class="puntos-number"><?php echo number_format($cliente['puntos_actuales']); ?></span>
                        <span class="puntos-label">pts</span>
                    </td>
                    <td class="gastado">
                        $<?php echo number_format($cliente['total_gastado'] ?? 0, 2); ?>
                    </td>
                    <td>
                        <?php 
                        $sql_mascotas = "SELECT COUNT(*) as total FROM MASCOTA WHERE id_cliente = " . $cliente['cliente_id'] . " AND activo = 1";
                        $total_mascotas = $conn->query($sql_mascotas)->fetch_assoc()['total'];
                        echo $total_mascotas;
                        ?>
                     </td>
                    <td class="estado <?php echo ($cliente['activo'] ?? 1) ? 'activo' : 'inactivo'; ?>">
                        <?php echo ($cliente['activo'] ?? 1) ? '✅ Activo' : '❌ Inactivo'; ?>
                    </td>
                    <td class="acciones">
                        <a href="cliente_detalle.php?id=<?php echo $cliente['cliente_id']; ?>" class="btn-ver">👁️ Ver</a>
                        <a href="cliente_editar.php?id=<?php echo $cliente['cliente_id']; ?>" class="btn-editar">✏️ Editar</a>
                        <?php if (($cliente['activo'] ?? 1)): ?>
                            <form method="POST" style="display: inline-block;">
                                <input type="hidden" name="id_cliente" value="<?php echo $cliente['cliente_id']; ?>">
                                <button type="submit" name="action" value="desactivar" class="btn-desactivar" onclick="return confirm('¿Desactivar este cliente?')">🔴 Desactivar</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display: inline-block;">
                                <input type="hidden" name="id_cliente" value="<?php echo $cliente['cliente_id']; ?>">
                                <button type="submit" name="action" value="activar" class="btn-activar" onclick="return confirm('¿Activar este cliente?')">🟢 Activar</button>
                            </form>
                        <?php endif; ?>
                        <a href="cliente_puntos.php?id=<?php echo $cliente['cliente_id']; ?>" class="btn-puntos">⭐ Puntos</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>