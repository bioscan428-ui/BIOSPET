<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol
if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'recepcionista'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener todas las facturas
$sql = "SELECT f.*, 
               v.fecha_venta, 
               v.total as monto_venta,
               c.nombre as cliente_nombre,
               c.ape_pat,
               c.ape_mat
        FROM FACTURA f
        JOIN VENTA v ON f.id_venta = v.id
        JOIN CLIENTE c ON f.id_cliente = c.id
        ORDER BY f.fecha_creacion DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facturas - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { background: #f5f5f5; }
        .admin-header { background: #E68D0B; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .admin-header a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 1200px; margin: 30px auto; background: white; border-radius: 15px; padding: 25px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        h1 { color: var(--primary); margin-bottom: 20px; }
        .facturas-table { width: 100%; border-collapse: collapse; }
        .facturas-table th, .facturas-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .facturas-table th { background: #f8f9fa; font-weight: bold; color: #666; }
        .facturas-table tr:hover { background: #f8f9fa; }
        .btn-ver { background: #2196f3; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none; font-size: 12px; }
        .btn-ver:hover { background: #0b7dda; }
        .badge { background: #4caf50; color: white; padding: 3px 8px; border-radius: 12px; font-size: 11px; }
        .total-card {
            background: linear-gradient(135deg, #E68D0B, #f0a33a);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .total-card .numero { font-size: 2rem; font-weight: bold; }
        .total-card .label { font-size: 0.9rem; opacity: 0.9; }
        .acciones { display: flex; gap: 10px; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Facturas</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="punto_venta.php">🛒 Punto de Venta</a>
            <a href="reportes.php">📊 Reportes</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <div class="total-card">
            <div>
                <div class="label">Total de facturas emitidas</div>
                <div class="numero"><?php echo $result->num_rows; ?></div>
            </div>
            <div>📄</div>
        </div>

        <h1>📄 Listado de Facturas</h1>

        <div class="tabla-scroll-container" style="overflow-x: auto;">
            <table class="facturas-table">
                <thead>
                    <tr>
                        <th>ID Factura</th>
                        <th>Venta #</th>
                        <th>Fecha Venta</th>
                        <th>Cliente</th>
                        <th>RFC</th>
                        <th>Razón Social</th>
                        <th>Monto</th>
                        <th>Fecha Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($factura = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $factura['id']; ?></span>
                            <td><?php echo $factura['id_venta']; ?></span>
                            <td><?php echo date('d/m/Y', strtotime($factura['fecha_venta'])); ?></span>
                            <td>
                                <?php echo htmlspecialchars($factura['cliente_nombre'] . ' ' . $factura['ape_pat'] . ' ' . $factura['ape_mat']); ?>
                             </span>
                            <td><?php echo htmlspecialchars($factura['rfc']); ?></span>
                            <td><?php echo htmlspecialchars($factura['razon_social']); ?></span>
                            <td>$<?php echo number_format($factura['monto_venta'], 2); ?></span>
                            <td><?php echo date('d/m/Y H:i', strtotime($factura['fecha_creacion'])); ?></span>
                            <td class="acciones">
                                <a href="factura_ver.php?id=<?php echo $factura['id']; ?>" class="btn-ver">👁️ Ver Detalle</a>
                                <a href="factura_pdf.php?id=<?php echo $factura['id']; ?>" class="btn-ver" style="background: #f44336;">📄 PDF</a>
                            </span>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #999;">
                                No hay facturas registradas
                             </span>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>