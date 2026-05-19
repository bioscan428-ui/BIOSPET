<?php
// admin/validar_codigo_barras.php
session_start();
require_once __DIR__ . '/../includes/conexion.php';

header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['existe' => false, 'error' => 'No autorizado']);
    exit;
}

$codigo = $_GET['codigo'] ?? '';
if (empty($codigo)) {
    echo json_encode(['existe' => false]);
    exit;
}

// Verificar si el código ya existe en la BD
$sql = "SELECT id, nombre FROM PRODUCTO WHERE codigo_barras = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $codigo);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $producto = $result->fetch_assoc();
    echo json_encode([
        'existe' => true, 
        'mensaje' => 'El código ya existe en el producto: ' . $producto['nombre']
    ]);
} else {
    echo json_encode(['existe' => false]);
}
?>