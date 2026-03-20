<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BIOSPET - Clínica Veterinaria</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/estilo.css">
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
                <a href="#contacto">Contacto</a>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="container">
            <h1>Diagnóstico por Imagen de Alta Precisión</h1>
            <p>Tomografía Computarizada • Rayos X Digital • Ultrasonido Doppler • Electrocardiograma</p>
            <a href="citas.php" class="btn">Agendar Cita</a>
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
                        'icono' => '🖥️'
                    ],
                    [
                        'nombre' => 'Rayos X',
                        'descripcion' => 'Digital directo. Mínima radiación, máxima calidad de imagen.',
                        'icono' => '📡'
                    ],
                    [
                        'nombre' => 'Ultrasonido',
                        'descripcion' => 'Equipo Doppler color. Estudios abdominales y cardíacos.',
                        'icono' => '🔊'
                    ],
                    [
                        'nombre' => 'Electrocardiograma',
                        'descripcion' => 'Monitoreo cardíaco completo con interpretación especializada.',
                        'icono' => '💓'
                    ]
                ];
                
                foreach($servicios as $servicio) {
                    echo '<div class="servicio-card">';
                    echo '<div class="icono">' . $servicio['icono'] . '</div>';
                    echo '<h3>' . $servicio['nombre'] . '</h3>';
                    echo '<p>' . $servicio['descripcion'] . '</p>';
                    echo '</div>';
                }
                ?>
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
                </div>
            </div>
            <hr class="footer-divider">
            <p class="footer-copyright">© <?php echo date('Y'); ?> BIOSPET. Todos los derechos reservados.</p>
        </div>
</footer>
</body>
</html>