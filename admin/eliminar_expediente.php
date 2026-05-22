<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID no válido']);
    exit;
}

// Obtener ruta del archivo antes de eliminar
$sql = "SELECT ruta_archivo FROM EXPEDIENTE_EMPLEADO WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();

if ($doc) {
    // Eliminar archivo físico
    if (file_exists($doc['ruta_archivo'])) {
        unlink($doc['ruta_archivo']);
    }
    
    // Eliminar registro
    $sql_delete = "UPDATE EXPEDIENTE_EMPLEADO SET activo = 0 WHERE id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $id);
    
    if ($stmt_delete->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al eliminar registro']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Documento no encontrado']);
}
?>