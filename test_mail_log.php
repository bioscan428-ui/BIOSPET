<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$to = "i18221889.19@puebla.tecnm.mx";
$subject = "Prueba BIOSPET - Verificación";
$message = "Este es un correo de prueba desde BIOSPET.\n\nSi recibes esto, el sistema funciona.";
$headers = "From: citas@biospet.bioscan.services\r\n";
$headers .= "Reply-To: citas@biospet.bioscan.services\r\n";

// Intentar enviar
$resultado = mail($to, $subject, $message, $headers);

if ($resultado) {
    echo "✅ El servidor ACEPTÓ el envío a $to<br>";
    echo "ℹ️ Pero puede que el correo haya sido filtrado como spam o bloqueado.<br>";
    echo "🔍 Revisa:<br>";
    echo "- Carpeta de SPAM en $to<br>";
    echo "- Que el correo citas@biospet.bioscan.services exista y tenga contraseña correcta<br>";
    echo "- Configuración SPF/DKIM en GoDaddy<br>";
} else {
    echo "❌ El servidor NO pudo enviar el correo";
}

// Mostrar información del servidor
echo "<br><br>📡 Información del servidor:<br>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Servidor: " . $_SERVER['SERVER_NAME'] . "<br>";
?>