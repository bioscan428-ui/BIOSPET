<?php
// admin/get_citas_mascota.php
session_start();
if (!isset($_SESSION['user_id'])) {
    exit("No autorizado");
}

require_once __DIR__ . '/../includes/conexion.php';

$id_mascota = (int)($_GET['id'] ?? 0);
if (!$id_mascota) {
    exit("ID no válido");
}

$sql = "SELECT 
            c.id,
            c.fecha_cita,
            c.hora_cita,
            c.estado,
            GROUP_CONCAT(s.nombre_servicio SEPARATOR ', ') as servicios,
            COALESCE(SUM(dc.precio_fijado), 0) as total
        FROM CITA c
        LEFT JOIN DETALLE_CITA dc ON c.id = dc.id_cita
        LEFT JOIN SERVICIO s ON dc.id_servicio = s.id
        WHERE c.id_mascota = ?
        GROUP BY c.id
        ORDER BY c.fecha_cita DESC, c.hora_cita DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_mascota);
$stmt->execute();
$result = $stmt->get_result();
?>

<?php if ($result->num_rows > 0): ?>
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Servicios</th>
                <th>Total</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php while($cita = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
                    <td><?php echo $cita['hora_cita']; ?></td>
                    <td><?php echo htmlspecialchars($cita['servicios'] ?: '-'); ?></td>
                    <td>$<?php echo number_format($cita['total'], 2); ?></td>
                    <td>
                        <span style="background: <?php echo $cita['estado'] == 'pendiente' ? '#ff9800' : ($cita['estado'] == 'confirmada' ? '#4caf50' : '#2196f3'); ?>; color: white; padding: 4px 8px; border-radius: 20px; font-size: 11px;">
                            <?php echo ucfirst($cita['estado']); ?>
                        </span>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php else: ?>
    <p style="text-align: center; color: #999; padding: 20px;">No hay citas registradas para esta mascota</p>
<?php endif; ?>