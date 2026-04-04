<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Verificando estructura de dompdf</h2>";

$base = __DIR__ . '/biospet/vendor/dompdf/';
echo "Buscando en: " . $base . "<br><br>";

if (is_dir($base)) {
    echo "✅ Carpeta dompdf encontrada<br>";
    
    // Verificar contenido de dompdf
    $contenido = scandir($base);
    echo "Contenido de dompdf:<br>";
    foreach($contenido as $item) {
        if ($item != '.' && $item != '..') {
            echo "- $item<br>";
        }
    }
    
    // Verificar si existe src/Dompdf.php
    $archivo_dompdf = $base . 'src/Dompdf.php';
    if (file_exists($archivo_dompdf)) {
        echo "<br>✅ src/Dompdf.php existe<br>";
    } else {
        echo "<br>❌ src/Dompdf.php NO existe<br>";
        // Buscar en otras ubicaciones
        $alternativas = [
            $base . 'Dompdf.php',
            $base . 'dompdf.php',
            $base . 'src/Dompdf/Dompdf.php'
        ];
        foreach($alternativas as $alt) {
            if (file_exists($alt)) {
                echo "✅ Alternativa encontrada: $alt<br>";
            }
        }
    }
    
} else {
    echo "❌ Carpeta dompdf NO encontrada en " . $base;
}
?>