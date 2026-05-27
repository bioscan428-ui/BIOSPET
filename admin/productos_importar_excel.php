<?php
// admin/productos_importar_excel.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!in_array($_SESSION['rol'], ['super_admin', 'admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Ruta CORRECTA al autoloader
require_once $_SERVER['DOCUMENT_ROOT'] . '/biospet/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

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
        
        // Eliminar encabezados
        array_shift($filas);
        
        $importados = 0;
        $actualizados = 0;
        $errores = [];
        
        foreach ($filas as $indice => $fila) {
            // Mapeo de columnas (12 columnas totales)
            $nombre = trim($fila[0] ?? '');
            $descripcion = trim($fila[1] ?? '');
            $codigo_barras = trim($fila[2] ?? '');
            $id_categoria = trim($fila[3] ?? '');
            $id_proveedor = trim($fila[4] ?? '');      // NUEVA COLUMNA E
            $precio_compra = !empty($fila[5]) ? (float)$fila[5] : 0;
            $precio_venta = !empty($fila[6]) ? (float)$fila[6] : 0;
            $stock_actual = !empty($fila[7]) ? (int)$fila[7] : 0;
            $stock_minimo = !empty($fila[8]) ? (int)$fila[8] : 5;
            $unidad_medida = trim($fila[9] ?? 'pieza');
            $ubicacion = trim($fila[10] ?? '');
            $fecha_vencimiento = !empty($fila[11]) ? $fila[11] : null;
            
            // Validar campos obligatorios
            if (empty($nombre)) {
                $errores[] = "Fila " . ($indice + 2) . ": Nombre vacío";
                continue;
            }
            
            if (empty($id_categoria) || !is_numeric($id_categoria)) {
                $errores[] = "Fila " . ($indice + 2) . ": Categoría inválida";
                continue;
            }
            
            if (empty($precio_venta) || $precio_venta <= 0) {
                $errores[] = "Fila " . ($indice + 2) . ": Precio venta inválido";
                continue;
            }
            
            // Verificar categoría
            $check_cat = $conn->prepare("SELECT id FROM CATEGORIA_PRODUCTO WHERE id = ? AND activo = 1");
            $check_cat->bind_param("i", $id_categoria);
            $check_cat->execute();
            $cat_result = $check_cat->get_result();
            
            if ($cat_result->num_rows === 0) {
                $errores[] = "Categoría ID $id_categoria no existe";
                continue;
            }
            
            // Verificar proveedor (si se proporcionó)
            $id_proveedor_valor = null;
            if (!empty($id_proveedor) && is_numeric($id_proveedor)) {
                $check_prov = $conn->prepare("SELECT id FROM PROVEEDOR WHERE id = ? AND activo = 1");
                $check_prov->bind_param("i", $id_proveedor);
                $check_prov->execute();
                $prov_result = $check_prov->get_result();
                
                if ($prov_result->num_rows > 0) {
                    $id_proveedor_valor = (int)$id_proveedor;
                } else {
                    $errores[] = "Proveedor ID $id_proveedor no existe para el producto '$nombre'";
                    continue;
                }
            }
            
            // Verificar si el producto ya existe (por código de barras)
            $existe = false;
            if (!empty($codigo_barras)) {
                $check = $conn->prepare("SELECT id FROM PRODUCTO WHERE codigo_barras = ?");
                $check->bind_param("s", $codigo_barras);
                $check->execute();
                $existe = $check->get_result()->num_rows > 0;
            }
            
            if ($existe) {
                // Actualizar producto existente (incluyendo proveedor)
                $sql = "UPDATE PRODUCTO SET 
                            nombre = ?, 
                            descripcion = ?, 
                            id_categoria = ?, 
                            id_proveedor = ?,
                            precio_compra = ?, 
                            precio_venta = ?, 
                            stock_actual = ?,
                            stock_minimo = ?, 
                            unidad_medida = ?, 
                            ubicacion = ?,
                            fecha_vencimiento = ?, 
                            activo = 1
                        WHERE codigo_barras = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssiiiddiissss", 
                    $nombre, 
                    $descripcion, 
                    $id_categoria, 
                    $id_proveedor_valor,
                    $precio_compra, 
                    $precio_venta, 
                    $stock_actual,
                    $stock_minimo, 
                    $unidad_medida, 
                    $ubicacion,
                    $fecha_vencimiento, 
                    $codigo_barras
                );
                
                if ($stmt->execute()) {
                    $actualizados++;
                } else {
                    $errores[] = "Error actualizando '$nombre': " . $conn->error;
                }
                $stmt->close();
            } else {
                // Insertar nuevo producto (incluyendo proveedor)
                $sql = "INSERT INTO PRODUCTO (
                            nombre, 
                            descripcion, 
                            codigo_barras, 
                            id_categoria,
                            id_proveedor, 
                            precio_compra, 
                            precio_venta, 
                            stock_actual, 
                            stock_minimo, 
                            unidad_medida, 
                            ubicacion, 
                            fecha_vencimiento, 
                            activo
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssiiddiisss", 
                    $nombre, 
                    $descripcion, 
                    $codigo_barras, 
                    $id_categoria,
                    $id_proveedor_valor,
                    $precio_compra, 
                    $precio_venta, 
                    $stock_actual, 
                    $stock_minimo,
                    $unidad_medida, 
                    $ubicacion, 
                    $fecha_vencimiento
                );
                
                if ($stmt->execute()) {
                    $importados++;
                } else {
                    $errores[] = "Error insertando '$nombre': " . $conn->error;
                }
                $stmt->close();
            }
        }
        
        $mensaje = "✅ Importación: $importados nuevos, $actualizados actualizados";
        if (count($errores) > 0) {
            $mensaje .= " ⚠️ " . count($errores) . " errores";
            $_SESSION['importacion_detalle_errores'] = $errores;
        }
        
        $_SESSION['importacion_mensaje'] = $mensaje;
        $_SESSION['importacion_tipo'] = 'exito';
        
    } catch (Exception $e) {
        $_SESSION['importacion_mensaje'] = 'Error: ' . $e->getMessage();
        $_SESSION['importacion_tipo'] = 'error';
    }
} else {
    $_SESSION['importacion_mensaje'] = 'No se recibió ningún archivo';
    $_SESSION['importacion_tipo'] = 'error';
}

header('Location: productos.php');
exit;
?>