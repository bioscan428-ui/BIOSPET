<?php
// includes/enviar_email.php - VERSIÓN FUNCIONAL
function enviarEmailConfirmacion($email_cliente, $nombre_cliente, $fecha_cita, $hora_cita, $servicios, $total) {
    
    $asunto = "✅ BIOSPET - Tu cita ha sido confirmada";
    
    $mensaje = "Hola $nombre_cliente,\n\n";
    $mensaje .= "Tu cita ha sido CONFIRMADA:\n\n";
    $mensaje .= "📅 Fecha: $fecha_cita\n";
    $mensaje .= "⏰ Hora: $hora_cita\n";
    $mensaje .= "💊 Servicios: $servicios\n";
    $mensaje .= "💰 Total: $" . number_format($total, 2) . " MXN\n\n";
    $mensaje .= "Gracias por confiar en BIOSPET.\n\n";
    $mensaje .= "---\n";
    $mensaje .= "BIOSPET - Clínica Veterinaria\n";
    $mensaje .= "https://biospet.bioscan.services";
    
    $headers = "From: BIOSPET <citas@biospet.bioscan.services>\r\n";
    $headers .= "Reply-To: citas@biospet.bioscan.services\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($email_cliente, $asunto, $mensaje, $headers);
}
?>