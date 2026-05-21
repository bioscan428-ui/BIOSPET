<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!in_array($_SESSION['rol'], ['super_admin', 'admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_cita = (int)$_POST['id_cita'] ?? 0;
$id_groomer = (int)$_POST['id_groomer'] ?? 0;

if (!$id_cita || !$id_groomer) {
    $_SESSION['error'] = 'Datos incompletos';
    header("Location: detalle_cita.php?id=$id_cita");
    exit;
}

// Verificar si ya existe una asignación de grooming
$sql_check = "SELECT id FROM ASIGNACION_CITA WHERE id_cita = ? AND rol_asignado = 'grooming'";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("i", $id_cita);
$stmt_check->execute();
$existing = $stmt_check->get_result()->fetch_assoc();

if ($existing) {
    // Actualizar groomer existente
    $sql_update = "UPDATE ASIGNACION_CITA SET id_empleado = ? WHERE id_cita = ? AND rol_asignado = 'grooming'";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ii", $id_groomer, $id_cita);
    $stmt_update->execute();
    $mensaje = "Groomer actualizado correctamente";
} else {
    // Insertar nueva asignación
    $sql_insert = "INSERT INTO ASIGNACION_CITA (id_cita, id_empleado, rol_asignado) VALUES (?, ?, 'grooming')";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("ii", $id_cita, $id_groomer);
    $stmt_insert->execute();
    $mensaje = "Groomer asignado correctamente";
}

$_SESSION['mensaje'] = $mensaje;
header("Location: detalle_cita.php?id=$id_cita");
exit;
?>