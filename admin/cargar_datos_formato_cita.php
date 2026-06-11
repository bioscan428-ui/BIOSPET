<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$data = json_decode(file_get_contents('php://input'), true);

$id_cita = (int)($data['id_cita'] ?? 0);
$id_formato = (int)($data['id_formato'] ?? 0);
$tipo_formato = $data['tipo_formato'] ?? '';
$datos = $data['datos'] ?? [];

if ($id_cita <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de cita inválido']);
    exit;
}

$conn->begin_transaction();

try {
    // Obtener la cita actual
    $sql_cita = "SELECT c.id_mascota, m.id_cliente 
                 FROM CITA c
                 JOIN MASCOTA m ON c.id_mascota = m.id
                 WHERE c.id = ?";
    $stmt = $conn->prepare($sql_cita);
    $stmt->bind_param("i", $id_cita);
    $stmt->execute();
    $cita = $stmt->get_result()->fetch_assoc();
    
    if (!$cita) {
        throw new Exception('Cita no encontrada');
    }
    
    $id_cliente = $cita['id_cliente'];
    $id_mascota_actual = $cita['id_mascota'];
    
    // Verificar si los datos de la mascota coinciden o actualizar
    $nombre_mascota = $datos['mascota_nombre'] ?? '';
    $especie = $datos['mascota_especie'] ?? 'Canino';
    $raza = $datos['mascota_raza'] ?? '';
    $genero = $datos['mascota_sexo'] ?? '';
    $peso = $datos['mascota_peso'] ?? null;
    
    if (!empty($nombre_mascota)) {
        // Buscar si ya existe una mascota con ese nombre para este cliente
        $sql_buscar = "SELECT id FROM MASCOTA WHERE id_cliente = ? AND nombre_mascota = ? AND activo = 1";
        $stmt_buscar = $conn->prepare($sql_buscar);
        $stmt_buscar->bind_param("is", $id_cliente, $nombre_mascota);
        $stmt_buscar->execute();
        $mascota_existente = $stmt_buscar->get_result()->fetch_assoc();
        
        if ($mascota_existente) {
            $id_mascota = $mascota_existente['id'];
        } else {
            // Crear nueva mascota
            $sql_insert = "INSERT INTO MASCOTA (id_cliente, nombre_mascota, especie, raza, genero) 
                           VALUES (?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("issss", $id_cliente, $nombre_mascota, $especie, $raza, $genero);
            $stmt_insert->execute();
            $id_mascota = $conn->insert_id;
        }
        
        // Actualizar la cita con la nueva mascota
        $sql_update = "UPDATE CITA SET id_mascota = ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $id_mascota, $id_cita);
        $stmt_update->execute();
        
        // Agregar nota en la cita
        $nota_adicional = "\n[Datos cargados desde formato: {$tipo_formato} el " . date('Y-m-d H:i') . "]";
        $sql_nota = "UPDATE CITA SET notas = CONCAT(IFNULL(notas, ''), ?) WHERE id = ?";
        $stmt_nota = $conn->prepare($sql_nota);
        $stmt_nota->bind_param("si", $nota_adicional, $id_cita);
        $stmt_nota->execute();
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Datos cargados correctamente'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>