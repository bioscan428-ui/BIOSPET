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

        <!-- ========== NUEVA SECCIÓN: FILTRO DE CITAS POR FECHA ========== -->
        <div class="card" style="margin-bottom: 20px;">
            <h3>📅 Reporte de Citas por Rango de Fechas</h3>
            <form method="GET" action="reportes.php" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px;">
                <input type="hidden" name="filtrar_citas" value="1">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Fecha Inicio:</label>
                    <input type="date" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>" style="padding: 8px; border-radius: 5px; border: 1px solid #ddd;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Fecha Fin:</label>
                    <input type="date" name="fecha_fin" value="<?php echo $fecha_fin; ?>" style="padding: 8px; border-radius: 5px; border: 1px solid #ddd;">
                    </div>
                    <button type="submit" class="btn-primary" style="background: var(--primary); color: white; border: none; padding: 8px 20px; border-radius: 5px; cursor: pointer;">🔍 Buscar</button>
            </form>
            <?php if (!empty($citas_por_rango)): ?>
                <div style="overflow-x: auto;">
                    <table class="productos-top-table full-width">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Estado</th>
                                <th>Mascota</th>
                                <th>Dueño</th>
                                <th>Teléfono</th>
                                <th>Servicios</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($citas_por_rango as $cita): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
                                <td><?php echo $cita['hora_cita']; ?></td>
                                <td>
                                    <span class="estado-<?php echo $cita['estado']; ?>">
                                        <?php echo ucfirst($cita['estado']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($cita['nombre_mascota']); ?></td>
                                <td><?php echo htmlspecialchars($cita['dueno']); ?></td>
                                <td><?php echo $cita['telefono']; ?></td>
                                <td><?php echo $cita['servicios']; ?></td>
                                <td class="ingreso">$<?php echo number_format($cita['total'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f5f5f5; font-weight: bold;">
                                <td colspan="7" style="text-align: right;">Total:</td>
                                <td class="ingreso">$<?php echo number_format(array_sum(array_column($citas_por_rango, 'total')), 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php elseif (isset($_GET['filtrar_citas'])): ?>
                <p style="color: #999; text-align: center; padding: 20px;">No hay citas en el rango de fechas seleccionado.</p>
            <?php endif; ?>
        </div>
        <!-- ========== FIN DE SECCIÓN: FILTRO DE CITAS POR FECHA ========== -->

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

        <!-- ========== NUEVA SECCIÓN: INGRESOS DIARIOS (usando vista_ingresos_diarios) ========== -->
        <?php
        // Consultar ingresos diarios usando la vista
        $sql_ingresos_diarios = "SELECT * FROM vista_ingresos_diarios 
                                 WHERE fecha_cita >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                                 ORDER BY fecha_cita DESC";
        $ingresos_diarios = $conn->query($sql_ingresos_diarios);
        ?>
        
        <div class="card" style="margin-top: 20px;">
            <h2 style="margin: 0 0 20px 0; color: var(--primary); border-left: 4px solid var(--primary); padding-left: 15px;">💰 Ingresos Diarios (últimos 30 días)</h2>
            
            <?php if ($ingresos_diarios && $ingresos_diarios->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="productos-top-table full-width">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Citas</th>
                                <th>Servicios</th>
                                <th>Ingresos Totales</th>
                                <th>Promedio por Servicio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $ingresos_diarios->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo date('d/m/Y', strtotime($row['fecha_cita'])); ?></strong></td>
                                <td><?php echo $row['total_citas']; ?> citas</td>
                                <td><?php echo $row['total_servicios']; ?> servicios</td>
                                <td class="ingreso">$<?php echo number_format($row['ingresos_totales'], 2); ?></td>
                                <td>$<?php echo number_format($row['promedio_por_servicio'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: #999; text-align: center; padding: 20px;">No hay datos de ingresos en los últimos 30 días.</p>
            <?php endif; ?>
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
                <h3>🏆 Top 5 Productos más vendidos</h3>
                <table class="productos-top-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Unidades</th>
                            <th>Ingresos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach($productos_top as $p): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo htmlspecialchars($p['nombre']); ?></strong></td>
                            <td><?php echo $p['categoria']; ?></td>
                            <td><?php echo $p['unidades_vendidas']; ?> uni.</td>
                            <td>$<?php echo number_format($p['ingresos_generados'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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

        <!-- ========== NUEVA SECCIÓN: TOP 20 PRODUCTOS ========== -->
        <div class="card" style="margin-top: 20px;">
            <h3>📊 Top 20 Productos más vendidos (Completo)</h3>
            <div style="overflow-x: auto;">
                <table class="productos-top-table full-width">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Producto</th>
                            <th>Código de Barras</th>
                            <th>Categoría</th>
                            <th>Unidades</th>
                            <th>Ventas</th>
                            <th>Ingresos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach($productos_top20 as $p): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo htmlspecialchars($p['nombre']); ?></strong></td>
                            <td><?php echo $p['codigo_barras'] ?: '—'; ?></td>
                            <td><?php echo $p['categoria']; ?></td>
                            <td><?php echo $p['unidades_vendidas']; ?></td>
                            <td><?php echo $p['total_ventas']; ?> ventas</span></td>
                            <td class="ingreso">$<?php echo number_format($p['ingresos_generados'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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

        <!-- ========== NUEVA SECCIÓN: REPORTE FINANCIERO CONSOLIDADO ========== -->
        <div class="card" style="margin-bottom: 20px; margin-top: 20px;">
            <h3>💰 Reporte Financiero Consolidado</h3>
            <form method="GET" action="reportes.php" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px;">
                <input type="hidden" name="filtrar_financiero" value="1">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Fecha Inicio:</label>
                    <input type="date" name="fecha_inicio_financiero" value="<?php echo $fecha_inicio_financiero ?? date('Y-m-01'); ?>" style="padding: 8px; border-radius: 5px; border: 1px solid #ddd;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Fecha Fin:</label>
                    <input type="date" name="fecha_fin_financiero" value="<?php echo $fecha_fin_financiero ?? date('Y-m-t'); ?>" style="padding: 8px; border-radius: 5px; border: 1px solid #ddd;">
                </div>
                <button type="submit" class="btn-primary" style="background: var(--primary); color: white; border: none; padding: 8px 20px; border-radius: 5px; cursor: pointer;">💰 Consultar</button>
            </form>
            <?php if (isset($_GET['filtrar_financiero']) && !empty($ventas_periodo ?? [])): ?>
                <div class="dashboard-grid" style="margin-bottom: 20px;">
                    <div class="card">
                        <h4>🛒 Ventas de Productos</h4>
                        <div class="resumen-cifras">
                            <div class="cifra">
                                <div class="valor"><?php echo $ventas_periodo['total_transacciones'] ?? 0; ?></div>
                                <div class="label">Transacciones</div>
                            </div>
                            <div class="cifra">
                                <div class="valor">$<?php echo number_format($ventas_periodo['monto_total'] ?? 0, 2); ?></div>
                                <div class="label">Monto Total</div>
                            </div>
                            <div class="cifra">
                                <div class="valor">$<?php echo number_format($ventas_periodo['promedio'] ?? 0, 2); ?></div>
                                <div class="label">Ticket Promedio</div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <h4>🏥 Servicios Médicos</h4>
                        <div class="resumen-cifras">
                            <div class="cifra">
                                <div class="valor"><?php echo $servicios_periodo['total_transacciones'] ?? 0; ?></div>
                                <div class="label">Citas Completadas</div>
                            </div>
                            <div class="cifra">
                                <div class="valor">$<?php echo number_format($servicios_periodo['monto_total'] ?? 0, 2); ?></div>
                                <div class="label">Ingresos por Servicios</div>
                            </div>
                            <div class="cifra">
                                <div class="valor">$<?php echo number_format($servicios_periodo['promedio'] ?? 0, 2); ?></div>
                                <div class="label">Promedio por Servicio</div>
                            </div>
                        </div>
                    </div>
                </div>
                <h4>🏆 Top 10 Productos más vendidos</h4>
                <div style="overflow-x: auto;">
                    <table class="productos-top-table full-width">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Producto</th>
                                <th>Unidades</th>
                                <th>Ingresos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach($productos_top_periodo ?? [] as $p): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($p['producto']); ?></strong></td>
                                <td><?php echo $p['unidades_vendidas']; ?> uni.</td>
                                <td class="ingreso">$<?php echo number_format($p['ingresos'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="ingresos-totales" style="margin-top: 20px;">
                    💰 Ingreso Total del Período:
                    $<?php echo number_format(($ventas_periodo['monto_total'] ?? 0) + ($servicios_periodo['monto_total'] ?? 0), 2); ?>
                </div>
                <?php elseif (isset($_GET['filtrar_financiero'])): ?>
                    <p style="color: #999; text-align: center; padding: 20px;">No hay datos en el período seleccionado.</p>
                    <?php endif; ?>
        </div>

        <!-- ========== FIN DE NUEVA SECCION ========== -->


    </div>

    <!-- Datos ocultos para JavaScript -->
    <div id="datos-reportes" style="display: none;"
        data-fechas='<?php echo json_encode($datos_js['fechas'] ?? []); ?>'
        data-citas='<?php echo json_encode($datos_js['citasData'] ?? []); ?>'
        data-ingresos='<?php echo json_encode($datos_js['ingresosData'] ?? []); ?>'
        data-meses='<?php echo json_encode($datos_js['meses'] ?? []); ?>'
        data-citas-mensuales='<?php echo json_encode($datos_js['citasMensuales'] ?? []); ?>'
        data-ventas-por-dia-fechas='<?php echo json_encode($datos_js['ventasPorDiaFechas'] ?? []); ?>'
        data-ventas-por-dia-cantidad='<?php echo json_encode($datos_js['ventasPorDiaCantidad'] ?? []); ?>'
        data-ingresos-ventas-por-dia='<?php echo json_encode($datos_js['ingresosVentasPorDia'] ?? []); ?>'
        data-ingresos-combinados='<?php echo htmlspecialchars(json_encode($datos_js['ingresosCombinados'] ?? []), ENT_QUOTES, 'UTF-8'); ?>'
        data-tasa-conversion='<?php echo $tasa_conversion; ?>'
        data-ingresos-mes='<?php echo $ingresos_mes; ?>'
        data-productos-stock-bajo='<?php echo $productos_stock_bajo; ?>'
        data-ventas-hoy='<?php echo $ventas_hoy; ?>'>
    </div>
</body>
</html>