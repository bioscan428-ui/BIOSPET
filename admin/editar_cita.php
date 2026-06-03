<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'recepcionista', 'caja'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_cita = (int)($_GET['id'] ?? 0);
if (!$id_cita) {
    header('Location: dashboard.php');
    exit;
}

// Obtener datos de la cita
$sql = "SELECT 
            c.id as cita_id,
            c.fecha_cita,
            c.hora_cita,
            c.notas,
            c.origen,
            c.estado,
            m.id as mascota_id,
            m.nombre_mascota,
            m.especie,
            m.raza,
            m.genero,
            m.foto,
            cl.id as cliente_id,
            cl.nombre as cliente_nombre,
            cl.ape_pat,
            cl.ape_mat,
            cl.telefono,
            cl.email,
            cl.direccion
        FROM CITA c
        JOIN MASCOTA m ON c.id_mascota = m.id
        JOIN CLIENTE cl ON m.id_cliente = cl.id
        WHERE c.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_cita);
$stmt->execute();
$cita = $stmt->get_result()->fetch_assoc();

if (!$cita) {
    die("Cita no encontrada");
}

// ========== VALIDACIÓN: No editar citas canceladas o completadas ==========
$estados_no_editables = ['cancelada', 'completada'];
$cita_editable = !in_array($cita['estado'], $estados_no_editables);

// Obtener servicios de la cita
$sql_servicios_cita = "SELECT id_servicio FROM DETALLE_CITA WHERE id_cita = ?";
$stmt_serv = $conn->prepare($sql_servicios_cita);
$stmt_serv->bind_param("i", $id_cita);
$stmt_serv->execute();
$servicios_cita = $stmt_serv->get_result()->fetch_all(MYSQLI_ASSOC);
$servicios_seleccionados = array_column($servicios_cita, 'id_servicio');

// Obtener todos los servicios para el checkbox
$sql_servicios = "SELECT id, nombre_servicio, precio FROM SERVICIO WHERE activo = 1 ORDER BY nombre_servicio";
$servicios = $conn->query($sql_servicios);

