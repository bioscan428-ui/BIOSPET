<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol para acceso
if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'recepcionista', 'caja'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Determinar tipo de vista (diario o mensual)
$tipo_vista = $_GET['tipo'] ?? 'diario';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$fecha_corte = $_GET['fecha'] ?? date('Y-m-d');

// Para vista mensual, establecer fechas si no vienen
if ($tipo_vista === 'mensual') {
    if (empty($fecha_inicio) && empty($fecha_fin)) {
        $fecha_inicio = date('Y-m-01');
        $fecha_fin = date('Y-m-t');
    }
}

// Obtener empleado que realiza el corte
$empleado_nombre = $_SESSION['nombre'] ?? $_SESSION['usuario'] ?? 'Administrador';
$empleado_id = $_SESSION['empleado_id'] ?? null;

// Variables para resultados
$caja = null;
$corte_existente = null;
$transacciones = [];
$total_general = 0;
$efectivo_total = 0;
$electronico_total = 0;

// Variables para desglose
$detalle_productos = null;
$detalle_servicios_citas = null;      // Servicios agendados (vienen de DETALLE_CITA)
$detalle_servicios_productos = null;   // Servicios vendidos como productos (maneja_stock = 0)

if ($tipo_vista === 'diario') {
    // VISTA DIARIA
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
    
    $sql_verificar = "SELECT rc.*, e.nombre as empleado_nombre 
                      FROM REGISTRO_CORTE rc
                      LEFT JOIN EMPLEADO e ON rc.id_empleado = e.id
                      WHERE rc.fecha_corte = ?";
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->bind_param("s", $fecha_corte);
    $stmt_verificar->execute();
    $corte_existente = $stmt_verificar->get_result()->fetch_assoc();
    
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
    
    // ========== 2. SERVICIOS DE CITAS AGENDADOS (vienen de DETALLE_CITA) ==========
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

    
    
} else {
    // VISTA MENSUAL
    $stmt_ventas = $conn->prepare("CALL obtener_resumen_ventas(?, ?)");
    $stmt_ventas->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt_ventas->execute();
    $ventas_data = $stmt_ventas->get_result()->fetch_assoc();
    $stmt_ventas->close();
    $conn->next_result();
    
    $stmt_servicios = $conn->prepare("CALL obtener_resumen_servicios(?, ?)");
    $stmt_servicios->bind_param("ss", $fecha_inicio, $fecha_fin);
    $stmt_servicios->execute();
    $servicios_data = $stmt_servicios->get_result()->fetch_assoc();
    $stmt_servicios->close();
    $conn->next_result();

    $caja = [
        'total_ventas' => $ventas_data['total_ventas'] ?? 0,
        'numero_ventas' => $ventas_data['numero_ventas'] ?? 0,
        'efectivo_ventas' => $ventas_data['efectivo_ventas'] ?? 0,
        'total_servicios' => $servicios_data['total_servicios'] ?? 0,
        'numero_servicios' => $servicios_data['numero_servicios'] ?? 0,
        'efectivo_servicios' => $servicios_data['efectivo_servicios'] ?? 0
    ];
    
    $sql_transacciones = "SELECT 
                            'venta' as tipo,
                            v.id as referencia,
                            v.fecha_venta as fecha,
                            v.total as monto,
                            v.metodo_pago,
                            COALESCE(c.nombre, 'Mostrador') as cliente
                          FROM VENTA v
                          LEFT JOIN CLIENTE c ON v.id_cliente = c.id
                          WHERE DATE(v.fecha_venta) BETWEEN ? AND ? 
                            AND v.estado = 'completada'
                          UNION ALL
                          SELECT 
                            'servicio' as tipo,
                            c.id as referencia,
                            c.fecha_cita as fecha,
                            SUM(d.precio_fijado) as monto,
                            COALESCE(a.metodo_pago, 'No registrado') as metodo_pago,
                            cl.nombre as cliente
                          FROM CITA c
                          JOIN MASCOTA m ON c.id_mascota = m.id
                          JOIN CLIENTE cl ON m.id_cliente = cl.id
                          JOIN DETALLE_CITA d ON c.id = d.id_cita
                          LEFT JOIN AUDITORIA_PAGOS a ON c.id = a.id_cita
                          WHERE DATE(c.fecha_cita) BETWEEN ? AND ? 
                            AND c.pagada = 1 
                            AND c.estado IN ('completada', 'confirmada')
                          GROUP BY c.id, c.fecha_cita, a.metodo_pago, cl.nombre
                          ORDER BY fecha DESC";
    
    $stmt2 = $conn->prepare($sql_transacciones);
    $stmt2->bind_param("ssss", $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin);
    $stmt2->execute();
    $transacciones = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();
    
    $total_general = ($caja['total_ventas'] ?? 0) + ($caja['total_servicios'] ?? 0);
    $efectivo_total = ($caja['efectivo_ventas'] ?? 0) + ($caja['efectivo_servicios'] ?? 0);
    $electronico_total = $total_general - $efectivo_total;
}

