<?php
// public/controllers/buscar_mascotas_ajax.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/conexion.php';

$telefono = $_GET['telefono'] ?? '';

if (strlen($telefono) >= 10) {
    $sql = "SELECT m.id, m.nombre_mascota, m.especie, m.raza 
            FROM MASCOTA m
            JOIN CLIENTE c ON m.id_cliente = c.id
            WHERE c.telefono = ? AND m.activo = 1
            ORDER BY m.nombre_mascota";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $telefono);
    $stmt->execute();
    $result = $stmt->get_result();
    $mascotas = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($mascotas);
} else {
    echo json_encode([]);
}
?>