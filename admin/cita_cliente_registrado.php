<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol para acceso
if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'veterinario', 'asistente', 'recepcionista', 'caja'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener lista de clientes activos
$sql_clientes = "SELECT id, CONCAT(nombre, ' ', IFNULL(ape_pat, ''), ' ', IFNULL(ape_mat, '')) as nombre_completo, telefono 
                 FROM CLIENTE 
                 WHERE activo = 1 
                 ORDER BY nombre ASC";
$clientes = $conn->query($sql_clientes);

// Obtener servicios disponibles
$sql_servicios = "SELECT id, nombre_servicio, precio FROM SERVICIO WHERE activo = 1 ORDER BY nombre_servicio";
$servicios = $conn->query($sql_servicios);

// Si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_cliente = (int)$_POST['id_cliente'];
    $mascotas_nuevas = $_POST['mascotas_nuevas'] ?? [];
    $mascotas_existentes = $_POST['mascotas_existentes'] ?? [];
    $fecha_cita = $_POST['fecha_cita'];
    $hora_cita = $_POST['hora_cita'];
    $notas = $_POST['notas'] ?? '';
    $servicios_seleccionados = $_POST['servicios'] ?? [];
    $origen = $_POST['origen'] ?? 'Presencial';
    
    // Validar datos mínimos
    $errores = [];
    if (!$id_cliente) $errores[] = "Seleccione un cliente";
    if (!$fecha_cita) $errores[] = "Fecha de cita es requerida";
    if (!$hora_cita) $errores[] = "Hora de cita es requerida";
    
    // Contar mascotas
    $total_mascotas = count($mascotas_nuevas) + count($mascotas_existentes);
    if ($total_mascotas == 0) $errores[] = "Debe agregar al menos una mascota";
    
    if (empty($errores)) {
        try {
            $conn->begin_transaction();
            $ids_citas = [];
            $mascotas_procesadas = [];
            
            // 1. Procesar mascotas NUEVAS
            foreach ($mascotas_nuevas as $idx => $mascota) {
                $nombre_mascota = trim($mascota['nombre'] ?? '');
                $especie = $mascota['especie'] ?? 'Canino';
                $raza = trim($mascota['raza'] ?? '');
                $genero = $mascota['genero'] ?? null;
                $fecha_nacimiento = !empty($mascota['fecha_nacimiento']) ? $mascota['fecha_nacimiento'] : null;
                
                if (!$nombre_mascota) continue;
                
                // Insertar mascota
                $sql_mascota = "INSERT INTO MASCOTA (id_cliente, nombre_mascota, especie, raza, genero, fecha_nacimiento, activo) 
                               VALUES (?, ?, ?, ?, ?, ?, 1)";
                $stmt_mascota = $conn->prepare($sql_mascota);
                $stmt_mascota->bind_param("isssss", $id_cliente, $nombre_mascota, $especie, $raza, $genero, $fecha_nacimiento);
                $stmt_mascota->execute();
                $id_mascota = $conn->insert_id;
                
                // Crear cita
                $sql_cita = "INSERT INTO CITA (fecha_cita, hora_cita, id_mascota, estado, notas, origen) 
                            VALUES (?, ?, ?, 'pendiente', ?, ?)";
                $stmt_cita = $conn->prepare($sql_cita);
                $stmt_cita->bind_param("ssiss", $fecha_cita, $hora_cita, $id_mascota, $notas, $origen);
                $stmt_cita->execute();
                $id_cita = $conn->insert_id;
                $ids_citas[] = $id_cita;
                $mascotas_procesadas[] = $nombre_mascota;
            }
            
            // 2. Procesar mascotas EXISTENTES
            foreach ($mascotas_existentes as $idx => $mascota) {
                $id_mascota = (int)($mascota['id'] ?? 0);
                if (!$id_mascota) continue;
                
                // Crear cita para mascota existente
                $sql_cita = "INSERT INTO CITA (fecha_cita, hora_cita, id_mascota, estado, notas, origen) 
                            VALUES (?, ?, ?, 'pendiente', ?, ?)";
                $stmt_cita = $conn->prepare($sql_cita);
                $stmt_cita->bind_param("ssiss", $fecha_cita, $hora_cita, $id_mascota, $notas, $origen);
                $stmt_cita->execute();
                $id_cita = $conn->insert_id;
                $ids_citas[] = $id_cita;
                
                // Obtener nombre de la mascota
                $sql_nombre = "SELECT nombre_mascota FROM MASCOTA WHERE id = ?";
                $stmt_nombre = $conn->prepare($sql_nombre);
                $stmt_nombre->bind_param("i", $id_mascota);
                $stmt_nombre->execute();
                $result_nombre = $stmt_nombre->get_result();
                $mascotas_procesadas[] = $result_nombre->fetch_assoc()['nombre_mascota'];
            }
            
            // 3. Agregar servicios a TODAS las citas
            if (!empty($servicios_seleccionados) && !empty($ids_citas)) {
                foreach ($ids_citas as $id_cita) {
                    $sql_detalle = "INSERT INTO DETALLE_CITA (id_cita, id_servicio, precio_fijado) 
                                   SELECT ?, id, precio FROM SERVICIO WHERE id = ?";
                    $stmt_detalle = $conn->prepare($sql_detalle);
                    foreach ($servicios_seleccionados as $id_servicio) {
                        $stmt_detalle->bind_param("ii", $id_cita, $id_servicio);
                        $stmt_detalle->execute();
                    }
                }
            }
            
            $conn->commit();
            
            $total_citas = count($ids_citas);
            $mensaje_mascotas = implode(', ', $mascotas_procesadas);
            $_SESSION['mensaje'] = "✅ $total_citas cita(s) agendada(s) exitosamente para: $mensaje_mascotas";
            header("Location: dashboard.php");
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $errores[] = "Error al guardar: " . $e->getMessage();
        }
    }
    
    if (!empty($errores)) {
        $_SESSION['error'] = implode(", ", $errores);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cita para Cliente Registrado - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        .form-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .form-title {
            color: #E68D0B;
            margin-bottom: 25px;
            text-align: center;
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
            color: #333;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .info-cliente {
            background: #e8f0fe;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        .info-cliente.visible {
            display: block;
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
        .servicio-item {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        .servicio-item input {
            width: auto;
        }
        .servicio-item .nombre {
            flex: 1;
        }
        .servicio-item .precio {
            color: #E68D0B;
            font-weight: bold;
        }
        .total-preview {
            margin-top: 15px;
            padding: 12px;
            background: #e8f0fe;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .btn-submit {
            background: #E68D0B;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            margin-top: 20px;
        }
        .btn-submit:hover {
            background: #d47a0a;
        }
        .mascota-card {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #eee;
        }
        .mascota-card h4 {
            color: #E68D0B;
            margin-bottom: 15px;
            font-size: 1.1rem;
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
        hr {
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Cita para Cliente Registrado</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="clientes.php">👥 Clientes</a>
            <a href="citas.php">📅 Agendar Online</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="form-container">
        <h2 class="form-title">📝 Agendar Cita(s) para Cliente Existente</h2>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert-error">
                ❌ <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <!-- Selección de Cliente -->
            <div class="form-group full-width">
                <label>👤 Seleccionar Cliente *</label>
                <select name="id_cliente" id="id_cliente" required style="padding: 12px; font-size: 14px;">
                    <option value="">-- Seleccione un cliente --</option>
                    <?php 
                    $clientes->data_seek(0);
                    while($cliente = $clientes->fetch_assoc()): ?>
                        <option value="<?php echo $cliente['id']; ?>">
                            <?php echo htmlspecialchars($cliente['nombre_completo']); ?> - 📞 <?php echo $cliente['telefono']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <!-- Información del cliente seleccionado -->
            <div id="infoCliente" class="info-cliente">
                <strong>✓ Cliente seleccionado</strong><br>
                <span id="clienteInfo"></span>
            </div>
            
            <!-- Contenedor para mostrar mascotas existentes -->
            <div id="mascotas-existentes-container" class="form-group full-width" style="display:none;">
                <div style="background: #e8f0fe; padding: 12px; border-radius: 8px;">
                    <strong>📋 Mascotas registradas de este cliente:</strong>
                    <div id="lista-mascotas-existentes"></div>
                </div>
            </div>
            
            <hr>
            
            <h3 style="color: #E68D0B; margin-bottom: 20px;">🐕 Mascotas a agendar</h3>
            
            <!-- Contenedor dinámico para múltiples mascotas -->
            <div id="mascotas-container">
                <div class="mascota-card" data-idx="0">
                    <h4>🐕 Mascota #1</h4>
                    
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
                            <label>Fecha de nacimiento</label>
                            <input type="date" name="mascotas_nuevas[0][fecha_nacimiento]" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <!-- Selector para mascota EXISTENTE -->
                    <div id="mascota-existente-0" class="mascota-existente-form" style="display:none;">
                        <div class="form-group">
                            <label>Seleccionar mascota</label>
                            <select name="mascotas_existentes[0][id]" class="select-mascota-existente" data-idx="0">
                                <option value="">-- Primero selecciona un cliente --</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="button" class="btn-remove-mascota" onclick="eliminarMascota(this)" style="display:none;">❌ Eliminar mascota</button>
                </div>
            </div>
            
            <button type="button" class="btn-agregar-mascota" onclick="agregarMascota()">➕ Agregar otra mascota</button>
            
            <hr>
            
            <h3 style="color: #E68D0B; margin-bottom: 20px;">📅 Detalles de la Cita</h3>
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Fecha *</label>
                    <input type="date" name="fecha_cita" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label>Hora *</label>
                    <input type="time" name="hora_cita" required>
                </div>
                
                <div class="form-group full-width">
                    <label>Motivo / Síntomas</label>
                    <textarea name="notas" rows="3" placeholder="Describe los síntomas o motivo de la consulta..."></textarea>
                </div>
                
                <div class="form-group full-width">
                    <label>🩺 Servicios que requiere</label>
                    <div class="servicios-grid">
                        <?php 
                        $servicios->data_seek(0);
                        while($servicio = $servicios->fetch_assoc()): ?>
                            <label class="servicio-item">
                                <input type="checkbox" name="servicios[]" value="<?php echo $servicio['id']; ?>" 
                                       data-precio="<?php echo $servicio['precio']; ?>" onchange="actualizarTotal()">
                                <span class="nombre"><?php echo htmlspecialchars($servicio['nombre_servicio']); ?></span>
                                <span class="precio">$<?php echo number_format($servicio['precio'], 2); ?></span>
                            </label>
                        <?php endwhile; ?>
                    </div>
                    <div id="totalPreview" class="total-preview">
                        💰 Total estimado: $0.00
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label>📱 Origen de la cita</label>
                    <select name="origen">
                        <option value="Presencial">🏥 Presencial</option>
                        <option value="Whatsapp">💬 WhatsApp</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn-submit">✅ Agendar Cita(s)</button>
        </form>
    </div>
    
    <script>
    let contadorMascotas = 1;
    let mascotasDelCliente = [];
    
    // Mostrar información del cliente seleccionado
    const clienteSelect = document.getElementById('id_cliente');
    const infoClienteDiv = document.getElementById('infoCliente');
    const clienteInfoSpan = document.getElementById('clienteInfo');
    
    if (clienteSelect) {
        clienteSelect.addEventListener('change', function() {
            if (this.value) {
                const selectedOption = this.options[this.selectedIndex];
                clienteInfoSpan.innerHTML = selectedOption.text;
                infoClienteDiv.classList.add('visible');
                cargarMascotasDelCliente(this.value);
            } else {
                infoClienteDiv.classList.remove('visible');
                document.getElementById('mascotas-existentes-container').style.display = 'none';
            }
        });
    }
    
    function cargarMascotasDelCliente(clienteId) {
        fetch(`controllers/buscar_mascotas_por_cliente.php?cliente_id=${clienteId}`)
            .then(response => response.json())
            .then(data => {
                mascotasDelCliente = data;
                const container = document.getElementById('mascotas-existentes-container');
                const listaDiv = document.getElementById('lista-mascotas-existentes');
                
                if (container && listaDiv) {
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
                        listaDiv.innerHTML = '<div style="color: #856404;">⚠️ Este cliente no tiene mascotas registradas. Debes agregar una nueva.</div>';
                        container.style.display = 'block';
                    }
                }
            })
            .catch(error => console.error('Error:', error));
    }
    
    function actualizarSelectMascotas(select, mascotas) {
        if (!select) return;
        select.innerHTML = '<option value="">-- Seleccionar mascota --</option>';
        if(mascotas && mascotas.length) {
            mascotas.forEach(m => {
                select.innerHTML += `<option value="${m.id}">${m.nombre_mascota} (${m.especie})</option>`;
            });
        }
    }
    
    function toggleMascotaForm(idx, tipo) {
        const nuevaForm = document.getElementById(`mascota-nueva-${idx}`);
        const existenteForm = document.getElementById(`mascota-existente-${idx}`);
        
        if (!nuevaForm || !existenteForm) {
            console.error('No se encontraron los formularios para idx:', idx);
            return;
        }
        
        if(tipo === 'nueva') {
            nuevaForm.style.display = 'block';
            existenteForm.style.display = 'none';
            
            const select = document.querySelector(`select[name="mascotas_existentes[${idx}][id]"]`);
            if(select) select.removeAttribute('required');
            
            const nombreInput = document.querySelector(`input[name="mascotas_nuevas[${idx}][nombre]"]`);
            if(nombreInput) nombreInput.setAttribute('required', 'required');
        } else {
            nuevaForm.style.display = 'none';
            existenteForm.style.display = 'block';
            
            const select = document.querySelector(`select[name="mascotas_existentes[${idx}][id]"]`);
            if(select) select.setAttribute('required', 'required');
            
            const nombreInput = document.querySelector(`input[name="mascotas_nuevas[${idx}][nombre]"]`);
            if(nombreInput) nombreInput.removeAttribute('required');
            
            if(mascotasDelCliente.length) {
                actualizarSelectMascotas(select, mascotasDelCliente);
            }
        }
    }
    
    function agregarMascota() {
        console.log('agregarMascota ejecutada'); // Debug
        
        const container = document.getElementById('mascotas-container');
        if (!container) {
            console.error('No se encontró el contenedor mascotas-container');
            return;
        }
        
        // Obtener el primer elemento como template
        const primerMascota = document.querySelector('.mascota-card');
        if (!primerMascota) {
            console.error('No se encontró el template .mascota-card');
            return;
        }
        
        // Clonar profundamente
        const template = primerMascota.cloneNode(true);
        const nuevoIdx = contadorMascotas;
        
        console.log('Creando mascota con índice:', nuevoIdx);
        
        // Actualizar data-idx
        template.setAttribute('data-idx', nuevoIdx);
        
        // Actualizar título
        const titulo = template.querySelector('h4');
        if(titulo) titulo.textContent = `🐕 Mascota #${nuevoIdx + 1}`;
        
        // Actualizar el select de tipo (mascota_tipo)
        const selectTipo = template.querySelector('select[name*="mascota_tipo"]');
        if(selectTipo) {
            const nuevoName = `mascota_tipo[${nuevoIdx}]`;
            selectTipo.setAttribute('name', nuevoName);
            selectTipo.setAttribute('onchange', `toggleMascotaForm(${nuevoIdx}, this.value)`);
            selectTipo.value = 'nueva'; // Resetear a nueva
        }
        
        // Actualizar todos los inputs con name que contengan [0]
        template.querySelectorAll('[name]').forEach(el => {
            const name = el.getAttribute('name');
            if (name && name.includes('[0]')) {
                const nuevoName = name.replace('[0]', `[${nuevoIdx}]`);
                el.setAttribute('name', nuevoName);
            }
            // Limpiar valores de inputs (excepto checkbox, radio, file)
            if (el.tagName === 'INPUT') {
                if (el.type !== 'checkbox' && el.type !== 'radio' && el.type !== 'file') {
                    el.value = '';
                }
            }
            if (el.tagName === 'SELECT' && el !== selectTipo) {
                el.value = '';
            }
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
                selectExistente.innerHTML = '<option value="">-- Primero selecciona un cliente --</option>';
            }
        }
        
        // Mostrar botón eliminar
        const btnRemove = template.querySelector('.btn-remove-mascota');
        if(btnRemove) {
            btnRemove.style.display = 'inline-block';
            btnRemove.setAttribute('onclick', 'eliminarMascota(this)');
        }
        
        // Agregar al contenedor
        container.appendChild(template);
        contadorMascotas++;
        
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
    
    function actualizarTotal() {
        let total = 0;
        document.querySelectorAll('input[name="servicios[]"]:checked').forEach(chk => {
            total += parseFloat(chk.dataset.precio);
        });
        const totalPreview = document.getElementById('totalPreview');
        if (totalPreview) {
            totalPreview.innerHTML = `💰 Total estimado por cita: $${total.toFixed(2)} MXN`;
        }
    }
    
    // Inicializar al cargar la página
    document.addEventListener('DOMContentLoaded', () => {
        console.log('DOM cargado, inicializando...');
        
        // Inicializar la primera mascota
        const primerSelectTipo = document.querySelector('select[name="mascota_tipo[0]"]');
        if (primerSelectTipo) {
            primerSelectTipo.value = 'nueva';
            toggleMascotaForm(0, 'nueva');
        }
        
        // Si ya hay un cliente seleccionado (por ejemplo después de un error)
        if (clienteSelect && clienteSelect.value) {
            cargarMascotasDelCliente(clienteSelect.value);
        }
    });
</script>
</body>
</html>