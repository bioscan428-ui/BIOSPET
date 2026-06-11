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
$peso = '';
$sexo = '';

// Cargar datos del cliente si viene por URL
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

// Cargar datos de la mascota si viene por URL
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
    <title>Ingreso a Estética/Baño/Spa - BIOSPET</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <style>
        * { box-sizing: border-box; }
        body { background: #f5f5f5; font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; }
        .form-container { max-width: 900px; margin: 0 auto; background: white; border-radius: 20px; padding: 25px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .form-header { text-align: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #E68D0B; }
        .form-header h1 { color: #E68D0B; margin: 0; font-size: 1.8rem; }
        .form-header p { color: #666; margin: 5px 0 0; }
        .form-section { margin-bottom: 25px; padding: 15px; background: #f9f9f9; border-radius: 12px; }
        .form-section h3 { color: #E68D0B; margin-top: 0; margin-bottom: 15px; font-size: 1.2rem; border-left: 4px solid #E68D0B; padding-left: 10px; }
        .form-row { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; min-width: 150px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; color: #333; font-size: 0.85rem; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .form-group input:focus, .form-group select:focus { border-color: #E68D0B; outline: none; }
        .checkbox-group { display: flex; flex-wrap: wrap; gap: 15px; align-items: center; margin-top: 10px; }
        .checkbox-group label { display: flex; align-items: center; gap: 5px; font-weight: normal; }
        .signature-area { border: 2px dashed #ddd; border-radius: 12px; padding: 20px; text-align: center; margin-top: 15px; }
        .btn-submit { background: #E68D0B; color: white; border: none; padding: 14px 25px; border-radius: 10px; font-size: 1.1rem; cursor: pointer; width: 100%; margin-top: 20px; }
        .btn-submit:hover { background: #d47a0a; }
        .info-box {
            background: #e3f2fd;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            color: #1565c0;
            font-size: 13px;
            text-align: center;
        }
        @media (max-width: 600px) { .form-container { padding: 15px; } .form-row { flex-direction: column; } .form-group { min-width: 100%; } }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <h1>🐾 Ingreso a Estética / Baño / Spa</h1>
            <p>Completa todos los campos. Al finalizar, firma en la pantalla.</p>
        </div>

        <?php if ($id_cliente > 0): ?>
        <div class="info-box">
            📋 Datos precargados desde la cita del cliente. Verifica que sean correctos.
        </div>
        <?php endif; ?>

        <form action="guardar_formato.php" method="POST">
            <input type="hidden" name="tipo_formato" value="ingreso_estetica">
            <input type="hidden" name="return_url" value="ingreso_estetica.php">
            <input type="hidden" name="id_cliente" value="<?php echo $id_cliente; ?>">
            <input type="hidden" name="id_mascota" value="<?php echo $id_mascota; ?>">

            <!-- DATOS DEL PROPIETARIO -->
            <div class="form-section">
                <h3>📋 Datos del Propietario</h3>
                <div class="form-row">
                    <div class="form-group"><label>Nombre completo *</label><input type="text" name="propietario_nombre" value="<?php echo htmlspecialchars($nombre_propietario); ?>" required></div>
                    <div class="form-group"><label>Teléfono *</label><input type="tel" name="propietario_telefono" value="<?php echo htmlspecialchars($telefono); ?>" required></div>
                </div>
            </div>

            <!-- DATOS DE LA MASCOTA -->
            <div class="form-section">
                <h3>🐕 Datos de la Mascota</h3>
                <div class="form-row">
                    <div class="form-group"><label>Nombre de la mascota *</label><input type="text" name="mascota_nombre" value="<?php echo htmlspecialchars($nombre_mascota); ?>" required></div>
                    <div class="form-group"><label>Especie *</label>
                        <select name="mascota_especie" required>
                            <option value="Canino" <?php echo $especie == 'Canino' ? 'selected' : ''; ?>>Perro (Canino)</option>
                            <option value="Felino" <?php echo $especie == 'Felino' ? 'selected' : ''; ?>>Gato (Felino)</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Raza</label><input type="text" name="mascota_raza" value="<?php echo htmlspecialchars($raza); ?>"></div>
                    <div class="form-group"><label>Color</label><input type="text" name="mascota_color" value="<?php echo htmlspecialchars($color); ?>"></div>
                    <div class="form-group"><label>Peso (Kg)</label><input type="number" step="0.1" name="mascota_peso" value="<?php echo htmlspecialchars($peso); ?>"></div>
                    <div class="form-group"><label>Sexo</label>
                        <select name="mascota_sexo">
                            <option value="M" <?php echo $sexo == 'M' ? 'selected' : ''; ?>>Macho</option>
                            <option value="H" <?php echo $sexo == 'H' ? 'selected' : ''; ?>>Hembra</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Hora de entrada</label><input type="time" name="hora_entrada"></div>
                    <div class="form-group"><label>Hora de salida</label><input type="time" name="hora_salida"></div>
                </div>
            </div>

            <!-- CONDICIONES DE LLEGADA -->
            <div class="form-section">
                <h3>🔍 Condiciones de Llegada</h3>
                <div class="form-row">
                    <div class="form-group"><label>Estado del pelo</label>
                        <select name="estado_pelo">
                            <option value="Normal">Normal</option>
                            <option value="Sucio">Sucio</option>
                            <option value="Motas/Nudos">Motas/Nudos</option>
                            <option value="Pelo apelmazado">Pelo apelmazado</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Parásitos externos</label>
                        <select name="parasitos">
                            <option value="No">No</option>
                            <option value="Pulgas">Pulgas</option>
                            <option value="Garrapatas">Garrapatas</option>
                            <option value="Ambos">Ambos</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Estado de la piel</label>
                        <select name="estado_piel">
                            <option value="Normal">Normal</option>
                            <option value="Infección">Infección</option>
                            <option value="Heridas">Heridas</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Estado dental</label>
                        <select name="estado_dental">
                            <option value="Normal">Normal</option>
                            <option value="Sarro">Sarro</option>
                            <option value="Placa dentaria">Placa dentaria</option>
                            <option value="Halitosis">Halitosis</option>
                            <option value="Gingivitis">Gingivitis</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Estado de oídos</label>
                        <select name="estado_oidos">
                            <option value="Normal">Normal</option>
                            <option value="Sucios">Sucios</option>
                            <option value="Infección">Infección</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Estado ocular</label>
                        <select name="estado_ocular">
                            <option value="Normal">Normal</option>
                            <option value="Secreción">Secreción</option>
                            <option value="Infección">Infección</option>
                            <option value="Irritación">Irritación</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Bolsas anales</label>
                        <select name="bolsas_anales">
                            <option value="Vacías">Vacías</option>
                            <option value="Llenas">Llenas</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Almohadillas</label>
                        <select name="almohadillas">
                            <option value="Normal">Normal</option>
                            <option value="Infección">Infección</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- SERVICIOS ADICIONALES -->
            <div class="form-section">
                <h3>✂️ Servicios Adicionales</h3>
                <div class="checkbox-group">
                    <label><input type="checkbox" name="corte_unas"> Corte de uñas</label>
                    <label><input type="checkbox" name="vaciado_glandulas"> Vaciado de glándulas anales</label>
                    <label><input type="checkbox" name="limpieza_dientes"> Limpieza de dientes</label>
                    <label><input type="checkbox" name="producto_oidos"> Producto de limpieza de oídos</label>
                    <label><input type="checkbox" name="perfume"> Perfume</label>
                    <label><input type="checkbox" name="pañuelo"> Pañuelo</label>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Estilo de corte</label>
                        <select name="estilo_corte">
                            <option value="">-- Seleccione un estilo --</option>
                            <option value="Corte Completo">✂️ Corte Completo</option>
                            <option value="Corte Higiénico">🧼 Corte Higiénico</option>
                            <option value="Corte Raza">🐕 Corte de Raza</option>
                            <option value="Corte León">🦁 Corte León (para gatos)</option>
                            <option value="Corte Teddy">🧸 Corte Teddy</option>
                            <option value="Corte Japonés">🇯🇵 Corte Japonés</option>
                            <option value="Corte Militar">🎖️ Corte Militar</option>
                            <option value="Corte de Verano">☀️ Corte de Verano</option>
                            <option value="Recorte de puntas">✂️ Recorte de puntas</option>
                            <option value="Afeitado completo">🪒 Afeitado completo</option>
                            <option value="Sin corte">🚫 Sin corte (solo baño)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tipo de Shampoo</label>
                        <select name="tipo_shampoo">
                            <option value="Limpieza">🧴 Limpieza</option>
                            <option value="Medicado">💊 Medicado</option>
                            <option value="Dermatológico">🩺 Dermatológico</option>
                            <option value="Antipulgas">🦟 Antipulgas</option>
                            <option value="Hipoalergénico">🌸 Hipoalergénico</option>
                            <option value="Aclarado">✨ Aclarado</option>
                            <option value="Hidratante">💧 Hidratante</option>
                            <option value="Blanqueador">⚪ Blanco para pelo blanco</option>
                            <option value="Cepillo seco">🧽 Cepillo seco</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ACEPTACIÓN -->
            <div class="form-section">
                <h3>📝 Autorización y Consentimiento</h3>
                <div style="background:white; padding:15px; border-radius:10px; margin-bottom:15px; font-size:12px; color:#666; max-height:150px; overflow-y:auto;">
                    <p>✓ Entiendo que se utilizan implementos individuales y esterilizados.</p>
                    <p>✓ Reconozco que mi mascota puede comportarse de manera inadvertida y podrían ocurrir accidentes menores.</p>
                    <p>✓ Declaro conocer los factores de riesgo (edad, cardiopatías, sobrepeso, alergias).</p>
                    <p>✓ En caso de abandono, se procederá legalmente.</p>
                </div>
                <div class="checkbox-group"><label><input type="checkbox" name="acepta_terminos" required> He leído y acepto los términos y condiciones *</label></div>
            </div>

            <!-- FIRMA -->
            <div class="form-section">
                <h3>✍️ Firma Digital</h3>
                <div class="form-group"><label>Escribe tu nombre completo como firma *</label><input type="text" name="firma_nombre" required></div>
                <div class="form-group"><label>Fecha de firma</label><input type="date" name="fecha_firma" value="<?php echo date('Y-m-d'); ?>" required></div>
            </div>

            <button type="submit" class="btn-submit">✅ Enviar Formulario</button>
        </form>
    </div>
</body>
</html>