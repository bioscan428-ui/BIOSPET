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
if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'recepcionista'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_cliente = (int)($_GET['id'] ?? 0);
if (!$id_cliente) {
    header('Location: puntos_clientes.php');
    exit;
}

// Obtener datos del cliente
$sql_cliente = "SELECT c.*, 
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
                WHERE c.id = ? AND c.activo = 1";
$stmt = $conn->prepare($sql_cliente);
$stmt->bind_param("i", $id_cliente);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();

if (!$cliente) {
    header('Location: puntos_clientes.php');
    exit;
}

// Obtener configuración actual
$sql_config = "SELECT * FROM PROGRAMA_FIDELIDAD WHERE activo = 1 ORDER BY id DESC LIMIT 1";
$config = $conn->query($sql_config)->fetch_assoc();

$puntos_necesarios = $config['puntos_requeridos_canje'] ?? 100;
$valor_canje = $config['valor_canje'] ?? 50;
$puede_canjear = ($cliente['puntos_actuales'] >= $puntos_necesarios);

// Obtener movimientos de puntos
$sql_movimientos = "SELECT mp.*, 
                           v.folio as venta_folio,
                           v.total as venta_total
                    FROM MOVIMIENTO_PUNTOS mp
                    LEFT JOIN VENTA v ON mp.id_venta = v.id
                    WHERE mp.id_cliente = ?
                    ORDER BY mp.fecha_movimiento DESC
                    LIMIT 50";
$stmt_mov = $conn->prepare($sql_movimientos);
$stmt_mov->bind_param("i", $id_cliente);
$stmt_mov->execute();
$movimientos = $stmt_mov->get_result();

// Procesar canje de puntos
$mensaje = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'canjear') {
    if ($puede_canjear) {
        $conn->begin_transaction();
        
        try {
            // Registrar canje
            $sql_canje = "INSERT INTO CANJE_PUNTOS (id_cliente, puntos_usados, valor_descuento, tipo_canje, id_empleado) 
                          VALUES (?, ?, ?, 'descuento_venta', ?)";
            $stmt_canje = $conn->prepare($sql_canje);
            $empleado_id = $_SESSION['empleado_id'] ?? 1;
            $stmt_canje->bind_param("iidi", $id_cliente, $puntos_necesarios, $valor_canje, $empleado_id);
            $stmt_canje->execute();
            
            // Actualizar puntos del cliente
            $nuevos_puntos = $cliente['puntos_actuales'] - $puntos_necesarios;
            $sql_update = "UPDATE CLIENTE_PUNTOS SET puntos_actuales = ? WHERE id_cliente = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("ii", $nuevos_puntos, $id_cliente);
            $stmt_update->execute();
            
            // Registrar movimiento de puntos (gasto)
            $sql_movimiento = "INSERT INTO MOVIMIENTO_PUNTOS (id_cliente, tipo, puntos, saldo_antes, saldo_despues, concepto) 
                               VALUES (?, 'canjeados', ?, ?, ?, ?)";
            $stmt_movimiento = $conn->prepare($sql_movimiento);
            $concepto = "Canje de {$puntos_necesarios} puntos por \${$valor_canje} de descuento";
            $stmt_movimiento->bind_param("iiiis", $id_cliente, $puntos_necesarios, $cliente['puntos_actuales'], $nuevos_puntos, $concepto);
            $stmt_movimiento->execute();
            
            $conn->commit();
            
            $_SESSION['mensaje'] = "✅ ¡Canje exitoso! Se han canjeado {$puntos_necesarios} puntos por \${$valor_canje} de descuento.";
            header("Location: cliente_puntos.php?id={$id_cliente}");
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error al canjear puntos: " . $e->getMessage();
        }
    } else {
        $error = "No tienes suficientes puntos para canjear.";
    }
}

