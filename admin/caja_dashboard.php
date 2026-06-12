<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol para acceso
if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'caja'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// ========== OBTENER CITAS PARA MOSTRAR ==========
$sql_citas = "SELECT 
                c.id as cita_id,
                c.fecha_cita,
                c.hora_cita,
                c.estado,
                c.notas,
                c.origen,
                c.id_mascota,
                m.nombre_mascota,
                m.especie,
                m.foto,
                cl.id as cliente_id,
                cl.nombre as nombre_dueno,
                cl.ape_pat,
                cl.telefono,
                GROUP_CONCAT(s.nombre_servicio SEPARATOR ', ') AS servicios,
                COALESCE(SUM(dc.precio_fijado), 0) as total_servicios
            FROM CITA c
            JOIN MASCOTA m ON c.id_mascota = m.id
            JOIN CLIENTE cl ON m.id_cliente = cl.id
            LEFT JOIN DETALLE_CITA dc ON c.id = dc.id_cita
            LEFT JOIN SERVICIO s ON dc.id_servicio = s.id
            GROUP BY c.id
            ORDER BY c.fecha_cita DESC, c.hora_cita DESC";

$citas = $conn->query($sql_citas);

// Contar citas pendientes
$sql_pendientes = "SELECT COUNT(*) as total FROM CITA WHERE estado = 'pendiente'";
$pendientes = $conn->query($sql_pendientes)->fetch_assoc()['total'];

// ========== FIN CITAS ==========

// Procesar actualización de stock
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'actualizar_stock') {
        $id_producto = (int)$_POST['id_producto'];
        $nuevo_stock = (int)$_POST['nuevo_stock'];
        $motivo = trim($_POST['motivo'] ?? 'Ajuste manual desde caja');
        
        $check = $conn->prepare("SELECT nombre, stock_actual FROM PRODUCTO WHERE id = ? AND activo = 1");
        $check->bind_param("i", $id_producto);
        $check->execute();
        $producto = $check->get_result()->fetch_assoc();
        
        if ($producto) {
            $stock_anterior = $producto['stock_actual'];
            
            $update = $conn->prepare("UPDATE PRODUCTO SET stock_actual = ? WHERE id = ?");
            $update->bind_param("ii", $nuevo_stock, $id_producto);
            
            if ($update->execute()) {
                $diferencia = $nuevo_stock - $stock_anterior;
                $tipo = $diferencia >= 0 ? 'ajuste' : 'salida';
                
                $movimiento = $conn->prepare("INSERT INTO MOVIMIENTO_INVENTARIO 
                    (id_producto, tipo, cantidad, motivo, referencia, id_empleado, fecha_movimiento) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $referencia = "Ajuste manual de stock";
                $id_empleado = $_SESSION['empleado_id'] ?? null;
                $movimiento->bind_param("isisis", $id_producto, $tipo, abs($diferencia), $motivo, $referencia, $id_empleado);
                $movimiento->execute();
                
                $mensaje = "✅ Stock actualizado correctamente. {$producto['nombre']}: {$stock_anterior} → {$nuevo_stock}";
            } else {
                $error = "❌ Error al actualizar el stock";
            }
        } else {
            $error = "❌ Producto no encontrado";
        }
    }
}

// Obtener productos con stock bajo
$sql_stock_bajo = "SELECT p.id, p.nombre, p.stock_actual, p.stock_minimo, c.nombre as categoria
                    FROM PRODUCTO p
                    JOIN CATEGORIA_PRODUCTO c ON p.id_categoria = c.id
                    WHERE p.activo = 1 
                        AND p.maneja_stock = 1
                        AND p.stock_actual <= p.stock_minimo
                    ORDER BY p.stock_actual ASC
                    LIMIT 50";
$productos_stock_bajo = $conn->query($sql_stock_bajo);

// Obtener todos los productos
$sql_productos = "SELECT p.id, p.nombre, p.stock_actual, p.stock_minimo, c.nombre as categoria
                    FROM PRODUCTO p
                    JOIN CATEGORIA_PRODUCTO c ON p.id_categoria = c.id
                    WHERE p.activo = 1
                    ORDER BY p.nombre ASC";
$todos_productos = $conn->query($sql_productos);

// Obtener movimientos recientes
$sql_movimientos = "SELECT m.*, p.nombre as producto_nombre, e.nombre as empleado_nombre
                    FROM MOVIMIENTO_INVENTARIO m
                    JOIN PRODUCTO p ON m.id_producto = p.id
                    LEFT JOIN EMPLEADO e ON m.id_empleado = e.id
                    ORDER BY m.fecha_movimiento DESC
                    LIMIT 20";
