<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/conexion.php';

$id_venta = $_GET['id'] ?? 0;
if (!$id_venta) {
    die("Venta no encontrada");
}

// Usar la vista para obtener datos del ticket
$sql = "SELECT * FROM vista_ticket_venta WHERE venta_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_venta);
$stmt->execute();
$venta = $stmt->get_result()->fetch_assoc();

if (!$venta) {
    die("Venta no encontrada");
}

// Usar la vista para obtener productos
$sql_prod = "SELECT * FROM vista_ticket_detalle WHERE id_venta = ? ORDER BY id_producto";
$stmt_prod = $conn->prepare($sql_prod);
$stmt_prod->bind_param("i", $id_venta);
$stmt_prod->execute();
$productos = $stmt_prod->get_result();

// Recibir datos de vuelto (si viene de pago en efectivo)
$recibido = $_GET['recibido'] ?? null;
$vuelto = $_GET['vuelto'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket #<?php echo str_pad($id_venta, 8, '0', STR_PAD_LEFT); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            width: 280px;
            margin: 0 auto;
            padding: 10px;
            background: white;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .line { border-top: 1px dashed #000; margin: 8px 0; }
        .line-doble { border-top: 2px solid #000; margin: 8px 0; }
        .total { font-size: 14px; font-weight: bold; }
        .producto { margin: 4px 0; display: flex; justify-content: space-between; }
        .producto-nombre { width: 55%; }
        .producto-cantidad { width: 15%; text-align: center; }
        .producto-precio { width: 30%; text-align: right; }
        .info-row { display: flex; justify-content: space-between; margin: 3px 0; }
        .info-label { font-weight: bold; }
        .gracias { margin-top: 15px; text-align: center; font-style: italic; }
        @media print {
            body { margin: 0; padding: 5px; }
            .no-print { display: none; }
        }
        .btn-print {
            background: #4caf50;
            color: white;
            border: none;
            padding: 8px 15px;
            margin-top: 15px;
            cursor: pointer;
            border-radius: 5px;
            width: 100%;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <!-- Encabezado -->
    <div class="center">
        <h3><?php echo $venta['empresa_nombre']; ?></h3>
        <p><?php echo $venta['empresa_eslogan']; ?><br>
        <?php echo $venta['empresa_direccion']; ?><br>
        <?php echo $venta['empresa_telefono']; ?></p>
        <div class="line"></div>
        <p><strong>TICKET #<?php echo str_pad($id_venta, 8, '0', STR_PAD_LEFT); ?></strong><br>
        <?php echo date('d/m/Y H:i:s', strtotime($venta['fecha_venta'])); ?></p>
        <div class="line"></div>
    </div>

    <!-- Datos del cliente -->
    <div>
        <div class="info-row">
            <span class="info-label">Cliente:</span>
            <span><?php echo $venta['cliente_nombre']; ?></span>
        </div>
        <?php if($venta['cliente_telefono']): ?>
        <div class="info-row">
            <span class="info-label">Teléfono:</span>
            <span><?php echo $venta['cliente_telefono']; ?></span>
        </div>
        <?php endif; ?>
        <?php if($venta['cliente_email']): ?>
        <div class="info-row">
            <span class="info-label">Email:</span>
            <span><?php echo $venta['cliente_email']; ?></span>
        </div>
        <?php endif; ?>
    </div>

    <div class="line"></div>

    <!-- Datos del vendedor -->
    <div class="info-row">
        <span class="info-label">Atendió:</span>
        <span><?php echo $venta['empleado_nombre'] ?? 'Sistema'; ?></span>
    </div>

    <div class="line"></div>

    <!-- Productos -->
    <div>
        <div class="producto bold">
            <span>Producto</span>
            <span>Cant</span>
            <span>Total</span>
        </div>
        <div class="line"></div>
        
        <?php while($prod = $productos->fetch_assoc()): ?>
        <div class="producto">
            <span class="producto-nombre"><?php echo htmlspecialchars($prod['producto_nombre']); ?></span>
            <span class="producto-cantidad"><?php echo $prod['cantidad']; ?></span>
            <span class="producto-precio">$<?php echo number_format($prod['subtotal'], 2); ?></span>
        </div>
        <?php if($prod['descuento'] > 0): ?>
        <div class="producto" style="font-size: 9px; color: #666; margin-top: -2px;">
            <span class="producto-nombre">  Descuento <?php echo $prod['porcentaje_descuento']; ?></span>
            <span class="producto-cantidad"></span>
            <span class="producto-precio">-$<?php echo number_format($prod['descuento'], 2); ?></span>
        </div>
        <?php endif; ?>
        <?php endwhile; ?>
    </div>

    <div class="line"></div>

    <!-- Totales -->
    <div>
        <div class="info-row">
            <span>SUBTOTAL:</span>
            <span>$<?php echo number_format($venta['subtotal'], 2); ?></span>
        </div>
        <?php if($venta['iva'] > 0): ?>
        <div class="info-row">
            <span>IVA (16%):</span>
            <span>$<?php echo number_format($venta['iva'], 2); ?></span>
        </div>
        <?php endif; ?>
        <div class="info-row total">
            <span>TOTAL:</span>
            <span>$<?php echo number_format($venta['total'], 2); ?></span>
        </div>
    </div>

    <div class="line"></div>

    <!-- Método de pago y vuelto -->
    <div>
        <div class="info-row">
            <span>Método de pago:</span>
            <span><?php echo ucfirst($venta['metodo_pago']); ?></span>
        </div>
        
        <?php if($recibido && $vuelto): ?>
        <div class="info-row">
            <span>Recibido:</span>
            <span>$<?php echo number_format($recibido, 2); ?></span>
        </div>
        <div class="info-row">
            <span>Vuelto:</span>
            <span>$<?php echo number_format($vuelto, 2); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <div class="line-doble"></div>

    <!-- Mensaje de agradecimiento -->
    <div class="gracias">
        <p>¡Gracias por su compra!<br>
        Vuelva pronto</p>
        <p style="font-size: 9px;">Este ticket es comprobante de pago<br>
        No tiene validez fiscal</p>
    </div>

    <!-- Botones (solo visibles en pantalla, no se imprimen) -->
    <div class="no-print" style="margin-top: 15px;">
        <button onclick="window.print()" class="btn-print">
            🖨️ Imprimir Ticket
        </button>
        <button onclick="window.close()" class="btn-print" style="background: #666; margin-top: 5px;">
            ❌ Cerrar
        </button>
    </div>

    <script>
        // Imprimir automáticamente al cargar (opcional)
        // window.onload = function() {
        //     window.print();
        //     setTimeout(() => { window.close(); }, 1000);
        // }
    </script>
</body>
</html>