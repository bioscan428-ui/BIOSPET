<?php
session_start();
require_once 'includes/conexion.php';

// Verificar que hay productos en el carrito
if (empty($_SESSION['carrito'])) {
    header('Location: tienda.php');
    exit;
}

// Obtener productos del carrito
$productos_carrito = [];
$total = 0;
$ids = array_keys($_SESSION['carrito']);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "SELECT id, nombre, precio_venta FROM PRODUCTO WHERE id IN ($placeholders) AND activo = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
$stmt->execute();
$result = $stmt->get_result();

while ($producto = $result->fetch_assoc()) {
    $cantidad = $_SESSION['carrito'][$producto['id']];
    $subtotal = $producto['precio_venta'] * $cantidad;
    $productos_carrito[] = [
        'id' => $producto['id'],
        'nombre' => $producto['nombre'],
        'precio' => $producto['precio_venta'],
        'cantidad' => $cantidad,
        'subtotal' => $subtotal
    ];
    $total += $subtotal;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Checkout - BIOSPET</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/estilo.css">
    <style>
        .checkout-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--radius-sm);
        }
        .resumen-compra {
            background: #f5f5f5;
            padding: 15px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
        }
        .total {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
        }
        .btn-pagar {
            background: #4caf50;
            color: white;
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 1.1rem;
            cursor: pointer;
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
                <a href="carrito.php">🛒 Carrito</a>
                <a href="#contacto">Contacto</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="checkout-container">
            <h1>✅ Finalizar Compra</h1>
            
            <div class="resumen-compra">
                <h3>Resumen del pedido</h3>
                <?php foreach ($productos_carrito as $item): ?>
                    <p><?php echo $item['cantidad']; ?> x <?php echo $item['nombre']; ?> - $<?php echo number_format($item['subtotal'], 2); ?></p>
                <?php endforeach; ?>
                <hr>
                <p><strong>Total: <span class="total">$<?php echo number_format($total, 2); ?></span></strong></p>
            </div>
            
            <form action="procesar_compra.php" method="POST">
                <h3>Datos del Cliente</h3>
                <div class="form-group">
                    <label>Nombre completo *</label>
                    <input type="text" name="nombre" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Teléfono *</label>
                    <input type="tel" name="telefono" required>
                </div>
                <div class="form-group">
                    <label>Dirección *</label>
                    <input type="text" name="direccion" required>
                </div>
                <div class="form-group">
                    <label>Método de pago *</label>
                    <select name="metodo_pago" required>
                        <option value="efectivo">💵 Efectivo (pago en clínica)</option>
                        <option value="tarjeta">💳 Tarjeta (pago en clínica)</option>
                        <option value="transferencia">🏦 Transferencia</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-pagar">✅ Confirmar compra</button>
            </form>
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
</body>
</html>