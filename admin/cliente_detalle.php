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

// ========== OBTENER DATOS DEL CLIENTE EN TIEMPO REAL ==========
// Datos básicos del cliente
$sql_cliente_base = "SELECT * FROM CLIENTE WHERE id = ?";
$stmt_base = $conn->prepare($sql_cliente_base);
$stmt_base->bind_param("i", $id_cliente);
$stmt_base->execute();
$cliente = $stmt_base->get_result()->fetch_assoc();

if (!$cliente) {
    die('Cliente no encontrado');
}

// ========== CALCULAR ESTADÍSTICAS EN TIEMPO REAL ==========

// Total de mascotas
$sql_mascotas = "SELECT COUNT(*) as total FROM MASCOTA WHERE id_cliente = ? AND activo = 1";
$stmt_masc = $conn->prepare($sql_mascotas);
$stmt_masc->bind_param("i", $id_cliente);
$stmt_masc->execute();
$cliente['total_mascotas'] = $stmt_masc->get_result()->fetch_assoc()['total'];

// Total de citas
$sql_citas_total = "SELECT COUNT(*) as total 
                    FROM CITA c
                    JOIN MASCOTA m ON c.id_mascota = m.id
                    WHERE m.id_cliente = ?";
$stmt_citas_total = $conn->prepare($sql_citas_total);
$stmt_citas_total->bind_param("i", $id_cliente);
$stmt_citas_total->execute();
$cliente['total_citas'] = $stmt_citas_total->get_result()->fetch_assoc()['total'];

// Citas completadas
$sql_completadas = "SELECT COUNT(*) as total 
                    FROM CITA c
                    JOIN MASCOTA m ON c.id_mascota = m.id
                    WHERE m.id_cliente = ? AND c.estado = 'completada'";
$stmt_completadas = $conn->prepare($sql_completadas);
$stmt_completadas->bind_param("i", $id_cliente);
$stmt_completadas->execute();
$cliente['citas_completadas'] = $stmt_completadas->get_result()->fetch_assoc()['total'];

// Total gastado en ventas de productos
$sql_ventas = "SELECT COALESCE(SUM(total), 0) as total FROM VENTA WHERE id_cliente = ? AND estado = 'completada'";
$stmt_ventas = $conn->prepare($sql_ventas);
$stmt_ventas->bind_param("i", $id_cliente);
$stmt_ventas->execute();
$total_ventas = $stmt_ventas->get_result()->fetch_assoc()['total'];

// Total gastado en servicios (citas completadas)
$sql_servicios = "SELECT COALESCE(SUM(dc.precio_fijado), 0) as total 
                  FROM CITA c
                  JOIN MASCOTA m ON c.id_mascota = m.id
                  JOIN DETALLE_CITA dc ON c.id = dc.id_cita
                  WHERE m.id_cliente = ? AND c.estado = 'completada'";
$stmt_servicios = $conn->prepare($sql_servicios);
$stmt_servicios->bind_param("i", $id_cliente);
$stmt_servicios->execute();
$total_servicios = $stmt_servicios->get_result()->fetch_assoc()['total'];

$cliente['total_gastado'] = $total_ventas + $total_servicios;

// ========== OBTENER DATOS DE FIDELIDAD ==========
$sql_fidelidad = "SELECT 
                    COALESCE(cp.puntos_actuales, 0) as puntos_actuales,
                    COALESCE(cp.puntos_acumulados_historial, 0) as puntos_acumulados,
                    cp.ultima_actualizacion,
                    CASE 
                        WHEN COALESCE(cp.puntos_actuales, 0) >= 500 THEN 'platino'
                        WHEN COALESCE(cp.puntos_actuales, 0) >= 300 THEN 'oro'
                        WHEN COALESCE(cp.puntos_actuales, 0) >= 100 THEN 'plata'
                        ELSE 'bronce'
                    END as nivel
                  FROM CLIENTE c
                  LEFT JOIN CLIENTE_PUNTOS cp ON c.id = cp.id_cliente
                  WHERE c.id = ?";
$stmt_fid = $conn->prepare($sql_fidelidad);
$stmt_fid->bind_param("i", $id_cliente);
$stmt_fid->execute();
$fidelidad = $stmt_fid->get_result()->fetch_assoc();

if (!$fidelidad) {
    $fidelidad = [
        'puntos_actuales' => 0,
        'puntos_acumulados' => 0,
        'nivel' => 'bronce'
    ];
}

// Obtener mascotas del cliente (para la tabla)
$sql_mascotas_lista = "SELECT * FROM MASCOTA WHERE id_cliente = ? AND activo = 1 ORDER BY nombre_mascota";
$stmt_masc_lista = $conn->prepare($sql_mascotas_lista);
$stmt_masc_lista->bind_param("i", $id_cliente);
$stmt_masc_lista->execute();
$mascotas = $stmt_masc_lista->get_result();

// Obtener citas recientes del cliente
$sql_citas = "SELECT 
                c.id,
                c.fecha_cita,
                c.hora_cita,
                c.estado,
                m.nombre_mascota,
                GROUP_CONCAT(s.nombre_servicio SEPARATOR ', ') as servicios,
                SUM(dc.precio_fijado) as total_cobrado
              FROM CITA c
              JOIN MASCOTA m ON c.id_mascota = m.id
              JOIN DETALLE_CITA dc ON c.id = dc.id_cita
              JOIN SERVICIO s ON dc.id_servicio = s.id
              WHERE m.id_cliente = ?
              GROUP BY c.id
              ORDER BY c.fecha_cita DESC, c.hora_cita DESC
              LIMIT 10";
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
        
        <!-- Información del Cliente -->
        <div class="cliente-card">
            <div class="cliente-header">
                <h1>🐾 <?php echo htmlspecialchars($cliente['nombre'] . ' ' . ($cliente['ape_pat'] ?? '') . ' ' . ($cliente['ape_mat'] ?? '')); ?></h1>
                <div class="nivel-badge nivel-<?php echo $fidelidad['nivel']; ?>">
                    <?php 
                    $iconos = ['bronce' => '🥉', 'plata' => '🥈', 'oro' => '🥇', 'platino' => '💎'];
                    echo $iconos[$fidelidad['nivel']] . ' ' . ucfirst($fidelidad['nivel']);
                    ?>
                </div>
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
        
        <!-- Tarjetas de estadísticas -->
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
        
        <!-- Tarjetas de Fidelidad -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($fidelidad['puntos_actuales']); ?></div>
                <div class="stat-label">⭐ Puntos Disponibles</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($fidelidad['puntos_acumulados']); ?></div>
                <div class="stat-label">📊 Puntos Históricos</div>
            </div>
        </div>
        
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
        
        <!-- Citas Recientes -->
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
                            <td><a href="detalle_cita.php?id=<?php echo $cita['id']; ?>" class="btn-small">Ver</a></td>
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