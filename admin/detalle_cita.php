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

$id_cita = (int)($_GET['id'] ?? 0);
if (!$id_cita) {
    header('Location: dashboard.php');
    exit;
}

// Obtener datos de la cita
$sql = "SELECT 
            c.*,
            m.nombre_mascota,
            m.especie,
            m.raza,
            m.fecha_nacimiento,
            m.genero,
            m.foto,
            cl.nombre AS nombre_dueno,
            cl.ape_pat,
            cl.ape_mat,
            cl.telefono,
            cl.email,
            GROUP_CONCAT(s.nombre_servicio SEPARATOR ', ') AS servicios,
            IFNULL(SUM(dc.precio_fijado), 0) AS total_servicios
        FROM CITA c
        JOIN MASCOTA m ON c.id_mascota = m.id
        JOIN CLIENTE cl ON m.id_cliente = cl.id
        LEFT JOIN DETALLE_CITA dc ON c.id = dc.id_cita
        LEFT JOIN SERVICIO s ON dc.id_servicio = s.id
        WHERE c.id = ?
        GROUP BY c.id";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_cita);
$stmt->execute();
$result = $stmt->get_result();
$cita = $result->fetch_assoc();

if (!$cita) {
    die("Cita no encontrada");
}

// ========== USAR FUNCIÓN total_servicios_cita ==========
$total_servicios_cita = $conn->query("SELECT total_servicios_cita($id_cita) as total")->fetch_assoc()['total'];

// Obtener productos ya agregados a esta cita
$sql_productos_cita = "SELECT COALESCE(SUM(dv.subtotal), 0) as total_productos 
                       FROM DETALLE_VENTA dv
                       JOIN VENTA_CITA vc ON vc.id_venta = dv.id_venta
                       WHERE vc.id_cita = ?";
                       
$stmt_prod = $conn->prepare($sql_productos_cita);
$stmt_prod->bind_param("i", $id_cita);
$stmt_prod->execute();
$total_productos = $stmt_prod->get_result()->fetch_assoc()['total_productos'] ?? 0;

// Verificar si la cita ya tiene pago
$pago_existente = $cita['pagada'] ? true : false;

// Calcular total general
$total_general = ($cita['total_servicios'] ?? 0) + $total_productos;

// Calcular edad de la mascota
$edad_mascota = null;
if ($cita['fecha_nacimiento']) {
    $sql_edad = "SELECT edad_mascota(?) as edad";
    $stmt_edad = $conn->prepare($sql_edad);
    $stmt_edad->bind_param("s", $cita['fecha_nacimiento']);
    $stmt_edad->execute();
    $result_edad = $stmt_edad->get_result();
    $row_edad = $result_edad->fetch_assoc();
    $edad_mascota = $row_edad['edad'];
}

// Verificar si la cita se puede cancelar
$sql_cancelable = "SELECT cita_cancelable(?) as cancelable";
$stmt_cancelable = $conn->prepare($sql_cancelable);
$stmt_cancelable->bind_param("i", $id_cita);
$stmt_cancelable->execute();
$result_cancelable = $stmt_cancelable->get_result();
$row_cancelable = $result_cancelable->fetch_assoc();
$cita_cancelable = $row_cancelable['cancelable'];

// Verificar si el veterinario solo puede ver sus citas asignadas
if ($_SESSION['rol'] === 'veterinario') {
    $sql_check = "SELECT id FROM ASIGNACION_CITA WHERE id_cita = ? AND id_empleado = ? AND rol_asignado = 'veterinario'";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $id_cita, $_SESSION['empleado_id']);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($result_check->num_rows == 0) {
        die("No tienes permiso para ver esta cita");
    }
}

// Obtener veterinarios y asistentes para las asignaciones
$veterinarios = [];
$asistentes = [];
$asignado = null;
$asignado_asistente = null;

// ========== NUEVO: Grooming (Estética) ==========
$groomers = [];
$asignado_groomer = null;
$tiene_servicios_estetica = false;

