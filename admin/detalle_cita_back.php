<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol para acceso
if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'veterinario', 'asistente', 'recepcionista'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_cita = (int)($_GET['id'] ?? 0);
if (!$id_cita) {
    header('Location: dashboard.php');
    exit;
}

// Obtener datos de la cita
$sql = "SELECT 
            c.*,
            m.nombre_mascota,
            m.especie,
            m.raza,
            m.fecha_nacimiento,
            m.genero,
            m.foto,
            cl.nombre AS nombre_dueno,
            cl.ape_pat,
            cl.ape_mat,
            cl.telefono,
            cl.email,
            GROUP_CONCAT(s.nombre_servicio SEPARATOR ', ') AS servicios,
            IFNULL(SUM(dc.precio_fijado), 0) AS total_servicios
        FROM CITA c
        JOIN MASCOTA m ON c.id_mascota = m.id
        JOIN CLIENTE cl ON m.id_cliente = cl.id
        LEFT JOIN DETALLE_CITA dc ON c.id = dc.id_cita
        LEFT JOIN SERVICIO s ON dc.id_servicio = s.id
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

// ========== USAR FUNCIÓN total_servicios_cita ==========
$total_servicios_cita = $conn->query("SELECT total_servicios_cita($id_cita) as total")->fetch_assoc()['total'];

// Obtener productos ya agregados a esta cita
$sql_productos_cita = "SELECT COALESCE(SUM(dv.subtotal), 0) as total_productos 
                       FROM DETALLE_VENTA dv
                       JOIN VENTA_CITA vc ON vc.id_venta = dv.id_venta
                       WHERE vc.id_cita = ?";
                       
$stmt_prod = $conn->prepare($sql_productos_cita);
$stmt_prod->bind_param("i", $id_cita);
$stmt_prod->execute();
$total_productos = $stmt_prod->get_result()->fetch_assoc()['total_productos'] ?? 0;

// Verificar si la cita ya tiene pago
$pago_existente = $cita['pagada'] ? true : false;

// Calcular total general
$total_general = ($cita['total_servicios'] ?? 0) + $total_productos;

// Calcular edad de la mascota
$edad_mascota = null;
if ($cita['fecha_nacimiento']) {
    $sql_edad = "SELECT edad_mascota(?) as edad";
    $stmt_edad = $conn->prepare($sql_edad);
    $stmt_edad->bind_param("s", $cita['fecha_nacimiento']);
    $stmt_edad->execute();
    $result_edad = $stmt_edad->get_result();
    $row_edad = $result_edad->fetch_assoc();
    $edad_mascota = $row_edad['edad'];
}

// Verificar si la cita se puede cancelar
$sql_cancelable = "SELECT cita_cancelable(?) as cancelable";
$stmt_cancelable = $conn->prepare($sql_cancelable);
$stmt_cancelable->bind_param("i", $id_cita);
$stmt_cancelable->execute();
$result_cancelable = $stmt_cancelable->get_result();
$row_cancelable = $result_cancelable->fetch_assoc();
$cita_cancelable = $row_cancelable['cancelable'];

// Verificar si el veterinario solo puede ver sus citas asignadas
if ($_SESSION['rol'] === 'veterinario') {
    $sql_check = "SELECT id FROM ASIGNACION_CITA WHERE id_cita = ? AND id_empleado = ? AND rol_asignado = 'veterinario'";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $id_cita, $_SESSION['empleado_id']);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($result_check->num_rows == 0) {
        die("No tienes permiso para ver esta cita");
    }
}

// Obtener veterinarios y asistentes para las asignaciones
$veterinarios = [];
$asistentes = [];
$asignado = null;
$asignado_asistente = null;

// ========== NUEVO: Grooming (Estética) ==========
$groomers = [];
$asignado_groomer = null;
$tiene_servicios_estetica = false;

// Verificar si la cita tiene servicios de estética
$servicios_estetica = ['estética', 'baño', 'corte', 'cepillado', 'uñas', 'grooming'];
$servicios_cita = strtolower($cita['servicios'] ?? '');
foreach ($servicios_estetica as $keyword) {
    if (strpos($servicios_cita, $keyword) !== false) {
        $tiene_servicios_estetica = true;
        break;
    }
}
if (in_array($_SESSION['rol'], ['super_admin', 'admin']) && $tiene_servicios_estetica) {
    // Groomer actualmente asignado
    $sql_asignado_groomer = "SELECT e.id, e.nombre, e.ape_pat 
                            FROM ASIGNACION_CITA ac
                            JOIN EMPLEADO e ON ac.id_empleado = e.id
                            WHERE ac.id_cita = ? AND ac.rol_asignado = 'grooming'";
    $stmt_asig_groomer = $conn->prepare($sql_asignado_groomer);
    $stmt_asig_groomer->bind_param("i", $id_cita);
    $stmt_asig_groomer->execute();
    $asignado_groomer = $stmt_asig_groomer->get_result()->fetch_assoc();

    // Lista de groomers
    $sql_groomers = "SELECT e.id, e.nombre, e.ape_pat
                    FROM EMPLEADO e
                    JOIN USUARIO u ON e.id = u.id_empleado
                    WHERE e.puesto = 'grooming' AND e.activo = 1 AND u.activo = 1
                    ORDER BY e.nombre";
    $groomers = $conn->query($sql_groomers);
}
// ========== FIN DE NUEVO: Grooming (Estética) ==========

