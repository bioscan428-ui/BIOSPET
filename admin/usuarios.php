<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar que sea super_admin
if ($_SESSION['rol'] !== 'super_admin') {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener todos los usuarios usando la vista
$sql = "SELECT * FROM vista_empleados_activos ORDER BY puesto, nombre";
$result = $conn->query($sql);

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'activar':
                $id = (int)$_POST['id'];
                $conn->query("UPDATE USUARIO SET activo = 1 WHERE id_empleado = $id");
                break;
            case 'desactivar':
                $id = (int)$_POST['id'];
                $conn->query("UPDATE USUARIO SET activo = 0 WHERE id_empleado = $id");
                break;
            case 'cambiar_rol':
                $id = (int)$_POST['id'];
                $rol = $_POST['rol'];
                $conn->query("UPDATE USUARIO SET rol = '$rol' WHERE id_empleado = $id");
                break;
        }
        header('Location: usuarios.php');
        exit;
    }
}

// Obtener detalles del empleado para el modal (vía AJAX o directamente)
$detalle_empleado = null;
if (isset($_GET['ver_historial']) && is_numeric($_GET['ver_historial'])) {
    $empleado_id = (int)$_GET['ver_historial'];
    
    // Usar la vista para obtener información completa
    $sql_detalle = "SELECT * FROM vista_empleados_activos WHERE id = ?";
    $stmt_detalle = $conn->prepare($sql_detalle);
    $stmt_detalle->bind_param("i", $empleado_id);
    $stmt_detalle->execute();
    $detalle_empleado = $stmt_detalle->get_result()->fetch_assoc();
    
    // Obtener citas del empleado
    $sql_citas = "SELECT 
                    c.id, c.fecha_cita, c.hora_cita, c.estado,
                    m.nombre_mascota,
                    cl.nombre AS dueno
                  FROM ASIGNACION_CITA ac
                  JOIN CITA c ON ac.id_cita = c.id
                  JOIN MASCOTA m ON c.id_mascota = m.id
                  JOIN CLIENTE cl ON m.id_cliente = cl.id
                  WHERE ac.id_empleado = ?
                  ORDER BY c.fecha_cita DESC, c.hora_cita DESC
                  LIMIT 20";
    $stmt_citas = $conn->prepare($sql_citas);
    $stmt_citas->bind_param("i", $empleado_id);
    $stmt_citas->execute();
    $citas_empleado = $stmt_citas->get_result();
    
    // Obtener horario del empleado
    $sql_horario = "SELECT * FROM HORARIO_EMPLEADO 
                    WHERE id_empleado = ? AND activo = 1
                    ORDER BY FIELD(dia_semana, 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo')";
    $stmt_horario = $conn->prepare($sql_horario);
    $stmt_horario->bind_param("i", $empleado_id);
    $stmt_horario->execute();
    $horario_empleado = $stmt_horario->get_result();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/usuarios.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Gestión de Usuarios</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            
            <!-- Dropdown Empleados -->
            <div class="dropdown">
                <a href="javascript:void(0)">👥 Empleados ▼</a>
                <div class="dropdown-content">
                    <a href="usuarios.php">📋 Lista de Usuarios</a>
                    <a href="empleados_sin_usuario.php">➕ Empleados sin usuario</a>
                    <hr style="margin: 5px 0; border-color: #eee;">
                    <a href="horarios_empleado.php">🕐 Horarios de Empleados</a>
                </div>
            </div>
            
            <a href="productos.php">🛒 Productos</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <div style="display: flex; gap: 10px; margin-bottom: 20px;">
            <a href="usuario_nuevo.php" class="btn-nuevo">+ Nuevo Usuario</a>
        </div>
        
        <table class="usuarios-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Email</th>
                    <th>Puesto</th>
                    <th>Rol</th>
                    <th>Citas</th>
                    <th>Estado</th>
                    <th>Último Acceso</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while($user = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['nombre'] . ' ' . $user['ape_pat']); ?></span>
                    <td><?php echo htmlspecialchars($user['nombre_usuario'] ?? 'Sin usuario'); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></span>
                    <td><?php echo $user['puesto']; ?></td>
                    <td>
                        <span class="rol-<?php echo $user['rol'] ?? 'recepcionista'; ?>">
                            <?php 
                            $roles = [
                                'super_admin' => 'Super Admin',
                                'admin' => 'Admin',
                                'veterinario' => 'Veterinario',
                                'asistente' => 'Asistente',
                                'recepcionista' => 'Recepcionista'
                            ];
                            echo $roles[$user['rol']] ?? ($user['rol'] ?? 'Sin rol');
                            ?>
                        </span>
                    </td>
                    <td style="text-align: center;">
                        <span class="badge" style="background: #17a2b8;">
                            <?php echo $user['citas_asignadas'] ?? 0; ?> citas
                        </span>
                    </span>
                    <td class="<?php echo ($user['activo'] ?? 1) ? 'activo' : 'inactivo'; ?>">
                        <?php echo ($user['activo'] ?? 1) ? '✅ Activo' : '❌ Inactivo'; ?>
                    </span>
                    <td><?php echo $user['ultimo_acceso'] ?: 'Nunca'; ?></td>
                    <td>
                        <!-- Botón Historial -->
                        <button onclick="verHistorial(<?php echo $user['id']; ?>)" class="btn-historial">
                            📊 Historial
                        </button>
                        <button onclick="verExpediente(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['nombre'] . ' ' . $user['ape_pat']); ?>')" class="btn-expediente">
                            📁 Expediente
                        </button>
                        
                        <?php if ($user['nombre_usuario']): ?>
                        <form method="POST" style="display: inline-block;">
                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                            <?php if (($user['rol'] ?? '') !== 'super_admin'): ?>
                                <select name="rol" class="select-rol" onchange="this.form.submit()">
                                    <option value="">Cambiar rol</option>
                                    <option value="super_admin">Super Administrador</option>
                                    <option value="admin">Admin</option>
                                    <option value="veterinario">Veterinario</option>
                                    <option value="asistente">Asistente</option>
                                    <option value="recepcionista">Recepcionista</option>
                                    <option value="grooming">Grooming</option>
                                    <option value="caja">Caja</option>
                                </select>
                                <input type="hidden" name="action" value="cambiar_rol">
                            <?php endif; ?>
                        </form>
                        
                        <form method="POST" style="display: inline-block;">
                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                            <?php if ($user['activo'] ?? 1): ?>
                                <button type="submit" name="action" value="desactivar" class="btn-accion btn-desactivar" onclick="return confirm('¿Desactivar este usuario?')">Desactivar</button>
                            <?php else: ?>
                                <button type="submit" name="action" value="activar" class="btn-accion btn-activar" onclick="return confirm('¿Activar este usuario?')">Activar</button>
                            <?php endif; ?>
                        </form>
                        <?php endif; ?>
                    </span>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal de Historial -->
    <div id="historialModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📊 Historial del Empleado</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body" id="modalBody">
                <div style="text-align: center; padding: 40px;">
                    Cargando...
                </div>
            </div>
        </div>
    </div>
    <!-- Modal de Expediente -->
    <div id="expedienteModal" class="modal">
        <div class="modal-content modal-expediente">
            <div class="modal-header">
                <h2>📁 Expediente de <span id="expedienteEmpleadoNombre"></span></h2>
                <span class="close-expediente">&times;</span>
            </div>
            <div class="modal-body" id="expedienteBody">
                <div style="text-align: center; padding: 40px;">
                    Cargando...
                </div>
            </div>
        </div>
    </div>

    <script>
        // Modal functionality
        const modal = document.getElementById('historialModal');
        const closeBtn = document.getElementsByClassName('close')[0];
        
        function verHistorial(empleadoId) {
            // Mostrar modal con loader
            modal.style.display = 'block';
            document.getElementById('modalBody').innerHTML = '<div style="text-align: center; padding: 40px;">Cargando...</div>';
            
            // Cargar datos vía AJAX
            fetch(`get_historial_empleado.php?id=${empleadoId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('modalBody').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('modalBody').innerHTML = '<div style="color: red; text-align: center; padding: 40px;">Error al cargar los datos</div>';
                });
        }
        
        closeBtn.onclick = function() {
            modal.style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Modal de Expediente
const expedienteModal = document.getElementById('expedienteModal');
const closeExpediente = document.getElementsByClassName('close-expediente')[0];
let empleadoIdActual = 0;

function verExpediente(empleadoId, empleadoNombre) {
    empleadoIdActual = empleadoId;
    document.getElementById('expedienteEmpleadoNombre').innerText = empleadoNombre;
    expedienteModal.style.display = 'block';
    cargarExpediente(empleadoId);
}

function cargarExpediente(empleadoId) {
    document.getElementById('expedienteBody').innerHTML = '<div style="text-align: center; padding: 40px;">Cargando documentos...</div>';
    
    fetch(`expediente_empleado.php?id=${empleadoId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('expedienteBody').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('expedienteBody').innerHTML = '<div style="color: red; text-align: center; padding: 40px;">Error al cargar los documentos</div>';
        });
}

function subirDocumento() {
    const formData = new FormData();
    formData.append('id_empleado', empleadoIdActual);
    formData.append('tipo_documento', document.getElementById('tipo_documento').value);
    formData.append('descripcion', document.getElementById('descripcion_documento').value);
    formData.append('archivo', document.getElementById('archivo_documento').files[0]);
    
    fetch('subir_expediente.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Documento subido correctamente');
            cargarExpediente(empleadoIdActual);
            document.getElementById('archivo_documento').value = '';
            document.getElementById('descripcion_documento').value = '';
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        alert('Error al subir el documento');
    });
}

function eliminarDocumento(documentoId) {
    if (confirm('¿Eliminar este documento permanentemente?')) {
        fetch('eliminar_expediente.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + documentoId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Documento eliminado');
                cargarExpediente(empleadoIdActual);
            } else {
                alert('Error: ' + data.error);
            }
        });
    }
}

closeExpediente.onclick = function() {
    expedienteModal.style.display = 'none';
}
    </script>
</body>
</html>