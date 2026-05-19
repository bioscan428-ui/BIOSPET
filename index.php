<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'includes/conexion.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BIOSPET - Clínica Veterinaria | Tecnología de Punta</title>
    <meta name="description" content="Clínica veterinaria con tecnología de última generación. Tomografía, Rayos X, Ultrasonido y más. Atención 24/7 para tu mascota.">
    <link rel="icon" href="assets/images/favicon_biospet.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,500;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/home.css">
</head>
<body>

<!-- HEADER PREMIUM -->
<nav class="navbar">
    <div class="container-fluid px-2">
        <a class="navbar-brand" href="index.php">
            <img src="assets/images/logo_biospet_inverso.png" alt="BIOSPET" class="logo-navbar">
        </a>
        <div class="slogan">Cuidamos lo que más amas</div>

        <div class="redes-sociales-header">
            <a href="https://www.instagram.com/biospet_puebla" target="_blank">
                <img src="assets/images/instagram_sfondo.png" alt="Instagram">
            </a>
            <a href="https://wa.me/522218203396" target="_blank">
                <img src="assets/images/whatsapp_sfondo.png" alt="WhatsApp">
            </a>
            <a href="https://www.facebook.com/share/1B46s8Hz3g" target="_blank">
                <img src="assets/images/facebook_sfondo.png" alt="Facebook">
            </a>
        </div>

        <div class="usuario-icono">
            <img src="assets/images/usuario_sfondo.png" alt="Usuario" id="btn-usuario">
        </div>

        <button class="menu-toggle">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
</nav>

<!-- MENU LATERAL -->
<div id="menu-lateral" class="menu-lateral">
    <a href="index.php">Inicio</a>
    <a href="#servicios">Servicios</a>
    <a href="#tienda">Tienda</a>
    <a href="citas.php">Citas</a>
    <a href="#ubicacion">Ubicación</a>
    <a href="#contacto">Contacto</a>
</div>
<div id="overlay" class="overlay"></div>

<!-- MODAL LOGIN -->
<div id="modal-login" class="modal-login">
    <div class="modal-contenido">
        <h3>Iniciar Sesión</h3>
        <label>Usuario</label>
        <input type="text" id="login-usuario" placeholder="Ingresa tu usuario">
        <label>Contraseña</label>
        <input type="password" id="login-password" placeholder="Ingresa tu contraseña">
        <div class="modal-botones">
            <button id="cerrar-modal">Cancelar</button>
            <button class="btn-acceder" id="btn-acceder">Acceder</button>
        </div>
        <div style="text-align: center; margin-top: 15px; font-size: 12px;">
            <a href="admin/login.php" style="color: var(--naranja); text-decoration: none;">🔐 Panel Administración</a>
        </div>
    </div>
</div>

<!-- LAYOUT PRINCIPAL 50/50 -->
<div class="home-layout">
    <!-- HERO CON SLIDER -->
    <section class="hero">
        <div class="hero-bg">
            <img src="assets/images/perro1.jpg" class="active">
            <img src="assets/images/gato1.jpg">
            <img src="assets/images/perro2.jpg">
            <img src="assets/images/gato2.jpg">
            <img src="assets/images/perro3.jpg">
        </div>
        <div class="container">
            <h1>Bienvenido a BIOSPET</h1>
            <p id="hero-texto">Lunes a Sábado: 8:00am - 6:00pm</p>
            <div class="hero-botones">
                <a href="citas.php" class="btn-main">Agendar cita</a>
                <a href="#collage-info" class="btn-servicios">Más información</a>
            </div>
        </div>
    </section>

    <!-- SERVICIOS -->
    <section class="servicios" id="servicios">
        <div class="servicios-box">
            <h2>Nuestros Servicios</h2>
            <div class="servicios-grid">
                <div class="servicio-item"><i class="fa-solid fa-x-ray icon-serv"></i><span>Rayos X</span></div>
                <div class="servicio-item"><i class="fa-solid fa-camera-rotate icon-serv"></i><span>Tomografía</span></div>
                <div class="servicio-item"><i class="fa-solid fa-wave-square icon-serv"></i><span>Ultrasonidos</span></div>
                <div class="servicio-item"><i class="fa-solid fa-flask-vial icon-serv"></i><span>Análisis Clínicos</span></div>
                <div class="servicio-item"><i class="fa-solid fa-heart-pulse icon-serv"></i><span>Electrocardiograma</span></div>
                <div class="servicio-item"><i class="fa-solid fa-stethoscope icon-serv"></i><span>Consulta Veterinaria</span></div>
                <div class="servicio-item"><i class="fa-solid fa-bone icon-serv"></i><span>Productos</span></div>
                <div class="servicio-item"><i class="fa-solid fa-scissors icon-serv"></i><span>Estética</span></div>
            </div>
        </div>
    </section>
</div>

<!-- COLLAGE INFO -->
<section class="collage-info" id="collage-info">
    <img src="assets/images/collage_inicio.png" alt="Collage BIOSPET">
    <div class="flechas-scroll">
        <i class="fa-solid fa-chevron-down"></i>
        <i class="fa-solid fa-chevron-down"></i>
        <i class="fa-solid fa-chevron-down"></i>
    </div>
</section>

