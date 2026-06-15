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
    <style>
        .form-cita {
            max-width: 900px;
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
        .mascota-card {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #eee;
        }
        .mascota-card h3 {
            color: #E68D0B;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        .btn-agregar-mascota {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin: 10px 0 20px;
            font-size: 14px;
        }
        .btn-agregar-mascota:hover {
            background: #5a6268;
        }
        .btn-remove-mascota {
            background: #dc3545;
            color: white;
            padding: 5px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
            font-size: 12px;
        }
        .btn-remove-mascota:hover {
            background: #c82333;
        }
        .mascota-existente-form, .mascota-nueva-form {
            transition: all 0.3s ease;
        }
        .alert-info {
            background: #e8f0fe;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
            color: #004085;
        }
        .servicios-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 8px;
            background: #f9f9f9;
            margin-top: 10px;
        }
        .servicio-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        .servicio-checkbox input {
            width: auto;
        }
        .servicio-checkbox.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .servicio-checkbox.disabled input {
            cursor: not-allowed;
        }
        .servicios-por-mascota {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #ddd;
        }
        .servicios-por-mascota label:first-child {
            font-weight: 600;
            margin-bottom: 10px;
            display: block;
            color: #E68D0B;
        }
        .btn {
            background: #E68D0B;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #d47a0a;
        }
        .total-mascota {
            text-align: right;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-weight: bold;
            color: #E68D0B;
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
            
            <form action="controllers/CitaController.php" method="POST" class="form-cita" enctype="multipart/form-data" id="form-cita">
                <div class="form-grid">
                    <h3 style="grid-column: span 2; color: var(--primary);">Datos del Dueño</h3>
                    
                    <div class="form-group">
                        <label>Nombre(s) *</label>
                        <input type="text" name="nombre_dueno" id="nombre_dueno" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Apellido Paterno</label>
                        <input type="text" name="ape_pat" id="ape_pat">
                    </div>
                    
                    <div class="form-group">
                        <label>Apellido Materno</label>
                        <input type="text" name="ape_mat" id="ape_mat">
                    </div>
                    
                    <div class="form-group">
                        <label>Teléfono *</label>
                        <input type="tel" name="telefono" id="telefono" required onblur="buscarMascotasPorTelefono()">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Email</label>
                        <input type="email" name="email" id="email">
                    </div>

                    <div class="form-group full-width">
                        <label>Dirección</label>
                        <input type="text" name="direccion" id="direccion" placeholder="Calle, número, colonia, ciudad, código postal">
                    </div>

                    <!-- Contenedor para mostrar mascotas existentes -->
                    <div id="mascotas-existentes-container" class="form-group full-width" style="display:none;">
                        <div class="alert-info">
                            <strong>📋 Mascotas registradas con este teléfono:</strong>
                            <div id="lista-mascotas-existentes"></div>
                        </div>
                    </div>

                    <h3 style="grid-column: span 2; color: var(--primary); margin-top: 20px;">🐕 Mascotas a agendar</h3>
                    
                    <!-- Contenedor dinámico para múltiples mascotas -->
                    <div id="mascotas-container" class="full-width" style="grid-column: span 2;">
                        <!-- Mascota #1 (template) -->
                        <div class="mascota-card" data-idx="0">
                            <h3>🐕 Mascota #1</h3>
                            
                            <div class="form-group">
                                <label>¿Es una mascota nueva o ya registrada?</label>
                                <select name="mascota_tipo[0]" onchange="toggleMascotaForm(0, this.value)">
                                    <option value="nueva">➕ Nueva mascota</option>
                                    <option value="existente">📋 Seleccionar existente</option>
                                </select>
                            </div>
                            
                            <!-- Formulario para mascota NUEVA -->
                            <div id="mascota-nueva-0" class="mascota-nueva-form">
                                <div class="form-group">
                                    <label>Nombre de la mascota *</label>
                                    <input type="text" name="mascotas_nuevas[0][nombre]" required>
                                </div>
                                <div class="form-group">
                                    <label>Especie *</label>
                                    <select name="mascotas_nuevas[0][especie]" class="especie-select" data-idx="0" required>
                                        <option value="Canino">Perro</option>
                                        <option value="Felino">Gato</option>
                                        <option value="Otro">Otro</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Raza</label>
                                    <input type="text" name="mascotas_nuevas[0][raza]">
                                </div>
                                <div class="form-group">
                                    <label>Género</label>
                                    <select name="mascotas_nuevas[0][genero]">
                                        <option value="">Seleccione...</option>
                                        <option value="MACHO">Macho</option>
                                        <option value="HEMBRA">Hembra</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Foto (opcional)</label>
                                    <input type="file" name="mascotas_fotos[0]" accept="image/jpeg,image/png,image/jpg">
                                </div>
                            </div>
                            
                            <!-- Selector para mascota EXISTENTE -->
                            <div id="mascota-existente-0" class="mascota-existente-form" style="display:none;">
                                <div class="form-group">
                                    <label>Seleccionar mascota</label>
                                    <select name="mascotas_existentes[0][id]" class="select-mascota-existente" data-idx="0">
                                        <option value="">-- Primero ingresa un teléfono --</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Especie</label>
                                    <input type="text" class="especie-mostrada" readonly style="background:#f0f0f0;">
                                </div>
                            </div>
                            
                            <!-- SERVICIOS PARA ESTA MASCOTA -->
                            <div class="servicios-por-mascota">
                                <label>🩺 Servicios para esta mascota:</label>
                                <div class="servicios-grid" data-idx="0">
                                    <?php
                                    $sql_servicios = "SELECT id, nombre_servicio, precio FROM SERVICIO WHERE activo = 1 ORDER BY nombre_servicio";
                                    $result_servicios = $conn->query($sql_servicios);
                                    while($servicio = $result_servicios->fetch_assoc()):
                                    ?>
                                        <label class="servicio-checkbox" data-especie="ambos">
                                            <input type="checkbox" 
                                                   name="servicios_por_mascota[0][<?php echo $servicio['id']; ?>]" 
                                                   value="<?php echo $servicio['id']; ?>"
                                                   data-precio="<?php echo $servicio['precio']; ?>"
                                                   data-nombre="<?php echo htmlspecialchars($servicio['nombre_servicio']); ?>">
                                            <span><?php echo htmlspecialchars($servicio['nombre_servicio']); ?></span>
                                            <strong>$<?php echo number_format($servicio['precio'], 2); ?></strong>
                                        </label>
                                    <?php endwhile; ?>
                                </div>
                                <div class="total-mascota" id="total-mascota-0">💰 Total: $0.00</div>
                            </div>
                            
                            <button type="button" class="btn-remove-mascota" onclick="eliminarMascota(this)" style="display:none;">❌ Eliminar mascota</button>
                        </div>
                    </div>
                    
                    <button type="button" class="btn-agregar-mascota" onclick="agregarMascota()">➕ Agregar otra mascota</button>

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
                </div>

                <div class="form-group full-width">
                    <label>¿Cómo te gustaría agendar?</label>
                    <select name="origen" required>
                        <option value="web">📱 Por WhatsApp</option>
                        <option value="presencial">🏥 Directamente en la clínica</option>
                    </select>
                </div>
                
                <button type="submit" class="btn" style="width: 100%; margin-top: 20px;">Solicitar Cita(s)</button>
            </form>
            <p style="text-align: center; margin-top: 20px; color: #666; font-size: 14px;">
                Una vez enviada tu solicitud, nos comunicaremos contigo para confirmar la(s) cita(s).
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
    <script src = "../assets/js/citas.js"></script>
</body>
</html>