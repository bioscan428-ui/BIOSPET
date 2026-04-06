<?php
session_start();
require_once 'includes/conexion.php';

// Verificar que hay productos en el carrito
if (empty($_SESSION['carrito'])) {
    header('Location: tienda.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: carrito.php');
    exit;
}

// Datos del cliente
$nombre = trim($_POST['nombre'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$metodo_pago = $_POST['metodo_pago'] ?? 'efectivo';

// Validaciones básicas
if (empty($nombre) || empty($email) || empty($telefono)) {
    die("Error: Datos incompletos");
}

// Calcular total
$total = 0;
$productos_venta = [];
$ids = array_keys($_SESSION['carrito']);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "SELECT id, nombre, precio_venta, stock_actual FROM PRODUCTO WHERE id IN ($placeholders) AND activo = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
$stmt->execute();
$result = $stmt->get_result();

while ($producto = $result->fetch_assoc()) {
    $cantidad = $_SESSION['carrito'][$producto['id']];
    
    // Validar stock
    if ($producto['stock_actual'] < $cantidad) {
        die("Error: Stock insuficiente para {$producto['nombre']}. Disponible: {$producto['stock_actual']}");
    }
    
    $subtotal = $producto['precio_venta'] * $cantidad;
    $productos_venta[] = [
        'id' => $producto['id'],
        'cantidad' => $cantidad,
        'precio' => $producto['precio_venta'],
        'subtotal' => $subtotal
    ];
    $total += $subtotal;
}

$conn->begin_transaction();

try {
    // 1. Buscar o crear cliente
    $sql_cliente = "SELECT id FROM CLIENTE WHERE email = ?";
    $stmt = $conn->prepare($sql_cliente);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $cliente = $result->fetch_assoc();
        $id_cliente = $cliente['id'];
    } else {
        // Crear cliente nuevo
        $sql_insert = "INSERT INTO CLIENTE (nombre, email, telefono, direccion, fecha_registro) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql_insert);
        $stmt->bind_param("ssss", $nombre, $email, $telefono, $direccion);
        $stmt->execute();
        $id_cliente = $conn->insert_id;
    }
    
    // 2. Registrar venta
    $iva = $total * 0.16;
    $sql_venta = "INSERT INTO VENTA (id_cliente, subtotal, iva, total, metodo_pago, estado, fecha_venta) 
                  VALUES (?, ?, ?, ?, ?, 'completada', NOW())";
    $stmt = $conn->prepare($sql_venta);
    $stmt->bind_param("iddds", $id_cliente, $total, $iva, $total, $metodo_pago);
    $stmt->execute();
    $id_venta = $conn->insert_id;
    
    // 3. Registrar detalles de venta y actualizar stock
    $sql_detalle = "INSERT INTO DETALLE_VENTA (id_venta, id_producto, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)";
    $stmt_detalle = $conn->prepare($sql_detalle);
    
    $sql_update_stock = "UPDATE PRODUCTO SET stock_actual = stock_actual - ? WHERE id = ?";
    $stmt_stock = $conn->prepare($sql_update_stock);
    
    foreach ($productos_venta as $item) {
        // Insertar detalle
        $stmt_detalle->bind_param("iiidd", $id_venta, $item['id'], $item['cantidad'], $item['precio'], $item['subtotal']);
        $stmt_detalle->execute();
        
        // Actualizar stock
        $stmt_stock->bind_param("ii", $item['cantidad'], $item['id']);
        $stmt_stock->execute();
    }
    
    $conn->commit();
    
    // Limpiar carrito
    unset($_SESSION['carrito']);
    
    // Redirigir a página de éxito
    header('Location: compra_exitosa.php?id=' . $id_venta);
    exit;
    
} catch (Exception $e) {
    $conn->rollback();
    die("Error al procesar la compra: " . $e->getMessage());
}
?>