if (in_array($_SESSION['rol'], ['super_admin', 'admin'])) {
    // Veterinario actualmente asignado
    $sql_asignado = "SELECT e.id, e.nombre, e.ape_pat, e.especialidad 
                    FROM ASIGNACION_CITA ac
                    JOIN EMPLEADO e ON ac.id_empleado = e.id
                    WHERE ac.id_cita = ? AND ac.rol_asignado = 'veterinario'";
    $stmt_asig = $conn->prepare($sql_asignado);
    $stmt_asig->bind_param("i", $id_cita);
    $stmt_asig->execute();
    $asignado = $stmt_asig->get_result()->fetch_assoc();
    
    // Lista de veterinarios
    $sql_vets = "SELECT e.id, e.nombre, e.ape_pat, e.especialidad 
                FROM EMPLEADO e
                JOIN USUARIO u ON e.id = u.id_empleado
                WHERE e.puesto = 'veterinario' AND e.activo = 1 AND u.activo = 1
                ORDER BY e.nombre";
    $veterinarios = $conn->query($sql_vets);
    
    // Asistente actualmente asignado
    $sql_asignado_asistente = "SELECT e.id, e.nombre, e.ape_pat
                            FROM ASIGNACION_CITA ac
                            JOIN EMPLEADO e ON ac.id_empleado = e.id
                            WHERE ac.id_cita = ? AND ac.rol_asignado = 'asistente'";
    $stmt_asig_asistente = $conn->prepare($sql_asignado_asistente);
    $stmt_asig_asistente->bind_param("i", $id_cita);
    $stmt_asig_asistente->execute();
    $asignado_asistente = $stmt_asig_asistente->get_result()->fetch_assoc();
    
    // Lista de asistentes
    $sql_asistentes = "SELECT e.id, e.nombre, e.ape_pat
                    FROM EMPLEADO e
                    JOIN USUARIO u ON e.id = u.id_empleado
                    WHERE e.puesto = 'asistente' AND e.activo = 1 AND u.activo = 1
                    ORDER BY e.nombre";
    $asistentes = $conn->query($sql_asistentes);
}

// Obtener servicios disponibles
$sql_servicios = "SELECT id, nombre_servicio, precio FROM SERVICIO WHERE activo = 1 ORDER BY nombre_servicio";
$servicios_disponibles = $conn->query($sql_servicios);

// ========== BÚSQUEDA DE PRODUCTOS ==========
$busqueda_producto = $_GET['buscar_producto'] ?? '';
$productos = null;

if (!empty($busqueda_producto)) {
    // Usar el procedimiento buscar_producto
    $stmt = $conn->prepare("CALL buscar_producto(?)");
    $stmt->bind_param("s", $busqueda_producto);
    $stmt->execute();
    $productos = $stmt->get_result();
    $stmt->close();
    $conn->next_result();
} else {
    // Mostrar todos los productos activos con stock
    $sql_productos = "SELECT id, nombre, precio_venta, stock_actual 
                    FROM PRODUCTO 
                    WHERE activo = 1 AND stock_actual > 0 
                    ORDER BY nombre 
                    LIMIT 10";
    $productos = $conn->query($sql_productos);
}
// ========== FIN DE BÚSQUEDA DE PRODUCTOS ==========

