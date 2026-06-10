<?php
require_once '../includes/conexion.php';
header('Content-Type: application/json');

$telefono = $_GET['telefono'] ?? '';

if (strlen($telefono) >= 10) {
    $sql = "SELECT id, nombre, ape_pat, ape_mat, telefono FROM CLIENTE WHERE telefono = ? AND activo = 1 LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $telefono);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    
    if ($cliente) {
        $sql_masc = "SELECT id, nombre_mascota, especie FROM MASCOTA WHERE id_cliente = ? AND activo = 1";
        $stmt_masc = $conn->prepare($sql_masc);
        $stmt_masc->bind_param("i", $cliente['id']);
        $stmt_masc->execute();
        $mascotas = $stmt_masc->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode([
            'success' => true,
            'cliente' => $cliente,
            'mascotas' => $mascotas
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Teléfono inválido']);
}