<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Diagnóstico de vendor</h2>";

// 1. Verificar que autoload.php existe
$ruta_autoload = __DIR__ . '/biospet/vendor/autoload.php';
echo "Buscando autoload en: " . $ruta_autoload . "<br>";

if (file_exists($ruta_autoload)) {
    echo "✅ autoload.php encontrado<br>";
    require_once $ruta_autoload;
    echo "✅ autoload.php cargado<br><br>";
    
    // 2. Verificar si la clase Dompdf existe
    if (class_exists('Dompdf\Dompdf')) {
        echo "✅ Clase Dompdf encontrada<br>";
    } else {
        echo "❌ Clase Dompdf NO encontrada<br>";
    }
    
    if (class_exists('Dompdf\Options')) {
        echo "✅ Clase Options encontrada<br>";
    } else {
        echo "❌ Clase Options NO encontrada<br>";
    }
    
    // 3. Listar clases de Dompdf cargadas
    $clases = get_declared_classes();
    $clases_dompdf = array_filter($clases, function($c) {
        return strpos($c, 'Dompdf') !== false;
    });
    
    if (!empty($clases_dompdf)) {
        echo "<br>📚 Clases de Dompdf cargadas:<br>";
        foreach($clases_dompdf as $clase) {
            echo "- $clase<br>";
        }
    } else {
        echo "<br>❌ No hay clases de Dompdf cargadas<br>";
    }
    
} else {
    echo "❌ autoload.php NO encontrado<br>";
    echo "Contenido de la carpeta biospet/vendor:<br>";
    if (is_dir(__DIR__ . '/biospet/vendor')) {
        $archivos = scandir(__DIR__ . '/biospet/vendor');
        foreach($archivos as $archivo) {
            if ($archivo != '.' && $archivo != '..') {
                echo "- $archivo<br>";
            }
        }
    } else {
        echo "La carpeta biospet/vendor no existe";
    }
}
?>