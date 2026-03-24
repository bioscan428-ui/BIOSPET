<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$to = "i18221889.19@puebla.tecnm.mx";
$subject = "Prueba BIOSPET - con remitente existente";
$message = "Hola, este es un correo de prueba desde BIOSPET usando el remitente citas@biospet.bioscan.services\n\nSi recibes esto, el sistema de correo funciona correctamente.";
$headers = "From: BIOSPET <citas@biospet.bioscan.services>\r\n";
$headers .= "Reply-To: citas@biospet.bioscan.services\r\n";

if (mail($to, $subject, $message, $headers)) {
    echo "✅ Email enviado correctamente usando mail() con remitente citas@biospet.bioscan.services. Revisa tu bandeja (y spam).";
} else {
    echo "❌ Error: mail() no pudo enviar el email.";
}
?>