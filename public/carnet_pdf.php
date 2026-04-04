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

// ========== CARGAR DOMPDF ==========
// vendor está dentro de biospet/
require_once __DIR__ . '/biospet/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Verificar que dompdf se cargó
if (!class_exists('Dompdf\Dompdf')) {
    die("dompdf no se pudo cargar. Verifica que vendor/autoload.php existe.");
}

// ========== Conexión a la BD ==========
// conexion.php está en includes/ (raíz)
require_once __DIR__ . '/includes/conexion.php';

$id_mascota = (int)($_GET['id'] ?? 0);
if (!$id_mascota) {
    die("Mascota no especificada");
}

// Obtener datos de la mascota
$sql = "SELECT 
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
        WHERE m.id = ?";
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

// Ruta de la foto
$foto_url = !empty($mascota['foto']) && file_exists(__DIR__ . '/biospet/' . $mascota['foto']) 
    ? 'biospet/' . $mascota['foto'] 
    : 'biospet/assets/images/default-pet.png';

// Generar HTML
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Carnet de ' . htmlspecialchars($mascota['nombre_mascota']) . '</title>
    <style>
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
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
        </div>
        <div class="foto">
            <img src="' . $foto_url . '" alt="' . htmlspecialchars($mascota['nombre_mascota']) . '">
        </div>
        <div class="info">
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
            </div>
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
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Descargar el PDF
$dompdf->stream('carnet_' . $mascota['nombre_mascota'] . '.pdf', array('Attachment' => true));
exit;
?>