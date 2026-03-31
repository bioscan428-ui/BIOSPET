<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['super_admin', 'admin', 'veterinario'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_cita = (int)($_POST['id_cita'] ?? 0);
$id_servicio = (int)($_POST['id_servicio'] ?? 0);
$precio_fijado = (float)($_POST['precio_fijado'] ?? 0);

if (!$id_cita || !$id_servicio || !$precio_fijado) {
    die("Datos incompletos");
}

// Verificar que la cita existe y no está cancelada o completada
$sql_check = "SELECT estado FROM CITA WHERE id = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("i", $id_cita);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$cita = $result_check->fetch_assoc();

if (!$cita) {
    die("Cita no encontrada");
}

if (!in_array($cita['estado'], ['pendiente', 'confirmada'])) {
    die("Solo se pueden agregar servicios a citas pendientes o confirmadas");
}

// Insertar detalle de cita
$sql = "INSERT INTO DETALLE_CITA (id_cita, id_servicio, precio_fijado) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iid", $id_cita, $id_servicio, $precio_fijado);
$stmt->execute();

// Redirigir de vuelta
header("Location: detalle_cita.php?id=$id_cita");
exit;
?>