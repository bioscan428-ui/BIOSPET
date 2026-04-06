<?php
session_start();
require_once 'includes/conexion.php';

// Procesar eliminación
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    unset($_SESSION['carrito'][$id]);
    header('Location: carrito.php');
    exit;
}

// Procesar vaciar carrito
if (isset($_GET['vaciar'])) {
    unset($_SESSION['carrito']);
    header('Location: carrito.php');
    exit;
}

// Procesar actualización de cantidades
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar'])) {
    foreach ($_POST['cantidad'] as $id => $cantidad) {
        if ($cantidad <= 0) {
            unset($_SESSION['carrito'][$id]);
        } else {
            $_SESSION['carrito'][$id] = (int)$cantidad;
        }
    }
    header('Location: carrito.php');
    exit;
}

// Obtener productos del carrito
$productos_carrito = [];
$total = 0;

if (!empty($_SESSION['carrito'])) {
    $ids = array_keys($_SESSION['carrito']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT id, nombre, precio_venta, stock_actual, imagen FROM PRODUCTO WHERE id IN ($placeholders) AND activo = 1";
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
            'subtotal' => $subtotal,
            'imagen' => $producto['imagen'] ?? 'assets/images/productos/placeholder.jpg',
            'stock' => $producto['stock_actual']
        ];
        $total += $subtotal;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras - BIOSPET</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/estilo.css">
    <style>
        .carrito-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
        }
        .carrito-tabla {
            width: 100%;
            border-collapse: collapse;
        }
        .carrito-tabla th, .carrito-tabla td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .carrito-tabla th {
            background: var(--black);
            color: white;
        }
        .producto-imagen {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: var(--radius-sm);
        }
        .cantidad-input {
            width: 60px;
            padding: 5px;
            text-align: center;
        }
        .btn-actualizar, .btn-eliminar, .btn-vaciar {
            padding: 5px 10px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-size: 12px;
        }
        .btn-actualizar {
            background: var(--primary);
            color: white;
            border: none;
            cursor: pointer;
        }
        .btn-eliminar {
            background: #f44336;
            color: white;
        }
        .btn-vaciar {
            background: #666;
            color: white;
        }
        .resumen {
            text-align: right;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #eee;
        }
        .total {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
        }
        .acciones-carrito {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .btn-checkout {
            background: #4caf50;
            color: white;
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: bold;
        }
        .carrito-vacio {
            text-align: center;
            padding: 40px;
            color: #999;
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
        <div class="carrito-container">
            <h1>🛒 Mi Carrito</h1>
            
            <?php if (empty($productos_carrito)): ?>
                <div class="carrito-vacio">
                    <p>🛍️ Tu carrito está vacío</p>
                    <a href="tienda.php" class="btn">Seguir comprando</a>
                </div>
            <?php else: ?>
                <form method="POST">
                    <table class="carrito-tabla">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Precio</th>
                                <th>Cantidad</th>
                                <th>Subtotal</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos_carrito as $item): ?>
                            <tr>
                                <td style="display: flex; align-items: center; gap: 10px;">
                                    <img src="<?php echo $item['imagen']; ?>" class="producto-imagen">
                                    <?php echo htmlspecialchars($item['nombre']); ?>
                                </td>
                                <td>$<?php echo number_format($item['precio'], 2); ?></td>
                                <td>
                                    <input type="number" name="cantidad[<?php echo $item['id']; ?>]" 
                                           value="<?php echo $item['cantidad']; ?>" min="1" max="<?php echo $item['stock']; ?>" 
                                           class="cantidad-input">
                                </td>
                                <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                                <td>
                                    <a href="carrito.php?eliminar=<?php echo $item['id']; ?>" class="btn-eliminar">Eliminar</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="acciones-carrito">
                        <a href="tienda.php" class="btn">➕ Seguir comprando</a>
                        <div>
                            <button type="submit" name="actualizar" class="btn-actualizar">🔄 Actualizar carrito</button>
                            <a href="carrito.php?vaciar=1" class="btn-vaciar" onclick="return confirm('¿Vaciar carrito?')">🗑️ Vaciar carrito</a>
                        </div>
                    </div>
                </form>
                
                <div class="resumen">
                    <p><strong>Total:</strong> <span class="total">$<?php echo number_format($total, 2); ?></span></p>
                    <a href="checkout.php" class="btn-checkout">✅ Proceder al pago</a>
                </div>
            <?php endif; ?>
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