// Verificar si la cita tiene servicios de estética
$servicios_estetica = ['estética', 'baño', 'corte', 'cepillado', 'uñas', 'grooming'];
$servicios_cita = strtolower($cita['servicios'] ?? '');
foreach ($servicios_estetica as $keyword) {
    if (strpos($servicios_cita, $keyword) !== false) {
        $tiene_servicios_estetica = true;
        break;
    }
}
if (in_array($_SESSION['rol'], ['super_admin', 'admin']) && $tiene_servicios_estetica) {
    // Groomer actualmente asignado
    $sql_asignado_groomer = "SELECT e.id, e.nombre, e.ape_pat 
                            FROM ASIGNACION_CITA ac
                            JOIN EMPLEADO e ON ac.id_empleado = e.id
                            WHERE ac.id_cita = ? AND ac.rol_asignado = 'grooming'";
    $stmt_asig_groomer = $conn->prepare($sql_asignado_groomer);
    $stmt_asig_groomer->bind_param("i", $id_cita);
    $stmt_asig_groomer->execute();
    $asignado_groomer = $stmt_asig_groomer->get_result()->fetch_assoc();

    // Lista de groomers
    $sql_groomers = "SELECT e.id, e.nombre, e.ape_pat
                    FROM EMPLEADO e
                    JOIN USUARIO u ON e.id = u.id_empleado
                    WHERE e.puesto = 'grooming' AND e.activo = 1 AND u.activo = 1
                    ORDER BY e.nombre";
    $groomers = $conn->query($sql_groomers);
    }
// ========== FIN DE NUEVO: Grooming (Estética) ==========

if (in_array($_SESSION['rol'], ['super_admin', 'admin'])) {
    // Veterinario actualmente asignado
    $sql_asignado = "SELECT e.id, e.nombre, e.ape_pat, e.especialidad 
                    FROM ASIGNACION_CITA ac
                    JOIN EMPLEADO e ON ac.id_empleado = e.id
                    WHERE ac.id_cita = ? AND ac.rol_asignado = 'veterinario'";
    $stmt_asig = $conn->prepare($sql_asignado);
    $stmt_asig->bind_param("i", $id_cita);
    $stmt_asig->execute();
    $asignado = $stmt_asig->get_result()->fetch_assoc();
    
    // Lista de veterinarios
    $sql_vets = "SELECT e.id, e.nombre, e.ape_pat, e.especialidad 
                FROM EMPLEADO e
                JOIN USUARIO u ON e.id = u.id_empleado
                WHERE e.puesto = 'veterinario' AND e.activo = 1 AND u.activo = 1
                ORDER BY e.nombre";
    $veterinarios = $conn->query($sql_vets);
    
    // Asistente actualmente asignado
    $sql_asignado_asistente = "SELECT e.id, e.nombre, e.ape_pat
                            FROM ASIGNACION_CITA ac
                            JOIN EMPLEADO e ON ac.id_empleado = e.id
                            WHERE ac.id_cita = ? AND ac.rol_asignado = 'asistente'";
    $stmt_asig_asistente = $conn->prepare($sql_asignado_asistente);
    $stmt_asig_asistente->bind_param("i", $id_cita);
    $stmt_asig_asistente->execute();
    $asignado_asistente = $stmt_asig_asistente->get_result()->fetch_assoc();
    
    // Lista de asistentes
    $sql_asistentes = "SELECT e.id, e.nombre, e.ape_pat
                    FROM EMPLEADO e
                    JOIN USUARIO u ON e.id = u.id_empleado
                    WHERE e.puesto = 'asistente' AND e.activo = 1 AND u.activo = 1
                    ORDER BY e.nombre";
    $asistentes = $conn->query($sql_asistentes);
}

// Obtener servicios disponibles
$sql_servicios = "SELECT id, nombre_servicio, precio FROM SERVICIO WHERE activo = 1 ORDER BY nombre_servicio";
$servicios_disponibles = $conn->query($sql_servicios);

