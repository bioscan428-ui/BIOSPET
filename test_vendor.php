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
    
    // 2. Verificar Dompdf
    echo "<h3>📄 Dompdf</h3>";
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
    
    // 3. Verificar PhpSpreadsheet
    echo "<h3>📊 PhpSpreadsheet</h3>";
    if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        echo "✅ Clase PhpOffice\\PhpSpreadsheet\\Spreadsheet encontrada<br>";
        
        // Probar crear una instancia
        try {
            $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
            echo "✅ Se pudo crear una instancia de Spreadsheet<br>";
            echo "✅ PhpSpreadsheet está funcionando correctamente!<br>";
        } catch (Exception $e) {
            echo "❌ Error al crear instancia: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "❌ Clase PhpOffice\\PhpSpreadsheet\\Spreadsheet NO encontrada<br>";
    }
    
    if (class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
        echo "✅ Clase PhpOffice\\PhpSpreadsheet\\IOFactory encontrada<br>";
    } else {
        echo "❌ Clase PhpOffice\\PhpSpreadsheet\\IOFactory NO encontrada<br>";
    }
    
    // 4. Listar todas las clases de PhpOffice cargadas
    $clases = get_declared_classes();
    $clases_phpoffice = array_filter($clases, function($c) {
        return strpos($c, 'PhpOffice') !== false;
    });
    
    if (!empty($clases_phpoffice)) {
        echo "<br>📚 Clases de PhpOffice cargadas:<br>";
        foreach($clases_phpoffice as $clase) {
            echo "- $clase<br>";
        }
    } else {
        echo "<br>❌ No hay clases de PhpOffice cargadas<br>";
    }
    
    // 5. Verificar la estructura de carpetas
    echo "<h3>📁 Estructura de vendor</h3>";
    $vendor_path = __DIR__ . '/biospet/vendor';
    if (is_dir($vendor_path)) {
        echo "Contenido de $vendor_path:<br>";
        $archivos = scandir($vendor_path);
        foreach($archivos as $archivo) {
            if ($archivo != '.' && $archivo != '..') {
                if (is_dir($vendor_path . '/' . $archivo)) {
                    echo "📁 $archivo/<br>";
                    // Listar subcarpetas de phpoffice
                    if ($archivo == 'phpoffice') {
                        $subdir = $vendor_path . '/phpoffice';
                        $subarchivos = scandir($subdir);
                        foreach($subarchivos as $sub) {
                            if ($sub != '.' && $sub != '..') {
                                echo "   📁 phpoffice/$sub/<br>";
                                // Listar contenido de PhpSpreadsheet
                                if ($sub == 'PhpSpreadsheet') {
                                    $subsubdir = $subdir . '/PhpSpreadsheet';
                                    $subsubarchivos = scandir($subsubdir);
                                    foreach($subsubarchivos as $subsub) {
                                        if ($subsub != '.' && $subsub != '..') {
                                            if (is_dir($subsubdir . '/' . $subsub)) {
                                                echo "      📁 $subsub/<br>";
                                            } else {
                                                echo "      📄 $subsub<br>";
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else {
                    echo "📄 $archivo<br>";
                }
            }
        }
    } else {
        echo "❌ La carpeta vendor no existe en: $vendor_path";
    }
    
} else {
    echo "❌ autoload.php NO encontrado<br>";
}
?>