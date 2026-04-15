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

$id_compra = (int)($_GET['id'] ?? 0);
if (!$id_compra) {
    header('Location: compras.php');
    exit;
}

// Obtener datos de la compra
$sql = "SELECT c.*, p.nombre as proveedor_nombre, p.telefono as proveedor_telefono, 
               p.email as proveedor_email, e.nombre as empleado_nombre
        FROM COMPRA c
        JOIN PROVEEDOR p ON c.id_proveedor = p.id
        JOIN EMPLEADO e ON c.id_empleado = e.id
        WHERE c.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_compra);
$stmt->execute();
$compra = $stmt->get_result()->fetch_assoc();

if (!$compra) {
    header('Location: compras.php');
    exit;
}

// Obtener detalles de la compra
$sql_detalle = "SELECT dc.*, pr.nombre as producto_nombre, pr.unidad_medida
                FROM DETALLE_COMPRA dc
                JOIN PRODUCTO pr ON dc.id_producto = pr.id
                WHERE dc.id_compra = ?
                ORDER BY dc.id";
$stmt_detalle = $conn->prepare($sql_detalle);
$stmt_detalle->bind_param("i", $id_compra);
$stmt_detalle->execute();
$detalles = $stmt_detalle->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Compra #<?php echo $id_compra; ?> - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/compra_detalle.css">
    
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Detalle de Compra</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="proveedores.php">🏭 Proveedores</a>
            <a href="compras.php">🛒 Compras</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <a href="compras.php" class="btn-volver">← Volver a Compras</a>
            <button onclick="window.print()" class="btn-volver" style="background: #2196f3;">🖨️ Imprimir</button>
        </div>
        
        <!-- Información de la Compra -->
        <div class="compra-card">
            <div class="compra-header">
                <h1>🧾 Compra #<?php echo $id_compra; ?></h1>
                <div class="badge-proveedor">
                    📅 <?php echo date('d/m/Y', strtotime($compra['fecha_compra'])); ?>
                </div>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Proveedor:</span>
                    <span class="info-value">
                        <strong><?php echo htmlspecialchars($compra['proveedor_nombre']); ?></strong>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Teléfono:</span>
                    <span class="info-value"><?php echo $compra['proveedor_telefono'] ?: 'No registrado'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo $compra['proveedor_email'] ?: 'No registrado'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Folio factura:</span>
                    <span class="info-value"><?php echo $compra['folio_factura'] ?: 'No especificado'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Registrado por:</span>
                    <span class="info-value"><?php echo htmlspecialchars($compra['empleado_nombre']); ?></span>
                </div>
                <?php if (!empty($compra['notas'])): ?>
                <div class="info-item">
                    <span class="info-label">Notas:</span>
                    <span class="info-value"><?php echo nl2br(htmlspecialchars($compra['notas'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Detalle de Productos -->
        <div class="productos-section">
            <h2>📦 Productos de la Compra</h2>
            
            <table class="detalles-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Unidad</th>
                        <th class="text-right">Cantidad</th>
                        <th class="text-right">Precio Unitario</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_compra = 0;
                    while($detalle = $detalles->fetch_assoc()): 
                        $subtotal = $detalle['cantidad'] * $detalle['precio_unitario'];
                        $total_compra += $subtotal;
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($detalle['producto_nombre']); ?></strong></td>
                        <td><?php echo $detalle['unidad_medida'] ?: 'pieza'; ?></td>
                        <td class="text-right"><?php echo number_format($detalle['cantidad']); ?></td>
                        <td class="text-right">$<?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                        <td class="text-right">$<?php echo number_format($subtotal, 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <!-- Fila de total -->
                    <tr class="total-row">
                        <td colspan="4" class="text-right"><strong>TOTAL:</strong></td>
                        <td class="text-right total-amount">
                            <strong>$<?php echo number_format($total_compra, 2); ?></strong>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>