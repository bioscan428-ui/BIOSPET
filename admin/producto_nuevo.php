<?php
session_start();

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol
if (!in_array($_SESSION['rol'], ['super_admin', 'admin'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Función para generar código de barras único
function generarCodigoBarras($conn, $intento = 1) {
    // Formato: BIOSPET + año + mes + día + número aleatorio + dígito de control
    $prefijo = 'BIO';
    $fecha = date('ymd');
    $aleatorio = str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    $codigo_base = $prefijo . $fecha . $aleatorio;
    
    // Calcular dígito de control (módulo 10)
    $digito_control = calcularDigitoControl($codigo_base);
    $codigo_barras = $codigo_base . $digito_control;
    
    // Verificar si ya existe en la BD
    $sql_check = "SELECT id FROM PRODUCTO WHERE codigo_barras = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("s", $codigo_barras);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0 && $intento < 5) {
        // Si ya existe, intentar de nuevo (máximo 5 intentos)
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
    $digito_control = ($resto == 0) ? 0 : (10 - $resto);
    return $digito_control;
}

// Obtener categorías
$sql_categorias = "SELECT id, nombre FROM CATEGORIA_PRODUCTO WHERE activo = 1";
$categorias = $conn->query($sql_categorias);

// Generar código de barras sugerido
$codigo_sugerido = generarCodigoBarras($conn);
$error = '';

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
    
    // Si no se ingresó código de barras, generar uno automático
    if (empty($codigo_barras)) {
        $codigo_barras = generarCodigoBarras($conn);
    }
    
    // Crear directorio si no existe
    $upload_dir = __DIR__ . '/../assets/images/productos/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Manejo de imagen
    $imagen = '';
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $nombre_imagen = 'producto_' . time() . '_' . uniqid() . '.' . $extension;
        $ruta_destino = $upload_dir . $nombre_imagen;
        
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
        .admin-header { background: var(--primary); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        .admin-header a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 800px; margin: 20px auto; padding: 20px; background: white; border-radius: var(--radius-md); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: var(--radius-sm); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .btn-guardar { background: var(--primary); color: white; padding: 12px 30px; border: none; border-radius: var(--radius-sm); cursor: pointer; }
        .btn-cancelar { background: #666; color: white; padding: 12px 30px; text-decoration: none; border-radius: var(--radius-sm); display: inline-block; margin-left: 10px; }
        .btn-generar { background: #28a745; color: white; border: none; border-radius: var(--radius-sm); cursor: pointer; padding: 8px 15px; margin-left: 10px; }
        .error { color: red; margin-bottom: 15px; }
        .codigo-wrapper { display: flex; align-items: center; gap: 10px; }
        .codigo-wrapper input { flex: 1; }
        .info-text { font-size: 12px; color: #666; margin-top: 5px; }
        .codigo-ejemplo { background: #f0f0f0; padding: 10px; border-radius: 5px; margin-top: 10px; font-family: monospace; text-align: center; }
    </style>
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
                    <select name="id_categoria" required>
                        <option value="">Seleccionar...</option>
                        <?php while($cat = $categorias->fetch_assoc()): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
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
                    <input type="number" step="0.01" name="precio_compra">
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

    <script>
        // Generar código de barras vía AJAX
        document.getElementById('btnGenerarCodigo').addEventListener('click', async function() {
            const btn = this;
            const inputCodigo = document.getElementById('codigo_barras');
            
            btn.textContent = '⏳ Generando...';
            btn.disabled = true;
            
            try {
                const response = await fetch('generar_codigo_barras.php');
                const data = await response.json();
                
                if (data.success) {
                    inputCodigo.value = data.codigo;
                    // Mostrar efecto visual
                    inputCodigo.style.backgroundColor = '#d4edda';
                    setTimeout(() => {
                        inputCodigo.style.backgroundColor = '';
                    }, 1000);
                } else {
                    alert('Error al generar código: ' + data.message);
                }
            } catch (error) {
                alert('Error al conectar con el servidor');
            } finally {
                btn.textContent = '🎲 Generar';
                btn.disabled = false;
            }
        });
        
        // Vista previa de imagen
        document.getElementById('imagenProducto').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const vistaPrevia = document.getElementById('vistaPrevia');
            const previewImg = document.getElementById('previewImg');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    previewImg.src = event.target.result;
                    vistaPrevia.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                vistaPrevia.style.display = 'none';
                previewImg.src = '';
            }
        });
        
        // Validar que el código de barras no esté repetido (si se ingresa manualmente)
        const inputCodigo = document.getElementById('codigo_barras');
        inputCodigo.addEventListener('blur', async function() {
            const codigo = this.value.trim();
            if (codigo === '') return;
            
            try {
                const response = await fetch(`validar_codigo_barras.php?codigo=${encodeURIComponent(codigo)}`);
                const data = await response.json();
                
                if (data.existe) {
                    alert('⚠️ Este código de barras ya existe en otro producto. Se generará uno automático al guardar.');
                    this.style.backgroundColor = '#f8d7da';
                } else {
                    this.style.backgroundColor = '#d4edda';
                    setTimeout(() => {
                        this.style.backgroundColor = '';
                    }, 1000);
                }
            } catch (error) {
                console.error('Error al validar:', error);
            }
        });
    </script>
</body>
</html>