<?php
if ($_SESSION['rol'] === 'caja') {
    header('Location: caja_dashboard.php');
    exit;
}
session_start();
$dashboard_link = ($_SESSION['rol'] === 'caja') ? 'caja_dashboard.php' : 'dashboard.php';

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol para acceso (solo admin y super_admin pueden gestionar proveedores)
if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'caja'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Procesar acciones (activar/desactivar proveedor)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $id_proveedor = (int)$_POST['id_proveedor'];
        
        switch ($_POST['action']) {
            case 'activar':
                $conn->query("UPDATE PROVEEDOR SET activo = 1 WHERE id = $id_proveedor");
                $_SESSION['mensaje'] = "Proveedor activado correctamente";
                break;
            case 'desactivar':
                $conn->query("UPDATE PROVEEDOR SET activo = 0 WHERE id = $id_proveedor");
                $_SESSION['mensaje'] = "Proveedor desactivado correctamente";
                break;
        }
        header('Location: proveedores.php');
        exit;
    }
}

// Obtener lista de proveedores (directamente desde la tabla PROVEEDOR)
$sql = "SELECT * FROM PROVEEDOR ORDER BY nombre ASC";
$result = $conn->query($sql);

// Obtener estadísticas de compras por proveedor (usando la vista)
$stats = [];
$stats_result = $conn->query("SELECT * FROM vista_compras_proveedor");
while($row = $stats_result->fetch_assoc()) {
    $stats[$row['proveedor_id']] = $row;
}

// Contar total de proveedores activos e inactivos
$total_activos = $conn->query("SELECT COUNT(*) as total FROM PROVEEDOR WHERE activo = 1")->fetch_assoc()['total'];
$total_inactivos = $conn->query("SELECT COUNT(*) as total FROM PROVEEDOR WHERE activo = 0")->fetch_assoc()['total'];
$total_proveedores = $total_activos + $total_inactivos;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Proveedores - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/proveedores.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Gestión de Proveedores</h1>
        <div>
            <a href="<?php echo $dashboard_link; ?>">📋 Dashboard</a>
            <a href="clientes.php">👥 Clientes</a>
            <a href="proveedores.php">🏭 Proveedores</a>
            <a href="productos.php">🛒 Productos</a>
            <?php if ($_SESSION['rol'] === 'super_admin'): ?>
            <a href="usuarios.php">👥 Usuarios</a>
            <?php endif; ?>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <!-- Mostrar mensajes -->
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="alert-success"><?php echo $_SESSION['mensaje']; ?></div>
            <?php unset($_SESSION['mensaje']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert-error"><?php echo $_SESSION['error']; ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="header-actions">
            <a href="proveedor_nuevo.php" class="btn-nuevo">+ Nuevo Proveedor</a>
            <div class="stats-badge">
                📊 Total: <span><?php echo $total_proveedores; ?></span> | 
                Activos: <span><?php echo $total_activos; ?></span> | 
                Inactivos: <span><?php echo $total_inactivos; ?></span>
            </div>
        </div>

        <table class="proveedores-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Contacto</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Dirección</th>
                    <th>Compras</th>
                    <th>Total Gastado</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while($proveedor = $result->fetch_assoc()): 
                        $stat = $stats[$proveedor['id']] ?? null;
                    ?>
                    <tr>
                        <td><?php echo $proveedor['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($proveedor['nombre']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($proveedor['contacto_nombre'] ?? '—'); ?></td>
                        <td><?php echo $proveedor['telefono'] ?: '—'; ?></td>
                        <td><?php echo $proveedor['email'] ?: '—'; ?></td>
                        <td><?php echo htmlspecialchars(substr($proveedor['direccion'] ?? '', 0, 50)) . (strlen($proveedor['direccion'] ?? '') > 50 ? '...' : ''); ?></td>
                        <td>
                            <span class="compras-badge">📦 <?php echo $stat['total_compras'] ?? 0; ?></span>
                        </td>
                        <td class="total-col">
                            $<?php echo number_format($stat['total_gastado'] ?? 0, 2); ?>
                        </td>
                        <td class="estado <?php echo $proveedor['activo'] ? 'activo' : 'inactivo'; ?>">
                            <?php echo $proveedor['activo'] ? '✅ Activo' : '❌ Inactivo'; ?>
                        </td>
                        <td class="acciones">
                            <a href="proveedor_detalle.php?id=<?php echo $proveedor['id']; ?>" class="btn-ver">👁️ Ver</a>
                            <a href="proveedor_editar.php?id=<?php echo $proveedor['id']; ?>" class="btn-editar">✏️ Editar</a>
                            <a href="compras.php?proveedor=<?php echo $proveedor['id']; ?>" class="btn-compras">🛒 Compras</a>
                            <?php if ($proveedor['activo']): ?>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="id_proveedor" value="<?php echo $proveedor['id']; ?>">
                                    <button type="submit" name="action" value="desactivar" class="btn-desactivar" onclick="return confirm('¿Desactivar este proveedor?')">🔴 Desactivar</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="id_proveedor" value="<?php echo $proveedor['id']; ?>">
                                    <button type="submit" name="action" value="activar" class="btn-activar" onclick="return confirm('¿Activar este proveedor?')">🟢 Activar</button>
                                </form>
                            <?php endif; ?>
                            <a href="proveedor_eliminar.php?id=<?php echo $proveedor['id']; ?>" class="btn-eliminar" onclick="return confirm('¿Estás seguro de que deseas eliminar este proveedor? Esta acción no se puede deshacer.')">🗑️ Eliminar</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 40px; color: #999;">
                            No hay proveedores registrados. 
                            <a href="proveedor_nuevo.php" style="color: var(--primary);">Crear el primero</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>