<!-- PRODUCTOS DESTACADOS (TU SECCIÓN ORIGINAL CON BD) -->
<section class="productos-section" id="tienda">
    <div class="section-header">
        <h2>🛒 Productos <span>Destacados</span></h2>
        <p>Alimentos, medicamentos y accesorios para tu mascota</p>
    </div>
    <div class="productos-grid">
        <?php
        $sql_productos = "SELECT id, nombre, descripcion, precio_venta, stock_actual, imagen 
                        FROM PRODUCTO 
                        WHERE activo = 1 AND stock_actual > 0 
                        ORDER BY id DESC LIMIT 6";
        $result_productos = $conn->query($sql_productos);
        
        if ($result_productos && $result_productos->num_rows > 0) {
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
                echo '<a href="producto.php?id=' . $producto['id'] . '" class="btn-producto">Ver detalles</a>';
                echo '</div></div>';
            }
        } else {
            echo '<p class="no-productos" style="text-align:center; grid-column:1/-1;">✨ Próximamente más productos disponibles</p>';
        }
        ?>
    </div>
    <div class="ver-mas">
        <a href="tienda.php" class="btn-ver-mas">Ver todos los productos →</a>
    </div>
</section>

<!-- UBICACION -->
<section class="ubicacion-section" id="ubicacion">
    <div class="ubicacion-box">
        <div class="ubicacion-texto">
            <h3>Nos encontramos en Torre Alpha, Lomas de Angelópolis, Puebla.</h3>
            <p>Un espacio moderno y especializado para brindar atención veterinaria con tecnología de vanguardia, comodidad y fácil acceso para ti y tu mascota.</p>
            <a href="https://maps.app.goo.gl/T1DTftYnZW9pGdTg6" target="_blank" class="btn-ubicacion">Ver ubicación en Google Maps</a>
        </div>
        <div class="ubicacion-mapa">
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3772.608227867161!2d-98.2757488!3d18.9929019!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x85cfb9fe6df667c7%3A0xe13969eb3756b95c!2sBiospet!5e0!3m2!1ses-419!2smx!4v1778882905961!5m2!1ses-419!2smx" allowfullscreen="" loading="lazy"></iframe>
        </div>
    </div>
</section>

<!-- REDES SOCIALES SECTION -->
<section class="redes-sociales-section" id="contacto">
    <h3>Síguenos en redes sociales</h3>
    <div class="social-icons">
        <a href="https://www.facebook.com/share/1B46s8Hz3g" target="_blank" class="social-icon facebook">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
                <path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/>
            </svg>
        </a>
        <a href="https://www.instagram.com/biospet_puebla" target="_blank" class="social-icon instagram">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0z"/>
            </svg>
        </a>
        <a href="https://wa.me/522218203396" target="_blank" class="social-icon whatsapp">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.74.44 3.44 1.23 4.93L2 22l5.35-1.35c1.43.77 3.05 1.19 4.69 1.19 5.46 0 9.91-4.45 9.91-9.91 0-5.45-4.45-9.9-9.91-9.9zm-.01 15.24c-1.37 0-2.71-.37-3.88-1.07l-.28-.16-3.16.86.86-3.05-.18-.29c-.79-1.25-1.21-2.69-1.21-4.16 0-4.43 3.61-8.04 8.04-8.04s8.04 3.61 8.04 8.04-3.61 8.04-8.04 8.04zM16.52 14.45c-.25-.13-1.48-.73-1.71-.81-.23-.08-.4-.13-.57.13-.17.26-.65.81-.8.98-.15.17-.3.19-.55.06-.25-.13-1.06-.39-2.02-1.25-.75-.67-1.25-1.5-1.4-1.75-.15-.25-.02-.38.11-.51.11-.11.25-.29.38-.44.13-.15.17-.26.25-.43.09-.17.04-.32-.02-.45-.06-.13-.57-1.37-.78-1.87-.21-.5-.4-.42-.57-.42-.15 0-.33-.01-.5-.01-.17 0-.45.07-.69.33-.24.26-.92.9-.92 2.2 0 1.3.95 2.55 1.08 2.73.13.18 1.86 2.85 4.51 3.98.63.27 1.12.43 1.5.55.63.2 1.2.17 1.66.1.51-.07 1.57-.64 1.79-1.26.22-.62.22-1.15.15-1.26-.07-.11-.26-.18-.51-.31z"/>
            </svg>
        </a>
    </div>
</section>

<!-- WHATSAPP FLOTANTE -->
<a href="https://wa.me/522218203396?text=Hola%2C%20me%20gustar%C3%ADa%20agendar%20una%20cita%20para%20mi%20mascota." class="whatsapp-float" target="_blank">
    <i class="fa-brands fa-whatsapp" style="font-size: 24px;"></i> Agendar cita
</a>

<!-- FOOTER -->
<footer class="footer-biospet">
    <div class="footer-logo">
        <img src="assets/images/logo_biospet_inverso_2.png" alt="BIOSPET">
    </div>
    <div class="footer-info">
        <h4>BIOSPET</h4>
        <p>Diagnóstico veterinario especializado para mascotas</p>
        <p>© <?php echo date('Y'); ?> BIOSPET - Todos los derechos reservados</p>
    </div>
    <div class="footer-redes">
        <a href="https://www.instagram.com/biospet_puebla" target="_blank">
            <img src="assets/images/instagram_sfondo.png" alt="Instagram">
        </a>
        <a href="https://www.facebook.com/share/1B46s8Hz3g" target="_blank">
            <img src="assets/images/facebook_sfondo.png" alt="Facebook">
        </a>
    </div>
</footer>

<script src="assets/js/home.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>