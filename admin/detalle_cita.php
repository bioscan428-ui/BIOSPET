<?php
session_start();

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
            IFNULL(SUM(dc.precio_fijado), 0) AS total
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

        <!-- Servicios Solicitados -->
        <div class="section">
            <h3>💊 Servicios Solicitados</h3>
            <div class="info-row">
                <div class="info-label">Servicios:</div>
                <div class="info-value"><?php echo !empty($cita['servicios']) ? $cita['servicios'] : '<span class="sin-servicios">(Sin servicios asignados)</span>'; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Total:</div>
                <div class="info-value"><strong>$<?php echo number_format($cita['total'], 2); ?></strong></div>
            </div>
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
</body>
</html>