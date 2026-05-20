<?php
// admin/productos_importar_csv.php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_csv'])) {
    $archivo = $_FILES['archivo_csv'];
    
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['importacion_mensaje'] = 'Error al subir el archivo';
        $_SESSION['importacion_tipo'] = 'error';
        header('Location: productos.php');
        exit;
    }
    
    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    if (strtolower($extension) !== 'csv') {
        $_SESSION['importacion_mensaje'] = 'Formato no válido. Use .csv';
        $_SESSION['importacion_tipo'] = 'error';
        header('Location: productos.php');
        exit;
    }
    
    $handle = fopen($archivo['tmp_name'], 'r');
    if (!$handle) {
        $_SESSION['importacion_mensaje'] = 'Error al leer el archivo';
        $_SESSION['importacion_tipo'] = 'error';
        header('Location: productos.php');
        exit;
    }
    
    // Saltar encabezados
    fgetcsv($handle);
    
    $importados = 0;
    $actualizados = 0;
    $errores = [];
    
    while (($fila = fgetcsv($handle)) !== false) {
        $nombre = trim($fila[0] ?? '');
        $descripcion = trim($fila[1] ?? '');
        $codigo_barras = trim($fila[2] ?? '');
        $id_categoria = trim($fila[3] ?? '');
        $precio_compra = (float)($fila[4] ?? 0);
        $precio_venta = (float)($fila[5] ?? 0);
        $stock_actual = (int)($fila[6] ?? 0);
        $stock_minimo = (int)($fila[7] ?? 5);
        $unidad_medida = trim($fila[8] ?? 'pieza');
        $ubicacion = trim($fila[9] ?? '');
        $fecha_vencimiento = !empty($fila[10]) ? $fila[10] : null;
        
        if (empty($nombre) || empty($id_categoria) || empty($precio_venta)) {
            $errores[] = "Fila con datos incompletos - omitida";
            continue;
        }
        
        // Buscar si existe
        $existe = false;
        if (!empty($codigo_barras)) {
            $check = $conn->prepare("SELECT id FROM PRODUCTO WHERE codigo_barras = ?");
            $check->bind_param("s", $codigo_barras);
            $check->execute();
            $existe = $check->get_result()->num_rows > 0;
        }
        
        if ($existe) {
            $sql = "UPDATE PRODUCTO SET nombre=?, descripcion=?, id_categoria=?, precio_compra=?, 
                    precio_venta=?, stock_actual=?, stock_minimo=?, unidad_medida=?, 
                    ubicacion=?, fecha_vencimiento=?, activo=1 WHERE codigo_barras=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiddiissss", $nombre, $descripcion, $id_categoria, $precio_compra,
                            $precio_venta, $stock_actual, $stock_minimo, $unidad_medida,
                            $ubicacion, $fecha_vencimiento, $codigo_barras);
            if ($stmt->execute()) $actualizados++;
            else $errores[] = "Error actualizando '$nombre'";
        } else {
            $sql = "INSERT INTO PRODUCTO (nombre, descripcion, codigo_barras, id_categoria, 
                    precio_compra, precio_venta, stock_actual, stock_minimo, unidad_medida, 
                    ubicacion, fecha_vencimiento, activo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssiddiisss", $nombre, $descripcion, $codigo_barras, $id_categoria,
                            $precio_compra, $precio_venta, $stock_actual, $stock_minimo,
                            $unidad_medida, $ubicacion, $fecha_vencimiento);
            if ($stmt->execute()) $importados++;
            else $errores[] = "Error insertando '$nombre'";
        }
    }
    
    fclose($handle);
    
    $mensaje = "✅ Importación: $importados nuevos, $actualizados actualizados";
    if (count($errores) > 0) $mensaje .= " ⚠️ " . count($errores) . " errores";
    $_SESSION['importacion_mensaje'] = $mensaje;
    $_SESSION['importacion_tipo'] = 'exito';
}

header('Location: productos.php');
exit;
?>