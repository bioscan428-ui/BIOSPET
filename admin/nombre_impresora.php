<?php
// nombre_impresora.php
echo "<h1>Impresoras instaladas</h1>";
exec('wmic printer get name, portname 2>&1', $output);
echo "<pre>";
foreach ($output as $line) {
    echo htmlspecialchars($line) . "\n";
}
echo "</pre>";
?>