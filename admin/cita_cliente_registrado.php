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
if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'veterinario', 'asistente', 'recepcionista'])) {
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
    $nombre_mascota = trim($_POST['nombre_mascota']);
    $especie = $_POST['especie'];
    $raza = trim($_POST['raza']);
    $genero = $_POST['genero'];
    $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
    $fecha_cita = $_POST['fecha_cita'];
    $hora_cita = $_POST['hora_cita'];
    $notas = $_POST['notas'] ?? '';
    $servicios_seleccionados = $_POST['servicios'] ?? [];
    $origen = $_POST['origen'] ?? 'Presencial';
    
    // Validar datos mínimos
    $errores = [];
    if (!$id_cliente) $errores[] = "Seleccione un cliente";
    if (!$nombre_mascota) $errores[] = "Nombre de la mascota es requerido";
    if (!$especie) $errores[] = "Especie es requerida";
    if (!$fecha_cita) $errores[] = "Fecha de cita es requerida";
    if (!$hora_cita) $errores[] = "Hora de cita es requerida";
    
    if (empty($errores)) {
        try {
            // 1. Verificar si la mascota ya existe para este dueño
            $sql_check = "SELECT id FROM MASCOTA WHERE id_cliente = ? AND nombre_mascota = ? AND activo = 1";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("is", $id_cliente, $nombre_mascota);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows > 0) {
                // Mascota ya existe, usarla
                $mascota_existente = $result_check->fetch_assoc();
                $id_mascota = $mascota_existente['id'];
                $mensaje_mascota = " (mascota existente)";
            } else {
                // 2. Registrar nueva mascota
                $sql_mascota = "INSERT INTO MASCOTA (id_cliente, nombre_mascota, especie, raza, genero, fecha_nacimiento, activo) 
                               VALUES (?, ?, ?, ?, ?, ?, 1)";
                $stmt_mascota = $conn->prepare($sql_mascota);
                $stmt_mascota->bind_param("isssss", $id_cliente, $nombre_mascota, $especie, $raza, $genero, $fecha_nacimiento);
                $stmt_mascota->execute();
                $id_mascota = $conn->insert_id;
                $mensaje_mascota = " (mascota nueva)";
            }
            
            // 3. Crear cita
            $sql_cita = "INSERT INTO CITA (fecha_cita, hora_cita, id_mascota, estado, notas, origen) 
                        VALUES (?, ?, ?, 'pendiente', ?, ?)";
            $stmt_cita = $conn->prepare($sql_cita);
            $stmt_cita->bind_param("ssiss", $fecha_cita, $hora_cita, $id_mascota, $notas, $origen);
            $stmt_cita->execute();
            $id_cita = $conn->insert_id;
            
            // 4. Agregar servicios
            if (!empty($servicios_seleccionados)) {
                $sql_detalle = "INSERT INTO DETALLE_CITA (id_cita, id_servicio, precio_fijado) 
                               SELECT ?, id, precio FROM SERVICIO WHERE id = ?";
                $stmt_detalle = $conn->prepare($sql_detalle);
                foreach ($servicios_seleccionados as $id_servicio) {
                    $stmt_detalle->bind_param("ii", $id_cita, $id_servicio);
                    $stmt_detalle->execute();
                }
            }
            
            $_SESSION['mensaje'] = "✅ Cita agendada exitosamente para {$nombre_mascota}{$mensaje_mascota}";
            header("Location: dashboard.php");
            exit;
            
        } catch (Exception $e) {
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
    <link rel="stylesheet" href="../assets/css/cliente_registrado.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Cita para Cliente Registrado</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="clientes.php">👥 Clientes</a>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <div class="form-container">
            <h2 class="form-title">📝 Agendar Cita para Cliente Existente</h2>
            
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
                        <?php while($cliente = $clientes->fetch_assoc()): ?>
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
                
                <hr style="margin: 20px 0;">
                
                <h3 style="color: var(--primary); margin-bottom: 20px;">🐕 Datos de la Mascota</h3>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Nombre de la Mascota *</label>
                        <input type="text" name="nombre_mascota" required placeholder="Ej: Luna, Max, Simba">
                    </div>
                    
                    <div class="form-group">
                        <label>Especie *</label>
                        <select name="especie" required>
                            <option value="">Seleccione...</option>
                            <option value="Canino">🐕 Perro (Canino)</option>
                            <option value="Felino">🐈 Gato (Felino)</option>
                            <option value="Ave">🐦 Ave</option>
                            <option value="Reptil">🦎 Reptil</option>
                            <option value="Otro">🐾 Otro</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Raza</label>
                        <input type="text" name="raza" placeholder="Ej: Labrador, Persa...">
                    </div>
                    
                    <div class="form-group">
                        <label>Género</label>
                        <select name="genero">
                            <option value="">Seleccione...</option>
                            <option value="MACHO">♂️ Macho</option>
                            <option value="HEMBRA">♀️ Hembra</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Fecha de Nacimiento (opcional)</label>
                        <input type="date" name="fecha_nacimiento" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <hr style="margin: 20px 0;">
                
                <h3 style="color: var(--primary); margin-bottom: 20px;">📅 Detalles de la Cita</h3>
                
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
                        <div class="servicios-grid" id="serviciosGrid">
                            <?php while($servicio = $servicios->fetch_assoc()): ?>
                                <label class="servicio-item">
                                    <input type="checkbox" name="servicios[]" value="<?php echo $servicio['id']; ?>" 
                                           data-precio="<?php echo $servicio['precio']; ?>" onchange="actualizarTotal()">
                                    <span class="nombre"><?php echo htmlspecialchars($servicio['nombre_servicio']); ?></span>
                                    <span class="precio">$<?php echo number_format($servicio['precio'], 2); ?></span>
                                </label>
                            <?php endwhile; ?>
                        </div>
                        <div id="totalPreview" class="total-preview">
                            Total estimado: $0.00
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
                
                <button type="submit" class="btn-submit">✅ Agendar Cita</button>
            </form>
        </div>
    </div>
    
    <script>
        // Mostrar información del cliente seleccionado
        const clienteSelect = document.getElementById('id_cliente');
        const infoClienteDiv = document.getElementById('infoCliente');
        const clienteInfoSpan = document.getElementById('clienteInfo');
        
        clienteSelect.addEventListener('change', function() {
            if (this.value) {
                const selectedOption = this.options[this.selectedIndex];
                clienteInfoSpan.innerHTML = selectedOption.text;
                infoClienteDiv.classList.add('visible');
            } else {
                infoClienteDiv.classList.remove('visible');
            }
        });
        
        // Calcular total de servicios
        function actualizarTotal() {
            let total = 0;
            document.querySelectorAll('input[name="servicios[]"]:checked').forEach(chk => {
                total += parseFloat(chk.dataset.precio);
            });
            document.getElementById('totalPreview').innerHTML = `💰 Total estimado: $${total.toFixed(2)} MXN`;
        }
        
        // Fecha mínima hoy
        const fechaInput = document.querySelector('input[name="fecha_cita"]');
        if (fechaInput) {
            const hoy = new Date().toISOString().split('T')[0];
            fechaInput.min = hoy;
        }
    </script>
</body>
</html>