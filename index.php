<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BIOSPET - Clínica Veterinaria | Tecnología de Punta</title>
    <meta name="description" content="Clínica veterinaria con tecnología de última generación. Tomografía, Rayos X, Ultrasonido y más. Atención 24/7 para tu mascota.">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="assets/css/pagina.css">
    <script src="assets/js/modal.js" defer></script>
</head>
<body>

    <!-- WhatsApp Flotante -->
    <a href="https://wa.me/521234567890?text=Hola%2C%20me%20gustar%C3%ADa%20agendar%20una%20cita%20para%20mi%20mascota." 
        class="whatsapp-float" target="_blank" aria-label="WhatsApp">
        <img src="assets/images/whatsapp.png" alt="WhatsApp">
    </a>

    <!-- Header -->
    <header>
        <div class="container header-flex">
            <!-- LOGO -->
            
                
                <span>BIOSPET</span>
    
            <!-- BOTÓN HAMBURGUESA -->
            <div class="menu-toggle" id="menu-toggle">
                ☰
            </div>

            <!-- MENÚ -->
            <nav id="menu">
                <a href="index.php">Inicio</a>
                <a href="#servicios">Servicios</a>
                <a href="#tienda">Tienda</a>
                <a href="citas.php">Citas</a>
                <a href="#sucursales">Ubicaciones</a>
                <a href="#contacto">Contacto</a>
            </nav>
        </div>
    </header>

    <!-- Hero Section Moderno (sin slider) -->
    <section class="hero">
        <div class="container hero-flex">
            <div class="hero-texto">
                
                
                <h1>
                    Cuidamos a tu mascota<br>
                    <span>como parte de la familia</span>
                </h1>
                <p>Atención veterinaria con tecnología avanzada, diagnósticos precisos y trato humano.</p>

                <div class="hero-buttons">
                    <a href="citas.php" class="btn btn-primary">Agendar cita</a>
                    <a href="#servicios" class="btn btn-outline">Ver servicios</a>
                </div>
            </div>

            <div class="hero-img">
                <img src="assets/images/biospet.JPG" alt="BIOSPET">
            </div>
        </div>
    </section>

    <!-- Servicios Destacados -->
    <section class="servicios" id="servicios">
        <div class="container">
            <div class="section-header">
                <h2>Nuestros <span>Servicios</span></h2>
                <p class="section-subtitle">Cuidado integral con tecnología de vanguardia para tu mascota</p>
            </div>
            <div class="servicios-layout">
                <!-- IMAGEN DEL PERRITO -->
                <div class="servicios-img">
                    <img src="assets/images/perro_doctor.jpg" alt="Perro Doctor">
            </div>
            <!-- LISTA DE SERVICIOS -->
            <div class="servicios-grid">
                <?php
                $servicios_destacados = [
                    ['icono' => '🖥️', 'nombre' => 'Tomografía', 'descripcion' => 'Estudios 3D de alta resolución. Diagnóstico preciso con equipo Lightspeed.'],
                    ['icono' => '📡', 'nombre' => 'Rayos X', 'descripcion' => 'Digital directo. Mínima radiación, máxima calidad de imagen.'],
                    ['icono' => '🔊', 'nombre' => 'Ultrasonido', 'descripcion' => 'Doppler color. Estudios abdominales y cardíacos avanzados.'],
                    ['icono' => '💓', 'nombre' => 'Electrocardiograma', 'descripcion' => 'Monitoreo cardíaco completo con interpretación especializada.'],
                    ['icono' => '🩺', 'nombre' => 'Consulta General', 'descripcion' => 'Atención médica preventiva y curativa para tu mascota.'],
                    ['icono' => '🚑', 'nombre' => 'Baño', 'descripcion' => 'Atención inmediata para emergencias, los 365 días del año.']
                ];
                foreach($servicios_destacados as $servicio) {
                    echo '<div class="servicio-item">';
                    echo '<span class="servicio-icono">' . $servicio['icono'] . '</span>';
                    echo '<div>';
                    echo '<h3>' . $servicio['nombre'] . '</h3>';
                    echo '<p>' . $servicio['descripcion'] . '</p>';
                    echo '</div>';
                    echo '</div>';
                }
                ?>
            </div>

        </div>
    </section>

    <!-- Banner de imagenes -->
    <section class="confianza-banner">
    <div class="container confianza-flex">

        <!-- IZQUIERDA -->
        <div class="confianza-imgs">
            <img src="assets/images/tomografia.png" class="img img-1">
            <img src="assets/images/rayosx.png" class="img img-2">
            <img src="assets/images/ultrasonido.png" class="img img-3">
        </div>

        <!-- CENTRO -->
        <div class="confianza-content">
            <h2>
                Tecnología e instalaciones<br>
                <span>de primer nivel</span>
            </h2>
            <p>Equipos especializados para diagnósticos precisos y atención de calidad.</p>
            <a href="citas.php" class="btn btn-primary">Agendar cita</a>
        </div>

        <!-- DERECHA -->
        <div class="confianza-imgs">
            <img src="assets/images/electrocardiograma.png" class="img img-4">
            <img src="assets/images/consulta.png" class="img img-5">
            <img src="assets/images/bano.png" class="img img-6">
        </div>
    </div>
    </section>

    <!-- Productos Destacados (Tienda) -->
    <section class="productos" id="tienda">
        <div class="container">
            <div class="section-header">
                <h2>🛒 Sección de <span>Productos</span></h2>
                <p class="section-subtitle">Alimentos, medicamentos y accesorios para tu mascota</p>
            </div>
            
            <div class="productos-grid">
                <?php
                require_once 'includes/conexion.php';
                $sql_productos = "SELECT id, nombre, descripcion, precio_venta, stock_actual, imagen 
                                FROM PRODUCTO 
                                WHERE activo = 1 AND stock_actual > 0 
                                ORDER BY id DESC 
                                  LIMIT 6";
                $result_productos = $conn->query($sql_productos);
                
                if ($result_productos->num_rows > 0) {
                    while($producto = $result_productos->fetch_assoc()) {
                        $imagen = !empty($producto['imagen']) ? $producto['imagen'] : 'assets/images/productos/placeholder.jpg';
                        $stock_texto = $producto['stock_actual'] < 10 ? '¡Últimas unidades!' : 'En stock';
                        $stock_clase = $producto['stock_actual'] < 10 ? 'stock-bajo' : 'stock-normal';
                        
                        echo '<div class="producto-card">';
                        echo '<div class="producto-imagen">';
                        echo '<img src="' . $imagen . '" alt="' . htmlspecialchars($producto['nombre']) . '" loading="lazy">';
                        echo '<span class="stock ' . $stock_clase . '">' . $stock_texto . '</span>';
                        echo '</div>';
                        echo '<div class="producto-info">';
                        echo '<h3>' . htmlspecialchars($producto['nombre']) . '</h3>';
                        echo '<p>' . htmlspecialchars(substr($producto['descripcion'] ?? '', 0, 80)) . '</p>';
                        echo '<div class="producto-precio">$' . number_format($producto['precio_venta'], 2) . ' MXN</div>';
                        echo '<a href="producto.php?id=' . $producto['id'] . '" class="btn btn-producto">Ver detalles</a>';
                        echo '</div>';
                        echo '</div>';
                    }
                } else {
                    echo '<p class="no-productos">✨ Próximamente más productos disponibles</p>';
                }
                ?>
            </div>
            
            <div class="ver-mas">
                <a href="tienda.php" class="btn btn-ver-mas">Ver todos los productos →</a>
            </div>
        </div>
    </section>

    <!-- Sucursal/ Ubicaciones -->
    <section class="ubicacion" id="sucursales">
    <div class="container">
        <div class="section-header">
            <h2>Visítanos en <span>BIOSPET</span></h2>
            <p class="section-subtitle">Estamos listos para atenderte 24/7</p>
        </div>

        <div class="ubicacion-grid">

            <!-- INFO -->
            <div class="ubicacion-info">
                <h3>Sede Sonata</h3>
                <p>📍 Bioscan Sonata #448<br>
                P.º Opera 17002, Lomas de Angelópolis, 72830 Heroica Puebla de Zaragoza, Pue.</p>

                <p>🕒 Abierto 24 horas</p>
                <p>📞 55 9025 2000</p>

                <!-------------
                <a href="https://www.google.com/maps/place/Bioscan+Sonata/" 
                    target="_blank" 
                    class="btn-primary">
                    Ver en Google Maps
                </a>
                ---------------->
            </div>
            

            <!-- MAPA -->
            <div class="ubicacion-mapa">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3772.5918857307597!2d-98.27841802479666!3d18.993622982194044!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x85cfb94be532d229%3A0x9f7ca30f15a19773!2sBioscan%20Sonata!5e0!3m2!1ses-419!2smx!4v1777327527756!5m2!1ses-419!2smx" 
                    width="600" 
                    height="450" 
                    style="border:0;" 
                    allowfullscreen="" 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
        </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="main-footer" id="contacto">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <h3 class="footer-title">BIOSPET</h3>
                    <p>Cuidando la salud de tus mascotas con tecnología de punta y atención personalizada.</p>
                </div>
                <div class="footer-col">
                    <h3 class="footer-title">Contacto</h3>
                    <p>📞 Tel: (55) 9025 2000</p>
                    <p>📧 Email: info@biospet.com</p>
                    <a href="https://wa.me/521234567890?text=Hola%2C%20me%20gustar%C3%ADa%20agendar%20una%20cita%20para%20mi%20mascota." 
                        class="whatsapp-footer" target="_blank">
                        💬 Envíanos un WhatsApp
                    </a>
                </div>
                <div class="footer-col">
                    <h3 class="footer-title">Horarios</h3>
                    <p>🌙 Atención 24/7 en sede Miguel Ángel</p>
                    <p>🕘 Otras sedes: 9:00 - 21:00</p>
                </div>
                <div class="footer-col">
                    <h3 class="footer-title">Síguenos</h3>
                    <a href="#" class="footer-link">📱 Facebook</a>
                    <a href="#" class="footer-link">📷 Instagram</a>
                    <a href="admin/login.php" class="footer-link footer-admin">🔐 Administración</a>
                </div>
            </div>
            <hr class="footer-divider">
            <p class="footer-copyright">© <?php echo date('Y'); ?> BIOSPET. Todos los derechos reservados.</p>
        </div>
    </footer>

    <!-- Modal para equipos -->
    <div id="modal-equipo" class="modal">
        <div class="modal-contenido">
            <span class="modal-cerrar">&times;</span>
            <img id="modal-imagen" src="">
            <h3 id="modal-titulo"></h3>
            <p id="modal-descripcion"></p>
        </div>
    </div>

    <script>
        // ===== WhatsApp Tooltip =====
    const whatsappFloat = document.querySelector('.whatsapp-float');
    if (whatsappFloat) {
        whatsappFloat.setAttribute('title', '¡Contáctanos por WhatsApp!');
    }

    // ===== MENÚ HAMBURGUESA =====
    const toggle = document.getElementById('menu-toggle');
    const menu = document.getElementById('menu');

    if (toggle && menu) {
        toggle.addEventListener('click', () => {
            menu.classList.toggle('active');
        });
    }
    </script>
</body>
</html>