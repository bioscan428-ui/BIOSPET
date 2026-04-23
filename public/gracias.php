<?php
session_start();

// Verificar si hay notificación desde el controlador
$notificacion = $_SESSION['notificacion'] ?? null;
unset($_SESSION['notificacion']);

// Si no hay notificación, crear una por defecto
if (!$notificacion) {
    $notificacion = [
        'tipo' => 'success',
        'titulo' => '¡Cita Agendada!',
        'mensaje' => 'Hemos recibido tu solicitud de cita. En breve nos comunicaremos contigo para confirmar.'
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Cita Agendada! - BIOSPET</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .gracias-container {
            text-align: center;
            padding: 80px 20px;
            max-width: 600px;
            margin: 0 auto;
        }
        .gracias-icono {
            font-size: 5rem;
            margin-bottom: 20px;
        }
        .gracias-titulo {
            color: var(--primary);
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        .gracias-mensaje {
            font-size: 1.2rem;
            margin-bottom: 40px;
            color: #666;
        }
        .loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 999;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #E68A00;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loading p {
            margin-top: 20px;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- Loading inicial -->
    <div class="loading" id="loading">
        <div>
            <div class="spinner"></div>
            <p>Procesando tu solicitud...</p>
        </div>
    </div>

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
                <a href="#contacto">Contacto</a>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="gracias-container" style="display: none;" id="contenido">
                <div class="gracias-icono">🐾</div>
                <h1 class="gracias-titulo">¡Cita Agendada!</h1>
                <p class="gracias-mensaje">Hemos recibido tu solicitud de cita. En breve nos comunicaremos contigo para confirmar.</p>
                <a href="index.php" class="btn">Volver al Inicio</a>
            </div>
        </div>
    </main>

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

    <script>
        // Ocultar loading y mostrar notificación después de 1 segundo
        setTimeout(function() {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('contenido').style.display = 'block';
            
            // Mostrar SweetAlert
            Swal.fire({
                title: '<?php echo $notificacion['titulo']; ?>',
                text: '<?php echo $notificacion['mensaje']; ?>',
                icon: '<?php echo $notificacion['tipo']; ?>',
                confirmButtonColor: '#E68A00',
                confirmButtonText: 'Ir al Inicio',
                showCancelButton: true,
                cancelButtonText: 'Ver mis citas',
                cancelButtonColor: '#666',
                backdrop: true,
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'index.php';
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    window.location.href = 'citas.php';
                }
            });
        }, 1000);
    </script>
</body>
</html>