// Procesar actualización (solo si la cita es editable)
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $cita_editable) {
    // Datos del dueño
    $nombre_dueno = trim($_POST['nombre_dueno']);
    $ape_pat = trim($_POST['ape_pat'] ?? '');
    $ape_mat = trim($_POST['ape_mat'] ?? '');
    $telefono = trim($_POST['telefono']);
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    
    // Datos de la mascota
    $nombre_mascota = trim($_POST['nombre_mascota']);
    $especie = $_POST['especie'];
    $raza = trim($_POST['raza'] ?? '');
    $genero = $_POST['genero'] ?? null;
    
    // Datos de la cita
    $fecha_cita = $_POST['fecha_cita'];
    $hora_cita = $_POST['hora_cita'];
    $notas = trim($_POST['notas'] ?? '');
    $origen = $_POST['origen'];
    $servicios_seleccionados_post = $_POST['servicios'] ?? [];
    
    // Validaciones básicas
    if (empty($nombre_dueno) || empty($telefono) || empty($nombre_mascota) || empty($fecha_cita) || empty($hora_cita)) {
        $error = "❌ Campos requeridos vacíos";
    } elseif (!preg_match('/^[0-9]{10,15}$/', $telefono)) {
        $error = "❌ El teléfono debe contener solo números (10-15 dígitos)";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "❌ El email no tiene un formato válido";
    } else {
        $conn->begin_transaction();
        
        try {
            // 1. Actualizar CLIENTE
            $sql_update_cliente = "UPDATE CLIENTE SET nombre = ?, ape_pat = ?, ape_mat = ?, telefono = ?, email = ?, direccion = ? WHERE id = ?";
            $stmt_cli = $conn->prepare($sql_update_cliente);
            $stmt_cli->bind_param("ssssssi", $nombre_dueno, $ape_pat, $ape_mat, $telefono, $email, $direccion, $cita['cliente_id']);
            $stmt_cli->execute();
            
            // 2. Actualizar MASCOTA
            $sql_update_mascota = "UPDATE MASCOTA SET nombre_mascota = ?, especie = ?, raza = ?, genero = ? WHERE id = ?";
            $stmt_masc = $conn->prepare($sql_update_mascota);
            $stmt_masc->bind_param("ssssi", $nombre_mascota, $especie, $raza, $genero, $cita['mascota_id']);
            $stmt_masc->execute();
            
            // 3. Actualizar CITA
            $sql_update_cita = "UPDATE CITA SET fecha_cita = ?, hora_cita = ?, notas = ?, origen = ? WHERE id = ?";
            $stmt_cita = $conn->prepare($sql_update_cita);
            $stmt_cita->bind_param("ssssi", $fecha_cita, $hora_cita, $notas, $origen, $id_cita);
            $stmt_cita->execute();
            
            // 4. Actualizar servicios (eliminar y volver a insertar)
            $sql_delete_servicios = "DELETE FROM DETALLE_CITA WHERE id_cita = ?";
            $stmt_del = $conn->prepare($sql_delete_servicios);
            $stmt_del->bind_param("i", $id_cita);
            $stmt_del->execute();
            
            if (!empty($servicios_seleccionados_post)) {
                foreach ($servicios_seleccionados_post as $id_servicio) {
                    $sql_precio = "SELECT precio FROM SERVICIO WHERE id = ?";
                    $stmt_precio = $conn->prepare($sql_precio);
                    $stmt_precio->bind_param("i", $id_servicio);
                    $stmt_precio->execute();
                    $precio = $stmt_precio->get_result()->fetch_assoc()['precio'];
                    
                    $sql_detalle = "INSERT INTO DETALLE_CITA (id_cita, id_servicio, precio_fijado) VALUES (?, ?, ?)";
                    $stmt_det = $conn->prepare($sql_detalle);
                    $stmt_det->bind_param("iid", $id_cita, $id_servicio, $precio);
                    $stmt_det->execute();
                }
            }
            
            $conn->commit();
            $mensaje = "✅ Cita actualizada correctamente";
            
            // Recargar datos
            $stmt2 = $conn->prepare($sql);
            $stmt2->bind_param("i", $id_cita);
            $stmt2->execute();
            $cita = $stmt2->get_result()->fetch_assoc();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "❌ Error al actualizar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Cita #<?php echo $id_cita; ?> - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        .form-cita {
            max-width: 900px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
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
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .btn-guardar {
            background: #4caf50;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        .btn-cancelar {
            background: #666;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
            text-align: center;
            width: 100%;
        }
        .mensaje-exito { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .mensaje-error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
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
        .estado-cita {
            background: #e8f0fe;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .estado-no-editable {
            background: #f8d7da;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            color: #721c24;
        }
        h2, h3 { color: #E68D0B; margin-bottom: 15px; }
        hr { margin: 20px 0; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Editar Cita #<?php echo $id_cita; ?></h1>
        <div>
            <a href="dashboard.php">← Volver</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="form-cita">
        <h2>✏️ Editar Cita</h2>
        
        <?php if ($mensaje): ?>
            <div class="mensaje-exito"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mensaje-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!$cita_editable): ?>
            <div class="estado-no-editable">
                <strong>⚠️ No se puede editar esta cita</strong><br><br>
                La cita se encuentra en estado <strong><?php echo strtoupper($cita['estado']); ?></strong>.<br>
                Las citas <?php echo implode(' o ', $estados_no_editables); ?> no pueden ser modificadas.
                <br><br>
                <a href="dashboard.php" class="btn-cancelar" style="display: inline-block; width: auto; padding: 10px 25px;">← Volver al Dashboard</a>
            </div>
        <?php else: ?>

        <div class="estado-cita">
            <strong>Estado actual:</strong> 
            <span style="background: <?php echo $cita['estado'] == 'pendiente' ? '#ff9800' : ($cita['estado'] == 'confirmada' ? '#4caf50' : '#2196f3'); ?>; color: white; padding: 4px 12px; border-radius: 20px;">
                <?php echo ucfirst($cita['estado']); ?>
            </span>
        </div>

        <form method="POST">
            <div class="form-grid">
                <h3 style="grid-column: span 2; color: var(--primary);">📋 Datos del Dueño</h3>
                
                <div class="form-group">
                    <label>Nombre(s) *</label>
                    <input type="text" name="nombre_dueno" value="<?php echo htmlspecialchars($cita['cliente_nombre']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Apellido Paterno</label>
                    <input type="text" name="ape_pat" value="<?php echo htmlspecialchars($cita['ape_pat'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>Apellido Materno</label>
                    <input type="text" name="ape_mat" value="<?php echo htmlspecialchars($cita['ape_mat'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>Teléfono *</label>
                    <input type="tel" name="telefono" value="<?php echo htmlspecialchars($cita['telefono']); ?>" required>
                </div>
                
                <div class="form-group full-width">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($cita['email'] ?? ''); ?>">
                </div>

                <div class="form-group full-width">
                    <label>Dirección</label>
                    <input type="text" name="direccion" value="<?php echo htmlspecialchars($cita['direccion'] ?? ''); ?>">
                </div>

                <h3 style="grid-column: span 2; color: var(--primary); margin-top: 20px;">🐕 Datos de la Mascota</h3>
                
                <div class="form-group full-width">
                    <label>Nombre de la Mascota *</label>
                    <input type="text" name="nombre_mascota" value="<?php echo htmlspecialchars($cita['nombre_mascota']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Especie *</label>
                    <select name="especie" required>
                        <option value="Canino" <?php echo $cita['especie'] == 'Canino' ? 'selected' : ''; ?>>Perro</option>
                        <option value="Felino" <?php echo $cita['especie'] == 'Felino' ? 'selected' : ''; ?>>Gato</option>
                        <option value="Otro" <?php echo $cita['especie'] == 'Otro' ? 'selected' : ''; ?>>Otro</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Raza</label>
                    <input type="text" name="raza" value="<?php echo htmlspecialchars($cita['raza'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Género</label>
                    <select name="genero">
                        <option value="">Seleccione...</option>
                        <option value="MACHO" <?php echo $cita['genero'] == 'MACHO' ? 'selected' : ''; ?>>Macho</option>
                        <option value="HEMBRA" <?php echo $cita['genero'] == 'HEMBRA' ? 'selected' : ''; ?>>Hembra</option>
                    </select>
                </div>

                <?php if (!empty($cita['foto']) && file_exists('../' . $cita['foto'])): ?>
                <div class="form-group full-width">
                    <label>Foto actual</label>
                    <div>
                        <img src="../<?php echo $cita['foto']; ?>" alt="Foto de mascota" style="max-width: 100px; border-radius: 10px;">
                    </div>
                </div>
                <?php endif; ?>

                <h3 style="grid-column: span 2; color: var(--primary); margin-top: 20px;">📅 Detalles de la Cita</h3>
                
                <div class="form-group">
                    <label>Fecha *</label>
                    <input type="date" name="fecha_cita" value="<?php echo $cita['fecha_cita']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Hora *</label>
                    <input type="time" name="hora_cita" value="<?php echo $cita['hora_cita']; ?>" required>
                </div>
                
                <div class="form-group full-width">
                    <label>Motivo de consulta / Síntomas</label>
                    <textarea name="notas" rows="4"><?php echo htmlspecialchars($cita['notas'] ?? ''); ?></textarea>
                </div>

                <div class="form-group full-width">
                    <label>🩺 Servicios</label>
                    <div class="servicios-grid">
                        <?php 
                        $servicios->data_seek(0);
                        while($servicio = $servicios->fetch_assoc()): 
                            $checked = in_array($servicio['id'], $servicios_seleccionados) ? 'checked' : '';
                        ?>
                            <label class="servicio-checkbox">
                                <input type="checkbox" name="servicios[]" value="<?php echo $servicio['id']; ?>" <?php echo $checked; ?>>
                                <span><?php echo htmlspecialchars($servicio['nombre_servicio']); ?></span>
                                <strong>$<?php echo number_format($servicio['precio'], 2); ?></strong>
                            </label>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label>📱 Origen de la cita</label>
                    <select name="origen">
                        <option value="Whatsapp" <?php echo $cita['origen'] == 'Whatsapp' ? 'selected' : ''; ?>>📱 WhatsApp</option>
                        <option value="Presencial" <?php echo $cita['origen'] == 'Presencial' ? 'selected' : ''; ?>>🏥 Presencial</option>
                    </select>
                </div>
            </div>

            <hr>

            <button type="submit" class="btn-guardar">💾 Guardar Cambios</button>
            <a href="dashboard.php" class="btn-cancelar">❌ Cancelar</a>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>