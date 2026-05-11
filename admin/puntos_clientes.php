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

// Obtener configuración actual del programa de fidelidad
$sql_config = "SELECT * FROM PROGRAMA_FIDELIDAD WHERE activo = 1 ORDER BY id DESC LIMIT 1";
$result_config = $conn->query($sql_config);
$config = $result_config->fetch_assoc();

// Parámetros de búsqueda y filtros
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$nivel = isset($_GET['nivel']) ? $_GET['nivel'] : '';
$orden = isset($_GET['orden']) ? $_GET['orden'] : 'puntos_desc';

// Consulta base con JOIN a CLIENTE_PUNTOS
$sql = "SELECT 
            c.id,
            c.nombre,
            c.ape_pat,
            c.ape_mat,
            c.telefono,
            c.email,
            COALESCE(cp.puntos_actuales, 0) as puntos_actuales,
            COALESCE(cp.puntos_acumulados_historial, 0) as puntos_acumulados,
            CASE 
                WHEN COALESCE(cp.puntos_actuales, 0) >= 500 THEN 'platino'
                WHEN COALESCE(cp.puntos_actuales, 0) >= 300 THEN 'oro'
                WHEN COALESCE(cp.puntos_actuales, 0) >= 100 THEN 'plata'
                ELSE 'bronce'
            END as nivel,
            cp.ultima_actualizacion,
            (SELECT COUNT(*) FROM MASCOTA WHERE id_cliente = c.id AND activo = 1) as total_mascotas,
            (SELECT COUNT(*) FROM VENTA WHERE id_cliente = c.id AND estado = 'completada') as total_compras
        FROM CLIENTE c
        LEFT JOIN CLIENTE_PUNTOS cp ON c.id = cp.id_cliente
        WHERE c.activo = 1";

// Aplicar filtros
if (!empty($buscar)) {
    $sql .= " AND (c.nombre LIKE '%$buscar%' 
              OR c.ape_pat LIKE '%$buscar%' 
              OR c.telefono LIKE '%$buscar%' 
              OR c.email LIKE '%$buscar%')";
}

if ($nivel !== '') {
    if ($nivel == 'bronce') $sql .= " AND COALESCE(cp.puntos_actuales, 0) < 100";
    elseif ($nivel == 'plata') $sql .= " AND COALESCE(cp.puntos_actuales, 0) >= 100 AND COALESCE(cp.puntos_actuales, 0) < 300";
    elseif ($nivel == 'oro') $sql .= " AND COALESCE(cp.puntos_actuales, 0) >= 300 AND COALESCE(cp.puntos_actuales, 0) < 500";
    elseif ($nivel == 'platino') $sql .= " AND COALESCE(cp.puntos_actuales, 0) >= 500";
}

// Ordenar
switch ($orden) {
    case 'puntos_asc':
        $sql .= " ORDER BY puntos_actuales ASC";
        break;
    case 'nombre_asc':
        $sql .= " ORDER BY c.nombre ASC";
        break;
    case 'nombre_desc':
        $sql .= " ORDER BY c.nombre DESC";
        break;
    case 'puntos_desc':
    default:
        $sql .= " ORDER BY puntos_actuales DESC";
        break;
}

$clientes = $conn->query($sql);

// Estadísticas generales
$sql_stats = "SELECT 
                COUNT(DISTINCT c.id) as total_clientes,
                COUNT(DISTINCT cp.id_cliente) as clientes_con_puntos,
                SUM(COALESCE(cp.puntos_actuales, 0)) as puntos_totales,
                AVG(COALESCE(cp.puntos_actuales, 0)) as promedio_puntos,
                SUM(COALESCE(cp.puntos_acumulados_historial, 0)) as puntos_entregados
              FROM CLIENTE c
              LEFT JOIN CLIENTE_PUNTOS cp ON c.id = cp.id_cliente
              WHERE c.activo = 1";
