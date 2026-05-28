<?php
session_start();
$es_caja = ($_SESSION['rol'] === 'caja');

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol
if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'caja'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Función para generar código de barras único
function generarCodigoBarras($conn, $intento = 1) {
    $prefijo = 'BIO';
    $fecha = date('ymd');
    $aleatorio = str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    $codigo_base = $prefijo . $fecha . $aleatorio;
    $digito_control = calcularDigitoControl($codigo_base);
    $codigo_barras = $codigo_base . $digito_control;
    
    $sql_check = "SELECT id FROM PRODUCTO WHERE codigo_barras = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("s", $codigo_barras);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0 && $intento < 5) {
        return generarCodigoBarras($conn, $intento + 1);
    }
    
    return $codigo_barras;
}

// Función para calcular dígito de control (módulo 10)
function calcularDigitoControl($codigo) {
    $suma = 0;
    $longitud = strlen($codigo);
    $alternar = true;
    
    for ($i = $longitud - 1; $i >= 0; $i--) {
        $digito = intval($codigo[$i]);
        if ($alternar) {
            $digito *= 2;
            if ($digito > 9) {
                $digito = ($digito % 10) + 1;
            }
        }
        $suma += $digito;
        $alternar = !$alternar;
    }
    
    $resto = $suma % 10;
    return ($resto == 0) ? 0 : (10 - $resto);
}

// Obtener categorías
$sql_categorias = "SELECT id, nombre FROM CATEGORIA_PRODUCTO WHERE activo = 1";
$categorias = $conn->query($sql_categorias);

// Obtener proveedores activos
$sql_proveedores = "SELECT id, nombre FROM PROVEEDOR WHERE activo = 1 ORDER BY nombre ASC";
$proveedores = $conn->query($sql_proveedores);

