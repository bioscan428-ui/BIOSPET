<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_cliente = $_GET['id_cliente'] ?? 0;
$id_servicio = $_GET['id_servicio'] ?? 0;
$id_mascota = $_GET['id_mascota'] ?? 0;

// Obtener datos del cliente
$cliente = [];
if ($id_cliente > 0) {
    $sql = "SELECT nombre, ape_pat, ape_mat, telefono, email, direccion 
            FROM CLIENTE WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
}

//----------DATOS DEL EMPLEADO QUE INICIO SESION
$empleado_nombre = '';
$empleado_puesto = '';
$id_empleado = $_SESSION['empleado_id'] ?? 0;

if ($id_empleado > 0) {
    $sql_emp = "SELECT nombre, ape_pat, ape_mat, puesto FROM EMPLEADO WHERE id = ? AND activo = 1";
    $stmt_emp = $conn->prepare($sql_emp);
    $stmt_emp->bind_param("i", $id_empleado);
    $stmt_emp->execute();
    $empleado = $stmt_emp->get_result()->fetch_assoc();
    
    if ($empleado) {
        $empleado_nombre = trim($empleado['nombre'] . ' ' . ($empleado['ape_pat'] ?? '') . ' ' . ($empleado['ape_mat'] ?? ''));
        $empleado_puesto = $empleado['puesto'] ?? '';
        
        // Traducir puesto
        $puestos_traduccion = [
            'super_admin' => 'Administrador',
            'admin' => 'Administrador',
            'veterinario' => 'Médico Veterinario',
            'asistente' => 'Asistente Veterinario',
            'recepcionista' => 'Recepcionista',
            'grooming' => 'Especialista en Estética',
            'caja' => 'Cajero(a)'
        ];
        $empleado_puesto_traducido = $puestos_traduccion[$empleado_puesto] ?? $empleado_puesto;
    }
}

if (empty($empleado_nombre)) {
    $empleado_nombre = '_________________________';
    $empleado_puesto_traducido = 'Responsable del Servicio';
}
//----------FIN DE DATOS DEL EMPLEADO QUE INICIO SESION


// Obtener datos de la mascota
$mascota = [];
if ($id_mascota > 0) {
    $sql = "SELECT nombre_mascota, especie, raza 
            FROM MASCOTA WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_mascota);
    $stmt->execute();
    $mascota = $stmt->get_result()->fetch_assoc();
}

// Obtener nombre del servicio
$servicio_nombre = '';
if ($id_servicio > 0) {
    $sql = "SELECT nombre_servicio FROM SERVICIO WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_servicio);
    $stmt->execute();
    $servicio = $stmt->get_result()->fetch_assoc();
    $servicio_nombre = $servicio['nombre_servicio'] ?? '';
}

