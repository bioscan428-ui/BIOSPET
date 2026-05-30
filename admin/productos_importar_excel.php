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
require_once $_SERVER['DOCUMENT_ROOT'] . '/biospet/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function limpiarString($valor) {
    if ($valor === null || $valor === '') {
        return '';
    }
    return trim((string)$valor);
}

function obtenerFloat($valor) {
    if ($valor === null || $valor === '') {
        return 0;
    }
    if (is_string($valor)) {
        // Eliminar signos de moneda
        $valor = str_replace(['$', '€', '£', '¥', 'MXN', ' '], '', $valor);
        
        // Detectar si la coma es separador decimal o de miles
        // Si tiene una coma seguida de 1 o 2 dígitos al FINAL, es decimal (ej: 3,50)
        if (preg_match('/,\d{1,2}$/', $valor)) {
            // Es decimal: reemplazar coma por punto
            $valor = str_replace(',', '.', $valor);
        } else {
            // Es separador de miles: eliminar todas las comas
            $valor = str_replace(',', '', $valor);
        }
    }
    return (float)$valor;
}

function obtenerInt($valor) {
    if ($valor === null || $valor === '') {
        return 0;
    }
    return (int)$valor;
}

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
        
        array_shift($filas);
        
        $importados = 0;
        $actualizados = 0;
        $errores = [];
        
        foreach ($filas as $indice => $fila) {
            $numero_fila = $indice + 2;
            
            // Verificar fila vacía
            $fila_vacia = true;
            for ($i = 0; $i < count($fila); $i++) {
                $celda = isset($fila[$i]) ? limpiarString($fila[$i]) : '';
                if (!empty($celda)) {
                    $fila_vacia = false;
                    break;
                }
            }
            
            if ($fila_vacia) {
                continue;
            }
            
            // MAPEO DE COLUMNAS
            $codigo_barras = isset($fila[0]) ? limpiarString($fila[0]) : '';
            $nombre = isset($fila[1]) ? limpiarString($fila[1]) : '';
            $precio_compra = isset($fila[2]) ? obtenerFloat($fila[2]) : 0;
            $precio_venta = isset($fila[3]) ? obtenerFloat($fila[3]) : 0;
            $stock_actual = isset($fila[5]) ? obtenerInt($fila[5]) : 0;
            $stock_minimo = isset($fila[6]) ? obtenerInt($fila[6]) : 5;
            $departamento = isset($fila[7]) ? limpiarString($fila[7]) : '';
            
            if (empty($nombre)) {
                $errores[] = "Fila $numero_fila: Nombre vacío";
                continue;
            }
            
            if ($precio_venta <= 0) {
                $valor_original = isset($fila[3]) ? $fila[3] : 'null';
                $errores[] = "Fila $numero_fila: Precio venta inválido para '$nombre'. Valor original: '$valor_original'";
                continue;
            }
            
            if (empty($departamento)) {
                $errores[] = "Fila $numero_fila: Departamento vacío para '$nombre'";
                continue;
            }
            
            // Buscar o crear categoría
            $id_categoria = null;
            $sql_cat = "SELECT id FROM CATEGORIA_PRODUCTO WHERE nombre = ? AND activo = 1";
            $stmt_cat = $conn->prepare($sql_cat);
            $stmt_cat->bind_param("s", $departamento);
            $stmt_cat->execute();
            $cat_result = $stmt_cat->get_result();
            
            if ($cat_result->num_rows > 0) {
                $id_categoria = $cat_result->fetch_assoc()['id'];
            } else {
                $sql_insert_cat = "INSERT INTO CATEGORIA_PRODUCTO (nombre, activo) VALUES (?, 1)";
                $stmt_insert_cat = $conn->prepare($sql_insert_cat);
                $stmt_insert_cat->bind_param("s", $departamento);
                if ($stmt_insert_cat->execute()) {
                    $id_categoria = $conn->insert_id;
                } else {
                    $errores[] = "Fila $numero_fila: Error al crear categoría '$departamento'";
                    continue;
                }
            }
            
            // Verificar si el producto existe por código de barras
            $existe = false;
            if (!empty($codigo_barras)) {
                $check = $conn->prepare("SELECT id FROM PRODUCTO WHERE codigo_barras = ?");
                $check->bind_param("s", $codigo_barras);
                $check->execute();
                $existe = $check->get_result()->num_rows > 0;
            }
            
            $descripcion = $nombre;
            $unidad_medida = 'pieza';
            $ubicacion = '';
            $activo = 1;
            $maneja_stock = 1;
            
            if ($existe) {
                // ========== UPDATE - 11 parámetros (11 ?) ==========
                $sql = "UPDATE PRODUCTO SET 
                            nombre = ?, 
                            descripcion = ?, 
                            codigo_barras = ?,
                            id_categoria = ?, 
                            precio_compra = ?, 
                            precio_venta = ?, 
                            stock_actual = ?,
                            stock_minimo = ?, 
                            unidad_medida = ?, 
                            ubicacion = ?,
                            activo = 1
                        WHERE codigo_barras = ?";
                
                $stmt = $conn->prepare($sql);
                // 11 parámetros + 1 (WHERE) = 12? NO: Los SET son 11, el WHERE es 1, TOTAL 12
                $stmt->bind_param("sssiiddissss", 
                    $nombre,           // 1
                    $descripcion,      // 2
                    $codigo_barras,    // 3
                    $id_categoria,     // 4
                    $precio_compra,    // 5
                    $precio_venta,     // 6
                    $stock_actual,     // 7
                    $stock_minimo,     // 8
                    $unidad_medida,    // 9
                    $ubicacion,        // 10
                    $codigo_barras     // 11 - WHERE
                );
                
                if ($stmt->execute()) {
                    $actualizados++;
                } else {
                    $errores[] = "Fila $numero_fila: Error actualizando '$nombre': " . $stmt->error;
                }
                $stmt->close();
            } else {
                // ========== INSERT - 10 parámetros (10 ?) ==========
                $sql = "INSERT INTO PRODUCTO (
                            nombre, descripcion, codigo_barras, id_categoria,
                            precio_compra, precio_venta, stock_actual, stock_minimo,
                            unidad_medida, ubicacion, activo, maneja_stock
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($sql);
                // 12 parámetros en total
                $stmt->bind_param("sssiddiisssi", 
                    $nombre,           // 1
                    $descripcion,      // 2
                    $codigo_barras,    // 3
                    $id_categoria,     // 4
                    $precio_compra,    // 5
                    $precio_venta,     // 6
                    $stock_actual,     // 7
                    $stock_minimo,     // 8
                    $unidad_medida,    // 9
                    $ubicacion,        // 10
                    $activo,           // 11
                    $maneja_stock      // 12
                );
                
                if ($stmt->execute()) {
                    $importados++;
                } else {
                    $errores[] = "Fila $numero_fila: Error insertando '$nombre': " . $stmt->error;
                }
                $stmt->close();
            }
        }
        
        $mensaje = "📊 Importación: $importados nuevos, $actualizados actualizados";
        if (count($errores) > 0) {
            $mensaje .= " ⚠️ " . count($errores) . " errores";
            $_SESSION['importacion_detalle_errores'] = $errores;
            $primeros_errores = array_slice($errores, 0, 10);
            $_SESSION['importacion_errores_muestra'] = $primeros_errores;
        } else {
            $mensaje .= " ✅ Completado con éxito!";
        }
        
        $_SESSION['importacion_mensaje'] = $mensaje;
        $_SESSION['importacion_tipo'] = (count($errores) > 0) ? 'error' : 'exito';
        
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