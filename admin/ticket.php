<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/conexion.php';

// ============================================
// COMANDO PARA ABRIR CAJA (ESC/POS)
// ============================================
function enviarComandoAbrirCaja() {
    // Comando estándar ESC/POS para abrir caja
    $comando = chr(27) . chr(112) . chr(0) . chr(50) . chr(250);
    
    // Intentar diferentes métodos según el sistema operativo
    
    // Método 1: Windows - Impresora compartida
    $impresora = "GTP 801 Printer"; // Nombre exacto de tu impresora
    
    if (($handle = @fopen($impresora, "w"))) {
        fwrite($handle, $comando);
        fclose($handle);
        return true;
    }
    
    // Método 2: Windows - Puerto USB
    $usb_paths = ["\\\\.\\USB001", "\\\\.\\USB002", "\\\\.\\LPT1"];
    foreach ($usb_paths as $path) {
        if (($handle = @fopen($path, "w"))) {
            fwrite($handle, $comando);
            fclose($handle);
            return true;
        }
    }
    
    // Método 3: Linux (si el servidor tuviera acceso local)
    if (($handle = @fopen("/dev/usb/lp0", "w"))) {
        fwrite($handle, $comando);
        fclose($handle);
        return true;
    }
    
    return false;
}

// Enviar comando para abrir caja ANTES de mostrar el HTML
$caja_abierta = enviarComandoAbrirCaja();
// ============================================
// FIN DE COMANDO PARA ABRIR CAJA (ESC/POS)
// ============================================

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
        .line { border-top: none; margin: 4px 0; }
        .line-doble { border-top: none; margin: 8px 0; }
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
        <img src="/assets/images/logo_biospet_inverso.png" alt="BIOSPET" style="max-width: 180px; margin-bottom: 10px;">
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
            <!-----
            <span class="info-label">Cliente:</span>
            ------->
            <!-- Datos del cliente -->
            <span><?php // echo $venta['cliente_nombre']; ?></span>

        </div>
        <?php if($venta['cliente_telefono']): ?>
        
        
        <?php endif; ?>
        <?php if($venta['cliente_email']): ?>
        
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

    <!-- Código QR para facturación -->
    <?php
    // Generar URL para facturación (página pública)
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $url_factura = $protocol . $host . "/facturar.php?venta_id=" . $id_venta;
    // Generar QR usando API de Google Charts
    $qr_url = "https://quickchart.io/qr?size=100&text=" . urlencode($url_factura);
    ?>

    
    <div class="center" style="margin: 15px 0;">
        <div class="line"></div>
        <p><strong>📄 ¿Necesitas factura?</strong></p>
        <img src="<?php echo $qr_url; ?>" alt="Código QR para factura" style="width: 80px; height: 80px; margin: 5px auto;">
        <p style="font-size: 9px;">Escanea el QR para solicitar tu factura</p>
        <div class="line"></div>
    </div>
    <!-- FIN DE Código QR para facturación -->
    
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
         // Imprimir automáticamente al cargar la página
    // Intentar abrir caja desde el navegador (Web Serial API)
        async function abrirCajaWebSerial() {
            try {
                // Verificar si el navegador soporta Web Serial API
                if (!navigator.serial) {
                    console.log('Web Serial API no soportada en este navegador');
                    return;
                }
                
                // Solicitar puerto USB al usuario
                const port = await navigator.serial.requestPort();
                await port.open({ baudRate: 9600 });
                
                // Comando ESC/POS para abrir caja
                const comando = new Uint8Array([27, 112, 0, 50, 250]);
                const writer = port.writable.getWriter();
                await writer.write(comando);
                writer.releaseLock();
                await port.close();
                
                console.log('✅ Caja abierta desde navegador');
            } catch (error) {
                console.log('No se pudo abrir caja:', error);
            }
        }

        // Al cargar la página, intentar abrir caja
        window.onload = function() {
            // Intentar abrir caja vía Web Serial API
            abrirCajaWebSerial();
            
            // Luego imprimir automáticamente
            setTimeout(function() {
                window.print();
            }, 500);
        };
        
        // Detectar cuando se cierra el diálogo de impresión
        window.onafterprint = function() {
            setTimeout(function() {
                window.close();
            }, 1000);
        };
    </script>
</body>
</html>