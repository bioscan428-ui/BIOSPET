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
$sql = "SELECT fc.*, m.nombre_mascota, m.especie, m.raza, m.genero, m.id as mascota_id
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

$conn->begin_transaction();

try {
    $id_mascota = $formato['id_mascota'];
    
    // Si no hay mascota asociada pero hay datos, crear una nueva
    if (!$id_mascota && !empty($datos['mascota_nombre'])) {
        $sql_mascota = "INSERT INTO MASCOTA (id_cliente, nombre_mascota, especie, raza, genero) 
                        VALUES (?, ?, ?, ?, ?)";
        $stmt_masc = $conn->prepare($sql_mascota);
        $especie = $datos['mascota_especie'] ?? 'Canino';
        $raza = $datos['mascota_raza'] ?? '';
        $genero = $datos['mascota_sexo'] ?? '';
        $stmt_masc->bind_param("issss", $formato['id_cliente'], $datos['mascota_nombre'], $especie, $raza, $genero);
        $stmt_masc->execute();
        $id_mascota = $conn->insert_id;
        $stmt_masc->close();
    }
    
    // Actualizar la cita con la mascota
    if ($id_mascota) {
        $notas_adicional = "\n[Datos cargados desde formato: {$formato['tipo_formato']} el " . date('d/m/Y H:i') . "]";
        $sql_update = "UPDATE CITA SET id_mascota = ?, notas = CONCAT(IFNULL(notas, ''), ?) WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("isi", $id_mascota, $notas_adicional, $id_cita);
        $stmt_update->execute();
        $stmt_update->close();
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Datos cargados correctamente',
        'mascota_id' => $id_mascota
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>