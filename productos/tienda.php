<?php
require_once 'includes/conexion.php';

// Obtener categorías para filtros
$sql_categorias = "SELECT id, nombre FROM CATEGORIA_PRODUCTO WHERE activo = 1";
$result_categorias = $conn->query($sql_categorias);

// Obtener productos con filtros
$where = "p.activo = 1 AND p.stock_actual > 0";
$params = [];
$types = "";

if (isset($_GET['categoria']) && !empty($_GET['categoria'])) {
    $where .= " AND p.id_categoria = ?";
    $params[] = $_GET['categoria'];
    $types .= "i";
}

if (isset($_GET['buscar']) && !empty($_GET['buscar'])) {
    $where .= " AND (p.nombre LIKE ? OR p.descripcion LIKE ?)";
    $buscar = "%" . $_GET['buscar'] . "%";
    $params[] = $buscar;
    $params[] = $buscar;
    $types .= "ss";
}

$sql_productos = "SELECT p.*, c.nombre AS categoria_nombre 
                  FROM PRODUCTO p
                  JOIN CATEGORIA_PRODUCTO c ON p.id_categoria = c.id
                  WHERE $where
                  ORDER BY p.id DESC";

$stmt = $conn->prepare($sql_productos);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result_productos = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Productos - BIOSPET</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/estilo.css">
    <style>
        .tienda-header {
            background: #0d2c40; /* Color azul premium de tu marca */
            color: white;
            padding: 50px 0;
            text-align: center;
        }
        .tienda-header h1 { font-size: 2.5rem; margin-bottom: 10px; }
        
        .filtros {
            background: white;
            padding: 25px;
            border-radius: var(--radius-md);
            margin-bottom: 40px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
            box-shadow: var(--shadow-soft);
        }
        .filtros select, .filtros input {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: var(--radius-sm);
            flex: 1;
            min-width: 200px;
        }
        .filtros button {
            background: #0d2c40;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: bold;
            transition: background 0.2s;
        }
        .filtros button:hover { background: #163e59; }
        
        .grid-productos {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }
        .producto-card {
            background: white;
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-soft);
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
        }
        .producto-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .producto-imagen { height: 220px; background: #f5f5f5; position: relative; }
        .producto-imagen img { width: 100%; height: 100%; object-fit: cover; }
        
        .producto-info { 
            padding: 20px; 
            display: flex; 
            flex-direction: column; 
            flex-grow: 1; 
        }
        .producto-info h3 { font-size: 1.2rem; margin-bottom: 10px; color: #333; }
        .producto-info p { font-size: 0.9rem; color: #666; line-height: 1.4; margin-bottom: 15px; }
        
        .producto-meta {
            margin-top: auto; /* Empuja el precio y botón al fondo de la card */
        }
        .producto-precio { 
            font-size: 1.4rem; 
            font-weight: bold; 
            color: #0d2c40; 
            margin-bottom: 15px; 
        }
        .btn-producto {
            display: block;
            background: #0d2c40;
            color: white;
            text-align: center;
            padding: 12px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: bold;
            transition: background 0.2s;
        }
        .btn-producto:hover { background: #163e59; color: white; }
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
                <a href="index.php#contacto">Contacto</a>
            </nav>
        </div>
    </header>

    <div class="tienda-header">
        <div class="container">
            <h1>🛒 Nuestro Catálogo</h1>
            <p>Medicamentos, alimentos especializados y accesorios para el cuidado de tu mascota</p>
        </div>
    </div>

    <div class="container" style="margin-top: 40px; margin-bottom: 60px;">
        <div class="filtros">
            <select name="categoria" id="categoria">
                <option value="">Todas las categorías</option>
                <?php while($cat = $result_categorias->fetch_assoc()): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_GET['categoria']) && $_GET['categoria'] == $cat['id']) ? 'selected' : ''; ?>>
                    <?php echo $cat['nombre']; ?>
                </option>
                <?php endwhile; ?>
            </select>
            <input type="text" id="buscar" placeholder="¿Qué estás buscando?..." value="<?php echo isset($_GET['buscar']) ? htmlspecialchars($_GET['buscar']) : ''; ?>">
            <button onclick="filtrar()">Buscar</button>
            <a href="tienda.php" style="color: #666; text-decoration: none; font-size: 14px; margin-left: 10px;">Limpiar filtros</a>
        </div>

        <div class="grid-productos">
            <?php if ($result_productos->num_rows > 0): ?>
                <?php while($producto = $result_productos->fetch_assoc()): ?>
                <div class="producto-card">
                    <div class="producto-imagen">
                        <img src="<?php echo !empty($producto['imagen']) ? $producto['imagen'] : 'assets/images/productos/placeholder.jpg'; ?>" alt="<?php echo htmlspecialchars($producto['nombre']); ?>">
                    </div>
                    <div class="producto-info">
                        <h3><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                        <p><?php echo htmlspecialchars(substr($producto['descripcion'] ?? '', 0, 95)); ?>...</p>
                        
                        <div class="producto-meta">
                            <div class="producto-precio">$<?php echo number_format($producto['precio_venta'], 2); ?> MXN</div>
                            <a href="producto.php?id=<?php echo $producto['id']; ?>" class="btn-producto">Ver detalles</a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; grid-column: 1/-1; color: #666; font-size: 1.2rem; padding: 40px 0;">
                    🔍 No encontramos productos que coincidan con tu búsqueda.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <footer class="main-footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <h3 class="footer-title">BIOSPET</h3>
                    
                </div>
                
                <div>
                    <h3 class="footer-title">Síguenos</h3>
                    <a href="https://www.facebook.com/share/1B46s8Hz3g" target="_blank" class="footer-link">📱 Facebook</a>
                    <a href="https://www.instagram.com/biospet_puebla" target="_blank" class="footer-link">📷 Instagram</a>
                </div>
            </div>
            <hr class="footer-divider">
            <p class="footer-copyright">© <?php echo date('Y'); ?> BIOSPET. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script>
        function filtrar() {
            let categoria = document.getElementById('categoria').value;
            let buscar = document.getElementById('buscar').value;
            let url = 'tienda.php?';
            if (categoria) url += 'categoria=' + categoria + '&';
            if (buscar) url += 'buscar=' + encodeURIComponent(buscar);
            window.location.href = url;
        }

        // Permitir buscar al presionar "Enter" en el input
        document.getElementById('buscar').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                filtrar();
            }
        });
    </script>
</body>
</html>