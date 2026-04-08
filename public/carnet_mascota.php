<?php
session_start();

// Verificar que el usuario haya iniciado sesión y tenga rol permitido
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Roles que pueden ver el carnet
if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'veterinario', 'asistente', 'recepcionista'])) {
    die("No tienes permisos para ver esta página");
}

require_once __DIR__ . '/includes/conexion.php';

$id_mascota = (int)($_GET['id'] ?? 0);
if (!$id_mascota) {
    die("Mascota no especificada");
}

// ========== USANDO FUNCIONES ==========
// 1. Obtener edad de la mascota usando la función
$sql_edad = "SELECT edad_mascota(fecha_nacimiento) as edad FROM MASCOTA WHERE id = ?";
$stmt_edad = $conn->prepare($sql_edad);
$stmt_edad->bind_param("i", $id_mascota);
$stmt_edad->execute();
$result_edad = $stmt_edad->get_result();
$row_edad = $result_edad->fetch_assoc();
$edad = $row_edad['edad'];

// 2. Obtener última cita de la mascota usando la función
$sql_ultima_cita = "SELECT ultima_cita_mascota(?) as ultima_cita";
$stmt_ultima = $conn->prepare($sql_ultima_cita);
$stmt_ultima->bind_param("i", $id_mascota);
$stmt_ultima->execute();
$result_ultima = $stmt_ultima->get_result();
$row_ultima = $result_ultima->fetch_assoc();
$ultima_cita = $row_ultima['ultima_cita'];

// Obtener datos de la mascota y su dueño
$sql = "SELECT 
            m.id,
            m.nombre_mascota,
            m.especie,
            m.raza,
            m.genero,
            m.fecha_nacimiento,
            m.foto,
            cl.nombre AS nombre_dueno,
            cl.ape_pat,
            cl.ape_mat,
            cl.telefono,
            cl.email
        FROM MASCOTA m
        JOIN CLIENTE cl ON m.id_cliente = cl.id
        WHERE m.id = ? AND m.activo = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_mascota);
$stmt->execute();
$result = $stmt->get_result();
$mascota = $result->fetch_assoc();

if (!$mascota) {
    die("Mascota no encontrada");
}

// Obtener historial de citas (últimas 5)
$sql_citas = "SELECT 
                c.fecha_cita,
                c.estado,
                GROUP_CONCAT(s.nombre_servicio SEPARATOR ', ') AS servicios
              FROM CITA c
              JOIN DETALLE_CITA dc ON c.id = dc.id_cita
              JOIN SERVICIO s ON dc.id_servicio = s.id
              WHERE c.id_mascota = ?
              GROUP BY c.id
              ORDER BY c.fecha_cita DESC
              LIMIT 5";
$stmt_citas = $conn->prepare($sql_citas);
$stmt_citas->bind_param("i", $id_mascota);
$stmt_citas->execute();
$historial = $stmt_citas->get_result();

// Obtener próxima cita
$sql_proxima = "SELECT fecha_cita, hora_cita, estado
                FROM CITA
                WHERE id_mascota = ? AND fecha_cita >= CURDATE() AND estado IN ('pendiente', 'confirmada')
                ORDER BY fecha_cita ASC
                LIMIT 1";
$stmt_proxima = $conn->prepare($sql_proxima);
$stmt_proxima->bind_param("i", $id_mascota);
$stmt_proxima->execute();
$proxima = $stmt_proxima->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carnet de <?php echo htmlspecialchars($mascota['nombre_mascota']); ?> - BIOSPET</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/carnet_mascota.css">
</head>
<body>
    <div class="admin-barra">
        🔐 Acceso restringido al personal | <?php echo htmlspecialchars($_SESSION['nombre']); ?> (<?php echo ucfirst($_SESSION['rol']); ?>)
    </div>

    <div class="carnet">
        <div class="carnet-header">
            <h1>🐾 BIOSPET</h1>
            <p>Carnet de Identidad Veterinaria</p>
        </div>

        <div class="foto">
            <?php if (!empty($mascota['foto']) && file_exists($mascota['foto'])): ?>
                <img src="<?php echo $mascota['foto']; ?>" alt="<?php echo htmlspecialchars($mascota['nombre_mascota']); ?>">
            <?php else: ?>
                <img src="assets/images/default-pet.png" alt="Mascota sin foto">
            <?php endif; ?>
        </div>

        <div class="info">
            <div class="info-section">
                <h3>📋 Datos de la Mascota</h3>
                <div class="info-row">
                    <div class="info-label">Nombre:</div>
                    <div class="info-value"><?php echo htmlspecialchars($mascota['nombre_mascota']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Especie:</div>
                    <div class="info-value"><?php echo $mascota['especie']; ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Raza:</div>
                    <div class="info-value"><?php echo $mascota['raza'] ?: 'No especificada'; ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Género:</div>
                    <div class="info-value"><?php echo $mascota['genero'] == 'MACHO' ? '♂️ Macho' : ($mascota['genero'] == 'HEMBRA' ? '♀️ Hembra' : 'No registrado'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Edad:</div>
                    <div class="info-value">
                        <?php echo $edad !== null ? $edad . ' años' : 'No registrada'; ?>
                    </div>
                </div>
            </div>

            <div class="info-section">
                <h3>👤 Dueño</h3>
                <div class="info-row">
                    <div class="info-label">Nombre:</div>
                    <div class="info-value"><?php echo htmlspecialchars($mascota['nombre_dueno'] . ' ' . $mascota['ape_pat'] . ' ' . $mascota['ape_mat']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Teléfono:</div>
                    <div class="info-value"><?php echo $mascota['telefono']; ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Email:</div>
                    <div class="info-value"><?php echo $mascota['email'] ?: 'No registrado'; ?></div>
                </div>
            </div>

            <?php if ($ultima_cita): ?>
            <div class="info-section">
                <h3>📅 Última Cita</h3>
                <div class="ultima-cita">
                    <strong>📆 Fecha:</strong> <?php echo date('d/m/Y', strtotime($ultima_cita)); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($proxima): ?>
            <div class="info-section">
                <h3>📅 Próxima Cita</h3>
                <div class="proxima-cita">
                    <strong>📆 Fecha:</strong> <?php echo date('d/m/Y', strtotime($proxima['fecha_cita'])); ?><br>
                    <strong>⏰ Hora:</strong> <?php echo $proxima['hora_cita']; ?><br>
                    <strong>📌 Estado:</strong> <?php echo ucfirst($proxima['estado']); ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="info-section">
                <h3>📜 Historial de Citas</h3>
                <?php if ($historial->num_rows > 0): ?>
                    <?php while($cita = $historial->fetch_assoc()): ?>
                        <div class="historial-item">
                            <div class="historial-fecha"><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></div>
                            <div><?php echo $cita['servicios']; ?></div>
                            <div style="font-size: 12px; color: #666;">Estado: <?php echo ucfirst($cita['estado']); ?></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color: #999;">No hay citas registradas</p>
                <?php endif; ?>
            </div>
        </div>

        <button class="btn-imprimir" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
    </div>

    <div style="text-align: center; margin-top: 20px;">
        <a href="javascript:history.back()" class="btn-volver">← Volver</a>
    </div>
</body>
</html>