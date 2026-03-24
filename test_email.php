<?php
require_once 'includes/enviar_email.php';

$resultado = enviarEmailConfirmacion(
    'tu_email_de_prueba@gmail.com',  // Cambia por tu email
    'Cliente Prueba',
    '24/03/2026',
    '15:30',
    'Tomografía, Rayos X',
    3300.00
);

if ($resultado) {
    echo "✅ Email enviado correctamente. Revisa tu bandeja de entrada (y spam).";
} else {
    echo "❌ Error al enviar email. Revisa los logs del servidor.";
}
?>