<?php
session_start();

// Cambiar de admin_logged a user_id
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol para acceso
if (!in_array($_SESSION['rol'], ['super_admin', 'admin'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_producto = (int)($_GET['id'] ?? 0);
if (!$id_producto) {
    header('Location: productos.php');
    exit;
}

// Verificar si el producto existe
$sql_check = "SELECT imagen FROM PRODUCTO WHERE id = ?";
$stmt = $conn->prepare($sql_check);
$stmt->bind_param("i", $id_producto);
$stmt->execute();
$result = $stmt->get_result();
$producto = $result->fetch_assoc();

if (!$producto) {
    header('Location: productos.php');
    exit;
}

// Eliminar la imagen asociada si existe
if (!empty($producto['imagen']) && file_exists('../' . $producto['imagen'])) {
    unlink('../' . $producto['imagen']);
}

// Eliminar el producto
$sql_delete = "DELETE FROM PRODUCTO WHERE id = ?";
$stmt = $conn->prepare($sql_delete);
$stmt->bind_param("i", $id_producto);
$stmt->execute();

header('Location: productos.php?deleted=1');
exit;
?>