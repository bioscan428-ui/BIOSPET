<?php
// includes/conexion.php

$host = 'localhost';
$db_name = 'BIOSPET';        // Nombre de la base de datos
$user_name = 'biospet_user';  // Usuario de MySQL
$password = 'biospet_bioscan'; // Contraseña

// Crear conexión - ORDEN CORRECTO: host, usuario, password, dbname
$conn = new mysqli($host, $user_name, $password, $db_name);

// Verificar la conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Establecer charset
$conn->set_charset("utf8");

// Opcional: mensaje silencioso (puedes comentarlo después)
// echo "✅ Conexión exitosa";
?>