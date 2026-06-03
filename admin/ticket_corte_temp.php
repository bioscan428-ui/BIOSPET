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

// ========== 1. PRODUCTOS FÍSICOS (manejan stock) ==========
$sql_detalle_productos = "SELECT 
                            p.nombre as producto_nombre,
                            SUM(dv.cantidad) as total_cantidad,
                            SUM(dv.subtotal) as total_monto
                        FROM DETALLE_VENTA dv
                        JOIN VENTA v ON dv.id_venta = v.id
                        JOIN PRODUCTO p ON dv.id_producto = p.id
                        WHERE DATE(v.fecha_venta) = ?
                          AND v.estado = 'completada'
                          AND p.maneja_stock = 1
                        GROUP BY dv.id_producto, p.nombre
                        ORDER BY total_monto DESC";
$stmt_detalle = $conn->prepare($sql_detalle_productos);
$stmt_detalle->bind_param("s", $fecha_corte);
$stmt_detalle->execute();
$detalle_productos = $stmt_detalle->get_result();
$stmt_detalle->close();

// ========== 2. SERVICIOS DE CITAS AGENDADOS ==========
$sql_detalle_servicios_citas = "SELECT 
                                s.nombre_servicio,
                                COUNT(DISTINCT c.id) as numero_servicios,
                                SUM(dc.precio_fijado) as total_monto
                            FROM DETALLE_CITA dc
                            JOIN CITA c ON dc.id_cita = c.id
                            JOIN SERVICIO s ON dc.id_servicio = s.id
                            WHERE DATE(c.fecha_cita) = ?
                              AND c.pagada = 1
                              AND c.estado IN ('completada', 'confirmada')
                            GROUP BY s.id, s.nombre_servicio
                            ORDER BY total_monto DESC";
$stmt_detalle_serv_citas = $conn->prepare($sql_detalle_servicios_citas);
$stmt_detalle_serv_citas->bind_param("s", $fecha_corte);
$stmt_detalle_serv_citas->execute();
$detalle_servicios_citas = $stmt_detalle_serv_citas->get_result();
$stmt_detalle_serv_citas->close();

// ========== 3. SERVICIOS VENDIDOS COMO PRODUCTOS (baños, estética, etc.) ==========
$sql_detalle_servicios_productos = "SELECT 
                                p.nombre as servicio_nombre,
                                SUM(dv.cantidad) as total_cantidad,
                                SUM(dv.subtotal) as total_monto
                            FROM DETALLE_VENTA dv
                            JOIN VENTA v ON dv.id_venta = v.id
                            JOIN PRODUCTO p ON dv.id_producto = p.id
                            WHERE DATE(v.fecha_venta) = ?
                              AND v.estado = 'completada'
                              AND p.maneja_stock = 0
                            GROUP BY dv.id_producto, p.nombre
                            ORDER BY total_monto DESC";
