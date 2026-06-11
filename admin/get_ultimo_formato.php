<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_cliente = (int)($_GET['id_cliente'] ?? 0);
$tipo_formato = $_GET['tipo'] ?? '';

if ($id_cliente <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de cliente inválido']);
    exit;
}

if (empty($tipo_formato)) {
    echo json_encode(['success' => false, 'message' => 'Tipo de formato no especificado']);
    exit;
}

$sql = "SELECT fc.*, m.nombre_mascota, m.especie, m.raza, m.genero
        FROM FORMATO_CLIENTE fc
        LEFT JOIN MASCOTA m ON fc.id_mascota = m.id
        WHERE fc.id_cliente = ? AND fc.tipo_formato = ?
        ORDER BY fc.fecha_firma DESC
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $id_cliente, $tipo_formato);
$stmt->execute();
$result = $stmt->get_result();
$formato = $result->fetch_assoc();

if ($formato) {
    echo json_encode([
        'success' => true,
        'formato' => $formato
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No hay formato previo para este tipo'
    ]);
}
?>