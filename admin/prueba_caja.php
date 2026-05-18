<?php
// prueba_caja.php - Para diagnosticar tu caja Gprinter

echo "<h1>🔧 Prueba de Caja Registradora Gprinter</h1>";

// Comando estándar ESC/POS para abrir caja. Funciona en la mayoría de las Gprinter
$cmd_abrir_caja = chr(27) . chr(112) . chr(0) . chr(50) . chr(250);

// Creamos una lista de TODOS los posibles destinos a probar
$destinos = [];

// 1. Todos los puertos COM posibles
for ($i = 1; $i <= 16; $i++) {
    $destinos[] = "COM" . $i;
}

// 2. Puertos paralelos (más raros, pero por si acaso)
$destinos[] = "LPT1";
$destinos[] = "LPT2";

// 3. Posibles nombres para la impresora compartida en red
$destinos[] = "\\\\localhost\\POS";
$destinos[] = "\\\\localhost\\Gprinter";
$destinos[] = "\\\\localhost\\Gp58";
$destinos[] = "\\\\localhost\\EPSON";

// 4. Un archivo temporal para verificar que el comando es válido
$archivo_prueba = __DIR__ . "/test_caja.txt";
$destinos[] = $archivo_prueba;

$exito = false;
$destino_exitoso = null;

foreach ($destinos as $destino) {
    // Si ya encontramos uno, salimos del bucle
    if ($exito) {
        break;
    }
    
    echo "<p><strong>Probando:</strong> '$destino' ... ";

    // Intentamos escribir en este destino
    if (strpos($destino, '.txt') !== false) {
        // Es un archivo, intentamos escribirlo
        if (@file_put_contents($destino, $cmd_abrir_caja)) {
            echo "✅ Conexión exitosa (archivo de prueba creado). El comando es válido.</p>";
            $exito = true;
            $destino_exitoso = $destino;
        } else {
            echo "❌ No se pudo crear/abrir el archivo.</p>";
        }
    } else {
        // Es un puerto o impresora
        // Primero verificamos si el puerto existe
        $handle = @fopen($destino, "w");
        if ($handle !== false) {
            echo "✅ ¡Puerto/Impresora ENCONTRADO! Se intentará abrir la caja... ";
            
            $bytes_escritos = fwrite($handle, $cmd_abrir_caja);
            fclose($handle);
            
            if ($bytes_escritos > 0) {
                echo "✅ Comando enviado. ¿Se abrió la caja?<br>";
                echo "   ✅ <strong>¡Si la caja se abrió, hemos terminado! El destino correcto es: <code style='background:yellow'>$destino</code></strong></p>";
                $exito = true;
                $destino_exitoso = $destino;
            } else {
                echo "❌ No se pudo escribir en el puerto.</p>";
            }
        } else {
            echo "❌ No encontrado o no se puede abrir.</p>";
        }
    }
}

if (!$exito) {
    echo "<p style='color:red;'><strong>❌ No se encontró ningún puerto de impresora activo.</strong></p>";
    echo "<hr><h3>🖨️ Impresoras instaladas en tu sistema:</h3>";
    echo "<pre>";
    // Comando más compatible para listar impresoras
    exec('wmic printer get name, portname, sharename 2>&1', $output);
    if (empty($output)) {
        // Si wmic no funciona, probamos con PowerShell
        exec('powershell "Get-Printer | Select-Object Name, PortName" 2>&1', $output);
    }
    foreach ($output as $line) {
        echo htmlspecialchars($line) . "\n";
    }
    echo "</pre>";

    echo "<p><strong>👉 Acción requerida:</strong> Asegúrate de que la impresora/caja esté encendida y correctamente conectada a la computadora.</p>";
    
    // Si encontramos el archivo de prueba, damos una sugerencia adicional
    if (file_exists($archivo_prueba)) {
        echo "<p><strong>💡 Nota:</strong> El comando funciona (se pudo escribir en el archivo de prueba), pero tu sistema no detecta un puerto de impresora. Esto es normal si tu caja está conectada por USB y no está instalada como impresora genérica. En ese caso, busca el nombre de tu impresora Gprinter en la lista de arriba.</p>";
        unlink($archivo_prueba); // Limpiamos el archivo de prueba
    }
}

// Si encontramos el destino exitoso, lo mostramos
if ($destino_exitoso) {
    echo "<hr>";
    echo "<h2>✅ Configuración encontrada</h2>";
    echo "<p>El destino correcto para tu caja es: <strong style='background:yellow; padding:5px;'>$destino_exitoso</strong></p>";
    echo "<p>En tu archivo <code>procesar_venta.php</code>, debes configurar:<br>";
    echo "<code>\$impresora = \"$destino_exitoso\";</code></p>";
}
?>