$stats = $conn->query($sql_stats)->fetch_assoc();

// Próximos a canjear (clientes con puntos cercanos al canje)
$puntos_canje = $config['puntos_requeridos_canje'] ?? 100;
$sql_proximos = "SELECT 
                    c.id,
                    CONCAT(c.nombre, ' ', IFNULL(c.ape_pat, '')) as nombre,
                    cp.puntos_actuales,
                    (" . $puntos_canje . " - cp.puntos_actuales) as puntos_faltantes
                 FROM CLIENTE c
                 JOIN CLIENTE_PUNTOS cp ON c.id = cp.id_cliente
                 WHERE cp.puntos_actuales > 0 
                   AND cp.puntos_actuales < " . $puntos_canje . "
                   AND (" . $puntos_canje . " - cp.puntos_actuales) <= 50
                 ORDER BY puntos_faltantes ASC
                 LIMIT 5";
$proximos_canje = $conn->query($sql_proximos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Puntos de Clientes - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/puntos_clientes.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Puntos de Fidelidad</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="clientes.php">👥 Clientes</a>
            <a href="fidelidad_config.php">⚙️ Configuración</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <!-- Tarjetas de estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_clientes'] ?? 0); ?></div>
                <div class="stat-label">Total Clientes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['clientes_con_puntos'] ?? 0); ?></div>
                <div class="stat-label">Clientes con Puntos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['puntos_totales'] ?? 0); ?></div>
                <div class="stat-label">Puntos Acumulados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['promedio_puntos'] ?? 0, 0); ?></div>
                <div class="stat-label">Promedio por Cliente</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['puntos_entregados'] ?? 0); ?></div>
                <div class="stat-label">Puntos Entregados (Historial)</div>
            </div>
        </div>

        <!-- Próximos a canjear -->
        <?php if ($proximos_canje && $proximos_canje->num_rows > 0): ?>
        <div class="proximos-card">
            <h3>🎯 Clientes próximos a canjear (faltan 50 puntos o menos)</h3>
            <?php while($row = $proximos_canje->fetch_assoc()): ?>
            <div class="proximo-item">
                <span><strong><?php echo htmlspecialchars($row['nombre']); ?></strong></span>
                <span><?php echo number_format($row['puntos_actuales']); ?> puntos</span>
                <span style="color: #ff9800;">Faltan <?php echo $row['puntos_faltantes']; ?> puntos</span>
                <a href="cliente_detalle.php?id=<?php echo $row['id']; ?>" class="btn-ver">Ver</a>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="filtros">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; width: 100%;">
                <div class="filtro-group">
                    <label>🔍 Buscar</label>
                    <input type="text" name="buscar" placeholder="Nombre, teléfono, email..." value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                <div class="filtro-group">
                    <label>🏆 Nivel</label>
                    <select name="nivel">
                        <option value="">Todos</option>
                        <option value="bronce" <?php echo $nivel == 'bronce' ? 'selected' : ''; ?>>🥉 Bronce (0-99 pts)</option>
                        <option value="plata" <?php echo $nivel == 'plata' ? 'selected' : ''; ?>>🥈 Plata (100-299 pts)</option>
                        <option value="oro" <?php echo $nivel == 'oro' ? 'selected' : ''; ?>>🥇 Oro (300-499 pts)</option>
                        <option value="platino" <?php echo $nivel == 'platino' ? 'selected' : ''; ?>>💎 Platino (500+ pts)</option>
                    </select>
                </div>
                <div class="filtro-group">
                    <label>📊 Ordenar por</label>
                    <select name="orden">
                        <option value="puntos_desc" <?php echo $orden == 'puntos_desc' ? 'selected' : ''; ?>>Más puntos</option>
                        <option value="puntos_asc" <?php echo $orden == 'puntos_asc' ? 'selected' : ''; ?>>Menos puntos</option>
                        <option value="nombre_asc" <?php echo $orden == 'nombre_asc' ? 'selected' : ''; ?>>Nombre A-Z</option>
                        <option value="nombre_desc" <?php echo $orden == 'nombre_desc' ? 'selected' : ''; ?>>Nombre Z-A</option>
                    </select>
                </div>
                <div class="filtro-group">
                    <button type="submit" class="btn-buscar">🔍 Buscar</button>
                </div>
                <div class="filtro-group">
                    <a href="puntos_clientes.php" class="btn-limpiar">🗑️ Limpiar</a>
                </div>
            </form>
        </div>

        <!-- Tabla de clientes -->
        <table class="clientes-table">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Contacto</th>
                    <th>🎯 Nivel</th>
                    <th>⭐ Puntos</th>
                    <th>Mascotas</th>
                    <th>Compras</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($clientes && $clientes->num_rows > 0): ?>
                    <?php while($row = $clientes->fetch_assoc()): 
                        $nombre_completo = trim($row['nombre'] . ' ' . ($row['ape_pat'] ?? '') . ' ' . ($row['ape_mat'] ?? ''));
                        $nivel_clase = '';
                        switch($row['nivel']) {
                            case 'bronce': $nivel_clase = 'nivel-bronce'; break;
                            case 'plata': $nivel_clase = 'nivel-plata'; break;
                            case 'oro': $nivel_clase = 'nivel-oro'; break;
                            case 'platino': $nivel_clase = 'nivel-platino'; break;
                        }
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($nombre_completo); ?></strong><br>
                            <small style="color:#666;">ID: <?php echo $row['id']; ?></small>
                         </span>
                        <td>
                            <?php if($row['telefono']): ?>📞 <?php echo $row['telefono']; ?><br><?php endif; ?>
                            <?php if($row['email']): ?>✉️ <?php echo htmlspecialchars($row['email']); ?><?php endif; ?>
                         </span>
                        <td>
                            <span class="nivel-badge <?php echo $nivel_clase; ?>">
                                <?php echo ucfirst($row['nivel']); ?>
                            </span>
                         </span>
                        <td class="puntos">
                            <span class="puntos-number"><?php echo number_format($row['puntos_actuales']); ?></span>
                            <span class="puntos-label">pts</span>
                        </span>
                        <td><?php echo $row['total_mascotas']; ?> 🐾</span>
                        <td><?php echo $row['total_compras']; ?> 🛒</span>
                        <td class="acciones">
                            <a href="cliente_detalle.php?id=<?php echo $row['id']; ?>" class="btn-ver">👁️ Ver</a>
                            <a href="cliente_puntos_historial.php?id=<?php echo $row['id']; ?>" class="btn-ver" style="background: #9c27b0;">📜 Historial</a>
                        </span>
                     </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                            No se encontraron clientes
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Reglas del programa -->
        <div class="proximos-card" style="margin-top: 20px;">
            <h3>📜 ¿Cómo funcionan los puntos?</h3>
            <ul style="margin-left: 20px; line-height: 1.8;">
                <li>💰 Se acumulan <strong><?php echo $config['puntos_por_gasto']; ?> puntos</strong> por cada $10 gastados en compras o servicios.</li>
                <li>🎯 <strong><?php echo $config['puntos_requeridos_canje']; ?> puntos</strong> = <strong>$<?php echo number_format($config['valor_canje'], 2); ?> de descuento</strong>.</li>
                <li>⭐ Niveles: Bronce (0-99 pts), Plata (100-299 pts), Oro (300-499 pts), Platino (500+ pts).</li>
                <li>📅 Los puntos tienen vigencia de <strong>1 año</strong> desde su acumulación.</li>
                <li>🔁 Los puntos no son transferibles entre clientes.</li>
                <li>🎁 El descuento se puede aplicar en cualquier compra o servicio.</li>
            </ul>
        </div>
    </div>
</body>
</html>