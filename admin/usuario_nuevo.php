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
            $sql_usuario = "INSERT INTO USUARIO (id_empleado, nombre_usuario, contrasena, rol, activo) 
                            VALUES (?, ?, ?, ?, 1)";
            $stmt_user = $conn->prepare($sql_usuario);
            // Verificar que la preparación fue exitosa
            if (!$stmt_user) {
                throw new Exception("Error preparando consulta: " . $conn->error);
            }
            $stmt_user->bind_param("isss", $id_empleado, $nombre_usuario, $contrasena, $rol);
            // Depuración adicional
            error_log("ID Empleado: $id_empleado");
            error_log("Usuario: $nombre_usuario");
            error_log("Contraseña hash: $contrasena");
            error_log("Rol: $rol");

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
    <link rel="stylesheet" href="../assets/css/usuario_nuevo.css">
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
                        <option value="recepcionista">Grooming</option>
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
                        <option value="recepcionista">Grooming</option>
                    </select>
                    <small>Super Admin solo puede ser asignado por el sistema</small>
                </div>
            </div>
            
            <button type="submit" class="btn" style="margin-top: 20px;">Crear Usuario</button>
        </form>
    </div>
    <script src="../assets/js/usuario_nuevo.js"></script>
</body>
</html>