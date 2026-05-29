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

// Verificar si ya existe una venta para esta cita
$sql_venta_existente = "SELECT vc.id_venta 
                        FROM VENTA_CITA vc 
                        WHERE vc.id_cita = ?";
$stmt_venta_exist = $conn->prepare($sql_venta_existente);
$stmt_venta_exist->bind_param("i", $id_cita);
$stmt_venta_exist->execute();
$venta_existente = $stmt_venta_exist->get_result()->fetch_assoc();

$conn->begin_transaction();

try {
    $id_venta = null;
    error_log("ID_VENTA a usar: " . ($id_venta ?? 'NULL'));
    
    // Si ya existe una venta, usarla; si no, crear una nueva
    if ($venta_existente) {
        $id_venta = $venta_existente['id_venta'];
        error_log("Usando venta existente ID: $id_venta");
    } else {
        // Crear una nueva venta (estado pendiente)
        $sql_venta = "INSERT INTO VENTA (id_cliente, id_empleado, subtotal, iva, total, metodo_pago, estado, notas) 
                      VALUES (?, ?, ?, 0, ?, 'pendiente', 'pendiente', ?)";
        $stmt = $conn->prepare($sql_venta);
        $notas = "Productos para cita #$id_cita (pendiente de pago)";
        $subtotal_temp = 0;
        $stmt->bind_param("iidds", $cliente['id'], $_SESSION['empleado_id'], $subtotal_temp, $subtotal_temp, $notas);
        $stmt->execute();
        $id_venta = $conn->insert_id;
        error_log("Nueva venta creada ID: $id_venta");
        
        // Relacionar venta con cita
        $sql_relacion = "INSERT INTO VENTA_CITA (id_cita, id_venta) VALUES (?, ?)";
        $stmt = $conn->prepare($sql_relacion);
        $stmt->bind_param("ii", $id_cita, $id_venta);
        $stmt->execute();
    }
    
    // Calcular subtotales y verificar productos existentes
    $subtotal_total = 0;
    
    foreach ($productos as $item) {
        $sql_precio = "SELECT precio_venta FROM PRODUCTO WHERE id = ?";
        $stmt = $conn->prepare($sql_precio);
        $stmt->bind_param("i", $item['id_producto']);
        $stmt->execute();
        $precio = $stmt->get_result()->fetch_assoc()['precio_venta'];
        $subtotal_item = $item['cantidad'] * $precio;
        $subtotal_total += $subtotal_item;

        //DEPURACION
        // Dentro del foreach, antes de verificar existencia
error_log("=== Procesando producto ID: " . $item['id_producto'] . " ===");
error_log("Buscando en DETALLE_VENTA con id_venta: $id_venta y id_producto: " . $item['id_producto']);

$sql_check_existente = "SELECT id, cantidad FROM DETALLE_VENTA WHERE id_venta = ? AND id_producto = ?";
$stmt_check = $conn->prepare($sql_check_existente);
$stmt_check->bind_param("ii", $id_venta, $item['id_producto']);
$stmt_check->execute();
$existente = $stmt_check->get_result()->fetch_assoc();

if ($existente) {
    error_log("Producto EXISTE en DETALLE_VENTA. ID: " . $existente['id'] . ", Cantidad actual: " . $existente['cantidad']);
    // ... actualizar
} else {
    error_log("Producto NO EXISTE en DETALLE_VENTA. Se insertará nuevo.");
    // ... insertar
}
        //FIN DEPURACION
        
        // Verificar si el producto ya existe en DETALLE_VENTA para esta venta
        $sql_check_existente = "SELECT id, cantidad FROM DETALLE_VENTA WHERE id_venta = ? AND id_producto = ?";
        $stmt_check = $conn->prepare($sql_check_existente);
        $stmt_check->bind_param("ii", $id_venta, $item['id_producto']);
        $stmt_check->execute();
        $existente = $stmt_check->get_result()->fetch_assoc();
        
        if ($existente) {
            // ACTUALIZAR cantidad existente (NO insertar nuevo)
            $nueva_cantidad = $existente['cantidad'] + $item['cantidad'];
            $nuevo_subtotal = $nueva_cantidad * $precio;
            
            $sql_update = "UPDATE DETALLE_VENTA 
                          SET cantidad = ?, subtotal = ? 
                          WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("idi", $nueva_cantidad, $nuevo_subtotal, $existente['id']);
            $stmt_update->execute();
            error_log("Producto " . $item['id_producto'] . " actualizado. Nueva cantidad: $nueva_cantidad");
        } else {
            // INSERTAR nuevo producto
            $sql_detalle = "INSERT INTO DETALLE_VENTA (id_venta, id_producto, cantidad, precio_unitario, descuento, subtotal) 
                            VALUES (?, ?, ?, ?, 0, ?)";
            $stmt = $conn->prepare($sql_detalle);
            $stmt->bind_param("iiidd", $id_venta, $item['id_producto'], $item['cantidad'], $precio, $subtotal_item);
            $stmt->execute();
            error_log("Producto " . $item['id_producto'] . " insertado. Cantidad: " . $item['cantidad']);
        }
    }
    
    // Actualizar el total de la venta
    $sql_update_venta = "UPDATE VENTA SET subtotal = ?, total = ? WHERE id = ?";
    $stmt_update_venta = $conn->prepare($sql_update_venta);
    $stmt_update_venta->bind_param("ddi", $subtotal_total, $subtotal_total, $id_venta);
    $stmt_update_venta->execute();
    
    $conn->commit();
    
    echo json_encode(['success' => true, 'venta_id' => $id_venta]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("ERROR en transacción: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>