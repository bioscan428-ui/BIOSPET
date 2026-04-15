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

$id_proveedor = (int)($_GET['id'] ?? 0);
if (!$id_proveedor) {
    header('Location: proveedores.php');
    exit;
}

// Obtener datos del proveedor
$sql = "SELECT * FROM PROVEEDOR WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_proveedor);
$stmt->execute();
$proveedor = $stmt->get_result()->fetch_assoc();

if (!$proveedor) {
    header('Location: proveedores.php');
    exit;
}

// Obtener estadísticas de compras del proveedor
$sql_stats = "SELECT 
                COUNT(*) as total_compras,
                SUM(total) as total_gastado,
                MIN(fecha_compra) as primera_compra,
                MAX(fecha_compra) as ultima_compra
              FROM COMPRA 
              WHERE id_proveedor = ?";
$stmt_stats = $conn->prepare($sql_stats);
$stmt_stats->bind_param("i", $id_proveedor);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();

// Obtener últimas compras del proveedor
$sql_compras = "SELECT c.*, e.nombre as empleado_nombre
                FROM COMPRA c
                JOIN EMPLEADO e ON c.id_empleado = e.id
                WHERE c.id_proveedor = ?
                ORDER BY c.fecha_compra DESC, c.id DESC
                LIMIT 10";
$stmt_compras = $conn->prepare($sql_compras);
$stmt_compras->bind_param("i", $id_proveedor);
$stmt_compras->execute();
$compras = $stmt_compras->get_result();

// Obtener productos más comprados a este proveedor
$sql_productos = "SELECT 
                    p.id,
                    p.nombre,
                    SUM(dc.cantidad) as total_cantidad,
                    SUM(dc.subtotal) as total_gastado,
                    COUNT(DISTINCT c.id) as veces_comprado
                FROM DETALLE_COMPRA dc
                JOIN COMPRA c ON dc.id_compra = c.id
                JOIN PRODUCTO p ON dc.id_producto = p.id
                WHERE c.id_proveedor = ?
                GROUP BY p.id
                ORDER BY total_gastado DESC
                LIMIT 10";
$stmt_productos = $conn->prepare($sql_productos);
$stmt_productos->bind_param("i", $id_proveedor);
$stmt_productos->execute();
$productos = $stmt_productos->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Proveedor - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/proveedor_detalle.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Detalle de Proveedor</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="proveedores.php">🏭 Proveedores</a>
            <a href="compras.php">🛒 Compras</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <a href="proveedores.php" class="btn-volver">← Volver a Proveedores</a>
        
        <!-- Información del Proveedor -->
        <div class="proveedor-card">
            <div class="proveedor-header">
                <h1>🏭 <?php echo htmlspecialchars($proveedor['nombre']); ?></h1>
                <div class="estado-badge">
                    <?php echo $proveedor['activo'] ? '✅ Activo' : '❌ Inactivo'; ?>
                </div>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Contacto:</span>
                    <span class="info-value"><?php echo htmlspecialchars($proveedor['contacto_nombre'] ?? 'No especificado'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Teléfono:</span>
                    <span class="info-value"><?php echo $proveedor['telefono'] ?: 'No registrado'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo $proveedor['email'] ?: 'No registrado'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Dirección:</span>
                    <span class="info-value"><?php echo nl2br(htmlspecialchars($proveedor['direccion'] ?? 'No registrada')); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_compras'] ?? 0; ?></div>
                <div class="stat-label">Total Compras</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($stats['total_gastado'] ?? 0, 2); ?></div>
                <div class="stat-label">Total Gastado</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['primera_compra'] ? date('d/m/Y', strtotime($stats['primera_compra'])) : '—'; ?></div>
                <div class="stat-label">Primera Compra</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['ultima_compra'] ? date('d/m/Y', strtotime($stats['ultima_compra'])) : '—'; ?></div>
                <div class="stat-label">Última Compra</div>
            </div>
        </div>
        
        <!-- Últimas Compras -->
        <div class="seccion">
            <h2>📋 Últimas Compras</h2>
            <?php if ($compras->num_rows > 0): ?>
                <table class="compras-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Factura</th>
                            <th>Total</th>
                            <th>Registrado por</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($compra = $compras->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $compra['id']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($compra['fecha_compra'])); ?></td>
                            <td><?php echo $compra['folio_factura'] ?: '—'; ?></td>
                            <td class="total-col">$<?php echo number_format($compra['total'], 2); ?></td>
                            <td><?php echo htmlspecialchars($compra['empleado_nombre']); ?></td>
                            <td><a href="compra_detalle.php?id=<?php echo $compra['id']; ?>" class="btn-small">Ver Detalle</a></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #999;">No hay compras registradas de este proveedor.</p>
            <?php endif; ?>
        </div>
        
        <!-- Productos más comprados -->
        <div class="seccion">
            <h2>🏆 Productos más comprados</h2>
            <?php if ($productos->num_rows > 0): ?>
                <table class="productos-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Veces comprado</th>
                            <th>Cantidad total</th>
                            <th>Total gastado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($producto = $productos->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($producto['nombre']); ?></strong></td>
                            <td><span class="badge-producto">📦 <?php echo $producto['veces_comprado']; ?> veces</span></td>
                            <td><?php echo $producto['total_cantidad']; ?> unidades</span></td>
                            <td class="total-col">$<?php echo number_format($producto['total_gastado'], 2); ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #999;">No hay productos comprados a este proveedor.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>