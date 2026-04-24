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

// Verificar si la cita tiene pago registrado
$sql_pago = "SELECT * FROM PAGO_CITA WHERE id_cita = ?";
$stmt_pago = $conn->prepare($sql_pago);
$stmt_pago->bind_param("i", $id_cita);
$stmt_pago->execute();
$pago = $stmt_pago->get_result()->fetch_assoc();

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
    <style>
        /* Estilos para modales */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }
        .modal-content {
            background-color: white;
            margin: 50px auto;
            padding: 0;
            width: 90%;
            max-width: 500px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-header {
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h2 { margin: 0; color: white; font-size: 1.2rem; }
        .modal-body { padding: 20px; }
        .close-modal { color: white; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-modal:hover { color: #ddd; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn-guardar { background: var(--primary); color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        
        /* Botones de pago */
        .btn-pago { background: #4caf50; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-size: 12px; }
        .btn-recibo { background: #2196f3; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-block; }
        .btn-recibo-disabled { background: #ccc; color: #666; padding: 8px 15px; border-radius: 5px; font-size: 12px; cursor: not-allowed; display: inline-block; }
        
        /* Productos en el modal */
        .producto-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .producto-item:hover { background: #f9f9f9; }
        .producto-info { flex: 2; }
        .producto-nombre { font-weight: bold; }
        .producto-precio { color: var(--primary); }
        .producto-stock { font-size: 11px; color: #666; }
        .btn-agregar-producto { background: #4caf50; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; font-size: 11px; }
        .productos-seleccionados { margin-top: 15px; }
        .producto-seleccionado {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            background: #f5f5f5;
            margin-bottom: 5px;
            border-radius: 5px;
        }
        .btn-eliminar-producto { background: #f44336; color: white; border: none; padding: 3px 8px; border-radius: 3px; cursor: pointer; font-size: 10px; }
        .total-recibo { text-align: right; font-size: 1.2rem; font-weight: bold; margin-top: 15px; padding-top: 10px; border-top: 2px solid #eee; }
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
        <!-- Mostrar mensajes de éxito/error -->
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
            <?php if ($pago): ?>
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

        <!-- SECCIÓN: PAGO Y RECIBO -->
        <div class="section">
            <h3>💰 Pago y Recibo</h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php if ($pago): ?>
                    <span style="background: #4caf50; color: white; padding: 10px 15px; border-radius: 5px;">✅ Cita pagada - Total: $<?php echo number_format($cita['total'], 2); ?></span>
                    <a href="generar_recibo_cita.php?id=<?php echo $id_cita; ?>" class="btn-recibo" target="_blank">🧾 Generar Recibo</a>
                <?php else: ?>
                    <button onclick="abrirModalPago(<?php echo $id_cita; ?>, <?php echo $cita['total']; ?>)" class="btn-pago">
                        💰 Registrar Pago
                    </button>
                    <button onclick="abrirModalTienda(<?php echo $id_cita; ?>)" class="btn-pago" style="background: #ff9800;">
                        🛒 Agregar Productos
                    </button>
                    <span class="btn-recibo-disabled">🧾 Generar Recibo (Registre el pago primero)</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Botones de acción (Confirmar, Cancelar, Completar) -->
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

    <!-- Modal para Registrar Pago -->
    <div id="modalPago" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="background: #4caf50;">
                <h2>💰 Registrar Pago</h2>
                <span class="close-modal" onclick="cerrarModal('modalPago')">&times;</span>
            </div>
            <div class="modal-body">
                <form action="registrar_pago_cita.php" method="POST">
                    <input type="hidden" name="id_cita" id="pago_cita_id">
                    <div class="form-group">
                        <label>Monto total de la cita:</label>
                        <input type="text" id="monto_total" readonly style="background:#f5f5f5;">
                    </div>
                    <div class="form-group">
                        <label>Monto a pagar *</label>
                        <input type="number" step="0.01" name="monto" id="monto_pago" required>
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

    <!-- Modal para Agregar Productos de la Tienda -->
    <div id="modalTienda" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header" style="background: #ff9800;">
                <h2>🛒 Agregar Productos a la Venta</h2>
                <span class="close-modal" onclick="cerrarModal('modalTienda')">&times;</span>
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
                            <button onclick="agregarProductoCarrito(<?php echo $prod['id']; ?>, '<?php echo addslashes($prod['nombre']); ?>', <?php echo $prod['precio_venta']; ?>)" class="btn-agregar-producto">+ Agregar</button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                
                <div class="productos-seleccionados" id="productosSeleccionados">
                    <h4>Productos seleccionados:</h4>
                    <div id="listaProductos"></div>
                    <div class="total-recibo" id="totalProductos">Total: $0.00</div>
                </div>
                
                <form action="registrar_venta_cita.php" method="POST" id="formVentaCita" style="margin-top: 15px;">
                    <input type="hidden" name="id_cita" id="venta_cita_id">
                    <input type="hidden" name="productos_json" id="productos_json">
                    <button type="submit" class="btn-guardar" style="background: #ff9800;">Registrar Venta</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        let carritoProductos = [];
        let citaIdActual = 0;
        
        function abrirModalPago(citaId, total) {
            document.getElementById('pago_cita_id').value = citaId;
            document.getElementById('monto_total').value = '$' + total.toFixed(2);
            document.getElementById('monto_pago').value = total;
            document.getElementById('modalPago').style.display = 'block';
        }
        
        function abrirModalTienda(citaId) {
            citaIdActual = citaId;
            document.getElementById('venta_cita_id').value = citaId;
            carritoProductos = [];
            actualizarListaProductos();
            document.getElementById('modalTienda').style.display = 'block';
        }
        
        function agregarProductoCarrito(id, nombre, precio) {
            const cantidadInput = document.getElementById('cantidad_' + id);
            const cantidad = parseInt(cantidadInput.value);
            
            if (cantidad < 1) {
                alert('La cantidad debe ser al menos 1');
                return;
            }
            
            const existe = carritoProductos.find(p => p.id === id);
            if (existe) {
                existe.cantidad += cantidad;
            } else {
                carritoProductos.push({ id: id, nombre: nombre, precio: precio, cantidad: cantidad });
            }
            actualizarListaProductos();
        }
        
        function eliminarProductoCarrito(index) {
            carritoProductos.splice(index, 1);
            actualizarListaProductos();
        }
        
        function actualizarListaProductos() {
            const listaDiv = document.getElementById('listaProductos');
            const totalSpan = document.getElementById('totalProductos');
            let total = 0;
            
            if (carritoProductos.length === 0) {
                listaDiv.innerHTML = '<p style="color: #999;">No hay productos seleccionados</p>';
                totalSpan.innerHTML = 'Total: $0.00';
                document.getElementById('productos_json').value = '';
                return;
            }
            
            let html = '';
            carritoProductos.forEach((item, index) => {
                const subtotal = item.precio * item.cantidad;
                total += subtotal;
                html += `
                    <div class="producto-seleccionado">
                        <div>
                            <strong>${item.nombre}</strong><br>
                            ${item.cantidad} x $${item.precio.toFixed(2)} = <strong>$${subtotal.toFixed(2)}</strong>
                        </div>
                        <button onclick="eliminarProductoCarrito(${index})" class="btn-eliminar-producto">🗑️</button>
                    </div>
                `;
            });
            
            listaDiv.innerHTML = html;
            totalSpan.innerHTML = `Total: $${total.toFixed(2)}`;
            
            const productosJSON = carritoProductos.map(item => ({
                id_producto: item.id,
                cantidad: item.cantidad,
                descuento: 0
            }));
            document.getElementById('productos_json').value = JSON.stringify(productosJSON);
        }
        
        function cerrarModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modalPago = document.getElementById('modalPago');
            const modalTienda = document.getElementById('modalTienda');
            if (event.target == modalPago) modalPago.style.display = 'none';
            if (event.target == modalTienda) modalTienda.style.display = 'none';
        }
    </script>
</body>
</html>