<?php
// listar_archivos.php
$admin_dir = __DIR__ . '/admin/';
echo "<h2>Archivos en admin/:</h2>";
if (is_dir($admin_dir)) {
    $archivos = scandir($admin_dir);
    echo "<ul>";
    foreach ($archivos as $archivo) {
        if ($archivo != '.' && $archivo != '..' && !is_dir($admin_dir . $archivo)) {
            echo "<li>$archivo</li>";
        }
    }
    echo "</ul>";
} else {
    echo "La carpeta admin/ no existe";
}
?>