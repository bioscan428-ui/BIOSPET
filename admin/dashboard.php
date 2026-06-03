<?php
date_default_timezone_set('America/Mexico_City');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
// ========== NOTIFICACIONES PARA EL ADMIN ==========
$notificacion_admin = $_SESSION['notificacion_admin'] ?? null;
unset($_SESSION['notificacion_admin']);

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

// ========== MÉTRICAS RÁPIDAS PARA EL DASHBOARD ==========
$ventas_hoy = $conn->query("SELECT ventas_dia(CURDATE()) as total")->fetch_assoc()['total'];
$citas_hoy = $conn->query("SELECT total_citas_dia(CURDATE()) as total")->fetch_assoc()['total'];
$productos_stock_bajo = $conn->query("SELECT productos_stock_bajo() as total")->fetch_assoc()['total'];
$ingresos_mes = $conn->query("SELECT ingresos_mes_actual() as total")->fetch_assoc()['total'];

// ========== USANDO VISTA ==========
$sql = "SELECT * FROM vista_citas_completas ORDER BY fecha_cita DESC, hora_cita DESC";
$result = $conn->query($sql);

// ========== ESTADÍSTICAS DE ORIGEN ==========
$stats_origen = $conn->query("SELECT origen, COUNT(*) as total FROM CITA WHERE origen IS NOT NULL GROUP BY origen");
$origen_data = [];
while ($row = $stats_origen->fetch_assoc()) {
    $origen_data[$row['origen']] = $row['total'];
}

