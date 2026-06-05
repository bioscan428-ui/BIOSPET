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
            c.pagada,
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
            cl.direccion,
            (SELECT COALESCE(SUM(dc.precio_fijado), 0) FROM DETALLE_CITA dc WHERE dc.id_cita = c.id) as total_servicios
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

// Obtener todas las mascotas del cliente (para poder cambiar de mascota)
$sql_mascotas_cliente = "SELECT id, nombre_mascota, especie, raza FROM MASCOTA WHERE id_cliente = ? AND activo = 1";
$stmt_masc = $conn->prepare($sql_mascotas_cliente);
$stmt_masc->bind_param("i", $cita['cliente_id']);
$stmt_masc->execute();
$mascotas_cliente = $stmt_masc->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener servicios de la cita con sus precios actuales (fijados)
$sql_servicios_cita = "SELECT 
                            dc.id_servicio,
                            dc.precio_fijado,
                            s.nombre_servicio,
                            s.precio as precio_actual_oficial
                        FROM DETALLE_CITA dc
                        JOIN SERVICIO s ON dc.id_servicio = s.id
                        WHERE dc.id_cita = ?";
$stmt_serv = $conn->prepare($sql_servicios_cita);
$stmt_serv->bind_param("i", $id_cita);
$stmt_serv->execute();
$servicios_cita = $stmt_serv->get_result()->fetch_all(MYSQLI_ASSOC);

// Procesar actualización
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Datos del dueño
    $nombre_dueno = trim($_POST['nombre_dueno']);
    $ape_pat = trim($_POST['ape_pat'] ?? '');
    $ape_mat = trim($_POST['ape_mat'] ?? '');
    $telefono = trim($_POST['telefono']);
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    
    // Datos de la mascota (puede ser la misma u otra)
    $id_mascota = (int)($_POST['id_mascota'] ?? 0);
    $nombre_mascota = trim($_POST['nombre_mascota']);
    $especie = $_POST['especie'] ?? '';
    $raza = trim($_POST['raza'] ?? '');
    $genero = $_POST['genero'] ?? null;
    
    // Datos de la cita
    $fecha_cita = $_POST['fecha_cita'];
    $hora_cita = $_POST['hora_cita'];
    $notas = trim($_POST['notas'] ?? '');
    $origen = $_POST['origen'];
    
    // Servicios seleccionados y precios
    $servicios_seleccionados = $_POST['servicios_seleccionados'] ?? [];
    $servicios_precios = $_POST['servicios_precios'] ?? [];

    //DIAGNOSTICO
    echo "<pre>";
