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
        .btn-comprar {
            background: var(--primary);
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 1.1rem;
            border-radius: var(--radius-sm);
            cursor: pointer;
            width: 100%;
        }
        .btn-comprar:hover {
            background: var(--primary-dark);
        }
        .stock-info {
            margin: 20px 0;
            padding: 10px;
            background: #f5f5f5;
            border-radius: var(--radius-sm);
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
                    📦 Stock disponible: <?php echo $producto['stock_actual']; ?> unidades
                </div>
                <button class="btn-comprar" onclick="alert('Funcionalidad de carrito próximamente')">Agregar al carrito</button>
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
</body>
</html>