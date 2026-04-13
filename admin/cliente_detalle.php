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

$id_cliente = (int)($_GET['id'] ?? 0);
if (!$id_cliente) {
    header('Location: clientes.php');
    exit;
}

// ========== USANDO vista_clientes_activos (resumen de actividad) ==========
$sql_cliente = "SELECT * FROM vista_clientes_activos WHERE id = ?";
$stmt = $conn->prepare($sql_cliente);
$stmt->bind_param("i", $id_cliente);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();

if (!$cliente) {
    // Si no está en la vista activos, buscar en CLIENTE directamente
    $sql_cliente_base = "SELECT * FROM CLIENTE WHERE id = ?";
    $stmt_base = $conn->prepare($sql_cliente_base);
    $stmt_base->bind_param("i", $id_cliente);
    $stmt_base->execute();
    $cliente = $stmt_base->get_result()->fetch_assoc();
    
    if (!$cliente) {
        die('Cliente no encontrado');
    }
    // Inicializar valores por defecto
    $cliente['total_mascotas'] = 0;
    $cliente['total_citas'] = 0;
    $cliente['citas_completadas'] = 0;
    $cliente['total_gastado'] = 0;
}

// ========== USANDO vista_cliente_fidelidad (puntos y nivel) ==========
$sql_fidelidad = "SELECT * FROM vista_cliente_fidelidad WHERE cliente_id = ?";
$stmt_fid = $conn->prepare($sql_fidelidad);
$stmt_fid->bind_param("i", $id_cliente);
$stmt_fid->execute();
$fidelidad = $stmt_fid->get_result()->fetch_assoc();

// Obtener mascotas del cliente
$sql_mascotas = "SELECT * FROM MASCOTA WHERE id_cliente = ? AND activo = 1 ORDER BY nombre_mascota";
$stmt_masc = $conn->prepare($sql_mascotas);
$stmt_masc->bind_param("i", $id_cliente);
$stmt_masc->execute();
$mascotas = $stmt_masc->get_result();

// Obtener citas recientes del cliente (usando vista_citas_completas)
$sql_citas = "SELECT * FROM vista_citas_completas WHERE cliente_id = ? ORDER BY fecha_cita DESC, hora_cita DESC LIMIT 10";
$stmt_citas = $conn->prepare($sql_citas);
$stmt_citas->bind_param("i", $id_cliente);
$stmt_citas->execute();
$citas_recientes = $stmt_citas->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Cliente - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/cliente_detalle.css">
    
</head>
<body>
    <div class="container">
        <a href="clientes.php" class="btn-volver">← Volver a Clientes</a>
        
        <!-- Información del Cliente (usando vista_clientes_activos) -->
        <div class="cliente-card">
            <div class="cliente-header">
                <h1>🐾 <?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['ape_pat'] . ' ' . $cliente['ape_mat']); ?></h1>
                <?php if ($fidelidad): ?>
                <div class="nivel-badge">
                    <?php 
                    $iconos = ['bronce' => '🥉', 'plata' => '🥈', 'oro' => '🥇', 'platino' => '💎'];
                    echo $iconos[$fidelidad['nivel']] . ' ' . ucfirst($fidelidad['nivel']);
                    ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Teléfono:</span>
                    <span class="info-value"><?php echo $cliente['telefono'] ?: 'No registrado'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo $cliente['email'] ?: 'No registrado'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Fecha registro:</span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($cliente['fecha_registro'] ?? 'now')); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Estado:</span>
                    <span class="info-value"><?php echo ($cliente['activo'] ?? 1) ? '✅ Activo' : '❌ Inactivo'; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Tarjetas de estadísticas (usando vista_clientes_activos) -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $cliente['total_mascotas'] ?? 0; ?></div>
                <div class="stat-label">🐕 Mascotas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $cliente['total_citas'] ?? 0; ?></div>
                <div class="stat-label">📋 Total Citas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $cliente['citas_completadas'] ?? 0; ?></div>
                <div class="stat-label">✅ Citas Completadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($cliente['total_gastado'] ?? 0, 2); ?></div>
                <div class="stat-label">💰 Total Gastado</div>
            </div>
        </div>
        
        <!-- Tarjetas de Fidelidad (usando vista_cliente_fidelidad) -->
        <?php if ($fidelidad): ?>
        <div class="stats-grid">
            <div class="stat-card puntos-card">
                <div class="stat-number"><?php echo number_format($fidelidad['puntos_actuales']); ?></div>
                <div class="stat-label">⭐ Puntos Acumulados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $fidelidad['puntos_acumulados'] ?? 0; ?></div>
                <div class="stat-label">📊 Puntos Históricos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $fidelidad['descuento_maximo']; ?>%</div>
                <div class="stat-label">🎯 Descuento Máximo</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $fidelidad['multiplicador_puntos']; ?>x</div>
                <div class="stat-label">⚡ Multiplicador de Puntos</div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Mascotas del Cliente -->
        <div class="seccion">
            <h2>🐕 Mascotas</h2>
            <?php if ($mascotas->num_rows > 0): ?>
                <table class="mascotas-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Especie</th>
                            <th>Raza</th>
                            <th>Género</th>
                            <th>Edad</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($mascota = $mascotas->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($mascota['nombre_mascota']); ?></strong></td>
                            <td><?php echo $mascota['especie']; ?></td>
                            <td><?php echo $mascota['raza'] ?: '—'; ?></td>
                            <td><?php echo $mascota['genero'] == 'MACHO' ? '♂️ Macho' : '♀️ Hembra'; ?></td>
                            <td>
                                <?php 
                                if ($mascota['fecha_nacimiento']) {
                                    $edad = date_diff(date_create($mascota['fecha_nacimiento']), date_create('today'))->y;
                                    echo $edad . ' años';
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td>
                                <a href="historial_mascota.php?id=<?php echo $mascota['id']; ?>" class="btn-small">Ver Historial</a>
                            </span>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #999;">No hay mascotas registradas.</p>
            <?php endif; ?>
        </div>
        
        <!-- Citas Recientes (usando vista_citas_completas) -->
        <div class="seccion">
            <h2>📋 Citas Recientes</h2>
            <?php if ($citas_recientes->num_rows > 0): ?>
                <table class="citas-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Mascota</th>
                            <th>Servicios</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($cita = $citas_recientes->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
                            <td><?php echo substr($cita['hora_cita'], 0, 5); ?></td>
                            <td><?php echo htmlspecialchars($cita['nombre_mascota']); ?></td>
                            <td><?php echo substr($cita['servicios'], 0, 50) . (strlen($cita['servicios']) > 50 ? '...' : ''); ?></td>
                            <td>$<?php echo number_format($cita['total_cobrado'], 2); ?></td>
                            <td><span class="estado-<?php echo $cita['estado']; ?>"><?php echo ucfirst($cita['estado']); ?></span></td>
                            <td><a href="detalle_cita.php?id=<?php echo $cita['cita_id']; ?>" class="btn-small">Ver</a></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #999;">No hay citas registradas.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>