<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol para acceso (solo recepcionistas y admin)
if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'recepcionista', 'caja'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Procesar actualización de stock
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'actualizar_stock') {
        $id_producto = (int)$_POST['id_producto'];
        $nuevo_stock = (int)$_POST['nuevo_stock'];
        $motivo = trim($_POST['motivo'] ?? 'Ajuste manual desde caja');
        
        // Validar que el producto existe
        $check = $conn->prepare("SELECT nombre, stock_actual FROM PRODUCTO WHERE id = ? AND activo = 1");
        $check->bind_param("i", $id_producto);
        $check->execute();
        $producto = $check->get_result()->fetch_assoc();
        
        if ($producto) {
            $stock_anterior = $producto['stock_actual'];
            
            // Actualizar stock
            $update = $conn->prepare("UPDATE PRODUCTO SET stock_actual = ? WHERE id = ?");
            $update->bind_param("ii", $nuevo_stock, $id_producto);
            
            if ($update->execute()) {
                // Registrar movimiento
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

// Obtener productos con stock bajo (crítico)
$sql_stock_bajo = "SELECT p.id, p.nombre, p.stock_actual, p.stock_minimo, c.nombre as categoria
                   FROM PRODUCTO p
                   JOIN CATEGORIA_PRODUCTO c ON p.id_categoria = c.id
                   WHERE p.activo = 1 AND p.stock_actual <= p.stock_minimo
                   ORDER BY p.stock_actual ASC
                   LIMIT 50";
$productos_stock_bajo = $conn->query($sql_stock_bajo);

// Obtener todos los productos (para buscar)
$sql_productos = "SELECT p.id, p.nombre, p.stock_actual, p.stock_minimo, c.nombre as categoria
                  FROM PRODUCTO p
                  JOIN CATEGORIA_PRODUCTO c ON p.id_categoria = c.id
                  WHERE p.activo = 1
                  ORDER BY p.nombre ASC";
$todos_productos = $conn->query($sql_productos);

// Obtener movimientos recientes de inventario
$sql_movimientos = "SELECT m.*, p.nombre as producto_nombre, e.nombre as empleado_nombre
                    FROM MOVIMIENTO_INVENTARIO m
                    JOIN PRODUCTO p ON m.id_producto = p.id
                    LEFT JOIN EMPLEADO e ON m.id_empleado = e.id
                    ORDER BY m.fecha_movimiento DESC
                    LIMIT 20";
$movimientos_recientes = $conn->query($sql_movimientos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caja - Gestión de Productos | BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { background: #f5f5f5; font-family: Arial, sans-serif; }
        .admin-header { background: #E68D0B; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .admin-header a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-number { font-size: 2rem; font-weight: bold; color: #E68D0B; }
        .stat-label { color: #666; margin-top: 5px; }
        .section { background: white; border-radius: 12px; padding: 20px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .section h2 { color: #333; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #E68D0B; display: inline-block; }
        .productos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; margin-top: 20px; }
        .producto-card { background: #f8f9fa; border-radius: 10px; padding: 15px; border-left: 4px solid #E68D0B; }
        .producto-card.stock-critico { border-left-color: #f44336; background: #fff5f5; }
        .producto-card.stock-bajo { border-left-color: #ff9800; background: #fff8f0; }
        .producto-nombre { font-weight: bold; font-size: 1rem; margin-bottom: 8px; }
        .producto-stock { display: flex; justify-content: space-between; align-items: center; margin: 10px 0; }
        .stock-actual { font-size: 1.2rem; font-weight: bold; }
        .stock-minimo { color: #666; font-size: 0.8rem; }
        .stock-critico-valor { color: #f44336; }
        .stock-bajo-valor { color: #ff9800; }
        .stock-normal-valor { color: #4caf50; }
        .btn-actualizar { background: #2196f3; color: white; border: none; padding: 6px 12px; border-radius: 5px; cursor: pointer; font-size: 12px; }
        .btn-actualizar:hover { background: #0b7dda; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 15px; width: 450px; max-width: 90%; padding: 25px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { color: #E68D0B; }
        .close-modal { cursor: pointer; font-size: 24px; color: #999; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn-guardar { background: #4caf50; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; width: 100%; }
        .btn-guardar:hover { background: #45a049; }
        .tabla-movimientos { width: 100%; border-collapse: collapse; }
        .tabla-movimientos th, .tabla-movimientos td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        .tabla-movimientos th { background: #f8f9fa; font-weight: bold; color: #666; }
        .movimiento-entrada { color: #4caf50; }
        .movimiento-salida { color: #f44336; }
        .movimiento-ajuste { color: #ff9800; }
        .buscador { margin-bottom: 20px; }
        .buscador input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .badge-count { background: #f44336; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 8px; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Caja / Inventario</h1>
        <div>
            <div class="dropdown">
                <a href="javascript:void(0)">🛒 Productos ▼</a>
                <div class="dropdown-content">
                    <a href="productos.php">📦 Gestión de Productos</a>
                    <a href="categorias_productos.php">📁 Categorías</a>
                    <a href="producto_nuevo.php">➕ Nuevo Producto</a>
                    <?php if ($_SESSION['rol'] !== 'caja'): ?>
                    <hr style="margin: 5px 0; border-color: #eee;">
                    <a href="proveedores.php">🏭 Proveedores</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <a href="caja_dashboard.php">📦 Inventario</a>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <!-- Mensajes -->
        <?php if ($mensaje): ?>
            <div class="alert-success" style="background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px;"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px;"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Estadísticas rápidas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php 
                    $total_productos = $conn->query("SELECT COUNT(*) as total FROM PRODUCTO WHERE activo = 1")->fetch_assoc()['total'];
                    echo $total_productos;
                ?></div>
                <div class="stat-label">Productos Activos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php 
                    $stock_bajo_total = $conn->query("SELECT COUNT(*) as total FROM PRODUCTO WHERE activo = 1 AND stock_actual <= stock_minimo")->fetch_assoc()['total'];
                    echo $stock_bajo_total;
                ?></div>
                <div class="stat-label">Productos con Stock Bajo</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php 
                    $agotados = $conn->query("SELECT COUNT(*) as total FROM PRODUCTO WHERE activo = 1 AND stock_actual = 0")->fetch_assoc()['total'];
                    echo $agotados;
                ?></div>
                <div class="stat-label">Productos Agotados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php 
                    $valor_inventario = $conn->query("SELECT SUM(stock_actual * precio_compra) as total FROM PRODUCTO WHERE activo = 1")->fetch_assoc()['total'];
                    echo '$' . number_format($valor_inventario, 0);
                ?></div>
                <div class="stat-label">Valor del Inventario</div>
            </div>
        </div>

        <!-- Productos con Stock Crítico (Alerta) -->
        <div class="section">
            <h2>⚠️ Productos con Stock Crítico <span class="badge-count"><?php echo $productos_stock_bajo->num_rows; ?></span></h2>
            <div class="productos-grid">
                <?php if ($productos_stock_bajo->num_rows > 0): ?>
                    <?php while($prod = $productos_stock_bajo->fetch_assoc()): 
                        $stock_class = $prod['stock_actual'] == 0 ? 'stock-critico' : ($prod['stock_actual'] <= $prod['stock_minimo'] ? 'stock-bajo' : '');
                    ?>
                    <div class="producto-card stock-<?php echo $stock_class; ?>">
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
                        <button class="btn-actualizar" onclick="abrirModalStock(<?php echo $prod['id']; ?>, '<?php echo addslashes($prod['nombre']); ?>', <?php echo $prod['stock_actual']; ?>)">
                            📦 Actualizar stock
                        </button>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color: #4caf50; text-align: center;">✅ No hay productos con stock crítico</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Buscador de Productos -->
        <div class="section">
            <h2>🔍 Buscar Producto</h2>
            <div class="buscador">
                <input type="text" id="buscadorProductos" placeholder="🔎 Buscar por nombre..." autocomplete="off">
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
                    <button class="btn-actualizar" onclick="abrirModalStock(<?php echo $prod['id']; ?>, '<?php echo addslashes($prod['nombre']); ?>', <?php echo $prod['stock_actual']; ?>)">
                        📦 Actualizar stock
                    </button>
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
                <table class="tabla-movimientos">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Motivo</th>
                            <th>Empleado</th>
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
                            <td>
                                <?php echo $mov['cantidad']; ?> unidades
                             </span>
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

    <script>
        // Buscador de productos
        const buscador = document.getElementById('buscadorProductos');
        if (buscador) {
            buscador.addEventListener('input', function() {
                const termino = this.value.toLowerCase();
                const productos = document.querySelectorAll('#productosLista .producto-card');
                productos.forEach(card => {
                    const nombre = card.getAttribute('data-nombre');
                    if (nombre && nombre.includes(termino)) {
                        card.style.display = '';
                    } else if (!nombre && termino === '') {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        }

        // Modal de stock
        const modalStock = document.getElementById('modalStock');
        
        function abrirModalStock(id, nombre, stockActual) {
            document.getElementById('modal_id_producto').value = id;
            document.getElementById('modal_producto_nombre').value = nombre;
            document.getElementById('modal_stock_actual').value = stockActual + ' unidades';
            document.getElementById('modal_nuevo_stock').value = stockActual;
            modalStock.classList.add('active');
        }
        
        function cerrarModalStock() {
            modalStock.classList.remove('active');
        }
        
        // Cerrar modal al hacer clic fuera
        modalStock.addEventListener('click', function(e) {
            if (e.target === modalStock) {
                cerrarModalStock();
            }
        });
    </script>
</body>
</html>