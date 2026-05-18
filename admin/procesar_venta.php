<?php
session_start();
header('Content-Type: application/json');

// ============================================
// DEPURACIÓN DE SESIÓN
// ============================================
error_log("=== PROCESAR VENTA - INICIO ===");
error_log("SESSION user_id: " . ($_SESSION['user_id'] ?? 'NO DEFINIDO'));
error_log("SESSION rol: " . ($_SESSION['rol'] ?? 'NO DEFINIDO'));
error_log("SESSION empleado_id: " . ($_SESSION['empleado_id'] ?? 'NO DEFINIDO'));
error_log("session_id(): " . session_id());

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
escribirLog("SESSION DATA", [
    'user_id' => $_SESSION['user_id'] ?? 'NO',
    'rol' => $_SESSION['rol'] ?? 'NO',
    'empleado_id' => $_SESSION['empleado_id'] ?? 'NO',
    'session_id' => session_id()
]);

// ============================================
// VERIFICAR SESIÓN
// ============================================
if (!isset($_SESSION['user_id'])) {
    escribirLog("ERROR: No autorizado - sin sesión");
    echo json_encode([
        'success' => false, 
        'message' => 'No autorizado. Sesión no iniciada.',
        'debug' => [
            'session_id' => session_id(),
            'cookie_exists' => isset($_COOKIE[session_name()])
        ]
    ]);
    exit;
}

// Verificar rol
$roles_permitidos = ['super_admin', 'admin', 'recepcionista', 'veterinario', 'asistente'];
if (!in_array($_SESSION['rol'], $roles_permitidos)) {
    escribirLog("ERROR: Permiso denegado - rol: " . $_SESSION['rol']);
    echo json_encode([
        'success' => false, 
        'message' => 'No tienes permiso para realizar ventas. Tu rol es: ' . $_SESSION['rol'],
        'roles_permitidos' => $roles_permitidos
    ]);
    exit;
}

// Verificar/Asignar empleado_id
if (!isset($_SESSION['empleado_id'])) {
    escribirLog("ADVERTENCIA: empleado_id no está en sesión, intentando obtener...");
    
    require_once __DIR__ . '/../includes/conexion.php';
    
    $sql_empleado = "SELECT e.id FROM EMPLEADO e 
                     JOIN USUARIO u ON e.id = u.id_empleado 
                     WHERE u.id = ?";
    $stmt = $conn->prepare($sql_empleado);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $_SESSION['empleado_id'] = $row['id'];
        escribirLog("empleado_id obtenido y guardado en sesión: " . $_SESSION['empleado_id']);
    } else {
        escribirLog("ERROR: No se encontró empleado para user_id: " . $_SESSION['user_id']);
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró un empleado asociado a tu usuario. Contacta al administrador.'
        ]);
        exit;
    }
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

// MANEJO DE CLIENTE "VENTA AL PÚBLICO" (ID 0)
$id_cliente = $data['id_cliente'] ?? 0;
$metodo_pago = $data['metodo_pago'] ?? '';
$productos = $data['productos'] ?? [];
$total = $data['total'] ?? 0;
$recibido = $data['recibido'] ?? 0;
$empleado_id = $_SESSION['empleado_id'] ?? null;

