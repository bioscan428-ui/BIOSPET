<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BIOSPET - Clínica Veterinaria</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <style>
        .hero {
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), 
                        url('assets/images/tomografo.jpg');
            background-size: cover;
            background-position: center;
            height: 600px;
            display: flex;
            align-items: center;
            text-align: center;
            color: var(--white);
        }
        
        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        
        .hero p {
            font-size: 1.3rem;
            margin-bottom: 30px;
            opacity: 0.95;
        }
        
        .servicios {
            padding: 80px 0;
            background: #f8f9fa;
        }
        
        .servicios h2 {
            text-align: center;
            color: var(--primary);
            font-size: 2.5rem;
            margin-bottom: 50px;
        }
        
        .grid-servicios {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }
        
        .servicio-card {
            background: var(--white);
            padding: 40px 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .servicio-card:hover {
            transform: translateY(-10px);
        }
        
        .servicio-card .icono {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        
        .servicio-card h3 {
            color: var(--primary);
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .servicio-card p {
            color: #666;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">🐾 BIOSPET</div>
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

    <footer style="background: var(--black); color: var(--white); padding: 50px 0; margin-top: 50px;">
        <div class="container">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px;">
                <div>
                    <h3 style="color: var(--primary); margin-bottom: 20px;">BIOSPET</h3>
                    <p>Cuidando la salud de tus mascotas con tecnología de punta.</p>
                </div>
                <div>
                    <h3 style="color: var(--primary); margin-bottom: 20px;">Contacto</h3>
                    <p>📞 Tel: (123) 456-7890</p>
                    <p>📧 Email: info@biospet.com</p>
                </div>
                <div>
                    <h3 style="color: var(--primary); margin-bottom: 20px;">Síguenos</h3>
                    <a href="#" style="color: var(--white); text-decoration: none; display: block; margin-bottom: 10px;">📱 Facebook</a>
                    <a href="#" style="color: var(--white); text-decoration: none; display: block;">📷 Instagram</a>
                </div>
            </div>
            <hr style="margin: 30px 0; border-color: #333;">
            <p style="text-align: center;">© <?php echo date('Y'); ?> BIOSPET. Todos los derechos reservados.</p>
        </div>
    </footer>
</body>
</html>