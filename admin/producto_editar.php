<?php
session_start();

// Cambiar de admin_logged a user_id
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

$id_producto = (int)($_GET['id'] ?? 0);
if (!$id_producto) {
    header('Location: productos.php');
    exit;
}

// Obtener datos del producto
$sql_producto = "SELECT * FROM PRODUCTO WHERE id = ?";
$stmt = $conn->prepare($sql_producto);
$stmt->bind_param("i", $id_producto);
$stmt->execute();
$result = $stmt->get_result();
$producto = $result->fetch_assoc();

if (!$producto) {
    header('Location: productos.php');
    exit;
}

// Obtener categorías para el select
$sql_categorias = "SELECT id, nombre FROM CATEGORIA_PRODUCTO WHERE activo = 1";
$categorias = $conn->query($sql_categorias);

// Obtener proveedores activos
$sql_proveedores = "SELECT id, nombre FROM PROVEEDOR WHERE activo = 1 ORDER BY nombre ASC";
$proveedores = $conn->query($sql_proveedores);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $codigo_barras = trim($_POST['codigo_barras']);
    if($codigo_barras === ''){
        $codigo_barras = null;
    }
    $id_categoria = (int)$_POST['id_categoria'];
    $id_proveedor = !empty($_POST['id_proveedor']) ? (int)$_POST['id_proveedor'] : null;
    $precio_compra = (float)$_POST['precio_compra'];
    $precio_venta = (float)$_POST['precio_venta'];
    $stock_actual = (int)$_POST['stock_actual'];
    $stock_minimo = (int)$_POST['stock_minimo'];
    $unidad_medida = $_POST['unidad_medida'];
    $ubicacion = trim($_POST['ubicacion']);
    $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;
    $activo = isset($_POST['activo']) ? 1 : 0;
    $maneja_stock = isset($_POST['maneja_stock']) ? 0 : 1;
    
    // Si no maneja stock, forzar stock_actual = 0 y stock_minimo = 0
    if ($maneja_stock == 0) {
        $stock_actual = 0;
        $stock_minimo = 0;
        $ubicacion = null;
        $fecha_vencimiento = null;
    }
    
    // Crear directorio si no existe
    $upload_dir = __DIR__ . '/../assets/images/productos/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Manejo de imagen (si se sube una nueva)
    $imagen = $producto['imagen']; // mantener la actual por defecto
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $nombre_imagen = 'producto_' . time() . '_' . uniqid() . '.' . $extension;
        $ruta_destino = $upload_dir . $nombre_imagen;
        
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta_destino)) {
            $imagen = 'assets/images/productos/' . $nombre_imagen;
            // Eliminar imagen anterior si existe
            if (!empty($producto['imagen']) && file_exists('../' . $producto['imagen'])) {
                unlink('../' . $producto['imagen']);
            }
        }
    }
    
    $sql = "UPDATE PRODUCTO SET 
            nombre = ?, descripcion = ?, codigo_barras = ?, id_categoria = ?, id_proveedor = ?,
            precio_compra = ?, precio_venta = ?, stock_actual = ?, stock_minimo = ?, 
            unidad_medida = ?, ubicacion = ?, fecha_vencimiento = ?, imagen = ?, activo = ?, maneja_stock = ? 
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssiiiddiissssii", 
        $nombre, $descripcion, $codigo_barras, $id_categoria, $id_proveedor,
        $precio_compra, $precio_venta, $stock_actual, $stock_minimo, 
        $unidad_medida, $ubicacion, $fecha_vencimiento, $imagen, $activo, $maneja_stock, $id_producto
    );
    
    if ($stmt->execute()) {
        header('Location: productos.php?success=2');
        exit;
    } else {
        $error = "Error al actualizar: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/producto_editar.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Editar Producto</h1>
        <div>
            <a href="productos.php">← Volver</a>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Nombre del producto *</label>
                <input type="text" name="nombre" value="<?php echo htmlspecialchars($producto['nombre']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" rows="4"><?php echo htmlspecialchars($producto['descripcion']); ?></textarea>
            </div>

            <!-- Código de barras con generador -->
            <div class="form-group">
                <label>Código de barras</label>
                <div class="codigo-wrapper">
                    <input type="text" name="codigo_barras" id="codigo_barras" value="<?php echo htmlspecialchars($producto['codigo_barras']); ?>" placeholder="Déjalo vacío para generar automáticamente">
                    <button type="button" class="btn-generar" id="btnGenerarCodigo">🎲 Generar</button>
                </div>
                <p class="info-text">
                    ⚡ Si dejas el campo vacío, se generará un código único automáticamente al guardar.
                </p>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Categoría *</label>
                    <div style="display: flex; gap: 10px;">
                        <select name="id_categoria" required style="flex: 1;">
                            <option value="">Seleccionar...</option>
                            <?php while($cat = $categorias->fetch_assoc()): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $producto['id_categoria'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo $cat['nombre']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <a href="categorias_productos.php" target="_blank" class="btn-small" style="background: #2196f3; color: white; padding: 10px 15px; border-radius: 5px; text-decoration: none; white-space: nowrap; display: inline-flex; align-items: center; gap: 5px;">
                            📁 Gestionar
                        </a>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Proveedor</label>
                    <div style="display: flex; gap: 10px;">
                        <select name="id_proveedor" style="flex: 1;">
                            <option value="">-- Seleccionar proveedor --</option>
                            <?php 
                            // Resetear puntero del resultado
                            $proveedores->data_seek(0);
                            while($prov = $proveedores->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $prov['id']; ?>" <?php echo $producto['id_proveedor'] == $prov['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prov['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <a href="proveedor_nuevo.php" target="_blank" class="btn-small" style="background: #4caf50; color: white; padding: 10px 15px; border-radius: 5px; text-decoration: none; white-space: nowrap; display: inline-flex; align-items: center; gap: 5px;">
                            ➕ Nuevo
                        </a>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Unidad de medida</label>
                    <select name="unidad_medida">
                        <option value="pieza" <?php echo $producto['unidad_medida'] == 'pieza' ? 'selected' : ''; ?>>Pieza</option>
                        <option value="kg" <?php echo $producto['unidad_medida'] == 'kg' ? 'selected' : ''; ?>>Kilogramo</option>
                        <option value="litro" <?php echo $producto['unidad_medida'] == 'litro' ? 'selected' : ''; ?>>Litro</option>
                        <option value="bolsa" <?php echo $producto['unidad_medida'] == 'bolsa' ? 'selected' : ''; ?>>Bolsa</option>
                        <option value="caja" <?php echo $producto['unidad_medida'] == 'caja' ? 'selected' : ''; ?>>Caja</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Precio compra (MXN)</label>
                    <input type="number" step="0.01" name="precio_compra" value="<?php echo $producto['precio_compra']; ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Precio venta (MXN) *</label>
                    <input type="number" step="0.01" name="precio_venta" value="<?php echo $producto['precio_venta']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Stock actual</label>
                    <input type="number" name="stock_actual" id="stock_actual" value="<?php echo $producto['stock_actual']; ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Stock mínimo (alerta)</label>
                    <input type="number" name="stock_minimo" id="stock_minimo" value="<?php echo $producto['stock_minimo']; ?>">
                </div>
                
                <div class="form-group">
                    <label>Ubicación (estante)</label>
                    <input type="text" name="ubicacion" id="ubicacion" value="<?php echo htmlspecialchars($producto['ubicacion']); ?>" placeholder="Ej: Estante A1">
                </div>
            </div>

            <!-- CHECKBOX DE MANEJO DE STOCK -->
            <div class="form-group">
                <label>
                    <input type="checkbox" name="maneja_stock" id="maneja_stock" <?php echo ($producto['maneja_stock'] == 0) ? 'checked' : ''; ?> value="0" onchange="toggleStockFields()">
                    ❌ No maneja stock (es un servicio o producto sin inventario)
                </label>
                <small style="color:#666;">Marca esta opción si es un servicio (estética, baño, consulta) o un producto que no requiere control de inventario</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Fecha vencimiento</label>
                    <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" value="<?php echo $producto['fecha_vencimiento']; ?>">
                </div>
                
                <div class="form-group">
                    <label>Imagen del producto</label>
                    <?php if(!empty($producto['imagen'])): ?>
                        <div class="imagen-actual">
                            <img src="../<?php echo $producto['imagen']; ?>" alt="Imagen actual">
                            <p><small>Imagen actual. Sube una nueva para reemplazarla.</small></p>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="imagen" accept="image/*">
                    <small style="color:#666;">Formatos: JPG, PNG, GIF. Tamaño recomendado: 300x300px</small>
                </div>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="activo" <?php echo $producto['activo'] ? 'checked' : ''; ?>> Producto activo (visible en tienda)
                </label>
            </div>
            
            <div style="margin-top: 30px;">
                <button type="submit" class="btn-guardar">Guardar Cambios</button>
                <a href="productos.php" class="btn-cancelar">Cancelar</a>
            </div>
        </form>
    </div>

    <script src="../assets/js/producto_editar.js"></script>
</body>
</html>