<?php
session_start();
if (!isset($_SESSION['admin_logged'])) {
    header('Location: login.php');
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