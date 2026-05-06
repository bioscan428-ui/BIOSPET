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
            try {
                // Preparar los productos en el formato que espera el procedimiento
                // El procedimiento espera: [{"id_producto": 7, "cantidad": 10, "precio_unitario": 20}]
                $productos_procedimiento = [];
                foreach ($productos_compra as $item) {
                    $productos_procedimiento[] = [
                        'id_producto' => (int)$item['id_producto'],
                        'cantidad' => (int)$item['cantidad'],
                        'precio_unitario' => (float)$item['precio_unitario']
                    ];
                }
                
                $json_procedimiento = json_encode($productos_procedimiento);
                $sql_test = "CALL test_json(?)";
$stmt_test = $conn->prepare($sql_test);
$stmt_test->bind_param("s", $json_procedimiento);
$stmt_test->execute();
$result_test = $stmt_test->get_result();

echo "<pre>";
echo "=== TEST JSON ===\n";
while ($row = $result_test->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";

$stmt_test->close();
$conn->next_result();
                
                // Llamar al procedimiento almacenado registrar_compra

                //depuracion

                //fin depuracion
                $sql = "CALL registrar_compra(?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $empleado_id = $_SESSION['empleado_id'] ?? 1;
                $stmt->bind_param("isis", $id_proveedor, $folio_factura, $empleado_id, $json_procedimiento);
                $stmt->execute();
                
                // Obtener el resultado (ID de compra y total)
                $result = $stmt->get_result();
                $compra = $result->fetch_assoc();
                $stmt->close();
                $conn->next_result(); // Limpiar resultados pendientes
                
                $_SESSION['mensaje'] = "✅ Compra registrada exitosamente. ID: {$compra['compra_id']}, Total: \${$compra['total_compra']}";
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
            <div class="error" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                ❌ <?php echo $error; ?>
            </div>
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
            
            <!-- Sección de productos -->
            <div class="productos-section">
                <h3>📦 Agregar Producto</h3>
                
                <!-- Selector de productos existentes -->
                <div class="producto-card">
                    <div class="producto-row">
                        <select id="productoExistente" style="flex:2; padding: 8px;">
                            <option value="">-- Seleccionar producto existente --</option>
                            <?php
                            $productos_existentes = $conn->query("SELECT id, nombre, precio_compra FROM PRODUCTO WHERE activo = 1 ORDER BY nombre");
                            while($prod = $productos_existentes->fetch_assoc()):
                            ?>
                                <option value="<?php echo $prod['id']; ?>" data-precio="<?php echo $prod['precio_compra']; ?>">
                                    <?php echo htmlspecialchars($prod['nombre']); ?> ($<?php echo number_format($prod['precio_compra'], 2); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <input type="number" id="productoCantidad" placeholder="Cantidad *" min="1" value="1" style="flex:1;">
                        <input type="number" id="productoPrecio" placeholder="Precio unitario *" step="0.01" style="flex:1;">
                        <button type="button" id="btnAgregarProducto" class="btn-agregar">+ Agregar</button>
                    </div>
                    
                    <!-- Checkbox para producto nuevo -->
                    <div class="checkbox-group">
                        <input type="checkbox" id="esNuevoProducto">
                        <label for="esNuevoProducto">📦 Este es un producto NUEVO (no está en el inventario)</label>
                    </div>
                    
                    <!-- Campos para producto nuevo -->
                    <div id="nuevoProductoFields" class="nuevo-producto-fields">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nombre del nuevo producto *</label>
                                <input type="text" id="nuevoProductoNombre" placeholder="Ej: Croquetas Premium">
                            </div>
                            <div class="form-group">
                                <label>Categoría *</label>
                                <select id="nuevaCategoria">
                                    <option value="">Seleccionar categoría...</option>
                                    <?php 
                                    $categorias2 = $conn->query("SELECT id, nombre FROM CATEGORIA_PRODUCTO WHERE activo = 1 ORDER BY nombre");
                                    while($cat = $categorias2->fetch_assoc()): ?>
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
                
                <!-- Tabla de productos agregados -->
                <table class="productos-agregados" id="tablaProductos">
                    <thead>
                        <tr>
                            <th>ID Producto</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="tablaProductosBody">
                        <tr class="empty-row">
                            <td colspan="6" style="text-align: center; color: #999;">No hay productos agregados</td>
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