$mensaje = '';
$error = '';

// ========== PROCESAR CIERRE DE CAJA Y REDIRIGIR A TICKET ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cerrar_caja' && $tipo_vista === 'diario') {
    if ($corte_existente) {
        $error = "⚠️ Ya se realizó un corte para esta fecha. No se puede volver a cerrar.";
    } else {
        $observaciones = $_POST['observaciones'] ?? '';
        
        $sql_insert = "INSERT INTO REGISTRO_CORTE (fecha_corte, id_empleado, total_ventas, total_servicios, total_efectivo, total_electronico, total_general, observaciones) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("siddddds", $fecha_corte, $empleado_id, $caja['total_ventas'], $caja['total_servicios'], $efectivo_total, $electronico_total, $total_general, $observaciones);
        
        if ($stmt_insert->execute()) {
            $id_corte = $conn->insert_id;
            header("Location: ticket_corte.php?id=$id_corte&fecha=$fecha_corte");
            exit;
        } else {
            $error = "❌ Error al cerrar el corte: " . $conn->error;
        }
    }
}

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corte de Caja - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f5f5f5; font-family: Arial, sans-serif; }
        .admin-header { background: #E68D0B; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .admin-header a { color: white; text-decoration: none; margin-left: 20px; }
        .corte-container { max-width: 900px; margin: 30px auto; background: white; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #E68D0B, #f0a33a); color: white; text-align: center; padding: 30px; }
        .header h1 { font-size: 2rem; margin-bottom: 5px; }
        .header h2 { font-size: 1.5rem; margin-bottom: 10px; }
        .totales-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; padding: 20px; background: #f8f9fa; }
        .total-card { background: white; padding: 25px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .total-card .label { font-size: 0.9rem; color: #666; margin-bottom: 10px; }
        .total-card .monto { font-size: 2rem; font-weight: bold; color: #E68D0B; }
        .total-general { background: #E68D0B; color: white; padding: 20px; text-align: center; margin: 20px; border-radius: 12px; }
        .total-general span:first-child { font-size: 1.2rem; margin-right: 20px; }
        .total-general span:last-child { font-size: 2rem; font-weight: bold; }
        .resumen-detalle { padding: 20px; border-top: 1px solid #eee; }
        .resumen-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .fecha-selector { background: #fff; padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .fecha-selector form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .fecha-selector input, .fecha-selector select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; }
        .btn-buscar, .btn-imprimir, .btn-volver, .btn-cerrar { background: #E68D0B; color: white; border: none; padding: 8px 20px; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-cerrar { background: #28a745; font-size: 1.1rem; padding: 12px 30px; }
        .btn-imprimir { background: #17a2b8; }
        .btn-volver { background: #6c757d; }
        .mensaje-exito { background: #d4edda; color: #155724; padding: 15px; margin: 20px; border-radius: 8px; text-align: center; }
        .mensaje-error { background: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border-radius: 8px; text-align: center; }
        .corte-realizado { background: #fff3cd; color: #856404; padding: 15px; margin: 20px; border-radius: 8px; text-align: center; }
        .seccion-cerrar { background: #f8f9fa; padding: 20px; margin: 20px; border-radius: 12px; text-align: center; }
        .seccion-cerrar textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin: 10px 0; }
        .tabla-transacciones { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .tabla-transacciones th, .tabla-transacciones td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .tabla-transacciones th { background: #f8f9fa; font-weight: bold; color: #666; }
        .footer { background: #f8f9fa; text-align: center; padding: 20px; color: #666; font-size: 12px; border-top: 1px solid #eee; }
        .tipo-selector { display: flex; gap: 10px; margin-bottom: 15px; }
        .tipo-btn { padding: 8px 20px; border: 1px solid #E68D0B; background: white; color: #E68D0B; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; }
        .tipo-btn.active { background: #E68D0B; color: white; }
        .no-print { print: none; }
        /* Estilos para el desglose */
        .desglose-section { margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 8px; }
        .desglose-section h3 { color: #E68D0B; margin-bottom: 10px; font-size: 16px; }
        .desglose-item { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dotted #ddd; font-size: 13px; }
        .desglose-nombre { flex: 2; }
        .desglose-cantidad { flex: 0.5; text-align: center; }
        .desglose-monto { flex: 1; text-align: right; }
        .desglose-total { font-weight: bold; margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd; }
        
        @media print { 
            .no-print { display: none; } 
            body { background: white; } 
            .corte-container { margin: 0; box-shadow: none; } 
            .admin-header { display: none; } 
            .total-card { box-shadow: none; }
            .desglose-section {
                background: none;
                padding: 5px 0;
            }
        }
    </style>
</head>
<body>
    <div class="admin-header no-print">
        <h1>🐾 BIOSPET - Corte de Caja</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="punto_venta.php">🛒 Punto de Venta</a>
            <a href="reportes.php">📊 Reportes</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="corte-container">
        <div class="header">
            <h1>🐾 BIOSPET</h1>
            <h2>Corte de Caja <?php echo $tipo_vista === 'diario' ? 'Diario' : 'Mensual'; ?></h2>
            <p>
                <?php if ($tipo_vista === 'diario'): ?>
                    <?php echo date('d/m/Y', strtotime($fecha_corte)); ?>
                <?php else: ?>
                    Del <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> al <?php echo date('d/m/Y', strtotime($fecha_fin)); ?>
                <?php endif; ?>
            </p>
            <p>Realizado por: <?php echo htmlspecialchars($empleado_nombre); ?></p>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Selector de tipo de vista y fechas -->
        <div class="fecha-selector no-print">
            <div class="tipo-selector">
                <a href="?tipo=diario&fecha=<?php echo date('Y-m-d'); ?>" class="tipo-btn <?php echo $tipo_vista === 'diario' ? 'active' : ''; ?>">📅 Vista Diaria</a>
                <a href="?tipo=mensual&fecha_inicio=<?php echo date('Y-m-01'); ?>&fecha_fin=<?php echo date('Y-m-t'); ?>" class="tipo-btn <?php echo $tipo_vista === 'mensual' ? 'active' : ''; ?>">📆 Vista Mensual</a>
            </div>
            
            <?php if ($tipo_vista === 'diario'): ?>
            <form method="GET" action="">
                <input type="hidden" name="tipo" value="diario">
                <label>Fecha:</label>
                <input type="date" name="fecha" value="<?php echo $fecha_corte; ?>">
                <button type="submit" class="btn-buscar">🔍 Consultar</button>
            </form>
            <?php else: ?>
            <form method="GET" action="">
                <input type="hidden" name="tipo" value="mensual">
                <label>Fecha inicio:</label>
                <input type="date" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>">
                <label>Fecha fin:</label>
                <input type="date" name="fecha_fin" value="<?php echo $fecha_fin; ?>">
                <button type="submit" class="btn-buscar">🔍 Consultar</button>
            </form>
            <?php endif; ?>
            
            <div>
                <button onclick="window.print()" class="btn-imprimir">🖨️ Imprimir Vista</button>
                <a href="dashboard.php" class="btn-volver" style="margin-left: 10px;">⬅️ Volver</a>
            </div>
        </div>

        <?php if ($corte_existente && $tipo_vista === 'diario'): ?>
            <div class="corte-realizado">
                <p>✅ <strong>Corte de caja ya realizado para esta fecha</strong></p>
                <p>Realizado el: <?php echo date('d/m/Y H:i:s', strtotime($corte_existente['fecha_registro'])); ?></p>
                <?php if($corte_existente['observaciones']): ?>
                <p>Observaciones: <?php echo htmlspecialchars($corte_existente['observaciones']); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Tarjetas principales -->
        <div class="totales-grid">
            <div class="total-card">
                <div class="label">🛒 Ventas de Productos</div>
                <div class="monto">$<?php echo number_format($caja['total_ventas'] ?? 0, 2); ?></div>
            </div>
            <div class="total-card">
                <div class="label">🏥 Servicios Médicos</div>
                <div class="monto">$<?php echo number_format($caja['total_servicios'] ?? 0, 2); ?></div>
            </div>
        </div>

        <!-- DESGLOSE DE PRODUCTOS FÍSICOS -->
        <?php if ($tipo_vista === 'diario' && $detalle_productos && $detalle_productos->num_rows > 0): ?>
        <div class="desglose-section">
            <h3>📦 Productos Vendidos</h3>
            <?php while($prod = $detalle_productos->fetch_assoc()): ?>
            <div class="desglose-item">
                <span class="desglose-nombre"><?php echo htmlspecialchars($prod['producto_nombre']); ?></span>
                <span class="desglose-cantidad"><?php echo $prod['total_cantidad']; ?></span>
                <span class="desglose-monto">$<?php echo number_format($prod['total_monto'], 2); ?></span>
            </div>
            <?php endwhile; ?>
            <div class="desglose-total">
                <div class="desglose-item">
                    <span class="desglose-nombre"><strong>TOTAL PRODUCTOS</strong></span>
                    <span class="desglose-cantidad"></span>
                    <span class="desglose-monto"><strong>$<?php echo number_format($caja['total_ventas'] ?? 0, 2); ?></strong></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- DESGLOSE DE SERVICIOS DE CITAS AGENDADOS -->
        <?php if ($tipo_vista === 'diario' && $detalle_servicios_citas && $detalle_servicios_citas->num_rows > 0): ?>
        <div class="desglose-section">
            <h3>🏥 Servicios de Citas</h3>
            <?php while($serv = $detalle_servicios_citas->fetch_assoc()): ?>
            <div class="desglose-item">
                <span class="desglose-nombre"><?php echo htmlspecialchars($serv['nombre_servicio']); ?></span>
                <span class="desglose-cantidad"><?php echo $serv['numero_servicios']; ?></span>
                <span class="desglose-monto">$<?php echo number_format($serv['total_monto'], 2); ?></span>
            </div>
            <?php endwhile; ?>
            <div class="desglose-total">
                <div class="desglose-item">
                    <span class="desglose-nombre"><strong>TOTAL SERVICIOS CITAS</strong></span>
                    <span class="desglose-cantidad"></span>
                    <span class="desglose-monto"><strong>$<?php echo number_format($caja['total_servicios'] ?? 0, 2); ?></strong></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- DESGLOSE DE SERVICIOS VENDIDOS EN PUNTO DE VENTA (baños, estética, etc.) -->
        <?php if ($tipo_vista === 'diario' && $detalle_servicios_productos && $detalle_servicios_productos->num_rows > 0): ?>
        <div class="desglose-section">
            <h3>✂️ Servicios (Punto de Venta)</h3>
            <?php while($serv = $detalle_servicios_productos->fetch_assoc()): ?>
            <div class="desglose-item">
                <span class="desglose-nombre"><?php echo htmlspecialchars($serv['servicio_nombre']); ?></span>
                <span class="desglose-cantidad"><?php echo $serv['total_cantidad']; ?></span>
                <span class="desglose-monto">$<?php echo number_format($serv['total_monto'], 2); ?></span>
            </div>
            <?php endwhile; ?>
            <div class="desglose-total">
                <div class="desglose-item">
                    <span class="desglose-nombre"><strong>TOTAL SERVICIOS PDV</strong></span>
                    <span class="desglose-cantidad"></span>
                    <span class="desglose-monto"><strong>$<?php echo number_format($caja['total_ventas'] ?? 0, 2); ?></strong></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Resumen detallado de ingresos por método de pago -->
        <div class="resumen-detalle">
            <h3 style="margin-bottom: 15px;">📋 Resumen de Ingresos</h3>
            
            <div class="resumen-item">
                <span class="label">💵 Efectivo:</span>
                <span class="valor">$<?php echo number_format($efectivo_total, 2); ?></span>
            </div>
            <div class="resumen-item">
                <span class="label">💳 Tarjeta / Transferencia:</span>
                <span class="valor">$<?php echo number_format($electronico_total, 2); ?></span>
            </div>
            
            <div style="margin: 15px 0; border-top: 1px dashed #ddd;"></div>
            
            <div class="resumen-item">
                <span class="label">📊 Número de transacciones (ventas):</span>
                <span><?php echo $caja['numero_ventas'] ?? 0; ?></span>
            </div>

            

            <div class="resumen-item">
                <span class="label">📊 Número de servicios (citas):</span>
                <span><?php echo $caja['numero_servicios'] ?? 0; ?></span>
            </div>
        </div>

        <!-- Tabla de transacciones para vista mensual -->
        <?php if ($tipo_vista === 'mensual' && !empty($transacciones)): ?>
        <div class="resumen-detalle">
            <h3 style="margin-bottom: 15px;">📋 Transacciones del período</h3>
            <table class="tabla-transacciones">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Cliente</th>
                        <th>Referencia</th>
                        <th>Monto</th>
                        <th>Método</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transacciones as $t): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($t['fecha'])); ?></td>
                        <td><?php echo $t['tipo'] === 'venta' ? '🛒 Venta' : '🏥 Servicio'; ?></td>
                        <td><?php echo htmlspecialchars($t['cliente'] ?? '-'); ?></td>
                        <td>#<?php echo $t['referencia']; ?></td>
                        <td>$<?php echo number_format($t['monto'], 2); ?></td>
                        <td><?php echo $t['metodo_pago'] ?? 'No registrado'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Total general -->
        <div class="total-general">
            <span>💰 TOTAL GENERAL DEL <?php echo $tipo_vista === 'diario' ? 'DÍA' : 'PERÍODO'; ?></span>
            <span>$<?php echo number_format($total_general, 2); ?></span>
        </div>

        <!-- Formulario para cerrar caja (solo vista diaria y sin corte existente) -->
        <?php if ($tipo_vista === 'diario' && !$corte_existente): ?>
        <div class="seccion-cerrar">
            <h3 style="margin-bottom: 15px;">🔒 Cierre de Caja</h3>

            <!-- Botón para imprimir ticket (sin cerrar) -->
            <button type="button" class="btn-imprimir-ticket" onclick="imprimirTicketCorte()" style="background: #17a2b8; margin-right: 10px;">
                🖨️ Imprimir Ticket de Corte (Previsualización)
            </button>
            <!-- Botón para cerrar corte -->
            <form method="POST" onsubmit="return confirmarCierre()" style="display: inline-block;">
                <input type="hidden" name="accion" value="cerrar_caja">
                <div class="form-group">
                    <label>Observaciones (opcional)</label>
                    <textarea name="observaciones" rows="2" placeholder="Notas sobre el corte, diferencias, etc." style="width: 100%; margin-bottom: 10px;"></textarea>
                </div>
                <button type="submit" class="btn-cerrar">🔒 Cerrar Corte de Caja</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p>Este documento es un comprobante interno de caja</p>
            <p>Fecha y hora de emisión: <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
    </div>

    <script>
        function confirmarCierre() {
            return confirm('⚠️ ¿Estás seguro de cerrar el corte de caja?\n\nUna vez cerrado, NO se podrán modificar las ventas de esta fecha.\n\nSe abrirá el ticket de corte para imprimir.\n\n¿Deseas continuar?');
        }
        
        function imprimirTicketCorte() {
            const fecha = document.querySelector('input[name="fecha"]')?.value || '<?php echo $fecha_corte; ?>';
            window.open(`ticket_corte_temp.php?fecha=${fecha}`, '_blank', 'width=380,height=600,toolbar=no,menubar=no,scrollbars=yes');
        }
    </script>
</body>
</html>