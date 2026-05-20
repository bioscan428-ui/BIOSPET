<?php
session_start();
header('Content-Type: application/json');

error_log("=== AGREGAR PRODUCTOS CITA ===");
error_log("POST recibido: " . print_r($_POST, true));

if (!isset($_SESSION['user_id'])) {
    error_log("ERROR: Usuario no autorizado");
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_cita = (int)$_POST['id_cita'];
$productos_json = $_POST['productos_json'];

error_log("ID Cita: $id_cita");
error_log("Productos JSON: $productos_json");

if (!$id_cita) {
    error_log("ERROR: ID de cita no recibido");
    echo json_encode(['success' => false, 'error' => 'ID de cita no recibido']);
    exit;
}

if (empty($productos_json)) {
    error_log("ERROR: No se recibieron productos");
    echo json_encode(['success' => false, 'error' => 'No se recibieron productos']);
    exit;
}

$productos = json_decode($productos_json, true);
error_log("Productos decodificados: " . print_r($productos, true));

if (empty($productos)) {
    echo json_encode(['success' => false, 'error' => 'No hay productos']);
    exit;
}

// Obtener cliente de la cita
$sql_cliente = "SELECT cl.id FROM CITA c JOIN MASCOTA m ON c.id_mascota = m.id JOIN CLIENTE cl ON m.id_cliente = cl.id WHERE c.id = ?";
$stmt = $conn->prepare($sql_cliente);
$stmt->bind_param("i", $id_cita);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();

if (!$cliente) {
    echo json_encode(['success' => false, 'error' => 'Cliente no encontrado']);
    exit;
}

// Validar stock antes de insertar
foreach ($productos as $item) {
    $sql_stock = "SELECT stock_actual FROM PRODUCTO WHERE id = ?";
    $stmt = $conn->prepare($sql_stock);
    $stmt->bind_param("i", $item['id_producto']);
    $stmt->execute();
    $stock = $stmt->get_result()->fetch_assoc()['stock_actual'];
    
    if ($stock < $item['cantidad']) {
        echo json_encode(['success' => false, 'error' => "Stock insuficiente para producto ID: " . $item['id_producto']]);
        exit;
    }
}

// Calcular totales
$subtotal = 0;
foreach ($productos as $item) {
    $sql_precio = "SELECT precio_venta FROM PRODUCTO WHERE id = ?";
    $stmt = $conn->prepare($sql_precio);
    $stmt->bind_param("i", $item['id_producto']);
    $stmt->execute();
    $precio = $stmt->get_result()->fetch_assoc()['precio_venta'];
    $subtotal += $item['cantidad'] * $precio;
}

$conn->begin_transaction();

try {
    // Crear la venta (estado pendiente)
    $sql_venta = "INSERT INTO VENTA (id_cliente, id_empleado, subtotal, iva, total, metodo_pago, estado, notas) 
                  VALUES (?, ?, ?, 0, ?, 'pendiente', 'pendiente', ?)";
    $stmt = $conn->prepare($sql_venta);
    $notas = "Productos para cita #$id_cita (pendiente de pago)";
    $stmt->bind_param("iidds", $cliente['id'], $_SESSION['empleado_id'], $subtotal, $subtotal, $notas);
    $stmt->execute();
    $id_venta = $conn->insert_id;
    
    // Insertar detalles (el trigger se encargará del stock)
    foreach ($productos as $item) {
        $sql_precio = "SELECT precio_venta FROM PRODUCTO WHERE id = ?";
        $stmt = $conn->prepare($sql_precio);
        $stmt->bind_param("i", $item['id_producto']);
        $stmt->execute();
        $precio = $stmt->get_result()->fetch_assoc()['precio_venta'];
        $subtotal_item = $item['cantidad'] * $precio;
        
        $sql_detalle = "INSERT INTO DETALLE_VENTA (id_venta, id_producto, cantidad, precio_unitario, descuento, subtotal) 
                        VALUES (?, ?, ?, ?, 0, ?)";
        $stmt = $conn->prepare($sql_detalle);
        $stmt->bind_param("iiidd", $id_venta, $item['id_producto'], $item['cantidad'], $precio, $subtotal_item);
        $stmt->execute();
        
        // ⚠️ NO actualices el stock aquí - el trigger after_insert_detalle_venta lo hará
        // $sql_update = "UPDATE PRODUCTO SET stock_actual = stock_actual - ? WHERE id = ?";
        // $stmt = $conn->prepare($sql_update);
        // $stmt->bind_param("ii", $item['cantidad'], $item['id_producto']);
        // $stmt->execute();
    }
    
    // Relacionar venta con cita
    $sql_relacion = "INSERT INTO VENTA_CITA (id_cita, id_venta) VALUES (?, ?)";
    $stmt = $conn->prepare($sql_relacion);
    $stmt->bind_param("ii", $id_cita, $id_venta);
    $stmt->execute();
    
    $conn->commit();
    
    echo json_encode(['success' => true, 'venta_id' => $id_venta]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>