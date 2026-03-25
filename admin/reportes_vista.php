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
        <!-- Tarjetas de resumen -->
        <div class="dashboard-grid">
            <div class="card">
                <h3>📊 Resumen General</h3>
                <div class="resumen-cifras">
                    <div class="cifra">
                        <div class="valor"><?php echo $totales['total_citas'] ?? 0; ?></div>
                        <div class="label">Total Citas</div>
                    </div>
                    <div class="cifra">
                        <div class="valor">$<?php echo number_format($totales['total_ingresos'] ?? 0, 2); ?></div>
                        <div class="label">Total Ingresos</div>
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

        <!-- Gráficos -->
        <div class="card" style="margin-bottom: 20px;">
            <h3>📈 Citas por día (últimos 30 días)</h3>
            <canvas id="citasPorDiaChart"></canvas>
        </div>

        <div class="card" style="margin-bottom: 20px;">
            <h3>💰 Ingresos por día (últimos 30 días)</h3>
            <canvas id="ingresosPorDiaChart"></canvas>
        </div>

        <div class="card">
            <h3>📅 Citas por mes (últimos 12 meses)</h3>
            <canvas id="citasPorMesChart"></canvas>
        </div>
    </div>

    <!-- Datos ocultos para JavaScript -->
    <div id="datos-reportes" style="display: none;"
        data-fechas='<?php echo json_encode($datos_js['fechas']); ?>'
        data-citas='<?php echo json_encode($datos_js['citasData']); ?>'
        data-ingresos='<?php echo json_encode($datos_js['ingresosData']); ?>'
        data-meses='<?php echo json_encode($datos_js['meses']); ?>'
        data-citas-mensuales='<?php echo json_encode($datos_js['citasMensuales']); ?>'>
    </div>
</body>
</html>