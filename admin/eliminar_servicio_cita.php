<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['super_admin', 'admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_detalle = (int)($_GET['id'] ?? 0);
$id_cita = (int)($_GET['id_cita'] ?? 0);

if (!$id_detalle || !$id_cita) {
    die("Datos incompletos");
}

$sql = "DELETE FROM DETALLE_CITA WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_detalle);
$stmt->execute();

header("Location: detalle_cita.php?id=$id_cita");
exit;
?>