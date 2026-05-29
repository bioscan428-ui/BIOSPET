<?php
// admin/actualizar_pago_cita.php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_cita = (int)($_POST['id_cita'] ?? 0);
$metodo_pago = $_POST['metodo_pago'] ?? '';
$referencia = $_POST['referencia'] ?? null;
$recibido = isset($_POST['recibido']) ? (float)$_POST['recibido'] : null;
$vuelto = isset($_POST['vuelto']) ? (float)$_POST['vuelto'] : null;

if (!$id_cita || !$metodo_pago) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    // Verificar que la cita existe y no está pagada
    $sql_cita = "SELECT c.*, cl.id as id_cliente 
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
    
    if ($cita['pagada'] == 1) {
        echo json_encode(['success' => false, 'message' => 'Esta cita ya está pagada']);
        exit;
    }
    
    // Calcular total de servicios
    $sql_servicios = "SELECT SUM(dc.precio_fijado) as total_servicios FROM DETALLE_CITA dc WHERE dc.id_cita = ?";
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
    
    $empleado_id = $_SESSION['empleado_id'] ?? null;
    $ip_usuario = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    $conn->begin_transaction();
    
    $id_venta = null;
    $total_productos = 0;
    
    if ($venta_existente) {
        // ========== USAR VENTA EXISTENTE ==========
        $id_venta = $venta_existente['id_venta'];
        
        // Calcular total de productos de la venta existente
        $sql_productos = "SELECT COALESCE(SUM(subtotal), 0) as total_productos 
                          FROM DETALLE_VENTA 
                          WHERE id_venta = ?";
        $stmt_prod = $conn->prepare($sql_productos);
        $stmt_prod->bind_param("i", $id_venta);
        $stmt_prod->execute();
        $total_productos = $stmt_prod->get_result()->fetch_assoc()['total_productos'] ?? 0;
        
        $total_general = $total_servicios + $total_productos;
        
        // ACTUALIZAR la venta existente (NO crear nueva)
        $sql_update_venta = "UPDATE VENTA SET 
                            metodo_pago = ?, 
                            estado = 'completada',
                            subtotal = ?,
                            total = ?
                            WHERE id = ?";
        $stmt_update_venta = $conn->prepare($sql_update_venta);
        $stmt_update_venta->bind_param("sddi", $metodo_pago, $total_general, $total_general, $id_venta);
        $stmt_update_venta->execute();
        
        error_log("Venta existente ACTUALIZADA ID: $id_venta, Total: $total_general");
        
    } else {
        // ========== CREAR NUEVA VENTA (solo si no existe) ==========
        $total_productos = 0;
        $total_general = $total_servicios;
        
        $sql_venta = "INSERT INTO VENTA (id_cliente, id_empleado, subtotal, iva, total, metodo_pago, estado, notas) 
                      VALUES (?, ?, ?, 0, ?, ?, 'completada', ?)";
        $stmt_venta = $conn->prepare($sql_venta);
        $notas = "Pago de cita #" . $id_cita;
        $stmt_venta->bind_param("iiddss", $cita['id_cliente'], $empleado_id, $total_general, $total_general, $metodo_pago, $notas);
        $stmt_venta->execute();
        $id_venta = $conn->insert_id;
        
        // Relacionar venta con cita
        $sql_relacion = "INSERT INTO VENTA_CITA (id_cita, id_venta) VALUES (?, ?)";
        $stmt_rel = $conn->prepare($sql_relacion);
        $stmt_rel->bind_param("ii", $id_cita, $id_venta);
        $stmt_rel->execute();
        
        error_log("Nueva venta CREADA ID: $id_venta");
    }
    
    // ⚠️ IMPORTANTE: NO volver a insertar productos en DETALLE_VENTA
    // Los productos ya existen en DETALLE_VENTA desde que se agregaron a la cita
    
    // Registrar pago en auditoría
    $sql_auditoria = "INSERT INTO AUDITORIA_PAGOS (id_cita, monto, metodo_pago, referencia, id_empleado, ip_usuario, accion) 
                      VALUES (?, ?, ?, ?, ?, ?, 'pago')";
    $stmt_audit = $conn->prepare($sql_auditoria);
    $total_general_final = $total_servicios + $total_productos;
    $stmt_audit->bind_param("idssis", $id_cita, $total_general_final, $metodo_pago, $referencia, $empleado_id, $ip_usuario);
    $stmt_audit->execute();
    
    // Actualizar cita como pagada
    $sql_update = "UPDATE CITA SET pagada = 1, metodo_pago = ?, referencia_pago = ?,
                    pago_registrado_por = ?, pago_fecha_registro = NOW(), pago_ip_usuario = ?
                   WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ssisi", $metodo_pago, $referencia, $empleado_id, $ip_usuario, $id_cita);
    $stmt_update->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Pago registrado exitosamente',
        'id_venta' => $id_venta,
        'id_cita' => $id_cita,
        'total' => $total_general_final,
        'recibido' => $recibido,
        'vuelto' => $vuelto
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("ERROR en actualizar_pago_cita: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>