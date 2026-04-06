<?php
session_start();

$id_producto = (int)($_GET['id'] ?? 0);
$cantidad = (int)($_GET['cantidad'] ?? 1);

if (!$id_producto) {
    header('Location: tienda.php');
    exit;
}

// Inicializar carrito si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Si el producto ya está en el carrito, aumentar cantidad
if (isset($_SESSION['carrito'][$id_producto])) {
    $_SESSION['carrito'][$id_producto] += $cantidad;
} else {
    $_SESSION['carrito'][$id_producto] = $cantidad;
}

header('Location: carrito.php');
exit;
?>