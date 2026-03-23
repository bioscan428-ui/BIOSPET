<?php
// includes/enviar_email.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config_smtp.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function enviarEmailConfirmacion($email_cliente, $nombre_cliente, $fecha_cita, $hora_cita, $servicios, $total) {
    
    $mail = new PHPMailer(true);
    
    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        // Remitente y destinatario
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($email_cliente, $nombre_cliente);
        
        // Contenido del email
        $mail->isHTML(true);
        $mail->Subject = '✅ BIOSPET - Tu cita ha sido confirmada';
        
        // Cuerpo del mensaje (HTML)
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px; border-radius: 10px; }
                .header { background: #E68A00; color: white; text-align: center; padding: 20px; border-radius: 10px 10px 0 0; }
                .content { padding: 20px; }
                .btn { background: #E68A00; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; }
                .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🐾 BIOSPET</h2>
                    <p>Clínica Veterinaria</p>
                </div>
                <div class='content'>
                    <h3>Hola $nombre_cliente,</h3>
                    <p>Tu cita ha sido <strong>CONFIRMADA</strong>. A continuación los detalles:</p>
                    
                    <div style='background: white; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                        <p><strong>📅 Fecha:</strong> $fecha_cita</p>
                        <p><strong>⏰ Hora:</strong> $hora_cita</p>
                        <p><strong>💊 Servicios:</strong> $servicios</p>
                        <p><strong>💰 Total:</strong> $" . number_format($total, 2) . " MXN</p>
                    </div>
                    
                    <p>Por favor, llega 10 minutos antes para realizar los trámites de ingreso.</p>
                    <p>Si necesitas cambiar o cancelar tu cita, comunícate con nosotros al (123) 456-7890.</p>
                    
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='https://biospet.bioscan.services' class='btn'>Visitar sitio web</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " BIOSPET - Diagnóstico Veterinario de Precisión</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Texto alternativo (para clientes que no soportan HTML)
        $mail->AltBody = "Hola $nombre_cliente,\n\nTu cita ha sido CONFIRMADA para el $fecha_cita a las $hora_cita.\n\nServicios: $servicios\nTotal: $" . number_format($total, 2) . " MXN\n\nBIOSPET - Clínica Veterinaria";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        // Guardar error en log (opcional)
        error_log("Error al enviar email: " . $mail->ErrorInfo);
        return false;
    }
}
?>