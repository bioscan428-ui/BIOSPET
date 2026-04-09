<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!in_array($_SESSION['rol'], ['super_admin', 'admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$tab = $_GET['tab'] ?? 'todos';

if ($tab === 'criticos') {
    $sql = "SELECT * FROM vista_stock_critico";
    $result = $conn->query($sql);
    $criticos_count = $conn->query("SELECT COUNT(*) as total FROM vista_stock_critico")->fetch_assoc()['total'];
} else {
    $sql = "SELECT p.*, c.nombre AS categoria_nombre 
            FROM PRODUCTO p
            JOIN CATEGORIA_PRODUCTO c ON p.id_categoria = c.id
            ORDER BY p.stock_actual ASC, p.id DESC";
    $result = $conn->query($sql);
    $criticos_count = $conn->query("SELECT COUNT(*) as total FROM vista_stock_critico")->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/productos.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Gestión de Productos</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="calendario.php">📅 Calendario</a>
            <a href="productos.php">🛒 Productos</a>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <div class="header-actions">
            <div class="tab-buttons">
                <a href="?tab=todos" class="tab-btn <?php echo $tab === 'todos' ? 'active' : ''; ?>">
                    📦 Todos los productos
                </a>
                <a href="?tab=criticos" class="tab-btn <?php echo $tab === 'criticos' ? 'active' : ''; ?>">
                    ⚠️ Stock crítico
                    <?php if ($criticos_count > 0): ?>
                        <span class="badge-count"><?php echo $criticos_count; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <a href="producto_nuevo.php" class="btn-nuevo">+ Nuevo Producto</a>
        </div>

        <?php if ($tab === 'criticos' && $result->num_rows === 0): ?>
            <div class="alert-success">
                ¡No hay productos con stock crítico o por vencer!
            </div>
        <?php endif; ?>

        <table class="productos-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Imagen</th>
                    <th>Nombre</th>
                    <th>Categoría</th>
                    <th>Precio</th>
                    <th>Stock</th>
                    <?php if ($tab === 'criticos'): ?>
                        <th>Stock Mínimo</th>
                        <th>Vencimiento</th>
                    <?php endif; ?>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while($producto = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $producto['id']; ?></td>
                    <td>
                        <?php if(!empty($producto['imagen']) && file_exists('../' . $producto['imagen'])): ?>
                            <img src="../<?php echo $producto['imagen']; ?>" class="imagen-preview" alt="<?php echo htmlspecialchars($producto['nombre']); ?>">
                        <?php else: ?>
                            <span class="sin-imagen">📦</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo htmlspecialchars($producto['nombre']); ?></strong></td>
                    <td><?php echo $producto['categoria_nombre'] ?? $producto['categoria']; ?></td>
                    <td>$<?php echo number_format($producto['precio_venta'], 2); ?></td>
                    <td>
                        <?php 
                        $stock_minimo = $producto['stock_minimo'] ?? 5;
                        if ($producto['stock_actual'] <= 0): ?>
                            <span class="stock-critico">AGOTADO</span>
                        <?php elseif ($producto['stock_actual'] <= $stock_minimo): ?>
                            <span class="stock-bajo"><?php echo $producto['stock_actual']; ?> unidades</span>
                        <?php else: ?>
                            <span class="stock-normal"><?php echo $producto['stock_actual']; ?> unidades</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($tab === 'criticos'): ?>
                        <td><?php echo $producto['stock_minimo']; ?> unidades</td>
                        <td class="<?php echo ($producto['dias_vencimiento'] ?? 999) <= 30 ? 'vencimiento-critico' : 'vencimiento-normal'; ?>">
                            <?php 
                            if ($producto['fecha_vencimiento']) {
                                echo date('d/m/Y', strtotime($producto['fecha_vencimiento']));
                                if (isset($producto['dias_vencimiento']) && $producto['dias_vencimiento'] <= 30) {
                                    echo " <small>({$producto['dias_vencimiento']} días)</small>";
                                }
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                    <?php endif; ?>
                    <td>
                        <?php if ($producto['activo'] ?? 1): ?>
                            <span class="estado-activo">✅ Activo</span>
                        <?php else: ?>
                            <span class="estado-inactivo">❌ Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="producto_editar.php?id=<?php echo $producto['id']; ?>" class="btn-editar">✏️ Editar</a>
                        <a href="producto_eliminar.php?id=<?php echo $producto['id']; ?>" class="btn-eliminar" onclick="return confirm('¿Eliminar este producto?')">🗑️ Eliminar</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>