$stmt_detalle_serv_prod = $conn->prepare($sql_detalle_servicios_productos);
$stmt_detalle_serv_prod->bind_param("s", $fecha_corte);
$stmt_detalle_serv_prod->execute();
$detalle_servicios_productos = $stmt_detalle_serv_prod->get_result();
$stmt_detalle_serv_prod->close();
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
            font-size: 11px;
            width: 100%;
            margin: 0;
            padding: 8px 4px;
            background: white;
            color: #000;
        }
        .center { text-align: center; }
        .line { border-top: 1px dashed #000; margin: 3px 0; }
        .line-doble { border-top: 2px solid #000; margin: 4px 0; }
        .info-row { display: flex; justify-content: space-between; margin: 2px 0; }
        .total { font-size: 13px; font-weight: bold; }
        .subtitulo { 
            font-weight: bold; 
            margin: 5px 0 2px 0; 
            text-decoration: underline;
        }
        .producto-item {
            display: flex;
            justify-content: space-between;
            margin: 1px 0;
            font-size: 10px;
        }
        .producto-nombre {
            width: 65%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .producto-cantidad {
            width: 15%;
            text-align: center;
        }
        .producto-monto {
            width: 20%;
            text-align: right;
        }
        .nota { 
            font-size: 8px; 
            text-align: center; 
            margin-top: 6px; 
        }
        .btn-print {
            background: #4caf50;
            color: white;
            border: none;
            padding: 6px 12px;
            margin: 8px auto;
            cursor: pointer;
            border-radius: 5px;
            display: block;
            font-family: monospace;
            font-size: 12px;
        }
        .btn-close {
            background: #666;
            color: white;
            border: none;
            padding: 6px 12px;
            margin: 4px auto;
            cursor: pointer;
            border-radius: 5px;
            display: block;
            font-family: monospace;
            font-size: 12px;
            width: 150px;
        }
        @media print {
            .btn-print, .btn-close { display: none; }
            body { padding: 3px 2px; }
        }
    </style>
</head>
<body>
    <div class="center">
        <img src="/assets/images/logo_biospet_inverso.png" alt="BIOSPET" style="max-width: 100px; margin-bottom: 3px;">
        <p><strong>BIOSPET</strong><br>
        PREVISUALIZACIÓN DE CORTE<br>
        <?php echo date('d/m/Y', strtotime($fecha_corte)); ?></p>
        <div class="line"></div>
        <p>Atendió: <?php echo htmlspecialchars($empleado_nombre); ?></p>
        <div class="line"></div>
    </div>

    <!-- DESGLOSE DE PRODUCTOS FÍSICOS -->
    <?php if ($detalle_productos && $detalle_productos->num_rows > 0): ?>
        <div class="subtitulo">📦 PRODUCTOS</div>
        <?php while($prod = $detalle_productos->fetch_assoc()): ?>
            <div class="producto-item">
                <span class="producto-nombre"><?php echo htmlspecialchars(substr($prod['producto_nombre'], 0, 30)); ?></span>
                <span class="producto-cantidad"><?php echo $prod['total_cantidad']; ?></span>
                <span class="producto-monto">$<?php echo number_format($prod['total_monto'], 2); ?></span>
            </div>
        <?php endwhile; ?>
        <div class="line"></div>
    <?php endif; ?>

    <!-- DESGLOSE DE SERVICIOS DE CITAS -->
    <?php if ($detalle_servicios_citas && $detalle_servicios_citas->num_rows > 0): ?>
        <div class="subtitulo">🏥 SERVICIOS CITAS</div>
        <?php while($serv = $detalle_servicios_citas->fetch_assoc()): ?>
            <div class="producto-item">
                <span class="producto-nombre"><?php echo htmlspecialchars(substr($serv['nombre_servicio'], 0, 30)); ?></span>
                <span class="producto-cantidad"><?php echo $serv['numero_servicios']; ?></span>
                <span class="producto-monto">$<?php echo number_format($serv['total_monto'], 2); ?></span>
            </div>
        <?php endwhile; ?>
        <div class="line"></div>
    <?php endif; ?>

    <!-- DESGLOSE DE SERVICIOS PDV (baños, estética, etc.) -->
    <?php if ($detalle_servicios_productos && $detalle_servicios_productos->num_rows > 0): ?>
        <div class="subtitulo">✂️ SERVICIOS PDV</div>
        <?php while($serv = $detalle_servicios_productos->fetch_assoc()): ?>
            <div class="producto-item">
                <span class="producto-nombre"><?php echo htmlspecialchars(substr($serv['servicio_nombre'], 0, 30)); ?></span>
                <span class="producto-cantidad"><?php echo $serv['total_cantidad']; ?></span>
                <span class="producto-monto">$<?php echo number_format($serv['total_monto'], 2); ?></span>
            </div>
        <?php endwhile; ?>
        <div class="line"></div>
    <?php endif; ?>

    <!-- TOTALES -->
    <div class="info-row">
        <span>🛒 VENTAS PRODUCTOS:</span>
        <span>$<?php echo number_format($caja['total_ventas'] ?? 0, 2); ?></span>
    </div>
    <div class="info-row">
        <span>🏥 SERVICIOS CITAS:</span>
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
    <div class="line-doble"></div>
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
    <button class="btn-close" onclick="window.close()">❌ Cerrar</button>

    <script>
        // No cerrar automáticamente, dejar que el usuario decida
    </script>
</body>
</html>