// Calcular progreso al siguiente nivel
$puntos_actuales = $cliente['puntos_actuales'];
if ($puntos_actuales < 100) {
    $siguiente_nivel = 'Plata';
    $puntos_faltantes = 100 - $puntos_actuales;
    $progreso = ($puntos_actuales / 100) * 100;
} elseif ($puntos_actuales < 300) {
    $siguiente_nivel = 'Oro';
    $puntos_faltantes = 300 - $puntos_actuales;
    $progreso = (($puntos_actuales - 100) / 200) * 100;
} elseif ($puntos_actuales < 500) {
    $siguiente_nivel = 'Platino';
    $puntos_faltantes = 500 - $puntos_actuales;
    $progreso = (($puntos_actuales - 300) / 200) * 100;
} else {
    $siguiente_nivel = null;
    $puntos_faltantes = 0;
    $progreso = 100;
}

$nivel_icono = [
    'bronce' => '🥉',
    'plata' => '🥈',
    'oro' => '🥇',
    'platino' => '💎'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Puntos de <?php echo htmlspecialchars($cliente['nombre']); ?> - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/cliente_puntos.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Puntos de Fidelidad</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="clientes.php">👥 Clientes</a>
            <a href="puntos_clientes.php">⭐ Todos los Clientes</a>
            <a href="fidelidad_config.php">⚙️ Configuración</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <a href="clientes.php" class="btn-volver">← Volver a Clientes</a>
        
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="alert-success"><?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Información del Cliente -->
        <div class="cliente-card">
            <div class="cliente-header">
                <h2><?php echo htmlspecialchars($cliente['nombre'] . ' ' . ($cliente['ape_pat'] ?? '') . ' ' . ($cliente['ape_mat'] ?? '')); ?></h2>
                <div class="nivel-badge nivel-<?php echo $cliente['nivel']; ?>">
                    <?php echo $nivel_icono[$cliente['nivel']] . ' ' . ucfirst($cliente['nivel']); ?>
                </div>
            </div>
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <span>📞 <?php echo $cliente['telefono'] ?: 'No registrado'; ?></span>
                <span>✉️ <?php echo $cliente['email'] ?: 'No registrado'; ?></span>
                <span>📅 Registrado: <?php echo date('d/m/Y', strtotime($cliente['fecha_registro'])); ?></span>
            </div>
        </div>

        <!-- Puntos actuales -->
        <div class="puntos-card">
            <div class="puntos-number"><?php echo number_format($cliente['puntos_actuales']); ?></div>
            <div class="puntos-label">⭐ Puntos Disponibles</div>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($cliente['puntos_acumulados']); ?></div>
                <div class="stat-label">📊 Puntos Acumulados (Historial)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $puntos_necesarios; ?></div>
                <div class="stat-label">🎯 Puntos para Canje</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($valor_canje, 2); ?></div>
                <div class="stat-label">💰 Valor del Canje</div>
            </div>
        </div>

        <!-- Progreso al siguiente nivel -->
        <?php if ($siguiente_nivel): ?>
        <div class="progress-section">
            <h3>📈 Progreso al nivel <?php echo $siguiente_nivel; ?></h3>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: <?php echo min($progreso, 100); ?>%;"></div>
            </div>
            <p style="margin-top: 10px; color: #666;">¡Faltan <strong><?php echo $puntos_faltantes; ?> puntos</strong> para alcanzar el nivel <?php echo $siguiente_nivel; ?>!</p>
        </div>
        <?php endif; ?>

        <!-- Sección de Canje -->
        <div class="canje-section">
            <h3>🎁 Canjear Puntos</h3>
            <p><strong><?php echo $puntos_necesarios; ?> puntos</strong> = <strong>$<?php echo number_format($valor_canje, 2); ?> de descuento</strong> en tu próxima compra o servicio.</p>
            
            <?php if ($puede_canjear): ?>
                <p style="color: #4caf50; margin: 10px 0;">✅ ¡Tienes suficientes puntos para canjear!</p>
                <form method="POST" onsubmit="return confirm('¿Estás seguro de canjear <?php echo $puntos_necesarios; ?> puntos por $<?php echo number_format($valor_canje, 2); ?> de descuento?')">
                    <input type="hidden" name="action" value="canjear">
                    <button type="submit" class="btn-canjear">🎁 Canjear Puntos Ahora</button>
                </form>
            <?php else: ?>
                <p style="color: #ff9800;">⚠️ Te faltan <strong><?php echo $puntos_necesarios - $cliente['puntos_actuales']; ?> puntos</strong> para poder canjear.</p>
                <button class="btn-canjear" disabled style="opacity: 0.5;">🎁 No disponible</button>
            <?php endif; ?>
        </div>

        <!-- Historial de Movimientos -->
        <h3 style="margin: 25px 0 15px;">📜 Historial de Movimientos</h3>
        
        <?php if ($movimientos->num_rows > 0): ?>
        <table class="movimientos-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Puntos</th>
                    <th>Saldo Anterior</th>
                    <th>Saldo Nuevo</th>
                    <th>Concepto</th>
                    <th>Vencimiento</th>
                </tr>
            </thead>
            <tbody>
                <?php while($mov = $movimientos->fetch_assoc()): 
                    $tipo_class = '';
                    $tipo_texto = '';
                    switch($mov['tipo']) {
                        case 'ganados':
                            $tipo_class = 'tipo-ganados';
                            $tipo_texto = '➕ Ganados';
                            break;
                        case 'canjeados':
                            $tipo_class = 'tipo-canjeados';
                            $tipo_texto = '➖ Canjeados';
                            break;
                        case 'vencidos':
                            $tipo_class = 'tipo-vencidos';
                            $tipo_texto = '⏰ Vencidos';
                            break;
                        default:
                            $tipo_class = '';
                            $tipo_texto = $mov['tipo'];
                    }
                ?>
                <tr>
                    <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])); ?></td>
                    <td class="<?php echo $tipo_class; ?>"><?php echo $tipo_texto; ?></td>
                    <td class="<?php echo $tipo_class; ?>"><?php echo $mov['puntos'] > 0 ? '+' . number_format($mov['puntos']) : number_format($mov['puntos']); ?></td>
                    <td><?php echo number_format($mov['saldo_antes']); ?></td>
                    <td><?php echo number_format($mov['saldo_despues']); ?></td>
                    <td><?php echo htmlspecialchars($mov['concepto'] ?: '—'); ?></td>
                    <td>
                        <?php if ($mov['fecha_vencimiento']): ?>
                            <?php echo date('d/m/Y', strtotime($mov['fecha_vencimiento'])); ?>
                            <?php if (strtotime($mov['fecha_vencimiento']) < time()): ?>
                                <span style="color: #f44336;"> (Vencidos)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="background: white; padding: 40px; text-align: center; border-radius: 12px; color: #999;">
            No hay movimientos de puntos registrados.
        </div>
        <?php endif; ?>

        <!-- Reglas del programa -->
        <div style="background: #f9f9f9; border-radius: 12px; padding: 20px; margin-top: 25px;">
            <h4>📋 Resumen del Programa de Fidelidad</h4>
            <ul style="margin-left: 20px; line-height: 1.8;">
                <li>💰 Se acumulan <strong><?php echo $config['puntos_por_gasto']; ?> puntos</strong> por cada $10 gastados.</li>
                <li>🎯 <strong><?php echo $puntos_necesarios; ?> puntos</strong> = <strong>$<?php echo number_format($valor_canje, 2); ?> de descuento</strong>.</li>
                <li>⭐ Niveles: Bronce (0-99), Plata (100-299), Oro (300-499), Platino (500+).</li>
                <li>📅 Los puntos tienen vigencia de 1 año.</li>
                <li>🔁 Los puntos no son transferibles.</li>
            </ul>
        </div>
    </div>
</body>
</html>