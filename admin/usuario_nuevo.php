<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log para depuración
error_log("=== INICIO DE usuario_nuevo.php ===");
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener TODOS los empleados activos (para la opción "nuevo_rol")
$sql_todos_empleados = "SELECT e.* FROM EMPLEADO e WHERE e.activo = 1 ORDER BY e.nombre ASC";
$todos_empleados = $conn->query($sql_todos_empleados);

// Obtener empleados sin usuario (para la opción "existente")
$sql_empleados_sin_usuario = "SELECT e.* FROM EMPLEADO e 
                              LEFT JOIN USUARIO u ON e.id = u.id_empleado 
                              WHERE u.id IS NULL AND e.activo = 1";
$empleados_sin_usuario = $conn->query($sql_empleados_sin_usuario);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("=== POST RECIBIDO ===");
    error_log("Opción: " . ($_POST['opcion'] ?? 'no'));
    error_log("POST completo: " . print_r($_POST, true));
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
            $sql_usuario = "INSERT INTO USUARIO (id_empleado, nombre_usuario, contrasena, rol, activo) 
                            VALUES (?, ?, ?, ?, 1)";
            $stmt_user = $conn->prepare($sql_usuario);
            if (!$stmt_user) {
                throw new Exception("Error preparando consulta: " . $conn->error);
            }
            $stmt_user->bind_param("isss", $id_empleado, $nombre_usuario, $contrasena, $rol);
            
            if (!$stmt_user->execute()) {
                throw new Exception("Error al insertar usuario: " . $stmt_user->error);
            }
            
            $conn->commit();
            header('Location: usuarios.php?success=1');
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error al crear empleado y usuario: " . $e->getMessage();
        }
        
    } elseif ($opcion === 'existente') {
        // ========== USAR EMPLEADO EXISTENTE (SIN USUARIO) ==========
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
        
    } elseif ($opcion === 'nuevo_rol') {
        // ========== NUEVO ROL PARA EMPLEADO EXISTENTE ==========
        error_log("=== PROCESANDO NUEVO ROL ===");
        $id_empleado = (int)$_POST['id_empleado_rol'];
        $nombre_usuario = trim($_POST['nombre_usuario_rol']);
        $contrasena = password_hash($_POST['contrasena_rol'], PASSWORD_DEFAULT);
        $rol = $_POST['rol_rol'];

        error_log("ID Empleado: $id_empleado");
        error_log("Usuario: $nombre_usuario");
        error_log("Rol: $rol");
        
        // Verificar que el nombre de usuario no exista
        $check = $conn->prepare("SELECT id FROM USUARIO WHERE nombre_usuario = ?");
        $check->bind_param("s", $nombre_usuario);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "❌ El nombre de usuario '$nombre_usuario' ya existe. Elige otro.";
        } else {
            $sql = "INSERT INTO USUARIO (id_empleado, nombre_usuario, contrasena, rol, activo) VALUES (?, ?, ?, ?, 1)";
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
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Usuario / Empleado - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/usuario_nuevo.css">
    <style>
        .seccion-empleado, .seccion-employado {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-top: 15px;
            background: #f9f9f9;
        }
        .opcion-group {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .opcion-group label {
            cursor: pointer;
            padding: 8px 15px;
            background: #f0f0f0;
            border-radius: 8px;
        }
        .opcion-group label:hover {
            background: #e0e0e0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .btn {
            background: #E68D0B;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #cc7a00;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }
        h4 {
            margin-bottom: 15px;
            color: #E68D0B;
        }
    </style>
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
                <label>
                    <input type="radio" name="opcion" value="nuevo_rol"> Nuevo rol para empleado existente
                </label>
            </div>
            
            <!-- ========== SECCIÓN 1: CREAR NUEVO EMPLEADO ========== -->
            <div id="seccion-nuevo" class="seccion-empleado">
                <h4>📋 Datos del Empleado</h4>
                <div class="form-group">
                    <label>Nombre(s) *</label>
                    <input type="text" name="nombre">
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
                    <input type="email" name="email">
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="tel" name="telefono">
                </div>
                <div class="form-group">
                    <label>Puesto *</label>
                    <select name="puesto" required>
                        <option value="super_admin">Super Administrador</option>
                        <option value="admin">Administrador</option>
                        <option value="veterinario">Veterinario</option>
                        <option value="asistente">Asistente</option>
                        <option value="recepcionista">Recepcionista</option>
                        <option value="grooming">Grooming</option>
                        <option value="caja">Caja</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Especialidad (solo veterinarios)</label>
                    <input type="text" name="especialidad" placeholder="Ej: Radiología, Cirugía">
                </div>
                <div class="form-group">
                    <label>Fecha de Contratación *</label>
                    <input type="date" name="fecha_contratacion">
                </div>
                
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
                        <option value="grooming">Grooming</option>
                        <option value="caja">Caja</option>
                    </select>
                    <small>Super Admin solo puede ser asignado por el sistema</small>
                </div>
            </div>
            
            <!-- ========== SECCIÓN 2: USAR EMPLEADO EXISTENTE (SIN USUARIO) ========== -->
            <div id="seccion-existente" class="seccion-empleado" style="display:none;">
                <h4>📋 Seleccionar Empleado</h4>
                <div class="form-group">
                    <label>Empleado *</label>
                    <select name="id_empleado">
                        <option value="">Seleccionar empleado...</option>
                        <?php 
                        $empleados_sin_usuario->data_seek(0);
                        while($emp = $empleados_sin_usuario->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $emp['id']; ?>">
                                <?php echo htmlspecialchars($emp['nombre'] . ' ' . $emp['ape_pat'] . ' - ' . $emp['puesto']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <small>⚠️ Solo empleados que aún NO tienen usuario asignado</small>
                </div>
                
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
                        <option value="grooming">Grooming</option>
                        <option value="caja">Caja</option>
                    </select>
                </div>
            </div>
            
            <!-- ========== SECCIÓN 3: NUEVO ROL PARA EMPLEADO EXISTENTE ========== -->
            <div id="seccion-nuevo-rol" class="seccion-empleado" style="display:none;">
                <h4>📋 Seleccionar Empleado</h4>
                <div class="form-group">
                    <label>Empleado *</label>
                    <select name="id_empleado_rol">
                        <option value="">Seleccionar empleado...</option>
                        <?php 
                        $todos_empleados->data_seek(0);
                        while($emp = $todos_empleados->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $emp['id']; ?>">
                                <?php echo htmlspecialchars($emp['nombre'] . ' ' . $emp['ape_pat'] . ' - ' . $emp['puesto']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <small>⚠️ Este empleado ya puede tener otros roles activos</small>
                </div>
                
                <h4>🔐 Nuevo rol de acceso</h4>
                <div class="form-group">
                    <label>Nombre de usuario *</label>
                    <input type="text" name="nombre_usuario_rol" required placeholder="Ej: maria_admin">
                </div>
                <div class="form-group">
                    <label>Contraseña *</label>
                    <input type="password" name="contrasena_rol" required>
                </div>
                <div class="form-group">
                    <label>Rol en el sistema *</label>
                    <select name="rol_rol" required>
                        <option value="admin">Administrador</option>
                        <option value="veterinario">Veterinario</option>
                        <option value="asistente">Asistente</option>
                        <option value="recepcionista">Recepcionista</option>
                        <option value="grooming">Grooming</option>
                        <option value="caja">Caja</option>
                    </select>
                    <small>Super Admin solo puede ser asignado por el sistema</small>
                </div>
            </div>
            
            <button type="submit" class="btn" style="margin-top: 20px;">Crear Usuario</button>
        </form>
    </div>
    
    <script>
    // Mostrar/ocultar secciones según la opción seleccionada
    const radios = document.querySelectorAll('input[name="opcion"]');
    const seccionNuevo = document.getElementById('seccion-nuevo');
    const seccionExistente = document.getElementById('seccion-existente');
    const seccionNuevoRol = document.getElementById('seccion-nuevo-rol');
    
    function toggleSecciones() {
        const selected = document.querySelector('input[name="opcion"]:checked').value;
        
        // Ocultar todas las secciones
        seccionNuevo.style.display = 'none';
        seccionExistente.style.display = 'none';
        seccionNuevoRol.style.display = 'none';
        
        // Deshabilitar TODOS los campos de todas las secciones
        deshabilitarCampos(seccionNuevo, true);
        deshabilitarCampos(seccionExistente, true);
        deshabilitarCampos(seccionNuevoRol, true);
        
        // Mostrar la sección seleccionada y habilitar sus campos
        if (selected === 'nuevo') {
            seccionNuevo.style.display = 'block';
            deshabilitarCampos(seccionNuevo, false);
        } else if (selected === 'existente') {
            seccionExistente.style.display = 'block';
            deshabilitarCampos(seccionExistente, false);
        } else if (selected === 'nuevo_rol') {
            seccionNuevoRol.style.display = 'block';
            deshabilitarCampos(seccionNuevoRol, false);
        }
    }
    
    function deshabilitarCampos(seccion, deshabilitar) {
        if (!seccion) return;
        const inputs = seccion.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (deshabilitar) {
                input.disabled = true;
            } else {
                input.disabled = false;
            }
        });
    }
    
    radios.forEach(radio => {
        radio.addEventListener('change', toggleSecciones);
    });
    
    // Ejecutar al cargar
    toggleSecciones();
</script>
</body>
</html>