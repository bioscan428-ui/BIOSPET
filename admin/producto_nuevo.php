<?php
session_start();
if (!isset($_SESSION['admin_logged'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener categorías para el select
$sql_categorias = "SELECT id, nombre FROM CATEGORIA_PRODUCTO WHERE activo = 1";
$categorias = $conn->query($sql_categorias);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $codigo_barras = trim($_POST['codigo_barras']);
    $id_categoria = (int)$_POST['id_categoria'];
    $precio_compra = (float)$_POST['precio_compra'];
    $precio_venta = (float)$_POST['precio_venta'];
    $stock_actual = (int)$_POST['stock_actual'];
    $stock_minimo = (int)$_POST['stock_minimo'];
    $unidad_medida = $_POST['unidad_medida'];
    $ubicacion = trim($_POST['ubicacion']);
    $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    // Manejo de imagen (opcional)
    $imagen = '';
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $nombre_imagen = 'producto_' . time() . '.' . $extension;
        $ruta_destino = '../assets/images/productos/' . $nombre_imagen;
        
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta_destino)) {
            $imagen = 'assets/images/productos/' . $nombre_imagen;
        }
    }
    
    $sql = "INSERT INTO PRODUCTO (nombre, descripcion, codigo_barras, id_categoria, precio_compra, precio_venta, 
                                   stock_actual, stock_minimo, unidad_medida, ubicacion, 
                                   fecha_vencimiento, imagen, activo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssiddiissssi", $nombre, $descripcion, $codigo_barras, $id_categoria, $precio_compra, 
                      $precio_venta, $stock_actual, $stock_minimo, $unidad_medida, 
                      $ubicacion, $fecha_vencimiento, $imagen, $activo);
    
    if ($stmt->execute()) {
        header('Location: productos.php?success=1');
        exit;
    } else {
        $error = "Error al guardar: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Producto - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { background: var(--muted); }
        .admin-header { background: var(--primary); color: white; padding: 20px; }
        .admin-header a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 800px; margin: 20px auto; padding: 20px; background: white; border-radius: var(--radius-md); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: var(--radius-sm); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .btn-guardar { background: var(--primary); color: white; padding: 12px 30px; border: none; border-radius: var(--radius-sm); cursor: pointer; }
        .btn-cancelar { background: #666; color: white; padding: 12px 30px; text-decoration: none; border-radius: var(--radius-sm); display: inline-block; margin-left: 10px; }
        .error { color: red; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Nuevo Producto</h1>
        <a href="productos.php">← Volver</a>
        <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
        <a href="logout.php">🚪 Cerrar Sesión</a>
    </div>

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Nombre del producto *</label>
                <input type="text" name="nombre" required>
            </div>
            
            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" rows="4"></textarea>
            </div>

            <div class="form-group">
                <label>Codigo de barras</label>
                <textarea name="codigo_barras" rows="1"></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Categoría *</label>
                    <select name="id_categoria" required>
                        <option value="">Seleccionar...</option>
                        <?php while($cat = $categorias->fetch_assoc()): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo $cat['nombre']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Unidad de medida</label>
                    <select name="unidad_medida">
                        <option value="pieza">Pieza</option>
                        <option value="kg">Kilogramo</option>
                        <option value="litro">Litro</option>
                        <option value="bolsa">Bolsa</option>
                        <option value="caja">Caja</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Precio compra (MXN)</label>
                    <input type="number" step="0.01" name="precio_compra" required>
                </div>
                <div class="form-group">
                    <label>Precio venta (MXN) *</label>
                    <input type="number" step="0.01" name="precio_venta" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Stock actual</label>
                    <input type="number" name="stock_actual" value="0">
                </div>
                <div class="form-group">
                    <label>Stock mínimo (alerta)</label>
                    <input type="number" name="stock_minimo" value="5">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Ubicación (estante)</label>
                    <input type="text" name="ubicacion" placeholder="Ej: Estante A1">
                </div>
                <div class="form-group">
                    <label>Fecha vencimiento</label>
                    <input type="date" name="fecha_vencimiento">
                </div>
            </div>
            
            <div class="form-group">
                <label>Imagen del producto</label>
                <input type="file" name="imagen" accept="image/*">
                <small style="color:#666;">Formatos: JPG, PNG, GIF. Tamaño recomendado: 300x300px</small>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="activo" checked> Producto activo (visible en tienda)
                </label>
            </div>
            
            <div style="margin-top: 30px;">
                <button type="submit" class="btn-guardar">Guardar Producto</button>
                <a href="productos.php" class="btn-cancelar">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>