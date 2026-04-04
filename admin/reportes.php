<?php
session_start();

// Verificar que el usuario haya iniciado sesión (usando user_id, no admin_logged)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Opcional: verificar rol para acceso
if (!in_array($_SESSION['rol'], ['super_admin', 'admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// ========== 1. Resumen por estado de CITA ==========
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

// ========== 5. Totales de servicios ==========
$sql_totales_servicios = "SELECT 
                    COUNT(*) as total_citas,
                    SUM(dc.precio_fijado) as total_ingresos
                FROM CITA c
                JOIN DETALLE_CITA dc ON c.id = dc.id_cita";
$result_totales_servicios = $conn->query($sql_totales_servicios);
$totales_servicios = $result_totales_servicios->fetch_assoc();

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
$ingresos_servicios_por_mes = [];
while ($row = $result_meses->fetch_assoc()) {
    $citas_por_mes[$row['mes']] = $row['total_citas'];
    $ingresos_servicios_por_mes[$row['mes']] = $row['total_ingresos'];
}

// ========== 7. VENTAS DE PRODUCTOS ==========

// Totales de ventas
$sql_totales_ventas = "SELECT 
                        COUNT(*) as total_ventas,
                        SUM(total) as total_ingresos,
                        AVG(total) as ticket_promedio
                    FROM VENTA 
                    WHERE estado = 'completada'";
$result_totales_ventas = $conn->query($sql_totales_ventas);
$totales_ventas = $result_totales_ventas->fetch_assoc();

// Ventas por día (últimos 30 días)
$sql_ventas_dia = "SELECT 
                    DATE(fecha_venta) as fecha,
                    COUNT(*) as total_ventas,
                    SUM(total) as total_ingresos
                FROM VENTA 
                WHERE fecha_venta >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    AND estado = 'completada'
                GROUP BY DATE(fecha_venta)
                ORDER BY fecha ASC";
$result_ventas_dia = $conn->query($sql_ventas_dia);
$ventas_por_dia = [];
$ingresos_ventas_por_dia = [];
while ($row = $result_ventas_dia->fetch_assoc()) {
    $ventas_por_dia[$row['fecha']] = $row['total_ventas'];
    $ingresos_ventas_por_dia[$row['fecha']] = $row['total_ingresos'];
}

// Productos más vendidos
$sql_productos_top = "SELECT 
                        p.nombre,
                        SUM(dv.cantidad) as unidades_vendidas,
                        COUNT(DISTINCT dv.id_venta) as total_ventas,
                        SUM(dv.subtotal) as ingresos
                    FROM DETALLE_VENTA dv
                    JOIN PRODUCTO p ON dv.id_producto = p.id
                    JOIN VENTA v ON dv.id_venta = v.id
                    WHERE v.estado = 'completada'
                    GROUP BY p.id
                    ORDER BY unidades_vendidas DESC
                    LIMIT 5";
$result_productos_top = $conn->query($sql_productos_top);
$productos_top = [];
while ($row = $result_productos_top->fetch_assoc()) {
    $productos_top[] = $row;
}

// Ventas por método de pago
$sql_pagos = "SELECT 
                metodo_pago,
                COUNT(*) as total_ventas,
                SUM(total) as total_ingresos
            FROM VENTA 
            WHERE estado = 'completada'
            GROUP BY metodo_pago";
$result_pagos = $conn->query($sql_pagos);
$pagos = [];
while ($row = $result_pagos->fetch_assoc()) {
    $pagos[] = $row;
}

// Ingresos combinados (servicios + ventas) por mes
$sql_ingresos_combinados = "SELECT 
                                meses.mes,
                                COALESCE(servicios.ingresos, 0) as ingresos_servicios,
                                COALESCE(ventas.ingresos, 0) as ingresos_ventas
                            FROM (
                                SELECT DATE_FORMAT(fecha_cita, '%Y-%m') as mes
                                FROM CITA 
                                WHERE fecha_cita >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                                GROUP BY mes
                                UNION
                                SELECT DATE_FORMAT(fecha_venta, '%Y-%m') as mes
                                FROM VENTA 
                                WHERE fecha_venta >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                                GROUP BY mes
                            ) meses
                            LEFT JOIN (
                                SELECT DATE_FORMAT(fecha_cita, '%Y-%m') as mes,
                                       SUM(dc.precio_fijado) as ingresos
                                FROM CITA c
                                JOIN DETALLE_CITA dc ON c.id = dc.id_cita
                                WHERE c.fecha_cita >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                                GROUP BY mes
                            ) servicios ON meses.mes = servicios.mes
                            LEFT JOIN (
                                SELECT DATE_FORMAT(fecha_venta, '%Y-%m') as mes,
                                       SUM(total) as ingresos
                                FROM VENTA
                                WHERE fecha_venta >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                                    AND estado = 'completada'
                                GROUP BY mes
                            ) ventas ON meses.mes = ventas.mes
                            ORDER BY meses.mes ASC";
$result_combinados = $conn->query($sql_ingresos_combinados);
$ingresos_combinados = [];
while ($row = $result_combinados->fetch_assoc()) {
    $ingresos_combinados[] = $row;
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
    'totales_servicios' => $totales_servicios,
    'totales_ventas' => $totales_ventas,
    'ventas_por_dia' => $ventas_por_dia,
    'ingresos_ventas_por_dia' => $ingresos_ventas_por_dia,
    'productos_top' => $productos_top,
    'pagos' => $pagos,
    'ingresos_combinados' => $ingresos_combinados
];

// Incluir la vista
include 'reportes_vista.php';
?>