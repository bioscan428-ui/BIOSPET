<?php
// ticket_cita.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/conexion.php';

$id_venta = $_GET['id'] ?? 0;
if (!$id_venta) {
    die("Venta no encontrada");
}

// Usar la vista para el encabezado
$sql = "SELECT * FROM vista_ticket_cita_venta WHERE venta_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_venta);
$stmt->execute();
$venta = $stmt->get_result()->fetch_assoc();

if (!$venta) {
    die("Venta no encontrada");
}

// Usar la vista para el detalle
$sql_prod = "SELECT * FROM vista_ticket_cita_detalle WHERE id_venta = ? ORDER BY tipo_item DESC, descripcion";
$stmt_prod = $conn->prepare($sql_prod);
$stmt_prod->bind_param("i", $id_venta);
$stmt_prod->execute();
$items = $stmt_prod->get_result();

$recibido = $_GET['recibido'] ?? null;
$vuelto = $_GET['vuelto'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket Cita #<?php echo str_pad($id_venta, 8, '0', STR_PAD_LEFT); ?> - BIOSPET</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            width: 280px;
            margin: 0 auto;
            padding: 10px;
            background: white;
            color: #000;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .line { border-top: 1px dashed #000; margin: 6px 0; }
        .line-doble { border-top: 1px double #000; margin: 10px 0; }
        .total { font-size: 16px; font-weight: bold; }
        .producto { margin: 6px 0; display: flex; justify-content: space-between; align-items: flex-start; }
        .producto-nombre { width: 55%; word-wrap: break-word; }
        .producto-cantidad { width: 15%; text-align: center; }
        .producto-precio { width: 30%; text-align: right; }
        .info-row { display: flex; justify-content: space-between; margin: 4px 0; }
        .info-label { font-weight: bold; }
        .gracias { margin-top: 20px; text-align: center; }
        .text-muted { font-size: 11px; }
        .servicio-item { color: #2c7da0; }
        .producto-item { color: #2e7d32; }
        @media print { body { margin: 0; padding: 5px; } .no-print { display: none; } }
        .btn-print {
            background: #4caf50;
            color: white;
            border: none;
            padding: 10px 15px;
            margin-top: 15px;
            cursor: pointer;
            border-radius: 5px;
            width: 100%;
            font-family: monospace;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="center">
        <img src="/assets/images/logo_biospet_inverso.png" alt="BIOSPET" style="max-width: 180px; margin-bottom: 10px;">
        <p><strong><?php echo $venta['empresa_eslogan']; ?></strong><br>
        <?php echo $venta['empresa_direccion']; ?><br>
        Tel: <?php echo $venta['empresa_telefono']; ?></p>
        <div class="line"></div>
        <p><strong>TICKET DE CITA #<?php echo str_pad($id_venta, 8, '0', STR_PAD_LEFT); ?></strong><br>
        <?php echo date('d/m/Y H:i:s', strtotime($venta['fecha_venta'])); ?></p>
        <div class="line"></div>
    </div>

    <!-- Información de la cita -->
    <div class="info-row">
        <span class="info-label">Cita #:</span>
        <span><?php echo $venta['cita_id']; ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Fecha Cita:</span>
        <span><?php echo date('d/m/Y', strtotime($venta['fecha_cita'])); ?> <?php echo $venta['hora_cita']; ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Mascota:</span>
        <span><?php echo htmlspecialchars($venta['nombre_mascota']); ?></span>
    </div>
    <?php if($venta['motivo']): ?>
    <div class="info-row">
        <span class="info-label">Motivo:</span>
        <span><?php echo substr(htmlspecialchars($venta['motivo']), 0, 50); ?></span>
    </div>
    <?php endif; ?>
    <div class="line"></div>

    <div class="info-row">
        <span class="info-label">Atendió:</span>
        <span><?php echo $venta['empleado_nombre'] ?? 'Sistema'; ?></span>
    </div>

    <div class="line"></div>

    <!-- Items (productos y servicios) -->
    <div>
        <div class="producto bold">
            <span class="producto-nombre">Concepto</span>
            <span class="producto-cantidad">Cant</span>
            <span class="producto-precio">Total</span>
        </div>
        <div class="line"></div>
        
        <?php while($item = $items->fetch_assoc()): ?>
        <div class="producto <?php echo $item['tipo_item'] === 'servicio' ? 'servicio-item' : 'producto-item'; ?>">
            <span class="producto-nombre"><?php echo htmlspecialchars($item['descripcion']); ?></span>
            <span class="producto-cantidad"><?php echo $item['cantidad']; ?></span>
            <span class="producto-precio">$<?php echo number_format($item['subtotal'], 2); ?></span>
        </div>
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

    <!-- QR para facturación -->
    <?php
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $url_factura = $protocol . $host . "/facturar.php?venta_id=" . $id_venta;
    $qr_url = "https://quickchart.io/qr?size=120&text=" . urlencode($url_factura);
    ?>

    <div class="center" style="margin: 15px 0;">
        <p><strong>📄 ¿Necesitas factura?</strong></p>
        <img src="<?php echo $qr_url; ?>" alt="Código QR para factura" style="width: 100px; height: 100px; margin: 8px auto; display: block;">
        <p class="text-muted">Escanea el QR para solicitar tu factura</p>
    </div>
    
    <div class="line"></div>
    
    <div class="gracias">
        <p><strong>¡Gracias por su visita!</strong><br>
        Vuelva pronto con su mascota</p>
        <br>
        <p class="text-muted">Este ticket es comprobante de pago<br>
        No tiene validez fiscal</p>
    </div>

    <div class="no-print" style="margin-top: 15px;">
        <button onclick="window.print()" class="btn-print">🖨️ Imprimir Ticket</button>
        <button onclick="window.close()" class="btn-print" style="background: #666; margin-top: 5px;">❌ Cerrar</button>
    </div>

    <script>
        function abrirCajaWebSerial() {
            if (!navigator.serial) return;
            navigator.serial.requestPort()
                .then(port => port.open({ baudRate: 9600 }))
                .then(port => {
                    const writer = port.writable.getWriter();
                    writer.write(new Uint8Array([27, 112, 0, 50, 250]));
                    writer.releaseLock();
                    return port.close();
                })
                .catch(e => console.log('No se pudo abrir caja:', e));
        }

        window.onload = function() {
            abrirCajaWebSerial();
            setTimeout(() => window.print(), 500);
        };
        
        window.onafterprint = function() {
            setTimeout(() => window.close(), 1000);
        };
    </script>
</body>
</html>