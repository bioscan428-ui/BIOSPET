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

// Si no hay servicio específico, mostrar campo en blanco
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
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
            padding: 40px 20px;
        }
        
        .formato {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        /* Estilos para impresión */
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
        
        .firma {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            gap: 40px;
        }
        
        .firma-caja {
            flex: 1;
            text-align: center;
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
        
        <!-- Datos del cliente (pre-llenados o en blanco) -->
        <div class="datos-cliente">
            <div class="campo">
                <span class="campo-label">Nombre del propietario:</span>
                <span class="campo-linea">
                    <?php echo $cliente ? htmlspecialchars($cliente['nombre'] . ' ' . ($cliente['ape_pat'] ?? '')) : '_________________________'; ?>
                </span>
            </div>
            <div class="campo">
                <span class="campo-label">Teléfono / Celular:</span>
                <span class="campo-linea">
                    <?php echo $cliente ? htmlspecialchars($cliente['telefono'] ?? '') : '_________________________'; ?>
                </span>
            </div>
            <div class="campo">
                <span class="campo-label">Correo electrónico:</span>
                <span class="campo-linea">
                    <?php echo $cliente ? htmlspecialchars($cliente['email'] ?? '') : '_________________________'; ?>
                </span>
            </div>
            <div class="campo">
                <span class="campo-label">Nombre de la mascota:</span>
                <span class="campo-linea">
                    <?php echo $mascota ? htmlspecialchars($mascota['nombre_mascota']) : '_________________________'; ?>
                </span>
            </div>
            <div class="campo">
                <span class="campo-label">Especie / Raza:</span>
                <span class="campo-linea">
                    <?php 
                    if ($mascota) {
                        echo htmlspecialchars($mascota['especie'] . ($mascota['raza'] ? ' - ' . $mascota['raza'] : ''));
                    } else {
                        echo '_________________________';
                    }
                    ?>
                </span>
            </div>
            <div class="campo">
                <span class="campo-label">Servicio solicitado:</span>
                <span class="campo-linea">
                    <?php echo $servicio_nombre ? htmlspecialchars($servicio_nombre) : '_________________________'; ?>
                </span>
            </div>
            <div class="campo">
                <span class="campo-label">Fecha:</span>
                <span class="campo-linea">____ / ____ / ________</span>
            </div>
        </div>
        
        <!-- Contenido del consentimiento -->
        <div class="contenido">
            <p>Por medio del presente, yo <strong>_________________________________</strong>, identificado con cédula de ciudadanía No. 
            <strong>_______________</strong>, actuando en mi calidad de propietario y/o responsable de la mascota 
            <strong>_______________</strong>, manifiesto que:</p>
            
            <h3>1. INFORMACIÓN DEL SERVICIO</h3>
            <p>He sido informado(a) de manera clara y suficiente sobre el procedimiento o servicio que se realizará, 
            incluyendo sus beneficios, posibles riesgos, cuidados posteriores y alternativas existentes.</p>
            
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
        </div>
        
        <!-- Firmas -->
        <div class="firma">
            <div class="firma-caja">
                <div class="firma-linea"></div>
                <p><strong>Firma del propietario</strong></p>
                <p>Nombre: _________________________</p>
                <p>CC: _________________________</p>
            </div>
            <div class="firma-caja">
                <div class="firma-linea"></div>
                <p><strong>Firma del responsable</strong></p>
                <p>BIOSPET - Clínica Veterinaria</p>
                <p>Sello</p>
            </div>
        </div>
        
        <!-- Testigo (opcional) -->
        <div style="margin-top: 30px;">
            <div class="campo">
                <span class="campo-label">Nombre del testigo:</span>
                <span class="campo-linea">_________________________</span>
            </div>
            <div class="campo">
                <span class="campo-label">Parentesco / Relación:</span>
                <span class="campo-linea">_________________________</span>
            </div>
            <div class="campo">
                <span class="campo-label">Teléfono del testigo:</span>
                <span class="campo-linea">_________________________</span>
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
    <button class="btn-imprimir no-print" onclick="window.print(); setTimeout(() => window.close(), 1000);">
        🖨️ Imprimir Formato
    </button>
    
    <script>
        // Imprimir automáticamente al cargar si se prefiere
        // window.print();
    </script>
</body>
</html>