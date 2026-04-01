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

// Calcular edad
$edad = null;
if ($mascota['fecha_nacimiento']) {
    $fecha_nac = new DateTime($mascota['fecha_nacimiento']);
    $hoy = new DateTime();
    $edad = $fecha_nac->diff($hoy)->y;
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
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Arial, sans-serif;
            padding: 40px 20px;
        }
        .carnet {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .carnet-header {
            background: var(--primary);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .carnet-header h1 {
            margin: 0;
            font-size: 1.8rem;
        }
        .carnet-header p {
            margin: 5px 0 0;
            opacity: 0.9;
        }
        .foto {
            text-align: center;
            padding: 20px;
            background: #f9f9f9;
            border-bottom: 1px solid #eee;
        }
        .foto img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary);
        }
        .info {
            padding: 20px;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-section h3 {
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
            padding-bottom: 8px;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        .info-row {
            display: flex;
            margin-bottom: 10px;
        }
        .info-label {
            width: 100px;
            font-weight: bold;
            color: #555;
        }
        .info-value {
            flex: 1;
            color: #333;
        }
        .proxima-cita {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 12px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .historial-item {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .historial-fecha {
            font-weight: bold;
            color: var(--primary);
        }
        .btn-imprimir {
            display: block;
            width: calc(100% - 40px);
            margin: 0 20px 20px;
            background: var(--primary);
            color: white;
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            border: none;
            cursor: pointer;
        }
        .btn-imprimir:hover {
            background: var(--primary-dark);
        }
        .btn-volver {
            display: inline-block;
            margin: 10px auto 0;
            text-align: center;
            color: var(--primary);
            text-decoration: none;
        }
        .admin-barra {
            background: var(--primary-dark);
            color: white;
            padding: 8px 20px;
            text-align: center;
            font-size: 12px;
        }
        @media print {
            .btn-imprimir, .btn-volver, .admin-barra {
                display: none;
            }
            body {
                background: white;
                padding: 0;
            }
            .carnet {
                box-shadow: none;
            }
        }
    </style>
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
                    <div class="info-value"><?php echo $edad !== null ? $edad . ' años' : 'No registrada'; ?></div>
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