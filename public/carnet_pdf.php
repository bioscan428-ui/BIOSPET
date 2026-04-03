<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();


// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Roles que pueden ver el carnet
if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'veterinario', 'asistente', 'recepcionista'])) {
    die("No tienes permisos para ver esta página");
}

require_once __DIR__ . '/includes/conexion.php';

// CORREGIDO: Ruta correcta a vendor (dentro de la carpeta biospet)
// Como carnet_pdf.php está dentro de biospet/, vendor está al mismo nivel
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$id_mascota = (int)($_GET['id'] ?? 0);
if (!$id_mascota) {
    die("Mascota no especificada");
}

// Obtener datos de la mascota y su dueño
$sql = "SELECT 
            m.id,
            m.nombre_mascota,
            m.especie,
            m.raza,
            m.genero,
            m.fecha_nacimiento,
            m.foto,
            cl.nombre AS nombre_dueno,
            cl.ape_pat,
            cl.ape_mat,
            cl.telefono,
            cl.email
        FROM MASCOTA m
        JOIN CLIENTE cl ON m.id_cliente = cl.id
        WHERE m.id = ? AND m.activo = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_mascota);
$stmt->execute();
$result = $stmt->get_result();
$mascota = $result->fetch_assoc();

if (!$mascota) {
    die("Mascota no encontrada");
}

// Calcular edad
$edad = null;
if ($mascota['fecha_nacimiento']) {
    $fecha_nac = new DateTime($mascota['fecha_nacimiento']);
    $hoy = new DateTime();
    $edad = $fecha_nac->diff($hoy)->y;
}

// Obtener historial de citas (últimas 5)
$sql_citas = "SELECT 
                c.fecha_cita,
                c.estado,
                GROUP_CONCAT(s.nombre_servicio SEPARATOR ', ') AS servicios
              FROM CITA c
              JOIN DETALLE_CITA dc ON c.id = dc.id_cita
              JOIN SERVICIO s ON dc.id_servicio = s.id
              WHERE c.id_mascota = ?
              GROUP BY c.id
              ORDER BY c.fecha_cita DESC
              LIMIT 5";
$stmt_citas = $conn->prepare($sql_citas);
$stmt_citas->bind_param("i", $id_mascota);
$stmt_citas->execute();
$historial = $stmt_citas->get_result();

// Obtener próxima cita
$sql_proxima = "SELECT fecha_cita, hora_cita, estado
                FROM CITA
                WHERE id_mascota = ? AND fecha_cita >= CURDATE() AND estado IN ('pendiente', 'confirmada')
                ORDER BY fecha_cita ASC
                LIMIT 1";
$stmt_proxima = $conn->prepare($sql_proxima);
$stmt_proxima->bind_param("i", $id_mascota);
$stmt_proxima->execute();
$proxima = $stmt_proxima->get_result()->fetch_assoc();

// CORREGIDO: Ruta correcta para la foto
$foto_url = !empty($mascota['foto']) && file_exists(__DIR__ . '/' . $mascota['foto']) ? $mascota['foto'] : 'assets/images/default-pet.png';

