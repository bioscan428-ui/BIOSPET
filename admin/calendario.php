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

// Obtener citas confirmadas para el calendario
$sql = "SELECT 
            c.id,
            c.fecha_cita,
            c.hora_cita,
            m.nombre_mascota,
            cl.nombre AS nombre_dueno,
            GROUP_CONCAT(s.nombre_servicio SEPARATOR ', ') AS servicios
        FROM CITA c
        JOIN MASCOTA m ON c.id_mascota = m.id
        JOIN CLIENTE cl ON m.id_cliente = cl.id
        JOIN DETALLE_CITA dc ON c.id = dc.id_cita
        JOIN SERVICIO s ON dc.id_servicio = s.id
        WHERE c.estado = 'confirmada'
        GROUP BY c.id
        ORDER BY c.fecha_cita ASC";

$result = $conn->query($sql);
$eventos = [];

while ($row = $result->fetch_assoc()) {
    $fecha_hora = $row['fecha_cita'] . 'T' . $row['hora_cita'];
    $eventos[] = [
        'id' => $row['id'],
        'title' => $row['nombre_mascota'] . ' - ' . $row['servicios'],
        'start' => $fecha_hora,
        'description' => 'Dueño: ' . $row['nombre_dueno'],
        'url' => 'detalle_cita.php?id=' . $row['id']
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario de Citas - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.js"></script>
    <style>
        body { background: var(--muted); }
        .admin-header { background: var(--primary); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        .admin-header a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 1400px; margin: 20px auto; padding: 0 20px; }
        .calendar-container { background: white; padding: 20px; border-radius: var(--radius-md); box-shadow: var(--shadow-soft); }
        .fc-event { cursor: pointer; }
        .legend { display: flex; gap: 20px; margin-bottom: 20px; background: white; padding: 10px 20px; border-radius: var(--radius-md); }
        .legend-item { display: flex; align-items: center; gap: 8px; }
        .legend-color { width: 20px; height: 20px; border-radius: 4px; }
        .legend-color.confirmada { background: #4caf50; }
        .legend-color.pendiente { background: #ff9800; }
        .legend-color.cancelada { background: #f44336; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Calendario de Citas</h1>
        <div>
            <a href="dashboard.php">📋 Citas</a>
            <a href="calendario.php">📅 Calendario</a>
            <a href="productos.php">🛒 Productos</a>
            <a href="reportes.php">📊 Reportes</a>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <div class="legend">
            <div class="legend-item">
                <div class="legend-color confirmada"></div>
                <span>Citas Confirmadas</span>
            </div>
            <div class="legend-item">
                <div class="legend-color pendiente"></div>
                <span>Citas Pendientes (no se muestran en calendario)</span>
            </div>
        </div>

        <div class="calendar-container">
            <div id="calendar"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                locale: 'es',
                initialView: 'timeGridWeek',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: <?php echo json_encode($eventos); ?>,
                eventClick: function(info) {
                    if (info.event.url) {
                        window.location.href = info.event.url;
                    }
                },
                eventDidMount: function(info) {
                    // Agregar tooltip con descripción
                    info.el.setAttribute('title', info.event.extendedProps.description);
                }
            });
            calendar.render();
        });
    </script>
</body>
</html>