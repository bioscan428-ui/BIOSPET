<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['super_admin', 'admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_cita = (int)($_POST['id_cita'] ?? 0);
$id_asistente = (int)($_POST['id_asistente'] ?? 0);

if (!$id_cita || !$id_asistente) {
    die("Datos incompletos");
}

// Verificar que la cita existe
$sql_check = "SELECT estado FROM CITA WHERE id = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("i", $id_cita);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$cita = $result_check->fetch_assoc();

if (!$cita) {
    die("Cita no encontrada");
}

// Verificar que el empleado existe y es asistente
$sql_emp = "SELECT id FROM EMPLEADO WHERE id = ? AND puesto = 'asistente' AND activo = 1";
$stmt_emp = $conn->prepare($sql_emp);
$stmt_emp->bind_param("i", $id_asistente);
$stmt_emp->execute();
$result_emp = $stmt_emp->get_result();

if ($result_emp->num_rows == 0) {
    die("Asistente no válido");
}

// Verificar si ya existe asignación de asistente
$sql_check_asig = "SELECT id FROM ASIGNACION_CITA WHERE id_cita = ? AND rol_asignado = 'asistente'";
$stmt_asig = $conn->prepare($sql_check_asig);
$stmt_asig->bind_param("i", $id_cita);
$stmt_asig->execute();
$result_asig = $stmt_asig->get_result();

if ($result_asig->num_rows > 0) {
    // Actualizar asignación existente
    $sql = "UPDATE ASIGNACION_CITA SET id_empleado = ? WHERE id_cita = ? AND rol_asignado = 'asistente'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id_asistente, $id_cita);
} else {
    // Crear nueva asignación
    $sql = "INSERT INTO ASIGNACION_CITA (id_cita, id_empleado, rol_asignado) VALUES (?, ?, 'asistente')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id_cita, $id_asistente);
}

$stmt->execute();

header("Location: detalle_cita.php?id=$id_cita");
exit;
?>