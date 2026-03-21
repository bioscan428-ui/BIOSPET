<?php
session_start();
if (!isset($_SESSION['admin_logged'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_cita = (int)($_GET['id'] ?? 0);
if (!$id_cita) {
    header('Location: index.php');
    exit;
}

$sql = "SELECT 
            c.*,
            m.nombre_mascota,
            m.especie,
            m.raza,
            m.fecha_nacimiento,
            cl.nombre AS nombre_dueno,
            cl.ape_pat,
            cl.ape_mat,
            cl.telefono,
            cl.email,
            GROUP_CONCAT(s.nombre_servicio SEPARATOR ', ') AS servicios,
            SUM(dc.precio_fijado) AS total
        FROM CITA c
        JOIN MASCOTA m ON c.id_mascota = m.id
        JOIN CLIENTE cl ON m.id_cliente = cl.id
        JOIN DETALLE_CITA dc ON c.id = dc.id_cita
        JOIN SERVICIO s ON dc.id_servicio = s.id
        WHERE c.id = ?
        GROUP BY c.id";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_cita);
$stmt->execute();
$result = $stmt->get_result();
$cita = $result->fetch_assoc();

if (!$cita) {
    die("Cita no encontrada");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle Cita #<?php echo $id_cita; ?> - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { background: var(--muted); }
        .admin-header { background: var(--primary); color: white; padding: 20px; }
        .admin-header a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 800px; margin: 20px auto; padding: 20px; background: white; border-radius: var(--radius-md); box-shadow: var(--shadow-soft); }
        .section { margin-bottom: 30px; }
        .section h3 { color: var(--primary); border-bottom: 2px solid var(--primary); padding-bottom: 10px; margin-bottom: 15px; }
        .info-row { display: flex; margin-bottom: 10px; }
        .info-label { width: 150px; font-weight: bold; }
        .info-value { flex: 1; }
        .estado { display: inline-block; padding: 4px 12px; border-radius: 20px; color: white; }
        .estado-pendiente { background: #ff9800; }
        .estado-confirmada { background: #4caf50; }
        .estado-cancelada { background: #f44336; }
        .estado-completada { background: #2196f3; }
        .btn-back { background: var(--primary); color: white; padding: 10px 20px; border-radius: var(--radius-sm); text-decoration: none; display: inline-block; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Detalle de Cita #<?php echo $id_cita; ?></h1>
        <a href="dashboard.php">← Volver al listado</a>
        <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
        <a href="logout.php">🚪 Cerrar Sesión</a>
    </div>

    <div class="container">
        <div class="section">
            <h3>📋 Información de la Cita</h3>
            <div class="info-row">
                <div class="info-label">Fecha:</div>
                <div class="info-value"><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Hora:</div>
                <div class="info-value"><?php echo $cita['hora_cita']; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Estado:</div>
                <div class="info-value"><span class="estado estado-<?php echo $cita['estado']; ?>"><?php echo ucfirst($cita['estado']); ?></span></div>
            </div>
        </div>

        <div class="section">
            <h3>👤 Dueño</h3>
            <div class="info-row">
                <div class="info-label">Nombre:</div>
                <div class="info-value"><?php echo htmlspecialchars($cita['nombre_dueno'] . ' ' . $cita['ape_pat'] . ' ' . $cita['ape_mat']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Teléfono:</div>
                <div class="info-value"><?php echo $cita['telefono']; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Email:</div>
                <div class="info-value"><?php echo $cita['email'] ?: 'No registrado'; ?></div>
            </div>
        </div>

        <div class="section">
            <h3>🐕 Mascota</h3>
            <div class="info-row">
                <div class="info-label">Nombre:</div>
                <div class="info-value"><?php echo htmlspecialchars($cita['nombre_mascota']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Especie:</div>
                <div class="info-value"><?php echo $cita['especie']; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Raza:</div>
                <div class="info-value"><?php echo $cita['raza'] ?: 'No especificada'; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Fecha Nac.:</div>
                <div class="info-value"><?php echo $cita['fecha_nacimiento'] ?: 'No registrada'; ?></div>
            </div>
        </div>

        <div class="section">
            <h3>💊 Servicios Solicitados</h3>
            <div class="info-row">
                <div class="info-label">Servicios:</div>
                <div class="info-value"><?php echo $cita['servicios']; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Total:</div>
                <div class="info-value"><strong>$<?php echo number_format($cita['total'], 2); ?></strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">Notas:</div>
                <div class="info-value"><?php echo nl2br(htmlspecialchars($cita['notas'] ?: 'Sin notas')); ?></div>
            </div>
        </div>

        <div style="margin-top: 30px; display: flex; gap: 10px;">
            <a href="actualizar_estado.php?id=<?php echo $id_cita; ?>&estado=confirmada" class="btn-back" style="background:#4caf50;">✅ Confirmar Cita</a>
            <a href="actualizar_estado.php?id=<?php echo $id_cita; ?>&estado=cancelada" class="btn-back" style="background:#f44336;">❌ Cancelar Cita</a>
            <a href="actualizar_estado.php?id=<?php echo $id_cita; ?>&estado=completada" class="btn-back" style="background:#2196f3;">✓ Marcar Completada</a>
        </div>
    </div>
</body>
</html>