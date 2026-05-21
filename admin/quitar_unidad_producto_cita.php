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

// Obtener el detalle actual
$sql_detalle = "SELECT cantidad, precio_unitario, subtotal FROM DETALLE_VENTA WHERE id_venta = ? AND id_producto = ?";
$stmt_det = $conn->prepare($sql_detalle);
$stmt_det->bind_param("ii", $id_venta, $id_producto);
$stmt_det->execute();
$detalle = $stmt_det->get_result()->fetch_assoc();

if (!$detalle) {
    echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
    exit;
}

$nueva_cantidad = $detalle['cantidad'] - 1;
$nuevo_subtotal = $nueva_cantidad * $detalle['precio_unitario'];

if ($nueva_cantidad <= 0) {
    // Si la cantidad llega a 0, eliminar el producto
    $sql_delete = "DELETE FROM DETALLE_VENTA WHERE id_venta = ? AND id_producto = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("ii", $id_venta, $id_producto);
    $stmt_delete->execute();
} else {
    // Actualizar cantidad y subtotal
    $sql_update = "UPDATE DETALLE_VENTA SET cantidad = ?, subtotal = ? WHERE id_venta = ? AND id_producto = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("idii", $nueva_cantidad, $nuevo_subtotal, $id_venta, $id_producto);
    $stmt_update->execute();
}

// Restaurar 1 unidad al stock
$sql_restaurar = "UPDATE PRODUCTO SET stock_actual = stock_actual + 1 WHERE id = ?";
$stmt_restore = $conn->prepare($sql_restaurar);
$stmt_restore->bind_param("i", $id_producto);
$stmt_restore->execute();

// Actualizar totales de la venta
$sql_totales = "UPDATE VENTA SET 
                subtotal = (SELECT COALESCE(SUM(subtotal), 0) FROM DETALLE_VENTA WHERE id_venta = ?),
                total = (SELECT COALESCE(SUM(subtotal), 0) FROM DETALLE_VENTA WHERE id_venta = ?)
                WHERE id = ?";
$stmt_totales = $conn->prepare($sql_totales);
$stmt_totales->bind_param("iii", $id_venta, $id_venta, $id_venta);
$stmt_totales->execute();

// Verificar si no quedan productos en la venta
$sql_check = "SELECT COUNT(*) as total FROM DETALLE_VENTA WHERE id_venta = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("i", $id_venta);
$stmt_check->execute();
$count = $stmt_check->get_result()->fetch_assoc()['total'];

if ($count == 0) {
    $sql_del_rel = "DELETE FROM VENTA_CITA WHERE id_cita = ?";
    $stmt_del_rel = $conn->prepare($sql_del_rel);
    $stmt_del_rel->bind_param("i", $id_cita);
    $stmt_del_rel->execute();
    
    $sql_del_venta = "DELETE FROM VENTA WHERE id = ?";
    $stmt_del_venta = $conn->prepare($sql_del_venta);
    $stmt_del_venta->bind_param("i", $id_venta);
    $stmt_del_venta->execute();
}

echo json_encode(['success' => true, 'message' => 'Unidad eliminada y stock restaurado']);
?>