// Generar HTML para el PDF
$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carnet de ' . htmlspecialchars($mascota['nombre_mascota']) . '</title>
    <style>
        body {
            font-family: "DejaVu Sans", sans-serif;
            margin: 0;
            padding: 20px;
        }
        .carnet {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .carnet-header {
            background: #E68A00;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .carnet-header h1 {
            margin: 0;
            font-size: 1.8rem;
        }
        .carnet-header p {
            margin: 5px 0 0;
            opacity: 0.9;
        }
        .foto {
            text-align: center;
            padding: 20px;
            background: #f9f9f9;
            border-bottom: 1px solid #eee;
        }
        .foto img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #E68A00;
        }
        .info {
            padding: 20px;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-section h3 {
            color: #E68A00;
            border-bottom: 2px solid #E68A00;
            padding-bottom: 8px;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        .info-row {
            display: flex;
            margin-bottom: 8px;
        }
        .info-label {
            width: 100px;
            font-weight: bold;
            color: #555;
        }
        .info-value {
            flex: 1;
            color: #333;
        }
        .proxima-cita {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 10px;
            border-radius: 8px;
            margin-top: 15px;
            font-size: 12px;
        }
        .historial-item {
            background: #f5f5f5;
            padding: 8px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 12px;
        }
        .historial-fecha {
            font-weight: bold;
            color: #E68A00;
        }
        .footer {
            background: #f0f2f5;
            text-align: center;
            padding: 10px;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="carnet">
        <div class="carnet-header">
            <h1>🐾 BIOSPET</h1>
            <p>Carnet de Identidad Veterinaria</p>
        </div>';

// Foto (corregido para que funcione con dompdf)
$html .= '<div class="foto">
            <img src="' . $foto_url . '" alt="' . htmlspecialchars($mascota['nombre_mascota']) . '">
          </div>';

$html .= '<div class="info">
            <div class="info-section">
                <h3>📋 Datos de la Mascota</h3>
                <div class="info-row"><div class="info-label">Nombre:</div><div class="info-value">' . htmlspecialchars($mascota['nombre_mascota']) . '</div></div>
                <div class="info-row"><div class="info-label">Especie:</div><div class="info-value">' . $mascota['especie'] . '</div></div>
                <div class="info-row"><div class="info-label">Raza:</div><div class="info-value">' . ($mascota['raza'] ?: 'No especificada') . '</div></div>
                <div class="info-row"><div class="info-label">Género:</div><div class="info-value">' . ($mascota['genero'] == 'MACHO' ? '♂️ Macho' : ($mascota['genero'] == 'HEMBRA' ? '♀️ Hembra' : 'No registrado')) . '</div></div>
                <div class="info-row"><div class="info-label">Edad:</div><div class="info-value">' . ($edad !== null ? $edad . ' años' : 'No registrada') . '</div></div>
            </div>

            <div class="info-section">
                <h3>👤 Dueño</h3>
                <div class="info-row"><div class="info-label">Nombre:</div><div class="info-value">' . htmlspecialchars($mascota['nombre_dueno'] . ' ' . $mascota['ape_pat'] . ' ' . $mascota['ape_mat']) . '</div></div>
                <div class="info-row"><div class="info-label">Teléfono:</div><div class="info-value">' . $mascota['telefono'] . '</div></div>
                <div class="info-row"><div class="info-label">Email:</div><div class="info-value">' . ($mascota['email'] ?: 'No registrado') . '</div></div>
            </div>';

if ($proxima) {
    $html .= '<div class="info-section">
                <h3>📅 Próxima Cita</h3>
                <div class="proxima-cita">
                    📆 Fecha: ' . date('d/m/Y', strtotime($proxima['fecha_cita'])) . '<br>
                    ⏰ Hora: ' . $proxima['hora_cita'] . '<br>
                    📌 Estado: ' . ucfirst($proxima['estado']) . '
                </div>
              </div>';
}

$html .= '<div class="info-section">
            <h3>📜 Historial de Citas</h3>';
if ($historial->num_rows > 0) {
    while ($cita = $historial->fetch_assoc()) {
        $html .= '<div class="historial-item">
                    <div class="historial-fecha">' . date('d/m/Y', strtotime($cita['fecha_cita'])) . '</div>
                    <div>' . $cita['servicios'] . '</div>
                    <div style="font-size: 10px; color: #666;">Estado: ' . ucfirst($cita['estado']) . '</div>
                  </div>';
    }
} else {
    $html .= '<p style="color: #999;">No hay citas registradas</p>';
}

$html .= '</div>
        </div>
        <div class="footer">
            <p>Documento generado el ' . date('d/m/Y H:i:s') . '</p>
            <p>BIOSPET - Clínica Veterinaria</p>
        </div>
    </div>
</body>
</html>';

// Configurar dompdf
$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', true); // Permitir imágenes remotas

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Descargar el PDF
$dompdf->stream('carnet_' . $mascota['nombre_mascota'] . '.pdf', array('Attachment' => true));
exit;
?>