<?php
// admin/productos_importar.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!in_array($_SESSION['rol'], ['super_admin', 'admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Requerir PhpSpreadsheet


use PhpOffice\PhpSpreadsheet\IOFactory;

$mensaje = '';
$tipo = 'exito';
$importados = 0;
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_excel'])) {
    $archivo = $_FILES['archivo_excel'];
    
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['importacion_mensaje'] = 'Error al subir el archivo';
        $_SESSION['importacion_tipo'] = 'error';
        header('Location: productos.php');
        exit;
    }
    
    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    if (!in_array(strtolower($extension), ['xlsx', 'xls'])) {
        $_SESSION['importacion_mensaje'] = 'Formato no válido. Use .xlsx o .xls';
        $_SESSION['importacion_tipo'] = 'error';
        header('Location: productos.php');
        exit;
    }
    
    try {
        $spreadsheet = IOFactory::load($archivo['tmp_name']);
        $hoja = $spreadsheet->getActiveSheet();
        $filas = $hoja->toArray();
        
        // Eliminar encabezados (primera fila)
        $encabezados = array_shift($filas);
        
        foreach ($filas as $fila) {
            // Validar datos mínimos
            $nombre = trim($fila[0] ?? '');
            $id_categoria = trim($fila[3] ?? '');
            $precio_venta = trim($fila[5] ?? '');
            
            if (empty($nombre)) {
                $errores[] = "Fila con nombre vacío - omitida";
                continue;
            }
            
            if (empty($id_categoria) || !is_numeric($id_categoria)) {
                $errores[] = "Producto '$nombre' - categoría inválida";
                continue;
            }
            
            if (empty($precio_venta) || !is_numeric($precio_venta)) {
                $errores[] = "Producto '$nombre' - precio venta inválido";
                continue;
            }
            
            // Verificar si la categoría existe
            $check_cat = $conn->prepare("SELECT id FROM CATEGORIA_PRODUCTO WHERE id = ? AND activo = 1");
            $check_cat->bind_param("i", $id_categoria);
            $check_cat->execute();
            $cat_result = $check_cat->get_result();
            
            if ($cat_result->num_rows === 0) {
                $errores[] = "Producto '$nombre' - categoría ID $id_categoria no existe";
                continue;
            }
            
            // Verificar si el producto ya existe por código de barras
            $codigo_barras = trim($fila[2] ?? '');
            $producto_existente = null;
            
            if (!empty($codigo_barras)) {
                $check = $conn->prepare("SELECT id FROM PRODUCTO WHERE codigo_barras = ?");
                $check->bind_param("s", $codigo_barras);
                $check->execute();
                $result_check = $check->get_result();
                if ($result_check->num_rows > 0) {
                    $producto_existente = $result_check->fetch_assoc();
                }
            }
            
            $descripcion = trim($fila[1] ?? '');
            $precio_compra = !empty($fila[4]) ? (float)$fila[4] : 0;
            $precio_venta = (float)$precio_venta;
            $stock_actual = !empty($fila[6]) ? (int)$fila[6] : 0;
            $stock_minimo = !empty($fila[7]) ? (int)$fila[7] : 5;
            $unidad_medida = !empty($fila[8]) ? $fila[8] : 'pieza';
            $ubicacion = trim($fila[9] ?? '');
            $fecha_vencimiento = !empty($fila[10]) ? $fila[10] : null;
            
            if ($producto_existente) {
                // Actualizar producto existente
                $sql = "UPDATE PRODUCTO SET 
                            nombre = ?, descripcion = ?, id_categoria = ?, 
                            precio_compra = ?, precio_venta = ?, stock_actual = ?,
                            stock_minimo = ?, unidad_medida = ?, ubicacion = ?,
                            fecha_vencimiento = ?, activo = 1
                        WHERE codigo_barras = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssiddiissss", 
                    $nombre, $descripcion, $id_categoria,
                    $precio_compra, $precio_venta, $stock_actual,
                    $stock_minimo, $unidad_medida, $ubicacion,
                    $fecha_vencimiento, $codigo_barras
                );
                
                if ($stmt->execute()) {
                    $importados++;
                } else {
                    $errores[] = "Error al actualizar '$nombre': " . $conn->error;
                }
            } else {
                // Insertar nuevo producto
                $sql = "INSERT INTO PRODUCTO (nombre, descripcion, codigo_barras, id_categoria, 
                                              precio_compra, precio_venta, stock_actual, stock_minimo, 
                                              unidad_medida, ubicacion, fecha_vencimiento, activo) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssiddiisss", 
                    $nombre, $descripcion, $codigo_barras, $id_categoria,
                    $precio_compra, $precio_venta, $stock_actual, $stock_minimo,
                    $unidad_medida, $ubicacion, $fecha_vencimiento
                );
                
                if ($stmt->execute()) {
                    $importados++;
                } else {
                    $errores[] = "Error al insertar '$nombre': " . $conn->error;
                }
            }
        }
        
        // Generar mensaje final
        $mensaje = "✅ Importación completada: $importados productos procesados.";
        if (count($errores) > 0) {
            $mensaje .= " ⚠️ " . count($errores) . " errores encontrados.";
            // Guardar errores en sesión para mostrar
            $_SESSION['importacion_detalle_errores'] = $errores;
        }
        
        $_SESSION['importacion_mensaje'] = $mensaje;
        $_SESSION['importacion_tipo'] = 'exito';
        
    } catch (Exception $e) {
        $_SESSION['importacion_mensaje'] = 'Error al procesar el archivo: ' . $e->getMessage();
        $_SESSION['importacion_tipo'] = 'error';
    }
} else {
    $_SESSION['importacion_mensaje'] = 'No se recibió ningún archivo';
    $_SESSION['importacion_tipo'] = 'error';
}

header('Location: productos.php');
exit;
?>