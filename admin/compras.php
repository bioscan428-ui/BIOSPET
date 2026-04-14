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

// Obtener filtro de proveedor si existe
$filtro_proveedor = isset($_GET['proveedor']) ? (int)$_GET['proveedor'] : 0;

// Construir consulta de compras
$sql = "SELECT c.*, p.nombre as proveedor_nombre, e.nombre as empleado_nombre
        FROM COMPRA c
        JOIN PROVEEDOR p ON c.id_proveedor = p.id
        JOIN EMPLEADO e ON c.id_empleado = e.id";

if ($filtro_proveedor > 0) {
    $sql .= " WHERE c.id_proveedor = $filtro_proveedor";
}

$sql .= " ORDER BY c.fecha_compra DESC, c.id DESC";

$result = $conn->query($sql);

// Obtener lista de proveedores para el filtro
$proveedores = $conn->query("SELECT id, nombre FROM PROVEEDOR WHERE activo = 1 ORDER BY nombre");

// Obtener estadísticas
$total_compras = $conn->query("SELECT COUNT(*) as total FROM COMPRA")->fetch_assoc()['total'];
$total_gastado = $conn->query("SELECT SUM(total) as total FROM COMPRA")->fetch_assoc()['total'];
$ultima_compra = $conn->query("SELECT fecha_compra FROM COMPRA ORDER BY fecha_compra DESC LIMIT 1")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Compras - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/compras.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Historial de Compras</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="proveedores.php">🏭 Proveedores</a>
            <a href="compras.php">🛒 Compras</a>
            <a href="productos.php">🛍️ Productos</a>
            <?php if ($_SESSION['rol'] === 'super_admin'): ?>
            <a href="usuarios.php">👥 Usuarios</a>
            <?php endif; ?>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="alert-success"><?php echo $_SESSION['mensaje']; ?></div>
            <?php unset($_SESSION['mensaje']); ?>
        <?php endif; ?>

        <div class="header-actions">
            <a href="compra_nueva.php" class="btn-nuevo">+ Nueva Compra</a>
        </div>

        <!-- Filtros -->
        <div class="filtros">
            <label>🔍 Filtrar por proveedor:</label>
            <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <select name="proveedor">
                    <option value="0">Todos los proveedores</option>
                    <?php while($prov = $proveedores->fetch_assoc()): ?>
                        <option value="<?php echo $prov['id']; ?>" <?php echo ($filtro_proveedor == $prov['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($prov['nombre']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="btn-filtrar">Filtrar</button>
                <a href="compras.php" class="btn-limpiar">Limpiar</a>
            </form>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_compras; ?></div>
                <div class="stat-label">Total Compras</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($total_gastado ?? 0, 2); ?></div>
                <div class="stat-label">Total Gastado</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $ultima_compra ? date('d/m/Y', strtotime($ultima_compra['fecha_compra'])) : '—'; ?></div>
                <div class="stat-label">Última Compra</div>
            </div>
        </div>

        <!-- Tabla de Compras -->
        <table class="compras-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Proveedor</th>
                    <th>Factura</th>
                    <th>Total</th>
                    <th>Registrado por</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while($compra = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $compra['id']; ?></td>
                        <td><?php echo date('d/m/Y', strtotime($compra['fecha_compra'])); ?></td>
                        <td><strong><?php echo htmlspecialchars($compra['proveedor_nombre']); ?></strong></td>
                        <td><?php echo $compra['folio_factura'] ?: '—'; ?></td>
                        <td class="total-col">$<?php echo number_format($compra['total'], 2); ?></td>
                        <td><?php echo htmlspecialchars($compra['empleado_nombre']); ?></td>
                        <td class="acciones">
                            <a href="compra_detalle.php?id=<?php echo $compra['id']; ?>" class="btn-ver">👁️ Ver Detalle</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                            No hay compras registradas. 
                            <a href="compra_nueva.php" style="color: var(--primary);">Registrar la primera compra</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>