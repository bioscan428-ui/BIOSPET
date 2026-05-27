<?php
// admin/buscar_por_codigo.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$codigo_barras = trim($_GET['codigo'] ?? $_POST['codigo'] ?? '');

if (empty($codigo_barras)) {
    echo json_encode(['success' => false, 'error' => 'Código de barras vacío']);
    exit;
}

// Buscar producto por código de barras
$sql = "SELECT p.id, p.nombre, p.precio_venta, p.stock_actual, p.maneja_stock 
        FROM PRODUCTO p
        WHERE p.codigo_barras = ? AND p.activo = 1
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $codigo_barras);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $producto = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'id' => $producto['id'],
        'nombre' => $producto['nombre'],
        'precio_venta' => $producto['precio_venta'],
        'stock_actual' => $producto['stock_actual'],
        'maneja_stock' => $producto['maneja_stock']
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
}
?>