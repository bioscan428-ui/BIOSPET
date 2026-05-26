<?php
session_start();
$dashboard_link = ($_SESSION['rol'] === 'caja') ? 'caja_dashboard.php' : 'dashboard.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol
if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'caja'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Procesar acciones
$accion = $_GET['accion'] ?? '';
$id_categoria = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    if ($accion === 'editar' && $id_categoria > 0) {
        // Actualizar categoría
        $sql = "UPDATE CATEGORIA_PRODUCTO SET nombre = ?, descripcion = ?, activo = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $nombre, $descripcion, $activo, $id_categoria);
        
        if ($stmt->execute()) {
            $_SESSION['mensaje'] = "Categoría actualizada correctamente";
            $_SESSION['tipo_mensaje'] = "exito";
        } else {
            $_SESSION['mensaje'] = "Error al actualizar: " . $conn->error;
            $_SESSION['tipo_mensaje'] = "error";
        }
        $stmt->close();
        header('Location: categorias_productos.php');
        exit;
        
    } elseif ($accion === 'nueva') {
        // Crear nueva categoría
        $sql = "INSERT INTO CATEGORIA_PRODUCTO (nombre, descripcion, activo) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $nombre, $descripcion, $activo);
        
        if ($stmt->execute()) {
            $_SESSION['mensaje'] = "Categoría creada correctamente";
            $_SESSION['tipo_mensaje'] = "exito";
        } else {
            $_SESSION['mensaje'] = "Error al crear: " . $conn->error;
            $_SESSION['tipo_mensaje'] = "error";
        }
        $stmt->close();
        header('Location: categorias_productos.php');
        exit;
    }
}

// Eliminar categoría (solo si no tiene productos asociados)
if ($accion === 'eliminar' && $id_categoria > 0) {
    // Verificar si tiene productos
    $check = $conn->prepare("SELECT COUNT(*) as total FROM PRODUCTO WHERE id_categoria = ?");
    $check->bind_param("i", $id_categoria);
    $check->execute();
    $result = $check->get_result();
    $total_productos = $result->fetch_assoc()['total'];
    $check->close();
    
    if ($total_productos > 0) {
        $_SESSION['mensaje'] = "No se puede eliminar la categoría porque tiene $total_productos productos asociados. Desactívala en su lugar.";
        $_SESSION['tipo_mensaje'] = "error";
    } else {
        $sql = "DELETE FROM CATEGORIA_PRODUCTO WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_categoria);
        if ($stmt->execute()) {
            $_SESSION['mensaje'] = "Categoría eliminada correctamente";
            $_SESSION['tipo_mensaje'] = "exito";
        } else {
            $_SESSION['mensaje'] = "Error al eliminar: " . $conn->error;
            $_SESSION['tipo_mensaje'] = "error";
        }
        $stmt->close();
    }
    header('Location: categorias_productos.php');
    exit;
}

// Obtener todas las categorías
$sql = "SELECT c.*, COUNT(p.id) as total_productos 
        FROM CATEGORIA_PRODUCTO c
        LEFT JOIN PRODUCTO p ON c.id = p.id_categoria AND p.activo = 1
        GROUP BY c.id
        ORDER BY c.nombre ASC";
$categorias = $conn->query($sql);

// Si hay que editar, obtener datos de la categoría
$categoria_editar = null;
if ($accion === 'editar' && $id_categoria > 0) {
    $sql_edit = "SELECT * FROM CATEGORIA_PRODUCTO WHERE id = ?";
    $stmt_edit = $conn->prepare($sql_edit);
    $stmt_edit->bind_param("i", $id_categoria);
    $stmt_edit->execute();
    $categoria_editar = $stmt_edit->get_result()->fetch_assoc();
    $stmt_edit->close();
}

