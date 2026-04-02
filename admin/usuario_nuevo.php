<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener empleados sin usuario (solo para mostrar en el select)
$sql_empleados = "SELECT e.* FROM EMPLEADO e 
                  LEFT JOIN USUARIO u ON e.id = u.id_empleado 
                  WHERE u.id IS NULL AND e.activo = 1";
$empleados = $conn->query($sql_empleados);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determinar si se está creando un empleado nuevo o usando uno existente
    $opcion = $_POST['opcion'] ?? 'nuevo';
    
    if ($opcion === 'nuevo') {
        // ========== CREAR NUEVO EMPLEADO + USUARIO ==========
        $nombre = trim($_POST['nombre']);
        $ape_pat = trim($_POST['ape_pat'] ?? '');
        $ape_mat = trim($_POST['ape_mat'] ?? '');
        $email = trim($_POST['email']);
        $telefono = trim($_POST['telefono'] ?? '');
        $puesto = $_POST['puesto'];
        $especialidad = trim($_POST['especialidad'] ?? '');
        $fecha_contratacion = $_POST['fecha_contratacion'];
        
        $nombre_usuario = trim($_POST['nombre_usuario']);
        $contrasena = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);
        $rol = $_POST['rol'];
        
        // Iniciar transacción
        $conn->begin_transaction();
        
        try {
            // 1. Insertar en EMPLEADO
            $sql_empleado = "INSERT INTO EMPLEADO (nombre, ape_pat, ape_mat, email, telefono, puesto, especialidad, fecha_contratacion, activo) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
            $stmt_emp = $conn->prepare($sql_empleado);
            $stmt_emp->bind_param("ssssssss", $nombre, $ape_pat, $ape_mat, $email, $telefono, $puesto, $especialidad, $fecha_contratacion);
            $stmt_emp->execute();
            $id_empleado = $conn->insert_id;
            
            // 2. Insertar en USUARIO
            $sql_usuario = "INSERT INTO USUARIO (id_empleado, nombre_usuario, contrasena, rol) VALUES (?, ?, ?, ?)";
            $stmt_user = $conn->prepare($sql_usuario);
            $stmt_user->bind_param("isss", $id_empleado, $nombre_usuario, $contrasena, $rol);
            $stmt_user->execute();
            
            $conn->commit();
            header('Location: usuarios.php?success=1');
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error al crear empleado y usuario: " . $e->getMessage();
        }
        
    } else {
        // ========== USAR EMPLEADO EXISTENTE ==========
        $id_empleado = (int)$_POST['id_empleado'];
        $nombre_usuario = trim($_POST['nombre_usuario']);
        $contrasena = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);
        $rol = $_POST['rol'];
        
        $sql = "INSERT INTO USUARIO (id_empleado, nombre_usuario, contrasena, rol) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $id_empleado, $nombre_usuario, $contrasena, $rol);
        
        if ($stmt->execute()) {
            header('Location: usuarios.php?success=1');
            exit;
        } else {
            $error = "Error al crear usuario: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Usuario / Empleado - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { background: var(--muted); }
        .admin-header { background: var(--primary); color: white; padding: 20px; }
        .admin-header a { color: white; margin-left: 20px; }
        .container { max-width: 700px; margin: 20px auto; background: white; padding: 30px; border-radius: var(--radius-md); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: var(--radius-sm); }
        .btn { background: var(--primary); color: white; padding: 12px 30px; border: none; border-radius: var(--radius-sm); cursor: pointer; }
        .error { color: red; margin-bottom: 15px; }
        .opcion-group { display: flex; gap: 20px; margin-bottom: 20px; }
        .opcion-group label { display: inline-block; margin-left: 5px; font-weight: normal; }
        .seccion-empleado { border: 1px solid #ddd; padding: 15px; border-radius: var(--radius-sm); margin-top: 15px; }
        .seccion-empleado h4 { margin-top: 0; color: var(--primary); }
    </style>
    <script>
        function toggleFormulario() {
            const opcion = document.querySelector('input[name="opcion"]:checked').value;
            const seccionNuevo = document.getElementById('seccion-nuevo');
            const seccionExistente = document.getElementById('seccion-existente');
            const selectExistente = document.getElementById('id_empleado');
            
            if (opcion === 'nuevo') {
                seccionNuevo.style.display = 'block';
                seccionExistente.style.display = 'none';
                // Remover required del select existente
                if (selectExistente) {
                    selectExistente.removeAttribute('required');
                }
                // Agregar required a campos de nuevo empleado
                document.querySelectorAll('#seccion-nuevo input, #seccion-nuevo select').forEach(input => {
                    if (input.hasAttribute('data-required')) {
                        input.setAttribute('required', 'required');
                    }
                });
            } else {
                seccionNuevo.style.display = 'none';
                seccionExistente.style.display = 'block';
                // Agregar required al select existente
                if (selectExistente) {
                    selectExistente.setAttribute('required', 'required');
                }
                // Remover required de campos de nuevo empleado
                document.querySelectorAll('#seccion-nuevo input, #seccion-nuevo select').forEach(input => {
                    input.removeAttribute('required');
                });
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Marcar campos de nuevo empleado como requeridos condicionalmente
            document.querySelectorAll('#seccion-nuevo input, #seccion-nuevo select').forEach(input => {
                if (input.hasAttribute('required')) {
                    input.setAttribute('data-required', 'true');
                    input.removeAttribute('required');
                }
            });
            
            toggleFormulario();
            
            document.querySelectorAll('input[name="opcion"]').forEach(radio => {
                radio.addEventListener('change', toggleFormulario);
            });
        });
    </script>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Nuevo Usuario / Empleado</h1>
        <a href="usuarios.php">← Volver</a>
    </div>

    <div class="container">
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
        
        <form method="POST">
            <div class="opcion-group">
                <label>
                    <input type="radio" name="opcion" value="nuevo" checked> Crear nuevo empleado + usuario
                </label>
                <label>
                    <input type="radio" name="opcion" value="existente"> Usar empleado existente (sin usuario)
                </label>
            </div>
            
            <!-- Sección: Crear nuevo empleado -->
            <div id="seccion-nuevo" class="seccion-empleado">
                <h4>📋 Datos del Empleado</h4>
                <div class="form-group">
                    <label>Nombre(s) *</label>
                    <input type="text" name="nombre" required>
                </div>
                <div class="form-group">
                    <label>Apellido Paterno</label>
                    <input type="text" name="ape_pat">
                </div>
                <div class="form-group">
                    <label>Apellido Materno</label>
                    <input type="text" name="ape_mat">
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="tel" name="telefono">
                </div>
                <div class="form-group">
                    <label>Puesto *</label>
                    <select name="puesto" required>
                        <option value="admin">Administrador</option>
                        <option value="veterinario">Veterinario</option>
                        <option value="asistente">Asistente</option>
                        <option value="recepcionista">Recepcionista</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Especialidad (solo veterinarios)</label>
                    <input type="text" name="especialidad" placeholder="Ej: Radiología, Cirugía">
                </div>
                <div class="form-group">
                    <label>Fecha de Contratación *</label>
                    <input type="date" name="fecha_contratacion" required>
                </div>
            </div>
            
            <!-- Sección: Usar empleado existente -->
            <div id="seccion-existente" class="seccion-employado" style="display:none;">
                <h4>📋 Seleccionar Empleado</h4>
                <div class="form-group">
                    <label>Empleado *</label>
                    <select name="id_empleado" id="id_empleado">
                        <option value="">Seleccionar empleado...</option>
                        <?php while($emp = $empleados->fetch_assoc()): ?>
                            <option value="<?php echo $emp['id']; ?>">
                                <?php echo htmlspecialchars($emp['nombre'] . ' ' . $emp['ape_pat'] . ' - ' . $emp['puesto']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <!-- Datos de Usuario (comunes a ambas opciones) -->
            <div class="seccion-empleado" style="margin-top: 20px;">
                <h4>🔐 Datos de Acceso</h4>
                <div class="form-group">
                    <label>Nombre de usuario *</label>
                    <input type="text" name="nombre_usuario" required>
                </div>
                <div class="form-group">
                    <label>Contraseña *</label>
                    <input type="password" name="contrasena" required>
                </div>
                <div class="form-group">
                    <label>Rol en el sistema *</label>
                    <select name="rol" required>
                        <option value="admin">Administrador</option>
                        <option value="veterinario">Veterinario</option>
                        <option value="asistente">Asistente</option>
                        <option value="recepcionista">Recepcionista</option>
                    </select>
                    <small>Super Admin solo puede ser asignado por el sistema</small>
                </div>
            </div>
            
            <button type="submit" class="btn" style="margin-top: 20px;">Crear Usuario</button>
        </form>
    </div>
</body>
</html>