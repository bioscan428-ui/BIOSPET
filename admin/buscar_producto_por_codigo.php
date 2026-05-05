<?php
// admin/buscar_producto_por_codigo.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$codigo = $_GET['codigo'] ?? '';

if (empty($codigo)) {
    echo json_encode(['error' => 'Código vacío']);
    exit;
}

$sql = "SELECT id, nombre, precio_venta, stock_actual 
        FROM PRODUCTO 
        WHERE codigo_barras = ? AND activo = 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $codigo);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $producto = $result->fetch_assoc();
    echo json_encode($producto);
} else {
    echo json_encode(null);
}
?>