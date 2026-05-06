<?php
session_start();
header('Content-Type: application/json');

// Mostrar errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
$recibido = $data['recibido'] ?? 0;
$empleado_id = $_SESSION['empleado_id'] ?? null;

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
    'total' => $total
]);

// Iniciar transacción
$conn->begin_transaction();

try {
    // 1. Verificar stock de todos los productos
    escribirLog("=== VERIFICANDO STOCK ===");
    foreach ($productos as $prod) {
        $sql_stock = "SELECT stock_actual, nombre, id FROM PRODUCTO WHERE id = ?";
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
    
    // 3. Crear DETALLE_VENTA y ACTUALIZAR STOCK DIRECTAMENTE
    escribirLog("=== CREANDO DETALLES Y ACTUALIZANDO STOCK ===");
    $contador_detalles = 0;
    
    foreach ($productos as $prod) {
        // Obtener datos del producto
        $sql_producto = "SELECT precio_venta, nombre, stock_actual FROM PRODUCTO WHERE id = ?";
        $stmt_producto = $conn->prepare($sql_producto);
        $stmt_producto->bind_param("i", $prod['id']);
        $stmt_producto->execute();
        $producto = $stmt_producto->get_result()->fetch_assoc();
        
        if (!$producto) {
            throw new Exception("Producto no encontrado ID: {$prod['id']}");
        }
        
        $precio_unitario = $producto['precio_venta'];
        $subtotal_prod = $precio_unitario * $prod['cantidad'];
        
        escribirLog("Procesando producto", [
            'id_producto' => $prod['id'],
            'nombre' => $producto['nombre'],
            'stock_actual' => $producto['stock_actual'],
            'cantidad_vender' => $prod['cantidad']
        ]);
        
        // Insertar detalle de venta
        $sql_detalle = "INSERT INTO DETALLE_VENTA (id_venta, id_producto, cantidad, precio_unitario, subtotal) 
                        VALUES (?, ?, ?, ?, ?)";
        $stmt_detalle = $conn->prepare($sql_detalle);
        $stmt_detalle->bind_param("iiidd", $id_venta, $prod['id'], $prod['cantidad'], $precio_unitario, $subtotal_prod);
        
        if (!$stmt_detalle->execute()) {
            throw new Exception("Error al insertar detalle: " . $stmt_detalle->error);
        }
        
        // ACTUALIZAR STOCK (la parte importante que faltaba)
        $sql_update = "UPDATE PRODUCTO SET stock_actual = stock_actual - ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $prod['cantidad'], $prod['id']);
        
        if (!$stmt_update->execute()) {
            throw new Exception("Error al actualizar stock: " . $stmt_update->error);
        }
        
        escribirLog("Stock actualizado", [
            'id_producto' => $prod['id'],
            'cantidad_restada' => $prod['cantidad'],
            'filas_afectadas' => $stmt_update->affected_rows
        ]);
        
        $contador_detalles++;
    }
    
    escribirLog("Total detalles insertados y stock actualizado", ['cantidad' => $contador_detalles]);
    
    // 4. Registrar PAGO
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
    
    // Confirmar transacción
    $conn->commit();
    
    escribirLog("=== TRANSACCIÓN COMPLETADA EXITOSAMENTE ===", [
        'id_venta' => $id_venta,
        'total' => $total,
        'productos_vendidos' => $contador_detalles
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Venta realizada exitosamente',
        'id_venta' => $id_venta,
        'total' => $total
    ]);
    
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