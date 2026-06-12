<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_cita = (int)($_GET['id_cita'] ?? 0);

if ($id_cita <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de cita inválido']);
    exit;
}

// Agregar c.id_mascota a la consulta
$sql = "SELECT c.id, m.id_cliente as cliente_id, c.id_mascota as mascota_id
        FROM CITA c
        JOIN MASCOTA m ON c.id_mascota = m.id
        WHERE c.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_cita);
$stmt->execute();
$result = $stmt->get_result();
$cita = $result->fetch_assoc();

if (!$cita) {
    echo json_encode(['success' => false, 'message' => 'Cita no encontrada']);
    exit;
}

echo json_encode([
    'success' => true,
    'cliente_id' => $cita['cliente_id'],
    'mascota_id' => $cita['mascota_id']  // ← Agregar esta línea
]);
?>