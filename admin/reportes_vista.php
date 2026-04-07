<?php
// admin/reportes_vista.php - SOLO HTML Y ESTRUCTURA
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/reportes.css">
    <link rel="stylesheet" href="../assets/css/reportes_vista.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/reportes.js" defer></script>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Reportes y Estadísticas</h1>
        <div>
            <a href="dashboard.php">📋 Citas</a>
            <a href="calendario.php">📅 Calendario</a>
            <a href="reportes.php">📊 Reportes</a>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <!-- ========== SECCIÓN NUEVA: MÉTRICAS RÁPIDAS ========== -->
        <h2 style="margin: 20px 0 10px;">📈 Métricas Rápidas</h2>

        <div class="metricas-grid">
            <div class="metrica-card">
                <div class="metrica-valor"><?php echo number_format($tasa_conversion, 1); ?>%</div>
                <div class="metrica-label">Tasa de conversión de citas</div>
            </div>
            <div class="metrica-card">
                <div class="metrica-valor">$<?php echo number_format($ingresos_mes, 2); ?></div>
                <div class="metrica-label">Ingresos del mes actual</div>
            </div>
            <div class="metrica-card">
                <div class="metrica-valor"><?php echo $productos_stock_bajo; ?></div>
                <div class="metrica-label">Productos con stock bajo</div>
            </div>
            <div class="metrica-card">
                <div class="metrica-valor">$<?php echo number_format($ventas_hoy, 2); ?></div>
                <div class="metrica-label">Ventas de hoy</div>
            </div>
        </div>

        <!-- ========== SECCIÓN 1: SERVICIOS ========== -->
        <h2 style="margin: 40px 0 10px;">🏥 Servicios Médicos</h2>
        
        <div class="dashboard-grid">
            <div class="card">
                <h3>📊 Resumen de Servicios</h3>
                <div class="resumen-cifras">
                    <div class="cifra">
                        <div class="valor"><?php echo $totales_servicios['total_citas'] ?? 0; ?></div>
                        <div class="label">Total Citas</div>
                    </div>
                    <div class="cifra">
                        <div class="valor">$<?php echo number_format($totales_servicios['total_ingresos'] ?? 0, 2); ?></div>
                        <div class="label">Ingresos por Servicios</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>📌 Citas por Estado</h3>
                <?php
                $estados_colores = [
                    'pendiente' => '#ff9800',
                    'confirmada' => '#4caf50',
                    'cancelada' => '#f44336',
                    'completada' => '#2196f3'
                ];
                foreach($estados as $estado => $total): ?>
                <div class="estado-badge">
                    <span style="color: <?php echo $estados_colores[$estado] ?? '#666'; ?>;">●</span>
                    <?php echo ucfirst($estado); ?>
                    <span class="total"><?php echo $total; ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <h3>🏆 Servicios más solicitados</h3>
                <?php foreach($servicios_top as $s): ?>
                <div class="servicio-item">
                    <span><?php echo $s['nombre_servicio']; ?></span>
                    <span class="total"><?php echo $s['total_solicitudes']; ?> citas</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Gráficos de Servicios -->
        <div class="card" style="margin-bottom: 20px;">
            <h3>📈 Citas por día (últimos 30 días)</h3>
            <canvas id="citasPorDiaChart"></canvas>
        </div>

        <div class="card" style="margin-bottom: 20px;">
            <h3>💰 Ingresos por Servicios (últimos 30 días)</h3>
            <canvas id="ingresosPorDiaChart"></canvas>
        </div>

        <div class="card">
            <h3>📅 Citas por mes (últimos 12 meses)</h3>
            <canvas id="citasPorMesChart"></canvas>
        </div>

        <!-- ========== SECCIÓN 2: VENTAS DE PRODUCTOS ========== -->
        <h2 style="margin: 40px 0 10px;">🛒 Ventas de Productos</h2>

        <div class="dashboard-grid-ventas">
            <div class="card">
                <h3>📊 Resumen de Ventas</h3>
                <div class="resumen-cifras">
                    <div class="cifra">
                        <div class="valor"><?php echo $totales_ventas['total_ventas'] ?? 0; ?></div>
                        <div class="label">Total Transacciones</div>
                    </div>
                    <div class="cifra">
                        <div class="valor">$<?php echo number_format($totales_ventas['total_ingresos'] ?? 0, 2); ?></div>
                        <div class="label">Ingresos por Ventas</div>
                    </div>
                    <div class="cifra">
                        <div class="valor">$<?php echo number_format($totales_ventas['ticket_promedio'] ?? 0, 2); ?></div>
                        <div class="label">Ticket Promedio</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>🏆 Productos más vendidos</h3>
                <?php foreach($productos_top as $p): ?>
                <div class="producto-item">
                    <span class="nombre"><?php echo $p['nombre']; ?></span>
                    <span class="total"><?php echo $p['unidades_vendidas']; ?> unidades</span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <h3>💳 Ventas por Método de Pago</h3>
                <?php foreach($pagos as $pago): 
                    $metodos = [
                        'efectivo' => '💵 Efectivo',
                        'tarjeta' => '💳 Tarjeta',
                        'transferencia' => '🏦 Transferencia',
                        'credito' => '📝 Crédito'
                    ];
                ?>
                <div class="pago-item">
                    <span class="nombre"><?php echo $metodos[$pago['metodo_pago']] ?? $pago['metodo_pago']; ?></span>
                    <span class="total">$<?php echo number_format($pago['total_ingresos'], 2); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Gráficos de Ventas -->
        <div class="chart-row">
            <div class="card">
                <h3>📈 Ventas por día (últimos 30 días)</h3>
                <canvas id="ventasPorDiaChart"></canvas>
            </div>
            <div class="card">
                <h3>💰 Ingresos por Ventas (últimos 30 días)</h3>
                <canvas id="ingresosVentasPorDiaChart"></canvas>
            </div>
        </div>

        <!-- ========== SECCIÓN 3: INGRESOS COMBINADOS ========== -->
        <h2 style="margin: 40px 0 10px;">💰 Ingresos Totales</h2>

        <div class="card">
            <h3>📊 Comparativa Servicios vs Productos (últimos 12 meses)</h3>
            <canvas id="ingresosCombinadosChart"></canvas>
        </div>

        <div class="ingresos-totales">
            💰 Ingreso Total Acumulado: 
            $<?php echo number_format(($totales_servicios['total_ingresos'] ?? 0) + ($totales_ventas['total_ingresos'] ?? 0), 2); ?>
        </div>
    </div>

    <!-- Datos ocultos para JavaScript -->
    <div id="datos-reportes" style="display: none;"
        data-fechas='<?php echo json_encode($datos_js['fechas']); ?>'
        data-citas='<?php echo json_encode($datos_js['citasData']); ?>'
        data-ingresos='<?php echo json_encode($datos_js['ingresosData']); ?>'
        data-meses='<?php echo json_encode($datos_js['meses']); ?>'
        data-citas-mensuales='<?php echo json_encode($datos_js['citasMensuales']); ?>'
        data-ventas-por-dia-fechas='<?php echo json_encode(array_keys($ventas_por_dia)); ?>'
        data-ventas-por-dia-cantidad='<?php echo json_encode(array_values($ventas_por_dia)); ?>'
        data-ingresos-ventas-por-dia='<?php echo json_encode(array_values($ingresos_ventas_por_dia)); ?>'
        data-ingresos-combinados='<?php echo htmlspecialchars(json_encode($ingresos_combinados), ENT_QUOTES, 'UTF-8'); ?>'
        data-tasa-conversion='<?php echo $tasa_conversion; ?>'
        data-ingresos-mes='<?php echo $ingresos_mes; ?>'
        data-productos-stock-bajo='<?php echo $productos_stock_bajo; ?>'
        data-ventas-hoy='<?php echo $ventas_hoy; ?>'>
    </div>
</body>
</html>