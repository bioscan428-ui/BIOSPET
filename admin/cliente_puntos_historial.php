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
                WHERE c.id = $id_cliente AND c.activo = 1";
$result_cliente = $conn->query($sql_cliente);
$cliente = $result_cliente->fetch_assoc();

if (!$cliente) {
    header('Location: puntos_clientes.php');
    exit;
}

// Obtener configuración actual
$sql_config = "SELECT * FROM PROGRAMA_FIDELIDAD WHERE activo = 1 ORDER BY id DESC LIMIT 1";
$config = $conn->query($sql_config)->fetch_assoc();

// Obtener historial de movimientos de puntos
$sql_movimientos = "SELECT mp.*, 
                            v.folio as venta_folio,
                            v.total as venta_total
                    FROM MOVIMIENTO_PUNTOS mp
                    LEFT JOIN VENTA v ON mp.id_venta = v.id
                    WHERE mp.id_cliente = $id_cliente
                    ORDER BY mp.fecha_movimiento DESC";
$movimientos = $conn->query($sql_movimientos);

// Obtener estadísticas del cliente
$sql_stats = "SELECT 
                    COUNT(*) as total_movimientos,
                    SUM(CASE WHEN tipo = 'ganados' THEN puntos ELSE 0 END) as puntos_ganados,
                    SUM(CASE WHEN tipo = 'canjeados' THEN puntos ELSE 0 END) as puntos_canjeados,
                    COUNT(CASE WHEN tipo = 'vencidos' THEN 1 END) as puntos_vencidos
                FROM MOVIMIENTO_PUNTOS
                WHERE id_cliente = $id_cliente";
$stats = $conn->query($sql_stats)->fetch_assoc();

// Puntos restantes para próximo nivel
$puntos_actuales = $cliente['puntos_actuales'] ?? 0;
$puntos_plata_faltan = $puntos_actuales < 100 ? 100 - $puntos_actuales : 0;
$puntos_oro_faltan = $puntos_actuales < 300 ? 300 - $puntos_actuales : 0;
$puntos_platino_faltan = $puntos_actuales < 500 ? 500 - $puntos_actuales : 0;

