<?php
session_start();

// Verificar que el usuario haya iniciado sesión (usando user_id, no admin_logged)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Opcional: verificar rol para acceso
if (!in_array($_SESSION['rol'], ['super_admin', 'admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener todos los productos
$sql = "SELECT p.*, c.nombre AS categoria_nombre 
        FROM PRODUCTO p
        JOIN CATEGORIA_PRODUCTO c ON p.id_categoria = c.id
        ORDER BY p.id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { background: var(--muted); }
        .admin-header { background: var(--primary); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        .admin-header a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        .btn-nuevo { background: #4caf50; color: white; padding: 10px 20px; border-radius: var(--radius-sm); text-decoration: none; display: inline-block; margin-bottom: 20px; }
        .btn-editar { background: #2196f3; color: white; padding: 5px 10px; border-radius: var(--radius-sm); text-decoration: none; font-size: 12px; }
        .btn-eliminar { background: #f44336; color: white; padding: 5px 10px; border-radius: var(--radius-sm); text-decoration: none; font-size: 12px; }
        .productos-table { width: 100%; background: white; border-radius: var(--radius-md); overflow: hidden; box-shadow: var(--shadow-soft); }
        .productos-table th, .productos-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .productos-table th { background: var(--black); color: white; }
        .stock-bajo { color: #f44336; font-weight: bold; }
        .imagen-preview { width: 50px; height: 50px; object-fit: cover; border-radius: var(--radius-sm); }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Gestión de Productos</h1>
        <div>
            <a href="dashboard.php">📋 Citas</a>
            <a href="calendario.php">📅 Calendario</a>
            <a href="reportes.php">📊 Reportes</a>
            <a href="productos.php">🛒 Productos</a>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <a href="producto_nuevo.php" class="btn-nuevo">+ Nuevo Producto</a>
        
        <table class="productos-table">
            <thead>
                 <tr>
                    <th>ID</th>
                    <th>Imagen</th>
                    <th>Nombre</th>
                    <th>Categoría</th>
                    <th>Precio Venta</th>
                    <th>Stock</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                 </tr>
            </thead>
            <tbody>
                <?php while($producto = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $producto['id']; ?></td>
                    <td>
                        <?php if(!empty($producto['imagen'])): ?>
                            <img src="../<?php echo $producto['imagen']; ?>" class="imagen-preview">
                        <?php else: ?>
                            <span style="color:#999;">Sin imagen</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                    <td><?php echo $producto['categoria_nombre']; ?></td>
                    <td>$<?php echo number_format($producto['precio_venta'], 2); ?></td>
                    <td class="<?php echo $producto['stock_actual'] <= $producto['stock_minimo'] ? 'stock-bajo' : ''; ?>">
                        <?php echo $producto['stock_actual']; ?>
                        <?php if($producto['stock_actual'] <= $producto['stock_minimo']): ?>
                            (⚠️ bajo)
                        <?php endif; ?>
                    </td>
                    <td><?php echo $producto['activo'] ? '✅ Activo' : '❌ Inactivo'; ?></td>
                    <td>
                        <a href="producto_editar.php?id=<?php echo $producto['id']; ?>" class="btn-editar">Editar</a>
                        <a href="producto_eliminar.php?id=<?php echo $producto['id']; ?>" class="btn-eliminar" onclick="return confirm('¿Eliminar este producto?')">Eliminar</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>