// ============================================
// MANEJO DE CLIENTE "VENTA AL PÚBLICO" (ID 0)
// ============================================
if ($id_cliente == 0) {
    // Buscar el cliente "VENTA AL PÚBLICO"
    $sql_general = "SELECT id FROM CLIENTE WHERE nombre = 'VENTA AL PÚBLICO' AND activo = 1 LIMIT 1";
    $result_general = $conn->query($sql_general);
    
    if ($result_general && $result_general->num_rows > 0) {
        $id_cliente = $result_general->fetch_assoc()['id'];
        escribirLog("Cliente VENTA AL PÚBLICO encontrado, ID: " . $id_cliente);
    } else {
        // Crear el cliente genérico si no existe
        $sql_insert = "INSERT INTO CLIENTE (nombre, telefono, activo, fecha_registro) VALUES ('VENTA AL PÚBLICO', '0000000000', 1, NOW())";
        if ($conn->query($sql_insert)) {
            $id_cliente = $conn->insert_id;
            escribirLog("Cliente VENTA AL PÚBLICO creado con ID: " . $id_cliente);
        } else {
            escribirLog("ERROR: No se pudo crear el cliente VENTA AL PÚBLICO");
            // Fallback: usar ID 1 como último recurso
            $id_cliente = 1;
        }
    }
} elseif (!$id_cliente) {
    escribirLog("ERROR: Cliente no seleccionado");
    echo json_encode(['success' => false, 'message' => 'Seleccione un cliente o elija "Venta al público"']);
    exit;
}
// FIN DEL MANEJO DE CLIENTE "VENTA AL PÚBLICO" (ID 0)

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
    'total' => $total,
    'empleado_id' => $empleado_id
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
    
    // 3. Crear DETALLE_VENTA (el trigger se encarga del stock)
    escribirLog("=== CREANDO DETALLES DE VENTA ===");
    $contador_detalles = 0;
    
    foreach ($productos as $prod) {
        // Obtener datos del producto
        $sql_producto = "SELECT precio_venta, nombre FROM PRODUCTO WHERE id = ?";
        $stmt_producto = $conn->prepare($sql_producto);
        $stmt_producto->bind_param("i", $prod['id']);
        $stmt_producto->execute();
        $producto = $stmt_producto->get_result()->fetch_assoc();
        
        if (!$producto) {
            throw new Exception("Producto no encontrado ID: {$prod['id']}");
        }
        
        $precio_unitario = $producto['precio_venta'];
        $subtotal_prod = $precio_unitario * $prod['cantidad'];
        
        escribirLog("Insertando detalle", [
            'id_producto' => $prod['id'],
            'nombre' => $producto['nombre'],
            'cantidad' => $prod['cantidad']
        ]);
        
        // Insertar detalle de venta
        $sql_detalle = "INSERT INTO DETALLE_VENTA (id_venta, id_producto, cantidad, precio_unitario, subtotal) 
                        VALUES (?, ?, ?, ?, ?)";
        $stmt_detalle = $conn->prepare($sql_detalle);
        $stmt_detalle->bind_param("iiidd", $id_venta, $prod['id'], $prod['cantidad'], $precio_unitario, $subtotal_prod);
        
        if (!$stmt_detalle->execute()) {
            throw new Exception("Error al insertar detalle: " . $stmt_detalle->error);
        }
        
        $contador_detalles++;
    }
    
    escribirLog("Total detalles insertados", ['cantidad' => $contador_detalles]);
    
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
    
    // ============================================
    // DIAGNÓSTICO DE PUERTO COM1 (UBICADO AQUÍ)
    // ============================================
    $diagnostico_com1 = [];
    
    // 1. Verificar si el archivo/puerto existe
    $diagnostico_com1['file_exists'] = file_exists("COM1");
    escribirLog("DIAGNÓSTICO - file_exists COM1: " . ($diagnostico_com1['file_exists'] ? 'SI' : 'NO'));
    
    // 2. Intentar abrir con diferentes métodos
    $handle1 = @fopen("COM1", "w");
    $diagnostico_com1['fopen_w'] = ($handle1 !== false);
    if ($handle1) fclose($handle1);
    escribirLog("DIAGNÓSTICO - fopen COM1: " . ($diagnostico_com1['fopen_w'] ? 'SI' : 'NO'));
    
    $handle2 = @fopen("\\\\.\\COM1", "w");
    $diagnostico_com1['fopen_long'] = ($handle2 !== false);
    if ($handle2) fclose($handle2);
    escribirLog("DIAGNÓSTICO - fopen \\\\.\\COM1: " . ($diagnostico_com1['fopen_long'] ? 'SI' : 'NO'));
    
    // 3. Verificar permisos de escritura
    $diagnostico_com1['is_writable'] = is_writable("COM1");
    escribirLog("DIAGNÓSTICO - is_writable COM1: " . ($diagnostico_com1['is_writable'] ? 'SI' : 'NO'));
    
    // 4. Listar puertos COM disponibles
    exec('mode 2>&1', $output, $return_var);
    $diagnostico_com1['modes'] = $output;
    escribirLog("DIAGNÓSTICO - Puertos disponibles", $output);
    
    escribirLog("DIAGNÓSTICO COMPLETO", $diagnostico_com1);
    
    // ============================================
    // ABRIR CAJA REGISTRADORA
    // ============================================
    $caja_abierta = false;
    $mensaje_caja = '';
    
    try {
        // Comando ESC/POS para abrir caja (funciona con Gprinter)
        $comando_caja = chr(27) . chr(112) . chr(0) . chr(50) . chr(250);
        
        // Puerto COM1 detectado en la prueba
        $puerto_caja = "COM1";
        
        escribirLog("Intentando abrir caja en puerto: " . $puerto_caja);
        
        // Intentar abrir el puerto COM1
        if (($handle = @fopen($puerto_caja, "w"))) {
            $bytes_escritos = fwrite($handle, $comando_caja);
            fclose($handle);
            
            if ($bytes_escritos > 0) {
                $caja_abierta = true;
                $mensaje_caja = " Caja abierta correctamente.";
                escribirLog("✅ Caja registradora abierta en COM1");
            } else {
                $mensaje_caja = " No se pudo escribir en COM1.";
                escribirLog("⚠️ No se pudo escribir en COM1");
            }
        } else {
            $mensaje_caja = " No se pudo abrir el puerto COM1.";
            escribirLog("⚠️ No se pudo abrir el puerto COM1");
        }
        
    } catch (Exception $e) {
        $mensaje_caja = " Error al abrir caja: " . $e->getMessage();
        escribirLog("❌ Error al abrir caja: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Venta realizada exitosamente' . $mensaje_caja,
        'id_venta' => $id_venta,
        'total' => $total,
        'caja_abierta' => $caja_abierta,
        'diagnostico' => $diagnostico_com1  // Para depuración
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