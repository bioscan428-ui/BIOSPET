<?php
session_start();
require_once 'includes/conexion.php';

// 1. Validaciones de seguridad iniciales
if (empty($_SESSION['carrito'])) {
    header('Location: tienda.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: carrito.php');
    exit;
}

// 2. Recolección de datos
$nombre = trim($_POST['nombre'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$metodo_pago = $_POST['metodo_pago'] ?? 'efectivo';

if (empty($nombre) || empty($email) || empty($telefono)) {
    die("Error: Datos del cliente incompletos.");
}

// --- INICIO DEL PROCESO CRÍTICO ---

$conn->begin_transaction();

try {
    // 3. BUSCAR O CREAR CLIENTE
    $sql_cliente = "SELECT id FROM CLIENTE WHERE email = ? FOR UPDATE";
    $stmt = $conn->prepare($sql_cliente);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $id_cliente = $result->fetch_assoc()['id'];
    } else {
        $sql_insert = "INSERT INTO CLIENTE (nombre, email, telefono, direccion, fecha_registro, activo) 
                       VALUES (?, ?, ?, ?, NOW(), 1)";
        $stmt = $conn->prepare($sql_insert);
        $stmt->bind_param("ssss", $nombre, $email, $telefono, $direccion);
        $stmt->execute();
        $id_cliente = $conn->insert_id;
    }

    // 4. VALIDAR STOCK Y CALCULAR TOTALES (DENTRO DE LA TRANSACCIÓN)
    $total = 0;
    $productos_venta = [];

    foreach ($_SESSION['carrito'] as $id_prod => $cantidad) {
        // Bloqueamos la fila del producto para que nadie más la modifique hasta terminar
        $sql_check = "SELECT id, nombre, precio_venta, stock_actual, 
                      stock_suficiente(id, ?) as disponible 
                      FROM PRODUCTO WHERE id = ? AND activo = 1 FOR UPDATE";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ii", $cantidad, $id_prod);
        $stmt_check->execute();
        $prod_data = $stmt_check->get_result()->fetch_assoc();

        if (!$prod_data) {
            throw new Exception("El producto ID $id_prod no existe o no está activo.");
        }

        if (!$prod_data['disponible']) {
            throw new Exception("Stock insuficiente para: " . $prod_data['nombre'] . 
                                ". Disponible: " . $prod_data['stock_actual']);
        }

        $subtotal = $prod_data['precio_venta'] * $cantidad;
        $total += $subtotal;
        
        $productos_venta[] = [
            'id' => $prod_data['id'],
            'cantidad' => $cantidad,
            'precio' => $prod_data['precio_venta'],
            'subtotal' => $subtotal
        ];
    }

    // 5. REGISTRAR LA VENTA
    $iva = $total * 0.16;
    $sql_venta = "INSERT INTO VENTA (id_cliente, subtotal, iva, total, metodo_pago, estado, fecha_venta) 
                  VALUES (?, ?, ?, ?, ?, 'completada', NOW())";
    $stmt_venta = $conn->prepare($sql_venta);
    $stmt_venta->bind_param("iddds", $id_cliente, $total, $iva, $total, $metodo_pago);
    $stmt_venta->execute();
    $id_venta = $conn->insert_id;

    // 6. REGISTRAR DETALLES
    // Nota: Tu trigger 'after_insert_detalle_venta' se activará aquí automáticamente
    // actualizando el stock y creando el movimiento de inventario.
    $sql_det = "INSERT INTO DETALLE_VENTA (id_venta, id_producto, cantidad, precio_unitario, subtotal) 
                VALUES (?, ?, ?, ?, ?)";
    $stmt_det = $conn->prepare($sql_det);

    foreach ($productos_venta as $item) {
        $stmt_det->bind_param("iiidd", $id_venta, $item['id'], $item['cantidad'], $item['precio'], $item['subtotal']);
        $stmt_det->execute();
    }

    // Si todo salió bien, guardamos los cambios
    $conn->commit();

    // 7. Limpieza y redirección
    unset($_SESSION['carrito']);
    header('Location: compra_exitosa.php?id=' . $id_venta);
    exit;

} catch (Exception $e) {
    // Si algo falló, deshacemos todo lo que se hizo en la base de datos
    $conn->rollback();
    die("Error al procesar la compra: " . $e->getMessage());
}
?>