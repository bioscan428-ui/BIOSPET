<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_factura = (int)($_GET['id'] ?? 0);
if (!$id_factura) {
    die("Factura no encontrada");
}

$sql = "SELECT f.*, 
               v.fecha_venta, 
               v.total as monto_venta,
               v.subtotal,
               v.iva,
               v.metodo_pago,
               c.nombre as cliente_nombre,
               c.ape_pat,
               c.ape_mat,
               c.telefono,
               c.email
        FROM FACTURA f
        JOIN VENTA v ON f.id_venta = v.id
        JOIN CLIENTE c ON f.id_cliente = c.id
        WHERE f.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_factura);
$stmt->execute();
$factura = $stmt->get_result()->fetch_assoc();

if (!$factura) {
    die("Factura no encontrada");
}

// Obtener productos de la venta
$sql_productos = "SELECT dv.*, p.nombre 
                  FROM DETALLE_VENTA dv
                  JOIN PRODUCTO p ON dv.id_producto = p.id
                  WHERE dv.id_venta = ?";
$stmt_prod = $conn->prepare($sql_productos);
$stmt_prod->bind_param("i", $factura['id_venta']);
$stmt_prod->execute();
$productos = $stmt_prod->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura #<?php echo $id_factura; ?> - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { background: #f5f5f5; }
        .container { max-width: 800px; margin: 30px auto; background: white; border-radius: 15px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .factura-header { text-align: center; margin-bottom: 30px; }
        .factura-header img { max-width: 150px; margin-bottom: 15px; }
        .factura-header h1 { color: var(--primary); margin: 0; }
        .info-section { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 10px; }
        .info-row { display: flex; justify-content: space-between; margin: 8px 0; }
        .info-label { font-weight: bold; color: #666; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; }
        .total-row { font-weight: bold; font-size: 1.1rem; }
        .btn-back { background: #666; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-block; }
        .btn-pdf { background: #f44336; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-block; margin-left: 10px; }
        .acciones { margin-top: 30px; display: flex; justify-content: center; gap: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="factura-header">
            <img src="../assets/images/logo_biospet_inverso.png" alt="BIOSPET">
            <h1>Factura de Venta</h1>
            <p><strong>Folio Fiscal:</strong> <?php echo $factura['uuid'] ?? 'No disponible'; ?></p>
        </div>

        <div class="info-section">
            <h3>📄 Información de la Factura</h3>
            <div class="info-row">
                <span class="info-label">Factura #:</span>
                <span><?php echo $id_factura; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Fecha de emisión:</span>
                <span><?php echo date('d/m/Y H:i', strtotime($factura['fecha_creacion'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">RFC:</span>
                <span><?php echo htmlspecialchars($factura['rfc']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Razón Social:</span>
                <span><?php echo htmlspecialchars($factura['razon_social']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Régimen Fiscal:</span>
                <span><?php echo htmlspecialchars($factura['regimen_fiscal'] ?? 'No especificado'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Uso de CFDI:</span>
                <span><?php echo htmlspecialchars($factura['uso_cfdi'] ?? 'No especificado'); ?></span>
            </div>
        </div>

        <div class="info-section">
            <h3>👤 Datos del Cliente</h3>
            <div class="info-row">
                <span class="info-label">Cliente:</span>
                <span><?php echo htmlspecialchars($factura['cliente_nombre'] . ' ' . $factura['ape_pat'] . ' ' . $factura['ape_mat']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Teléfono:</span>
                <span><?php echo htmlspecialchars($factura['telefono'] ?? 'No registrado'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span><?php echo htmlspecialchars($factura['email'] ?? 'No registrado'); ?></span>
            </div>
        </div>

        <div class="info-section">
            <h3>🛒 Detalle de la Venta</h3>
            <div class="info-row">
                <span class="info-label">Venta #:</span>
                <span><?php echo $factura['id_venta']; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Fecha de venta:</span>
                <span><?php echo date('d/m/Y', strtotime($factura['fecha_venta'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Método de pago:</span>
                <span><?php echo ucfirst($factura['metodo_pago']); ?></span>
            </div>
        </div>

        <h3>📦 Productos</h3>
        <table>
            <thead>
                <tr><th>Producto</th><th>Cantidad</th><th>Precio Unitario</th><th>Subtotal</th></tr>
            </thead>
            <tbody>
                <?php while($prod = $productos->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($prod['nombre']); ?></td>
                    <td><?php echo $prod['cantidad']; ?></td>
                    <td>$<?php echo number_format($prod['precio_unitario'], 2); ?></td>
                    <td>$<?php echo number_format($prod['subtotal'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="3" style="text-align: right;"><strong>Subtotal:</strong></td><td>$<?php echo number_format($factura['subtotal'], 2); ?></td></tr>
                <tr><td colspan="3" style="text-align: right;"><strong>IVA (16%):</strong></td><td>$<?php echo number_format($factura['iva'], 2); ?></td></tr>
                <tr class="total-row"><td colspan="3" style="text-align: right;"><strong>TOTAL:</strong></td><td><strong>$<?php echo number_format($factura['monto_venta'], 2); ?></strong></td></tr>
            </tfoot>
        </table>

        <div class="acciones">
            <a href="facturas_lista.php" class="btn-back">← Volver</a>
            <a href="factura_pdf.php?id=<?php echo $id_factura; ?>" class="btn-pdf">📄 Descargar PDF</a>
        </div>
    </div>
</body>
</html>