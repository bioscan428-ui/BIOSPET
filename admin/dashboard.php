<?php
session_start();
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
$stock_critico = $conn->query("SELECT * FROM vista_stock_critico LIMIT 5");

// ========== ADICIONAL: Resumen ejecutivo ==========
$resumen = $conn->query("SELECT * FROM vista_resumen_negocio")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Admin - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* Estilos para el badge de origen */
        .origen-whatsapp {
            background: #25d366;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }
        .origen-presencial {
            background: #ff9800;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }
        
        /* Tarjeta de estadísticas de origen */
        .origen-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .origen-card {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: var(--shadow-soft);
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
            min-width: 150px;
        }
        .origen-card .icono {
            font-size: 2rem;
        }
        .origen-card .info {
            text-align: center;
            flex: 1;
        }
        .origen-card .numero {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
        }
        .origen-card .label {
            font-size: 0.8rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Panel de Administración</h1>
        <div>
            <a href="dashboard.php">📋 Citas</a>
            <a href="calendario.php">📅 Calendario</a>
            <a href="reportes.php">📊 Reportes</a>
            <a href="productos.php">🛒 Productos</a>
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
                    <div class="numero"><?php echo $origen_data['whatsapp'] ?? 0; ?></div>
                    <div class="label">Citas por WhatsApp</div>
                </div>
            </div>
            <div class="origen-card">
                <div class="icono">🏥</div>
                <div class="info">
                    <div class="numero"><?php echo $origen_data['presencial'] ?? 0; ?></div>
                    <div class="label">Citas Presenciales</div>
                </div>
            </div>
        </div>

        <!-- Alerta de stock crítico -->
        <?php if ($stock_critico->num_rows > 0): ?>
        <div class="alert-card">
            <h3 style="color: #f44336; margin-bottom: 15px;">⚠️ Productos con Stock Crítico</h3>
            <?php while($producto = $stock_critico->fetch_assoc()): ?>
            <div class="alert-item">
                <span><strong><?php echo $producto['nombre']; ?></strong> (<?php echo $producto['categoria']; ?>)</span>
                <span class="alert-stock-bajo">Stock: <?php echo $producto['stock_actual']; ?> / Mínimo: <?php echo $producto['stock_minimo']; ?></span>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>

        <div class="total-citas">
            <strong>Total de citas:</strong> <?php echo $result->num_rows; ?>
        </div>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin: 20px 0;">
            <h2>📋 Listado de Citas</h2>
            <button onclick="abrirModalMascotas()" class="btn-mascotas">
                🐾 Ver Historial de Mascotas
            </button>
        </div>

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
                <tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): 
                    $origen_class = ($row['origen'] ?? 'presencial') == 'whatsapp' ? 'origen-whatsapp' : 'origen-presencial';
                    $origen_icono = ($row['origen'] ?? 'presencial') == 'whatsapp' ? '💬' : '🏥';
                    $origen_texto = ($row['origen'] ?? 'presencial') == 'whatsapp' ? 'WhatsApp' : 'Presencial';
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
                    </td>
                    <td>
                        <?php if (!empty($row['servicios'])): ?>
                            <?php echo substr($row['servicios'], 0, 50); ?>
                        <?php else: ?>
                            <span class="sin-servicios">—</span>
                        <?php endif; ?>
                    </td>
                    <td>$<?php echo number_format($row['total_cobrado'], 2); ?></td>
                    <td>
                        <span class="<?php echo $origen_class; ?>">
                            <?php echo $origen_icono; ?> <?php echo $origen_texto; ?>
                        </span>
                    </td>
                    <td>
                        <span class="estado-<?php echo $row['estado']; ?>">
                            <?php echo ucfirst($row['estado']); ?>
                        </span>
                    </td>
                    <td class="acciones">
                        <a href="detalle_cita.php?id=<?php echo $row['cita_id']; ?>" class="btn-small">Ver</a>
                        <?php if ($row['estado'] == 'pendiente'): ?>
                            <a href="actualizar_estado.php?id=<?php echo $row['cita_id']; ?>&estado=confirmada" class="btn-small">Confirmar</a>
                        <?php endif; ?>
                        <?php if ($row['estado'] != 'cancelada' && $row['estado'] != 'completada'): ?>
                            <a href="actualizar_estado.php?id=<?php echo $row['cita_id']; ?>&estado=cancelada" class="btn-small" style="background:#f44336;">Cancelar</a>
                        <?php endif; ?>
                    </span>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
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