$movimientos_recientes = $conn->query($sql_movimientos);

// Estadísticas
$total_productos = $conn->query("SELECT COUNT(*) as total FROM PRODUCTO WHERE activo = 1")->fetch_assoc()['total'];
$stock_bajo_total = $conn->query("SELECT COUNT(*) as total FROM PRODUCTO WHERE activo = 1 AND maneja_stock = 1 AND stock_actual <= stock_minimo")->fetch_assoc()['total'];
$agotados = $conn->query("SELECT COUNT(*) as total FROM PRODUCTO WHERE activo = 1 AND maneja_stock = 1 AND stock_actual = 0")->fetch_assoc()['total'];
$valor_inventario = $conn->query("SELECT SUM(stock_actual * precio_compra) as total FROM PRODUCTO WHERE activo = 1 AND maneja_stock = 1")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caja - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/caja_dashboard.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Panel de Caja</h1>
        <div>
            <a href="../citas.php" target="_blank" class="btn-citas" style="color: white;">📝 Agendar Cita</a>
            <a href="punto_venta.php" class="btn-principal" style="color: white;">💰 Punto de Venta</a>
            <a href="corte_caja.php" class="btn-corte" style="color: white;">📊 Corte de Caja</a>
            
            <div class="dropdown">
                <a href="javascript:void(0)">🛒 Productos ▼</a>
                <div class="dropdown-content">
                    <a href="#buscar-producto">🔍 Buscar Producto</a>
                </div>
            </div>

            <div class="dropdown">
                <a href="javascript:void(0)">📋 Formatos Digitales ▼</a>
                <div class="dropdown-content">
                    <a href="imprimir_consentimiento_formato.php">📝 Consentimiento Informado</a>
                    <a href="formatos_digitales/formato_ingreso_estetica.php">📝 Formato de Ingreso a estética/baño</a>
                    <a href="formatos_digitales/acta_compromiso.php">📝 Acta Compromiso veterinaria</a>
                    <a href="formatos_digitales/desparacitacion.php">📝 Desparacitación</a>
                    <a href="formatos_digitales/autorizacion_estetica.php">📝 Formato de autorización</a>
                    <hr style="margin: 5px 0; border-color: #eee;">
                    <a href="formatos_lista.php">📁 Ver todos los formatos guardados</a>
                </div>
            </div>
            <a href="clientes.php">📋 Lista de Clientes</a>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <!-- Tarjeta de bienvenida -->
        <div style="background: linear-gradient(135deg, #E68D0B, #f0a33a); color: white; padding: 20px; border-radius: 15px; margin-bottom: 20px;">
            <h2 style="margin: 0;">¡Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?>! 👋</h2>
            <p style="margin: 5px 0 0 0; opacity: 0.9;">Hoy es <?php echo date('d/m/Y'); ?> - <?php echo date('h:i A'); ?></p>
        </div>

        <!-- Mensajes -->
        <?php if ($mensaje): ?>
            <div class="alert-success" style="background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px;"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px;"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- ========== LISTADO DE CITAS ========== -->
        <div class="section">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 15px;">
                <h2 style="margin: 0;">📋 Listado de Citas</h2>
                <div style="display: flex; gap: 10px;">
                    <a href="../citas.php" target="_blank" class="btn-small" style="background: #4caf50;">📝 Nueva Cita</a>
                    <a href="cita_cliente_registrado.php" target="_blank" class="btn-small" style="background: #2196f3;">👥 Cita con Cliente Registrado</a>
                    <button onclick="abrirModalMascotas()" class="btn-small" style="background: #9c27b0;">🐾 Ver Historial de Mascotas</button>
                    <button onclick="eliminarCitasCanceladas()" class="btn-small" style="background: #f44336;">🗑️ Eliminar Citas Canceladas</button>
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
                            <th>Formatos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($citas && $citas->num_rows > 0): ?>
                            <?php while($row = $citas->fetch_assoc()): 
                                $origen = $row['origen'] ?? 'Presencial';
                                $origen_class = ($origen == 'Whatsapp') ? 'origen-whatsapp' : 'origen-presencial';
                                $origen_icono = ($origen == 'Whatsapp') ? '💬' : '🏥';
                                $origen_texto = ($origen == 'Whatsapp') ? 'WhatsApp' : 'Presencial';
                            ?>
                            <tr>
                                <td><?php echo $row['cita_id']; ?></td>
                                <td>
                                    <?php if (!empty($row['foto']) && file_exists('../' . $row['foto'])): ?>
                                        <img src="../<?php echo $row['foto']; ?>" class="foto-miniatura">
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
                                <td>$<?php echo number_format($row['total_servicios'], 2); ?></td>
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
                                    <a href="editar_cita.php?id=<?php echo $row['cita_id']; ?>" class="btn-small" style="background: #ff9800; color: white;">✏️ Editar</a>
                                    <?php if ($row['estado'] == 'pendiente'): ?>
                                        <a href="actualizar_estado.php?id=<?php echo $row['cita_id']; ?>&estado=confirmada" class="btn-small" style="background:#4caf50;">Confirmar</a>
                                    <?php endif; ?>
                                    <?php if ($row['estado'] != 'cancelada' && $row['estado'] != 'completada'): ?>
                                        <a href="actualizar_estado.php?id=<?php echo $row['cita_id']; ?>&estado=cancelada" class="btn-small" style="background:#f44336;">Cancelar</a>
                                    <?php endif; ?>
                                </span>
                                <td>
                                    <button onclick="verFormatosCita(<?php echo $row['cita_id']; ?>, <?php echo $row['cliente_id']; ?>)" 
                                            class="btn-formatos">
                                        📋 Formatos
                                    </button>
                                 </span>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="13" style="text-align: center; padding: 40px;">No hay citas registradas</span>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- ========== FIN LISTADO DE CITAS ========== -->

        <!-- Estadísticas rápidas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_productos; ?></div>
                <div class="stat-label">Productos Activos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stock_bajo_total; ?></div>
                <div class="stat-label">Productos con Stock Bajo</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $agotados; ?></div>
                <div class="stat-label">Productos Agotados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($valor_inventario, 0); ?></div>
                <div class="stat-label">Valor del Inventario</div>
            </div>
        </div>

        <!-- Productos con Stock Crítico -->
        <div class="section">
            <h2>⚠️ Productos con Stock Crítico <span class="badge-count"><?php echo $productos_stock_bajo->num_rows; ?></span></h2>
            <div class="productos-grid" style="max-height: 400px; overflow-y: auto;">
                <?php if ($productos_stock_bajo->num_rows > 0): ?>
                    <?php while($prod = $productos_stock_bajo->fetch_assoc()): ?>
                    <div class="producto-card">
                        <div class="producto-nombre"><?php echo htmlspecialchars($prod['nombre']); ?></div>
                        <div class="producto-categoria" style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($prod['categoria']); ?></div>
                        <div class="producto-stock">
                            <span>Stock actual:</span>
                            <span class="stock-actual stock-critico-valor">
                                <?php echo $prod['stock_actual']; ?> unidades
                            </span>
                        </div>
                        <div class="producto-stock">
                            <span>Stock mínimo:</span>
                            <span class="stock-minimo"><?php echo $prod['stock_minimo']; ?> unidades</span>
                        </div>
                        <?php if ($_SESSION['rol'] !== 'caja'): ?>
                            <button class="btn-actualizar" onclick="abrirModalStock(<?php echo $prod['id']; ?>, '<?php echo addslashes($prod['nombre']); ?>', <?php echo $prod['stock_actual']; ?>)">
                                📦 Actualizar stock
                            </button>
                        <?php else: ?>
                            <div style="font-size: 12px; color: #999; margin-top: 10px;">
                                🔒 Solo administradores pueden actualizar stock
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color: #4caf50; text-align: center;">✅ No hay productos con stock crítico</p>
                <?php endif; ?>
                </div>
            </div>

        <!-- Buscador de Productos -->
        <div id="buscar-producto" class="section">
            <h2>🔍 Buscar Producto</h2>
            <div class="buscador">
                <input type="text" id="buscadorProductos" placeholder="🔎 Buscar por nombre..." autocomplete="off" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
            </div>
            <div id="productosLista" class="productos-grid" style="max-height: 400px; overflow-y: auto;">
                <?php
                if ($todos_productos && $todos_productos->num_rows > 0):
                    while($prod = $todos_productos->fetch_assoc()):
                ?>
                <div class="producto-card" data-nombre="<?php echo strtolower($prod['nombre']); ?>">
                    <div class="producto-nombre"><?php echo htmlspecialchars($prod['nombre']); ?></div>
                    <div class="producto-categoria" style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($prod['categoria']); ?></div>
                    <div class="producto-stock">
                        <span>Stock actual:</span>
                        <span class="stock-actual <?php echo $prod['stock_actual'] == 0 ? 'stock-critico-valor' : ($prod['stock_actual'] <= $prod['stock_minimo'] ? 'stock-bajo-valor' : 'stock-normal-valor'); ?>">
                            <?php echo $prod['stock_actual']; ?> unidades
                        </span>
                    </div>
                    <div class="producto-stock">
                        <span>Stock mínimo:</span>
                        <span class="stock-minimo"><?php echo $prod['stock_minimo']; ?> unidades</span>
                    </div>
                    <?php if ($_SESSION['rol'] !== 'caja'): ?>
                        <button class="btn-actualizar" onclick="abrirModalStock(<?php echo $prod['id']; ?>, '<?php echo addslashes($prod['nombre']); ?>', <?php echo $prod['stock_actual']; ?>)">
                            📦 Actualizar stock
                        </button>
                    <?php else: ?>
                        <div style="font-size: 12px; color: #999; margin-top: 10px;">
                            🔒 Solo administradores pueden actualizar stock
                        </div>
                    <?php endif; ?>
                </div>
                <?php
                    endwhile;
                endif;
                ?>
            </div>
        </div>

        <!-- Movimientos Recientes -->
        <div class="section">
            <h2>📋 Movimientos de Inventario Recientes</h2>
            <?php if ($movimientos_recientes && $movimientos_recientes->num_rows > 0): ?>
            <div style="overflow-x: auto;">
                <table class="citas-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Motivo</th>
                            <th>Empleado</th>
                            <th>Formatos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($mov = $movimientos_recientes->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])); ?></span>
                            <td><?php echo htmlspecialchars($mov['producto_nombre']); ?></span>
                            <td>
                                <span class="movimiento-<?php echo $mov['tipo']; ?>">
                                    <?php 
                                    $tipos = ['entrada' => '📥 Entrada', 'salida' => '📤 Salida', 'ajuste' => '🔧 Ajuste', 'devolucion' => '🔄 Devolución'];
                                    echo $tipos[$mov['tipo']] ?? $mov['tipo'];
                                    ?>
                                </span>
                             </span>
                            <td><?php echo $mov['cantidad']; ?> unidades</span>
                            <td><?php echo htmlspecialchars($mov['motivo'] ?? '-'); ?></span>
                            <td><?php echo htmlspecialchars($mov['empleado_nombre'] ?? 'Sistema'); ?></span>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p style="text-align: center; color: #999;">No hay movimientos registrados</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para Actualizar Stock -->
    <div id="modalStock" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📦 Actualizar Stock</h3>
                <span class="close-modal" onclick="cerrarModalStock()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="actualizar_stock">
                <input type="hidden" id="modal_id_producto" name="id_producto">
                <div class="form-group">
                    <label>Producto</label>
                    <input type="text" id="modal_producto_nombre" readonly style="background: #f5f5f5;">
                </div>
                <div class="form-group">
                    <label>Stock actual</label>
                    <input type="text" id="modal_stock_actual" readonly style="background: #f5f5f5;">
                </div>
                <div class="form-group">
                    <label>Nuevo stock *</label>
                    <input type="number" name="nuevo_stock" id="modal_nuevo_stock" required min="0">
                </div>
                <div class="form-group">
                    <label>Motivo (opcional)</label>
                    <textarea name="motivo" rows="2" placeholder="Ej: Ajuste de inventario, nueva compra, etc."></textarea>
                </div>
                <button type="submit" class="btn-guardar">💾 Actualizar Stock</button>
            </form>
        </div>
    </div>

    <!-- Modal de Historial de Mascotas -->
    <div id="modalMascotas" class="modal">
        <div class="modal-content modal-grande">
            <div class="modal-header" style="background: #9c27b0; padding: 15px; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="color: white; margin: 0;">🐾 Historial de Mascotas</h2>
                <span class="close-mascotas" style="color: white; font-size: 28px; cursor: pointer;">&times;</span>
            </div>
            <div class="modal-body" id="modalMascotasBody" style="padding: 20px; max-height: 500px; overflow-y: auto;">
                <div style="text-align: center; padding: 40px;">
                    Cargando...
                </div>
            </div>
        </div>
    </div>
<script src="../assets/js/caja_dashboard.js"></script>
</body>
</html>