// Variables pre-llenadas
$nombre_propietario = $cliente ? htmlspecialchars($cliente['nombre'] . ' ' . ($cliente['ape_pat'] ?? '')) : '';
$nombre_mascota = $mascota ? htmlspecialchars($mascota['nombre_mascota']) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Formato de Consentimiento Informado - BIOSPET</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            background: #f0f0f0;
            padding: 20px;
        }
        
        .formato {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        /* Estilos para tablets */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            .formato {
                padding: 20px;
            }
            .campo-label {
                width: 120px !important;
                font-size: 14px;
            }
            .campo-linea {
                min-width: 150px !important;
            }
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            .formato {
                box-shadow: none;
                padding: 20px;
            }
            .no-print {
                display: none;
            }
            .btn-imprimir {
                display: none;
            }
            input, .campo-linea {
                border-bottom: 1px solid #333 !important;
            }
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        
        .logo {
            max-width: 150px;
            margin-bottom: 15px;
        }
        
        .header h1 {
            font-size: 24px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .header p {
            font-size: 12px;
            color: #666;
        }
        
        .titulo {
            text-align: center;
            margin: 30px 0;
        }
        
        .titulo h2 {
            font-size: 18px;
            text-decoration: underline;
        }
        
        .contenido {
            line-height: 1.6;
            margin: 20px 0;
        }
        
        .contenido p {
            margin: 15px 0;
            text-align: justify;
        }
        
        .contenido h3 {
            margin: 20px 0 10px 0;
            font-size: 16px;
        }
        
        .datos-cliente {
            background: #f5f5f5;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #4caf50;
        }
        
        .campo {
            margin: 10px 0;
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
        }
        
        .campo-label {
            width: 150px;
            font-weight: bold;
        }
        
        .campo-linea {
            flex: 1;
            border-bottom: 1px solid #333;
            margin-left: 10px;
            min-width: 200px;
        }
        
        /* Para inputs editables */
        .campo-input {
            flex: 1;
            border: none;
            border-bottom: 1px solid #333;
            margin-left: 10px;
            padding: 5px 0;
            font-family: inherit;
            font-size: inherit;
            background: transparent;
            outline: none;
        }
        
        .campo-input:focus {
            border-bottom-color: #4caf50;
        }
        
        .firma {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            gap: 40px;
            flex-wrap: wrap;
        }
        
        .firma-caja {
            flex: 1;
            text-align: center;
            min-width: 200px;
        }
        
        .firma-linea {
            border-top: 1px solid #333;
            margin-top: 40px;
            margin-bottom: 10px;
            padding-top: 10px;
        }
        
        .firma-caja p {
            margin: 5px 0;
        }
        
        .nota {
            font-size: 11px;
            color: #666;
            text-align: center;
            margin-top: 40px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        
        .btn-imprimir {
            display: block;
            width: 200px;
            margin: 20px auto;
            padding: 12px;
            background: #4caf50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-align: center;
        }
        
        .btn-imprimir:hover {
            background: #45a049;
        }
        
        .sync-info {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="formato">
        <!-- Encabezado -->
        <div class="header">
            <img src="../assets/images/logo_biospet_inverso.png" alt="BIOSPET" class="logo" style="max-width: 180px;">
            <h1>CONSENTIMIENTO INFORMADO</h1>
            <p>Clínica Veterinaria y Estética • Calidad y Bienestar Animal</p>
        </div>
        
        <!-- Título -->
        <div class="titulo">
            <h2>FORMATO DE AUTORIZACIÓN PARA SERVICIOS</h2>
        </div>
        
        <!-- Datos del cliente (editables) -->
        <div class="datos-cliente">
            <div class="campo">
                <span class="campo-label">Nombre del propietario:</span>
                <input type="text" id="propietario" class="campo-input" value="<?php echo $nombre_propietario; ?>" placeholder="Escriba el nombre completo">
            </div>
            <div class="sync-info">⬆ Este nombre se copiará automáticamente en el texto del consentimiento</div>
            
            <div class="campo">
                <span class="campo-label">Teléfono / Celular:</span>
                <input type="text" id="telefono" class="campo-input" value="<?php echo $cliente ? htmlspecialchars($cliente['telefono'] ?? '') : ''; ?>" placeholder="Teléfono">
            </div>
            <div class="campo">
                <span class="campo-label">Correo electrónico:</span>
                <input type="email" id="email" class="campo-input" value="<?php echo $cliente ? htmlspecialchars($cliente['email'] ?? '') : ''; ?>" placeholder="Correo electrónico">
            </div>
            <div class="campo">
                <span class="campo-label">Nombre de la mascota:</span>
                <input type="text" id="mascota_nombre" class="campo-input" value="<?php echo $nombre_mascota; ?>" placeholder="Nombre de la mascota">
            </div>
            <div class="sync-info">⬆ Este nombre se copiará automáticamente en el texto del consentimiento</div>
            
            <div class="campo">
                <span class="campo-label">Especie / Raza:</span>
                <input type="text" id="especie_raza" class="campo-input" value="<?php 
                    if ($mascota) {
                        echo htmlspecialchars($mascota['especie'] . ($mascota['raza'] ? ' - ' . $mascota['raza'] : ''));
                    } else {
                        echo '';
                    }
                ?>" placeholder="Ej: Canino - Labrador">
            </div>
            <div class="campo">
                <span class="campo-label">Servicio solicitado:</span>
                <input type="text" id="servicio" class="campo-input" value="<?php echo htmlspecialchars($servicio_nombre); ?>" placeholder="Servicio solicitado">
            </div>
            <div class="campo">
                <span class="campo-label">Fecha:</span>
                <input type="text" id="fecha" class="campo-input" value="<?php echo date('d/m/Y'); ?>" placeholder="dd/mm/aaaa">
            </div>
        </div>
        
        <!-- Contenido del consentimiento con campos sincronizados -->
        <div class="contenido">
            <p>Por medio del presente, yo <strong id="propietario_texto">_________________________</strong>, 
            actuando en mi calidad 
            de propietario y/o responsable de la mascota <strong id="mascota_texto">_______________</strong>, 
            manifiesto que:</p>
            
            <h3>1. INFORMACIÓN DEL SERVICIO</h3>
            <p>He sido informado(a) de manera clara y suficiente sobre el procedimiento o servicio 
            <strong id="servicio_texto">_________________</strong> que se realizará, incluyendo sus beneficios, 
            posibles riesgos, cuidados posteriores y alternativas existentes.</p>
            
            <h3>2. AUTORIZACIÓN</h3>
            <p>Autorizo voluntariamente al personal de BIOSPET a realizar el servicio solicitado, así como a tomar 
            las decisiones que consideren necesarias en beneficio de la salud y bienestar de mi mascota durante el 
            procedimiento.</p>
            
            <h3>3. RESPONSABILIDAD</h3>
            <p>Me comprometo a seguir las indicaciones y recomendaciones proporcionadas por el personal después del 
            servicio. Entiendo que el incumplimiento de estas indicaciones puede afectar los resultados esperados.</p>
            
            <h3>4. ESTADO DE SALUD</h3>
            <p>Declaro que he proporcionado información verídica sobre el estado de salud de mi mascota, incluyendo 
            condiciones preexistentes, alergias, medicamentos que está tomando, y cualquier otra situación relevante 
            para la realización del servicio.</p>
            
            <h3>5. AUTORIZACIÓN DE IMÁGENES</h3>
            <p>Autorizo a BIOSPET a tomar fotografías o videos de mi mascota antes, durante y después del servicio, 
            para fines educativos, promocionales o de control de calidad, siempre respetando mi privacidad y sin 
            revelar mis datos personales.</p>
            
            <h3>6. ACEPTACIÓN</h3>
            <p>He leído y comprendido todas las cláusulas de este consentimiento informado. Acepto voluntariamente 
            los términos aquí establecidos.</p>
            
            <p>Fecha: <strong id="fecha_texto">____/____/________</strong></p>
        </div>
        
        <!-- Firmas -->
        <div class="firma">
            <div class="firma-caja">
                <div class="firma-linea"></div>
                <p><strong>Firma del propietario</strong></p>
                <p>Nombre: <span id="firma_nombre">_________________________</span></p>
                <p>CC: _________________________</p>
            </div>

            <div class="firma-caja">
                <div class="firma-linea"></div>
                <p><strong>Firma del responsable</strong></p>
                <p><strong><?php echo htmlspecialchars($empleado_nombre); ?></strong></p>
                <p><?php echo htmlspecialchars($empleado_puesto_traducido); ?></p>
                <p>BIOSPET - Clínica Veterinaria</p>
                <p>Sello</p>
            </div>
        </div>
        
        
        <!-- Nota legal -->
        <div class="nota">
            <p>Este documento es un comprobante de consentimiento informado. Se firma en dos ejemplares, 
            uno para el cliente y otro para la clínica. BIOSPET no se hace responsable por el incumplimiento 
            de las indicaciones post-servicio por parte del propietario.</p>
            <p style="margin-top: 10px;"><strong>BIOSPET - Cuidando a tu mejor amigo 🐾</strong></p>
        </div>
    </div>
    
    <!-- Botón para imprimir (solo visible en pantalla) -->
    <button class="btn-imprimir no-print" onclick="window.print();">
        🖨️ Imprimir Formato
    </button>
    
    <script>
        // Función para actualizar todos los campos sincronizados
        function actualizarCampos() {
            // Obtener valores
            let propietario = document.getElementById('propietario').value;
            let mascota = document.getElementById('mascota_nombre').value;
            let servicio = document.getElementById('servicio').value;
            let fecha = document.getElementById('fecha').value;
            
            // Si están vacíos, mostrar guiones
            if (propietario === '') propietario = '_________________________';
            if (mascota === '') mascota = '_______________';
            if (servicio === '') servicio = '_________________';
            if (fecha === '') fecha = '____/____/________';
            
            // Actualizar todos los lugares donde aparece el nombre del propietario
            document.getElementById('propietario_texto').innerHTML = propietario;
            document.getElementById('firma_nombre').innerHTML = propietario;
            
            // Actualizar nombre de la mascota
            document.getElementById('mascota_texto').innerHTML = mascota;
            
            // Actualizar servicio
            document.getElementById('servicio_texto').innerHTML = servicio;
            
            // Actualizar fecha
            document.getElementById('fecha_texto').innerHTML = fecha;
        }
        
        // Agregar event listeners a los campos editables
        document.getElementById('propietario').addEventListener('input', actualizarCampos);
        document.getElementById('mascota_nombre').addEventListener('input', actualizarCampos);
        document.getElementById('servicio').addEventListener('input', actualizarCampos);
        document.getElementById('fecha').addEventListener('input', actualizarCampos);
        
        // Ejecutar al cargar la página para sincronizar valores iniciales
        document.addEventListener('DOMContentLoaded', function() {
            actualizarCampos();
        });
        
        // Para tablets: asegurar que el teclado no bloquee la vista
        if ('ontouchstart' in window) {
            var inputs = document.querySelectorAll('input');
            inputs.forEach(function(input) {
                input.addEventListener('focus', function() {
                    setTimeout(function() {
                        input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 300);
                });
            });
        }
    </script>
</body>
</html>