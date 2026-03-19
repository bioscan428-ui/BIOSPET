<?php
// public/citas.php
require_once __DIR__ . '/../includes/conexion.php';

// Obtener servicios activos para el select
$sql_servicios = "SELECT id, nombre_servicio, precio FROM SERVICIO WHERE activo = 1";
$result_servicios = $conn->query($sql_servicios);
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
        .precio-info {
            background: var(--muted);
            padding: 10px;
            border-radius: var(--radius-sm);
            margin-top: 10px;
            font-size: 1.1rem;
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
            
            <form action="../controllers/CitaController.php" method="POST" class="form-cita">
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
                            <option value="Ave">Ave</option>
                            <option value="Reptil">Reptil</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Raza</label>
                        <input type="text" name="raza">
                    </div>

                    <h3 style="grid-column: span 2; color: var(--primary); margin-top: 20px;">Detalles de la Cita</h3>
                    
                    <div class="form-group">
                        <label>Servicio *</label>
                        <select name="id_servicio" id="servicio" required>
                            <option value="">Seleccione...</option>
                            <?php while($row = $result_servicios->fetch_assoc()): ?>
                                <option value="<?php echo $row['id']; ?>" data-precio="<?php echo $row['precio']; ?>">
                                    <?php echo $row['nombre_servicio']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Fecha *</label>
                        <input type="date" name="fecha_cita" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Hora *</label>
                        <input type="time" name="hora_cita" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Notas adicionales</label>
                        <textarea name="notas" rows="4" placeholder="Indicaciones especiales, síntomas, etc."></textarea>
                    </div>

                    <div class="form-group full-width">
                        <div class="precio-info" id="precio-info">
                            Seleccione un servicio para ver el precio
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn" style="width: 100%; margin-top: 20px;">Confirmar Cita</button>
            </form>
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
        // Mostrar precio del servicio seleccionado
        document.getElementById('servicio').addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const precio = selected.dataset.precio;
            const infoDiv = document.getElementById('precio-info');
            
            if (precio) {
                infoDiv.innerHTML = `💰 Precio del servicio: $${parseFloat(precio).toFixed(2)} MXN`;
            } else {
                infoDiv.innerHTML = 'Seleccione un servicio para ver el precio';
            }
        });
    </script>
</body>
</html>