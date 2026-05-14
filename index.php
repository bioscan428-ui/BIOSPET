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
            <!-- ===== NUEVOOOOOOOOOOOOOOOOO SLIDER DE IMÁGENES ===== -->
            <div class="servicios-slider">
                <div class="slider-container">
                    <div class="slider-track">
                        <div class="slider-slide">
                            <img src="assets/images/tomografia.png" alt="Tomografía">
                            <p>Tomografía</p>
                        </div>
                        <div class="slider-slide">
                            <img src="assets/images/rayosx.png" alt="Rayos X">
                            <p>Rayos X</p>
                        </div>
                        <div class="slider-slide">
                            <img src="assets/images/ultrasonido.png" alt="Ultrasonido">
                            <p>Ultrasonido</p>
                        </div>
                        <div class="slider-slide">
                            <img src="assets/images/electrocardiograma.png" alt="Electrocardiograma">
                            <p>Electrocardiograma</p>
                        </div>
                        <div class="slider-slide">
                            <img src="assets/images/consulta.png" alt="Consulta">
                            <p>Consulta General</p>
                        </div>
                        <div class="slider-slide">
                            <img src="assets/images/bano.png" alt="Baño">
                            <p>Baño</p>
                        </div>
                    </div>
                </div>
                <button class="slider-btn prev" id="sliderPrev">❮</button>
                <button class="slider-btn next" id="sliderNext">❯</button>
                <div class="slider-dots" id="sliderDots"></div>
            </div>
            <!-- ===== FIN DE NUEVO SLIDER DE IMÁGENES ===== -->

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
        <!-- ===== NUEVA SECCIÓN: REDES SOCIALES DEBAJO DEL MAPA ===== -->
        <div class="redes-sociales">
            <h3>Síguenos en redes sociales</h3>
            <div class="social-icons">
                <a href="https://www.facebook.com/tu-clinica" target="_blank" class="social-icon facebook" aria-label="Facebook">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/>
                    </svg>
                </a>
                <a href="https://www.instagram.com/tu-clinica" target="_blank" class="social-icon instagram" aria-label="Instagram">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 
                        4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 
                        0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 
                        2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 
                        4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 
                        15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 
                        0 000-2.881z"/>
                    </svg>
                </a>
                <a href="https://wa.me/521234567890" target="_blank" class="social-icon whatsapp" aria-label="WhatsApp">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.74.44 3.44 1.23 4.93L2 22l
                        5.35-1.35c1.43.77 3.05 1.19 4.69 1.19 5.46 0 9.91-4.45 9.91-9.91 0-5.45-4.45-9.9-9.91-9.9zm-.01 15.2
                        4c-1.37 0-2.71-.37-3.88-1.07l-.28-.16-3.16.86.86-3.05-.18-.29c-.79-1.25-1.21-2.69-1.21-4.16 0-4.43
                        3.61-8.04 8.04-8.04s8.04 3.61 8.04 8.04-3.61 8.04-8.04 8.04zM16.52 14.45c-.25-.13-1.48-.73-1.71-.81
                        -.23-.08-.4-.13-.57.13-.17.26-.65.81-.8.98-.15.17-.3.19-.55.06-.25-.13-1.06-.39-2.02-1.25-.75-.67-1.
                        25-1.5-1.4-1.75-.15-.25-.02-.38.11-.51.11-.11.25-.29.38-.44.13-.15.17-.26.25-.43.09-.17.04-.32-.02-.
                        45-.06-.13-.57-1.37-.78-1.87-.21-.5-.4-.42-.57-.42-.15 0-.33-.01-.5-.01-.17 0-.45.07-.69.33-.24.26-.
                        92.9-.92 2.2 0 1.3.95 2.55 1.08 2.73.13.18 1.86 2.85 4.51 3.98.63.27 1.12.43 1.5.55.63.2 1.2.17 1.6
                        6.1.51-.07 1.57-.64 1.79-1.26.22-.62.22-1.15.15-1.26-.07-.11-.26-.18-.51-.31z"/>
                    </svg>
                </a>
            </div>
        </div>
        <!-- =====  FIN DE NUEVA SECCIÓN: REDES SOCIALES DEBAJO DEL MAPA ===== -->
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

     // ===== SLIDER DE SERVICIOS (AUTOMÁTICO) =====
    const sliderTrack = document.querySelector('.slider-track');
    const slides = document.querySelectorAll('.slider-slide');
    const prevBtn = document.getElementById('sliderPrev');
    const nextBtn = document.getElementById('sliderNext');
    const dotsContainer = document.getElementById('sliderDots');

    let currentIndex = 0;
    const totalSlides = slides.length;
    let autoSlideInterval;
    const autoSlideDelay = 3000; // 3 segundos

    //Crear dots
    function createDots(){
        if(!dotsContainer) return;
        dotsContainer.innerHTML = '';
        for (let i = 0; i <= totalSlides; i++){
            const dot = document.createElement('div');
            dot.classList.add('dot');
            if(i === currentIndex) dot.classList.add('active');
            dot.addEventListener('click', () => goToSlide(i));
            dotsContainer.appendChild(dot);
        }
    }

    // Ir a slide específico
    function goToSlide(index) {
        if (index < 0) index = 0;
        if (index >= totalSlides) index = totalSlides - 1;
        currentIndex = index;
        sliderTrack.style.transform = `translateX(-${currentIndex * 100}%)`;
        
        // Actualizar dots
        document.querySelectorAll('.dot').forEach((dot, i) => {
            dot.classList.toggle('active', i === currentIndex);
        });
    }

    // Siguiente slide
    function nextSlide() {
        if (currentIndex < totalSlides - 1) {
            goToSlide(currentIndex + 1);
        } else {
            goToSlide(0); // Volver al inicio
        }
    }

    // Anterior slide
    function prevSlide() {
        if (currentIndex > 0) {
            goToSlide(currentIndex - 1);
        } else {
            goToSlide(totalSlides - 1); // Ir al final
        }
    }

    // Iniciar auto-slide
    function startAutoSlide() {
        if (autoSlideInterval) clearInterval(autoSlideInterval);
        autoSlideInterval = setInterval(nextSlide, autoSlideDelay);
    }

    // Detener auto-slide
    function stopAutoSlide() {
        if (autoSlideInterval) clearInterval(autoSlideInterval);
    }

    // Eventos
    if (prevBtn) prevBtn.addEventListener('click', () => {
        prevSlide();
        stopAutoSlide();
        startAutoSlide();
    });

    if (nextBtn) nextBtn.addEventListener('click', () => {
        nextSlide();
        stopAutoSlide();
        startAutoSlide();
    });

    // Pausar auto-slide al hacer hover
    const sliderContainer = document.querySelector('.servicios-slider');
    if (sliderContainer) {
        sliderContainer.addEventListener('mouseenter', stopAutoSlide);
        sliderContainer.addEventListener('mouseleave', startAutoSlide);
    }

    createDots();
    goToSlide(0);
    startAutoSlide();
    </script>
</body>
</html>