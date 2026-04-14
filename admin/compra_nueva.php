<?php
session_start();

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol para acceso
if (!in_array($_SESSION['rol'], ['super_admin', 'admin'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener lista de proveedores activos
$proveedores = $conn->query("SELECT id, nombre FROM PROVEEDOR WHERE activo = 1 ORDER BY nombre");

// Obtener lista de categorías para nuevo producto
$categorias = $conn->query("SELECT id, nombre FROM CATEGORIA_PRODUCTO WHERE activo = 1 ORDER BY nombre");

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_proveedor = (int)$_POST['id_proveedor'];
    $folio_factura = trim($_POST['folio_factura'] ?? '');
    $productos_json = $_POST['productos_json'] ?? '';
    
    if (!$id_proveedor) {
        $error = "Debe seleccionar un proveedor";
    } elseif (empty($productos_json)) {
        $error = "Debe agregar al menos un producto";
    } else {
        $productos_compra = json_decode($productos_json, true);
        
        if (empty($productos_compra)) {
            $error = "No hay productos válidos en la compra";
        } else {
            $total = 0;
            foreach ($productos_compra as $item) {
                $total += $item['subtotal'];
            }
            
            $conn->begin_transaction();
            
            try {
                // 1. Insertar cabecera de compra
                $sql_compra = "INSERT INTO COMPRA (id_proveedor, fecha_compra, folio_factura, total, id_empleado) 
                               VALUES (?, CURDATE(), ?, ?, ?)";
                $stmt_compra = $conn->prepare($sql_compra);
                $stmt_compra->bind_param("isdi", $id_proveedor, $folio_factura, $total, $_SESSION['empleado_id']);
                $stmt_compra->execute();
                $id_compra = $conn->insert_id;
                
                // 2. Insertar detalles de compra y actualizar stock
                foreach ($productos_compra as $item) {
                    // Si el producto es nuevo (id = 0), crearlo primero
                    $id_producto = $item['id_producto'];
                    
                    if ($id_producto == 0 && !empty($item['nombre_nuevo'])) {
                        // Crear nuevo producto
                        $sql_new_product = "INSERT INTO PRODUCTO (nombre, id_categoria, precio_compra, precio_venta, stock_actual, activo) 
                                           VALUES (?, ?, ?, ?, 0, 1)";
                        $stmt_new = $conn->prepare($sql_new_product);
                        $precio_venta_sugerido = $item['precio_unitario'] * 1.3; // 30% de margen
                        $stmt_new->bind_param("sidd", $item['nombre_nuevo'], $item['id_categoria'], $item['precio_unitario'], $precio_venta_sugerido);
                        $stmt_new->execute();
                        $id_producto = $conn->insert_id;
                    }
                    
                    // Insertar detalle de compra
                    $sql_detalle = "INSERT INTO DETALLE_COMPRA (id_compra, id_producto, cantidad, precio_unitario, subtotal) 
                                    VALUES (?, ?, ?, ?, ?)";
                    $stmt_detalle = $conn->prepare($sql_detalle);
                    $stmt_detalle->bind_param("iiidd", $id_compra, $id_producto, $item['cantidad'], $item['precio_unitario'], $item['subtotal']);
                    $stmt_detalle->execute();
                    
                    // Actualizar stock del producto
                    $sql_update_stock = "UPDATE PRODUCTO SET stock_actual = stock_actual + ? WHERE id = ?";
                    $stmt_update = $conn->prepare($sql_update_stock);
                    $stmt_update->bind_param("ii", $item['cantidad'], $id_producto);
                    $stmt_update->execute();
                }
                
                $conn->commit();
                $_SESSION['mensaje'] = "Compra registrada correctamente";
                header('Location: compras.php');
                exit;
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error al registrar la compra: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Compra - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/compra_nueva.css">

</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Nueva Compra a Proveedor</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="proveedores.php">🏭 Proveedores</a>
            <a href="compras.php">🛒 Compras</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <h2>Registrar Compra</h2>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" id="formCompra">
            <div class="form-row">
                <div class="form-group">
                    <label>Proveedor *</label>
                    <select name="id_proveedor" required>
                        <option value="">Seleccionar proveedor...</option>
                        <?php while($prov = $proveedores->fetch_assoc()): ?>
                            <option value="<?php echo $prov['id']; ?>"><?php echo htmlspecialchars($prov['nombre']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Folio de factura</label>
                    <input type="text" name="folio_factura" placeholder="Opcional">
                </div>
            </div>
            
            <!-- Sección de productos - INPUT LIBRE -->
            <div class="productos-section">
                <h3>📦 Agregar Producto</h3>
                
                <div class="producto-card">
                    <div class="producto-row">
                        <input type="text" id="productoNombre" placeholder="Nombre del producto *" style="flex:2;">
                        <input type="number" id="productoCantidad" placeholder="Cantidad *" min="1" value="1" style="flex:1;">
                        <input type="number" id="productoPrecio" placeholder="Precio unitario *" step="0.01" style="flex:1;">
                        <button type="button" id="btnAgregarProducto" class="btn-agregar">+ Agregar</button>
                    </div>
                    
                    <!-- Checkbox para producto nuevo (si no existe en inventario) -->
                    <div class="checkbox-group">
                        <input type="checkbox" id="esNuevoProducto">
                        <label for="esNuevoProducto">📦 Este es un producto NUEVO (no está en el inventario)</label>
                    </div>
                    
                    <!-- Campos para producto nuevo -->
                    <div id="nuevoProductoFields" class="nuevo-producto-fields">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Categoría *</label>
                                <select id="nuevaCategoria">
                                    <option value="">Seleccionar categoría...</option>
                                    <?php while($cat = $categorias->fetch_assoc()): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Precio de venta sugerido</label>
                                <input type="text" id="precioVentaSugerido" readonly style="background:#f5f5f5;">
                                <small>Se calculará automáticamente (precio compra + 30%)</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <table class="productos-agregados" id="tablaProductos">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="tablaProductosBody">
                        <tr class="empty-row">
                            <td colspan="5" style="text-align: center; color: #999;">No hay productos agregados</td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="total-compra">
                    Total de la compra: <span id="totalCompra">$0.00</span>
                </div>
                
                <input type="hidden" name="productos_json" id="productos_json">
            </div>
            
            <div style="margin-top: 30px;">
                <button type="submit" class="btn-guardar">Guardar Compra</button>
                <a href="compras.php" class="btn-cancelar">Cancelar</a>
            </div>
        </form>
    </div>
    
        <script src="../assets/js/compra_nueva.js"></script>

</body>
</html>