<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_cita = (int)($_POST['id_cita'] ?? 0);
$id_producto = (int)($_POST['id_producto'] ?? 0);

if (!$id_cita || !$id_producto) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

// Buscar la venta asociada a la cita
$sql_venta = "SELECT v.id FROM VENTA v 
              JOIN VENTA_CITA vc ON v.id = vc.id_venta 
              WHERE vc.id_cita = ? AND v.estado = 'pendiente'";
$stmt_venta = $conn->prepare($sql_venta);
$stmt_venta->bind_param("i", $id_cita);
$stmt_venta->execute();
$venta = $stmt_venta->get_result()->fetch_assoc();

if (!$venta) {
    echo json_encode(['success' => false, 'error' => 'No se encontró la venta asociada']);
    exit;
}

$id_venta = $venta['id'];

// Obtener la cantidad actual del producto en el detalle ANTES de eliminar
$sql_cantidad = "SELECT cantidad, precio_unitario FROM DETALLE_VENTA WHERE id_venta = ? AND id_producto = ?";
$stmt_cant = $conn->prepare($sql_cantidad);
$stmt_cant->bind_param("ii", $id_venta, $id_producto);
$stmt_cant->execute();
$detalle = $stmt_cant->get_result()->fetch_assoc();

if (!$detalle) {
    echo json_encode(['success' => false, 'error' => 'Producto no encontrado en esta cita']);
    exit;
}

$cantidad_eliminar = $detalle['cantidad'];
$precio_unitario = $detalle['precio_unitario'];

// Restaurar el stock (devuelve la cantidad eliminada al inventario)
$sql_restaurar_stock = "UPDATE PRODUCTO SET stock_actual = stock_actual + ? WHERE id = ?";
$stmt_restore = $conn->prepare($sql_restaurar_stock);
$stmt_restore->bind_param("ii", $cantidad_eliminar, $id_producto);
$stmt_restore->execute();

// Eliminar el producto del detalle
$sql_delete = "DELETE FROM DETALLE_VENTA WHERE id_venta = ? AND id_producto = ?";
$stmt_delete = $conn->prepare($sql_delete);
$stmt_delete->bind_param("ii", $id_venta, $id_producto);

if ($stmt_delete->execute()) {
    // Actualizar totales de la venta
    $sql_update = "UPDATE VENTA SET 
                   subtotal = (SELECT COALESCE(SUM(subtotal), 0) FROM DETALLE_VENTA WHERE id_venta = ?),
                   total = (SELECT COALESCE(SUM(subtotal), 0) FROM DETALLE_VENTA WHERE id_venta = ?)
                   WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("iii", $id_venta, $id_venta, $id_venta);
    $stmt_update->execute();
    
    // Si no quedan productos, eliminar la venta y la relación
    $sql_check = "SELECT COUNT(*) as total FROM DETALLE_VENTA WHERE id_venta = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $id_venta);
    $stmt_check->execute();
    $count = $stmt_check->get_result()->fetch_assoc()['total'];
    
    if ($count == 0) {
        $sql_delete_relacion = "DELETE FROM VENTA_CITA WHERE id_cita = ?";
        $stmt_del_rel = $conn->prepare($sql_delete_relacion);
        $stmt_del_rel->bind_param("i", $id_cita);
        $stmt_del_rel->execute();
        
        $sql_delete_venta = "DELETE FROM VENTA WHERE id = ?";
        $stmt_del_venta = $conn->prepare($sql_delete_venta);
        $stmt_del_venta->bind_param("i", $id_venta);
        $stmt_del_venta->execute();
    }
    
    echo json_encode(['success' => true, 'message' => 'Producto eliminado y stock restaurado']);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al eliminar el producto']);
}
?>