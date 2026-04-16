<?php
session_start();

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol para acceso
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

// ========== 2. Servicios más solicitados ==========
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

// ========== 3. Tasa de conversión (usando función) ==========
$tasa_conversion = $conn->query("SELECT tasa_conversion_citas() as tasa")->fetch_assoc()['tasa'];

// ========== 4. Totales de servicios ==========
$sql_totales_servicios = "SELECT 
                    COUNT(*) as total_citas,
                    SUM(dc.precio_fijado) as total_ingresos
                FROM CITA c
                JOIN DETALLE_CITA dc ON c.id = dc.id_cita";
$result_totales_servicios = $conn->query($sql_totales_servicios);
$totales_servicios = $result_totales_servicios->fetch_assoc();

// ========== 5. Ingresos del mes actual (usando función) ==========
$ingresos_mes = $conn->query("SELECT ingresos_mes_actual() as total")->fetch_assoc()['total'];

// ========== 6. Productos con stock bajo (usando función) ==========
$productos_stock_bajo = $conn->query("SELECT productos_stock_bajo() as total")->fetch_assoc()['total'];

// ========== 7. Ventas del día (usando función) ==========
$ventas_hoy = $conn->query("SELECT ventas_dia(CURDATE()) as total")->fetch_assoc()['total'];

// ========== 8. Totales de ventas ==========
$sql_totales_ventas = "SELECT 
                        COUNT(*) as total_ventas,
                        SUM(total) as total_ingresos,
                        AVG(total) as ticket_promedio
                    FROM VENTA 
                    WHERE estado = 'completada'";
$result_totales_ventas = $conn->query($sql_totales_ventas);
$totales_ventas = $result_totales_ventas->fetch_assoc();

// ========== 9. Productos más vendidos (USANDO LA VISTA) ==========
// Antes: consulta manual con LIMIT 5
// Ahora: usar la vista con LIMIT 5 (o 20 si quieres mostrar más)
$sql_productos_top = "SELECT * FROM vista_productos_mas_vendidos LIMIT 5";
$result_productos_top = $conn->query($sql_productos_top);
$productos_top = [];
while ($row = $result_productos_top->fetch_assoc()) {
    $productos_top[] = $row;
}

// ========== 10. TOP 20 productos (para gráfico o tabla adicional) ==========
$sql_productos_top20 = "SELECT * FROM vista_productos_mas_vendidos";
$result_productos_top20 = $conn->query($sql_productos_top20);
$productos_top20 = [];
while ($row = $result_productos_top20->fetch_assoc()) {
    $productos_top20[] = $row;
}

// ========== 11. Ventas por método de pago ==========
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

// ========== 12. Datos para gráficos (mantener los existentes) ==========
// ... (tus consultas existentes para gráficos)

// Versión simplificada usando la vista
$sql_ingresos_combinados = "SELECT * FROM vista_ingresos_mensuales ORDER BY mes DESC";
$result_combinados = $conn->query($sql_ingresos_combinados);
$ingresos_combinados = [];
while ($row = $result_combinados->fetch_assoc()) {
    $ingresos_combinados[] = [
        'mes' => date('M Y', strtotime($row['mes'] . '-01')),
        'ingresos_servicios' => $row['ingresos_servicios'],
        'ingresos_ventas' => $row['ingresos_ventas']
    ];
}

$datos_js = [
    'estados' => $estados,
    'serviciosTop' => $servicios_top,
    'totales_servicios' => $totales_servicios,
    'totales_ventas' => $totales_ventas,
    'productos_top' => $productos_top,
    'productos_top20' => $productos_top20, // Nuevo
    'pagos' => $pagos,
    'tasa_conversion' => $tasa_conversion,
    'ingresos_mes' => $ingresos_mes,
    'productos_stock_bajo' => $productos_stock_bajo,
    'ventas_hoy' => $ventas_hoy,
    'ingresosCombinados' => $ingresos_combinados
];

// Incluir la vista
include 'reportes_vista.php';
?>