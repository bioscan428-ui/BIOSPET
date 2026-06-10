<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Acta Compromiso Veterinaria - BIOSPET</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <style>
        * { box-sizing: border-box; }
        body { background: #f5f5f5; font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; }
        .form-container { max-width: 900px; margin: 0 auto; background: white; border-radius: 20px; padding: 25px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .form-header { text-align: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #E68D0B; }
        .form-header h1 { color: #E68D0B; margin: 0; font-size: 1.8rem; }
        .form-section { margin-bottom: 25px; padding: 15px; background: #f9f9f9; border-radius: 12px; }
        .form-section h3 { color: #E68D0B; margin-top: 0; margin-bottom: 15px; font-size: 1.2rem; border-left: 4px solid #E68D0B; padding-left: 10px; }
        .form-row { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; min-width: 150px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; color: #333; font-size: 0.85rem; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .clausulas { background: white; padding: 15px; border-radius: 10px; margin: 15px 0; font-size: 13px; color: #444; max-height: 300px; overflow-y: auto; }
        .clausulas p { margin: 8px 0; }
        .btn-submit { background: #E68D0B; color: white; border: none; padding: 14px 25px; border-radius: 10px; font-size: 1.1rem; cursor: pointer; width: 100%; margin-top: 20px; }
        @media (max-width: 600px) { .form-row { flex-direction: column; } .form-group { min-width: 100%; } }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <h1>📋 Acta Compromiso Veterinaria</h1>
            <p>MVZ. Juan Perez| Ced. Prof. 1234567</p>
        </div>

        <form action="guardar_formato.php" method="POST">
            <input type="hidden" name="tipo_formato" value="acta_compromiso">
            <input type="hidden" name="return_url" value="acta_compromiso.php">

            <div class="form-section">
                <h3>👤 Datos del Propietario</h3>
                <div class="form-row">
                    <div class="form-group"><label>Nombre completo *</label><input type="text" name="propietario_nombre" required></div>
                    <div class="form-group"><label>Dirección</label><input type="text" name="propietario_direccion"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Teléfono *</label><input type="tel" name="propietario_telefono" required></div>
                    <div class="form-group"><label>Depósito inicial ($)</label><input type="number" step="0.01" name="deposito_inicial" placeholder="0.00"></div>
                </div>
            </div>

            <div class="form-section">
                <h3>🐕 Datos del Paciente</h3>
                <div class="form-row">
                    <div class="form-group"><label>Nombre de la mascota *</label><input type="text" name="mascota_nombre" required></div>
                    <div class="form-group"><label>Especie</label><select name="mascota_especie"><option value="Canino">Canino</option><option value="Felino">Felino</option></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Raza</label><input type="text" name="mascota_raza"></div>
                    <div class="form-group"><label>Color</label><input type="text" name="mascota_color"></div>
                    <div class="form-group"><label>Sexo</label><select name="mascota_sexo"><option value="M">Macho</option><option value="H">Hembra</option></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Fecha de nacimiento</label><input type="date" name="mascota_fecha_nac"></div>
                    <div class="form-group"><label>Edad</label><input type="text" name="mascota_edad" placeholder="Ej: 2 años"></div>
                    <div class="form-group"><label>Peso (Kg)</label><input type="number" step="0.1" name="mascota_peso"></div>
                </div>
                <div class="form-group"><label>Señas particulares</label><input type="text" name="mascota_senias"></div>
            </div>

            <div class="form-section">
                <h3>⚖️ Cláusulas de Aceptación</h3>
                <div class="clausulas">
                    <p>1. Acepto que se realicen procedimientos como: canalización, aplicación de analgésicos, antibióticos, exámenes de laboratorio, según sea necesario para tratar a mi mascota.</p>
                    <p>2. Asumo la totalidad de los gastos de hospitalización, alimentación, exámenes, tratamientos y procedimientos médico-quirúrgicos.</p>
                    <p>3. Reconozco que pueden presentarse complicaciones durante procedimientos médicos, prequirúrgicos, quirúrgicos y postoperatorios.</p>
                    <p>4. Eximo de responsabilidad a la clínica veterinaria y a los médicos veterinarios tratantes por eventualidades derivadas de maniobras médicas aplicadas bajo condiciones rutinarias.</p>
                    <p>5. Si mi mascota tiene seguro, presentaré la autorización correspondiente.</p>
                    <p>6. Si después de 2 días hábiles no he cancelado los costos, autorizo a la clínica a disponer de mi mascota según lo considere conveniente.</p>
                </div>
                <div class="checkbox-group"><label><input type="checkbox" name="acepta_clausulas" required> He leído y acepto todas las cláusulas *</label></div>
            </div>

            <div class="form-section">
                <h3>✍️ Firma Digital</h3>
                <div class="form-group"><label>Responsable legalmente del paciente *</label><input type="text" name="firma_propietario" required></div>
                <div class="form-group"><label>Fecha</label><input type="date" name="fecha_firma" value="<?php echo date('Y-m-d'); ?>" required></div>
            </div>

            <button type="submit" class="btn-submit">✅ Aceptar y Enviar</button>
        </form>
    </div>
</body>
</html>