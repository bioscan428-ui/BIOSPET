<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$fecha_corte = $_GET['fecha'] ?? date('Y-m-d');
$empleado_id = $_SESSION['empleado_id'] ?? null;
$empleado_nombre = $_SESSION['nombre'] ?? $_SESSION['usuario'] ?? 'Administrador';

// Obtener datos del corte (sin guardar, solo para previsualización)
$stmt = $conn->prepare("CALL caja_diaria(?)");
$stmt->bind_param("s", $fecha_corte);
$stmt->execute();
$result = $stmt->get_result();
$caja = $result->fetch_assoc();
$stmt->close();
$conn->next_result();

$total_general = ($caja['total_ventas'] ?? 0) + ($caja['total_servicios'] ?? 0);
$efectivo_total = $caja['efectivo'] ?? 0;
$electronico_total = $caja['electronico'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Previsualización Corte de Caja - BIOSPET</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            width: 100%;
            margin: 0;
            padding: 10px 0px;
            background: white;
            color: #000;
        }
        .center { text-align: center; }
        .line { border-top: 1px dashed #000; margin: 5px 0; }
        .info-row { display: flex; justify-content: space-between; margin: 4px 0; }
        .total { font-size: 15px; font-weight: bold; }
        .nota { font-size: 10px; text-align: center; margin-top: 10px; }
        .btn-print {
            background: #4caf50;
            color: white;
            border: none;
            padding: 8px 16px;
            margin: 10px auto;
            cursor: pointer;
            border-radius: 5px;
            display: block;
            font-family: monospace;
        }
        @media print {
            .btn-print { display: none; }
            body { padding: 5px 0px; }
        }
    </style>
</head>
<body>
    <div class="center">
        <img src="/assets/images/logo_biospet_inverso.png" alt="BIOSPET" style="max-width: 140px; margin-bottom: 5px;">
        <p><strong>BIOSPET</strong><br>
        PREVISUALIZACIÓN DE CORTE<br>
        <?php echo date('d/m/Y', strtotime($fecha_corte)); ?></p>
        <div class="line"></div>
        <p>Atendió: <?php echo htmlspecialchars($empleado_nombre); ?></p>
        <div class="line"></div>
    </div>

    <div class="info-row">
        <span>🛒 VENTAS PRODUCTOS:</span>
        <span>$<?php echo number_format($caja['total_ventas'] ?? 0, 2); ?></span>
    </div>
    <div class="info-row">
        <span>🏥 SERVICIOS:</span>
        <span>$<?php echo number_format($caja['total_servicios'] ?? 0, 2); ?></span>
    </div>
    <div class="line"></div>
    <div class="info-row">
        <span>💵 EFECTIVO:</span>
        <span>$<?php echo number_format($efectivo_total, 2); ?></span>
    </div>
    <div class="info-row">
        <span>💳 TARJETA/TRANSFERENCIA:</span>
        <span>$<?php echo number_format($electronico_total, 2); ?></span>
    </div>
    <div class="line-doble" style="border-top: 2px solid #000; margin: 5px 0;"></div>
    <div class="info-row total">
        <span>TOTAL GENERAL:</span>
        <span>$<?php echo number_format($total_general, 2); ?></span>
    </div>
    <div class="line"></div>
    <div class="nota">
        <p>--- ESTA ES UNA PREVISUALIZACIÓN ---</p>
        <p>El corte aún NO ha sido cerrado oficialmente</p>
        <p><?php echo date('d/m/Y H:i:s'); ?></p>
    </div>

    <button class="btn-print" onclick="window.print()">🖨️ Imprimir</button>
    <button class="btn-print" onclick="window.close()" style="background:#666;">❌ Cerrar</button>

    <script>
        // No cerrar automáticamente, dejar que el usuario decida
    </script>
</body>
</html>