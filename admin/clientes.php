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

// Procesar acciones (activar/desactivar cliente)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $id_cliente = (int)$_POST['id_cliente'];
        
        switch ($_POST['action']) {
            case 'activar':
                $conn->query("UPDATE CLIENTE SET activo = 1 WHERE id = $id_cliente");
                $_SESSION['mensaje'] = "Cliente activado correctamente";
                break;
            case 'desactivar':
                $conn->query("UPDATE CLIENTE SET activo = 0 WHERE id = $id_cliente");
                $_SESSION['mensaje'] = "Cliente desactivado correctamente";
                break;
        }
        header('Location: clientes.php');
        exit;
    }
}

// ========== BUSCADOR DE CLIENTES ==========
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$clientes = [];

if (!empty($busqueda)) {
    // Usar el procedimiento buscar_cliente
    $stmt = $conn->prepare("CALL buscar_cliente(?)");
    $stmt->bind_param("s", $busqueda);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Guardar resultados
    while ($row = $result->fetch_assoc()) {
        // Calcular total gastado para cada cliente en la búsqueda
        $id_cliente_temp = $row['id'];
        
        // Total ventas
        $sql_ventas = "SELECT COALESCE(SUM(total), 0) as total FROM VENTA WHERE id_cliente = $id_cliente_temp AND estado = 'completada'";
        $total_ventas = $conn->query($sql_ventas)->fetch_assoc()['total'];
        
        // Total servicios
        $sql_servicios = "SELECT COALESCE(SUM(dc.precio_fijado), 0) as total 
                          FROM CITA cita
                          JOIN MASCOTA m ON cita.id_mascota = m.id
                          JOIN DETALLE_CITA dc ON cita.id = dc.id_cita
                          WHERE m.id_cliente = $id_cliente_temp AND cita.estado = 'completada'";
        $total_servicios = $conn->query($sql_servicios)->fetch_assoc()['total'];
        
        $row['total_gastado'] = $total_ventas + $total_servicios;
        $row['puntos_actuales'] = $row['puntos_actuales'] ?? 0;
        
        // Determinar nivel
        if ($row['puntos_actuales'] >= 500) {
            $row['nivel'] = 'platino';
        } elseif ($row['puntos_actuales'] >= 300) {
            $row['nivel'] = 'oro';
        } elseif ($row['puntos_actuales'] >= 100) {
            $row['nivel'] = 'plata';
        } else {
            $row['nivel'] = 'bronce';
        }
        
        $clientes[] = $row;
    }
    
    // IMPORTANTE: Cerrar el resultado y consumir siguientes resultados
    $stmt->close();
    $conn->next_result();
} else {
    // Sin búsqueda, mostrar todos los clientes con datos calculados en tiempo real
    $sql = "SELECT 
                c.id as cliente_id,
                c.nombre,
                c.ape_pat,
                c.ape_mat,
                c.telefono,
                c.email,
                c.activo,
                COALESCE(cp.puntos_actuales, 0) as puntos_actuales
            FROM CLIENTE c
            LEFT JOIN CLIENTE_PUNTOS cp ON c.id = cp.id_cliente
            WHERE c.activo = 1
            ORDER BY cp.puntos_actuales DESC, c.nombre ASC";
    
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $id_cliente_temp = $row['cliente_id'];
        
        // Calcular total gastado en tiempo real
        // Total ventas de productos
        $sql_ventas = "SELECT COALESCE(SUM(total), 0) as total FROM VENTA WHERE id_cliente = $id_cliente_temp AND estado = 'completada'";
        $total_ventas = $conn->query($sql_ventas)->fetch_assoc()['total'];
        
        // Total servicios de citas completadas
        $sql_servicios = "SELECT COALESCE(SUM(dc.precio_fijado), 0) as total 
                          FROM CITA cita
                          JOIN MASCOTA m ON cita.id_mascota = m.id
                          JOIN DETALLE_CITA dc ON cita.id = dc.id_cita
                          WHERE m.id_cliente = $id_cliente_temp AND cita.estado = 'completada'";
        $total_servicios = $conn->query($sql_servicios)->fetch_assoc()['total'];
        
        $row['total_gastado'] = $total_ventas + $total_servicios;
        
        // Determinar nivel basado en puntos
        $puntos = $row['puntos_actuales'];
        if ($puntos >= 500) {
            $row['nivel'] = 'platino';
        } elseif ($puntos >= 300) {
            $row['nivel'] = 'oro';
        } elseif ($puntos >= 100) {
            $row['nivel'] = 'plata';
        } else {
            $row['nivel'] = 'bronce';
        }
        
        $clientes[] = $row;
    }
}

