<?php
session_start();
header('Content-Type: application/json');

// ============================================
// DIAGNÓSTICO: Crear archivo de log
// ============================================
$log_file = __DIR__ . '/debug_venta.log';

function escribirLog($mensaje, $datos = null) {
    global $log_file;
    $fecha = date('Y-m-d H:i:s');
    $log = "[$fecha] $mensaje";
    if ($datos !== null) {
        $log .= " - " . json_encode($datos, JSON_PRETTY_PRINT);
    }
    $log .= PHP_EOL;
    file_put_contents($log_file, $log, FILE_APPEND);
}

escribirLog("=== INICIO DE PROCESAMIENTO DE VENTA ===");

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    escribirLog("ERROR: No autorizado - sin sesión");
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar rol
if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'recepcionista'])) {
    escribirLog("ERROR: Permiso denegado - rol: " . $_SESSION['rol']);
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para realizar ventas']);
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener datos del POST
$raw_input = file_get_contents('php://input');
escribirLog("Datos RAW recibidos", $raw_input);

$data = json_decode($raw_input, true);

if (!$data) {
    escribirLog("ERROR: Datos inválidos - no es JSON válido");
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

escribirLog("Datos decodificados", $data);

$id_cliente = $data['id_cliente'] ?? 0;
$metodo_pago = $data['metodo_pago'] ?? '';
$productos = $data['productos'] ?? [];
$total = $data['total'] ?? 0;
$recibido = $data['recibido'] ?? 0;  // NUEVO: Monto recibido en efectivo
$empleado_id = $_SESSION['empleado_id'] ?? null;

// ============================================
// VALIDACIÓN DE VUELTO PARA PAGO EN EFECTIVO
// ============================================
$vuelto = 0;

if ($metodo_pago === 'efectivo') {
    if ($recibido <= 0) {
        escribirLog("ERROR: Pago en efectivo sin especificar monto recibido");
        echo json_encode([
            'success' => false, 
            'message' => 'Para pagos en efectivo, especifique el monto recibido'
        ]);
        exit;
    }
    
    if ($recibido < $total) {
        escribirLog("ERROR: Monto insuficiente", [
            'total' => $total,
            'recibido' => $recibido,
            'faltante' => $total - $recibido
        ]);
        echo json_encode([
            'success' => false, 
            'message' => "Monto insuficiente. Total: $$total, Recibido: $$recibido, Faltante: $" . ($total - $recibido)
        ]);
        exit;
    }
    
    $vuelto = $recibido - $total;
    escribirLog("Vuelto calculado", [
        'total' => $total,
        'recibido' => $recibido,
        'vuelto' => $vuelto
    ]);
}

// ============================================
// DIAGNÓSTICO: Verificar productos duplicados ANTES de procesar
// ============================================
$conteo_original = count($productos);
$ids_originales = array_column($productos, 'id');
$ids_unicos = array_unique($ids_originales);
$duplicados = $conteo_original - count($ids_unicos);

if ($duplicados > 0) {
    escribirLog("⚠️ ALERTA: Se detectaron $duplicados productos duplicados en el carrito", [
        'total_productos' => $conteo_original,
        'ids_originales' => $ids_originales,
        'ids_unicos' => $ids_unicos
    ]);
} else {
    escribirLog("✅ No se detectaron productos duplicados", [
        'total_productos' => $conteo_original,
        'productos' => $productos
    ]);
}

// Agrupar productos por ID para evitar duplicados
$productos_agrupados = [];
foreach ($productos as $prod) {
    $id = $prod['id'];
    if (isset($productos_agrupados[$id])) {
        $productos_agrupados[$id]['cantidad'] += $prod['cantidad'];
        escribirLog("🔄 Producto duplicado detectado - sumando cantidades", [
            'id' => $id,
            'cantidad_original' => $prod['cantidad'],
            'nueva_cantidad' => $productos_agrupados[$id]['cantidad']
        ]);
    } else {
        $productos_agrupados[$id] = $prod;
    }
}

$total_agrupado = count($productos_agrupados);
if ($conteo_original != $total_agrupado) {
    escribirLog("✅ Productos agrupados correctamente", [
        'originales' => $conteo_original,
        'agrupados' => $total_agrupado,
        'productos_agrupados' => $productos_agrupados
    ]);
}

// Usar productos agrupados para el resto del proceso
$productos = array_values($productos_agrupados);

// Validaciones
if (!$id_cliente) {
    escribirLog("ERROR: Cliente no seleccionado");
    echo json_encode(['success' => false, 'message' => 'Seleccione un cliente']);
    exit;
}

if (!$metodo_pago) {
    escribirLog("ERROR: Método de pago no seleccionado");
    echo json_encode(['success' => false, 'message' => 'Seleccione un método de pago']);
    exit;
}

if (empty($productos)) {
    escribirLog("ERROR: Carrito vacío");
    echo json_encode(['success' => false, 'message' => 'Agregue productos al carrito']);
    exit;
}

escribirLog("Validaciones superadas", [
    'id_cliente' => $id_cliente,
    'metodo_pago' => $metodo_pago,
    'total_productos' => count($productos),
    'empleado_id' => $empleado_id,
    'total' => $total,
    'recibido' => $recibido,
    'vuelto' => $vuelto
]);

// Iniciar transacción
$conn->begin_transaction();

try {
    // 1. Verificar stock de todos los productos
    escribirLog("=== VERIFICANDO STOCK ===");
    foreach ($productos as $prod) {
        $sql_stock = "SELECT stock_actual, nombre FROM PRODUCTO WHERE id = ?";
        $stmt_stock = $conn->prepare($sql_stock);
        $stmt_stock->bind_param("i", $prod['id']);
        $stmt_stock->execute();
        $result_stock = $stmt_stock->get_result();
        $producto = $result_stock->fetch_assoc();
        
        if (!$producto) {
            throw new Exception("Producto no encontrado (ID: {$prod['id']})");
        }
        
        escribirLog("Stock verificado", [
            'id' => $prod['id'],
            'nombre' => $producto['nombre'],
            'stock_actual' => $producto['stock_actual'],
            'solicitado' => $prod['cantidad']
        ]);
        
        if ($producto['stock_actual'] < $prod['cantidad']) {
            throw new Exception("Stock insuficiente para '{$producto['nombre']}'. Disponible: {$producto['stock_actual']}");
        }
    }
    
    // 2. Crear VENTA
    escribirLog("=== CREANDO VENTA ===");
    $subtotal = $total;
    $iva = 0;
    
    $sql_venta = "INSERT INTO VENTA (id_cliente, id_empleado, subtotal, iva, total, metodo_pago, estado, fecha_venta) 
                  VALUES (?, ?, ?, ?, ?, ?, 'completada', NOW())";
    $stmt_venta = $conn->prepare($sql_venta);
    $stmt_venta->bind_param("iiddds", $id_cliente, $empleado_id, $subtotal, $iva, $total, $metodo_pago);
    $stmt_venta->execute();
    $id_venta = $conn->insert_id;
    
    if (!$id_venta) {
        throw new Exception("Error al crear la venta");
    }
    
    escribirLog("Venta creada", ['id_venta' => $id_venta]);
    
    // 3. Crear DETALLE_VENTA y actualizar stock
    escribirLog("=== CREANDO DETALLES DE VENTA ===");
    $contador_detalles = 0;
    
    foreach ($productos as $prod) {
        // Obtener precio actual del producto
        $sql_precio = "SELECT precio_venta, nombre FROM PRODUCTO WHERE id = ?";
        $stmt_precio = $conn->prepare($sql_precio);
        $stmt_precio->bind_param("i", $prod['id']);
        $stmt_precio->execute();
        $producto = $stmt_precio->get_result()->fetch_assoc();
        
        $precio_unitario = $producto['precio_venta'];
        $subtotal_prod = $precio_unitario * $prod['cantidad'];
        
        escribirLog("Insertando detalle", [
            'id_venta' => $id_venta,
            'id_producto' => $prod['id'],
            'nombre' => $producto['nombre'],
            'cantidad' => $prod['cantidad'],
            'precio_unitario' => $precio_unitario,
            'subtotal' => $subtotal_prod
        ]);
        
        // Insertar detalle
        $sql_detalle = "INSERT INTO DETALLE_VENTA (id_venta, id_producto, cantidad, precio_unitario, subtotal) 
                        VALUES (?, ?, ?, ?, ?)";
        $stmt_detalle = $conn->prepare($sql_detalle);
        $stmt_detalle->bind_param("iiidd", $id_venta, $prod['id'], $prod['cantidad'], $precio_unitario, $subtotal_prod);
        $stmt_detalle->execute();
        $contador_detalles++;
        
        escribirLog("Detalle insertado correctamente (stock será actualizado por trigger)");
    }
    
    escribirLog("Total detalles insertados", ['cantidad' => $contador_detalles]);
    
    // 4. Registrar PAGO (para venta de mostrador)
    escribirLog("=== REGISTRANDO PAGO ===");
    $sql_pago = "INSERT INTO PAGO (id_venta, monto, metodo_pago, fecha_pago) 
                 VALUES (?, ?, ?, NOW())";
    $stmt_pago = $conn->prepare($sql_pago);
    $stmt_pago->bind_param("ids", $id_venta, $total, $metodo_pago);
    $stmt_pago->execute();
    
    escribirLog("Pago registrado", [
        'id_venta' => $id_venta,
        'monto' => $total,
        'metodo_pago' => $metodo_pago
    ]);
    
    // 5. Registrar el vuelto (opcional - para auditoría)
    if ($metodo_pago === 'efectivo' && $vuelto > 0) {
        // Puedes crear una tabla de auditoría de vueltos o solo logearlo
        escribirLog("VUELTO REGISTRADO", [
            'id_venta' => $id_venta,
            'recibido' => $recibido,
            'vuelto' => $vuelto
        ]);
        
        // Opcional: Insertar en tabla de auditoría de vueltos
        // $sql_vuelto = "INSERT INTO auditoria_vueltos (id_venta, recibido, vuelto, fecha) VALUES (?, ?, ?, NOW())";
        // $stmt_vuelto = $conn->prepare($sql_vuelto);
        // $stmt_vuelto->bind_param("idd", $id_venta, $recibido, $vuelto);
        // $stmt_vuelto->execute();
    }
    
    // Confirmar transacción
    $conn->commit();
    
    escribirLog("=== TRANSACCIÓN COMPLETADA EXITOSAMENTE ===", [
        'id_venta' => $id_venta,
        'total' => $total,
        'productos_vendidos' => $contador_detalles,
        'metodo_pago' => $metodo_pago,
        'recibido' => $recibido,
        'vuelto' => $vuelto
    ]);
    
    // Respuesta exitosa (incluye vuelto si aplica)
    $respuesta = [
        'success' => true,
        'message' => 'Venta realizada exitosamente',
        'id_venta' => $id_venta,
        'total' => $total
    ];
    
    if ($metodo_pago === 'efectivo') {
        $respuesta['recibido'] = $recibido;
        $respuesta['vuelto'] = $vuelto;
    }
    
    echo json_encode($respuesta);
    
} catch (Exception $e) {
    $conn->rollback();
    escribirLog("❌ ERROR EN TRANSACCIÓN", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

escribirLog("=== FIN DEL PROCESAMIENTO ===");
?>