// ========== GENERAR LISTA DE PRODUCTOS PARA EL MODAL ==========
$lista_productos = '';
if ($productos && $productos->num_rows > 0) {
    // Reiniciar el puntero del resultado
    if (method_exists($productos, 'data_seek')) {
        $productos->data_seek(0);
    }
    while($prod = $productos->fetch_assoc()) {
        $lista_productos .= '
        <div class="producto-item">
            <div class="producto-info">
                <div class="producto-nombre">' . htmlspecialchars($prod['nombre']) . '</div>
                <div class="producto-precio">$' . number_format($prod['precio_venta'], 2) . '</div>
                <div class="producto-stock">Stock: ' . $prod['stock_actual'] . ' unidades</div>
            </div>
            <div>
                <input type="number" id="cantidad_' . $prod['id'] . '" value="1" min="1" max="' . $prod['stock_actual'] . '" style="width: 60px; padding: 5px;">
                <button type="button" class="btn-agregar-producto" data-id="' . $prod['id'] . '" data-nombre="' . addslashes($prod['nombre']) . '" data-precio="' . $prod['precio_venta'] . '">+ Agregar</button>
            </div>
        </div>';
    }
} else {
    $lista_productos = '<p style="text-align: center; padding: 20px; color: #999;">No hay productos disponibles. ' . (!empty($busqueda_producto) ? 'Intenta con otra búsqueda.' : '') . '</p>';
}

// ========== FIN DE LISTA DE PRODUCTOS PARA EL MODAL ==========

// ========== GENERAR OPCIONES PARA SELECTS ==========

// Opciones para groomers
$groomers_options = '';
if (isset($groomers) && $groomers && $groomers->num_rows > 0) {
    $groomers->data_seek(0);
    while($groomer = $groomers->fetch_assoc()) {
        $groomers_options .= '<option value="' . $groomer['id'] . '">✂️ ' . htmlspecialchars($groomer['nombre'] . ' ' . $groomer['ape_pat']) . '</option>';
    }
} else {
    $groomers_options = '<option value="" disabled>No hay groomers disponibles</option>';
}

// Opciones para veterinarios (para usar en el HTML)
$veterinarios_options = '';
if (isset($veterinarios) && $veterinarios && $veterinarios->num_rows > 0) {
    $veterinarios->data_seek(0);
    while($vet = $veterinarios->fetch_assoc()) {
        $veterinarios_options .= '<option value="' . $vet['id'] . '">🩺 Dr/a. ' . htmlspecialchars($vet['nombre'] . ' ' . $vet['ape_pat']) . '</option>';
    }
} else {
    $veterinarios_options = '<option value="" disabled>No hay veterinarios disponibles</option>';
}

// Opciones para asistentes (para usar en el HTML)
$asistentes_options = '';
if (isset($asistentes) && $asistentes && $asistentes->num_rows > 0) {
    $asistentes->data_seek(0);
    while($asistente = $asistentes->fetch_assoc()) {
        $asistentes_options .= '<option value="' . $asistente['id'] . '">🩺 ' . htmlspecialchars($asistente['nombre'] . ' ' . $asistente['ape_pat']) . '</option>';
    }
} else {
    $asistentes_options = '<option value="" disabled>No hay asistentes disponibles</option>';
}

// Opciones para servicios
$servicios_options = '';
if ($servicios_disponibles && $servicios_disponibles->num_rows > 0) {
    $servicios_disponibles->data_seek(0);
    while($serv = $servicios_disponibles->fetch_assoc()) {
        $servicios_options .= '<option value="' . $serv['id'] . '" data-precio="' . $serv['precio'] . '">' . htmlspecialchars($serv['nombre_servicio']) . ' - $' . number_format($serv['precio'], 2) . '</option>';
    }
} else {
    $servicios_options = '<option value="" disabled>No hay servicios disponibles</option>';
}

// Variables para mensajes
$mensaje_sesion = $_SESSION['mensaje'] ?? '';
$error_sesion = $_SESSION['error'] ?? '';
unset($_SESSION['mensaje']);
unset($_SESSION['error']);

// ========== GENERAR TABLA DE PRODUCTOS DE LA CITA ==========
$tabla_productos = '';
$sql_productos_lista = "SELECT dv.*, p.nombre, p.precio_venta 
                       FROM DETALLE_VENTA dv
                       JOIN PRODUCTO p ON dv.id_producto = p.id
                       JOIN VENTA_CITA vc ON vc.id_venta = dv.id_venta
                       WHERE vc.id_cita = ?";
$stmt_lista = $conn->prepare($sql_productos_lista);
$stmt_lista->bind_param("i", $id_cita);
$stmt_lista->execute();
$productos_cita = $stmt_lista->get_result();

