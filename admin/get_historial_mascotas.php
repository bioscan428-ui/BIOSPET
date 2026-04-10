<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Acceso denegado');
}

require_once __DIR__ . '/../includes/conexion.php';

// Usar la vista vista_mascotas_completas
$sql = "SELECT * FROM vista_mascotas_completas ORDER BY total_citas DESC, nombre_mascota ASC";
$result = $conn->query($sql);
?>

<div style="overflow-x: auto;">
    <table class="mascotas-table">
        <thead>
            <tr>
                <th>🐕 Mascota</th>
                <th>Especie</th>
                <th>Raza</th>
                <th>Edad</th>
                <th>Dueño</th>
                <th>Teléfono</th>
                <th>📊 Citas</th>
                <th>📅 Última Visita</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while($mascota = $result->fetch_assoc()): ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($mascota['nombre_mascota']); ?></strong>
                    <br>
                    <small><?php echo $mascota['genero'] == 'MACHO' ? '♂️ Macho' : '♀️ Hembra'; ?></small>
                </td>
                <td><?php echo $mascota['especie']; ?></td>
                <td><?php echo $mascota['raza'] ?: '—'; ?></td>
                <td><?php echo $mascota['edad_anios'] ? $mascota['edad_anios'] . ' años' : '—'; ?></td>
                <td><?php echo htmlspecialchars($mascota['nombre_dueno']); ?></td>
                <td><?php echo $mascota['telefono'] ?: '—'; ?></td>
                <td>
                    <span class="badge-citas">
                        📋 <?php echo $mascota['total_citas']; ?> visitas
                    </span>
                </td>
                <td class="ultima-cita">
                    <?php 
                    if ($mascota['ultima_cita']) {
                        echo date('d/m/Y', strtotime($mascota['ultima_cita']));
                    } else {
                        echo 'Sin visitas';
                    }
                    ?>
                </td>
                <td>
                    <a href="historial_mascota.php?id=<?php echo $mascota['id']; ?>" class="btn-small" style="background: #9c27b0;">
                        📜 Ver historial
                    </a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php if ($result->num_rows === 0): ?>
    <div style="text-align: center; padding: 40px;">
        <p>No hay mascotas registradas.</p>
    </div>
<?php endif; ?>