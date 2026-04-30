<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol para acceso
if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'recepcionista'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_cita = (int)($_POST['id_cita'] ?? 0);
$productos_json = $_POST['productos_json'] ?? '';

error_log("=== INICIO REGISTRAR VENTA CITA ===");
error_log("ID Cita: " . $id_cita);
error_log("JSON recibido: " . $productos_json);

if (!$id_cita) {
    $_SESSION['error'] = "ID de cita no válido";
    header("Location: detalle_cita.php?id=$id_cita");
    exit;
}

if (empty($productos_json)) {
    $_SESSION['error'] = "No hay productos para registrar";
    header("Location: detalle_cita.php?id=$id_cita");
    exit;
}

$productos_venta = json_decode($productos_json, true);

error_log("Productos decodificados: " . print_r($productos_venta, true));

if (empty($productos_venta)) {
    $_SESSION['error'] = "No hay productos válidos en la venta";
    header("Location: detalle_cita.php?id=$id_cita");
    exit;
}

// ========== OBTENER CLIENTE A TRAVÉS DE LA MASCOTA ==========
$sql_cliente = "SELECT cl.id AS id_cliente 
                FROM CITA c
                INNER JOIN MASCOTA m ON c.id_mascota = m.id
                INNER JOIN CLIENTE cl ON m.id_cliente = cl.id
                WHERE c.id = ?";
$stmt_cliente = $conn->prepare($sql_cliente);
$stmt_cliente->bind_param("i", $id_cita);
$stmt_cliente->execute();
$result_cliente = $stmt_cliente->get_result();
$cliente = $result_cliente->fetch_assoc();

if (!$cliente) {
    $_SESSION['error'] = "No se encontró el cliente asociado a la cita";
    header("Location: detalle_cita.php?id=$id_cita");
    exit;
}

$id_cliente = $cliente['id_cliente'];
error_log("ID Cliente encontrado: " . $id_cliente);

// Calcular totales y verificar stock
$subtotal = 0;
foreach ($productos_venta as $index => $item) {
    error_log("=== PROCESANDO PRODUCTO " . ($index + 1) . " ===");
    error_log("ID Producto: " . $item['id_producto']);
    error_log("Cantidad solicitada: " . $item['cantidad']);
    
    $sql_precio = "SELECT precio_venta, stock_actual FROM PRODUCTO WHERE id = ?";
    $stmt_precio = $conn->prepare($sql_precio);
    $stmt_precio->bind_param("i", $item['id_producto']);
    $stmt_precio->execute();
    $res_precio = $stmt_precio->get_result();
    $prod = $res_precio->fetch_assoc();
    
    if (!$prod) {
        $_SESSION['error'] = "Producto no encontrado: ID " . $item['id_producto'];
        header("Location: detalle_cita.php?id=$id_cita");
        exit;
    }
    
    $precio = $prod['precio_venta'];
    $stock_actual = $prod['stock_actual'];
    
    error_log("Stock actual en BD: " . $stock_actual);
    error_log("Precio: " . $precio);
    
    if ($stock_actual < $item['cantidad']) {
        $_SESSION['error'] = "Stock insuficiente para el producto ID: " . $item['id_producto'] . ". Stock actual: " . $stock_actual . ", solicitado: " . $item['cantidad'];
        header("Location: detalle_cita.php?id=$id_cita");
        exit;
    }
    
    $descuento = $item['descuento'] ?? 0;
    $precio_con_descuento = $precio * (1 - $descuento/100);
    $subtotal += $item['cantidad'] * $precio_con_descuento;
    
    error_log("Subtotal parcial: " . $subtotal);
}

$iva = 0;
$total = $subtotal;
error_log("Total de la venta: " . $total);

$conn->begin_transaction();

try {
    // 1. Insertar cabecera de venta
    $sql_venta = "INSERT INTO VENTA (id_cliente, id_empleado, subtotal, iva, total, metodo_pago, estado, notas) 
                  VALUES (?, ?, ?, ?, ?, 'efectivo', 'completada', ?)";
    $stmt_venta = $conn->prepare($sql_venta);
    $notas = "Venta asociada a la cita #$id_cita";
    $stmt_venta->bind_param("iiddds", $id_cliente, $_SESSION['empleado_id'], $subtotal, $iva, $total, $notas);
    $stmt_venta->execute();
    $id_venta = $conn->insert_id;
    
    error_log("Venta insertada - ID: " . $id_venta);
    
    // 2. Insertar detalles de venta y actualizar stock
    foreach ($productos_venta as $index => $item) {
        error_log("=== INSERTANDO DETALLE " . ($index + 1) . " ===");
        error_log("ID Producto: " . $item['id_producto']);
        error_log("Cantidad en PHP: " . $item['cantidad']);
        error_log("Tipo de dato cantidad: " . gettype($item['cantidad']));
        
        $sql_precio = "SELECT precio_venta, stock_actual FROM PRODUCTO WHERE id = ?";
        $stmt_precio = $conn->prepare($sql_precio);
        $stmt_precio->bind_param("i", $item['id_producto']);
        $stmt_precio->execute();
        $res_precio = $stmt_precio->get_result();
        $prod = $res_precio->fetch_assoc();
        
        $precio = $prod['precio_venta'];
        $stock_actual = $prod['stock_actual'];
        $descuento = $item['descuento'] ?? 0;
        $precio_con_descuento = $precio * (1 - $descuento/100);
        $subtotal_item = $item['cantidad'] * $precio_con_descuento;
        
        error_log("Precio: " . $precio);
        error_log("Subtotal item: " . $subtotal_item);
        
        $sql_detalle = "INSERT INTO DETALLE_VENTA (id_venta, id_producto, cantidad, precio_unitario, descuento, subtotal) 
                        VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_detalle = $conn->prepare($sql_detalle);
        $stmt_detalle->bind_param("iiiddd", $id_venta, $item['id_producto'], $item['cantidad'], $precio, $descuento, $subtotal_item);
        $stmt_detalle->execute();
        
        error_log("Detalle insertado correctamente");
        
        // NOTA: EL TRIGGER after_insert_detalle_venta YA ACTUALIZA EL STOCK
        // No es necesario hacer UPDATE aquí porque lo hace el trigger
        // Si lo dejas, el stock se actualizará DOBLE
        
        // Comenta esta línea para evitar doble actualización:
        // $sql_update = "UPDATE PRODUCTO SET stock_actual = stock_actual - ? WHERE id = ?";
        // $stmt_update = $conn->prepare($sql_update);
        // $stmt_update->bind_param("ii", $item['cantidad'], $item['id_producto']);
        // $stmt_update->execute();
        
        error_log("Stock actualizado (por trigger)");
    }
    
    // 3. Registrar relación venta-cita
    $sql_relacion = "INSERT INTO VENTA_CITA (id_cita, id_venta) VALUES (?, ?)";
    $stmt_relacion = $conn->prepare($sql_relacion);
    $stmt_relacion->bind_param("ii", $id_cita, $id_venta);
    $stmt_relacion->execute();
    
    $conn->commit();
    
    error_log("=== TRANSACCIÓN COMPLETADA EXITOSAMENTE ===");
    $_SESSION['mensaje'] = "Venta registrada correctamente. Total: $" . number_format($total, 2);
    header("Location: detalle_cita.php?id=$id_cita");
    exit;
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("=== ERROR EN LA TRANSACCIÓN ===");
    error_log("Error: " . $e->getMessage());
    $_SESSION['error'] = "Error al registrar la venta: " . $e->getMessage();
    header("Location: detalle_cita.php?id=$id_cita");
    exit;
}
?>