// Calcular siguiente nivel
if ($puntos_actuales < 100) {
    $siguiente_nivel = 'Plata';
    $puntos_faltantes = $puntos_plata_faltan;
} elseif ($puntos_actuales < 300) {
    $siguiente_nivel = 'Oro';
    $puntos_faltantes = $puntos_oro_faltan;
} elseif ($puntos_actuales < 500) {
    $siguiente_nivel = 'Platino';
    $puntos_faltantes = $puntos_platino_faltan;
} else {
    $siguiente_nivel = null;
    $puntos_faltantes = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Puntos - <?php echo htmlspecialchars($cliente['nombre']); ?> - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/cliente_puntos_historial.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Historial de Puntos</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="clientes.php">👥 Clientes</a>
            <a href="puntos_clientes.php">⭐ Puntos</a>
            <a href="fidelidad_config.php">⚙️ Configuración</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <!-- Botón volver -->
        <div style="margin-bottom: 20px;">
            <a href="puntos_clientes.php" class="btn-volver">← Volver a Puntos de Clientes</a>
        </div>

        <!-- Información del cliente -->
        <div class="cliente-header">
            <div class="cliente-info">
                <h2><?php echo htmlspecialchars($cliente['nombre'] . ' ' . ($cliente['ape_pat'] ?? '') . ' ' . ($cliente['ape_mat'] ?? '')); ?></h2>
                <p>📞 <?php echo $cliente['telefono'] ?: 'No registrado'; ?> | ✉️ <?php echo $cliente['email'] ?: 'No registrado'; ?></p>
                <p>🆔 Cliente #<?php echo $cliente['id']; ?></p>
            </div>
            <div style="text-align: center;">
                <div class="badge-puntos"><?php echo number_format($puntos_actuales); ?> pts</div>
                <div class="nivel-badge <?php echo 'nivel-' . $cliente['nivel']; ?>">
                    <?php echo ucfirst($cliente['nivel']); ?>
                </div>
            </div>
        </div>

        <!-- Tarjetas de estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['puntos_ganados'] ?? 0); ?></div>
                <div class="stat-label">Puntos Ganados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['puntos_canjeados'] ?? 0); ?></div>
                <div class="stat-label">Puntos Canjeados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($cliente['puntos_acumulados']); ?></div>
                <div class="stat-label">Puntos Acumulados (Historial)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($cliente['puntos_actuales']); ?></div>
                <div class="stat-label">Puntos Disponibles</div>
            </div>
        </div>

        <!-- Barra de progreso al siguiente nivel -->
        <?php if ($siguiente_nivel): ?>
        <div class="progress-section">
            <h3>📈 Progreso al siguiente nivel: <?php echo $siguiente_nivel; ?></h3>
            <div class="progress-bar-container">
                <?php
                $porcentaje = 0;
                if ($siguiente_nivel == 'Plata') $porcentaje = ($puntos_actuales / 100) * 100;
                elseif ($siguiente_nivel == 'Oro') $porcentaje = (($puntos_actuales - 100) / 200) * 100;
                elseif ($siguiente_nivel == 'Platino') $porcentaje = (($puntos_actuales - 300) / 200) * 100;
                ?>
                <div class="progress-bar" style="width: <?php echo min($porcentaje, 100); ?>%;"></div>
            </div>
            <p style="margin-top: 10px; color: #666;">¡Faltan <strong><?php echo $puntos_faltantes; ?> puntos</strong> para alcanzar el nivel <?php echo $siguiente_nivel; ?>!</p>
            <p style="font-size: 12px; color: #999;">✨ Los puntos se acumulan automáticamente al realizar compras o pagar citas.</p>
        </div>
        <?php endif; ?>

        <!-- Información de canje -->
        <?php if ($config): ?>
        <div class="progress-section" style="background: #f0f7ff;">
            <h3>🎁 Información de Canje</h3>
            <p>💵 <strong><?php echo $config['puntos_requeridos_canje']; ?> puntos</strong> = <strong>$<?php echo number_format($config['valor_canje'], 2); ?> de descuento</strong> en tu próxima compra o servicio.</p>
            <?php if ($puntos_actuales >= $config['puntos_requeridos_canje']): ?>
                <p style="color: #4caf50; margin-top: 10px;">✅ ¡Ya puedes canjear tus puntos por un descuento!</p>
            <?php else: ?>
                <p style="color: #ff9800; margin-top: 10px;">⚠️ Te faltan <strong><?php echo $config['puntos_requeridos_canje'] - $puntos_actuales; ?> puntos</strong> para poder canjear.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Historial de movimientos -->
        <h3 style="margin: 25px 0 15px;">📜 Historial de Movimientos</h3>
        
        <?php if ($movimientos && $movimientos->num_rows > 0): ?>
        <table class="movimientos-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Puntos</th>
                    <th>Saldo Anterior</th>
                    <th>Saldo Nuevo</th>
                    <th>Concepto</th>
                    <th>Referencia</th>
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
                        case 'ajuste':
                            $tipo_class = 'tipo-ajuste';
                            $tipo_texto = '🔄 Ajuste';
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
                        <?php if ($mov['id_venta']): ?>
                            <a href="detalle_venta.php?id=<?php echo $mov['id_venta']; ?>" style="color: var(--primary);">Venta #<?php echo $mov['id_venta']; ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
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
            No hay movimientos de puntos registrados para este cliente.
        </div>
        <?php endif; ?>

        <!-- Resumen de reglas -->
        <div style="background: #f9f9f9; border-radius: 12px; padding: 20px; margin-top: 25px;">
            <h4>📋 Resumen del Programa de Fidelidad</h4>
            <ul style="margin-left: 20px; line-height: 1.8;">
                <li>💰 Se acumulan <strong><?php echo $config['puntos_por_gasto']; ?> puntos</strong> por cada $10 gastados.</li>
                <li>🎯 <strong><?php echo $config['puntos_requeridos_canje']; ?> puntos</strong> = <strong>$<?php echo number_format($config['valor_canje'], 2); ?> de descuento</strong>.</li>
                <li>⭐ Niveles: Bronce (0-99), Plata (100-299), Oro (300-499), Platino (500+).</li>
                <li>📅 Los puntos tienen vigencia de 1 año.</li>
                <li>🔁 Los puntos no son transferibles.</li>
            </ul>
        </div>
    </div>
</body>
</html>