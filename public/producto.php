<?php
require_once 'includes/conexion.php';

$id_producto = (int)($_GET['id'] ?? 0);
if (!$id_producto) {
    header('Location: tienda.php');
    exit;
}

$sql = "SELECT p.*, c.nombre AS categoria_nombre 
        FROM PRODUCTO p
        JOIN CATEGORIA_PRODUCTO c ON p.id_categoria = c.id
        WHERE p.id = ? AND p.activo = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_producto);
$stmt->execute();
$result = $stmt->get_result();
$producto = $result->fetch_assoc();

if (!$producto) {
    header('Location: tienda.php');
    exit;
}

// Configuración de WhatsApp (¡CAMBIAR POR TU NÚMERO REAL!)
$whatsapp_num = "522218203396"; // Reemplaza con el número real: código país + número
$nombre_producto = $producto['nombre'];
$precio = $producto['precio_venta'];
$id = $producto['id'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $producto['nombre']; ?> - BIOSPET</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/estilo.css">
    <style>
        .producto-detalle {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin: 60px auto;
            background: white;
            padding: 40px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
        }
        .producto-imagen img {
            width: 100%;
            border-radius: var(--radius-md);
        }
        .producto-info h1 {
            color: var(--primary);
            margin-bottom: 15px;
        }
        .producto-categoria {
            color: #666;
            margin-bottom: 15px;
        }
        .producto-precio {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary);
            margin: 20px 0;
        }
        
        /* Estilos nuevos para la compra por WhatsApp */
        .cantidad-selector {
            margin: 20px 0;
        }
        .cantidad-selector label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
            color: #333;
        }
        .cantidad-selector input {
            width: 100px;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: var(--radius-sm);
            font-size: 1rem;
            text-align: center;
        }
        .cantidad-selector input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .btn-whatsapp {
            background: #25D366;
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 1.1rem;
            border-radius: var(--radius-sm);
            cursor: pointer;
            width: 100%;
            display: inline-block;
            text-align: center;
            text-decoration: none;
            transition: transform 0.2s, background 0.2s;
        }
        .btn-whatsapp:hover {
            background: #128C7E;
            transform: translateY(-2px);
        }
        
        .seguridad-info {
            margin-top: 15px;
            padding: 10px;
            background: #f0fdf4;
            border-left: 4px solid #25D366;
            border-radius: var(--radius-sm);
            font-size: 12px;
            color: #166534;
            text-align: center;
        }
        
        .stock-info {
            margin: 20px 0;
            padding: 10px;
            background: #f5f5f5;
            border-radius: var(--radius-sm);
        }
        
        .stock-bajo {
            color: #e67e22;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .producto-detalle {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <a href="index.php" class="logo">
                <img src="assets/images/biospet.JPG" alt="Logo BIOSPET" class="logo-img">
                <span>BIOSPET</span>
            </a>
            <nav>
                <a href="index.php">Inicio</a>
                <a href="index.php#servicios">Servicios</a>
                <a href="citas.php">Citas</a>
                <a href="tienda.php">Tienda</a>
                <a href="#contacto">Contacto</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="producto-detalle">
            <div class="producto-imagen">
                <img src="<?php echo !empty($producto['imagen']) ? $producto['imagen'] : 'assets/images/productos/placeholder.jpg'; ?>" alt="<?php echo $producto['nombre']; ?>">
            </div>
            <div class="producto-info">
                <h1><?php echo htmlspecialchars($producto['nombre']); ?></h1>
                <div class="producto-categoria">Categoría: <?php echo $producto['categoria_nombre']; ?></div>
                <p><?php echo nl2br(htmlspecialchars($producto['descripcion'])); ?></p>
                
                <div class="producto-precio">$<?php echo number_format($producto['precio_venta'], 2); ?> MXN</div>
                
                <div class="stock-info">
                    📦 Stock disponible: 
                    <?php if($producto['stock_actual'] > 0): ?>
                        <span class="<?php echo $producto['stock_actual'] < 10 ? 'stock-bajo' : ''; ?>">
                            <?php echo $producto['stock_actual']; ?> unidades
                        </span>
                        <?php if($producto['stock_actual'] < 10): ?>
                            <span style="display: block; margin-top: 5px;">⚠️ ¡Últimas unidades!</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="stock-bajo">🚫 Agotado</span>
                    <?php endif; ?>
                </div>
                
                <?php if($producto['stock_actual'] > 0): ?>
                <!-- Selector de cantidad -->
                <div class="cantidad-selector">
                    <label for="cantidad">Cantidad:</label>
                    <input type="number" id="cantidad" min="1" max="<?php echo $producto['stock_actual']; ?>" value="1">
                    <p style="font-size: 12px; color: #666; margin-top: 5px;">
                        Subtotal: $<span id="subtotal"><?php echo number_format($producto['precio_venta'], 2); ?></span> MXN
                    </p>
                </div>
                
                <!-- Botón de compra por WhatsApp -->
                <a href="#" id="btn-whatsapp" class="btn-whatsapp" target="_blank">
                    💬 Comprar por WhatsApp
                </a>
                
                <div class="seguridad-info">
                    🔒 Tu compra es segura. Te contactaremos por WhatsApp para confirmar disponibilidad y pago.
                </div>
                <?php else: ?>
                <button class="btn-comprar" style="background: #ccc; cursor: not-allowed;" disabled>
                    🚫 Producto agotado
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="main-footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <h3 class="footer-title">BIOSPET</h3>
                    <p>Cuidando la salud de tus mascotas con tecnología de punta.</p>
                </div>
                <div>
                    <h3 class="footer-title">Contacto</h3>
                    <p>📞 Tel: (123) 456-7890</p>
                    <p>📧 Email: info@biospet.com</p>
                </div>
                <div>
                    <h3 class="footer-title">Síguenos</h3>
                    <a href="#" class="footer-link">📱 Facebook</a>
                    <a href="#" class="footer-link">📷 Instagram</a>
                </div>
            </div>
            <hr class="footer-divider">
            <p class="footer-copyright">© <?php echo date('Y'); ?> BIOSPET. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script>
        // Configuración de WhatsApp (mismo número del PHP)
        const whatsappNumber = "<?php echo $whatsapp_num; ?>";
        const productName = "<?php echo addslashes($nombre_producto); ?>";
        const productPrice = <?php echo $precio; ?>;
        const productId = <?php echo $id; ?>;
        
        // Elementos del DOM
        const cantidadInput = document.getElementById('cantidad');
        const subtotalSpan = document.getElementById('subtotal');
        const btnWhatsapp = document.getElementById('btn-whatsapp');
        
        // Actualizar subtotal y enlace cuando cambia la cantidad
        function actualizarCompra() {
            let cantidad = parseInt(cantidadInput.value);
            let stock = <?php echo $producto['stock_actual']; ?>;
            
            // Validar cantidad
            if (isNaN(cantidad) || cantidad < 1) {
                cantidad = 1;
                cantidadInput.value = 1;
            }
            if (cantidad > stock) {
                cantidad = stock;
                cantidadInput.value = stock;
                alert('La cantidad no puede superar el stock disponible');
            }
            
            // Calcular subtotal
            let subtotal = cantidad * productPrice;
            subtotalSpan.textContent = subtotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            
            // Crear mensaje de WhatsApp
            let mensaje = "Hola, me gustaría comprar:%0A";
            mensaje += "*" + productName + "*%0A";
            mensaje += "Cantidad: " + cantidad + " unidades%0A";
            mensaje += "Precio unitario: $" + productPrice.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") + " MXN%0A";
            mensaje += "Subtotal: $" + subtotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",") + " MXN%0A%0A";
            mensaje += "ID Producto: " + productId + "%0A";
            mensaje += "Me gustaría saber disponibilidad y formas de pago.";
            
            // Actualizar el enlace
            btnWhatsapp.href = "https://wa.me/" + whatsappNumber + "?text=" + mensaje;
        }
        
        // Eventos
        if (cantidadInput) {
            cantidadInput.addEventListener('input', actualizarCompra);
            cantidadInput.addEventListener('change', actualizarCompra);
        }
        
        // Inicializar enlace al cargar la página
        actualizarCompra();
    </script>
</body>
</html>