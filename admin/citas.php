<?php
// public/citas.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/conexion.php'; 

if (!$conn) {
    die("Error crítico: No se pudo conectar a la base de datos.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendar Cita - BIOSPET</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/estilo.css">
    <script src="../assets/js/citas.js"></script>
    <style>
        .form-cita {
            max-width: 700px;
            margin: 40px auto;
            padding: 30px;
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group.full-width {
            grid-column: span 2;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--black);
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--radius-sm);
            font-family: var(--font-main);
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
        }
        .preview-img {
            max-width: 150px;
            max-height: 150px;
            border-radius: 10px;
            margin-top: 10px;
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
                <a href="#contacto">Contacto</a>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <h1 style="text-align: center; margin: 40px 0;">Agendar Cita</h1>
            
            <form action="controllers/CitaController.php" method="POST" class="form-cita" enctype="multipart/form-data">
                <div class="form-grid">
                    <h3 style="grid-column: span 2; color: var(--primary);">Datos del Dueño</h3>
                    
                    <div class="form-group">
                        <label>Nombre(s) *</label>
                        <input type="text" name="nombre_dueno" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Apellido Paterno</label>
                        <input type="text" name="ape_pat">
                    </div>
                    
                    <div class="form-group">
                        <label>Apellido Materno</label>
                        <input type="text" name="ape_mat">
                    </div>
                    
                    <div class="form-group">
                        <label>Teléfono *</label>
                        <input type="tel" name="telefono" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Email</label>
                        <input type="email" name="email">
                    </div>

                    <div class="form-group full-width">
                        <label>Dirección</label>
                        <input type="text" name="direccion" placeholder="Calle, número, colonia, ciudad, código postal">
                    </div>

                    <h3 style="grid-column: span 2; color: var(--primary); margin-top: 20px;">Datos de la Mascota</h3>
                    
                    <div class="form-group full-width">
                        <label>Nombre de la Mascota *</label>
                        <input type="text" name="nombre_mascota" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Especie *</label>
                        <select name="especie" required>
                            <option value="">Seleccione...</option>
                            <option value="Canino">Perro</option>
                            <option value="Felino">Gato</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Raza</label>
                        <input type="text" name="raza">
                    </div>

                    
                    <div class="form-group">
                        <label>Fecha de Nacimiento</label>
                        <input type="date" name="fecha_nacimiento" max="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Género</label>
                        <select name="genero">
                            <option value="">Seleccione...</option>
                            <option value="MACHO">Macho</option>
                            <option value="HEMBRA">Hembra</option>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label>Foto de la mascota (opcional)</label>
                        <input type="file" name="foto" accept="image/jpeg,image/png,image/jpg" id="foto">
                        <small style="color:#666;">Formatos: JPG, PNG. Tamaño máximo: 2MB</small>
                        <div id="preview" style="margin-top: 10px;"></div>
                    </div>

                    <h3 style="grid-column: span 2; color: var(--primary); margin-top: 20px;">Detalles de la Cita</h3>
                    
                    <div class="form-group">
                        <label>Fecha *</label>
                        <input type="date" name="fecha_cita" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Hora *</label>
                        <input type="time" name="hora_cita" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Motivo de consulta / Síntomas</label>
                        <textarea name="notas" rows="4" placeholder="Describe los síntomas que presenta tu mascota, desde cuándo, y cualquier detalle importante para el veterinario."></textarea>
                    </div>

                    <!-- Selección de servicios con checkboxes -->
                    <div class="form-group full-width">
                        <label>🩺 Servicios que deseas</label>
                        <div class="servicios-grid">
                            <?php
                            $sql_servicios = "SELECT id, nombre_servicio, precio FROM SERVICIO WHERE activo = 1 ORDER BY nombre_servicio";
                            $result_servicios = $conn->query($sql_servicios);
                            while($servicio = $result_servicios->fetch_assoc()):
                                ?>
                                <label class="servicio-checkbox">
                                    <input type="checkbox" name="servicios[]" value="<?php echo $servicio['id']; ?>"
                                        class="chk-servicio" data-precio="<?php echo $servicio['precio']; ?>">
                                    <span><?php echo htmlspecialchars($servicio['nombre_servicio']); ?></span>
                                    <strong>$<?php echo number_format($servicio['precio'], 2); ?></strong>
                                </label>
                            <?php endwhile; ?>
                        </div>
                        <div id="precio-info" class="precio-info">
                            Seleccione uno o más servicios
                        </div>
                    </div>
                </div>

                <!--------DIFERENCIA DE CLIENTES: EN LINEA Y ORESENCIAL----->
                <div class="form-group full-width">
                    <label>¿Cómo te gustaría agendar?</label>
                    <select name="origen" required>
                        <option value="Whatsapp">📱 Por Whatssap</option>
                        <option value="Presencial">🏥 Directamente en la clínica</option>
                    </select>
                </div>
                
                <button type="submit" class="btn" style="width: 100%; margin-top: 20px;">Solicitar Cita</button>
            </form>
            <p style="text-align: center; margin-top: 20px; color: #666; font-size: 14px;">
                Una vez enviada tu solicitud, nos comunicaremos contigo para confirmar la cita.
            </p>
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
        // Vista previa de la imagen
        document.getElementById('foto').addEventListener('change', function(e) {
            const preview = document.getElementById('preview');
            preview.innerHTML = '';
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const img = document.createElement('img');
                    img.src = event.target.result;
                    img.classList.add('preview-img');
                    preview.appendChild(img);
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>