<?php
// archivo: cambiar_contraseña.php (BORRAR DESPUÉS DE USAR)
require_once 'includes/conexion.php';

$usuario = 'super_admin';
$nueva_password = 'biospet2026';

$nuevo_hash = password_hash($nueva_password, PASSWORD_DEFAULT);

$sql = "UPDATE USUARIO SET contrasena = ? WHERE nombre_usuario = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $nuevo_hash, $usuario);
$stmt->execute();

echo "✅ Contraseña actualizada para '{$usuario}'";
?>