// ========== ADICIONAL: Productos con stock crítico ==========
$stock_critico = $conn->query("SELECT * FROM vista_stock_critico ");
// Si la vista no existe, usar consulta alternativa
if (!$stock_critico) {
    $stock_critico = $conn->query("SELECT p.id, p.nombre, c.nombre as categoria, p.stock_actual, p.stock_minimo 
                                   FROM PRODUCTO p
                                   LEFT JOIN CATEGORIA_PRODUCTO c ON p.id_categoria = c.id
                                   WHERE p.stock_actual <= p.stock_minimo AND p.activo = 1 
                                   ");
}

// ========== USANDO EL PROCEDIMIENTO dashboard_ejecutivo ==========
$proximas_citas_data = [];
$stock_bajo_data = [];
$ingresos_30dias_data = [];

$call_result = $conn->query("CALL dashboard_ejecutivo()");

if ($call_result) {
    // Primer resultado: Resumen del día (ya lo tenemos arriba, lo omitimos)
    $call_result->free();
    $conn->next_result();
    
    // Segundo resultado: Próximas citas (7 días)
    if ($conn->more_results()) {
        $proximas_result = $conn->store_result();
        while ($row = $proximas_result->fetch_assoc()) {
            $proximas_citas_data[] = $row;
        }
        $proximas_result->free();
        $conn->next_result();
    }
    
    // Tercer resultado: Productos con stock bajo
    if ($conn->more_results()) {
        $stock_result = $conn->store_result();
        while ($row = $stock_result->fetch_assoc()) {
            $stock_bajo_data[] = $row;
        }
        $stock_result->free();
        $conn->next_result();
    }
    
    // Cuarto resultado: Ingresos últimos 30 días
    if ($conn->more_results()) {
        $ingresos_result = $conn->store_result();
        while ($row = $ingresos_result->fetch_assoc()) {
            $ingresos_30dias_data[] = $row;
        }
        $ingresos_result->free();
        $conn->next_result();
    }
}

// ========== ADICIONAL: Resumen ejecutivo ==========
$resumen_sql = $conn->query("SELECT * FROM vista_resumen_negocio");
if ($resumen_sql) {
    $resumen = $resumen_sql->fetch_assoc();
} else {
    $resumen = [
        'clientes_activos' => 0,
        'mascotas_activas' => 0,
        'empleados_activos' => 0,
        'citas_pendientes' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Admin - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Panel de Administración</h1>
        <div>
            <a href="dashboard.php">📋 Citas</a>
            <a href="calendario.php">📅 Calendario</a>
            <a href="reportes.php">📊 Reportes</a>
            <!-- Dropdown Productos -->
            <div class="dropdown">
                <a href="javascript:void(0)">🛒 Productos ▼</a>
                <div class="dropdown-content">
                    <a href="productos.php">📦 Gestión de Productos</a>
                    <a href="categorias_productos.php">📁 Categorías</a>
                    <a href="producto_nuevo.php">➕ Nuevo Producto</a>
                    <hr style="margin: 5px 0; border-color: #eee;">
                    <a href="proveedores.php">🏭 Proveedores</a>
                </div>
            </div>
            <!-- Dropdown Productos -->
            <!------
            <a href="productos.php">🛒 Productos</a>----->
            <a href="punto_venta.php" style="background: #4caf50; padding: 5px 12px; border-radius: 5px;">💰 Punto de Venta</a>
            <a href="corte_caja.php" style="background: #9c27b0; padding: 5px 12px; border-radius: 5px;">💰 Corte de Caja</a>
            <a href="facturas_lista.php" style="background: #9c27b0; padding: 5px 12px; border-radius: 5px;">📄 Facturas</a>
            <!-- Dropdown Clientes -->
            <div class="dropdown">
                <a href="javascript:void(0)">👥 Clientes ▼</a>
                <div class="dropdown-content">
                    <a href="clientes.php">📋 Lista de Clientes</a>
                    <a href="fidelidad_config.php">⭐ Programa de Fidelidad</a>
                    <a href="puntos_clientes.php">🎯 Puntos y Recompensas</a>
                </div>
            </div>

            <!-- Dropdown Proveedores y Compras -->
            <div class="dropdown">
                <a href="javascript:void(0)">🏭 Proveedores ▼</a>
                <div class="dropdown-content">
                    <a href="proveedores.php">📋 Lista de Proveedores</a>
                    <a href="proveedor_nuevo.php">➕ Nuevo Proveedor</a>
                    <a href="compras.php">🛒 Historial de Compras</a>
                    <a href="compra_nueva.php">📦 Registrar Compra</a>
                    <hr style="margin: 5px 0; border-color: #eee;">
                    <a href="reporte_proveedores.php">📊 Reporte de Proveedores</a>
                </div>
            </div>

            <?php if ($_SESSION['rol'] === 'super_admin'): ?>
                <a href="usuarios.php">👥 Usuarios</a>
            <?php endif; ?>

            <a href="imprimir_consentimiento_formato.php">📋 Formatos</a>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <!-- Mostrar notificación si existe -->
    <?php if ($notificacion_admin): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: '<?php echo $notificacion_admin['titulo']; ?>',
                text: '<?php echo $notificacion_admin['mensaje']; ?>',
                icon: '<?php echo $notificacion_admin['tipo']; ?>',
                confirmButtonColor: '#E68A00',
                confirmButtonText: 'OK'
            });
        });
    </script>
    <?php endif; ?>

    
        
    <div class="container">
    <!-- ========== TARJETA DE BIENVENIDA ========== -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <div>
                <h2 style="margin: 0;">¡Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?>! 👋</h2>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">Hoy es <?php echo date('d/m/Y'); ?></p>
            </div>
            <div style="text-align: right;">
                <span style="background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 20px;">
                    ⏰ <?php echo date('h:i A'); ?>
                </span>
            </div>
        </div>
    <!-- ========== FIN DE TARJETA DE BIENVENIDA ========== -->
    
        <!-- Resumen Ejecutivo -->
        <div class="resumen-grid">
            <div class="resumen-card">
                <div class="resumen-number"><?php echo $resumen['clientes_activos']; ?></div>
                <div class="stat-label">Clientes Activos</div>
            </div>
            <div class="resumen-card">
                <div class="resumen-number"><?php echo $resumen['mascotas_activas']; ?></div>
                <div class="stat-label">Mascotas</div>
            </div>
            <div class="resumen-card">
                <div class="resumen-number"><?php echo $resumen['empleados_activos']; ?></div>
                <div class="stat-label">Empleados</div>
            </div>
            <div class="resumen-card">
                <div class="resumen-number"><?php echo $resumen['citas_pendientes']; ?></div>
                <div class="stat-label">Citas Pendientes</div>
            </div>
        </div>
        
        <!-- Tarjetas de resumen rápidas -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $citas_hoy; ?></div>
                <div class="stat-label">Citas de hoy</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($ventas_hoy, 2); ?></div>
                <div class="stat-label">Ventas de hoy</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $productos_stock_bajo; ?></div>
                <div class="stat-label">Productos con stock bajo</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($ingresos_mes, 2); ?></div>
                <div class="stat-label">Ingresos del mes</div>
            </div>
        </div>

        <!-- Estadísticas de origen de citas -->
        <div class="origen-stats">
            <div class="origen-card">
                <div class="icono">💬</div>
                <div class="info">
                    <div class="numero"><?php echo $origen_data['Whatsapp'] ?? 0; ?></div>
                    <div class="label">Citas por WhatsApp</div>
                </div>
            </div>
            <div class="origen-card">
                <div class="icono">🏥</div>
                <div class="info">
                    <div class="numero"><?php echo $origen_data['Presencial'] ?? 0; ?></div>
                    <div class="label">Citas Presenciales</div>
                </div>
            </div>
        </div>

        <!-- Alerta de stock crítico (desde vista_stock_critico) -->
        <?php
        if (isset($stock_critico) && $stock_critico && $stock_critico->num_rows > 0): 
        ?>
        <div class="alert-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="color: #f44336; margin: 0;">⚠️ Productos con Stock Crítico</h3>
                <span class="badge-count" style="background: #f44336; color: white; padding: 4px 10px; border-radius: 20px;">
                    <?php echo $stock_critico->num_rows; ?> productos
                </span>
            </div>
            <div style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
                <?php while($producto = $stock_critico->fetch_assoc()): ?>
                    <div class="alert-item">
                        <span>
                            <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                            <span style="color: #666; font-size: 12px;">(<?php echo htmlspecialchars($producto['categoria']); ?>)</span>
                        </span>
                        <span class="alert-stock-bajo">
                            Stock: <?php echo $producto['stock_actual']; ?> / 
                            Mínimo: <?php echo $producto['stock_minimo']; ?>
                        </span>
                    </div>
                    <?php endwhile; ?>
                </div>
                <div style="margin-top: 10px; text-align: center;">
                    <a href="productos.php?tab=criticos" class="btn-ver-todos" style="font-size: 12px; color: #E68D0B; text-decoration: none;">
                        📦 Ver todos los productos con stock bajo →
                    </a>
                </div>
            </div>
            <?php endif; ?>
        <!-- Fin de Alerta de stock crítico (desde vista_stock_critico) -->

        <!-- ========== NUEVA SECCIÓN: Próximas citas (del procedimiento) ========== -->
        <?php if (!empty($proximas_citas_data)): ?>
        <div class="proximas-citas">
            <h3>📅 Próximas Citas (7 días)</h3>
            <div class="tabla-scroll-container" style="max-height: 300px; overflow-y: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Mascota</th>
                            <th>Dueño</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($proximas_citas_data as $cita): ?>
                        <tr>
                            <td><?php echo $cita['id']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
                            <td><?php echo $cita['hora_cita']; ?></td>
                            <td><?php echo htmlspecialchars($cita['nombre_mascota']); ?></td>
                            <td><?php echo htmlspecialchars($cita['dueno']); ?></td>
                            <td>
                                <span class="estado-<?php echo $cita['estado']; ?>">
                                    <?php echo ucfirst($cita['estado']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ========== NUEVA SECCIÓN: Ingresos últimos 30 días (del procedimiento) ========== -->
        <?php if (!empty($ingresos_30dias_data)): ?>
        <div class="chart-container">
            <h3>📊 Tendencia de Ingresos (últimos 30 días)</h3>
            <div style="max-height: 300px; overflow-y: auto;">
                <?php 
                $max_ingreso = max(array_column($ingresos_30dias_data, 'total'));
                $max_ingreso = $max_ingreso > 0 ? $max_ingreso : 1;
                foreach(array_reverse($ingresos_30dias_data) as $ingreso): 
                    $porcentaje = ($ingreso['total'] / $max_ingreso) * 100;
                ?>
                <div class="barra-ingreso">
                    <div class="barra-fecha"><?php echo date('d/m', strtotime($ingreso['fecha'])); ?></div>
                    <div class="barra-linea">
                        <div class="barra-fill" style="width: <?php echo $porcentaje; ?>%; min-width: 30px;">
                            <?php if($ingreso['total'] > 0): ?>$<?php echo number_format($ingreso['total'], 0); ?><?php endif; ?>
                        </div>
                    </div>
                    <div class="barra-monto">$<?php echo number_format($ingreso['total'], 2); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top: 15px; text-align: center; font-size: 12px; color: #666;">
                <span class="barra-fill" style="display: inline-block; width: 20px; background: var(--primary);"></span> = Monto de ingresos por día
            </div>
        </div>
        <?php endif; ?>

        <div class="total-citas">
            <strong>Total de citas registradas:</strong> <?php echo $result->num_rows; ?>
        </div>
        
        <!-- Botones de acción -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin: 20px 0; gap: 15px; flex-wrap: wrap;">
            <h2>📋 Listado de Citas</h2>
            <div style="display: flex; gap: 10px;">
                <a href="../citas.php" class="btn-nueva-cita" target="_blank">
                    📝 Agendar Nueva Cita
                </a>
                <button onclick="abrirModalMascotas()" class="btn-mascotas">
                    🐾 Ver Historial de Mascotas
                </button>
                <a href="cita_cliente_registrado.php" class="btn-nueva-cita" target="_blank">
                    📝 Cita Para Clientes Registrados
                </a>
            </div>
        </div>

        <div class="tabla-scroll-container">
            <table class="citas-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Foto</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Dueño</th>
                        <th>Mascota</th>
                        <th>Motivo</th>
                        <th>Servicios</th>
                        <th>Total</th>
                        <th>Origen</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): 
                        $origen = $row['origen'] ?? 'Presencial';
                        if ($origen == 'Whatsapp') {
                            $origen_class = 'origen-whatsapp';
                            $origen_icono = '💬';
                            $origen_texto = 'WhatsApp';
                        } else {
                            $origen_class = 'origen-presencial';
                            $origen_icono = '🏥';
                            $origen_texto = 'Presencial';
                        }
                    ?>
                    <tr>
                        <td><?php echo $row['cita_id']; ?></td>
                        <td>
                            <?php if (!empty($row['foto']) && file_exists('../' . $row['foto'])): ?>
                                <img src="../<?php echo $row['foto']; ?>" alt="Foto de <?php echo $row['nombre_mascota']; ?>" class="foto-miniatura">
                            <?php else: ?>
                                <span style="color:#999; font-size:20px;">🐾</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($row['fecha_cita'])); ?></td>
                        <td><?php echo $row['hora_cita']; ?></td>
                        <td>
                            <?php echo htmlspecialchars($row['nombre_dueno']); ?><br>
                            <small><?php echo $row['telefono']; ?></small>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row['nombre_mascota']); ?><br>
                            <small><?php echo $row['especie']; ?></small>
                        </td>
                        <td class="sintomas-tooltip">
                            <?php if (!empty($row['notas'])): ?>
                                <span class="sintomas-icono">📋</span>
                                <span class="tooltip-texto">
                                    <strong>Motivo:</strong><br>
                                    <?php echo nl2br(htmlspecialchars(substr($row['notas'], 0, 100))); ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#ccc;">—</span>
                            <?php endif; ?>
                        </span>
                        <td>
                            <?php if (!empty($row['servicios'])): ?>
                                <?php echo substr($row['servicios'], 0, 50); ?>
                            <?php else: ?>
                                <span class="sin-servicios">—</span>
                            <?php endif; ?>
                        </span>
                        <td>$<?php echo number_format($row['total_cobrado'], 2); ?></span>
                        <td>
                            <span class="<?php echo $origen_class; ?>">
                                <?php echo $origen_icono; ?> <?php echo $origen_texto; ?>
                            </span>
                        </span>
                        <td>
                            <span class="estado-<?php echo $row['estado']; ?>">
                                <?php echo ucfirst($row['estado']); ?>
                            </span>
                        </span>
                        <td class="acciones">
                            <a href="detalle_cita.php?id=<?php echo $row['cita_id']; ?>" class="btn-small">Ver</a>
                            <a href="editar_cita.php?id=<?php echo $row['cita_id']; ?>" class="btn-small" style="background: #ff9800; color: white;">✏️ Editar</a>
                            <?php if ($row['estado'] == 'pendiente'): ?>
                                <a href="actualizar_estado.php?id=<?php echo $row['cita_id']; ?>&estado=confirmada" class="btn-small">Confirmar</a>
                            <?php endif; ?>
                            <?php if ($row['estado'] != 'cancelada' && $row['estado'] != 'completada'): ?>
                                <a href="actualizar_estado.php?id=<?php echo $row['cita_id']; ?>&estado=cancelada" class="btn-small" style="background:#f44336;">Cancelar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal de Historial de Mascotas -->
    <div id="modalMascotas" class="modal">
        <div class="modal-content modal-grande">
            <div class="modal-header" style="background: #9c27b0;">
                <h2>🐾 Historial de Mascotas</h2>
                <span class="close-mascotas" style="color: white; font-size: 28px; cursor: pointer;">&times;</span>
            </div>
            <div class="modal-body" id="modalMascotasBody">
                <div style="text-align: center; padding: 40px;">
                    Cargando...
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/dashboard.js"></script>
</body>
</html>