// Obtener productos disponibles para la tienda
$sql_productos = "SELECT id, nombre, precio_venta, stock_actual FROM PRODUCTO WHERE activo = 1 AND stock_actual > 0 ORDER BY nombre";
$productos = $conn->query($sql_productos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle Cita #<?php echo $id_cita; ?> - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/detalle_cita.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Detalle de Cita #<?php echo $id_cita; ?></h1>
        <div>
            <a href="dashboard.php">← Volver</a>
            <a href="calendario.php">📅 Calendario</a>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="alert-success" style="background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px;"><?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert-error" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px;"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- Información de la Cita -->
        <div class="section">
            <h3>📋 Información de la Cita</h3>
            <div class="info-row">
                <div class="info-label">Fecha:</div>
                <div class="info-value"><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Hora:</div>
                <div class="info-value"><?php echo $cita['hora_cita']; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Estado:</div>
                <div class="info-value"><span class="estado estado-<?php echo $cita['estado']; ?>"><?php echo ucfirst($cita['estado']); ?></span></div>
            </div>
            <?php if ($pago_existente): ?>
            <div class="info-row">
                <div class="info-label">Estado de pago:</div>
                <div class="info-value"><span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px;">✅ Pagado</span></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Dueño -->
        <div class="section">
            <h3>👤 Dueño</h3>
            <div class="info-row">  
                <div class="info-label">Nombre:</div>
                <div class="info-value"><?php echo htmlspecialchars($cita['nombre_dueno'] . ' ' . $cita['ape_pat'] . ' ' . $cita['ape_mat']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Teléfono:</div>
                <div class="info-value"><?php echo $cita['telefono']; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Email:</div>
                <div class="info-value"><?php echo $cita['email'] ?: 'No registrado'; ?></div>
            </div>
        </div>

        <!-- Mascota -->
        <div class="section">
            <h3>🐕 Mascota</h3>
            
            <?php if (!empty($cita['foto']) && file_exists('../' . $cita['foto'])): ?>
                <div class="foto-mascota">
                    <img src="../<?php echo $cita['foto']; ?>" alt="Foto de <?php echo htmlspecialchars($cita['nombre_mascota']); ?>">
                </div>
            <?php endif; ?>
            
            <div class="info-row">
                <div class="info-label">Nombre:</div>
                <div class="info-value"><?php echo htmlspecialchars($cita['nombre_mascota']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Especie:</div>
                <div class="info-value"><?php echo $cita['especie']; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Raza:</div>
                <div class="info-value"><?php echo $cita['raza'] ?: 'No especificada'; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Género:</div>
                <div class="info-value">
                    <?php 
                    if ($cita['genero'] == 'MACHO') echo '♂️ Macho';
                    elseif ($cita['genero'] == 'HEMBRA') echo '♀️ Hembra';
                    else echo 'No registrado';
                    ?>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Fecha Nac.:</div>
                <div class="info-value"><?php echo $cita['fecha_nacimiento'] ?: 'No registrada'; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Edad:</div>
                <div class="info-value"><?php echo $edad_mascota !== null ? $edad_mascota . ' años' : 'No registrada'; ?></div>
            </div>
            
            <div class="info-row" style="margin-top: 15px; border-top: 1px dashed #ddd; padding-top: 15px;">
                <div class="info-label">Carnet:</div>
                <div class="info-value">
                    <a href="../carnet_mascota.php?id=<?php echo $cita['id_mascota']; ?>" class="btn-small" target="_blank" style="background: var(--primary);">📄 Ver Carnet Digital</a>
                    <a href="../carnet_pdf.php?id=<?php echo $cita['id_mascota']; ?>" class="btn-small" target="_blank" style="background: #4caf50;">📑 Descargar PDF</a>
                </div>
            </div>
        </div>

        <!-- Motivo de Consulta -->
        <div class="section">
            <h3>📋 Motivo de Consulta / Síntomas</h3>
            <div class="sintomas-box">
                <?php echo nl2br(htmlspecialchars($cita['notas'] ?: 'No se especificaron síntomas o motivo de consulta.')); ?>
            </div>
        </div>

        <!-- Asignar Veterinario (solo admin/super_admin) -->
        <?php if (in_array($_SESSION['rol'], ['super_admin', 'admin'])): ?>
        <div class="section">
            <h3>👨‍⚕️ Asignar Veterinario</h3>
            <?php if ($asignado): ?>
                <div class="info-row" style="margin-bottom: 15px;">
                    <div class="info-label">Veterinario asignado:</div>
                    <div class="info-value">🩺 Dr/a. <?php echo $asignado['nombre'] . ' ' . $asignado['ape_pat']; ?></div>
                </div>
            <?php endif; ?>
            <form action="asignar_veterinario.php" method="POST">
                <input type="hidden" name="id_cita" value="<?php echo $id_cita; ?>">
                <div class="form-row">
                    <select name="id_veterinario" required style="flex:2; padding:8px;">
                        <option value="">Seleccionar...</option>
                        <?php while($vet = $veterinarios->fetch_assoc()): ?>
                            <option value="<?php echo $vet['id']; ?>">Dr/a. <?php echo $vet['nombre'] . ' ' . $vet['ape_pat']; ?></option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit" class="btn-small" style="background: var(--primary);">Asignar</button>
                </div>
            </form>
            <?php if (!$asignado && $cita['estado'] == 'confirmada'): ?>
                <p style="color: #ff9800; font-size: 12px; margin-top: 10px;">⚠️ Esta cita está confirmada pero no tiene veterinario asignado.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Asignar Asistente (solo admin/super_admin) -->
        <?php if (in_array($_SESSION['rol'], ['super_admin', 'admin'])): ?>
        <div class="section">
            <h3>🩺 Asignar Asistente</h3>
            <?php if ($asignado_asistente): ?>
                <div class="info-row" style="margin-bottom: 15px;">
                    <div class="info-label">Asistente asignado:</div>
                    <div class="info-value">🩺 <?php echo $asignado_asistente['nombre'] . ' ' . $asignado_asistente['ape_pat']; ?></div>
                </div>
            <?php endif; ?>
            <form action="asignar_asistente.php" method="POST">
                <input type="hidden" name="id_cita" value="<?php echo $id_cita; ?>">
                <div class="form-row">
                    <select name="id_asistente" required style="flex:2; padding:8px;">
                        <option value="">Seleccionar...</option>
                        <?php while($asistente = $asistentes->fetch_assoc()): ?>
                            <option value="<?php echo $asistente['id']; ?>"><?php echo $asistente['nombre'] . ' ' . $asistente['ape_pat']; ?></option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit" class="btn-small" style="background: var(--primary);">Asignar</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
        <!-- Seccion de grooming -->
        <!-- Asignar Groomer (Estética) - Solo si hay servicios de estética -->
<?php if (in_array($_SESSION['rol'], ['super_admin', 'admin']) && $tiene_servicios_estetica): ?>
<div class="section">
    <h3>✂️ Asignar Groomer (Estética)</h3>
    <?php if ($asignado_groomer): ?>
        <div class="info-row" style="margin-bottom: 15px;">
            <div class="info-label">Groomer asignado:</div>
            <div class="info-value">✂️ <?php echo $asignado_groomer['nombre'] . ' ' . $asignado_groomer['ape_pat']; ?></div>
        </div>
    <?php endif; ?>
    <form action="asignar_groomer.php" method="POST">
        <input type="hidden" name="id_cita" value="<?php echo $id_cita; ?>">
        <div class="form-row">
            <select name="id_groomer" required style="flex:2; padding:8px;">
                <option value="">Seleccionar...</option>
                <?php while($groomer = $groomers->fetch_assoc()): ?>
                    <option value="<?php echo $groomer['id']; ?>">✂️ <?php echo $groomer['nombre'] . ' ' . $groomer['ape_pat']; ?></option>
                <?php endwhile; ?>
            </select>
            <button type="submit" class="btn-small" style="background: var(--primary);">Asignar</button>
        </div>
    </form>
    <?php if (!$asignado_groomer && $cita['estado'] == 'confirmada' && $tiene_servicios_estetica): ?>
        <p style="color: #ff9800; font-size: 12px; margin-top: 10px;">⚠️ Esta cita tiene servicios de estética pero no tiene groomer asignado.</p>
    <?php endif; ?>
</div>
<?php elseif ($tiene_servicios_estetica && !in_array($_SESSION['rol'], ['super_admin', 'admin'])): ?>
<div class="section">
    <h3>✂️ Groomer (Estética)</h3>
    <?php if ($asignado_groomer): ?>
        <div class="info-row">
            <div class="info-label">Groomer asignado:</div>
            <div class="info-value">✂️ <?php echo $asignado_groomer['nombre'] . ' ' . $asignado_groomer['ape_pat']; ?></div>
        </div>
    <?php else: ?>
        <div class="info-row">
            <div class="info-label">Groomer:</div>
            <div class="info-value"><span style="color: #ff9800;">⚠️ Sin asignar</span></div>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
        <!-- FIN DE Seccion de grooming -->
        <!-- Servicios Solicitados -->
        <div class="section">
            <h3>💊 Servicios Solicitados</h3>
            <div class="info-row">
                <div class="info-label">Servicios:</div>
                <div class="info-value">
                    <?php echo !empty($cita['servicios']) ? $cita['servicios'] : '<span class="sin-servicios">(Sin servicios asignados)</span>'; ?>
                    <?php if ($total_servicios_cita > 0): ?>
                        <span class="badge-servicios">📋 <?php echo $total_servicios_cita; ?> servicio(s)</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Total Servicios:</div>
                <div class="info-value"><strong>$<?php echo number_format($cita['total_servicios'], 2); ?></strong></div>
            </div>
        </div>

        <!-- Productos Agregados a la Cita -->
        <div class="section">
            <h3>🛒 Productos de la Cita</h3>
            <?php
            $sql_productos_lista = "SELECT dv.*, p.nombre, p.precio_venta 
                       FROM DETALLE_VENTA dv
                       JOIN PRODUCTO p ON dv.id_producto = p.id
                       JOIN VENTA_CITA vc ON vc.id_venta = dv.id_venta
                       WHERE vc.id_cita = ?";
            $stmt_lista = $conn->prepare($sql_productos_lista);
            $stmt_lista->bind_param("i", $id_cita);
            $stmt_lista->execute();
            $productos_cita = $stmt_lista->get_result();
            
            if ($productos_cita->num_rows > 0):
            ?>
            <table class="productos-cita-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Precio</th>
                        <th>Subtotal</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($prod = $productos_cita->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($prod['nombre']); ?></span>
                            <td><?php echo $prod['cantidad']; ?></span>
                            <td>$<?php echo number_format($prod['precio_unitario'], 2); ?></span>
                            <td>$<?php echo number_format($prod['subtotal'], 2); ?></span>
                            <td style="display: flex; gap: 5px; align-items: center;">
                                <!-- Botón para quitar UNA unidad -->
                                <button type="button" class="btn-quitar-uno"
                                onclick="quitarUnidadProducto(<?php echo $prod['id_producto']; ?>, '<?php echo addslashes($prod['nombre']); ?>', <?php echo $prod['cantidad']; ?>)"
                                style="background: #ff9800; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;">
                                ➖ Quitar 1
                                </button>
                                <!-- Botón para eliminar TODAS las unidades -->
                                <button type="button" class="btn-eliminar-producto"
                                onclick="eliminarProductoDeCita(<?php echo $prod['id_producto']; ?>, '<?php echo addslashes($prod['nombre']); ?>')"
                                style="background: #f44336; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;">
                                🗑️ Eliminar todo
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="sin-productos">No hay productos agregados a esta cita.</p>
            <?php endif; ?>
        </div>

        <!-- Agregar Servicio -->
        <?php if (in_array($_SESSION['rol'], ['super_admin', 'admin', 'veterinario'])): ?>
        <div class="section">
            <h3>➕ Agregar Servicio</h3>
            <form action="agregar_servicio_cita.php" method="POST">
                <input type="hidden" name="id_cita" value="<?php echo $id_cita; ?>">
                <div class="form-row">
                    <select name="id_servicio" id="select_servicio" required style="flex:2; padding:8px;">
                        <option value="">Seleccionar...</option>
                        <?php while($serv = $servicios_disponibles->fetch_assoc()): ?>
                            <option value="<?php echo $serv['id']; ?>" data-precio="<?php echo $serv['precio']; ?>"><?php echo $serv['nombre_servicio']; ?> - $<?php echo number_format($serv['precio'], 2); ?></option>
                        <?php endwhile; ?>
                    </select>
                    <input type="number" step="0.01" name="precio_fijado" id="precio_fijado" required readonly style="background:#f5f5f5; width:120px; padding:8px;">
                    <button type="submit" class="btn-small" style="background:#4caf50;">+ Agregar</button>
                </div>
            </form>
        </div>
        <script>
            document.getElementById('select_servicio').addEventListener('change', function() {
                const precio = this.options[this.selectedIndex].dataset.precio;
                document.getElementById('precio_fijado').value = precio || '';
            });
        </script>
        <?php endif; ?>

        <!-- SECCIÓN DE PAGO (unificada) -->
        <div class="section">
            <h3>💰 Pago Total</h3>
            <div class="info-row">
                <div class="info-label">Servicios:</div>
                <div class="info-value">$<?php echo number_format($cita['total_servicios'], 2); ?></div>
            </div>
            <?php if ($total_productos > 0): ?>
            <div class="info-row">
                <div class="info-label">Productos:</div>
                <div class="info-value">$<?php echo number_format($total_productos, 2); ?></div>
            </div>
            <?php endif; ?>
            <div class="info-row total-row">
                <div class="info-label"><strong>TOTAL A PAGAR:</strong></div>
                <div class="info-value"><strong>$<?php echo number_format($total_general, 2); ?></strong></div>
            </div>
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px;">
                <?php if ($pago_existente): ?>
                    <span style="background: #4caf50; color: white; padding: 10px 15px; border-radius: 5px;">✅ Cita pagada</span>
                <?php else: ?>
                    <button onclick="abrirModalProductos(<?php echo $id_cita; ?>)" class="btn-producto">
                        🛒 Agregar Productos
                    </button>
                    <button onclick="abrirModalPago(<?php echo $id_cita; ?>, <?php echo $total_general; ?>, <?php echo $cita['total_servicios']; ?>, <?php echo $total_productos; ?>)" class="btn-pago">
                        💰 Pagar Todo
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Botones de acción -->
        <div style="margin-top: 30px; display: flex; gap: 10px; flex-wrap: wrap;">
            <?php if (in_array($_SESSION['rol'], ['super_admin', 'admin', 'recepcionista'])): ?>
                <?php if ($cita['estado'] == 'pendiente'): ?>
                    <a href="actualizar_estado.php?id=<?php echo $id_cita; ?>&estado=confirmada" class="btn-back" style="background:#4caf50;">✅ Confirmar Cita</a>
                <?php endif; ?>
                <?php if ($cita_cancelable && $cita['estado'] != 'cancelada' && $cita['estado'] != 'completada'): ?>
                    <a href="actualizar_estado.php?id=<?php echo $id_cita; ?>&estado=cancelada" class="btn-back" style="background:#f44336;" onclick="return confirm('¿Cancelar esta cita?')">❌ Cancelar Cita</a>
                <?php endif; ?>
                <?php if ($cita['estado'] == 'confirmada'): ?>
                    <a href="actualizar_estado.php?id=<?php echo $id_cita; ?>&estado=completada" class="btn-back" style="background:#2196f3;">✓ Marcar Completada</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para Registrar Pago (unificado) -->
<div id="modalPago" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header" style="background: #4caf50;">
            <h2>💰 Pagar Cita</h2>
            <span class="close-modal" onclick="cerrarModal('modalPago')">&times;</span>
        </div>
        <div class="modal-body">
            <form action="actualizar_pago_cita.php" method="POST">
                <input type="hidden" name="id_cita" id="pago_cita_id">
                
                <!-- Servicios Solicitados -->
                <div class="form-group">
                    <label>📋 Servicios Solicitados:</label>
                    <div id="lista_servicios_pago" style="background: #f5f5f5; padding: 10px; border-radius: 5px; margin-top: 5px;">
                        <?php
                        // Obtener servicios de la cita
                        $sql_servicios_pago = "SELECT s.nombre_servicio, dc.precio_fijado 
                                               FROM DETALLE_CITA dc
                                               JOIN SERVICIO s ON dc.id_servicio = s.id
                                               WHERE dc.id_cita = ?";
                        $stmt_serv = $conn->prepare($sql_servicios_pago);
                        $stmt_serv->bind_param("i", $id_cita);
                        $stmt_serv->execute();
                        $servicios_pago = $stmt_serv->get_result();
                        
                        if ($servicios_pago->num_rows > 0):
                        ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: var(--primary); color: white;">
                                        <th style="padding: 8px; text-align: left;">Servicio</th>
                                        <th style="padding: 8px; text-align: right;">Precio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($serv = $servicios_pago->fetch_assoc()): ?>
                                    <tr>
                                        <td style="padding: 8px; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($serv['nombre_servicio']); ?></td>
                                        <td style="padding: 8px; text-align: right; border-bottom: 1px solid #eee;">$<?php echo number_format($serv['precio_fijado'], 2); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background: #f9f9f9; font-weight: bold;">
                                        <td style="padding: 8px;">Total Servicios:</td>
                                        <td style="padding: 8px; text-align: right;">$<?php echo number_format($cita['total_servicios'], 2); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php else: ?>
                        <p>No hay servicios solicitados</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Productos Agregados -->
                <div class="form-group" id="productos_pago_group">
                    <label>🛒 Productos Agregados:</label>
                    <div id="lista_productos_pago" style="background: #f5f5f5; padding: 10px; border-radius: 5px; margin-top: 5px;">
                        <?php
                        // Obtener productos de la cita
                        $sql_productos_pago = "SELECT p.nombre, dv.cantidad, dv.precio_unitario, dv.subtotal 
                                               FROM DETALLE_VENTA dv
                                               JOIN PRODUCTO p ON dv.id_producto = p.id
                                               JOIN VENTA_CITA vc ON vc.id_venta = dv.id_venta
                                               WHERE vc.id_cita = ?";
                        $stmt_prod_pago = $conn->prepare($sql_productos_pago);
                        $stmt_prod_pago->bind_param("i", $id_cita);
                        $stmt_prod_pago->execute();
                        $productos_pago = $stmt_prod_pago->get_result();
                        
                        if ($productos_pago->num_rows > 0):
                        ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: var(--primary); color: white;">
                                        <th style="padding: 8px; text-align: left;">Producto</th>
                                        <th style="padding: 8px; text-align: center;">Cantidad</th>
                                        <th style="padding: 8px; text-align: right;">Precio</th>
                                        <th style="padding: 8px; text-align: right;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($prod = $productos_pago->fetch_assoc()): ?>
                                    <tr>
                                        <td style="padding: 8px; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($prod['nombre']); ?></td>
                                        <td style="padding: 8px; text-align: center; border-bottom: 1px solid #eee;"><?php echo $prod['cantidad']; ?></td>
                                        <td style="padding: 8px; text-align: right; border-bottom: 1px solid #eee;">$<?php echo number_format($prod['precio_unitario'], 2); ?></td>
                                        <td style="padding: 8px; text-align: right; border-bottom: 1px solid #eee;">$<?php echo number_format($prod['subtotal'], 2); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background: #f9f9f9; font-weight: bold;">
                                        <td colspan="3" style="padding: 8px;">Total Productos:</td>
                                        <td style="padding: 8px; text-align: right;">$<?php echo number_format($total_productos, 2); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php else: ?>
                        <p>No hay productos agregados</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Total General -->
                <div class="form-group total-row">
                    <label><strong>💰 TOTAL A PAGAR:</strong></label>
                    <input type="text" id="monto_total_pago" readonly style="background:#f5f5f5; font-size: 1.2rem; font-weight: bold; color: var(--primary);">
                </div>
                
                <div class="form-group">
                    <label>Método de pago *</label>
                    <select name="metodo_pago" required>
                        <option value="">Seleccionar...</option>
                        <option value="efectivo">💵 Efectivo</option>
                        <option value="tarjeta">💳 Tarjeta</option>
                        <option value="transferencia">🏦 Transferencia</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Referencia (opcional)</label>
                    <input type="text" name="referencia" placeholder="Número de transferencia, último 4 dígitos de tarjeta">
                </div>
                
                <button type="submit" class="btn-guardar">Registrar Pago</button>
            </form>
        </div>
    </div>
</div>

    <!-- Modal para Agregar Productos al Carrito -->
    <div id="modalProductos" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header" style="background: #ff9800;">
                <h2>🛒 Agregar Productos</h2>
                <span class="close-modal" onclick="cerrarModal('modalProductos')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="productosLista">
                    <?php while($prod = $productos->fetch_assoc()): ?>
                    <div class="producto-item">
                        <div class="producto-info">
                            <div class="producto-nombre"><?php echo htmlspecialchars($prod['nombre']); ?></div>
                            <div class="producto-precio">$<?php echo number_format($prod['precio_venta'], 2); ?></div>
                            <div class="producto-stock">Stock: <?php echo $prod['stock_actual']; ?> unidades</div>
                        </div>
                        <div>
    <input type="number" id="cantidad_<?php echo $prod['id']; ?>" value="1" min="1" max="<?php echo $prod['stock_actual']; ?>" style="width: 60px; padding: 5px;">
    <button type="button" class="btn-agregar-producto" data-id="<?php echo $prod['id']; ?>" data-nombre="<?php echo htmlspecialchars($prod['nombre']); ?>" data-precio="<?php echo $prod['precio_venta']; ?>">+ Agregar</button>
</div>
                    </div>
                    <?php endwhile; ?>
                </div>
                
                <div class="productos-seleccionados" id="productosSeleccionados">
                    <h4>Productos seleccionados:</h4>
                    <div id="listaProductos"></div>
                    <div class="total-recibo" id="totalProductos">Total: $0.00</div>
                </div>
                
                <button type="button" id="btnAgregarCita" class="btn-guardar" style="background: #ff9800; margin-top: 15px;">✅ Agregar a la cita</button>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/detalle_cita.js"></script>
</body>
</html>