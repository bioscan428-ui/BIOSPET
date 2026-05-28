<?php
// admin/productos_importar_excel.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

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
        $depuracion = []; // Array para mensajes de depuración
        
        $productos_ejemplo = [
            'Croqueta Premium para Perros',
            'Juguete Pelota de Goma',
            'Correa Nylon Resistente',
            'Vacuna Triple Felina'
        ];
        
        foreach ($filas as $indice => $fila) {
            $numero_fila = $indice + 2;
            
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
            
            $nombre = isset($fila[0]) ? limpiarString($fila[0]) : '';
            $descripcion = isset($fila[1]) ? limpiarString($fila[1]) : '';
            $codigo_barras = isset($fila[2]) ? limpiarString($fila[2]) : '';
            $id_categoria = isset($fila[3]) ? limpiarString($fila[3]) : '';
            $id_proveedor = isset($fila[4]) ? limpiarString($fila[4]) : '';
            $precio_compra = isset($fila[5]) ? obtenerFloat($fila[5]) : 0;
            $precio_venta = isset($fila[6]) ? obtenerFloat($fila[6]) : 0;
            $stock_actual = isset($fila[7]) ? obtenerInt($fila[7]) : 0;
            $stock_minimo = isset($fila[8]) ? obtenerInt($fila[8]) : 5;
            $unidad_medida = isset($fila[9]) ? limpiarString($fila[9]) : 'pieza';
            $ubicacion = isset($fila[10]) ? limpiarString($fila[10]) : '';
            $fecha_vencimiento = isset($fila[11]) && !empty($fila[11]) ? $fila[11] : null;
            
            // ========== DEPURACIÓN DEL PROVEEDOR ==========
            $depuracion[] = "--- FILA $numero_fila: $nombre ---";
            $depuracion[] = "Valor crudo de id_proveedor: '" . var_export($id_proveedor, true) . "'";
            $depuracion[] = "¿id_proveedor está vacío? " . (empty($id_proveedor) ? 'SÍ' : 'NO');
            $depuracion[] = "¿id_proveedor es numérico? " . (is_numeric($id_proveedor) ? 'SÍ : ' . gettype($id_proveedor) : 'NO');
            // ==============================================
            
            if (in_array($nombre, $productos_ejemplo)) {
                $depuracion[] = "➡️ Saltando producto de ejemplo: $nombre";
                continue;
            }
            
            if (empty($nombre)) {
                $depuracion[] = "➡️ Saltando fila sin nombre";
                continue;
            }
            
            if (empty($id_categoria) || !is_numeric($id_categoria)) {
                $errores[] = "Fila $numero_fila: Categoría inválida para '$nombre'";
                continue;
            }
            
            if (empty($precio_venta) || $precio_venta <= 0) {
                $errores[] = "Fila $numero_fila: Precio venta inválido para '$nombre'";
                continue;
            }
            
            $check_cat = $conn->prepare("SELECT id FROM CATEGORIA_PRODUCTO WHERE id = ? AND activo = 1");
            $check_cat->bind_param("i", $id_categoria);
            $check_cat->execute();
            $cat_result = $check_cat->get_result();
            
            if ($cat_result->num_rows === 0) {
                $errores[] = "Fila $numero_fila: Categoría ID $id_categoria no existe para '$nombre'";
                continue;
            }
            
            // ========== VERIFICACIÓN DE PROVEEDOR CON DEPURACIÓN ==========
            $id_proveedor_valor = null;
            
            $depuracion[] = "--- Validando proveedor para: $nombre ---";
            
            if (!empty($id_proveedor) && is_numeric($id_proveedor)) {
                $depuracion[] = "✅ Proveedor tiene valor numérico: $id_proveedor";
                
                $check_prov = $conn->prepare("SELECT id FROM PROVEEDOR WHERE id = ? AND activo = 1");
                $check_prov->bind_param("i", $id_proveedor);
                $check_prov->execute();
                $prov_result = $check_prov->get_result();
                
                $depuracion[] = "Resultado de búsqueda en BD: " . $prov_result->num_rows . " fila(s) encontrada(s)";
                
                if ($prov_result->num_rows > 0) {
                    $id_proveedor_valor = (int)$id_proveedor;
                    $depuracion[] = "✅✅ PROVEEDOR ASIGNADO CORRECTAMENTE: ID $id_proveedor_valor";
                } else {
                    $depuracion[] = "❌ ERROR: Proveedor ID $id_proveedor NO existe en la base de datos";
                    $errores[] = "Fila $numero_fila: Proveedor ID $id_proveedor no existe para '$nombre'";
                    continue;
                }
            } else {
                $depuracion[] = "⚠️ ADVERTENCIA: No se cumplió condición para asignar proveedor";
                $depuracion[] = "   - empty(id_proveedor): " . (empty($id_proveedor) ? 'true' : 'false');
                $depuracion[] = "   - is_numeric(id_proveedor): " . (is_numeric($id_proveedor) ? 'true' : 'false');
                $depuracion[] = "   - Valor de id_proveedor: '" . $id_proveedor . "'";
            }
            
            $depuracion[] = "Valor final de \$id_proveedor_valor: " . ($id_proveedor_valor ?? 'NULL');
            // ==============================================================
            
            $existe = false;
            if (!empty($codigo_barras)) {
                $check = $conn->prepare("SELECT id FROM PRODUCTO WHERE codigo_barras = ?");
                $check->bind_param("s", $codigo_barras);
                $check->execute();
                $existe = $check->get_result()->num_rows > 0;
            }
            
            if ($existe) {
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
                    $depuracion[] = "✅ Producto ACTUALIZADO con proveedor ID: " . ($id_proveedor_valor ?? 'NULL');
                } else {
                    $errores[] = "Fila $numero_fila: Error actualizando '$nombre': " . $conn->error;
                }
                $stmt->close();
            } else {
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
                    $depuracion[] = "✅✅ Producto INSERTADO con proveedor ID: " . ($id_proveedor_valor ?? 'NULL');
                } else {
                    $errores[] = "Fila $numero_fila: Error insertando '$nombre': " . $conn->error;
                }
                $stmt->close();
            }
            
            $depuracion[] = "--- FIN PROCESO FILA $numero_fila ---";
        }
        
        // Guardar mensajes de depuración en sesión para mostrarlos
        if (count($depuracion) > 0) {
            $_SESSION['importacion_depuracion'] = $depuracion;
        }
        
        $mensaje = "📊 Importación: $importados nuevos, $actualizados actualizados";
        if (count($errores) > 0) {
            $mensaje .= " ⚠️ " . count($errores) . " errores";
            $_SESSION['importacion_detalle_errores'] = $errores;
            $primeros_errores = array_slice($errores, 0, 5);
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