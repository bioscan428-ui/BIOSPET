<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de autoloader de Composer</h2>";

// Cargar autoloader
require_once __DIR__ . '/biospet/vendor/autoload.php';
echo "✅ autoloader cargado<br><br>";

// Verificar clases disponibles
$clases = get_declared_classes();
$clases_dompdf = array_filter($clases, function($c) {
    return strpos($c, 'Dompdf') !== false;
});

echo "Clases de Dompdf encontradas: " . count($clases_dompdf) . "<br>";
foreach($clases_dompdf as $clase) {
    echo "- $clase<br>";
}

if (!class_exists('Dompdf\Dompdf')) {
    echo "<br>❌ Dompdf\Dompdf NO está disponible<br>";
    
    // Verificar si el archivo físico existe
    $archivo = __DIR__ . '/biospet/vendor/dompdf/dompdf/src/Dompdf.php';
    echo "<br>Verificando archivo: " . $archivo . "<br>";
    if (file_exists($archivo)) {
        echo "✅ El archivo Dompdf.php existe<br>";
        // Intentar incluirlo manualmente
        require_once $archivo;
        if (class_exists('Dompdf\Dompdf')) {
            echo "✅ Clase cargada manualmente<br>";
        }
    } else {
        echo "❌ El archivo Dompdf.php NO existe en esa ubicación<br>";
        // Buscar en otras ubicaciones
        $rutas = [
            __DIR__ . '/biospet/vendor/dompdf/src/Dompdf.php',
            __DIR__ . '/vendor/dompdf/dompdf/src/Dompdf.php',
            __DIR__ . '/vendor/dompdf/src/Dompdf.php',
        ];
        foreach($rutas as $ruta) {
            if (file_exists($ruta)) {
                echo "✅ Encontrado en: $ruta<br>";
            }
        }
    }
}
?>