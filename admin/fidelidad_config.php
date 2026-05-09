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
if (!in_array($_SESSION['rol'], ['super_admin', 'admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener configuración actual del programa de fidelidad
$sql_config = "SELECT * FROM PROGRAMA_FIDELIDAD WHERE activo = 1 ORDER BY id DESC LIMIT 1";
$result_config = $conn->query($sql_config);
$config = $result_config->fetch_assoc();

// Si no hay configuración, crear una por defecto
if (!$config) {
    $sql_insert = "INSERT INTO PROGRAMA_FIDELIDAD (nombre, puntos_por_gasto, puntos_requeridos_canje, valor_canje, activo, fecha_inicio) 
                   VALUES ('Programa de Puntos', 10, 100, 50, 1, CURDATE())";
    $conn->query($sql_insert);
    
    // Volver a consultar
    $result_config = $conn->query($sql_config);
    $config = $result_config->fetch_assoc();
}

// Procesar actualización de configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $puntos_por_gasto = (float)$_POST['puntos_por_gasto'];
    $puntos_requeridos_canje = (int)$_POST['puntos_requeridos_canje'];
    $valor_canje = (float)$_POST['valor_canje'];
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    // Desactivar configuración anterior
    $conn->query("UPDATE PROGRAMA_FIDELIDAD SET activo = 0 WHERE activo = 1");
    
    // Insertar nueva configuración
    $sql_insert = "INSERT INTO PROGRAMA_FIDELIDAD (nombre, puntos_por_gasto, puntos_requeridos_canje, valor_canje, activo, fecha_inicio, created_by) 
                   VALUES ('Programa de Puntos', ?, ?, ?, ?, CURDATE(), ?)";
    $stmt = $conn->prepare($sql_insert);
    $empleado_id = $_SESSION['empleado_id'] ?? 1;
    $stmt->bind_param("didis", $puntos_por_gasto, $puntos_requeridos_canje, $valor_canje, $activo, $empleado_id);
    
    if ($stmt->execute()) {
        $_SESSION['mensaje'] = "✅ Configuración actualizada correctamente";
        header('Location: fidelidad_config.php');
        exit;
    } else {
        $error = "Error al actualizar: " . $conn->error;
    }
}

// Estadísticas de puntos
$sql_stats = "SELECT 
                COUNT(DISTINCT cp.id_cliente) as clientes_con_puntos,
                SUM(cp.puntos_actuales) as puntos_totales,
                AVG(cp.puntos_actuales) as promedio_puntos
              FROM CLIENTE_PUNTOS cp";
$stats = $conn->query($sql_stats)->fetch_assoc();

// Top 10 clientes con más puntos
$sql_top = "SELECT 
              c.id, 
              CONCAT(c.nombre, ' ', IFNULL(c.ape_pat, '')) as nombre,
              c.telefono,
              cp.puntos_actuales
            FROM CLIENTE_PUNTOS cp
            JOIN CLIENTE c ON cp.id_cliente = c.id
            WHERE cp.puntos_actuales > 0
            ORDER BY cp.puntos_actuales DESC
            LIMIT 10";
$top_clientes = $conn->query($sql_top);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programa de Fidelidad - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/fidelidad.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Programa de Fidelidad</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="clientes.php">👥 Clientes</a>
            <a href="puntos_clientes.php">🎯 Puntos por Cliente</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="alert-success"><?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Configuración actual -->
        <div class="card">
            <h2>⚙️ Configuración Actual</h2>
            <div class="config-actual">
                <div class="config-item">
                    <span class="label">💵 Puntos por cada $10 gastados:</span>
                    <span class="value"><?php echo $config['puntos_por_gasto']; ?> puntos</span>
                </div>
                <div class="config-item">
                    <span class="label">🎯 Puntos necesarios para canje:</span>
                    <span class="value"><?php echo $config['puntos_requeridos_canje']; ?> puntos</span>
                </div>
                <div class="config-item">
                    <span class="label">💰 Valor del canje:</span>
                    <span class="value">$<?php echo number_format($config['valor_canje'], 2); ?></span>
                </div>
                <div class="config-item">
                    <span class="label">📅 Fecha de inicio:</span>
                    <span class="value"><?php echo date('d/m/Y', strtotime($config['fecha_inicio'])); ?></span>
                </div>
                <div class="config-item">
                    <span class="label">🔘 Estado:</span>
                    <span class="value <?php echo $config['activo'] ? 'activo' : 'inactivo'; ?>">
                        <?php echo $config['activo'] ? '✅ Activo' : '❌ Inactivo'; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Formulario de configuración -->
        <div class="card">
            <h2>✏️ Configurar Programa de Fidelidad</h2>
            <form method="POST" class="form-config">
                <div class="form-row">
                    <div class="form-group">
                        <label>💵 Puntos por cada $10 gastados</label>
                        <input type="number" name="puntos_por_gasto" step="0.01" 
                               value="<?php echo $config['puntos_por_gasto']; ?>" required>
                        <small>Ej: 10 = 10 puntos por cada $10</small>
                    </div>
                    
                    <div class="form-group">
                        <label>🎯 Puntos necesarios para canje</label>
                        <input type="number" name="puntos_requeridos_canje" 
                               value="<?php echo $config['puntos_requeridos_canje']; ?>" required>
                        <small>Ej: 100 puntos = $50 de descuento</small>
                    </div>
                    
                    <div class="form-group">
                        <label>💰 Valor del canje (en dinero)</label>
                        <input type="number" name="valor_canje" step="0.01" 
                               value="<?php echo $config['valor_canje']; ?>" required>
                        <small>Ej: 50 = $50 de descuento</small>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <label>
                            <input type="checkbox" name="activo" <?php echo $config['activo'] ? 'checked' : ''; ?>>
                            🔘 Activar programa de fidelidad
                        </label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-guardar">💾 Guardar Configuración</button>
                </div>
            </form>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['clientes_con_puntos'] ?? 0; ?></div>
                <div class="stat-label">Clientes con puntos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['puntos_totales'] ?? 0); ?></div>
                <div class="stat-label">Puntos acumulados totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['promedio_puntos'] ?? 0, 0); ?></div>
                <div class="stat-label">Promedio de puntos por cliente</div>
            </div>
        </div>

        <!-- Top clientes -->
        <div class="card">
            <h2>🏆 Top 10 Clientes con más puntos</h2>
            <?php if ($top_clientes && $top_clientes->num_rows > 0): ?>
                <table class="clientes-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Teléfono</th>
                            <th>Puntos</th>
                            <th>Próximo canje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while($row = $top_clientes->fetch_assoc()): 
                            $canje = $config['puntos_requeridos_canje'] - $row['puntos_actuales'];
                        ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                            <td><?php echo $row['telefono'] ?: '—'; ?></td>
                            <td><strong><?php echo number_format($row['puntos_actuales']); ?></strong> pts</span></td>
                            <td>
                                <?php if ($canje > 0): ?>
                                    Faltan <?php echo $canje; ?> puntos
                                <?php else: ?>
                                    ✅ ¡Listo para canjear!
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #999; text-align: center; padding: 20px;">No hay clientes con puntos acumulados.</p>
            <?php endif; ?>
        </div>

        <!-- Reglas del programa -->
        <div class="card info-card">
            <h2>📜 Reglas del Programa de Fidelidad</h2>
            <ul>
                <li>💰 Los puntos se acumulan automáticamente al realizar compras o pagar citas.</li>
                <li>⭐ <?php echo $config['puntos_por_gasto']; ?> puntos por cada $10 gastados.</li>
                <li>🎯 <?php echo $config['puntos_requeridos_canje']; ?> puntos = $<?php echo number_format($config['valor_canje'], 2); ?> de descuento.</li>
                <li>📅 Los puntos tienen vigencia de 1 año desde su acumulación.</li>
                <li>🔁 Los puntos no son transferibles entre clientes.</li>
                <li>🎁 El descuento se aplica en la siguiente compra o cita.</li>
            </ul>
        </div>
    </div>
</body>
</html>