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

// ========== OBTENER DETALLE DE PRODUCTOS VENDIDOS ==========
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

// ========== OBTENER DETALLE DE SERVICIOS VENDIDOS ==========
$sql_detalle_servicios = "SELECT 
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
$stmt_detalle_serv = $conn->prepare($sql_detalle_servicios);
$stmt_detalle_serv->bind_param("s", $fecha_corte);
$stmt_detalle_serv->execute();
$detalle_servicios = $stmt_detalle_serv->get_result();
$stmt_detalle_serv->close();
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
            font-size: 12px;
            width: 100%;
            margin: 0;
            padding: 10px 5px;
            background: white;
            color: #000;
            letter-spacing: -0.3px;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .right { text-align: right; }
        .left { text-align: left; }
        .line { 
            border-top: 1px dashed #000; 
            margin: 4px 0; 
        }
        .line-doble { 
            border-top: 2px solid #000; 
            margin: 6px 0; 
        }
        .info-row { 
            display: flex; 
            justify-content: space-between; 
            margin: 3px 0; 
        }
        .total { 
            font-size: 14px; 
            font-weight: bold; 
        }
        .subtitulo { 
            font-weight: bold; 
            margin: 6px 0 3px 0; 
            text-decoration: underline;
        }
        .producto-item {
            display: flex;
            justify-content: space-between;
            margin: 2px 0;
            font-size: 11px;
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
        .no-print {
            text-align: center;
            margin-top: 15px;
        }
        .btn-print {
            background: #4caf50;
            color: white;
            border: none;
            padding: 8px 16px;
            margin: 5px;
            cursor: pointer;
            border-radius: 5px;
            font-family: monospace;
            font-size: 13px;
            width: 45%;
        }
        .btn-close {
            background: #666;
            color: white;
            border: none;
            padding: 8px 16px;
            margin: 5px;
            cursor: pointer;
            border-radius: 5px;
            font-family: monospace;
            font-size: 13px;
            width: 45%;
        }
        @media print {
            body { 
                padding: 5px 3px;
            }
            .no-print { 
                display: none; 
            }
        }
    </style>
</head>
<body>
    <div class="center">
        <img src="/assets/images/logo_biospet_inverso.png" alt="BIOSPET" style="max-width: 130px; margin-bottom: 5px;">
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

    <!-- DESGLOSE DE PRODUCTOS VENDIDOS -->
    <?php if ($detalle_productos && $detalle_productos->num_rows > 0): ?>
        <div class="subtitulo">📦 PRODUCTOS VENDIDOS</div>
        <?php while($prod = $detalle_productos->fetch_assoc()): ?>
            <div class="producto-item">
                <span class="producto-nombre"><?php echo htmlspecialchars(substr($prod['producto_nombre'], 0, 35)); ?></span>
                <span class="producto-cantidad"><?php echo $prod['total_cantidad']; ?></span>
                <span class="producto-monto">$<?php echo number_format($prod['total_monto'], 2); ?></span>
            </div>
        <?php endwhile; ?>
        <div class="line"></div>
    <?php endif; ?>

    <!-- DESGLOSE DE SERVICIOS VENDIDOS -->
    <?php if ($detalle_servicios && $detalle_servicios->num_rows > 0): ?>
        <div class="subtitulo">🏥 SERVICIOS VENDIDOS</div>
        <?php while($serv = $detalle_servicios->fetch_assoc()): ?>
            <div class="producto-item">
                <span class="producto-nombre"><?php echo htmlspecialchars(substr($serv['nombre_servicio'], 0, 35)); ?></span>
                <span class="producto-cantidad"><?php echo $serv['numero_servicios']; ?></span>
                <span class="producto-monto">$<?php echo number_format($serv['total_monto'], 2); ?></span>
            </div>
        <?php endwhile; ?>
        <div class="line"></div>
    <?php endif; ?>

    <!-- TOTALES -->
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
        <span>💳 TARJETA/TRANSFERENCIA:</span>
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
        <span style="font-size: 10px; word-wrap: break-word;"><?php echo htmlspecialchars($corte['observaciones']); ?></span>
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