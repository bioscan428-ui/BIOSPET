<?php
$to = "bioscan428@gmail.com"; // CAMBIA POR TU CORREO
$subject = "Prueba de correo BIOSPET";
$message = "Este es un correo de prueba desde BIOSPET. Si lo recibes, mail() funciona.";
$headers = "From: no-reply@biospet.bioscan.services\r\n";
$headers .= "Reply-To: citas@biospet.bioscan.services\r\n";

if (mail($to, $subject, $message, $headers)) {
    echo "✅ Correo enviado correctamente";
} else {
    echo "❌ Error al enviar correo";
}
?>