echo "CITA ID: " . $id_cita . "<br>";
echo "Cliente ID en BD: " . $cita['cliente_id'] . "<br>";
echo "Nombre actual en BD: " . $cita['cliente_nombre'] . "<br>";
echo "Mascota ID: " . $cita['mascota_id'] . "<br>";
echo "Dueño según la consulta: " . $cita['cliente_nombre'] . "<br>";
echo "</pre>";
    //FIN DIAGNOSTICO
    
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
            
            // 2. Si la mascota cambió o se actualizó
            if ($id_mascota > 0 && $id_mascota != $cita['mascota_id']) {
                // Cambiar a una mascota existente del mismo cliente
                $sql_update_cita_mascota = "UPDATE CITA SET id_mascota = ? WHERE id = ?";
                $stmt_update_masc = $conn->prepare($sql_update_cita_mascota);
                $stmt_update_masc->bind_param("ii", $id_mascota, $id_cita);
                $stmt_update_masc->execute();
            } else {
                // Actualizar datos de la mascota actual
                $sql_update_mascota = "UPDATE MASCOTA SET nombre_mascota = ?, especie = ?, raza = ?, genero = ? WHERE id = ?";
                $stmt_masc = $conn->prepare($sql_update_mascota);
                $stmt_masc->bind_param("ssssi", $nombre_mascota, $especie, $raza, $genero, $cita['mascota_id']);
                $stmt_masc->execute();
            }
            
            // 3. Actualizar CITA
            $sql_update_cita = "UPDATE CITA SET fecha_cita = ?, hora_cita = ?, notas = ?, origen = ? WHERE id = ?";
            $stmt_cita = $conn->prepare($sql_update_cita);
            $stmt_cita->bind_param("ssssi", $fecha_cita, $hora_cita, $notas, $origen, $id_cita);
            $stmt_cita->execute();
            
            // 4. Actualizar servicios
            // Eliminar todos los servicios actuales
            $sql_delete_servicios = "DELETE FROM DETALLE_CITA WHERE id_cita = ?";
            $stmt_del = $conn->prepare($sql_delete_servicios);
            $stmt_del->bind_param("i", $id_cita);
            $stmt_del->execute();
            $stmt_del->close();
            
            // Insertar los servicios seleccionados
            if (!empty($servicios_seleccionados)) {
                $sql_detalle = "INSERT INTO DETALLE_CITA (id_cita, id_servicio, precio_fijado) VALUES (?, ?, ?)";
                $stmt_det = $conn->prepare($sql_detalle);
                
                foreach ($servicios_seleccionados as $id_servicio) {
                    // Obtener precio (personalizado o el oficial)
                    if (isset($servicios_precios[$id_servicio]) && !empty($servicios_precios[$id_servicio])) {
                        $precio = floatval($servicios_precios[$id_servicio]);
                    } else {
                        $sql_precio = "SELECT precio FROM SERVICIO WHERE id = ?";
                        $stmt_precio = $conn->prepare($sql_precio);
                        $stmt_precio->bind_param("i", $id_servicio);
                        $stmt_precio->execute();
                        $precio = $stmt_precio->get_result()->fetch_assoc()['precio'];
                        $stmt_precio->close();
                    }
                    
                    $stmt_det->bind_param("iid", $id_cita, $id_servicio, $precio);
                    $stmt_det->execute();
                }
                $stmt_det->close();
            }
            
            $conn->commit();
            $mensaje = "✅ Cita actualizada correctamente";
            
            // Recargar datos
            $stmt2 = $conn->prepare($sql);
            $stmt2->bind_param("i", $id_cita);
            $stmt2->execute();
            $cita = $stmt2->get_result()->fetch_assoc();
            
            // Recargar servicios de la cita
            $stmt_serv2 = $conn->prepare($sql_servicios_cita);
            $stmt_serv2->bind_param("i", $id_cita);
            $stmt_serv2->execute();
            $servicios_cita = $stmt_serv2->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Recargar mascotas del cliente
            $stmt_masc2 = $conn->prepare($sql_mascotas_cliente);
            $stmt_masc2->bind_param("i", $cita['cliente_id']);
            $stmt_masc2->execute();
            $mascotas_cliente = $stmt_masc2->get_result()->fetch_all(MYSQLI_ASSOC);
            
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
            max-width: 1000px;
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
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        .estado-cita {
            background: #e8f0fe;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .badge-pagada {
            background: #28a745;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            margin-left: 10px;
        }
        h2, h3 { color: #E68D0B; margin-bottom: 15px; }
        hr { margin: 20px 0; }
        .alert-info {
            background: #e8f0fe;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
            color: #004085;
        }
        /* Estilo igual a citas.php */
        .servicios-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 12px;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            background: #f9f9f9;
            margin-bottom: 20px;
        }
        .servicio-checkbox {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .servicio-checkbox:hover {
            background: #f0f0f0;
        }
        .servicio-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            margin: 0;
        }
        .servicio-info {
            flex: 1;
        }
        .servicio-nombre {
            font-weight: 600;
            font-size: 14px;
        }
        .servicio-precio-oficial {
            font-size: 12px;
            color: #666;
        }
        .precio-personalizado {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            min-width: 110px;
        }
        .precio-personalizado input {
            width: 100px;
            padding: 5px 8px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
            text-align: right;
        }
        .precio-personalizado small {
            font-size: 10px;
            color: #999;
            margin-top: 3px;
        }
        .total-preview {
            margin-top: 15px;
            padding: 12px;
            background: #e8f0fe;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            font-size: 16px;
        }
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

        <!-- ADVERTENCIA PARA CITAS CONFIRMADAS/PAGADAS -->
        <?php if ($cita['estado'] == 'confirmada' || $cita['estado'] == 'completada' || $cita['pagada'] == 1): ?>
            <div class="alert-warning">
                <strong>⚠️ ¡EDITANDO CITA <?php echo strtoupper($cita['estado']); ?>!</strong><br><br>
                <?php if ($cita['pagada']): ?>
                    Esta cita ya fue pagada por <strong>$<?php echo number_format($cita['total_servicios'] ?? 0, 2); ?></strong>.
                    <br><br>
                    <strong>Los precios que modifiques aquí solo afectarán a ESTA cita</strong> y no modificarán los precios oficiales de los servicios.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="estado-cita">
            <strong>Estado actual:</strong> 
            <span style="background: <?php echo $cita['estado'] == 'pendiente' ? '#ff9800' : ($cita['estado'] == 'confirmada' ? '#4caf50' : '#2196f3'); ?>; color: white; padding: 4px 12px; border-radius: 20px;">
                <?php echo ucfirst($cita['estado']); ?>
            </span>
            <?php if ($cita['pagada']): ?>
                <span class="badge-pagada">✅ Pagada</span>
            <?php endif; ?>
        </div>

        <form method="POST">
            <div class="form-grid">
                <h3 style="grid-column: span 2; color: #E68D0B;">📋 Datos del Dueño</h3>
                
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

                <h3 style="grid-column: span 2; color: #E68D0B; margin-top: 20px;">🐕 Datos de la Mascota</h3>
                
                <?php if (count($mascotas_cliente) > 1): ?>
                <div class="form-group full-width">
                    <div class="alert-info">
                        <strong>📋 Este cliente tiene <?php echo count($mascotas_cliente); ?> mascota(s).</strong>
                        <br>Puedes cambiar la mascota de esta cita si es necesario.
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label>Cambiar a otra mascota del mismo dueño:</label>
                    <select name="id_mascota" id="select_mascota" onchange="cargarDatosMascota(this.value)">
                        <option value="<?php echo $cita['mascota_id']; ?>" selected>
                            🔄 Mantener mascota actual: <?php echo htmlspecialchars($cita['nombre_mascota']); ?>
                        </option>
                        <?php foreach ($mascotas_cliente as $m): ?>
                            <?php if ($m['id'] != $cita['mascota_id']): ?>
                            <option value="<?php echo $m['id']; ?>" 
                                    data-nombre="<?php echo htmlspecialchars($m['nombre_mascota']); ?>"
                                    data-especie="<?php echo $m['especie']; ?>"
                                    data-raza="<?php echo htmlspecialchars($m['raza']); ?>">
                                📋 <?php echo htmlspecialchars($m['nombre_mascota']); ?> (<?php echo $m['especie']; ?>)
                            </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                    <input type="hidden" name="id_mascota" value="<?php echo $cita['mascota_id']; ?>">
                <?php endif; ?>
                
                <div class="form-group full-width">
                    <label>Nombre de la Mascota *</label>
                    <input type="text" name="nombre_mascota" id="nombre_mascota" value="<?php echo htmlspecialchars($cita['nombre_mascota']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Especie *</label>
                    <select name="especie" id="especie" required>
                        <option value="Canino" <?php echo $cita['especie'] == 'Canino' ? 'selected' : ''; ?>>Perro</option>
                        <option value="Felino" <?php echo $cita['especie'] == 'Felino' ? 'selected' : ''; ?>>Gato</option>
                        <option value="Otro" <?php echo $cita['especie'] == 'Otro' ? 'selected' : ''; ?>>Otro</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Raza</label>
                    <input type="text" name="raza" id="raza" value="<?php echo htmlspecialchars($cita['raza'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Género</label>
                    <select name="genero" id="genero">
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

                <h3 style="grid-column: span 2; color: #E68D0B; margin-top: 20px;">📅 Detalles de la Cita</h3>
                
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

                <!-- SECCIÓN DE SERVICIOS - ESTILO IGUAL A citas.php -->
                <!-- SECCIÓN DE SERVICIOS - MODIFICADA -->
                <div class="form-group full-width">
                    <label>🩺 Servicios de la cita</label>
    
                    <?php
                    $servicios_ids_seleccionados = array_column($servicios_cita, 'id_servicio');
                    $sql_todos_servicios = "SELECT id, nombre_servicio, precio FROM SERVICIO WHERE activo = 1 ORDER BY nombre_servicio";
                    $todos_servicios = $conn->query($sql_todos_servicios);
                    ?>
    
                    <!-- Mostrar advertencia según el estado -->
                    <?php if ($cita['estado'] == 'pendiente'): ?>
                        <div class="alert-info" style="background: #e8f0fe; padding: 12px; border-radius: 8px; margin-bottom: 15px;">
                            <strong>📝 Cita pendiente:</strong> Puedes modificar libremente los servicios y precios. El cliente recibirá la actualización.
                        </div>
                    <?php elseif ($cita['estado'] == 'confirmada' && !$cita['pagada']): ?>
                        <div class="alert-warning" style="background: #fff3cd; padding: 12px; border-radius: 8px; margin-bottom: 15px;">
                            <strong>⚠️ Cita confirmada:</strong> Los cambios se notificarán al cliente. Si modificas precios, el total se actualizará.
                        </div>
                    <?php elseif ($cita['pagada']): ?>
                        <div class="alert-warning" style="background: #f8d7da; padding: 12px; border-radius: 8px; margin-bottom: 15px;">
                            <strong>💰 CITA YA PAGADA:</strong> Modificar servicios o precios afectará el ticket y el corte de caja.
                            <br><small>Solo modifica si es absolutamente necesario y con autorización.</small>
                        </div>
                    <?php endif; ?>
    
                    <div class="servicios-grid">
                        <?php while($servicio = $todos_servicios->fetch_assoc()): 
                            $checked = in_array($servicio['id'], $servicios_ids_seleccionados);
                            $precio_actual = $servicio['precio'];
                            if ($checked) {
                                foreach($servicios_cita as $sc) {
                                    if ($sc['id_servicio'] == $servicio['id']) {
                                        $precio_actual = $sc['precio_fijado'];
                                        break;
                                    }
                                }
                            }
                        ?>
                            <div class="servicio-item" style="display: flex; align-items: center; gap: 12px; padding: 10px; border-radius: 8px; background: white; border: 1px solid #eee;">
                                <input type="checkbox" 
                                        name="servicios_seleccionados[]" 
                                        value="<?php echo $servicio['id']; ?>"
                                        data-precio-oficial="<?php echo $servicio['precio']; ?>"
                                        data-precio-actual="<?php echo $precio_actual; ?>"
                                        onchange="togglePrecioInput(this, <?php echo $servicio['id']; ?>)"
                                        <?php echo $checked ? 'checked' : ''; ?>
                                        style="width: 18px; height: 18px; cursor: pointer; margin: 0;">
                
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($servicio['nombre_servicio']); ?></div>
                                    <div style="font-size: 12px; color: #666;">Oficial: $<?php echo number_format($servicio['precio'], 2); ?></div>
                                </div>
                
                                <div id="precio-input-<?php echo $servicio['id']; ?>" style="min-width: 110px; <?php echo $checked ? '' : 'display:none;' ?>">
                                    <input type="number" 
                                            name="servicios_precios[<?php echo $servicio['id']; ?>]" 
                                            value="<?php echo $precio_actual; ?>"
                                            step="0.01"
                                            min="0"
                                            class="precio-input"
                                            style="width: 100px; padding: 6px 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; text-align: right;"
                                            placeholder="$0.00"
                                            onclick="event.stopPropagation()"
                                            <?php echo ($cita['pagada']) ? 'onchange="confirmarCambioPrecio(this)"' : ''; ?>>
                                    <div style="font-size: 10px; color: #999; text-align: center;">Personalizar</div>
                            </div>
                    </div>
                <?php endwhile; ?>
            </div>
    
            <div class="total-preview" style="margin-top: 15px; padding: 12px; background: #e8f0fe; border-radius: 8px; text-align: center; font-weight: bold; font-size: 16px;">
                💰 Total: $<span id="total-monto">0.00</span>
            </div>
    
            <?php if ($cita['pagada']): ?>
                <div class="alert-warning" style="margin-top: 15px; background: #f8d7da; padding: 12px; border-radius: 8px;">
                    <strong>⚠️ ADVERTENCIA PARA CITAS PAGADAS:</strong>
                    <ul style="margin: 10px 0 0 20px; color: #721c24;">
                        <li>Al modificar servicios, el ticket se actualizará</li>
                        <li>El corte de caja mostrará los nuevos montos</li>
                        <li>Se recomienda documentar el cambio en observaciones</li>
                    </ul>
                </div>
            <?php endif; ?>
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
    </div>

    <script>
    function cargarDatosMascota(mascotaId) {
        if (!mascotaId) return;
        const select = document.getElementById('select_mascota');
        if (!select) return;
        const option = select.querySelector(`option[value="${mascotaId}"]`);
        if (option && option !== select.selectedOptions[0]) {
            document.getElementById('nombre_mascota').value = option.getAttribute('data-nombre') || '';
            document.getElementById('especie').value = option.getAttribute('data-especie') || 'Canino';
            document.getElementById('raza').value = option.getAttribute('data-raza') || '';
            document.getElementById('genero').value = '';
            if (!confirm('¿Cambiar la mascota de esta cita?\n\nSe actualizarán los datos de la mascota.')) {
                select.value = '<?php echo $cita['mascota_id']; ?>';
                location.reload();
            }
        }
    }
    
    function togglePrecioInput(checkbox, servicioId) {
        const div = document.getElementById(`precio-input-${servicioId}`);
        if (checkbox.checked) {
            div.style.display = 'flex';
            const input = div.querySelector('input');
            if (input && !input.value) {
                input.value = checkbox.getAttribute('data-precio-actual') || checkbox.getAttribute('data-precio-oficial') || 0;
            }
        } else {
            div.style.display = 'none';
        }
        calcularTotal();
    }
    
    function calcularTotal() {
        let total = 0;
        document.querySelectorAll('input[name="servicios_seleccionados[]"]:checked').forEach(chk => {
            const servicioId = chk.value;
            const input = document.querySelector(`input[name="servicios_precios[${servicioId}]"]`);
            let precio = parseFloat(chk.getAttribute('data-precio-oficial') || 0);
            if (input && input.value && input.value !== '') {
                precio = parseFloat(input.value);
            }
            if (!isNaN(precio)) total += precio;
        });
        const totalSpan = document.getElementById('total-monto');
        if (totalSpan) totalSpan.textContent = total.toFixed(2);
    }
    
    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList && e.target.classList.contains('precio-input')) {
            calcularTotal();
        }
    });
    
    document.addEventListener('change', function(e) {
        if (e.target && e.target.type === 'checkbox' && e.target.name === 'servicios_seleccionados[]') {
            calcularTotal();
        }
    });
    
    document.addEventListener('DOMContentLoaded', calcularTotal);

    function confirmarCambioPrecio(input) {
    const valorAnterior = input.defaultValue;
    const valorNuevo = input.value;
    
    if (valorAnterior !== valorNuevo) {
        if (!confirm('⚠️ Esta cita YA FUE PAGADA.\n\n¿Estás seguro de modificar el precio?\n\nAnterior: $' + parseFloat(valorAnterior).toFixed(2) + '\nNuevo: $' + parseFloat(valorNuevo).toFixed(2) + '\n\nEsta acción afectará el ticket y el corte de caja.')) {
            input.value = valorAnterior;
        }
    }
    calcularTotal();
}

function togglePrecioInput(checkbox, servicioId) {
    const div = document.getElementById(`precio-input-${servicioId}`);
    const esPagada = <?php echo $cita['pagada'] ? 'true' : 'false'; ?>;
    const estado = '<?php echo $cita['estado']; ?>';
    
    if (checkbox.checked) {
        if (esPagada) {
            if (!confirm('⚠️ ESTA CITA YA FUE PAGADA.\n\n¿Estás seguro de agregar este servicio?\n\nEl total se actualizará y afectará el ticket y corte de caja.')) {
                checkbox.checked = false;
                return;
            }
        }
        div.style.display = 'flex';
        const input = div.querySelector('input');
        if (input && !input.value) {
            input.value = checkbox.getAttribute('data-precio-actual') || checkbox.getAttribute('data-precio-oficial') || 0;
        }
    } else {
        if (esPagada) {
            if (!confirm('⚠️ ESTA CITA YA FUE PAGADA.\n\n¿Estás seguro de quitar este servicio?\n\nEl total se actualizará y afectará el ticket y corte de caja.')) {
                checkbox.checked = true;
                return;
            }
        }
        div.style.display = 'none';
    }
    calcularTotal();
}
    </script>
</body>
</html>