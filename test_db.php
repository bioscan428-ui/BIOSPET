<?php
// test_db.php - BORRAR DESPUÉS DE PROBAR
require_once 'includes/conexion.php';

echo "✅ Conexión exitosa a la base de datos<br>";

$sql = "SELECT COUNT(*) as total FROM servicios";
$result = $conn->query($sql);
$row = $result->fetch_assoc();

echo "📊 Total de servicios en BD: " . $row['total'];

$conn->close();
?>