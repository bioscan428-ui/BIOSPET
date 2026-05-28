<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/conexion.php';

// ============================================
// COMANDO PARA ABRIR CAJA (ESC/POS)
// ============================================
function enviarComandoAbrirCaja() {
    $comando = chr(27) . chr(112) . chr(0) . chr(50) . chr(250);
    $impresora = "GTP 801 Printer"; 
    
    if (($handle = @fopen($impresora, "w"))) {
        fwrite($handle, $comando);
        fclose($handle);
        return true;
    }
    
    $usb_paths = ["\\\\.\\USB001", "\\\\.\\USB002", "\\\\.\\LPT1"];
    foreach ($usb_paths as $path) {
        if (($handle = @fopen($path, "w"))) {
            fwrite($handle, $comando);
            fclose($handle);
            return true;
        }
    }
    
    if (($handle = @fopen("/dev/usb/lp0", "w"))) {
        fwrite($handle, $comando);
        fclose($handle);
        return true;
    }
    
    return false;
}

$caja_abierta = enviarComandoAbrirCaja();
// ============================================

$id_venta = $_GET['id'] ?? 0;
if (!$id_venta) {
    die("Venta no encontrada");
}

$sql = "SELECT * FROM vista_ticket_venta WHERE venta_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_venta);
$stmt->execute();
$venta = $stmt->get_result()->fetch_assoc();

if (!$venta) {
    die("Venta no encontrada");
}

$sql_prod = "SELECT * FROM vista_ticket_detalle WHERE id_venta = ? ORDER BY id_producto";
$stmt_prod = $conn->prepare($sql_prod);
$stmt_prod->bind_param("i", $id_venta);
$stmt_prod->execute();
$productos = $stmt_prod->get_result();

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
            font-size: 13px;
            /* AUMENTÉ EL ANCHO DE 280px a 350px para más espacio lateral */
            width: 350px;
            margin: 0 auto;
            /* AUMENTÉ EL PADDING LATERAL de 10px a 15px */
            padding: 15px 20px;
            background: white;
            color: #000;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        
        /* Líneas divisorias */
        .line { 
            border-top: 1px dashed #000; 
            margin: 8px 0; 
            height: 0;
        }
        .line-doble { 
            border-top: 1px double #000; 
            margin: 12px 0; 
            height: 4px;
        }
        
        .total { font-size: 16px; font-weight: bold; }
        
        /* Ajuste de columnas - MEJOR DISTRIBUCIÓN DEL ESPACIO */
        .producto { 
            margin: 6px 0; 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start; 
        }
        .producto-nombre { 
            width: 55%; 
            word-wrap: break-word; 
        }
        .producto-cantidad { 
            width: 15%; 
            text-align: center; 
        }
        .producto-precio { 
            width: 30%; 
            text-align: right; 
        }
        
        .info-row { 
            display: flex; 
            justify-content: space-between; 
            margin: 6px 0; 
        }
        .info-label { 
            font-weight: bold; 
        }
        .gracias { 
            margin-top: 20px; 
            text-align: center; 
        }
        
        .text-muted { 
            font-size: 11px; 
        }

        /* MEJOR VISUALIZACIÓN DEL QR */
        .qr-container {
            text-align: center;
            margin: 15px 0;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        .qr-container img {
            width: 100px;
            height: 100px;
            margin: 8px auto;
            display: block;
        }

        @media print {
            body { 
                margin: 0; 
                padding: 10px 15px; 
            }
            .no-print { 
                display: none; 
            }
            .qr-container {
                background: none;
            }
        }
        .btn-print {
            background: #4caf50;
            color: white;
            border: none;
            padding: 12px 15px;
            margin-top: 15px;
            cursor: pointer;
            border-radius: 5px;
            width: 100%;
            font-family: monospace;
            font-size: 14px;
        }
        .btn-close {
            background: #666;
            color: white;
            border: none;
            padding: 12px 15px;
            margin-top: 8px;
            cursor: pointer;
            border-radius: 5px;
            width: 100%;
            font-family: monospace;
            font-size: 14px;
        }
        
        /* MEJOR ESPACIADO PARA EL LOGO */
        .logo {
            max-width: 200px;
            margin-bottom: 15px;
        }
        
        /* SEPARACIÓN ENTRE SECCIONES */
        .section {
            margin-bottom: 5px;
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
        <p><strong>TICKET #<?php echo str_pad($id_venta, 8, '0', STR_PAD_LEFT); ?></strong><br>
        <?php echo date('d/m/Y H:i:s', strtotime($venta['fecha_venta'])); ?></p>
        <div class="line"></div>
    </div>

    <div>
        <div class="info-row">
            <span><?php // echo $venta['cliente_nombre']; ?></span>
        </div>
    </div>

    <div class="info-row">
        <span class="info-label">Atendió:</span>
        <span><?php echo $venta['empleado_nombre'] ?? 'Sistema'; ?></span>
    </div>

    <div class="line"></div>

    <div>
        <div class="producto bold">
            <span class="producto-nombre">Producto</span>
            <span class="producto-cantidad">Cant</span>
            <span class="producto-precio">Total</span>
        </div>
        <div class="line"></div>
        
        <?php while($prod = $productos->fetch_assoc()): ?>
        <div class="producto">
            <span class="producto-nombre"><?php echo htmlspecialchars($prod['producto_nombre']); ?></span>
            <span class="producto-cantidad"><?php echo $prod['cantidad']; ?></span>
            <span class="producto-precio">$<?php echo number_format($prod['subtotal'], 2); ?></span>
        </div>
        <?php if($prod['descuento'] > 0): ?>
        <div class="producto text-muted" style="margin-top: -2px;">
            <span class="producto-nombre">&nbsp;&nbsp;Descuento <?php echo $prod['porcentaje_descuento']; ?></span>
            <span class="producto-cantidad"></span>
            <span class="producto-precio">-$<?php echo number_format($prod['descuento'], 2); ?></span>
        </div>
        <?php endif; ?>
        <?php endwhile; ?>
    </div>

    <div class="line"></div>

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
        <p><strong>¡Gracias por su compra!</strong><br>
        Vuelva pronto</p>
        <br>
        <p class="text-muted">Este ticket es comprobante de pago<br>
        No tiene validez fiscal</p>
    </div>

    <div class="no-print" style="margin-top: 15px;">
        <button onclick="window.print()" class="btn-print">
            🖨️ Imprimir Ticket
        </button>
        <button onclick="window.close()" class="btn-print" style="background: #666; margin-top: 5px;">
            ❌ Cerrar
        </button>
    </div>

    <script>
        async function abrirCajaWebSerial() {
            try {
                if (!navigator.serial) {
                    console.log('Web Serial API no soportada');
                    return;
                }
                const port = await navigator.serial.requestPort();
                await port.open({ baudRate: 9600 });
                const comando = new Uint8Array([27, 112, 0, 50, 250]);
                const writer = port.writable.getWriter();
                await writer.write(comando);
                writer.releaseLock();
                await port.close();
            } catch (error) {
                console.log('No se pudo abrir caja:', error);
            }
        }

        window.onload = function() {
            abrirCajaWebSerial();
            setTimeout(function() {
                window.print();
            }, 500);
        };
        
        window.onafterprint = function() {
            setTimeout(function() {
                window.close();
            }, 1000);
        };
    </script>
</body>
</html>