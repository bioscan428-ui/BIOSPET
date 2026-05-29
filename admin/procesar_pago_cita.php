<?php
// admin/procesar_pago_cita.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_cita = (int)($_POST['id_cita'] ?? 0);
$metodo_pago = $_POST['metodo_pago'] ?? '';
$recibido = isset($_POST['recibido']) ? (float)$_POST['recibido'] : null;
$vuelto = isset($_POST['vuelto']) ? (float)$_POST['vuelto'] : null;

if (!$id_cita || !$metodo_pago) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Verificar que la cita existe y no está pagada
$sql_cita = "SELECT c.*, cl.id as id_cliente, cl.nombre as cliente_nombre, cl.telefono 
             FROM CITA c
             JOIN MASCOTA m ON c.id_mascota = m.id
             JOIN CLIENTE cl ON m.id_cliente = cl.id
             WHERE c.id = ?";
$stmt = $conn->prepare($sql_cita);
$stmt->bind_param("i", $id_cita);
$stmt->execute();
$cita = $stmt->get_result()->fetch_assoc();

if (!$cita) {
    echo json_encode(['success' => false, 'message' => 'Cita no encontrada']);
    exit;
}

if ($cita['pagada']) {
    echo json_encode(['success' => false, 'message' => 'Esta cita ya está pagada']);
    exit;
}

// Obtener servicios de la cita y calcular total
$sql_servicios = "SELECT SUM(dc.precio_fijado) as total_servicios 
                  FROM DETALLE_CITA dc 
                  WHERE dc.id_cita = ?";
$stmt_serv = $conn->prepare($sql_servicios);
$stmt_serv->bind_param("i", $id_cita);
$stmt_serv->execute();
$total_servicios = $stmt_serv->get_result()->fetch_assoc()['total_servicios'] ?? 0;

// Verificar si ya existe una venta para esta cita
$sql_venta_existente = "SELECT vc.id_venta 
                        FROM VENTA_CITA vc 
                        WHERE vc.id_cita = ?";
$stmt_venta_exist = $conn->prepare($sql_venta_existente);
$stmt_venta_exist->bind_param("i", $id_cita);
$stmt_venta_exist->execute();
$venta_existente = $stmt_venta_exist->get_result()->fetch_assoc();

$conn->begin_transaction();

try {
    $id_venta = null;
    
    // Si ya existe una venta para esta cita, usarla; si no, crear una nueva
    if ($venta_existente) {
        $id_venta = $venta_existente['id_venta'];
        
        // Actualizar la venta existente
        $sql_update_venta = "UPDATE VENTA SET 
                            metodo_pago = ?, 
                            estado = 'completada',
                            total = ?
                            WHERE id = ?";
        $stmt_update_venta = $conn->prepare($sql_update_venta);
        $total_general = $total_servicios;
        
        // Calcular total de productos ya existentes
        $sql_sum_productos = "SELECT SUM(subtotal) as total_productos 
                             FROM DETALLE_VENTA 
                             WHERE id_venta = ?";
        $stmt_sum = $conn->prepare($sql_sum_productos);
        $stmt_sum->bind_param("i", $id_venta);
        $stmt_sum->execute();
        $productos_total = $stmt_sum->get_result()->fetch_assoc()['total_productos'] ?? 0;
        $total_general += $productos_total;
        
        $stmt_update_venta->bind_param("sdi", $metodo_pago, $total_general, $id_venta);
        $stmt_update_venta->execute();
    } else {
        // Crear una nueva venta
        $id_cliente = $cita['id_cliente'];
        $empleado_id = $_SESSION['empleado_id'] ?? null;
        $notas = "Pago de cita #" . $id_cita;
        $total_general = $total_servicios;
        
        $sql_venta = "INSERT INTO VENTA (id_cliente, id_empleado, subtotal, iva, total, metodo_pago, estado, notas) 
                      VALUES (?, ?, ?, 0, ?, ?, 'completada', ?)";
        $stmt_venta = $conn->prepare($sql_venta);
        $subtotal = $total_servicios;
        $stmt_venta->bind_param("iiddss", $id_cliente, $empleado_id, $subtotal, $total_general, $metodo_pago, $notas);
        $stmt_venta->execute();
        $id_venta = $conn->insert_id;
        
        // Relacionar venta con cita
        $sql_relacion = "INSERT INTO VENTA_CITA (id_cita, id_venta) VALUES (?, ?)";
        $stmt_rel = $conn->prepare($sql_relacion);
        $stmt_rel->bind_param("ii", $id_cita, $id_venta);
        $stmt_rel->execute();
    }
    
    // NOTA: NO volver a insertar productos porque ya existen
    // Los productos ya se agregaron cuando se añadieron a la cita
    // Solo actualizar el estado de la venta
    
    // Actualizar la cita como pagada
    $sql_update_cita = "UPDATE CITA SET pagada = 1, metodo_pago = ?, pago_fecha_registro = NOW(), pago_registrado_por = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update_cita);
    $empleado_id = $_SESSION['empleado_id'] ?? null;
    $stmt_update->bind_param("sii", $metodo_pago, $empleado_id, $id_cita);
    $stmt_update->execute();
    
    // Registrar en auditoría de pagos
    $sql_auditoria = "INSERT INTO AUDITORIA_PAGOS (id_cita, monto, metodo_pago, id_empleado, ip_usuario, accion) 
                      VALUES (?, ?, ?, ?, ?, 'pago')";
    $stmt_aud = $conn->prepare($sql_auditoria);
    $ip_usuario = $_SERVER['REMOTE_ADDR'] ?? null;
    $total_general_final = $total_servicios;
    
    // Sumar productos si ya existen
    $sql_get_total = "SELECT SUM(subtotal) as total FROM DETALLE_VENTA WHERE id_venta = ?";
    $stmt_get = $conn->prepare($sql_get_total);
    $stmt_get->bind_param("i", $id_venta);
    $stmt_get->execute();
    $productos_sum = $stmt_get->get_result()->fetch_assoc()['total'] ?? 0;
    $total_general_final += $productos_sum;
    
    $stmt_aud->bind_param("idsis", $id_cita, $total_general_final, $metodo_pago, $empleado_id, $ip_usuario);
    $stmt_aud->execute();
    
    $conn->commit();
    
    // Guardar información del ticket en sesión
    $_SESSION['ultimo_ticket'] = [
        'id_venta' => $id_venta,
        'id_cita' => $id_cita,
        'total' => $total_general_final,
        'metodo_pago' => $metodo_pago,
        'recibido' => $recibido,
        'vuelto' => $vuelto
    ];
    
    echo json_encode([
        'success' => true, 
        'message' => 'Pago registrado correctamente',
        'id_venta' => $id_venta,
        'id_cita' => $id_cita,
        'total' => $total_general_final,
        'recibido' => $recibido,
        'vuelto' => $vuelto
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>