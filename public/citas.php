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
        .precio-info {
            margin-top: 10px;
            padding: 10px;
            background: #e8f0fe;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
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
                                    <select name="mascotas_nuevas[0][especie]" required>
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
                                    <input type="checkbox" name="servicios[]" value="<?php echo $servicio['id']; ?>">
                                    <span><?php echo htmlspecialchars($servicio['nombre_servicio']); ?></span>
                                    <strong>$<?php echo number_format($servicio['precio'], 2); ?></strong>
                                </label>
                            <?php endwhile; ?>
                        </div>
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

    <script>
        let contadorMascotas = 1;
        let mascotasDelCliente = [];

        function buscarMascotasPorTelefono() {
            const telefono = document.getElementById('telefono').value;
            if(telefono.length < 10) return;
            
            fetch(`controllers/buscar_mascotas_ajax.php?telefono=${telefono}`)
                .then(response => response.json())
                .then(data => {
                    mascotasDelCliente = data;
                    const container = document.getElementById('mascotas-existentes-container');
                    const listaDiv = document.getElementById('lista-mascotas-existentes');
                    
                    if(data.length > 0) {
                        listaDiv.innerHTML = '';
                        data.forEach(m => {
                            listaDiv.innerHTML += `
                                <div style="margin: 5px 0;">
                                    🐕 <strong>${m.nombre_mascota}</strong> - ${m.especie} ${m.raza ? '- ' + m.raza : ''}
                                </div>
                            `;
                        });
                        container.style.display = 'block';
                        
                        // Actualizar todos los selects de mascotas existentes
                        document.querySelectorAll('.select-mascota-existente').forEach(select => {
                            actualizarSelectMascotas(select, data);
                        });
                    } else {
                        container.style.display = 'none';
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function actualizarSelectMascotas(select, mascotas) {
            select.innerHTML = '<option value="">-- Seleccionar mascota --</option>';
            if(mascotas && mascotas.length) {
                mascotas.forEach(m => {
                    select.innerHTML += `<option value="${m.id}">${m.nombre_mascota} (${m.especie})</option>`;
                });
            } else {
                select.innerHTML += '<option value="" disabled>No hay mascotas registradas con este teléfono</option>';
            }
        }

        function toggleMascotaForm(idx, tipo) {
    const nuevaForm = document.getElementById(`mascota-nueva-${idx}`);
    const existenteForm = document.getElementById(`mascota-existente-${idx}`);
    
    console.log('toggleMascotaForm llamado:', idx, tipo, nuevaForm, existenteForm); // Debug
    
    if(!nuevaForm || !existenteForm) {
        console.error('No se encontraron los formularios para idx:', idx);
        return;
    }
    
    if(tipo === 'nueva') {
        nuevaForm.style.display = 'block';
        existenteForm.style.display = 'none';
        
        // Remover required de existente
        const select = document.querySelector(`select[name="mascotas_existentes[${idx}][id]"]`);
        if(select) select.removeAttribute('required');
        
        // Agregar required a nuevos
        const nombreInput = document.querySelector(`input[name="mascotas_nuevas[${idx}][nombre]"]`);
        if(nombreInput) nombreInput.setAttribute('required', 'required');
    } else {
        nuevaForm.style.display = 'none';
        existenteForm.style.display = 'block';
        
        // Agregar required a existente
        const select = document.querySelector(`select[name="mascotas_existentes[${idx}][id]"]`);
        if(select) select.setAttribute('required', 'required');
        
        // Remover required de nuevos
        const nombreInput = document.querySelector(`input[name="mascotas_nuevas[${idx}][nombre]"]`);
        if(nombreInput) nombreInput.removeAttribute('required');
        
        // Cargar mascotas si ya tenemos datos
        if(mascotasDelCliente.length) {
            actualizarSelectMascotas(select, mascotasDelCliente);
        }
    }
}

        function agregarMascota() {
    const container = document.getElementById('mascotas-container');
    const template = document.querySelector('.mascota-card').cloneNode(true);
    const nuevoIdx = contadorMascotas;
    
    // Actualizar índice
    template.setAttribute('data-idx', nuevoIdx);
    
    // Actualizar título
    const titulo = template.querySelector('h3');
    if(titulo) titulo.textContent = `🐕 Mascota #${nuevoIdx + 1}`;
    
    // Actualizar el name del select de tipo y su onchange
    const selectTipo = template.querySelector('select[name*="mascota_tipo"]');
    if(selectTipo) {
        const nuevoName = `mascota_tipo[${nuevoIdx}]`;
        selectTipo.setAttribute('name', nuevoName);
        selectTipo.setAttribute('onchange', `toggleMascotaForm(${nuevoIdx}, this.value)`);
        selectTipo.value = 'nueva';  // Resetear a "nueva"
    }
    
    // Actualizar todos los inputs con name que contengan [0]
    template.querySelectorAll('[name]').forEach(el => {
        const name = el.getAttribute('name');
        if(name && name.includes('[0]')) {
            const nuevoName = name.replace('[0]', `[${nuevoIdx}]`);
            el.setAttribute('name', nuevoName);
        }
        // Limpiar valores
        if(el.tagName === 'INPUT' && el.type !== 'file') el.value = '';
        if(el.tagName === 'SELECT' && el !== selectTipo) el.value = '';
    });
    
    // Actualizar IDs de los divs de nueva/existente
    const nuevaDiv = template.querySelector('.mascota-nueva-form');
    const existenteDiv = template.querySelector('.mascota-existente-form');
    if(nuevaDiv) {
        nuevaDiv.id = `mascota-nueva-${nuevoIdx}`;
        nuevaDiv.style.display = 'block';
    }
    if(existenteDiv) {
        existenteDiv.id = `mascota-existente-${nuevoIdx}`;
        existenteDiv.style.display = 'none';
    }
    
    // Actualizar el input de nombre de mascota nueva (agregar required)
    const nombreInput = template.querySelector('input[name*="[nombre]"]');
    if(nombreInput) {
        nombreInput.setAttribute('required', 'required');
    }
    
    // Actualizar select de mascotas existentes
    const selectExistente = template.querySelector('.select-mascota-existente');
    if(selectExistente) {
        selectExistente.setAttribute('name', `mascotas_existentes[${nuevoIdx}][id]`);
        selectExistente.setAttribute('data-idx', nuevoIdx);
        if(mascotasDelCliente.length > 0) {
            actualizarSelectMascotas(selectExistente, mascotasDelCliente);
        } else {
            selectExistente.innerHTML = '<option value="">-- Primero ingresa un teléfono --</option>';
        }
    }
    
    // Mostrar botón eliminar
    const btnRemove = template.querySelector('.btn-remove-mascota');
    if(btnRemove) {
        btnRemove.style.display = 'inline-block';
        btnRemove.setAttribute('onclick', 'eliminarMascota(this)');
    }
    
    container.appendChild(template);
    contadorMascotas++;
    
    // Debug: confirmar que se agregó
    console.log('Mascota agregada, total:', contadorMascotas);
}

        function eliminarMascota(btn) {
            const cards = document.querySelectorAll('.mascota-card');
            if(cards.length > 1) {
                btn.closest('.mascota-card').remove();
            } else {
                alert('Debe haber al menos una mascota por cita');
            }
        }

        // Inicializar
        document.addEventListener('DOMContentLoaded', () => {
            toggleMascotaForm(0, 'nueva');
        });
    </script>
</body>
</html>