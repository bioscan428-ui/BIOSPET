<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_formato = (int)($_GET['id_formato'] ?? 0);
$id_cita = (int)($_GET['cita_id'] ?? 0);

if ($id_formato <= 0) {
    echo json_encode(['success' => false, 'message' => 'Formato no válido']);
    exit;
}

// Obtener datos del formato
$sql = "SELECT fc.*, m.nombre_mascota, m.especie, m.raza, m.genero, m.foto
        FROM FORMATO_CLIENTE fc
        LEFT JOIN MASCOTA m ON fc.id_mascota = m.id
        WHERE fc.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_formato);
$stmt->execute();
$formato = $stmt->get_result()->fetch_assoc();

if (!$formato) {
    echo json_encode(['success' => false, 'message' => 'Formato no encontrado']);
    exit;
}

$datos = json_decode($formato['datos'], true);

// Actualizar la cita con los datos del formato
$conn->begin_transaction();

try {
    // Actualizar la mascota o crear una nueva
    $id_mascota = $formato['id_mascota'];
    
    if (!$id_mascota && !empty($datos['mascota_nombre'])) {
        // Crear nueva mascota
        $sql_mascota = "INSERT INTO MASCOTA (id_cliente, nombre_mascota, especie, raza, genero) 
                        VALUES (?, ?, ?, ?, ?)";
        $stmt_masc = $conn->prepare($sql_mascota);
        $stmt_masc->bind_param("issss", $formato['id_cliente'], $datos['mascota_nombre'], $datos['mascota_especie'], $datos['mascota_raza'] ?? '', $datos['mascota_sexo'] ?? '');
        $stmt_masc->execute();
        $id_mascota = $conn->insert_id;
        $stmt_masc->close();
    }
    
    // Actualizar la cita con la mascota
    if ($id_mascota) {
        $sql_update = "UPDATE CITA SET id_mascota = ?, notas = CONCAT(IFNULL(notas, ''), ' [Datos cargados desde formato]') WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $id_mascota, $id_cita);
        $stmt_update->execute();
        $stmt_update->close();
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Datos cargados correctamente',
        'mascota_id' => $id_mascota,
        'datos' => $datos
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>