if ($productos_cita->num_rows > 0):
    $tabla_productos = '<table class="productos-cita-table">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio</th>
                <th>Subtotal</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>';
    while($prod = $productos_cita->fetch_assoc()):
        $tabla_productos .= '
            <tr>
                <td>' . htmlspecialchars($prod['nombre']) . '</span>
                <td>' . $prod['cantidad'] . '</span>
                <td>$' . number_format($prod['precio_unitario'], 2) . '</span>
                <td>$' . number_format($prod['subtotal'], 2) . '</span>
                <td style="display: flex; gap: 5px; align-items: center;">
                    <button type="button" class="btn-quitar-uno" onclick="quitarUnidadProducto(' . $id_cita . ', ' . $prod['id_producto'] . ', \'' . addslashes($prod['nombre']) . '\', ' . $prod['cantidad'] . ')" style="background: #ff9800; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;">➖ Quitar 1</button>
                    <button type="button" class="btn-eliminar-producto" onclick="eliminarProductoDeCita(' . $id_cita . ', ' . $prod['id_producto'] . ', \'' . addslashes($prod['nombre']) . '\')" style="background: #f44336; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;">🗑️ Eliminar todo</button>
                </td>
            </tr>';
    endwhile;
    $tabla_productos .= '</tbody></table>';
else:
    $tabla_productos = '<p class="sin-productos">No hay productos agregados a esta cita.</p>';
endif;

// ========== GENERAR MODAL DE PAGO ==========
// Servicios
$servicios_pago_html = '';
$sql_servicios_pago = "SELECT s.nombre_servicio, dc.precio_fijado 
                       FROM DETALLE_CITA dc
                       JOIN SERVICIO s ON dc.id_servicio = s.id
                       WHERE dc.id_cita = ?";
$stmt_serv = $conn->prepare($sql_servicios_pago);
$stmt_serv->bind_param("i", $id_cita);
$stmt_serv->execute();
$servicios_pago = $stmt_serv->get_result();

if ($servicios_pago->num_rows > 0):
    $servicios_pago_html = '<div style="overflow-x: auto;"><table style="width: 100%; border-collapse: collapse;">
        <thead><tr style="background: var(--primary); color: white;"><th style="padding: 8px; text-align: left;">Servicio</th><th style="padding: 8px; text-align: right;">Precio</th></tr></thead>
        <tbody>';
    while($serv = $servicios_pago->fetch_assoc()):
        $servicios_pago_html .= '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($serv['nombre_servicio']) . '</td>
        <td style="padding: 8px; text-align: right; border-bottom: 1px solid #eee;">$' . number_format($serv['precio_fijado'], 2) . '</td></tr>';
    endwhile;
    $servicios_pago_html .= '</tbody><tfoot><tr style="background: #f9f9f9; font-weight: bold;">
        <td style="padding: 8px;">Total Servicios:</td>
        <td style="padding: 8px; text-align: right;">$' . number_format($cita['total_servicios'], 2) . '</td></tr></tfoot></table></div>';
else:
    $servicios_pago_html = '<p>No hay servicios solicitados</p>';
endif;

// Productos
$productos_pago_html = '';
$sql_productos_pago = "SELECT p.nombre, dv.cantidad, dv.precio_unitario, dv.subtotal 
                       FROM DETALLE_VENTA dv
                       JOIN PRODUCTO p ON dv.id_producto = p.id
                       JOIN VENTA_CITA vc ON vc.id_venta = dv.id_venta
                       WHERE vc.id_cita = ?";
$stmt_prod_pago = $conn->prepare($sql_productos_pago);
$stmt_prod_pago->bind_param("i", $id_cita);
$stmt_prod_pago->execute();
$productos_pago = $stmt_prod_pago->get_result();

if ($productos_pago->num_rows > 0):
    $productos_pago_html = '<div style="overflow-x: auto;"><table style="width: 100%; border-collapse: collapse;">
        <thead><tr style="background: var(--primary); color: white;">
        <th style="padding: 8px; text-align: left;">Producto</th><th style="padding: 8px; text-align: center;">Cantidad</th>
        <th style="padding: 8px; text-align: right;">Precio</th><th style="padding: 8px; text-align: right;">Subtotal</th></tr></thead><tbody>';
    while($prod = $productos_pago->fetch_assoc()):
        $productos_pago_html .= '<tr><td style="padding: 8px; border-bottom: 1px solid #eee;">' . htmlspecialchars($prod['nombre']) . '</td>
        <td style="padding: 8px; text-align: center;">' . $prod['cantidad'] . '</td>
        <td style="padding: 8px; text-align: right;">$' . number_format($prod['precio_unitario'], 2) . '</td>
        <td style="padding: 8px; text-align: right;">$' . number_format($prod['subtotal'], 2) . '</td></tr>';
    endwhile;
    $productos_pago_html .= '</tbody><tfoot><tr style="background: #f9f9f9; font-weight: bold;">
        <td colspan="3" style="padding: 8px;">Total Productos:</td>
        <td style="padding: 8px; text-align: right;">$' . number_format($total_productos, 2) . '</td></tr></tfoot></table></div>';
else:
    $productos_pago_html = '<p>No hay productos agregados</p>';
endif;
?>