<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_cliente = (int)($_GET['id_cliente'] ?? 0);

if ($id_cliente <= 0) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT 
            fc.id,
            fc.tipo_formato,
            fc.firma_nombre,
            fc.fecha_firma,
            fc.id_empleado,
            fc.id_mascota,
            m.nombre_mascota,
            e.nombre as empleado_nombre,
            e.ape_pat as empleado_ape_pat
        FROM FORMATO_CLIENTE fc
        LEFT JOIN MASCOTA m ON fc.id_mascota = m.id
        LEFT JOIN EMPLEADO e ON fc.id_empleado = e.id
        WHERE fc.id_cliente = ?
        ORDER BY fc.fecha_firma DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_cliente);
$stmt->execute();
$result = $stmt->get_result();

$formatos = [];
while ($row = $result->fetch_assoc()) {
    $row['empleado_nombre'] = trim(($row['empleado_nombre'] ?? '') . ' ' . ($row['empleado_ape_pat'] ?? ''));
    unset($row['empleado_ape_pat']);
    $formatos[] = $row;
}

echo json_encode($formatos);
?>