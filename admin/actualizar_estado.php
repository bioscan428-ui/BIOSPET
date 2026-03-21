<?php
session_start();
if (!isset($_SESSION['admin_logged'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_cita = (int)($_GET['id'] ?? 0);
$nuevo_estado = $_GET['estado'] ?? '';

$estados_validos = ['pendiente', 'confirmada', 'cancelada', 'completada'];

if (!$id_cita || !in_array($nuevo_estado, $estados_validos)) {
    die("Parámetros inválidos");
}

$sql = "UPDATE CITA SET estado = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $nuevo_estado, $id_cita);
$stmt->execute();

header('Location: detalle_cita.php?id=' . $id_cita);
exit;
?>