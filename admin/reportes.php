<?php
// admin/reportes.php - SOLO LÓGICA PHP
session_start();
if (!isset($_SESSION['admin_logged'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// ========== 1. Resumen por estado ==========
$sql_estados = "SELECT estado, COUNT(*) as total FROM CITA GROUP BY estado";
$result_estados = $conn->query($sql_estados);
$estados = [];
while ($row = $result_estados->fetch_assoc()) {
    $estados[$row['estado']] = $row['total'];
}

// ========== 2. Citas por día (últimos 30 días) ==========
$sql_dias = "SELECT 
                fecha_cita, 
                COUNT(*) as total_citas,
                SUM(dc.precio_fijado) as total_ingresos
            FROM CITA c
            JOIN DETALLE_CITA dc ON c.id = dc.id_cita
            WHERE c.fecha_cita >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY fecha_cita
            ORDER BY fecha_cita ASC";
$result_dias = $conn->query($sql_dias);
$citas_por_dia = [];
$ingresos_por_dia = [];
while ($row = $result_dias->fetch_assoc()) {
    $citas_por_dia[$row['fecha_cita']] = $row['total_citas'];
    $ingresos_por_dia[$row['fecha_cita']] = $row['total_ingresos'];
}

// ========== 3. Servicios más solicitados ==========
$sql_servicios = "SELECT 
                    s.nombre_servicio,
                    COUNT(dc.id) as total_solicitudes,
                    SUM(dc.precio_fijado) as total_ingresos
                FROM DETALLE_CITA dc
                JOIN SERVICIO s ON dc.id_servicio = s.id
                GROUP BY s.id
                ORDER BY total_solicitudes DESC
                LIMIT 5";
$result_servicios = $conn->query($sql_servicios);
$servicios_top = [];
while ($row = $result_servicios->fetch_assoc()) {
    $servicios_top[] = $row;
}

// ========== 4. Tendencia últimos 7 días ==========
$sql_tendencia = "SELECT 
                    fecha_cita,
                    COUNT(*) as total_citas,
                    SUM(dc.precio_fijado) as total_ingresos
                FROM CITA c
                JOIN DETALLE_CITA dc ON c.id = dc.id_cita
                WHERE c.fecha_cita >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY fecha_cita
                ORDER BY fecha_cita ASC";
$result_tendencia = $conn->query($sql_tendencia);
$tendencia = [];
while ($row = $result_tendencia->fetch_assoc()) {
    $tendencia[$row['fecha_cita']] = [
        'citas' => $row['total_citas'],
        'ingresos' => $row['total_ingresos']
    ];
}

// ========== 5. Totales generales ==========
$sql_totales = "SELECT 
                    COUNT(*) as total_citas,
                    SUM(dc.precio_fijado) as total_ingresos
                FROM CITA c
                JOIN DETALLE_CITA dc ON c.id = dc.id_cita";
$result_totales = $conn->query($sql_totales);
$totales = $result_totales->fetch_assoc();

// ========== 6. Citas por mes (últimos 12 meses) ==========
$sql_meses = "SELECT 
                DATE_FORMAT(fecha_cita, '%Y-%m') as mes,
                COUNT(*) as total_citas,
                SUM(dc.precio_fijado) as total_ingresos
            FROM CITA c
            JOIN DETALLE_CITA dc ON c.id = dc.id_cita
            WHERE c.fecha_cita >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY mes
            ORDER BY mes ASC";
$result_meses = $conn->query($sql_meses);
$citas_por_mes = [];
$ingresos_por_mes = [];
while ($row = $result_meses->fetch_assoc()) {
    $citas_por_mes[$row['mes']] = $row['total_citas'];
    $ingresos_por_mes[$row['mes']] = $row['total_ingresos'];
}

// Preparar datos para JavaScript
$datos_js = [
    'fechas' => array_keys($citas_por_dia),
    'citasData' => array_values($citas_por_dia),
    'ingresosData' => array_values($ingresos_por_dia),
    'meses' => array_keys($citas_por_mes),
    'citasMensuales' => array_values($citas_por_mes),
    'estados' => $estados,
    'serviciosTop' => $servicios_top,
    'totales' => $totales
];

// Incluir la vista
include 'reportes_vista.php';
?>