// Contar total de clientes (para el badge)
$total_clientes = $conn->query("SELECT COUNT(*) as total FROM CLIENTE WHERE activo = 1")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/clientes.css">
    <style>
        /* Estilos adicionales */
        .gastado {
            font-weight: bold;
            color: var(--primary);
        }
        .nivel-badge {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Gestión de Clientes</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="clientes.php">👥 Clientes</a>
            <a href="reportes.php">📊 Reportes</a>
            <a href="productos.php">🛒 Productos</a>
            <?php if ($_SESSION['rol'] === 'super_admin'): ?>
            <a href="usuarios.php">👥 Usuarios</a>
            <?php endif; ?>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <!-- Mostrar mensajes -->
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="alert-success"><?php echo $_SESSION['mensaje']; ?></div>
            <?php unset($_SESSION['mensaje']); ?>
        <?php endif; ?>

        <div class="header-actions">
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="cliente_nuevo.php" class="btn-nuevo">+ Nuevo Cliente</a>
                
                <!-- Formulario de búsqueda -->
                <form method="GET" style="display: flex; gap: 10px;">
                    <input type="text" name="buscar" placeholder="Buscar por nombre, teléfono o email..." 
                           value="<?php echo htmlspecialchars($busqueda); ?>" 
                           style="padding: 10px; width: 250px; border: 1px solid #ddd; border-radius: 8px;">
                    <button type="submit" class="btn-buscar">🔍 Buscar</button>
                    <?php if (!empty($busqueda)): ?>
                        <a href="clientes.php" class="btn-limpiar">🗑️ Limpiar</a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="stats-badge">
                📊 Total clientes: <?php echo $total_clientes; ?>
                <?php if (!empty($busqueda)): ?>
                    <span style="color: var(--primary);"> | Resultados: <?php echo count($clientes); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <table class="clientes-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>🎯 Nivel</th>
                    <th>⭐ Puntos</th>
                    <th>💰 Total Gastado</th>
                    <th>Mascotas</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($clientes) > 0): ?>
                    <?php foreach($clientes as $cliente): 
                        $id_cliente = $cliente['cliente_id'] ?? $cliente['id'];
                        $nombre = $cliente['nombre'];
                        $ape_pat = $cliente['ape_pat'] ?? '';
                        $ape_mat = $cliente['ape_mat'] ?? ''; 
                        $telefono = $cliente['telefono'] ?? '';
                        $email = $cliente['email'] ?? '';
                        $nivel = $cliente['nivel'] ?? 'bronce';
                        $puntos = $cliente['puntos_actuales'] ?? 0;
                        $total_gastado = $cliente['total_gastado'] ?? 0;
                        $activo = $cliente['activo'] ?? 1;
                        
                        // Contar mascotas usando función
                        $total_mascotas = $conn->query("SELECT total_mascotas_cliente($id_cliente) as total")->fetch_assoc()['total'];
                        
                        $nivel_icono = [
                            'bronce' => '🥉',
                            'plata' => '🥈',
                            'oro' => '🥇',
                            'platino' => '💎'
                        ];
                    ?>
                    <tr class="nivel-<?php echo $nivel; ?>">
                        <td><?php echo $id_cliente; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars(trim($nombre . ' ' . $ape_pat . ' ' . $ape_mat)); ?></strong>
                         </span>
                        <td><?php echo $telefono ?: '—'; ?></td>
                        <td><?php echo $email ?: '—'; ?></td>
                        <td class="nivel-badge">
                            <?php echo $nivel_icono[$nivel] . ' ' . ucfirst($nivel); ?>
                        </td>
                        <td class="puntos">
                            <span class="puntos-number"><?php echo number_format($puntos); ?></span>
                            <span class="puntos-label">pts</span>
                        </td>
                        <td class="gastado">
                            $<?php echo number_format($total_gastado, 2); ?>
                        </td>
                        <td><?php echo $total_mascotas; ?></td>
                        <td class="estado <?php echo $activo ? 'activo' : 'inactivo'; ?>">
                            <?php echo $activo ? '✅ Activo' : '❌ Inactivo'; ?>
                        </td>
                        <td class="acciones">
                            <a href="cliente_detalle.php?id=<?php echo $id_cliente; ?>" class="btn-ver">👁️ Ver</a>
                            <a href="cliente_editar.php?id=<?php echo $id_cliente; ?>" class="btn-editar">✏️ Editar</a>
                            <?php if ($activo): ?>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="id_cliente" value="<?php echo $id_cliente; ?>">
                                    <button type="submit" name="action" value="desactivar" class="btn-desactivar" onclick="return confirm('¿Desactivar este cliente?')">🔴 Desactivar</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="id_cliente" value="<?php echo $id_cliente; ?>">
                                    <button type="submit" name="action" value="activar" class="btn-activar" onclick="return confirm('¿Activar este cliente?')">🟢 Activar</button>
                                </form>
                            <?php endif; ?>
                            <a href="cliente_puntos.php?id=<?php echo $id_cliente; ?>" class="btn-puntos">⭐ Puntos</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 40px; color: #999;">
                            <?php if (!empty($busqueda)): ?>
                                No se encontraron clientes con "<strong><?php echo htmlspecialchars($busqueda); ?></strong>"
                            <?php else: ?>
                                No hay clientes registrados. 
                                <a href="cliente_nuevo.php" style="color: var(--primary);">Crear el primero</a>
                            <?php endif; ?>
                         </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>