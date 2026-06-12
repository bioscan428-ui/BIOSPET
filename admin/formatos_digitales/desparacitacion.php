<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../../includes/conexion.php';

// Obtener parámetros de la URL
$id_cliente = isset($_GET['id_cliente']) ? (int)$_GET['id_cliente'] : 0;
$id_mascota = isset($_GET['id_mascota']) ? (int)$_GET['id_mascota'] : 0;

// Variables para precargar
$nombre_propietario = '';
$telefono = '';
$nombre_mascota = '';
$especie = 'Canino';
$raza = '';
$color = '';
$sexo = '';
$peso = '';
$edad = '';

// Cargar datos del cliente
if ($id_cliente > 0) {
    $sql_cliente = "SELECT nombre, ape_pat, ape_mat, telefono FROM CLIENTE WHERE id = ?";
    $stmt = $conn->prepare($sql_cliente);
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    
    if ($cliente) {
        $nombre_propietario = trim($cliente['nombre'] . ' ' . ($cliente['ape_pat'] ?? '') . ' ' . ($cliente['ape_mat'] ?? ''));
        $telefono = $cliente['telefono'] ?? '';
    }
}

// Cargar datos de la mascota
if ($id_mascota > 0) {
    $sql_mascota = "SELECT nombre_mascota, especie, raza, genero FROM MASCOTA WHERE id = ?";
    $stmt = $conn->prepare($sql_mascota);
    $stmt->bind_param("i", $id_mascota);
    $stmt->execute();
    $mascota = $stmt->get_result()->fetch_assoc();
    
    if ($mascota) {
        $nombre_mascota = $mascota['nombre_mascota'] ?? '';
        $especie = $mascota['especie'] ?? 'Canino';
        $raza = $mascota['raza'] ?? '';
        $sexo = $mascota['genero'] ?? '';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Consentimiento de Desparasitación - BIOSPET</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <style>
        * { box-sizing: border-box; }
        body { background: #f5f5f5; font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; }
        .form-container { max-width: 800px; margin: 0 auto; background: white; border-radius: 20px; padding: 25px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .form-header { text-align: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #E68D0B; }
        .form-header h1 { color: #E68D0B; margin: 0; font-size: 1.8rem; }
        .form-section { margin-bottom: 25px; padding: 15px; background: #f9f9f9; border-radius: 12px; }
        .form-section h3 { color: #E68D0B; margin-top: 0; margin-bottom: 15px; font-size: 1.2rem; border-left: 4px solid #E68D0B; padding-left: 10px; }
        .form-row { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; min-width: 150px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; color: #333; font-size: 0.85rem; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .info-box { background: #e8f0fe; padding: 15px; border-radius: 10px; margin: 15px 0; font-size: 13px; color: #004085; }
        .info-success { background: #e3f2fd; padding: 12px; border-radius: 8px; margin-bottom: 15px; color: #1565c0; font-size: 13px; text-align: center; }
        .btn-submit { background: #E68D0B; color: white; border: none; padding: 14px 25px; border-radius: 10px; font-size: 1.1rem; cursor: pointer; width: 100%; margin-top: 20px; }
        @media (max-width: 600px) { .form-row { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <h1>🐾 Consentimiento de Desparasitación</h1>
            <p>MVZ. Jaraeth Hernández Fernández | Ced. Prof. 1234567</p>
        </div>

        <?php if ($id_cliente > 0): ?>
        <div class="info-success">
            📋 Datos precargados desde la cita del cliente. Verifica que sean correctos.
        </div>
        <?php endif; ?>

        <form action="guardar_formato.php" method="POST">
            <input type="hidden" name="tipo_formato" value="desparacitacion">
            <input type="hidden" name="return_url" value="desparacitacion.php">
            <input type="hidden" name="id_cliente" value="<?php echo $id_cliente; ?>">
            <input type="hidden" name="id_mascota" value="<?php echo $id_mascota; ?>">

            <div class="form-section">
                <h3>📋 Reseña del Paciente</h3>
                <div class="form-row">
                    <div class="form-group"><label>Nombre de la mascota *</label><input type="text" name="mascota_nombre" value="<?php echo htmlspecialchars($nombre_mascota); ?>" required></div>
                    <div class="form-group"><label>Especie</label>
                        <select name="mascota_especie">
                            <option value="Canino" <?php echo $especie == 'Canino' ? 'selected' : ''; ?>>Canino</option>
                            <option value="Felino" <?php echo $especie == 'Felino' ? 'selected' : ''; ?>>Felino</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Raza</label><input type="text" name="mascota_raza" value="<?php echo htmlspecialchars($raza); ?>"></div>
                    <div class="form-group"><label>Color</label><input type="text" name="mascota_color" value="<?php echo htmlspecialchars($color); ?>"></div>
                    <div class="form-group"><label>Sexo</label>
                        <select name="mascota_sexo">
                            <option value="M" <?php echo $sexo == 'M' ? 'selected' : ''; ?>>Macho</option>
                            <option value="H" <?php echo $sexo == 'H' ? 'selected' : ''; ?>>Hembra</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Edad</label><input type="text" name="mascota_edad" value="<?php echo htmlspecialchars($edad); ?>"></div>
                    <div class="form-group"><label>Chip (si aplica)</label><input type="text" name="mascota_chip"></div>
                    <div class="form-group"><label>Peso (Kg)</label><input type="number" step="0.1" name="mascota_peso" value="<?php echo htmlspecialchars($peso); ?>"></div>
                </div>
            </div>

            <div class="form-section">
                <h3>🩺 Constantes Fisiológicas</h3>
                <div class="form-row">
                    <div class="form-group"><label>T° (Temperatura)</label><input type="text" name="temp"></div>
                    <div class="form-group"><label>F.C. (Frecuencia Cardiaca)</label><input type="text" name="fc"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>F.R. (Frecuencia Respiratoria)</label><input type="text" name="fr"></div>
                    <div class="form-group"><label>Mucosas</label><input type="text" name="mucosas"></div>
                </div>
            </div>

            <div class="info-box">
                <strong>📌 Información importante:</strong> La desparasitación consiste en administrar medicamentos para tratar parásitos gastrointestinales. Pueden presentarse efectos adversos como vómito, diarrea, decaimiento o fiebre. <strong>En caso de presentar dichos síntomas deberá acudir a la clínica veterinaria para tratamiento, asumiendo el costo de estos.</strong>
            </div>

            <div class="form-section">
                <h3>✍️ Autorización</h3>
                <div class="form-group"><label>Yo, *</label><input type="text" name="nombre_propietario" value="<?php echo htmlspecialchars($nombre_propietario); ?>" placeholder="Nombre del propietario" required></div>
                <div class="form-group"><label>Teléfono</label><input type="tel" name="telefono_propietario" value="<?php echo htmlspecialchars($telefono); ?>"></div>
                <div class="checkbox-group"><label><input type="checkbox" name="autoriza" required> Autorizo a la clínica veterinaria a realizar la desparasitación de mi mascota. Reconozco que se me ha explicado el procedimiento y acepto los riesgos. *</label></div>
                <div class="form-group"><label>Firma (nombre completo)</label><input type="text" name="firma" value="<?php echo htmlspecialchars($nombre_propietario); ?>" required></div>
                <div class="form-group"><label>Fecha</label><input type="date" name="fecha_firma" value="<?php echo date('Y-m-d'); ?>" required></div>
            </div>

            <button type="submit" class="btn-submit">✅ Autorizar Desparasitación</button>
        </form>
    </div>
</body>
</html>