// Mensajes de sesión
$mensaje = $_SESSION['mensaje'] ?? '';
$tipo_mensaje = $_SESSION['tipo_mensaje'] ?? '';
unset($_SESSION['mensaje']);
unset($_SESSION['tipo_mensaje']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías de Productos - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        body { background: #f5f5f5; }
        .admin-header { background: #E68D0B; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .admin-header a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 1000px; margin: 30px auto; background: white; border-radius: 15px; padding: 25px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        h1 { color: var(--primary); margin-bottom: 20px; }
        .btn-nuevo { background: #4caf50; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-block; margin-bottom: 20px; }
        .btn-nuevo:hover { background: #45a049; }
        .btn-editar { background: #2196f3; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none; font-size: 12px; }
        .btn-eliminar { background: #f44336; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none; font-size: 12px; margin-left: 5px; }
        .btn-editar:hover { background: #0b7dda; }
        .btn-eliminar:hover { background: #da190b; }
        .categorias-table { width: 100%; border-collapse: collapse; }
        .categorias-table th, .categorias-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .categorias-table th { background: #f8f9fa; font-weight: bold; color: #666; }
        .categorias-table tr:hover { background: #f8f9fa; }
        .badge-activo { background: #4caf50; color: white; padding: 3px 8px; border-radius: 12px; font-size: 11px; display: inline-block; }
        .badge-inactivo { background: #f44336; color: white; padding: 3px 8px; border-radius: 12px; font-size: 11px; display: inline-block; }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 500px;
            max-width: 90%;
        }
        .modal-content h3 { color: var(--primary); margin-bottom: 20px; }
        .modal-content input, .modal-content textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin: 10px 0;
        }
        .modal-buttons { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .btn-guardar { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; }
        .btn-cancelar { background: #666; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; }
        .mensaje-exito { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #28a745; }
        .mensaje-error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #dc3545; }
        .total-card {
            background: linear-gradient(135deg, #E68D0B, #f0a33a);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .total-card .numero { font-size: 2rem; font-weight: bold; }
        .total-card .label { font-size: 0.9rem; opacity: 0.9; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Categorías de Productos</h1>
        <div>
            <a href="<?php echo $dashboard_link; ?>">📋 Dashboard</a>
            <a href="productos.php">🛒 Productos</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <!-- Tarjeta de total -->
        <div class="total-card">
            <div>
                <div class="label">Total de categorías</div>
                <div class="numero"><?php echo $categorias->num_rows; ?></div>
            </div>
            <div>📁</div>
        </div>

        <!-- Mensajes -->
        <?php if ($mensaje): ?>
            <div class="mensaje-<?php echo $tipo_mensaje; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <!-- Botón nueva categoría -->
        <button id="btnNuevaCategoria" class="btn-nuevo">+ Nueva Categoría</button>

        <!-- Tabla de categorías -->
        <table class="categorias-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Productos</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($categorias && $categorias->num_rows > 0): ?>
                    <?php while($cat = $categorias->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $cat['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($cat['nombre']); ?></strong></td>
                        <td><?php echo htmlspecialchars($cat['descripcion'] ?: '—'); ?></td>
                        <td>
                            <a href="productos.php?categoria=<?php echo $cat['id']; ?>" style="text-decoration: none; color: #2196f3;">
                                <?php echo $cat['total_productos']; ?> productos
                            </a>
                        </span>
                    <td>
                        <?php if ($cat['activo']): ?>
                            <span class="badge-activo">✅ Activo</span>
                        <?php else: ?>
                            <span class="badge-inactivo">❌ Inactivo</span>
                        <?php endif; ?>
                    </span>
                <td>
                    <a href="?accion=editar&id=<?php echo $cat['id']; ?>" class="btn-editar">✏️ Editar</a>
                    <a href="?accion=eliminar&id=<?php echo $cat['id']; ?>" class="btn-eliminar" onclick="return confirm('¿Eliminar esta categoría?')">🗑️ Eliminar</a>
                    </span>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #999;">
                            No hay categorías registradas
                        </span>
                    </tr>
                    <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f0f0f0; font-weight: bold;">
                    <td colspan="3" style="text-align: right;">TOTAL DE PRODUCTOS:</td>
                    <td>
                        <?php
                        // Calcular suma total de productos
                        $sql_total = "SELECT COUNT(p.id) as total
                                        FROM CATEGORIA_PRODUCTO c
                                        LEFT JOIN PRODUCTO p ON c.id = p.id_categoria AND p.activo = 1";
                        $total_result = $conn->query($sql_total);
                        $total_productos_general = $total_result->fetch_assoc()['total'];
                        echo $total_productos_general . ' productos';
                        ?>
                    </td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Modal para nueva categoría -->
    <div id="modalNuevaCategoria" class="modal">
        <div class="modal-content">
            <h3>➕ Nueva Categoría</h3>
            <form method="POST" action="?accion=nueva">
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" required placeholder="Ej: Alimentos, Medicamentos, Accesorios">
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="descripcion" rows="3" placeholder="Descripción de la categoría"></textarea>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="activo" checked> Categoría activa
                    </label>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn-cancelar" id="btnCancelarNueva">Cancelar</button>
                    <button type="submit" class="btn-guardar">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para editar categoría -->
    <div id="modalEditarCategoria" class="modal">
        <div class="modal-content">
            <h3>✏️ Editar Categoría</h3>
            <form method="POST" action="?accion=editar&id=<?php echo $categoria_editar['id'] ?? ''; ?>">
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($categoria_editar['nombre'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="descripcion" rows="3"><?php echo htmlspecialchars($categoria_editar['descripcion'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="activo" value="1" <?php echo ($categoria_editar['activo'] ?? 0) ? 'checked' : ''; ?>> Categoría activa
                    </label>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn-cancelar" id="btnCancelarEditar">Cancelar</button>
                    <button type="submit" class="btn-guardar">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal nueva categoría
        const modalNueva = document.getElementById('modalNuevaCategoria');
        const btnNueva = document.getElementById('btnNuevaCategoria');
        const btnCancelarNueva = document.getElementById('btnCancelarNueva');
        
        if (btnNueva) {
            btnNueva.addEventListener('click', () => {
                modalNueva.classList.add('active');
            });
        }
        
        if (btnCancelarNueva) {
            btnCancelarNueva.addEventListener('click', () => {
                modalNueva.classList.remove('active');
            });
        }
        
        // Modal editar categoría
        const modalEditar = document.getElementById('modalEditarCategoria');
        const btnCancelarEditar = document.getElementById('btnCancelarEditar');
        
        <?php if ($categoria_editar): ?>
        if (modalEditar) {
            modalEditar.classList.add('active');
        }
        <?php endif; ?>
        
        if (btnCancelarEditar) {
            btnCancelarEditar.addEventListener('click', () => {
                modalEditar.classList.remove('active');
                window.location.href = 'categorias_productos.php';
            });
        }
        
        // Cerrar modales al hacer clic fuera
        window.onclick = function(event) {
            if (event.target == modalNueva) modalNueva.classList.remove('active');
            if (event.target == modalEditar) modalEditar.classList.remove('active');
        }
    </script>
</body>
</html>