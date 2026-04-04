<?php
session_start();

// Verificar que el usuario haya iniciado sesión (usando user_id)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol para acceso (super_admin, admin, veterinario, asistente, recepcionista pueden ver detalles)
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

// Obtener datos de la cita (INCLUYENDO foto y género)
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle Cita #<?php echo $id_cita; ?> - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { background: var(--muted); }
        .admin-header { background: var(--primary); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .admin-header a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 800px; margin: 20px auto; padding: 20px; background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-soft); }
        .section { margin-bottom: 30px; }
        .section h3 { color: var(--primary); border-bottom: 2px solid var(--primary); padding-bottom: 10px; margin-bottom: 15px; }
        .info-row { display: flex; margin-bottom: 10px; }
        .info-label { width: 150px; font-weight: bold; }
        .info-value { flex: 1; }
        .estado { display: inline-block; padding: 4px 12px; border-radius: 20px; color: white; }
        .estado-pendiente { background: #ff9800; }
        .estado-confirmada { background: #4caf50; }
        .estado-cancelada { background: #f44336; }
        .estado-completada { background: #2196f3; }
        .btn-back { background: var(--primary); color: white; padding: 10px 20px; border-radius: var(--radius-sm); text-decoration: none; display: inline-block; }
        .btn-small { background: var(--primary); color: white; padding: 5px 10px; border-radius: var(--radius-sm); text-decoration: none; font-size: 12px; display: inline-block; }
        .sin-servicios { color: #999; font-style: italic; }
        .form-row { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
        select, input { padding: 8px; border: 1px solid #ddd; border-radius: var(--radius-sm); }
        .foto-mascota {
            text-align: center;
            margin-bottom: 20px;
        }
        .foto-mascota img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 15px;
            box-shadow: var(--shadow-soft);
            object-fit: cover;
        }
        .sintomas-box {
            background: #f9f9f9;
            padding: 15px;
            border-radius: var(--radius-sm);
            border-left: 4px solid var(--primary);
            margin-top: 10px;
        }
    </style>
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
            
            <!-- Enlace al carnet -->
            <div class="info-row" style="margin-top: 15px; border-top: 1px dashed #ddd; padding-top: 15px;">
                <div class="info-label">Carnet:</div>
                <div class="info-value">
                    <a href="../carnet_mascota.php?id=<?php echo $cita['id_mascota']; ?>" class="btn-small" target="_blank" style="background: var(--primary);">
                        📄 Ver Carnet Digital
                    </a>
                    <!-----DESCARGAR EN PDF----->
                    <a href="../carnet_pdf.php?id=<?php echo $cita['id_mascota']; ?>" class="btn-small" target="_blank" style="background: #4caf50;">
                        📑 Descargar PDF
                    </a>
                    <!-----FIN DE DESCARGA EN PDF----->
                </div>
            </div>
        </div>

        <div class="section">
            <h3>📋 Motivo de Consulta / Síntomas</h3>
            <div class="sintomas-box">
                <?php echo nl2br(htmlspecialchars($cita['notas'] ?: 'No se especificaron síntomas o motivo de consulta.')); ?>
            </div>
        </div>

        <!-----SECCION PARA ASIGNAR VETERINARIO----->
        <?php if (in_array($_SESSION['rol'], ['super_admin', 'admin'])): ?>
        <div class="section">
            <h3>👨‍⚕️ Asignar Veterinario</h3>
    
            <?php
            // Obtener veterinario actualmente asignado
            $sql_asignado = "SELECT e.id, e.nombre, e.ape_pat, e.especialidad 
                            FROM ASIGNACION_CITA ac
                            JOIN EMPLEADO e ON ac.id_empleado = e.id
                            WHERE ac.id_cita = ? AND ac.rol_asignado = 'veterinario'";
            $stmt_asig = $conn->prepare($sql_asignado);
            $stmt_asig->bind_param("i", $id_cita);
            $stmt_asig->execute();
            $result_asig = $stmt_asig->get_result();
            $asignado = $result_asig->fetch_assoc();
    
            // Obtener veterinarios disponibles
            $sql_vets = "SELECT e.id, e.nombre, e.ape_pat, e.especialidad 
                    FROM EMPLEADO e
                    JOIN USUARIO u ON e.id = u.id_empleado
                    WHERE e.puesto = 'veterinario' AND e.activo = 1 AND u.activo = 1
                    ORDER BY e.nombre";
            $veterinarios = $conn->query($sql_vets);
            ?>
    
            <?php if ($asignado): ?>
                <div class="info-row" style="margin-bottom: 15px;">
                    <div class="info-label">Veterinario asignado:</div>
                    <div class="info-value">
                        🩺 Dr/a. <?php echo $asignado['nombre'] . ' ' . $asignado['ape_pat']; ?>
                        <?php if ($asignado['especialidad']): ?>
                            <small>(<?php echo $asignado['especialidad']; ?>)</small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
    
            <form action="asignar_veterinario.php" method="POST" style="margin-top: 15px;">
                <input type="hidden" name="id_cita" value="<?php echo $id_cita; ?>">
                <div class="form-row" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 2;">
                        <label>Seleccionar veterinario:</label>
                        <select name="id_veterinario" required style="width: 100%; padding: 8px;">
                            <option value="">Seleccionar...</option>
                            <?php while($vet = $veterinarios->fetch_assoc()): ?>
                                <option value="<?php echo $vet['id']; ?>" <?php echo ($asignado && $asignado['id'] == $vet['id']) ? 'selected' : ''; ?>>
                                    Dr/a. <?php echo $vet['nombre'] . ' ' . $vet['ape_pat']; ?>
                                    <?php if ($vet['especialidad']): ?>
                                        (<?php echo $vet['especialidad']; ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn-small" style="background: var(--primary);">Asignar</button>
                    </div>
                </div>
            </form>
    
            <?php if (!$asignado && $cita['estado'] == 'confirmada'): ?>
                <p style="color: #ff9800; font-size: 12px; margin-top: 10px;">
                    ⚠️ Esta cita está confirmada pero no tiene veterinario asignado.
                </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <!-----FIN DE SECCION PARA ASIGNAR VETERINARIO----->

        <div class="section">
            <h3>💊 Servicios Solicitados</h3>
            <div class="info-row">
                <div class="info-label">Servicios:</div>
                <div class="info-value">
                    <?php if (!empty($cita['servicios'])): ?>
                        <?php echo $cita['servicios']; ?>
                    <?php else: ?>
                        <span class="sin-servicios">(Sin servicios asignados)</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Total:</div>
                <div class="info-value"><strong>$<?php echo number_format($cita['total'], 2); ?></strong></div>
            </div>
        </div>

        <!-----SECCION PARA ASIGNAR SERVICIOS----->
        <!-- Sección para agregar servicios (solo para roles con permisos) -->
        <?php if (in_array($_SESSION['rol'], ['super_admin', 'admin', 'veterinario'])): ?>
        <div class="section">
            <h3>➕ Agregar Servicio</h3>
            <?php
            // Obtener servicios disponibles del catálogo
            $sql_servicios = "SELECT id, nombre_servicio, precio FROM SERVICIO WHERE activo = 1 ORDER BY nombre_servicio";
            $servicios_disponibles = $conn->query($sql_servicios);
            ?>
            <form action="agregar_servicio_cita.php" method="POST" style="margin-top: 15px;">
                <input type="hidden" name="id_cita" value="<?php echo $id_cita; ?>">
                <div class="form-row" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 2;">
                        <label>Seleccionar servicio:</label>
                        <select name="id_servicio" id="select_servicio" required style="width: 100%; padding: 8px;">
                            <option value="">Seleccionar...</option>
                            <?php while($serv = $servicios_disponibles->fetch_assoc()): ?>
                                <option value="<?php echo $serv['id']; ?>" data-precio="<?php echo $serv['precio']; ?>">
                                    <?php echo $serv['nombre_servicio']; ?> - $<?php echo number_format($serv['precio'], 2); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Precio fijado:</label>
                        <input type="number" step="0.01" name="precio_fijado" id="precio_fijado" required readonly style="background: #f5f5f5; padding: 8px; width: 120px;">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn-small" style="background: #4caf50; padding: 8px 16px;">+ Agregar Servicio</button>
                    </div>
                </div>
            </form>
        </div>

        <script>
            // Al seleccionar servicio, cargar su precio
            document.getElementById('select_servicio').addEventListener('change', function() {
                const selected = this.options[this.selectedIndex];
                const precio = selected.dataset.precio;
                if (precio) {
                    document.getElementById('precio_fijado').value = precio;
                } else {
                    document.getElementById('precio_fijado').value = '';
                }
            });
        </script>
        <?php endif; ?>
        <!-----FIN DE SECCION PARA ASIGNAR SERVICIOS----->

        <!-- Botones de acción según rol -->
        <div style="margin-top: 30px; display: flex; gap: 10px; flex-wrap: wrap;">
            <?php if (in_array($_SESSION['rol'], ['super_admin', 'admin', 'recepcionista'])): ?>
                <?php if ($cita['estado'] == 'pendiente'): ?>
                    <a href="actualizar_estado.php?id=<?php echo $id_cita; ?>&estado=confirmada" class="btn-back" style="background:#4caf50;">✅ Confirmar Cita</a>
                <?php endif; ?>
                <?php if ($cita['estado'] != 'cancelada' && $cita['estado'] != 'completada'): ?>
                    <a href="actualizar_estado.php?id=<?php echo $id_cita; ?>&estado=cancelada" class="btn-back" style="background:#f44336;">❌ Cancelar Cita</a>
                <?php endif; ?>
                <?php if ($cita['estado'] == 'confirmada'): ?>
                    <a href="actualizar_estado.php?id=<?php echo $id_cita; ?>&estado=completada" class="btn-back" style="background:#2196f3;">✓ Marcar Completada</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>