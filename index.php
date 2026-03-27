<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BIOSPET - Clínica Veterinaria</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/estilo.css">
    <script src="assets/js/modal.js" defer></script>
    <script src="assets/js/slider.js" defer></script>
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
                <a href="#servicios">Servicios</a>
                <a href="citas.php">Citas</a>
                <a href="tienda.php">Tienda</a>
                <a href="#contacto">Contacto</a>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="slideshow-container">
            <div class="slide fade">
                <img src="assets/images/imagen1.jpeg">
                <div class="slide-content">
                    <!-- <a href="citas.php" class="btn">Agendar Cita</a> -->
                </div>
            </div>
            <div class="slide fade">
                <img src="assets/images/imagen2.jpeg">
                <div class="slide-content">
                    <!-- <a href="citas.php" class="btn">Agendar Cita</a> -->
                </div>
            </div>
            
            <!-- Botones de navegación -->
            <!-- <a class="prev" onclick="cambiarSlide(-1)">❮</a>
            <a class="next" onclick="cambiarSlide(1)">❯</a> -->
        </div>
        
        <!-- Indicadores de posición (puntos) -->
        <div class="dots-container">
            <span class="dot" onclick="slideActual(1)"></span>
            <span class="dot" onclick="slideActual(2)"></span>
        </div>
    </section>

    <section class="servicios" id="servicios">
        <div class="container">
            <h2>Nuestros Servicios</h2>
            <div class="grid-servicios">
                <?php
                $servicios = [
                    [
                        'nombre' => 'Tomografía',
                        'descripcion' => 'Equipo Lightspeed de última generación. Estudios 3D de alta resolución.',
                        'icono' => '🖥️',
                        'imagen' => 'assets/images/equipos/tomografo.png',
                        'detalle' => 'Tomógrafo GE Lightspeed de 16 cortes. Imágenes de alta resolución para diagnóstico preciso.'
                    ],
                    [
                        'nombre' => 'Rayos X',
                        'descripcion' => 'Digital directo. Mínima radiación, máxima calidad de imagen.',
                        'icono' => '📡',
                        'imagen' => 'assets/images/equipos/rayosx.jpg',
                        'detalle' => 'Equipo digital de última generación. Menor exposición a radiación.'
                    ],
                    [
                        'nombre' => 'Ultrasonido',
                        'descripcion' => 'Equipo Doppler color. Estudios abdominales y cardíacos.',
                        'icono' => '🔊',
                        'imagen' => 'assets/images/equipos/ultrasonido.jpg',
                        'detalle' => 'Ultrasonido Doppler color con tecnología 4D para estudios avanzados.'
                    ],
                    [
                        'nombre' => 'Electrocardiograma',
                        'descripcion' => 'Monitoreo cardíaco completo con interpretación especializada.',
                        'icono' => '💓',
                        'imagen' => 'assets/images/equipos/electro.jpg',
                        'detalle' => 'Electrocardiógrafo digital con análisis automático.'
                    ]
                ];
                
                foreach($servicios as $servicio) {
                    echo '<div class="servicio-card">';
                    echo '<div class="icono">' . $servicio['icono'] . '</div>';
                    echo '<h3>' . $servicio['nombre'] . '</h3>';
                    echo '<p>' . $servicio['descripcion'] . '</p>';
                    echo '<img src="' . $servicio['imagen'] . '" alt="' . $servicio['nombre'] . '" class="equipo-img" 
                          onclick="abrirModal(\'' . $servicio['imagen'] . '\', \'' . $servicio['nombre'] . '\', \'' . $servicio['detalle'] . '\')">';
                    echo '<a href="citas.php" class="btn" style="margin-top: 15px; display: inline-block;">Agendar Cita</a>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </section>

    <!-- MODAL (aparece al hacer clic en imagen) -->
    <div id="modal-equipo" class="modal">
        <div class="modal-contenido">
            <span class="modal-cerrar">&times;</span>
            <img id="modal-imagen" src="" alt="Equipo BIOSPET">
            <h3 id="modal-titulo"></h3>
            <p id="modal-descripcion"></p>
        </div>
    </div>

    <!-- SECCIÓN DE PRODUCTOS -->
    <section class="productos" id="productos">
        <div class="container">
            <h2>🛒 Productos Destacados</h2>
            <p class="section-desc">Alimentos, medicamentos y accesorios para tu mascota</p>
            
            <div class="grid-productos">
                <?php
                // Conectar a la base de datos para obtener productos activos
                require_once 'includes/conexion.php';
                
                // Obtener productos con stock disponible
                $sql_productos = "SELECT id, nombre, descripcion, precio_venta, stock_actual, imagen 
                                  FROM PRODUCTO 
                                  WHERE activo = 1 AND stock_actual > 0 
                                  ORDER BY id DESC 
                                  LIMIT 8";
                $result_productos = $conn->query($sql_productos);
                
                if ($result_productos->num_rows > 0) {
                    while($producto = $result_productos->fetch_assoc()) {
                        // Si no hay imagen, usar placeholder
                        $imagen = !empty($producto['imagen']) ? $producto['imagen'] : 'assets/images/productos/placeholder.jpg';
                        $stock_texto = $producto['stock_actual'] < 10 ? '¡Últimas unidades!' : 'En stock';
                        $stock_clase = $producto['stock_actual'] < 10 ? 'stock-bajo' : 'stock-normal';
                        
                        echo '<div class="producto-card">';
                        echo '<div class="producto-imagen">';
                        echo '<img src="' . $imagen . '" alt="' . htmlspecialchars($producto['nombre']) . '">';
                        echo '<span class="stock ' . $stock_clase . '">' . $stock_texto . '</span>';
                        echo '</div>';
                        echo '<div class="producto-info">';
                        echo '<h3>' . htmlspecialchars($producto['nombre']) . '</h3>';
                        echo '<p>' . htmlspecialchars(substr($producto['descripcion'], 0, 80)) . '...</p>';
                        echo '<div class="producto-precio">$' . number_format($producto['precio_venta'], 2) . ' MXN</div>';
                        echo '<a href="producto.php?id=' . $producto['id'] . '" class="btn btn-producto">Ver detalles</a>';
                        echo '</div>';
                        echo '</div>';
                    }
                } else {
                    echo '<p class="no-productos">Próximamente más productos disponibles</p>';
                }
                ?>
            </div>
            
            <div class="ver-mas">
                <a href="tienda.php" class="btn btn-ver-mas">Ver todos los productos →</a>
            </div>
        </div>
    </section>

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
                    <a href="admin/login.php" class="footer-link" style="opacity: 0.5; font-size: 12px;">🔐 Admin</a>
                </div>
            </div>
            <hr class="footer-divider">
            <p class="footer-copyright">© <?php echo date('Y'); ?> BIOSPET. Todos los derechos reservados.</p>
        </div>
    </footer>
</body>
</html>