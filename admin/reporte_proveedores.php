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

// Obtener top proveedores por gasto
$top_gasto = $conn->query("SELECT * FROM vista_compras_proveedor ORDER BY total_gastado DESC LIMIT 10");

// Obtener top proveedores por cantidad de compras
$top_compras = $conn->query("SELECT * FROM vista_compras_proveedor ORDER BY total_compras DESC LIMIT 10");

// Obtener proveedores inactivos
$inactivos = $conn->query("SELECT * FROM PROVEEDOR WHERE activo = 0 ORDER BY nombre");

// Totales generales
$totales = $conn->query("SELECT 
                            COUNT(*) as total_proveedores,
                            SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activos,
                            SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as inactivos
                        FROM PROVEEDOR")->fetch_assoc();

$total_gastado = $conn->query("SELECT SUM(total_gastado) as total FROM vista_compras_proveedor")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Proveedores - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/reporte_proveedores.css">

</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Reporte de Proveedores</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="proveedores.php">🏭 Proveedores</a>
            <a href="compras.php">🛒 Compras</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn-volver">← Volver al Dashboard</a>
        
        <!-- Tarjetas de estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $totales['total_proveedores']; ?></div>
                <div class="stat-label">Total Proveedores</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $totales['activos']; ?></div>
                <div class="stat-label">Activos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $totales['inactivos']; ?></div>
                <div class="stat-label">Inactivos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($total_gastado ?? 0, 2); ?></div>
                <div class="stat-label">Total Gastado</div>
            </div>
        </div>
        
        <!-- Top 10 Proveedores por Gasto -->
        <div class="seccion">
            <h2>🏆 Top 10 Proveedores por Gasto</h2>
            <table class="reporte-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Proveedor</th>
                        <th class="text-right">Total Compras</th>
                        <th class="text-right">Total Gastado</th>
                        <th>Última Compra</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rank = 1;
                    while($prov = $top_gasto->fetch_assoc()): 
                        $rank_class = '';
                        if ($rank == 1) $rank_class = 'rank-1';
                        elseif ($rank == 2) $rank_class = 'rank-2';
                        elseif ($rank == 3) $rank_class = 'rank-3';
                    ?>
                    <tr class="<?php echo $rank_class; ?>">
                        <td><strong>#<?php echo $rank; ?></strong></td>
                        <td><strong><?php echo htmlspecialchars($prov['proveedor']); ?></strong></td>
                        <td class="text-right"><?php echo $prov['total_compras']; ?> compras</td>
                        <td class="text-right total-col">$<?php echo number_format($prov['total_gastado'], 2); ?></td>
                        <td><?php echo $prov['ultima_compra'] ? date('d/m/Y', strtotime($prov['ultima_compra'])) : '—'; ?></td>
                    </tr>
                    <?php 
                        $rank++;
                    endwhile; 
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- Top 10 Proveedores por Cantidad de Compras -->
        <div class="seccion">
            <h2>📦 Top 10 Proveedores por Cantidad de Compras</h2>
            <table class="reporte-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Proveedor</th>
                        <th class="text-right">Total Compras</th>
                        <th class="text-right">Productos Distintos</th>
                        <th class="text-right">Total Gastado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rank = 1;
                    while($prov = $top_compras->fetch_assoc()): 
                    ?>
                    <tr>
                        <td><strong>#<?php echo $rank; ?></strong></td>
                        <td><?php echo htmlspecialchars($prov['proveedor']); ?></td>
                        <td class="text-right"><strong><?php echo $prov['total_compras']; ?></strong> compras</td>
                        <td class="text-right"><?php echo $prov['productos_distintos']; ?> productos</td>
                        <td class="text-right">$<?php echo number_format($prov['total_gastado'], 2); ?></td>
                    </tr>
                    <?php 
                        $rank++;
                    endwhile; 
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- Proveedores Inactivos -->
        <?php if ($inactivos->num_rows > 0): ?>
        <div class="seccion">
            <h2>⚠️ Proveedores Inactivos</h2>
            <table class="reporte-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Proveedor</th>
                        <th>Contacto</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($prov = $inactivos->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $prov['id']; ?></td>
                        <td><?php echo htmlspecialchars($prov['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($prov['contacto_nombre'] ?? '—'); ?></td>
                        <td><?php echo $prov['telefono'] ?: '—'; ?></td>
                        <td><?php echo $prov['email'] ?: '—'; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>