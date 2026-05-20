<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Verificando estructura de PhpSpreadsheet</h2>";

// Ruta corregida - incluye la carpeta biospet
$base = __DIR__ . '/biospet/vendor/phpoffice/PhpSpreadsheet/';
echo "Buscando en: " . $base . "<br><br>";

if (is_dir($base)) {
    echo "✅ Carpeta PhpSpreadsheet encontrada<br>";
    
    // Verificar contenido
    $contenido = scandir($base);
    echo "Contenido de PhpSpreadsheet:<br>";
    foreach($contenido as $item) {
        if ($item != '.' && $item != '..') {
            echo "- $item<br>";
        }
    }
    
    // Verificar el autoloader
    $autoload_path = __DIR__ . '/biospet/vendor/autoload.php';
    if (file_exists($autoload_path)) {
        require_once $autoload_path;
        
        if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            echo "<br>✅ Clase PhpOffice\\PhpSpreadsheet\\Spreadsheet encontrada!<br>";
            echo "✅ PhpSpreadsheet está listo para usar.<br>";
        } else {
            echo "<br>❌ Clase no encontrada. Verifica el autoloading.<br>";
        }
    } else {
        echo "<br>❌ No se encontró autoload.php en: " . $autoload_path;
    }
    
} else {
    echo "❌ Carpeta PhpSpreadsheet NO encontrada en " . $base;
    
    // Buscar en otras ubicaciones posibles
    $rutas_posibles = [
        __DIR__ . '/vendor/phpoffice/PhpSpreadsheet/',
        __DIR__ . '/../vendor/phpoffice/PhpSpreadsheet/',
        __DIR__ . '/biospet/vendor/PhpSpreadsheet/',
        __DIR__ . '/vendor/PhpSpreadsheet/'
    ];
    
    echo "<br><br>Buscando en rutas alternativas:<br>";
    foreach($rutas_posibles as $ruta) {
        if (is_dir($ruta)) {
            echo "✅ Encontrado en: $ruta<br>";
        } else {
            echo "❌ No encontrado: $ruta<br>";
        }
    }
}
?>