<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_corte = $_GET['id'] ?? 0;
$fecha_corte = $_GET['fecha'] ?? date('Y-m-d');

if (!$id_corte) {
    die("Corte no encontrado");
}

// Obtener datos del corte
$sql = "SELECT rc.*, e.nombre as empleado_nombre, e.ape_pat, e.ape_mat
        FROM REGISTRO_CORTE rc
        LEFT JOIN EMPLEADO e ON rc.id_empleado = e.id
        WHERE rc.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_corte);
$stmt->execute();
$corte = $stmt->get_result()->fetch_assoc();

if (!$corte) {
    die("Corte no encontrado");
}

$empleado_completo = trim($corte['empleado_nombre'] . ' ' . ($corte['ape_pat'] ?? '') . ' ' . ($corte['ape_mat'] ?? ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Corte de Caja #<?php echo str_pad($id_corte, 8, '0', STR_PAD_LEFT); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Courier New', monospace;
            font-size: 15px; /* Subido a 15px para igualar la fuerza del punto de venta */
            width: 100%;     /* Cambiado a 100% para expandir a los lados del papel */
            margin: 0;
            padding: 10px 0px; /* 0px a los costados para eliminar márgenes blancos */
            background: white;
            color: #000;
            letter-spacing: -0.3px; /* Espaciado compacto para evitar saltos de línea molestos */
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .right { text-align: right; }
        .left { text-align: left; }
        .line { 
            border-top: 1px dashed #000; 
            margin: 6px 0; 
        }
        .line-doble { 
            border-top: 2px solid #000; 
            margin: 8px 0; 
        }
        .info-row { 
            display: flex; 
            justify-content: space-between; 
            margin: 5px 0; 
        }
        .total { 
            font-size: 18px; /* Total destacado proporcionalmente */
            font-weight: bold; 
        }
        .no-print {
            text-align: center;
            margin-top: 20px;
        }
        .btn-print {
            background: #4caf50;
            color: white;
            border: none;
            padding: 10px 16px;
            margin: 5px;
            cursor: pointer;
            border-radius: 5px;
            font-family: monospace;
            font-size: 15px; /* Botones proporcionales a 15px */
            width: 45%;
        }
        .btn-close {
            background: #666;
            color: white;
            border: none;
            padding: 10px 16px;
            margin: 5px;
            cursor: pointer;
            border-radius: 5px;
            font-family: monospace;
            font-size: 15px; /* Botones proporcionales a 15px */
            width: 45%;
        }
        @media print {
            body { 
                padding: 2px 0px; /* Clave para la impresión física real */
            }
            .no-print { 
                display: none; 
            }
        }
    </style>
</head>
<body>
    <div class="center">
        <img src="/assets/images/logo_biospet_inverso.png" alt="BIOSPET" style="max-width: 160px; margin-bottom: 8px;">
        <p><strong>BIOSPET</strong><br>
        CORTE DE CAJA<br>
        <?php echo date('d/m/Y', strtotime($corte['fecha_corte'])); ?></p>
        <div class="line"></div>
        <p>Folio: #<?php echo str_pad($id_corte, 8, '0', STR_PAD_LEFT); ?><br>
        Cerrado: <?php echo date('d/m/Y H:i:s', strtotime($corte['fecha_registro'])); ?></p>
        <div class="line"></div>
    </div>

    <div class="info-row">
        <span>Atendió:</span>
        <span><?php echo htmlspecialchars($empleado_completo); ?></span>
    </div>

    <div class="line"></div>

    <div class="info-row">
        <span>🛒 VENTAS PRODUCTOS:</span>
        <span>$<?php echo number_format($corte['total_ventas'], 2); ?></span>
    </div>
    <div class="info-row">
        <span>🏥 SERVICIOS:</span>
        <span>$<?php echo number_format($corte['total_servicios'], 2); ?></span>
    </div>
    
    <div class="line"></div>
    
    <div class="info-row">
        <span>💵 EFECTIVO:</span>
        <span>$<?php echo number_format($corte['total_efectivo'], 2); ?></span>
    </div>
    <div class="info-row">
        <span>💳 TARJETA/TRANSF:</span>
        <span>$<?php echo number_format($corte['total_electronico'], 2); ?></span>
    </div>
    
    <div class="line-doble"></div>
    
    <div class="info-row total">
        <span>TOTAL GENERAL:</span>
        <span>$<?php echo number_format($corte['total_general'], 2); ?></span>
    </div>
    
    <div class="line"></div>
    
    <?php if($corte['observaciones']): ?>
    <div class="info-row">
        <span>Observaciones:</span>
    </div>
    <div class="info-row">
        <span style="font-size: 13px; word-wrap: break-word; width: 100%;"><?php echo htmlspecialchars($corte['observaciones']); ?></span>
    </div>
    <div class="line"></div>
    <?php endif; ?>
    
    <div class="center">
        <p>¡Corte realizado con éxito!</p>
        <p>---</p>
        <p>Este documento es un comprobante<br>interno de caja</p>
        <p><?php echo date('d/m/Y H:i:s'); ?></p>
    </div>

    <div class="no-print">
        <button onclick="window.print()" class="btn-print">
            🖨️ Imprimir
        </button>
        <button onclick="window.close()" class="btn-close">
            ❌ Cerrar
        </button>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
        
        window.onafterprint = function() {
            setTimeout(function() {
                window.close();
            }, 1000);
        };
    </script>
</body>
</html>