// Generar código de barras sugerido
$codigo_sugerido = generarCodigoBarras($conn);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $codigo_barras = trim($_POST['codigo_barras']);
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
    
    // Obtener valor de maneja_stock (checkbox: si está marcado = 0, no maneja stock)
    $maneja_stock = isset($_POST['maneja_stock']) ? 0 : 1;

    // Para usuarios caja, precio_compra y id_proveedor van NULL
    if ($es_caja) {
        $precio_compra = null;
        $id_proveedor = null;
    } else {
        $precio_compra = (float)$_POST['precio_compra'];
        $id_proveedor = !empty($_POST['id_proveedor']) ? (int)$_POST['id_proveedor'] : null;
    }
    
    // Si no maneja stock, forzar stock_actual = 0 y stock_minimo = 0
    if ($maneja_stock == 0) {
        $stock_actual = 0;
        $stock_minimo = 0;
        $ubicacion = null;
        $fecha_vencimiento = null;
    }
    
    // Si no se ingresó código de barras, generar uno automático
    if (empty($codigo_barras)) {
        $codigo_barras = generarCodigoBarras($conn);
    }
    
    $upload_dir = __DIR__ . '/../assets/images/productos/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $imagen = '';
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $nombre_imagen = 'producto_' . time() . '_' . uniqid() . '.' . $extension;
        $ruta_destino = $upload_dir . $nombre_imagen;
        
        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta_destino)) {
            $imagen = 'assets/images/productos/' . $nombre_imagen;
        }
    }
    
    $sql = "INSERT INTO PRODUCTO (nombre, descripcion, codigo_barras, id_categoria, id_proveedor,
                                   precio_compra, precio_venta, stock_actual, stock_minimo, unidad_medida, 
                                   ubicacion, fecha_vencimiento, imagen, activo, maneja_stock) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssiiiddiisssii", 
        $nombre, $descripcion, $codigo_barras, $id_categoria, $id_proveedor,
        $precio_compra, $precio_venta, $stock_actual, $stock_minimo, $unidad_medida, 
        $ubicacion, $fecha_vencimiento, $imagen, $activo, $maneja_stock
    );
    
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
    <link rel="stylesheet" href="../assets/css/producto_nuevo.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Nuevo Producto</h1>
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
        
        <form method="POST" enctype="multipart/form-data" id="productoForm">
            <div class="form-group">
                <label>Nombre del producto *</label>
                <input type="text" name="nombre" required>
            </div>
            
            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" rows="4"></textarea>
            </div>

            <!-- Código de barras con generador automático -->
            <div class="form-group">
                <label>Código de barras</label>
                <div class="codigo-wrapper">
                    <input type="text" name="codigo_barras" id="codigo_barras" placeholder="Déjalo vacío para generar automáticamente">
                    <button type="button" class="btn-generar" id="btnGenerarCodigo">🎲 Generar</button>
                </div>
                <p class="info-text">
                    ⚡ Si dejas el campo vacío, se generará un código único automáticamente al guardar.
                    <br>📋 Formato: BIO + fecha (YYMMDD) + 5 dígitos aleatorios + dígito de control.
                </p>
                <div class="codigo-ejemplo" id="codigoEjemplo">
                    💡 Ejemplo de código sugerido: <strong><?php echo $codigo_sugerido; ?></strong>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Categoría *</label>
                    <div style="display: flex; gap: 10px;">
                        <select name="id_categoria" required style="flex: 1;">
                            <option value="">Seleccionar...</option>
                            <?php while($cat = $categorias->fetch_assoc()): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <a href="categorias_productos.php" target="_blank" class="btn-small" style="background: #2196f3; color: white; padding: 10px 15px; 
                        border-radius: 5px; text-decoration: none; white-space: nowrap; display: inline-flex; align-items: center; gap: 5px;">
                        📁 Gestionar
                        </a>
                    </div>
                    <small style="color:#666;">Si la categoría no existe, créala desde "Gestionar"</small>
                </div>
                <?php if (!$es_caja): ?>
                <div class="form-group">
                    <label>Proveedor</label>
                    <div style="display: flex; gap: 10px;">
                        <select name="id_proveedor" style="flex: 1;">
                            <option value="">-- Seleccionar proveedor --</option>
                            <?php while($prov = $proveedores->fetch_assoc()): ?>
                                <option value="<?php echo $prov['id']; ?>"><?php echo htmlspecialchars($prov['nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <a href="proveedor_nuevo.php" target="_blank" class="btn-small" style="background: #4caf50; color: white; padding: 10px 15px; border-radius: 5px; 
                        text-decoration: none; white-space: nowrap; display: inline-flex; align-items: center; gap: 5px;">
                        ➕ Nuevo
                        </a>
                    </div>
                    <small style="color:#666;">¿No aparece el proveedor? Regístralo con el botón "Nuevo"</small>
                </div>
                <?php endif; ?>
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
                <?php if (!$es_caja): ?>
                <div class="form-group">
                    <label>Precio compra (MXN)</label>
                    <input type="number" step="0.01" name="precio_compra">
                </div>
                <?php endif; ?>
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

            <!-------CHECKBOX DE MANEJO DE STOCK-------->
            <div class="form-group">
                <label>
                    <input type="checkbox" name="maneja_stock" id="maneja_stock" value="0" onchange="toggleStockFields()">
                    ❌ No maneja stock (es un servicio o producto sin inventario)
                </label>
                <small style="color:#666;">Marca esta opción si es un servicio (estética, baño, consulta) o un producto que no requiere control de inventario</small>
            </div>
            <!-------FIN DE CHECKBOX DE MANEJO DE STOCK-------->
            
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
                <input type="file" name="imagen" accept="image/*" id="imagenProducto">
                <small style="color:#666;">Formatos: JPG, PNG, GIF. Tamaño recomendado: 300x300px</small>
                <div id="vistaPrevia" style="margin-top: 10px; display: none;">
                    <img id="previewImg" style="max-width: 150px; max-height: 150px; border-radius: 10px; border: 1px solid #ddd;">
                </div>
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
    <script src="../assets/js/producto_nuevo.js"></script>
</body>
</html>